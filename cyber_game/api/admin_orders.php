<?php
/**
 * Admin F&B Orders API
 * UC13: Xử lý đơn dịch vụ (Tiếp nhận, chuẩn bị, hoàn tất, hủy & hoàn tiền)
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$staff = checkStaffAuth();
$maNhanVien = $staff['maNhanVien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_orders':
        $filterStatus = $_GET['status'] ?? ''; // 'Chờ xử lý', 'Đang chuẩn bị', 'Đã hoàn thành', 'Đã hủy'

        $sql = "
            SELECT d.*, tk.tenDangNhap, kc.hoTen as tenKhach, mt.tenMay, mt.viTri, nv.hoTen as tenNhanVien
            FROM DON_DICH_VU d
            JOIN TAI_KHOAN tk ON d.maTaiKhoan = tk.maTaiKhoan
            JOIN KHACH_CHOI kc ON tk.maKhachChoi = kc.maKhachChoi
            JOIN MAY_TRAM mt ON d.maMay = mt.maMay
            LEFT JOIN NHAN_VIEN nv ON d.maNhanVien = nv.maNhanVien
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filterStatus)) {
            $sql .= " AND d.trangThaiDon = ?";
            $params[] = $filterStatus;
        }

        $sql .= " ORDER BY CASE d.trangThaiDon 
                    WHEN 'Chờ xử lý' THEN 1 
                    WHEN 'Đang chuẩn bị' THEN 2 
                    WHEN 'Đã hoàn thành' THEN 3 
                    ELSE 4 END, d.thoiGianDat DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$ord) {
            $stmtItems = $pdo->prepare("
                SELECT c.*, m.tenMon, m.donViTinh 
                FROM CHI_TIET_DON_DICH_VU c
                JOIN MON_DICH_VU m ON c.maMon = m.maMon
                WHERE c.maDon = ?
            ");
            $stmtItems->execute([$ord['maDon']]);
            $ord['items'] = $stmtItems->fetchAll();
        }

        // Đếm số đơn chờ xử lý
        $stmtPending = $pdo->query("SELECT COUNT(*) as pendingCount FROM DON_DICH_VU WHERE trangThaiDon = 'Chờ xử lý'");
        $pendingCount = $stmtPending->fetch()['pendingCount'] ?? 0;

        jsonResponse(true, 'Danh sách đơn dịch vụ', [
            'pendingCount' => $pendingCount,
            'orders' => $orders
        ]);
        break;

    case 'update_status':
        $maDon = trim($_POST['maDon'] ?? '');
        $newStatus = trim($_POST['newStatus'] ?? ''); // 'Đang chuẩn bị', 'Đã hoàn thành'

        if (empty($maDon) || empty($newStatus)) {
            jsonResponse(false, 'Thiếu thông tin cập nhật đơn hàng.');
        }

        $stmt = $pdo->prepare("SELECT * FROM DON_DICH_VU WHERE maDon = ?");
        $stmt->execute([$maDon]);
        $order = $stmt->fetch();

        if (!$order) {
            jsonResponse(false, 'Đơn hàng không tồn tại.');
        }

        $pdo->beginTransaction();
        try {
            $stmtUp = $pdo->prepare("
                UPDATE DON_DICH_VU 
                SET trangThaiDon = ?, maNhanVien = ?, trangThaiThanhToan = IF(trangThaiThanhToan = 'Chưa thanh toán' AND ? = 'Đã hoàn thành', 'Đã thanh toán', trangThaiThanhToan)
                WHERE maDon = ?
            ");
            $stmtUp->execute([$newStatus, $maNhanVien, $newStatus, $maDon]);

            logSystemAction(
                $pdo, 
                $maNhanVien, 
                'Cập nhật đơn dịch vụ', 
                'DON_DICH_VU', 
                $maDon, 
                $order['trangThaiDon'], 
                "Chuyển trạng thái đơn sang '{$newStatus}'"
            );

            $pdo->commit();
            jsonResponse(true, "Đã cập nhật đơn hàng #{$maDon} sang trạng thái '{$newStatus}'.");
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi cập nhật: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'cancel_order':
        // UC13 Luồng phụ 6a: Hủy đơn & hoàn tiền nếu đã trừ số dư
        $maDon = trim($_POST['maDon'] ?? '');
        $lyDo = trim($_POST['lyDo'] ?? 'Hết món hoặc khách yêu cầu hủy');

        $stmt = $pdo->prepare("SELECT * FROM DON_DICH_VU WHERE maDon = ?");
        $stmt->execute([$maDon]);
        $order = $stmt->fetch();

        if (!$order) {
            jsonResponse(false, 'Đơn hàng không tồn tại.');
        }

        if ($order['trangThaiDon'] === 'Đã hoàn thành') {
            jsonResponse(false, 'Không thể hủy đơn hàng đã hoàn tất.');
        }

        if ($order['trangThaiDon'] === 'Đã hủy') {
            jsonResponse(false, 'Đơn hàng này đã được hủy trước đó, không thể hủy lại.');
        }

        $pdo->beginTransaction();
        try {
            // Xác định trạng thái thanh toán mới sau khi hủy/hoàn tiền
            $newPaymentStatus = ($order['hinhThucThanhToan'] === 'Trừ số dư' && $order['trangThaiThanhToan'] === 'Đã thanh toán') ? 'Đã hoàn tiền' : $order['trangThaiThanhToan'];

            // Đảm bảo có cột lyDoHuy
            try {
                $checkCol = $pdo->query("SHOW COLUMNS FROM DON_DICH_VU LIKE 'lyDoHuy'");
                if (!$checkCol->fetch()) {
                    $pdo->exec("ALTER TABLE DON_DICH_VU ADD COLUMN lyDoHuy VARCHAR(255) NULL DEFAULT NULL AFTER trangThaiDon");
                }
                $stmtUp = $pdo->prepare("UPDATE DON_DICH_VU SET trangThaiDon = 'Đã hủy', trangThaiThanhToan = ?, lyDoHuy = ?, maNhanVien = ? WHERE maDon = ?");
                $stmtUp->execute([$newPaymentStatus, $lyDo, $maNhanVien, $maDon]);
            } catch (Exception $eCol) {
                $stmtUp = $pdo->prepare("UPDATE DON_DICH_VU SET trangThaiDon = 'Đã hủy', trangThaiThanhToan = ?, maNhanVien = ? WHERE maDon = ?");
                $stmtUp->execute([$newPaymentStatus, $maNhanVien, $maDon]);
            }

            // Hoàn lại số lượng tồn kho
            $stmtItems = $pdo->prepare("SELECT maMon, soLuong FROM CHI_TIET_DON_DICH_VU WHERE maDon = ?");
            $stmtItems->execute([$maDon]);
            $items = $stmtItems->fetchAll();

            $stmtRestock = $pdo->prepare("UPDATE MON_DICH_VU SET soLuongTon = soLuongTon + ? WHERE maMon = ?");
            foreach ($items as $it) {
                $stmtRestock->execute([$it['soLuong'], $it['maMon']]);
            }

            // Nếu đã trừ số dư tài khoản thì hoàn tiền
            $refunded = false;
            if ($order['hinhThucThanhToan'] === 'Trừ số dư' && $order['trangThaiThanhToan'] === 'Đã thanh toán') {
                $stmtRefund = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = soDu + ? WHERE maTaiKhoan = ?");
                $stmtRefund->execute([$order['tongTien'], $order['maTaiKhoan']]);
                $refunded = true;
            }

            logSystemAction(
                $pdo, 
                $maNhanVien, 
                'Hủy đơn dịch vụ', 
                'DON_DICH_VU', 
                $maDon, 
                $order['trangThaiDon'], 
                "Hủy đơn. Lý do: {$lyDo}." . ($refunded ? " Đã hoàn lại " . number_format($order['tongTien']) . "đ vào tài khoản." : "")
            );

            $pdo->commit();
            jsonResponse(true, "Đã hủy đơn hàng #{$maDon} thành công." . ($refunded ? " Đã hoàn lại " . number_format($order['tongTien'], 0, ',', '.') . " VNĐ vào số dư tài khoản khách hàng." : ""));
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi hủy đơn: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'get_inventory':
        $items = $pdo->query("SELECT * FROM MON_DICH_VU ORDER BY loaiMon, tenMon ASC")->fetchAll();
        jsonResponse(true, 'Danh sách kho món', $items);
        break;

    case 'update_stock':
        $maMon = trim($_POST['maMon'] ?? '');
        $addQuantity = intval($_POST['addQuantity'] ?? 0);
        $newQuantity = isset($_POST['newQuantity']) && $_POST['newQuantity'] !== '' ? intval($_POST['newQuantity']) : null;
        $trangThai = trim($_POST['trangThai'] ?? '');

        if (empty($maMon)) {
            jsonResponse(false, 'Mã món không hợp lệ.');
        }

        if ($newQuantity !== null) {
            $newStock = max(0, $newQuantity);
            $newStatus = (!empty($trangThai)) ? $trangThai : ($newStock > 0 ? 'Còn hàng' : 'Hết hàng');
            $stmt = $pdo->prepare("UPDATE MON_DICH_VU SET soLuongTon = ?, trangThai = ? WHERE maMon = ?");
            $stmt->execute([$newStock, $newStatus, $maMon]);
        } else if ($addQuantity > 0) {
            $stmt = $pdo->prepare("UPDATE MON_DICH_VU SET soLuongTon = soLuongTon + ?, trangThai = 'Còn hàng' WHERE maMon = ?");
            $stmt->execute([$addQuantity, $maMon]);
        } else if (!empty($trangThai)) {
            $stmt = $pdo->prepare("UPDATE MON_DICH_VU SET trangThai = ? WHERE maMon = ?");
            $stmt->execute([$trangThai, $maMon]);
        }

        $updated = $pdo->prepare("SELECT * FROM MON_DICH_VU WHERE maMon = ?");
        $updated->execute([$maMon]);
        $mon = $updated->fetch();

        logSystemAction($pdo, $maNhanVien, 'Cập nhật kho món', 'MON_DICH_VU', $maMon, '', "Cập nhật tồn kho món {$mon['tenMon']} thành {$mon['soLuongTon']}");

        jsonResponse(true, "Đã cập nhật món '{$mon['tenMon']}': Còn {$mon['soLuongTon']} {$mon['donViTinh']}.", $mon);
        break;

    case 'refill_all':
        $amount = intval($_POST['amount'] ?? 50);
        if ($amount <= 0) $amount = 50;
        $pdo->exec("UPDATE MON_DICH_VU SET soLuongTon = soLuongTon + {$amount}, trangThai = 'Còn hàng'");
        logSystemAction($pdo, $maNhanVien, 'Nạp kho hàng loạt', 'MON_DICH_VU', 'ALL', '', "Nạp thêm +{$amount} phần cho tất cả món");
        jsonResponse(true, "Đã nạp thêm +{$amount} phần cho tất cả các món trong thực đơn!");
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
