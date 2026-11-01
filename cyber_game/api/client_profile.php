<?php
/**
 * Client Profile & History API
 * UC4: Quản lý thông tin cá nhân
 * UC6: Xem lịch sử giao dịch & phiên chơi
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = checkGamerAuth();
$maTaiKhoan = $user['maTaiKhoan'];
$maKhachChoi = $user['maKhachChoi'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_profile':
        $stmt = $pdo->prepare("
            SELECT tk.maTaiKhoan, tk.tenDangNhap, tk.soDu, tk.nhomKhachHang, tk.ngayTao, tk.trangThai as trangThaiTK,
                   kc.maKhachChoi, kc.hoTen, kc.ngaySinh, kc.soDienThoai, kc.email, kc.gioiTinh
            FROM TAI_KHOAN tk
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            WHERE tk.maTaiKhoan = ?
        ");
        $stmt->execute([$maTaiKhoan]);
        $profile = $stmt->fetch();

        if (!$profile) {
            jsonResponse(false, 'Không tìm thấy hồ sơ khách chơi.');
        }

        // Thống kê sơ bộ
        $stmtStats = $pdo->prepare("
            SELECT COUNT(maPhien) as tongSoPhien, COALESCE(SUM(tongThoiGianPhut), 0) as tongThoiGianPhut
            FROM PHIEN_CHOI WHERE maTaiKhoan = ? AND trangThai = 'Đã kết thúc'
        ");
        $stmtStats->execute([$maTaiKhoan]);
        $stats = $stmtStats->fetch();

        $hours = round(($stats['tongThoiGianPhut'] ?? 0) / 60);
        $profile['tongSoPhien'] = $stats['tongSoPhien'];
        $profile['tongThoiGianPhut'] = $stats['tongThoiGianPhut'];

        jsonResponse(true, 'Thông tin cá nhân', [
            'user' => $profile,
            'stats' => [
                'totalHours' => $hours,
                'totalSessions' => (int)($stats['tongSoPhien'] ?? 0),
                'totalMinutes' => (int)($stats['tongThoiGianPhut'] ?? 0)
            ],
            'tenDangNhap' => $profile['tenDangNhap'],
            'hoTen' => $profile['hoTen'],
            'soDienThoai' => $profile['soDienThoai'],
            'email' => $profile['email'],
            'nhomKhachHang' => $profile['nhomKhachHang'],
            'ngaySinh' => $profile['ngaySinh'],
            'gioiTinh' => $profile['gioiTinh']
        ]);
        break;

    case 'update_profile':
        $hoTen = trim($_POST['hoTen'] ?? '');
        $soDienThoai = trim($_POST['soDienThoai'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ngaySinh = $_POST['ngaySinh'] ?? '';
        $gioiTinh = $_POST['gioiTinh'] ?? 'Nam';

        if (empty($hoTen) || empty($soDienThoai) || empty($email)) {
            jsonResponse(false, 'Vui lòng điền đầy đủ họ tên, số điện thoại và email.');
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

        if (empty($ngaySinh)) {
            $stmtCur = $pdo->prepare("SELECT ngaySinh, gioiTinh FROM KHACH_CHOI WHERE maKhachChoi = ?");
            $stmtCur->execute([$maKhachChoi]);
            $cur = $stmtCur->fetch();
            $ngaySinh = $cur['ngaySinh'] ?? '2000-01-01';
            $gioiTinh = $cur['gioiTinh'] ?? 'Nam';
        }

        // Kiểm tra trùng lặp SĐT / Email với người khác
        $stmtDup = $pdo->prepare("
            SELECT maKhachChoi FROM KHACH_CHOI 
            WHERE (soDienThoai = ? OR email = ?) AND maKhachChoi != ?
        ");
        $stmtDup->execute([$soDienThoai, $email, $maKhachChoi]);
        if ($stmtDup->fetch()) {
            jsonResponse(false, 'Số điện thoại hoặc email đã được sử dụng bởi tài khoản khác.');
        }

        $stmtUp = $pdo->prepare("
            UPDATE KHACH_CHOI 
            SET hoTen = ?, soDienThoai = ?, email = ?, ngaySinh = ?, gioiTinh = ?
            WHERE maKhachChoi = ?
        ");
        $stmtUp->execute([$hoTen, $soDienThoai, $email, $ngaySinh, $gioiTinh, $maKhachChoi]);

        $_SESSION['gamer_user']['hoTen'] = $hoTen;

        jsonResponse(true, 'Cập nhật thông tin cá nhân thành công!');
        break;

    case 'change_password':
        $matKhauCu = $_POST['matKhauCu'] ?? '';
        $matKhauMoi = $_POST['matKhauMoi'] ?? '';
        $xacNhanMatKhau = $_POST['xacNhanMatKhau'] ?? '';

        if (empty($matKhauCu) || empty($matKhauMoi)) {
            jsonResponse(false, 'Vui lòng nhập mật khẩu hiện tại và mật khẩu mới.');
        }

        if ($matKhauMoi !== $xacNhanMatKhau) {
            jsonResponse(false, 'Xác nhận mật khẩu mới không khớp.');
        }

        if (strlen($matKhauMoi) < 6) {
            jsonResponse(false, 'Mật khẩu mới phải có tối thiểu 6 ký tự.');
        }

        $stmt = $pdo->prepare("SELECT matKhauHash FROM TAI_KHOAN WHERE maTaiKhoan = ?");
        $stmt->execute([$maTaiKhoan]);
        $currHash = $stmt->fetch()['matKhauHash'] ?? '';

        if (!password_verify($matKhauCu, $currHash)) {
            jsonResponse(false, 'Mật khẩu hiện tại không chính xác.');
        }

        $newHash = password_hash($matKhauMoi, PASSWORD_BCRYPT);
        $stmtUp = $pdo->prepare("UPDATE TAI_KHOAN SET matKhauHash = ? WHERE maTaiKhoan = ?");
        $stmtUp->execute([$newHash, $maTaiKhoan]);

        jsonResponse(true, 'Đổi mật khẩu thành công!');
        break;

    case 'topup_history':
        $stmtGD = $pdo->prepare("
            SELECT gd.*, km.tenChuongTrinh, nv.hoTen as tenNhanVien
            FROM GIAO_DICH_NAP_TIEN gd
            LEFT JOIN CHUONG_TRINH_KHUYEN_MAI km ON gd.maKhuyenMai = km.maKhuyenMai
            LEFT JOIN NHAN_VIEN nv ON gd.maNhanVien = nv.maNhanVien
            WHERE gd.maTaiKhoan = ? OR gd.maTaiKhoan = (SELECT tk2.maTaiKhoan FROM TAI_KHOAN tk2 WHERE tk2.tenDangNhap = ? LIMIT 1)
            ORDER BY gd.thoiGianTao DESC LIMIT 50
        ");
        $stmtGD->execute([$maTaiKhoan, $user['tenDangNhap']]);
        jsonResponse(true, 'Lịch sử nạp tiền', $stmtGD->fetchAll());
        break;

    case 'session_history':
        $stmtPC = $pdo->prepare("
            SELECT pc.*, 
                   (SELECT GROUP_CONCAT(CONCAT(mt.tenMay, ' (', sd.donGiaApDung, 'đ)') SEPARATOR ' → ')
                    FROM CHI_TIET_SU_DUNG_MAY sd 
                    JOIN MAY_TRAM mt ON sd.maMay = mt.maMay 
                    WHERE sd.maPhien = pc.maPhien) as danhSachMay
            FROM PHIEN_CHOI pc
            WHERE pc.maTaiKhoan = ? OR pc.maTaiKhoan = (SELECT tk2.maTaiKhoan FROM TAI_KHOAN tk2 WHERE tk2.tenDangNhap = ? LIMIT 1)
            ORDER BY pc.thoiGianBatDau DESC LIMIT 50
        ");
        $stmtPC->execute([$maTaiKhoan, $user['tenDangNhap']]);
        jsonResponse(true, 'Lịch sử phiên chơi', $stmtPC->fetchAll());
        break;

    case 'get_history':
        $type = $_GET['type'] ?? 'all'; // 'topup', 'session', 'all'
        
        $topups = [];
        $sessions = [];

        if ($type === 'topup' || $type === 'all') {
            $stmtGD = $pdo->prepare("
                SELECT gd.*, km.tenChuongTrinh, nv.hoTen as tenNhanVien
                FROM GIAO_DICH_NAP_TIEN gd
                LEFT JOIN CHUONG_TRINH_KHUYEN_MAI km ON gd.maKhuyenMai = km.maKhuyenMai
                LEFT JOIN NHAN_VIEN nv ON gd.maNhanVien = nv.maNhanVien
                WHERE gd.maTaiKhoan = ?
                ORDER BY gd.thoiGianTao DESC LIMIT 50
            ");
            $stmtGD->execute([$maTaiKhoan]);
            $topups = $stmtGD->fetchAll();
        }

        if ($type === 'session' || $type === 'all') {
            $stmtPC = $pdo->prepare("
                SELECT pc.*, 
                   (SELECT GROUP_CONCAT(CONCAT(mt.tenMay, ' (', sd.donGiaApDung, 'đ)') SEPARATOR ' → ')
                    FROM CHI_TIET_SU_DUNG_MAY sd 
                    JOIN MAY_TRAM mt ON sd.maMay = mt.maMay 
                    WHERE sd.maPhien = pc.maPhien) as danhSachMay
                FROM PHIEN_CHOI pc
                WHERE pc.maTaiKhoan = ?
                ORDER BY pc.thoiGianBatDau DESC LIMIT 50
            ");
            $stmtPC->execute([$maTaiKhoan]);
            $sessions = $stmtPC->fetchAll();
        }

        jsonResponse(true, 'Lịch sử giao dịch và phiên chơi', [
            'topups' => $topups,
            'sessions' => $sessions
        ]);
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
