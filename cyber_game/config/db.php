<?php
/**
 * Database Connection & Global Helpers
 * Hệ thống Quản lý Khách chơi Game - CyberNet Gaming
 * Tương thích XAMPP (Apache + MySQL/MariaDB + PHP 8+)
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    @session_start();
}

$db_host = 'localhost';
$db_port = '3306';
$db_name = 'quan_net_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    // If running as API return JSON, otherwise show error
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Không thể kết nối CSDL XAMPP. Vui lòng bật MySQL trong XAMPP Control Panel và chạy install.php',
            'error_detail' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die("Lỗi kết nối CSDL: " . htmlspecialchars($e->getMessage()) . ". Hãy đảm bảo đã khởi động MySQL trong XAMPP.");
}

/**
 * Trả về JSON Response chuẩn
 */
function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Ghi nhật ký hệ thống (NHAT_KY_HE_THONG)
 */
function logSystemAction($pdo, $maNhanVien, $hanhDong, $doiTuong, $maDoiTuong = null, $noiDungCu = null, $noiDungMoi = null, $ketQua = 'Thành công') {
    try {
        $stmtId = $pdo->query("SELECT MAX(CAST(SUBSTRING(maNhatKy, 3) AS UNSIGNED)) as max_id FROM NHAT_KY_HE_THONG");
        $nextNum = ($stmtId->fetch()['max_id'] ?? 0) + 1;
        $maNhatKy = 'NK' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO NHAT_KY_HE_THONG 
            (maNhatKy, maNhanVien, hanhDong, doiTuong, maDoiTuong, noiDungCu, noiDungMoi, thoiGian, ketQua)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$maNhatKy, $maNhanVien, $hanhDong, $doiTuong, $maDoiTuong, $noiDungCu, $noiDungMoi, $ketQua]);
    } catch (Exception $e) {
        // Log silently
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Kiểm tra quyền khách chơi đã đăng nhập
 */
function checkGamerAuth() {
    if (empty($_SESSION['gamer_user'])) {
        jsonResponse(false, 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', null, 401);
    }
    return $_SESSION['gamer_user'];
}

/**
 * Kiểm tra quyền nhân viên / quản lý đã đăng nhập
 */
function checkStaffAuth() {
    if (empty($_SESSION['staff_user'])) {
        jsonResponse(false, 'Yêu cầu quyền truy cập nhân viên/quản lý.', null, 403);
    }
    return $_SESSION['staff_user'];
}

/**
 * Tạo mã tự tăng theo tiền tố
 */
function generateNextCode($pdo, $table, $column, $prefix, $padLength = 3) {
    $prefixLen = strlen($prefix) + 1;
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING({$column}, {$prefixLen}) AS UNSIGNED)) as max_id FROM {$table} WHERE {$column} LIKE '{$prefix}%'");
    $nextNum = ($stmt->fetch()['max_id'] ?? 0) + 1;
    return $prefix . str_pad($nextNum, $padLength, '0', STR_PAD_LEFT);
}

/**
 * Tự động kiểm tra và chốt kết thúc các phiên chơi đã hết số dư trên toàn bộ máy trạm (Server-side Auto-Cleanup)
 */
function autoCloseExpiredSessions($pdo) {
    try {
        $sql = "
            SELECT pc.maPhien, pc.thoiGianBatDau as pcThoiGianBatDau, pc.chiPhi as pcChiPhi, pc.maTaiKhoan,
                   sd.maSuDung, sd.maMay, sd.thoiGianBatDau as sdThoiGianBatDau, sd.donGiaApDung, sd.thanhTien as sdThanhTien,
                   tk.soDu, tk.tenDangNhap, mt.tenMay
            FROM PHIEN_CHOI pc
            JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL
            JOIN MAY_TRAM mt ON sd.maMay = mt.maMay
            JOIN TAI_KHOAN tk ON pc.maTaiKhoan = tk.maTaiKhoan
            WHERE pc.trangThai = 'Đang hoạt động'
        ";
        $stmt = $pdo->query($sql);
        $activeSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($activeSessions)) {
            return 0;
        }

        $now = new DateTime();
        $closedCount = 0;

        foreach ($activeSessions as $sess) {
            $donGiaGio = (float)$sess['donGiaApDung'];
            if ($donGiaGio <= 0) continue;
            $donGiaPhut = $donGiaGio / 60;

            $soDu = (float)$sess['soDu'];
            $alreadyChargedSD = (float)$sess['sdThanhTien'];

            $sdStart = new DateTime($sess['sdThoiGianBatDau']);
            $elapsedSDMinutes = max(0, floor(($now->getTimestamp() - $sdStart->getTimestamp()) / 60));

            $shouldChargeSD = round($elapsedSDMinutes * $donGiaPhut);
            $deltaToDeduct = max(0, $shouldChargeSD - $alreadyChargedSD);

            // Nếu tài khoản không còn tiền hoặc số tiền cần trừ vượt quá số dư hiện có
            if ($soDu <= 0 || $deltaToDeduct >= $soDu) {
                // Phiên chơi đã hết số dư -> Cưỡng chế đóng phiên ngay lập tức
                $affordableMins = $donGiaPhut > 0 ? floor($soDu / $donGiaPhut) : 0;
                $finalSDMinutes = floor($alreadyChargedSD / $donGiaPhut) + $affordableMins;

                $endTime = (clone $sdStart)->modify("+{$finalSDMinutes} minutes");
                if ($endTime > $now || $finalSDMinutes <= 0) {
                    $endTime = $now;
                }
                $endTimeStr = $endTime->format('Y-m-d H:i:s');

                $pcStart = new DateTime($sess['pcThoiGianBatDau']);
                $totalPCMinutes = max(1, floor(($endTime->getTimestamp() - $pcStart->getTimestamp()) / 60));
                $finalSDCost = $alreadyChargedSD + $soDu;

                $pdo->beginTransaction();
                try {
                    // 1. Chốt chi tiết sử dụng máy
                    $stmtCloseSD = $pdo->prepare("
                        UPDATE CHI_TIET_SU_DUNG_MAY 
                        SET thoiGianKetThuc = ?, thanhTien = ?
                        WHERE maSuDung = ?
                    ");
                    $stmtCloseSD->execute([$endTimeStr, $finalSDCost, $sess['maSuDung']]);

                    // 2. Chốt phiên chơi
                    $stmtClosePC = $pdo->prepare("
                        UPDATE PHIEN_CHOI 
                        SET thoiGianKetThuc = ?, tongThoiGianPhut = ?, chiPhi = chiPhi + ?, trangThai = 'Đã kết thúc'
                        WHERE maPhien = ?
                    ");
                    $stmtClosePC->execute([$endTimeStr, $totalPCMinutes, $soDu, $sess['maPhien']]);

                    // 3. Khóa số dư tài khoản về 0đ
                    $stmtZero = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = 0 WHERE maTaiKhoan = ?");
                    $stmtZero->execute([$sess['maTaiKhoan']]);

                    // 4. Giải phóng máy trạm về trạng thái 'Trống'
                    $stmtFree = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Trống' WHERE maMay = ?");
                    $stmtFree->execute([$sess['maMay']]);

                    // 5. Ghi nhật ký hệ thống
                    logSystemAction(
                        $pdo,
                        'NV01',
                        'Tự động ngắt phiên (Hết tiền)',
                        'PHIEN_CHOI',
                        $sess['maPhien'],
                        'Đang chơi',
                        "Hệ thống tự động ngắt máy {$sess['maMay']} ({$sess['tenMay']}) do tài khoản {$sess['tenDangNhap']} hết số dư. Chốt: {$totalPCMinutes}p, chi phí: " . number_format($finalSDCost, 0, ',', '.') . "đ"
                    );

                    $pdo->commit();
                    $closedCount++;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Lỗi tự động đóng phiên hết tiền: " . $e->getMessage());
                }
            } else if ($deltaToDeduct > 0) {
                // Vẫn còn tiền: Trừ dần cước phí theo phút vào CSDL để đồng bộ dữ liệu
                $pdo->beginTransaction();
                try {
                    $stmtDed = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = GREATEST(0, soDu - ?) WHERE maTaiKhoan = ?");
                    $stmtDed->execute([$deltaToDeduct, $sess['maTaiKhoan']]);

                    $stmtUpSD = $pdo->prepare("UPDATE CHI_TIET_SU_DUNG_MAY SET thanhTien = thanhTien + ? WHERE maSuDung = ?");
                    $stmtUpSD->execute([$deltaToDeduct, $sess['maSuDung']]);

                    $pcStart = new DateTime($sess['pcThoiGianBatDau']);
                    $totalElapsedMins = max(1, floor(($now->getTimestamp() - $pcStart->getTimestamp()) / 60));

                    $stmtUpPC = $pdo->prepare("UPDATE PHIEN_CHOI SET tongThoiGianPhut = ?, chiPhi = chiPhi + ? WHERE maPhien = ?");
                    $stmtUpPC->execute([$totalElapsedMins, $deltaToDeduct, $sess['maPhien']]);

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                }
            }
        }
        return $closedCount;
    } catch (Exception $e) {
        error_log("Lỗi quét autoCloseExpiredSessions: " . $e->getMessage());
        return 0;
    }
}

