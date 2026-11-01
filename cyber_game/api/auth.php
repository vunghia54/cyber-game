<?php
/**
 * Authentication API (Gamer & Staff)
 * UC1: Đăng ký tài khoản
 * UC2: Đăng nhập
 * UC3: Đăng xuất
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Tự động dọn dẹp các phiên hết tiền trước khi xử lý đăng nhập/đăng xuất
autoCloseExpiredSessions($pdo);

switch ($action) {
    case 'gamer_login':
        $tenDangNhap = trim($_POST['tenDangNhap'] ?? '');
        $matKhau = $_POST['matKhau'] ?? '';
        $maMay = trim($_POST['maMay'] ?? 'PC01');

        if (empty($tenDangNhap) || empty($matKhau)) {
            jsonResponse(false, 'Vui lòng nhập tên đăng nhập và mật khẩu.');
        }

        // Lấy thông tin tài khoản & khách chơi
        $stmt = $pdo->prepare("
            SELECT tk.*, kc.hoTen, kc.soDienThoai, kc.email 
            FROM TAI_KHOAN tk
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            WHERE tk.tenDangNhap = ?
        ");
        $stmt->execute([$tenDangNhap]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(false, 'Tên đăng nhập hoặc mật khẩu không chính xác.');
        }

        if (!password_verify($matKhau, $user['matKhauHash'])) {
            jsonResponse(false, 'Tên đăng nhập hoặc mật khẩu không chính xác.');
        }

        if ($user['trangThai'] === 'Đang khóa') {
            jsonResponse(false, 'Tài khoản của bạn hiện đang bị khóa. Vui lòng liên hệ quầy thu ngân.');
        }

        // Kiểm tra xem tài khoản có đang có phiên hoạt động hay không
        $stmtActive = $pdo->prepare("
            SELECT pc.maPhien, sd.maMay 
            FROM PHIEN_CHOI pc
            JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL
            WHERE pc.maTaiKhoan = ? AND pc.trangThai = 'Đang hoạt động'
        ");
        $stmtActive->execute([$user['maTaiKhoan']]);
        $existingSession = $stmtActive->fetch();

        // Chống chen máy: Đang chơi ở máy khác thì chặn tuyệt đối
        if ($existingSession && $existingSession['maMay'] !== $maMay) {
            jsonResponse(false, "Tài khoản của bạn đang có phiên chơi tại máy {$existingSession['maMay']}. Vui lòng đăng xuất trước khi đăng nhập máy mới.");
        }

        // Kiểm tra số dư: Nếu mở phiên mới thì tối thiểu 2.000đ; Nếu kết nối lại phiên cũ thì số dư phải > 0đ
        if (!$existingSession && $user['soDu'] < 2000) {
            jsonResponse(false, 'Số dư không đủ để mở phiên chơi mới (Tối thiểu 2.000 VNĐ). Vui lòng nạp thêm tiền tại quầy hoặc chọn nạp trực tuyến.');
        } else if ($user['soDu'] <= 0) {
            jsonResponse(false, 'Số dư tài khoản là 0đ, không đủ để mở máy. Vui lòng nạp tiền trước.');
        }

        // Kiểm tra máy trạm
        $stmtMay = $pdo->prepare("
            SELECT mt.*, lm.tenLoai, bg.maBangGia, bg.donGiaGio 
            FROM MAY_TRAM mt
            JOIN LOAI_MAY lm ON mt.maLoaiMay = lm.maLoaiMay
            JOIN BANG_GIA bg ON mt.maLoaiMay = bg.maLoaiMay
            WHERE mt.maMay = ?
        ");
        $stmtMay->execute([$maMay]);
        $station = $stmtMay->fetch();

        if (!$station) {
            jsonResponse(false, "Không tìm thấy thông tin máy trạm {$maMay}.");
        }

        if ($station['trangThai'] === 'Bảo trì') {
            jsonResponse(false, "Máy trạm {$maMay} hiện đang bảo trì. Vui lòng chọn máy khác.");
        }

        // Kiểm tra xem máy trạm có đang bị người khác sử dụng không (chặn người khác chen vào)
        if ($station['trangThai'] === 'Đang chơi') {
            $stmtOccupant = $pdo->prepare("
                SELECT pc.maTaiKhoan, tk.tenDangNhap 
                FROM PHIEN_CHOI pc
                JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL
                JOIN TAI_KHOAN tk ON pc.maTaiKhoan = tk.maTaiKhoan
                WHERE sd.maMay = ? AND pc.trangThai = 'Đang hoạt động'
            ");
            $stmtOccupant->execute([$maMay]);
            $occupant = $stmtOccupant->fetch();

            if ($occupant && $occupant['maTaiKhoan'] !== $user['maTaiKhoan']) {
                jsonResponse(false, "Máy trạm {$maMay} hiện đang có người chơi (@{$occupant['tenDangNhap']}) sử dụng. Vui lòng chọn máy trống khác!");
            }
        }

        $pdo->beginTransaction();
        try {
            // Nếu chưa có phiên hoạt động thì tạo mới
            if (!$existingSession) {
                $maPhien = generateNextCode($pdo, 'PHIEN_CHOI', 'maPhien', 'PC', 4);
                $stmtInsertPC = $pdo->prepare("
                    INSERT INTO PHIEN_CHOI (maPhien, thoiGianBatDau, tongThoiGianPhut, trangThai, chiPhi, maTaiKhoan)
                    VALUES (?, NOW(), 0, 'Đang hoạt động', 0, ?)
                ");
                $stmtInsertPC->execute([$maPhien, $user['maTaiKhoan']]);

                $maSuDung = generateNextCode($pdo, 'CHI_TIET_SU_DUNG_MAY', 'maSuDung', 'SD', 4);
                $stmtInsertSD = $pdo->prepare("
                    INSERT INTO CHI_TIET_SU_DUNG_MAY (maSuDung, maPhien, maMay, maBangGia, thoiGianBatDau, donGiaApDung, thanhTien)
                    VALUES (?, ?, ?, ?, NOW(), ?, 0)
                ");
                $stmtInsertSD->execute([$maSuDung, $maPhien, $maMay, $station['maBangGia'], $station['donGiaGio']]);

                // Cập nhật trạng thái máy
                $stmtUpMay = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Đang chơi' WHERE maMay = ?");
                $stmtUpMay->execute([$maMay]);
            } else {
                $maPhien = $existingSession['maPhien'];
            }

            $pdo->commit();

            $_SESSION['gamer_user'] = [
                'maTaiKhoan' => $user['maTaiKhoan'],
                'maKhachChoi' => $user['maKhachChoi'],
                'tenDangNhap' => $user['tenDangNhap'],
                'hoTen' => $user['hoTen'],
                'nhomKhachHang' => $user['nhomKhachHang'],
                'maMay' => $maMay,
                'maPhien' => $maPhien
            ];

            jsonResponse(true, 'Đăng nhập thành công! Bắt đầu phiên chơi.', [
                'user' => $_SESSION['gamer_user'],
                'station' => $station
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi tạo phiên chơi: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'gamer_register':
        $tenDangNhap = trim($_POST['tenDangNhap'] ?? '');
        $matKhau = $_POST['matKhau'] ?? '';
        $xacNhanMatKhau = $_POST['xacNhanMatKhau'] ?? '';
        $hoTen = trim($_POST['hoTen'] ?? '');
        $soDienThoai = trim($_POST['soDienThoai'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ngaySinh = $_POST['ngaySinh'] ?? '';
        $gioiTinh = $_POST['gioiTinh'] ?? 'Nam';

        if (empty($tenDangNhap) || empty($matKhau) || empty($hoTen) || empty($soDienThoai) || empty($email) || empty($ngaySinh)) {
            jsonResponse(false, 'Vui lòng điền đầy đủ tất cả các trường thông tin bắt buộc.');
        }

        if ($matKhau !== $xacNhanMatKhau) {
            jsonResponse(false, 'Xác nhận mật khẩu không khớp.');
        }

        if (strlen($matKhau) < 6) {
            jsonResponse(false, 'Mật khẩu phải có tối thiểu 6 ký tự.');
        }

        if (!preg_match('/^[0-9]{10,11}$/', $soDienThoai)) {
            jsonResponse(false, 'Số điện thoại không đúng định dạng (10-11 chữ số).');
        }

        if (strtotime($ngaySinh) === false || strtotime($ngaySinh) > time()) {
            jsonResponse(false, 'Ngày sinh không hợp lệ (không thể chọn ngày ở tương lai).');
        }

        // Kiểm tra trùng lặp
        $stmtCheck = $pdo->prepare("SELECT tenDangNhap FROM TAI_KHOAN WHERE tenDangNhap = ?");
        $stmtCheck->execute([$tenDangNhap]);
        if ($stmtCheck->fetch()) {
            jsonResponse(false, 'Tên đăng nhập đã được sử dụng. Vui lòng chọn tên khác.');
        }

        $stmtCheckKC = $pdo->prepare("SELECT maKhachChoi FROM KHACH_CHOI WHERE soDienThoai = ? OR email = ?");
        $stmtCheckKC->execute([$soDienThoai, $email]);
        if ($stmtCheckKC->fetch()) {
            jsonResponse(false, 'Số điện thoại hoặc email đã được sử dụng cho một tài khoản khác.');
        }

        $pdo->beginTransaction();
        try {
            $maKhachChoi = generateNextCode($pdo, 'KHACH_CHOI', 'maKhachChoi', 'KC', 3);
            $stmtKC = $pdo->prepare("
                INSERT INTO KHACH_CHOI (maKhachChoi, hoTen, ngaySinh, soDienThoai, email, gioiTinh, trangThai)
                VALUES (?, ?, ?, ?, ?, ?, 'Hoạt động')
            ");
            $stmtKC->execute([$maKhachChoi, $hoTen, $ngaySinh, $soDienThoai, $email, $gioiTinh]);

            $maTaiKhoan = generateNextCode($pdo, 'TAI_KHOAN', 'maTaiKhoan', 'TK', 3);
            $hash = password_hash($matKhau, PASSWORD_BCRYPT);
            
            // Tặng 10,000đ tân thủ kích hoạt tài khoản
            $soDuBanDau = 10000.00;

            $stmtTK = $pdo->prepare("
                INSERT INTO TAI_KHOAN (maTaiKhoan, tenDangNhap, matKhauHash, soDu, nhomKhachHang, ngayTao, trangThai, maKhachChoi)
                VALUES (?, ?, ?, ?, 'Hội viên', CURDATE(), 'Hoạt động', ?)
            ");
            $stmtTK->execute([$maTaiKhoan, $tenDangNhap, $hash, $soDuBanDau, $maKhachChoi]);

            // Ghi nhật ký khuyến mãi tân thủ vào nạp tiền
            $maGiaoDich = generateNextCode($pdo, 'GIAO_DICH_NAP_TIEN', 'maGiaoDich', 'GD', 3);
            $stmtGD = $pdo->prepare("
                INSERT INTO GIAO_DICH_NAP_TIEN (maGiaoDich, maTaiKhoan, maNhanVien, maKhuyenMai, soTienNap, tienKhuyenMai, phuongThuc, maThamChieu, thoiGianTao, thoiGianHoanTat, trangThai)
                VALUES (?, ?, NULL, 'KM01', 0, 10000, 'Khuyến mãi tân thủ', 'WELCOME-BONUS', NOW(), NOW(), 'Thành công')
            ");
            $stmtGD->execute([$maGiaoDich, $maTaiKhoan]);

            $pdo->commit();
            jsonResponse(true, 'Đăng ký tài khoản thành công! Tặng ngay 10.000 VNĐ trải nghiệm. Bạn có thể đăng nhập ngay.', [
                'tenDangNhap' => $tenDangNhap,
                'soDu' => $soDuBanDau
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Đăng ký không thành công: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'gamer_logout':
        $user = checkGamerAuth();
        $maTaiKhoan = $user['maTaiKhoan'];
        $maMay = $user['maMay'] ?? null;
        $maPhien = $user['maPhien'] ?? null;

        if (!$maPhien) {
            unset($_SESSION['gamer_user']);
            jsonResponse(true, 'Đã đăng xuất.');
        }

        $pdo->beginTransaction();
        try {
            // Lấy thông tin phiên hiện tại
            $stmtPC = $pdo->prepare("SELECT * FROM PHIEN_CHOI WHERE maPhien = ? AND trangThai = 'Đang hoạt động'");
            $stmtPC->execute([$maPhien]);
            $phien = $stmtPC->fetch();

            if ($phien) {
                // Tính thời gian kết thúc của chi tiết sử dụng máy
                $stmtSD = $pdo->prepare("
                    SELECT * FROM CHI_TIET_SU_DUNG_MAY 
                    WHERE maPhien = ? AND thoiGianKetThuc IS NULL 
                    ORDER BY thoiGianBatDau DESC LIMIT 1
                ");
                $stmtSD->execute([$maPhien]);
                $sd = $stmtSD->fetch();

                $totalCost = 0;

                if ($sd) {
                    $start = new DateTime($sd['thoiGianBatDau']);
                    $now = new DateTime();
                    $diffMinutes = max(1, round(($now->getTimestamp() - $start->getTimestamp()) / 60));
                    $costSD = round(($diffMinutes / 60) * $sd['donGiaApDung']);

                    $stmtCloseSD = $pdo->prepare("
                        UPDATE CHI_TIET_SU_DUNG_MAY 
                        SET thoiGianKetThuc = NOW(), thanhTien = ?
                        WHERE maSuDung = ?
                    ");
                    $stmtCloseSD->execute([$costSD, $sd['maSuDung']]);
                }

                // Tính tổng chi phí toàn phiên
                $stmtSum = $pdo->prepare("SELECT SUM(thanhTien) as tongChiPhi FROM CHI_TIET_SU_DUNG_MAY WHERE maPhien = ?");
                $stmtSum->execute([$maPhien]);
                $totalCost = $stmtSum->fetch()['tongChiPhi'] ?? 0;

                $startPC = new DateTime($phien['thoiGianBatDau']);
                $now = new DateTime();
                $totalMinutes = max(1, round(($now->getTimestamp() - $startPC->getTimestamp()) / 60));

                // Đóng phiên chơi
                $stmtClosePC = $pdo->prepare("
                    UPDATE PHIEN_CHOI 
                    SET thoiGianKetThuc = NOW(), tongThoiGianPhut = ?, chiPhi = ?, trangThai = 'Đã kết thúc'
                    WHERE maPhien = ?
                ");
                $stmtClosePC->execute([$totalMinutes, $totalCost, $maPhien]);

                // Trừ phần chênh lệch chi phí còn lại vào số dư tài khoản (tránh trừ trùng lặp)
                $alreadyDeducted = (float)($phien['chiPhi'] ?? 0);
                $remainingDelta = max(0, $totalCost - $alreadyDeducted);
                if ($remainingDelta > 0) {
                    $stmtDeduct = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = GREATEST(0, soDu - ?) WHERE maTaiKhoan = ?");
                    $stmtDeduct->execute([$remainingDelta, $maTaiKhoan]);
                }

                // Giải phóng máy trạm
                if ($maMay) {
                    $stmtFree = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Trống' WHERE maMay = ?");
                    $stmtFree->execute([$maMay]);
                }
            }

            $pdo->commit();
            unset($_SESSION['gamer_user']);
            jsonResponse(true, 'Đăng xuất và kết thúc phiên chơi thành công!');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi khi đăng xuất: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'staff_login':
        $tenDangNhap = trim($_POST['tenDangNhap'] ?? '');
        $matKhau = $_POST['matKhau'] ?? '';

        if (empty($tenDangNhap) || empty($matKhau)) {
            jsonResponse(false, 'Vui lòng nhập tên đăng nhập và mật khẩu.');
        }

        $stmt = $pdo->prepare("SELECT * FROM NHAN_VIEN WHERE tenDangNhap = ?");
        $stmt->execute([$tenDangNhap]);
        $staff = $stmt->fetch();

        if (!$staff || !password_verify($matKhau, $staff['matKhauHash'])) {
            jsonResponse(false, 'Tài khoản hoặc mật khẩu nhân viên không chính xác.');
        }

        if ($staff['trangThai'] !== 'Hoạt động') {
            jsonResponse(false, 'Tài khoản nhân viên đang bị tạm khóa.');
        }

        $_SESSION['staff_user'] = [
            'maNhanVien' => $staff['maNhanVien'],
            'tenDangNhap' => $staff['tenDangNhap'],
            'hoTen' => $staff['hoTen'],
            'vaiTro' => $staff['vaiTro'],
            'soDienThoai' => $staff['soDienThoai']
        ];

        logSystemAction($pdo, $staff['maNhanVien'], 'Đăng nhập hệ thống', 'NHAN_VIEN', $staff['maNhanVien'], null, 'Đăng nhập ca làm việc');

        jsonResponse(true, 'Đăng nhập hệ thống quản lý thành công!', $_SESSION['staff_user']);
        break;

    case 'staff_logout':
        if (!empty($_SESSION['staff_user'])) {
            logSystemAction($pdo, $_SESSION['staff_user']['maNhanVien'], 'Đăng xuất', 'NHAN_VIEN', $_SESSION['staff_user']['maNhanVien']);
            unset($_SESSION['staff_user']);
        }
        jsonResponse(true, 'Đã đăng xuất khỏi tài khoản nhân viên.');
        break;

    case 'get_session':
        jsonResponse(true, 'Thông tin phiên', [
            'gamer' => $_SESSION['gamer_user'] ?? null,
            'staff' => $_SESSION['staff_user'] ?? null
        ]);
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
