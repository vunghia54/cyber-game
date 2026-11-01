<?php
/**
 * Admin Customers API
 * UC9: Quản lý tài khoản khách hàng
 * UC10: Tìm kiếm & tra cứu khách hàng (Search-as-you-type)
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$staff = checkStaffAuth();
$maNhanVien = $staff['maNhanVien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'list_customers':
        $keyword = trim($_GET['keyword'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $group = trim($_GET['group'] ?? '');

        $sql = "
            SELECT tk.maTaiKhoan, tk.tenDangNhap, tk.soDu, tk.nhomKhachHang, tk.ngayTao, tk.trangThai as trangThaiTK,
                   kc.maKhachChoi, kc.hoTen, kc.ngaySinh, kc.soDienThoai, kc.email, kc.gioiTinh,
                   (SELECT sd.maMay 
                    FROM PHIEN_CHOI pc 
                    JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL 
                    WHERE pc.maTaiKhoan = tk.maTaiKhoan AND pc.trangThai = 'Đang hoạt động' LIMIT 1) as dangNgoiMay
            FROM TAI_KHOAN tk
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            WHERE 1=1
        ";
        $params = [];

        if (!empty($keyword)) {
            $sql .= " AND (kc.maKhachChoi LIKE ? OR tk.tenDangNhap LIKE ? OR kc.hoTen LIKE ? OR kc.soDienThoai LIKE ? OR kc.email LIKE ? OR tk.maTaiKhoan LIKE ?)";
            $term = "%{$keyword}%";
            $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
        }

        if (!empty($status)) {
            $sql .= " AND tk.trangThai = ?";
            $params[] = $status;
        }

        if (!empty($group)) {
            $sql .= " AND tk.nhomKhachHang = ?";
            $params[] = $group;
        }

        $sql .= " ORDER BY tk.maTaiKhoan DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();

        jsonResponse(true, 'Danh sách khách hàng', $customers);
        break;

    case 'get_customer':
        $maTaiKhoan = trim($_GET['maTaiKhoan'] ?? '');

        $stmt = $pdo->prepare("
            SELECT tk.*, kc.hoTen, kc.ngaySinh, kc.soDienThoai, kc.email, kc.gioiTinh, kc.trangThai as trangThaiKC,
                   (SELECT sd.maMay 
                    FROM PHIEN_CHOI pc 
                    JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL 
                    WHERE pc.maTaiKhoan = tk.maTaiKhoan AND pc.trangThai = 'Đang hoạt động' LIMIT 1) as dangNgoiMay
            FROM TAI_KHOAN tk
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            WHERE tk.maTaiKhoan = ?
        ");
        $stmt->execute([$maTaiKhoan]);
        $customer = $stmt->fetch();

        if (!$customer) {
            jsonResponse(false, 'Không tìm thấy tài khoản khách hàng.');
        }

        // Lịch sử 5 lần nạp tiền gần nhất
        $stmtGD = $pdo->prepare("SELECT * FROM GIAO_DICH_NAP_TIEN WHERE maTaiKhoan = ? ORDER BY thoiGianTao DESC LIMIT 5");
        $stmtGD->execute([$maTaiKhoan]);
        $customer['recentTopups'] = $stmtGD->fetchAll();

        // Lịch sử 5 phiên gần nhất
        $stmtPC = $pdo->prepare("SELECT * FROM PHIEN_CHOI WHERE maTaiKhoan = ? ORDER BY thoiGianBatDau DESC LIMIT 5");
        $stmtPC->execute([$maTaiKhoan]);
        $customer['recentSessions'] = $stmtPC->fetchAll();

        jsonResponse(true, 'Chi tiết thông tin khách hàng', $customer);
        break;

    case 'update_customer':
        $maTaiKhoan = trim($_POST['maTaiKhoan'] ?? '');
        $hoTen = trim($_POST['hoTen'] ?? '');
        $soDienThoai = trim($_POST['soDienThoai'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nhomKhachHang = trim($_POST['nhomKhachHang'] ?? 'Hội viên');
        $ngaySinh = $_POST['ngaySinh'] ?? '';
        $gioiTinh = $_POST['gioiTinh'] ?? 'Nam';

        if (empty($maTaiKhoan) || empty($hoTen) || empty($soDienThoai) || empty($email)) {
            jsonResponse(false, 'Vui lòng điền đầy đủ các thông tin.');
        }

        if (!preg_match('/^[0-9]{10,11}$/', $soDienThoai)) {
            jsonResponse(false, 'Số điện thoại không đúng định dạng (10-11 chữ số).');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Địa chỉ email không đúng định dạng.');
        }

        if (!empty($ngaySinh) && (strtotime($ngaySinh) === false || strtotime($ngaySinh) > time())) {
            jsonResponse(false, 'Ngày sinh không hợp lệ (không thể chọn ngày ở tương lai).');
        }

        $stmtTK = $pdo->prepare("SELECT maKhachChoi, nhomKhachHang FROM TAI_KHOAN WHERE maTaiKhoan = ?");
        $stmtTK->execute([$maTaiKhoan]);
        $tk = $stmtTK->fetch();

        if (!$tk) {
            jsonResponse(false, 'Tài khoản không tồn tại.');
        }

        // Kiểm tra trùng SĐT/Email
        $stmtDup = $pdo->prepare("SELECT maKhachChoi FROM KHACH_CHOI WHERE (soDienThoai = ? OR email = ?) AND maKhachChoi != ?");
        $stmtDup->execute([$soDienThoai, $email, $tk['maKhachChoi']]);
        if ($stmtDup->fetch()) {
            jsonResponse(false, 'Số điện thoại hoặc email đã trùng với khách hàng khác.');
        }

        $pdo->beginTransaction();
        try {
            $stmtUpKC = $pdo->prepare("
                UPDATE KHACH_CHOI 
                SET hoTen = ?, soDienThoai = ?, email = ?, ngaySinh = ?, gioiTinh = ?
                WHERE maKhachChoi = ?
            ");
            $stmtUpKC->execute([$hoTen, $soDienThoai, $email, $ngaySinh, $gioiTinh, $tk['maKhachChoi']]);

            $stmtUpTK = $pdo->prepare("UPDATE TAI_KHOAN SET nhomKhachHang = ? WHERE maTaiKhoan = ?");
            $stmtUpTK->execute([$nhomKhachHang, $maTaiKhoan]);

            logSystemAction(
                $pdo, 
                $maNhanVien, 
                'Cập nhật thông tin khách hàng', 
                'TAI_KHOAN', 
                $maTaiKhoan, 
                "Nhóm cũ: {$tk['nhomKhachHang']}", 
                "Họ tên: {$hoTen}, SĐT: {$soDienThoai}, Nhóm mới: {$nhomKhachHang}"
            );

            $pdo->commit();
            jsonResponse(true, 'Cập nhật thông tin khách hàng thành công!');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi cập nhật: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'toggle_status':
        // UC9 Luồng phụ 3a: Khóa hoặc mở khóa tài khoản
        $maTaiKhoan = trim($_POST['maTaiKhoan'] ?? '');
        $lyDo = trim($_POST['lyDo'] ?? 'Yêu cầu quản lý');

        $stmtTK = $pdo->prepare("SELECT tk.*, kc.hoTen FROM TAI_KHOAN tk JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi WHERE tk.maTaiKhoan = ?");
        $stmtTK->execute([$maTaiKhoan]);
        $tk = $stmtTK->fetch();

        if (!$tk) {
            jsonResponse(false, 'Tài khoản không tồn tại.');
        }

        $isLocking = ($tk['trangThai'] === 'Hoạt động');
        $newStatus = $isLocking ? 'Đang khóa' : 'Hoạt động';

        $pdo->beginTransaction();
        try {
            // Nếu khóa tài khoản và đang có phiên chơi, tự động kết thúc phiên và đăng xuất (UC9 luồng phụ 4a)
            if ($isLocking) {
                $stmtActive = $pdo->prepare("
                    SELECT pc.maPhien, pc.chiPhi as phienChiPhi, sd.maSuDung, sd.maMay, sd.thoiGianBatDau, sd.donGiaApDung 
                    FROM PHIEN_CHOI pc
                    JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL
                    WHERE pc.maTaiKhoan = ? AND pc.trangThai = 'Đang hoạt động'
                ");
                $stmtActive->execute([$maTaiKhoan]);
                $active = $stmtActive->fetch();

                if ($active) {
                    $now = new DateTime();
                    $startSD = new DateTime($active['thoiGianBatDau']);
                    $diffMinutes = max(1, round(($now->getTimestamp() - $startSD->getTimestamp()) / 60));
                    $sdCost = round(($diffMinutes / 60) * $active['donGiaApDung']);

                    $pdo->prepare("UPDATE CHI_TIET_SU_DUNG_MAY SET thoiGianKetThuc = NOW(), thanhTien = ? WHERE maSuDung = ?")->execute([$sdCost, $active['maSuDung']]);
                    $pdo->prepare("UPDATE PHIEN_CHOI SET thoiGianKetThuc = NOW(), tongThoiGianPhut = ?, chiPhi = ?, trangThai = 'Đã kết thúc' WHERE maPhien = ?")->execute([$diffMinutes, $sdCost, $active['maPhien']]);
                    
                    $alreadyDeducted = (float)($active['phienChiPhi'] ?? 0);
                    $remainingDelta = max(0, $sdCost - $alreadyDeducted);
                    if ($remainingDelta > 0) {
                        $pdo->prepare("UPDATE TAI_KHOAN SET soDu = GREATEST(0, soDu - ?) WHERE maTaiKhoan = ?")->execute([$remainingDelta, $maTaiKhoan]);
                    }
                    $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Trống' WHERE maMay = ?")->execute([$active['maMay']]);
                }
            }

            $stmtUp = $pdo->prepare("UPDATE TAI_KHOAN SET trangThai = ? WHERE maTaiKhoan = ?");
            $stmtUp->execute([$newStatus, $maTaiKhoan]);

            logSystemAction(
                $pdo, 
                $maNhanVien, 
                $isLocking ? 'Khóa tài khoản' : 'Mở khóa tài khoản', 
                'TAI_KHOAN', 
                $maTaiKhoan, 
                $tk['trangThai'], 
                "Trạng thái mới: {$newStatus}. Lý do: {$lyDo}"
            );

            $pdo->commit();
            jsonResponse(true, "Đã " . ($isLocking ? "khóa" : "mở khóa") . " tài khoản {$tk['tenDangNhap']} thành công!", [
                'trangThai' => $newStatus
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi thao tác trạng thái: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'reset_password':
        $maTaiKhoan = trim($_POST['maTaiKhoan'] ?? '');
        $matKhauMoi = trim($_POST['matKhauMoi'] ?? '123456');

        if (empty($maTaiKhoan)) {
            jsonResponse(false, 'Thiếu mã tài khoản.');
        }

        if (strlen($matKhauMoi) < 6) {
            jsonResponse(false, 'Mật khẩu mới phải có ít nhất 6 ký tự.');
        }

        $hash = password_hash($matKhauMoi, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE TAI_KHOAN SET matKhauHash = ? WHERE maTaiKhoan = ?");
        $stmt->execute([$hash, $maTaiKhoan]);

        logSystemAction($pdo, $maNhanVien, 'Đặt lại mật khẩu', 'TAI_KHOAN', $maTaiKhoan, null, 'Cấp lại mật khẩu mới');

        jsonResponse(true, "Đã đặt lại mật khẩu thành công cho tài khoản!");
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
