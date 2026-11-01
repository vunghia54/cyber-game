<?php
/**
 * Admin Counter Top-up API
 * UC11: Nạp tiền cho khách tại quầy & Tự động áp dụng khuyến mãi
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$staff = checkStaffAuth();
$maNhanVien = $staff['maNhanVien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'search_customer':
        $query = trim($_GET['query'] ?? '');
        if (strlen($query) < 1) {
            jsonResponse(true, 'Nhập từ khóa tìm kiếm', []);
        }

        $stmt = $pdo->prepare("
            SELECT tk.maTaiKhoan, tk.tenDangNhap, tk.soDu, tk.nhomKhachHang, tk.trangThai as trangThaiTK,
                   kc.maKhachChoi, kc.hoTen, kc.soDienThoai
            FROM TAI_KHOAN tk
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            WHERE kc.maKhachChoi LIKE ? OR tk.tenDangNhap LIKE ? OR kc.soDienThoai LIKE ? OR kc.hoTen LIKE ? OR tk.maTaiKhoan LIKE ?
            LIMIT 10
        ");
        $term = "%{$query}%";
        $stmt->execute([$term, $term, $term, $term, $term]);
        $results = $stmt->fetchAll();

        jsonResponse(true, 'Kết quả tìm kiếm', $results);
        break;

    case 'calculate_promotion':
        $soTienNap = (float)($_GET['soTienNap'] ?? 0);
        $maTaiKhoan = trim($_GET['maTaiKhoan'] ?? '');

        // Lấy thông tin nhóm khách hàng nếu có
        $nhomKhachHang = 'Hội viên';
        if (!empty($maTaiKhoan)) {
            $stmtRank = $pdo->prepare("SELECT nhomKhachHang FROM TAI_KHOAN WHERE maTaiKhoan = ?");
            $stmtRank->execute([$maTaiKhoan]);
            $rowRank = $stmtRank->fetch();
            if ($rowRank && !empty($rowRank['nhomKhachHang'])) {
                $nhomKhachHang = $rowRank['nhomKhachHang'];
            }
        }

        $stmtKM = $pdo->prepare("
            SELECT * FROM CHUONG_TRINH_KHUYEN_MAI 
            WHERE trangThai = 'Hoạt động' 
              AND (CURDATE() BETWEEN ngayBatDau AND ngayKetThuc)
              AND mucNapToiThieu <= ?
            ORDER BY soTienKhuyenMai DESC LIMIT 1
        ");
        $stmtKM->execute([$soTienNap]);
        $km = $stmtKM->fetch();

        $baseBonus = $km ? (float)$km['soTienKhuyenMai'] : 0.00;
        
        // Thưởng thêm theo hạng khách hàng (VIP: +10%, Kim Cương: +20%)
        $rankBonusPercent = match($nhomKhachHang) {
            'VIP' => 0.10,
            'Kim Cương' => 0.20,
            default => 0.00
        };
        $rankBonus = $soTienNap * $rankBonusPercent;
        $totalBonus = $baseBonus + $rankBonus;
        $tongNhan = $soTienNap + $totalBonus;

        jsonResponse(true, 'Khuyến mãi áp dụng', [
            'khuyenMai' => $km,
            'nhomKhachHang' => $nhomKhachHang,
            'baseBonus' => $baseBonus,
            'rankBonus' => $rankBonus,
            'rankBonusPercent' => ($rankBonusPercent * 100),
            'tienKhuyenMai' => $totalBonus,
            'tongNhan' => $tongNhan
        ]);
        break;

    case 'execute_topup':
        $maTaiKhoan = trim($_POST['maTaiKhoan'] ?? '');
        $soTienNap = (float)($_POST['soTienNap'] ?? 0);
        $phuongThuc = trim($_POST['phuongThuc'] ?? 'Tiền mặt');
        $maThamChieu = trim($_POST['maThamChieu'] ?? '');

        if (empty($maTaiKhoan)) {
            jsonResponse(false, 'Vui lòng chọn tài khoản khách hàng.');
        }

        if ($soTienNap < 10000) {
            jsonResponse(false, 'Số tiền nạp tối thiểu tại quầy là 10.000 VNĐ.');
        }

        // Lấy tài khoản
        $stmtTK = $pdo->prepare("
            SELECT tk.*, kc.hoTen, kc.soDienThoai 
            FROM TAI_KHOAN tk
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            WHERE tk.maTaiKhoan = ?
        ");
        $stmtTK->execute([$maTaiKhoan]);
        $account = $stmtTK->fetch();

        if (!$account) {
            jsonResponse(false, 'Tài khoản không tồn tại.');
        }

        if ($account['trangThai'] === 'Đang khóa') {
            jsonResponse(false, 'Tài khoản hiện đang bị khóa, không thể nạp tiền.');
        }

        // Tìm khuyến mãi sự kiện
        $stmtKM = $pdo->prepare("
            SELECT * FROM CHUONG_TRINH_KHUYEN_MAI 
            WHERE trangThai = 'Hoạt động' 
              AND (CURDATE() BETWEEN ngayBatDau AND ngayKetThuc)
              AND mucNapToiThieu <= ?
            ORDER BY soTienKhuyenMai DESC LIMIT 1
        ");
        $stmtKM->execute([$soTienNap]);
        $km = $stmtKM->fetch();

        $baseBonus = $km ? (float)$km['soTienKhuyenMai'] : 0.00;
        $nhomKhachHang = $account['nhomKhachHang'] ?? 'Hội viên';
        $rankBonusPercent = match($nhomKhachHang) {
            'VIP' => 0.10,
            'Kim Cương' => 0.20,
            default => 0.00
        };
        $rankBonus = $soTienNap * $rankBonusPercent;
        $tienKhuyenMai = $baseBonus + $rankBonus;
        $tongCong = $soTienNap + $tienKhuyenMai;

        $maKhuyenMai = $km ? $km['maKhuyenMai'] : null;

        $pdo->beginTransaction();
        try {
            $maGiaoDich = generateNextCode($pdo, 'GIAO_DICH_NAP_TIEN', 'maGiaoDich', 'GD', 4);
            $ref = !empty($maThamChieu) ? $maThamChieu : 'POS-' . date('Ymd') . '-' . substr($maGiaoDich, 2);

            $stmtInsertGD = $pdo->prepare("
                INSERT INTO GIAO_DICH_NAP_TIEN 
                (maGiaoDich, maTaiKhoan, maNhanVien, maKhuyenMai, soTienNap, tienKhuyenMai, phuongThuc, maThamChieu, thoiGianTao, thoiGianHoanTat, trangThai)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'Thành công')
            ");
            $stmtInsertGD->execute([$maGiaoDich, $maTaiKhoan, $maNhanVien, $maKhuyenMai, $soTienNap, $tienKhuyenMai, $phuongThuc, $ref]);

            $soDuCu = (float)$account['soDu'];
            $soDuMoi = $soDuCu + $tongCong;

            $stmtUpTK = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = ? WHERE maTaiKhoan = ?");
            $stmtUpTK->execute([$soDuMoi, $maTaiKhoan]);

            $bonusLog = "Nạp: " . number_format($soTienNap) . "đ";
            if ($baseBonus > 0) $bonusLog .= " + KM SK: " . number_format($baseBonus) . "đ";
            if ($rankBonus > 0) $bonusLog .= " + Thưởng {$nhomKhachHang}: " . number_format($rankBonus) . "đ";
            $bonusLog .= " => Số dư mới: " . number_format($soDuMoi) . "đ";

            logSystemAction(
                $pdo, 
                $maNhanVien, 
                'Nạp tiền tại quầy', 
                'TAI_KHOAN', 
                $maTaiKhoan, 
                "Số dư cũ: " . number_format($soDuCu) . "đ", 
                $bonusLog
            );

            $pdo->commit();

            // Trả về dữ liệu in phiếu thu
            jsonResponse(true, "Nạp tiền thành công cho tài khoản {$account['tenDangNhap']} ({$nhomKhachHang})! Tổng nhận: " . number_format($tongCong, 0, ',', '.') . " VNĐ.", [
                'receipt' => [
                    'maGiaoDich' => $maGiaoDich,
                    'maThamChieu' => $ref,
                    'thoiGian' => date('d/m/Y H:i:s'),
                    'tenDangNhap' => $account['tenDangNhap'],
                    'nhomKhachHang' => $nhomKhachHang,
                    'hoTen' => $account['hoTen'],
                    'soDienThoai' => $account['soDienThoai'],
                    'soTienNap' => $soTienNap,
                    'baseBonus' => $baseBonus,
                    'rankBonus' => $rankBonus,
                    'tienKhuyenMai' => $tienKhuyenMai,
                    'tenKhuyenMai' => $km['tenChuongTrinh'] ?? 'Không áp dụng',
                    'tongNhan' => $tongCong,
                    'soDuCu' => $soDuCu,
                    'soDuMoi' => $soDuMoi,
                    'phuongThuc' => $phuongThuc,
                    'thuNgan' => $staff['hoTen']
                ]
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi xử lý nạp tiền: ' . $e->getMessage(), null, 500);
        }
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
