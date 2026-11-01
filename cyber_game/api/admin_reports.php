<?php
/**
 * Admin Reports & Statistics API
 * UC15: Xem thống kê & báo cáo doanh thu, phiên chơi, dịch vụ
 * Triển khai trực tiếp các câu truy vấn phức tạp (SQL Query 6, 7, 8) theo tài liệu Tuần 5
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$staff = checkStaffAuth();
$maNhanVien = $staff['maNhanVien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$fromDate = $_GET['fromDate'] ?? date('Y-m-01');
$toDate = $_GET['toDate'] ?? date('Y-m-d');

switch ($action) {
    case 'summary':
        // 1. Tổng tiền nạp
        $stmtNap = $pdo->prepare("
            SELECT COALESCE(SUM(soTienNap), 0) as tongNap, COUNT(maGiaoDich) as soGiaoDich
            FROM GIAO_DICH_NAP_TIEN 
            WHERE trangThai = 'Thành công' AND DATE(thoiGianTao) BETWEEN ? AND ?
        ");
        $stmtNap->execute([$fromDate, $toDate]);
        $napData = $stmtNap->fetch();

        // 2. Tổng số phiên chơi & tổng giờ chơi
        $stmtPhien = $pdo->prepare("
            SELECT COUNT(maPhien) as tongPhien, COALESCE(SUM(tongThoiGianPhut), 0) as tongPhut, COALESCE(SUM(chiPhi), 0) as tongTienGio
            FROM PHIEN_CHOI 
            WHERE DATE(thoiGianBatDau) BETWEEN ? AND ?
        ");
        $stmtPhien->execute([$fromDate, $toDate]);
        $phienData = $stmtPhien->fetch();

        // 3. Tổng tiền dịch vụ F&B
        $stmtDichVu = $pdo->prepare("
            SELECT COALESCE(SUM(tongTien), 0) as tongDichVu, COUNT(maDon) as soDon
            FROM DON_DICH_VU 
            WHERE trangThaiDon = 'Đã hoàn thành' AND DATE(thoiGianDat) BETWEEN ? AND ?
        ");
        $stmtDichVu->execute([$fromDate, $toDate]);
        $dvData = $stmtDichVu->fetch();

        // 4. Doanh thu theo ngày (cho biểu đồ chart) - Gom nhóm trước khi kết nối để tránh lỗi nhân tiền tích Descartes
        $stmtDaily = $pdo->prepare("
            SELECT d.date, 
                   COALESCE(g.dailyTopup, 0) as dailyTopup,
                   COALESCE(v.dailyFnb, 0) as dailyFnb
            FROM (
                SELECT CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY as date
                FROM (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) as a
                CROSS JOIN (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) as b
            ) d
            LEFT JOIN (
                SELECT DATE(thoiGianTao) as gDate, SUM(soTienNap) as dailyTopup
                FROM GIAO_DICH_NAP_TIEN
                WHERE trangThai = 'Thành công'
                GROUP BY DATE(thoiGianTao)
            ) g ON g.gDate = d.date
            LEFT JOIN (
                SELECT DATE(thoiGianDat) as vDate, SUM(tongTien) as dailyFnb
                FROM DON_DICH_VU
                WHERE trangThaiDon = 'Đã hoàn thành'
                GROUP BY DATE(thoiGianDat)
            ) v ON v.vDate = d.date
            WHERE d.date BETWEEN ? AND ?
            ORDER BY d.date ASC
        ");
        $stmtDaily->execute([$fromDate, $toDate]);
        $dailyTrends = $stmtDaily->fetchAll();

        jsonResponse(true, 'Báo cáo tổng hợp', [
            'tongTienNap' => (float)$napData['tongNap'],
            'soGiaoDichNap' => (int)$napData['soGiaoDich'],
            'tongSoPhien' => (int)$phienData['tongPhien'],
            'tongThoiGianPhut' => (int)$phienData['tongPhut'],
            'tongGioChoi' => round($phienData['tongPhut'] / 60, 1),
            'tongTienGio' => (float)$phienData['tongTienGio'],
            'tongTienDichVu' => (float)$dvData['tongDichVu'],
            'soDonDichVu' => (int)$dvData['soDon'],
            'tongDoanhThu' => (float)$napData['tongNap'] + (float)$dvData['tongDichVu'],
            'dailyTrends' => $dailyTrends
        ]);
        break;

    case 'top_gamers':
        // Query 6 trong Tuần 5: Tổng thời gian chơi của từng khách
        $stmt = $pdo->query("
            SELECT 
                KC.maKhachChoi, 
                KC.hoTen, 
                COUNT(PC.maPhien) AS soPhienChoi, 
                COALESCE(SUM(PC.tongThoiGianPhut), 0) AS tongThoiGianPhut,
                ROUND(COALESCE(SUM(PC.tongThoiGianPhut), 0) / 60, 1) AS tongGioChoi
            FROM KHACH_CHOI KC
            JOIN TAI_KHOAN TK ON KC.maKhachChoi = TK.maKhachChoi
            JOIN PHIEN_CHOI PC ON TK.maTaiKhoan = PC.maTaiKhoan
            GROUP BY KC.maKhachChoi, KC.hoTen
            ORDER BY tongThoiGianPhut DESC
            LIMIT 15
        ");
        $topGamers = $stmt->fetchAll();
        jsonResponse(true, 'Top khách hàng chơi nhiều nhất (Query 6)', $topGamers);
        break;

    case 'top_spenders':
        // Query 8 trong Tuần 5: Danh sách khách hàng nạp tiền nhiều nhất
        $stmt = $pdo->query("
            SELECT 
                KC.maKhachChoi, 
                KC.hoTen, 
                COUNT(GD.maGiaoDich) AS soLanNap, 
                SUM(GD.soTienNap) AS tongTienNap
            FROM KHACH_CHOI KC
            JOIN TAI_KHOAN TK ON KC.maKhachChoi = TK.maKhachChoi
            JOIN GIAO_DICH_NAP_TIEN GD ON TK.maTaiKhoan = GD.maTaiKhoan
            WHERE GD.trangThai = 'Thành công'
            GROUP BY KC.maKhachChoi, KC.hoTen
            ORDER BY tongTienNap DESC
            LIMIT 15
        ");
        $topSpenders = $stmt->fetchAll();
        jsonResponse(true, 'Top khách hàng nạp tiền nhiều nhất (Query 8)', $topSpenders);
        break;

    case 'session_revenue':
        // Query 7 trong Tuần 5: Doanh thu theo từng phiên
        $stmt = $pdo->query("
            SELECT 
                PC.maPhien, 
                KC.hoTen, 
                PC.tongThoiGianPhut, 
                SUM(PC.chiPhi) AS doanhThu,
                PC.thoiGianBatDau,
                PC.thoiGianKetThuc
            FROM PHIEN_CHOI PC
            JOIN TAI_KHOAN TK ON PC.maTaiKhoan = TK.maTaiKhoan
            JOIN KHACH_CHOI KC ON TK.maKhachChoi = KC.maKhachChoi
            GROUP BY PC.maPhien, KC.hoTen, PC.tongThoiGianPhut, PC.thoiGianBatDau, PC.thoiGianKetThuc
            ORDER BY doanhThu DESC
            LIMIT 20
        ");
        $sessionRevenue = $stmt->fetchAll();
        jsonResponse(true, 'Doanh thu theo từng phiên chơi (Query 7)', $sessionRevenue);
        break;

    case 'audit_logs':
        // Nhật ký hệ thống (NHAT_KY_HE_THONG)
        $stmt = $pdo->query("
            SELECT nk.*, nv.hoTen as tenNhanVien
            FROM NHAT_KY_HE_THONG nk
            JOIN NHAN_VIEN nv ON nk.maNhanVien = nv.maNhanVien
            ORDER BY nk.thoiGian DESC LIMIT 50
        ");
        $logs = $stmt->fetchAll();
        jsonResponse(true, 'Nhật ký hệ thống', $logs);
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
