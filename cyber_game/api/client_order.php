<?php
/**
 * Client F&B Order API
 * UC8: Gọi đồ ăn / nước uống trực tiếp từ máy trạm
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = checkGamerAuth();
$maTaiKhoan = $user['maTaiKhoan'];
$maMay = $user['maMay'];
$maPhien = $user['maPhien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_menu':
        $category = $_GET['category'] ?? '';
        $sql = "SELECT * FROM MON_DICH_VU WHERE trangThai = 'Còn hàng' AND soLuongTon > 0";
        $params = [];

        if (!empty($category)) {
            $sql .= " AND loaiMon = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY loaiMon, tenMon ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Lấy danh sách các phân loại món
        $categoriesStmt = $pdo->query("SELECT DISTINCT loaiMon FROM MON_DICH_VU WHERE trangThai = 'Còn hàng'");
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

        jsonResponse(true, 'Thực đơn dịch vụ', [
            'categories' => $categories,
            'items' => $items
        ]);
        break;

    case 'create_order':
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $input = (is_array($jsonData)) ? $jsonData : $_POST;

        $items = $input['items'] ?? ($_POST['items'] ?? []);
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        $hinhThucThanhToan = $input['hinhThucThanhToan'] ?? ($_POST['hinhThucThanhToan'] ?? 'Trừ số dư');

        if (empty($items) || !is_array($items)) {
            jsonResponse(false, 'Giỏ hàng của bạn đang trống.');
        }

        // Kiểm tra phiên hoạt động
        $stmtPC = $pdo->prepare("SELECT * FROM PHIEN_CHOI WHERE maPhien = ? AND trangThai = 'Đang hoạt động'");
        $stmtPC->execute([$maPhien]);
        if (!$stmtPC->fetch()) {
            jsonResponse(false, 'Phiên chơi không hợp lệ hoặc đã kết thúc.');
        }

        // Đồng bộ máy trạm thực tế đang ngồi (hỗ trợ trường hợp đã chuyển máy - BUG_05)
        $stmtActiveMay = $pdo->prepare("
            SELECT maMay FROM CHI_TIET_SU_DUNG_MAY 
            WHERE maPhien = ? AND thoiGianKetThuc IS NULL 
            ORDER BY thoiGianBatDau DESC LIMIT 1
        ");
        $stmtActiveMay->execute([$maPhien]);
        $currentActiveMay = $stmtActiveMay->fetchColumn();
        if ($currentActiveMay) {
            $maMay = $currentActiveMay;
            $_SESSION['gamer_user']['maMay'] = $currentActiveMay;
        }

        // Gộp các món trùng nhau trong giỏ hàng (BUG_03 - tránh lỗi trùng PRIMARY KEY maDon, maMon)
        $mergedInput = [];
        foreach ($items as $it) {
            $mCode = $it['maMon'] ?? '';
            if (empty($mCode)) continue;
            $qty = max(1, (int)($it['soLuong'] ?? 1));
            $note = trim($it['ghiChu'] ?? '');

            if (!isset($mergedInput[$mCode])) {
                $mergedInput[$mCode] = [
                    'maMon' => $mCode,
                    'soLuong' => $qty,
                    'ghiChu' => $note
                ];
            } else {
                $mergedInput[$mCode]['soLuong'] += $qty;
                if (!empty($note)) {
                    $mergedInput[$mCode]['ghiChu'] = !empty($mergedInput[$mCode]['ghiChu'])
                        ? ($mergedInput[$mCode]['ghiChu'] . '; ' . $note)
                        : $note;
                }
            }
        }

        // Tính tổng tiền & kiểm tra từng món
        $totalOrderCost = 0;
        $verifiedItems = [];

        foreach ($mergedInput as $it) {
            $maMon = $it['maMon'];
            $soLuong = $it['soLuong'];
            $ghiChu = $it['ghiChu'];

            $stmtMon = $pdo->prepare("SELECT * FROM MON_DICH_VU WHERE maMon = ? AND trangThai = 'Còn hàng'");
            $stmtMon->execute([$maMon]);
            $mon = $stmtMon->fetch();

            if (!$mon) {
                jsonResponse(false, "Món '{$maMon}' không tồn tại hoặc đã hết hàng.");
            }

            if ($mon['soLuongTon'] < $soLuong) {
                jsonResponse(false, "Món '{$mon['tenMon']}' chỉ còn {$mon['soLuongTon']} {$mon['donViTinh']}, không đủ số lượng bạn yêu cầu.");
            }

            $thanhTien = $mon['donGia'] * $soLuong;
            $totalOrderCost += $thanhTien;

            $verifiedItems[] = [
                'maMon' => $maMon,
                'tenMon' => $mon['tenMon'],
                'soLuong' => $soLuong,
                'donGia' => $mon['donGia'],
                'ghiChu' => $ghiChu,
                'thanhTien' => $thanhTien
            ];
        }

        // Kiểm tra số dư nếu chọn trừ tài khoản
        if ($hinhThucThanhToan === 'Trừ số dư') {
            $stmtTK = $pdo->prepare("SELECT soDu FROM TAI_KHOAN WHERE maTaiKhoan = ?");
            $stmtTK->execute([$maTaiKhoan]);
            $currentBalance = (float)($stmtTK->fetch()['soDu'] ?? 0);

            if ($currentBalance < $totalOrderCost) {
                jsonResponse(false, "Số dư tài khoản không đủ để thanh toán (Hiện có: " . number_format($currentBalance, 0, ',', '.') . " VNĐ, Cần thanh toán: " . number_format($totalOrderCost, 0, ',', '.') . " VNĐ). Vui lòng nạp thêm tiền hoặc chọn thanh toán tiền mặt tại quầy.");
            }
        }

        $pdo->beginTransaction();
        try {
            $maDon = generateNextCode($pdo, 'DON_DICH_VU', 'maDon', 'DDV', 4);
            $trangThaiThanhToan = ($hinhThucThanhToan === 'Trừ số dư') ? 'Đã thanh toán' : 'Chưa thanh toán';

            // Tạo đơn hàng
            $stmtInsertDon = $pdo->prepare("
                INSERT INTO DON_DICH_VU 
                (maDon, maTaiKhoan, maPhien, maMay, maNhanVien, thoiGianDat, tongTien, hinhThucThanhToan, trangThaiThanhToan, trangThaiDon)
                VALUES (?, ?, ?, ?, NULL, NOW(), ?, ?, ?, 'Chờ xử lý')
            ");
            $stmtInsertDon->execute([$maDon, $maTaiKhoan, $maPhien, $maMay, $totalOrderCost, $hinhThucThanhToan, $trangThaiThanhToan]);

            // Thêm chi tiết & trừ tồn kho (tự động cập nhật Hết hàng nếu tồn về 0)
            $stmtInsertChiTiet = $pdo->prepare("
                INSERT INTO CHI_TIET_DON_DICH_VU (maDon, maMon, soLuong, donGia, ghiChu, thanhTien)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtDeductStock = $pdo->prepare("UPDATE MON_DICH_VU SET soLuongTon = GREATEST(0, soLuongTon - ?), trangThai = IF(soLuongTon - ? <= 0, 'Hết hàng', trangThai) WHERE maMon = ?");

            foreach ($verifiedItems as $it) {
                $stmtInsertChiTiet->execute([$maDon, $it['maMon'], $it['soLuong'], $it['donGia'], $it['ghiChu'], $it['thanhTien']]);
                $stmtDeductStock->execute([$it['soLuong'], $it['soLuong'], $it['maMon']]);
            }

            // Trừ số dư nếu thanh toán bằng tài khoản
            if ($hinhThucThanhToan === 'Trừ số dư') {
                $stmtDeductBal = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = soDu - ? WHERE maTaiKhoan = ?");
                $stmtDeductBal->execute([$totalOrderCost, $maTaiKhoan]);
            }

            $pdo->commit();

            jsonResponse(true, "Đặt món thành công! Đơn hàng #{$maDon} đã được gửi tới quầy dịch vụ. Nhân viên sẽ phục vụ tại máy {$maMay} trong ít phút.", [
                'maDon' => $maDon,
                'tongTien' => $totalOrderCost,
                'hinhThucThanhToan' => $hinhThucThanhToan,
                'trangThaiDon' => 'Chờ xử lý'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi tạo đơn hàng: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'my_orders':
    case 'get_my_orders':
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM DON_DICH_VU LIKE 'lyDoHuy'");
            if (!$checkCol->fetch()) {
                $pdo->exec("ALTER TABLE DON_DICH_VU ADD COLUMN lyDoHuy VARCHAR(255) NULL DEFAULT NULL AFTER trangThaiDon");
            }
        } catch (Exception $eCol) {}

        $stmt = $pdo->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM CHI_TIET_DON_DICH_VU WHERE maDon = d.maDon) as soMon
            FROM DON_DICH_VU d
            WHERE d.maTaiKhoan = ?
            ORDER BY d.thoiGianDat DESC LIMIT 20
        ");
        $stmt->execute([$maTaiKhoan]);
        $orders = $stmt->fetchAll();

        // Lấy chi tiết món cho từng đơn
        foreach ($orders as &$order) {
            if ($order['trangThaiDon'] === 'Đã hủy' && empty($order['lyDoHuy'])) {
                $order['lyDoHuy'] = 'Hết nguyên liệu món này';
            }
            $stmtDetail = $pdo->prepare("
                SELECT c.*, m.tenMon, m.donViTinh 
                FROM CHI_TIET_DON_DICH_VU c
                JOIN MON_DICH_VU m ON c.maMon = m.maMon
                WHERE c.maDon = ?
            ");
            $stmtDetail->execute([$order['maDon']]);
            $order['items'] = $stmtDetail->fetchAll();
        }

        jsonResponse(true, 'Danh sách đơn hàng của bạn', $orders);
        break;

    case 'test_refill_stock':
        $pdo->exec("UPDATE MON_DICH_VU SET soLuongTon = 100, trangThai = 'Còn hàng'");
        jsonResponse(true, 'Đã nạp đầy toàn bộ kho món (100 phần mỗi món) và kích hoạt Còn hàng.');
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
