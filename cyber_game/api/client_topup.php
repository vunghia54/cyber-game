<?php
/**
 * Client Online Top-up API
 * UC7: Nạp tiền trực tuyến (VietQR & Khuyến mãi)
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = checkGamerAuth();
$maTaiKhoan = $user['maTaiKhoan'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_promotions':
    case 'list_promotions':
        $stmt = $pdo->query("
            SELECT * FROM CHUONG_TRINH_KHUYEN_MAI 
            WHERE trangThai = 'Hoạt động' 
              AND (CURDATE() BETWEEN ngayBatDau AND ngayKetThuc)
            ORDER BY mucNapToiThieu ASC
        ");
        $promotions = $stmt->fetchAll();

        // Lấy nhóm khách hàng của gamer hiện tại
        $stmtUser = $pdo->prepare("SELECT nhomKhachHang FROM TAI_KHOAN WHERE maTaiKhoan = ?");
        $stmtUser->execute([$maTaiKhoan]);
        $userRank = $stmtUser->fetch()['nhomKhachHang'] ?? 'Hội viên';

        $rankBonusPercent = match($userRank) {
            'VIP' => 10,
            'Kim Cương' => 20,
            default => 0
        };

        jsonResponse(true, 'Danh sách chương trình khuyến mãi', [
            'promotions' => $promotions,
            'userRank' => $userRank,
            'rankBonusPercent' => $rankBonusPercent
        ]);
        break;

    case 'calculate_promotion':
        $soTienNap = (float)($_GET['soTienNap'] ?? ($_POST['soTienNap'] ?? 0));

        if ($soTienNap < 10000) {
            jsonResponse(false, 'Mức nạp tiền tối thiểu là 10.000 VNĐ.');
        }

        // Khuyến mãi sự kiện tốt nhất
        $stmtKM = $pdo->prepare("
            SELECT * FROM CHUONG_TRINH_KHUYEN_MAI 
            WHERE trangThai = 'Hoạt động' 
              AND (CURDATE() BETWEEN ngayBatDau AND ngayKetThuc)
              AND mucNapToiThieu <= ?
            ORDER BY soTienKhuyenMai DESC LIMIT 1
        ");
        $stmtKM->execute([$soTienNap]);
        $km = $stmtKM->fetch();

        // Lấy hạng tài khoản
        $stmtRank = $pdo->prepare("SELECT nhomKhachHang FROM TAI_KHOAN WHERE maTaiKhoan = ?");
        $stmtRank->execute([$maTaiKhoan]);
        $userRank = $stmtRank->fetch()['nhomKhachHang'] ?? 'Hội viên';

        $rankBonusPercent = match($userRank) {
            'VIP' => 0.10,
            'Kim Cương' => 0.20,
            default => 0.00
        };

        $baseBonus = $km ? (float)$km['soTienKhuyenMai'] : 0.00;
        $rankBonus = $soTienNap * $rankBonusPercent;
        $totalBonus = $baseBonus + $rankBonus;
        $tongNhan = $soTienNap + $totalBonus;

        jsonResponse(true, 'Tính toán khuyến mãi thành công', [
            'soTienNap' => $soTienNap,
            'khuyenMai' => $km,
            'tenKhuyenMai' => $km ? $km['tenChuongTrinh'] : null,
            'baseBonus' => $baseBonus,
            'userRank' => $userRank,
            'rankBonusPercent' => ($rankBonusPercent * 100),
            'rankBonus' => $rankBonus,
            'tienKhuyenMai' => $totalBonus,
            'tongNhan' => $tongNhan
        ]);
        break;

    case 'create_topup_request':
    case 'create_topup':
        $soTienNap = (float)($_POST['soTienNap'] ?? 0);
        $phuongThuc = $_POST['phuongThuc'] ?? 'Online VietQR';

        if ($soTienNap < 10000) {
            jsonResponse(false, 'Mức nạp tiền tối thiểu là 10.000 VNĐ.');
        }

        // Tìm khuyến mãi tốt nhất áp dụng cho mức nạp này
        $stmtKM = $pdo->prepare("
            SELECT * FROM CHUONG_TRINH_KHUYEN_MAI 
            WHERE trangThai = 'Hoạt động' 
              AND (CURDATE() BETWEEN ngayBatDau AND ngayKetThuc)
              AND mucNapToiThieu <= ?
            ORDER BY soTienKhuyenMai DESC LIMIT 1
        ");
        $stmtKM->execute([$soTienNap]);
        $km = $stmtKM->fetch();

        // Lấy hạng khách hàng của user
        $stmtUser = $pdo->prepare("SELECT nhomKhachHang FROM TAI_KHOAN WHERE maTaiKhoan = ?");
        $stmtUser->execute([$maTaiKhoan]);
        $userRank = $stmtUser->fetch()['nhomKhachHang'] ?? 'Hội viên';

        $rankBonusPercent = match($userRank) {
            'VIP' => 0.10,
            'Kim Cương' => 0.20,
            default => 0.00
        };

        $baseBonus = $km ? (float)$km['soTienKhuyenMai'] : 0.00;
        $rankBonus = $soTienNap * $rankBonusPercent;
        $tienKhuyenMai = $baseBonus + $rankBonus;
        $tenKhuyenMai = $km ? $km['tenChuongTrinh'] : null;

        $maKhuyenMai = $km ? $km['maKhuyenMai'] : null;
        $maGiaoDich = generateNextCode($pdo, 'GIAO_DICH_NAP_TIEN', 'maGiaoDich', 'GD', 4);
        $maThamChieu = 'VQR-' . strtoupper(substr(md5(uniqid()), 0, 8));

        // Thời gian hết hạn sau 15 phút
        $stmtInsert = $pdo->prepare("
            INSERT INTO GIAO_DICH_NAP_TIEN 
            (maGiaoDich, maTaiKhoan, maNhanVien, maKhuyenMai, soTienNap, tienKhuyenMai, phuongThuc, maThamChieu, thoiGianTao, thoiGianHetHan, trangThai)
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, NOW(), NOW() + INTERVAL 15 MINUTE, 'Đang chờ')
        ");
        $stmtInsert->execute([$maGiaoDich, $maTaiKhoan, $maKhuyenMai, $soTienNap, $tienKhuyenMai, $phuongThuc, $maThamChieu]);

        // Tạo URL mã VietQR trực quan (MBBank demo: 0987654321 / CYBERNET)
        $qrContent = "https://api.vietqr.io/image/970422-0987654321-compact.jpg?amount={$soTienNap}&addInfo={$maThamChieu}%20CYBERNET&accountName=CYBERNET%20GAMING";

        $tongNhan = $soTienNap + $tienKhuyenMai;

        jsonResponse(true, 'Khởi tạo giao dịch nạp tiền thành công', [
            'maGiaoDich' => $maGiaoDich,
            'maThamChieu' => $maThamChieu,
            'soTienNap' => $soTienNap,
            'baseBonus' => $baseBonus,
            'rankBonus' => $rankBonus,
            'userRank' => $userRank,
            'rankBonusPercent' => ($rankBonusPercent * 100),
            'tienKhuyenMai' => $tienKhuyenMai,
            'tongNhan' => $tongNhan,
            'tongNhanDuoc' => $tongNhan,
            'tenKhuyenMai' => $tenKhuyenMai,
            'qrUrl' => $qrContent,
            'qrCodeUrl' => $qrContent,
            'thoiGianHetHanGiay' => 900 // 15 phút
        ]);
        break;

    case 'simulate_payment_success':
    case 'simulate_payment':
        $maGiaoDich = trim($_POST['maGiaoDich'] ?? '');

        if (empty($maGiaoDich)) {
            jsonResponse(false, 'Thiếu mã giao dịch.');
        }

        $stmt = $pdo->prepare("
            SELECT * FROM GIAO_DICH_NAP_TIEN 
            WHERE maGiaoDich = ? AND maTaiKhoan = ? AND trangThai = 'Đang chờ'
        ");
        $stmt->execute([$maGiaoDich, $maTaiKhoan]);
        $gd = $stmt->fetch();

        if (!$gd) {
            jsonResponse(false, 'Giao dịch không tồn tại hoặc đã được xử lý/hết hạn.');
        }

        $totalCredit = (float)$gd['soTienNap'] + (float)$gd['tienKhuyenMai'];

        $pdo->beginTransaction();
        try {
            // Cộng tiền tài khoản
            $stmtUpTK = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = soDu + ? WHERE maTaiKhoan = ?");
            $stmtUpTK->execute([$totalCredit, $maTaiKhoan]);

            // Cập nhật trạng thái giao dịch
            $stmtUpGD = $pdo->prepare("UPDATE GIAO_DICH_NAP_TIEN SET trangThai = 'Thành công', thoiGianHoanTat = NOW() WHERE maGiaoDich = ?");
            $stmtUpGD->execute([$maGiaoDich]);

            $pdo->commit();

            // Lấy lại số dư mới nhất
            $stmtNewBal = $pdo->prepare("SELECT soDu FROM TAI_KHOAN WHERE maTaiKhoan = ?");
            $stmtNewBal->execute([$maTaiKhoan]);
            $newBalance = $stmtNewBal->fetch()['soDu'];

            jsonResponse(true, "Nạp tiền thành công! Đã cộng " . number_format($totalCredit, 0, ',', '.') . " VNĐ vào tài khoản.", [
                'tongNhan' => $totalCredit,
                'tongNhanDuoc' => $totalCredit,
                'soDuMoi' => (float)$newBalance,
                'soTienNap' => (float)$gd['soTienNap'],
                'tienKhuyenMai' => (float)$gd['tienKhuyenMai']
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi cập nhật số dư: ' . $e->getMessage(), null, 500);
        }
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
