<?php
/**
 * Admin Promotions Management API
 * UC14: Quản lý chương trình khuyến mãi (Thêm, sửa, bật/tắt, lưu lịch sử)
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$staff = checkStaffAuth();
$maNhanVien = $staff['maNhanVien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'list_promotions':
        $stmt = $pdo->query("SELECT * FROM CHUONG_TRINH_KHUYEN_MAI ORDER BY maKhuyenMai ASC");
        $promotions = $stmt->fetchAll();

        // Lấy lịch sử biến động khuyến mãi gần đây
        $stmtHistory = $pdo->query("
            SELECT ls.*, km.tenChuongTrinh, nv.hoTen as tenNhanVien
            FROM LICH_SU_KHUYEN_MAI ls
            JOIN CHUONG_TRINH_KHUYEN_MAI km ON ls.maKhuyenMai = km.maKhuyenMai
            JOIN NHAN_VIEN nv ON ls.maNhanVien = nv.maNhanVien
            ORDER BY ls.thoiGian DESC LIMIT 15
        ");
        $history = $stmtHistory->fetchAll();

        jsonResponse(true, 'Danh sách chương trình khuyến mãi', [
            'promotions' => $promotions,
            'history' => $history
        ]);
        break;

    case 'save_promotion':
        $maKhuyenMai = trim($_POST['maKhuyenMai'] ?? '');
        $tenChuongTrinh = trim($_POST['tenChuongTrinh'] ?? '');
        $mucNapToiThieu = (float)($_POST['mucNapToiThieu'] ?? 0);
        $soTienKhuyenMai = (float)($_POST['soTienKhuyenMai'] ?? 0);
        $ngayBatDau = $_POST['ngayBatDau'] ?? '';
        $ngayKetThuc = $_POST['ngayKetThuc'] ?? '';
        $trangThai = $_POST['trangThai'] ?? 'Hoạt động';

        if (empty($tenChuongTrinh) || empty($ngayBatDau) || empty($ngayKetThuc) || $mucNapToiThieu <= 0 || $soTienKhuyenMai <= 0) {
            jsonResponse(false, 'Vui lòng điền đầy đủ và chính xác các thông tin chương trình khuyến mãi.');
        }

        if ($ngayKetThuc < $ngayBatDau) {
            jsonResponse(false, 'Ngày kết thúc không được nhỏ hơn ngày bắt đầu.');
        }

        $pdo->beginTransaction();
        try {
            $maLichSu = generateNextCode($pdo, 'LICH_SU_KHUYEN_MAI', 'maLichSu', 'LS', 4);

            if (empty($maKhuyenMai)) {
                // Thêm mới
                $maKhuyenMai = generateNextCode($pdo, 'CHUONG_TRINH_KHUYEN_MAI', 'maKhuyenMai', 'KM', 2);
                $stmtInsert = $pdo->prepare("
                    INSERT INTO CHUONG_TRINH_KHUYEN_MAI 
                    (maKhuyenMai, tenChuongTrinh, mucNapToiThieu, soTienKhuyenMai, ngayBatDau, ngayKetThuc, trangThai)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([$maKhuyenMai, $tenChuongTrinh, $mucNapToiThieu, $soTienKhuyenMai, $ngayBatDau, $ngayKetThuc, $trangThai]);

                // Lưu lịch sử
                $noiDungMoi = "Thêm mới: {$tenChuongTrinh} (Nạp tối thiểu " . number_format($mucNapToiThieu) . "đ, Tặng " . number_format($soTienKhuyenMai) . "đ, Từ {$ngayBatDau} đến {$ngayKetThuc})";
                $stmtLS = $pdo->prepare("
                    INSERT INTO LICH_SU_KHUYEN_MAI (maLichSu, maKhuyenMai, maNhanVien, thaoTac, noiDungCu, noiDungMoi, thoiGian)
                    VALUES (?, ?, ?, 'Thêm mới', NULL, ?, NOW())
                ");
                $stmtLS->execute([$maLichSu, $maKhuyenMai, $maNhanVien, $noiDungMoi]);

                $msg = "Thêm chương trình khuyến mãi mới thành công!";
            } else {
                // Sửa
                $stmtOld = $pdo->prepare("SELECT * FROM CHUONG_TRINH_KHUYEN_MAI WHERE maKhuyenMai = ?");
                $stmtOld->execute([$maKhuyenMai]);
                $old = $stmtOld->fetch();

                if (!$old) {
                    jsonResponse(false, 'Chương trình khuyến mãi không tồn tại.');
                }

                $noiDungCu = "Tên: {$old['tenChuongTrinh']}, Nạp: {$old['mucNapToiThieu']}, Tặng: {$old['soTienKhuyenMai']}, Hạn: {$old['ngayBatDau']} đến {$old['ngayKetThuc']}";
                $noiDungMoi = "Tên: {$tenChuongTrinh}, Nạp: {$mucNapToiThieu}, Tặng: {$soTienKhuyenMai}, Hạn: {$ngayBatDau} đến {$ngayKetThuc}";

                $stmtUpdate = $pdo->prepare("
                    UPDATE CHUONG_TRINH_KHUYEN_MAI 
                    SET tenChuongTrinh = ?, mucNapToiThieu = ?, soTienKhuyenMai = ?, ngayBatDau = ?, ngayKetThuc = ?, trangThai = ?
                    WHERE maKhuyenMai = ?
                ");
                $stmtUpdate->execute([$tenChuongTrinh, $mucNapToiThieu, $soTienKhuyenMai, $ngayBatDau, $ngayKetThuc, $trangThai, $maKhuyenMai]);

                // Lưu lịch sử
                $stmtLS = $pdo->prepare("
                    INSERT INTO LICH_SU_KHUYEN_MAI (maLichSu, maKhuyenMai, maNhanVien, thaoTac, noiDungCu, noiDungMoi, thoiGian)
                    VALUES (?, ?, ?, 'Chỉnh sửa', ?, ?, NOW())
                ");
                $stmtLS->execute([$maLichSu, $maKhuyenMai, $maNhanVien, $noiDungCu, $noiDungMoi]);

                $msg = "Cập nhật chương trình khuyến mãi thành công!";
            }

            $pdo->commit();
            jsonResponse(true, $msg);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi lưu khuyến mãi: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'toggle_status':
        $maKhuyenMai = trim($_POST['maKhuyenMai'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM CHUONG_TRINH_KHUYEN_MAI WHERE maKhuyenMai = ?");
        $stmt->execute([$maKhuyenMai]);
        $km = $stmt->fetch();

        if (!$km) {
            jsonResponse(false, 'Chương trình khuyến mãi không tồn tại.');
        }

        $newStatus = ($km['trangThai'] === 'Hoạt động') ? 'Tạm dừng' : 'Hoạt động';

        $pdo->beginTransaction();
        try {
            $stmtUp = $pdo->prepare("UPDATE CHUONG_TRINH_KHUYEN_MAI SET trangThai = ? WHERE maKhuyenMai = ?");
            $stmtUp->execute([$newStatus, $maKhuyenMai]);

            $maLichSu = generateNextCode($pdo, 'LICH_SU_KHUYEN_MAI', 'maLichSu', 'LS', 4);
            $stmtLS = $pdo->prepare("
                INSERT INTO LICH_SU_KHUYEN_MAI (maLichSu, maKhuyenMai, maNhanVien, thaoTac, noiDungCu, noiDungMoi, thoiGian)
                VALUES (?, ?, ?, 'Đổi trạng thái', ?, ?, NOW())
            ");
            $stmtLS->execute([$maLichSu, $maKhuyenMai, $maNhanVien, "Trạng thái cũ: {$km['trangThai']}", "Trạng thái mới: {$newStatus}"]);

            $pdo->commit();
            jsonResponse(true, "Đã chuyển chương trình sang trạng thái '{$newStatus}'.", ['trangThai' => $newStatus]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Lỗi đổi trạng thái: ' . $e->getMessage(), null, 500);
        }
        break;

    default:
        jsonResponse(false, 'Hành động không hợp lệ.', null, 400);
}
