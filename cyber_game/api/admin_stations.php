<?php
/**
 * Admin Stations & Session Control API
 * UC12: Quản lý phiên chơi & Sơ đồ phòng máy (Mở phiên, Chuyển máy, Cưỡng chế ngắt)
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$staff = checkStaffAuth();
$maNhanVien = $staff['maNhanVien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Tự động quét và giải phóng các máy đã hết tiền ngay khi admin truy cập hoặc thao tác
autoCloseExpiredSessions($pdo);

switch ($action) {
    case 'get_stations':
        // Lấy toàn bộ máy kèm trạng thái và phiên đang hoạt động nếu có
        $sql = "
            SELECT mt.*, lm.tenLoai, lm.moTa, bg.maBangGia, bg.donGiaGio,
                   pc.maPhien, pc.thoiGianBatDau, pc.tongThoiGianPhut,
                   tk.maTaiKhoan, tk.tenDangNhap, tk.soDu,
                   kc.hoTen, kc.soDienThoai,
                   sd.maSuDung, sd.thoiGianBatDau as sdThoiGianBatDau
            FROM MAY_TRAM mt
            JOIN LOAI_MAY lm ON mt.maLoaiMay = lm.maLoaiMay
            JOIN BANG_GIA bg ON mt.maLoaiMay = bg.maLoaiMay
            LEFT JOIN CHI_TIET_SU_DUNG_MAY sd ON mt.maMay = sd.maMay AND sd.thoiGianKetThuc IS NULL
            LEFT JOIN PHIEN_CHOI pc ON sd.maPhien = pc.maPhien AND pc.trangThai = 'Đang hoạt động'
            LEFT JOIN TAI_KHOAN tk ON pc.maTaiKhoan = tk.maTaiKhoan
            LEFT JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            ORDER BY mt.maMay ASC
        ";
        $stmt = $pdo->query($sql);
        $stations = $stmt->fetchAll();

        $now = new DateTime();
        foreach ($stations as &$s) {
            if ($s['trangThai'] === 'Đang chơi' && !empty($s['thoiGianBatDau'])) {
                $start = new DateTime($s['thoiGianBatDau']);
                $minutes = max(1, floor(($now->getTimestamp() - $start->getTimestamp()) / 60));
                $s['phutDaChoi'] = $minutes;
                $s['chiPhiTamTinh'] = round(($minutes / 60) * $s['donGiaGio']);
                $s['soDuConLai'] = max(0, $s['soDu'] - $s['chiPhiTamTinh']);
                $s['phutConLai'] = $s['donGiaGio'] > 0 ? floor(($s['soDuConLai'] / $s['donGiaGio']) * 60) : 0;
            } else {
                $s['phutDaChoi'] = 0;
                $s['chiPhiTamTinh'] = 0;
                $s['soDuConLai'] = $s['soDu'] ?? 0;
                $s['phutConLai'] = 0;
            }
        }

        // Thống kê nhanh
        $stats = [
            'total' => count($stations),
            'playing' => count(array_filter($stations, fn($s) => $s['trangThai'] === 'Đang chơi')),
            'available' => count(array_filter($stations, fn($s) => $s['trangThai'] === 'Trống')),
            'maintenance' => count(array_filter($stations, fn($s) => $s['trangThai'] === 'Bảo trì'))
        ];

        jsonResponse(true, 'Sơ đồ phòng máy', [
            'stats' => $stats,
            'stations' => $stations
        ]);
        break;

    case 'open_session':
        $maMay = trim($_POST['maMay'] ?? '');
        $identifier = trim($_POST['identifier'] ?? ''); // Tên đăng nhập hoặc SĐT

        if (empty($maMay) || empty($identifier)) {
            jsonResponse(false, 'Vui lòng chọn máy và nhập Tên đăng nhập hoặc Số điện thoại khách hàng.');
        }

        // Kiểm tra máy
        $stmtM = $pdo->prepare("
            SELECT mt.*, bg.maBangGia, bg.donGiaGio 
            FROM MAY_TRAM mt 
            JOIN BANG_GIA bg ON mt.maLoaiMay = bg.maLoaiMay 
            WHERE mt.maMay = ?
        ");
        $stmtM->execute([$maMay]);
        $station = $stmtM->fetch();

        if (!$station) {
            jsonResponse(false, 'Máy trạm không tồn tại.');
        }

        if ($station['trangThai'] !== 'Trống') {
            jsonResponse(false, "Máy {$maMay} hiện không khả dụng (Trạng thái: {$station['trangThai']}).");
        }

        // Tìm khách hàng
        $stmtTK = $pdo->prepare("
            SELECT tk.*, kc.hoTen 
            FROM TAI_KHOAN tk
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            WHERE tk.tenDangNhap = ? OR kc.soDienThoai = ?
        ");
        $stmtTK->execute([$identifier, $identifier]);
        $account = $stmtTK->fetch();

        if (!$account) {
            jsonResponse(false, 'Không tìm thấy tài khoản khách hàng khớp với thông tin cung cấp.');
        }

        if ($account['trangThai'] === 'Đang khóa') {
            jsonResponse(false, 'Tài khoản khách hàng đang bị khóa.');
        }

        if ($account['soDu'] < 2000) {
            jsonResponse(false, 'Số dư khách hàng không đủ (Dưới 2.000 VNĐ). Vui lòng nạp tiền trước.');
        }

        // Kiểm tra khách có đang chơi ở máy khác không
        $stmtActive = $pdo->prepare("
            SELECT pc.maPhien, sd.maMay 
            FROM PHIEN_CHOI pc
            JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL
            WHERE pc.maTaiKhoan = ? AND pc.trangThai = 'Đang hoạt động'
        ");
        $stmtActive->execute([$account['maTaiKhoan']]);
        $activeSession = $stmtActive->fetch();

        if ($activeSession) {
            jsonResponse(false, "Tài khoản đang có phiên chơi tại máy {$activeSession['maMay']}. Vui lòng chuyển máy hoặc kết thúc phiên cũ.");
        }

        $pdo->beginTransaction();
        try {
            $maPhien = generateNextCode($pdo, 'PHIEN_CHOI', 'maPhien', 'PC', 4);
            $stmtInsertPC = $pdo->prepare("
                INSERT INTO PHIEN_CHOI (maPhien, thoiGianBatDau, tongThoiGianPhut, trangThai, chiPhi, maTaiKhoan)
                VALUES (?, NOW(), 0, 'Đang hoạt động', 0, ?)
            ");
            $stmtInsertPC->execute([$maPhien, $account['maTaiKhoan']]);

            $maSuDung = generateNextCode($pdo, 'CHI_TIET_SU_DUNG_MAY', 'maSuDung', 'SD', 4);
            $stmtInsertSD = $pdo->prepare("
                INSERT INTO CHI_TIET_SU_DUNG_MAY (maSuDung, maPhien, maMay, maBangGia, thoiGianBatDau, donGiaApDung, thanhTien)
                VALUES (?, ?, ?, ?, NOW(), ?, 0)
            ");
            $stmtInsertSD->execute([$maSuDung, $maPhien, $maMay, $station['maBangGia'], $station['donGiaGio']]);

            $stmtUpMay = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Đang chơi' WHERE maMay = ?");
            $stmtUpMay->execute([$maMay]);

            logSystemAction($pdo, $maNhanVien, 'Mở phiên chơi tại quầy', 'PHIEN_CHOI', $maPhien, 'Trống', "Mở máy {$maMay} cho tài khoản {$account['tenDangNhap']}");

            $pdo->commit();
            jsonResponse(true, "Mở máy {$maMay} thành công cho khách hàng {$account['hoTen']} ({$account['tenDangNhap']})!");
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi mở phiên chơi: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'transfer_station':
        // UC12: Chuyển máy
        $maMayCu = trim($_POST['maMayCu'] ?? '');
        $maMayMoi = trim($_POST['maMayMoi'] ?? '');

        if (empty($maMayCu) || empty($maMayMoi)) {
            jsonResponse(false, 'Vui lòng cung cấp mã máy hiện tại và máy muốn chuyển đến.');
        }

        if ($maMayCu === $maMayMoi) {
            jsonResponse(false, 'Máy đích phải khác máy hiện tại.');
        }

        // Kiểm tra máy đích
        $stmtMayMoi = $pdo->prepare("
            SELECT mt.*, bg.maBangGia, bg.donGiaGio 
            FROM MAY_TRAM mt 
            JOIN BANG_GIA bg ON mt.maLoaiMay = bg.maLoaiMay 
            WHERE mt.maMay = ?
        ");
        $stmtMayMoi->execute([$maMayMoi]);
        $destStation = $stmtMayMoi->fetch();

        if (!$destStation || $destStation['trangThai'] !== 'Trống') {
            jsonResponse(false, "Máy đích {$maMayMoi} hiện không khả dụng (Trạng thái: " . ($destStation['trangThai'] ?? 'Không tồn tại') . ").");
        }

        // Lấy chi tiết phiên chơi của máy cũ
        $stmtOldSD = $pdo->prepare("
            SELECT sd.*, pc.maTaiKhoan, tk.tenDangNhap 
            FROM CHI_TIET_SU_DUNG_MAY sd
            JOIN PHIEN_CHOI pc ON sd.maPhien = pc.maPhien
            JOIN TAI_KHOAN tk ON pc.maTaiKhoan = tk.maTaiKhoan
            WHERE sd.maMay = ? AND sd.thoiGianKetThuc IS NULL AND pc.trangThai = 'Đang hoạt động'
        ");
        $stmtOldSD->execute([$maMayCu]);
        $oldSD = $stmtOldSD->fetch();

        if (!$oldSD) {
            jsonResponse(false, "Không tìm thấy phiên chơi đang hoạt động tại máy {$maMayCu}.");
        }

        $pdo->beginTransaction();
        try {
            // Chốt tiền máy cũ
            $now = new DateTime();
            $startOld = new DateTime($oldSD['thoiGianBatDau']);
            $diffMinutes = max(1, round(($now->getTimestamp() - $startOld->getTimestamp()) / 60));
            $oldCost = round(($diffMinutes / 60) * $oldSD['donGiaApDung']);

            $stmtCloseOldSD = $pdo->prepare("
                UPDATE CHI_TIET_SU_DUNG_MAY 
                SET thoiGianKetThuc = NOW(), thanhTien = ?
                WHERE maSuDung = ?
            ");
            $stmtCloseOldSD->execute([$oldCost, $oldSD['maSuDung']]);

            // Mở chi tiết sử dụng cho máy mới (giữ nguyên maPhien)
            $maSuDungMoi = generateNextCode($pdo, 'CHI_TIET_SU_DUNG_MAY', 'maSuDung', 'SD', 4);
            $stmtInsertNewSD = $pdo->prepare("
                INSERT INTO CHI_TIET_SU_DUNG_MAY (maSuDung, maPhien, maMay, maBangGia, thoiGianBatDau, donGiaApDung, thanhTien)
                VALUES (?, ?, ?, ?, NOW(), ?, 0)
            ");
            $stmtInsertNewSD->execute([$maSuDungMoi, $oldSD['maPhien'], $maMayMoi, $destStation['maBangGia'], $destStation['donGiaGio']]);

            // Cập nhật trạng thái 2 máy
            $stmtFreeOld = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Trống' WHERE maMay = ?");
            $stmtFreeOld->execute([$maMayCu]);

            $stmtOccupyNew = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Đang chơi' WHERE maMay = ?");
            $stmtOccupyNew->execute([$maMayMoi]);

            logSystemAction(
                $pdo, 
                $maNhanVien, 
                'Chuyển máy', 
                'PHIEN_CHOI', 
                $oldSD['maPhien'], 
                "Máy cũ: {$maMayCu}", 
                "Chuyển sang {$maMayMoi} cho tài khoản {$oldSD['tenDangNhap']}"
            );

            $pdo->commit();
            jsonResponse(true, "Đã chuyển thành công tài khoản {$oldSD['tenDangNhap']} từ máy {$maMayCu} sang máy {$maMayMoi}!");
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi trong quá trình chuyển máy: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'stop_session':
        // UC12: Cưỡng chế kết thúc phiên
        $maMay = trim($_POST['maMay'] ?? '');

        if (empty($maMay)) {
            jsonResponse(false, 'Thiếu mã máy.');
        }

        $stmtSD = $pdo->prepare("
            SELECT sd.*, pc.maPhien, pc.thoiGianBatDau as phienBatDau, pc.chiPhi as phienChiPhi, pc.maTaiKhoan, tk.tenDangNhap 
            FROM CHI_TIET_SU_DUNG_MAY sd
            JOIN PHIEN_CHOI pc ON sd.maPhien = pc.maPhien
            JOIN TAI_KHOAN tk ON pc.maTaiKhoan = tk.maTaiKhoan
            WHERE sd.maMay = ? AND sd.thoiGianKetThuc IS NULL AND pc.trangThai = 'Đang hoạt động'
        ");
        $stmtSD->execute([$maMay]);
        $sd = $stmtSD->fetch();

        if (!$sd) {
            jsonResponse(false, "Máy {$maMay} không có phiên chơi nào đang chạy.");
        }

        $pdo->beginTransaction();
        try {
            $now = new DateTime();
            $startSD = new DateTime($sd['thoiGianBatDau']);
            $diffMinutes = max(1, round(($now->getTimestamp() - $startSD->getTimestamp()) / 60));
            $sdCost = round(($diffMinutes / 60) * $sd['donGiaApDung']);

            $stmtCloseSD = $pdo->prepare("
                UPDATE CHI_TIET_SU_DUNG_MAY 
                SET thoiGianKetThuc = NOW(), thanhTien = ?
                WHERE maSuDung = ?
            ");
            $stmtCloseSD->execute([$sdCost, $sd['maSuDung']]);

            // Tổng chi phí
            $stmtSum = $pdo->prepare("SELECT SUM(thanhTien) as tongChiPhi FROM CHI_TIET_SU_DUNG_MAY WHERE maPhien = ?");
            $stmtSum->execute([$sd['maPhien']]);
            $totalCost = $stmtSum->fetch()['tongChiPhi'] ?? 0;

            $startPC = new DateTime($sd['phienBatDau']);
            $totalMinutes = max(1, round(($now->getTimestamp() - $startPC->getTimestamp()) / 60));

            $stmtClosePC = $pdo->prepare("
                UPDATE PHIEN_CHOI 
                SET thoiGianKetThuc = NOW(), tongThoiGianPhut = ?, chiPhi = ?, trangThai = 'Đã kết thúc'
                WHERE maPhien = ?
            ");
            $stmtClosePC->execute([$totalMinutes, $totalCost, $sd['maPhien']]);

            // Trừ phần chênh lệch chi phí còn lại vào số dư tài khoản (tránh trừ trùng lặp với cước đã trừ theo phút)
            $alreadyDeducted = (float)($sd['phienChiPhi'] ?? 0);
            $remainingDelta = max(0, $totalCost - $alreadyDeducted);
            if ($remainingDelta > 0) {
                $stmtDeduct = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = GREATEST(0, soDu - ?) WHERE maTaiKhoan = ?");
                $stmtDeduct->execute([$remainingDelta, $sd['maTaiKhoan']]);
            }

            // Trả máy
            $stmtFree = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Trống' WHERE maMay = ?");
            $stmtFree->execute([$maMay]);

            logSystemAction(
                $pdo, 
                $maNhanVien, 
                'Cưỡng chế ngắt phiên', 
                'PHIEN_CHOI', 
                $sd['maPhien'], 
                "Đang chơi tại {$maMay}", 
                "Thu ngân ngắt phiên tài khoản {$sd['tenDangNhap']}. Thời gian: {$totalMinutes}p, Chi phí: {$totalCost}đ"
            );

            $pdo->commit();
            jsonResponse(true, "Đã ngắt phiên chơi tại máy {$maMay} thành công. Chốt chi phí: " . number_format($totalCost, 0, ',', '.') . " VNĐ.");
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi kết thúc phiên: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'toggle_maintenance':
        $maMay = trim($_POST['maMay'] ?? '');
        $stmt = $pdo->prepare("SELECT trangThai FROM MAY_TRAM WHERE maMay = ?");
        $stmt->execute([$maMay]);
        $curr = $stmt->fetch();

        if (!$curr) {
            jsonResponse(false, 'Máy trạm không tồn tại.');
        }

        if ($curr['trangThai'] === 'Đang chơi') {
            jsonResponse(false, 'Không thể chuyển sang bảo trì khi máy đang có khách chơi. Vui lòng ngắt phiên trước.');
        }

        $newStatus = ($curr['trangThai'] === 'Bảo trì') ? 'Trống' : 'Bảo trì';
        $stmtUp = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = ? WHERE maMay = ?");
        $stmtUp->execute([$newStatus, $maMay]);

        logSystemAction($pdo, $maNhanVien, 'Đổi trạng thái máy', 'MAY_TRAM', $maMay, $curr['trangThai'], $newStatus);

        jsonResponse(true, "Đã đổi trạng thái máy {$maMay} sang '{$newStatus}'.", ['trangThai' => $newStatus]);
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
