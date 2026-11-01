<?php
/**
 * Client Session API
 * UC5: Xem thông tin phiên chơi và số dư (Real-time Heartbeat, Trừ tiền theo phút & Cảnh báo số dư)
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = checkGamerAuth();
$maTaiKhoan = $user['maTaiKhoan'];
$maMay = $user['maMay'];
$maPhien = $user['maPhien'];

$action = $_GET['action'] ?? ($_POST['action'] ?? 'heartbeat');

// 1. Kiểm tra tài khoản
$stmtTK = $pdo->prepare("SELECT * FROM TAI_KHOAN WHERE maTaiKhoan = ?");
$stmtTK->execute([$maTaiKhoan]);
$taiKhoan = $stmtTK->fetch();

if (!$taiKhoan) {
    jsonResponse(false, 'Tài khoản không tồn tại.', null, 404);
}

if ($taiKhoan['trangThai'] === 'Đang khóa') {
    unset($_SESSION['gamer_user']);
    jsonResponse(false, 'Tài khoản của bạn đã bị khóa bởi người quản lý.', ['force_logout' => true], 403);
}

// 2. Truy xuất phiên chơi hiện tại (MySQL tính toán chính xác số giây theo đồng hồ máy chủ)
$stmtPC = $pdo->prepare("
    SELECT pc.*, 
           TIMESTAMPDIFF(SECOND, pc.thoiGianBatDau, NOW()) AS elapsedTotalSeconds,
           TIMESTAMPDIFF(SECOND, sd.thoiGianBatDau, NOW()) AS elapsedSdSeconds,
           sd.maSuDung, sd.maMay, sd.thoiGianBatDau as thoiGianDoiMay, sd.donGiaApDung, sd.thanhTien as thanhTienHienTai,
           mt.tenMay, mt.viTri, lm.tenLoai, lm.moTa
    FROM PHIEN_CHOI pc
    JOIN CHI_TIET_SU_DUNG_MAY sd ON pc.maPhien = sd.maPhien AND sd.thoiGianKetThuc IS NULL
    JOIN MAY_TRAM mt ON sd.maMay = mt.maMay
    JOIN LOAI_MAY lm ON mt.maLoaiMay = lm.maLoaiMay
    WHERE pc.maPhien = ? AND pc.trangThai = 'Đang hoạt động'
");
$stmtPC->execute([$maPhien]);
$session = $stmtPC->fetch();

if (!$session) {
    unset($_SESSION['gamer_user']);
    jsonResponse(false, 'Phiên chơi đã kết thúc.', ['force_logout' => true]);
}

$donGiaGio = (float)$session['donGiaApDung'];
$donGiaPhut = $donGiaGio / 60; // Chi phí mỗi phút

// ====================================================================
// XỬ LÝ CÁC CHỨC NĂNG THỬ NGHIỆM / MÔ PHỎNG (TESTING & DEMO CONTROLS)
// ====================================================================
if ($action === 'set_test_balance') {
    // Đặt số dư kiểm thử (ví dụ: 1500đ để test cảnh báo vàng, 0đ để test tự ngắt)
    $newBalance = max(0, (float)($_POST['balance'] ?? ($_GET['balance'] ?? 0)));
    $stmtSet = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = ? WHERE maTaiKhoan = ?");
    $stmtSet->execute([$newBalance, $maTaiKhoan]);
    $taiKhoan['soDu'] = $newBalance;
} elseif ($action === 'simulate_fast_forward') {
    // Tua nhanh số phút: Trừ tiền tương ứng VÀ lùi thoiGianBatDau để đồng hồ bấm giờ tăng theo
    $mins = max(1, (int)($_POST['minutes'] ?? ($_GET['minutes'] ?? 5)));
    $costToDeduct = round($mins * $donGiaPhut);

    $pdo->beginTransaction();
    try {
        $stmtDed = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = GREATEST(0, soDu - ?) WHERE maTaiKhoan = ?");
        $stmtDed->execute([$costToDeduct, $maTaiKhoan]);

        // Đẩy lùi thời gian bắt đầu của CHI_TIET_SU_DUNG_MAY
        $stmtUpSD = $pdo->prepare("
            UPDATE CHI_TIET_SU_DUNG_MAY 
            SET thanhTien = thanhTien + ?,
                thoiGianBatDau = DATE_SUB(thoiGianBatDau, INTERVAL ? MINUTE)
            WHERE maSuDung = ?
        ");
        $stmtUpSD->execute([$costToDeduct, $mins, $session['maSuDung']]);

        // Đẩy lùi thời gian bắt đầu của PHIEN_CHOI
        $stmtUpPC = $pdo->prepare("
            UPDATE PHIEN_CHOI 
            SET tongThoiGianPhut = tongThoiGianPhut + ?, 
                chiPhi = chiPhi + ?,
                thoiGianBatDau = DATE_SUB(thoiGianBatDau, INTERVAL ? MINUTE)
            WHERE maPhien = ?
        ");
        $stmtUpPC->execute([$mins, $costToDeduct, $mins, $maPhien]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }

    // Tải lại phiên chơi mới nhất để tính toán lại các số liệu thời gian thực
    $stmtPC->execute([$maPhien]);
    $session = $stmtPC->fetch();

    // Tải lại số dư mới nhất
    $stmtTK->execute([$maTaiKhoan]);
    $taiKhoan = $stmtTK->fetch();
}

// ====================================================================
// TÍNH TOÁN VÀ TỰ ĐỘNG TRỪ TIỀN THEO PHÚT (REAL-TIME PER-MINUTE DEDUCTION)
// ====================================================================
$elapsedSDSeconds = max(0, (int)($session['elapsedSdSeconds'] ?? 0));
$elapsedSDMinutes = floor($elapsedSDSeconds / 60);

$totalElapsedSeconds = max(0, (int)($session['elapsedTotalSeconds'] ?? $elapsedSDSeconds));
$totalElapsedMinutes = floor($totalElapsedSeconds / 60);

// Chi phí thực tế nên tính cho đoạn máy này
$shouldChargeSD = round($elapsedSDMinutes * $donGiaPhut);
$alreadyChargedSD = (float)$session['thanhTienHienTai'];
$deltaToDeduct = max(0, $shouldChargeSD - $alreadyChargedSD);

// Nếu có chênh lệch tiền phút mới phát sinh, trừ trực tiếp vào CSDL
if ($deltaToDeduct > 0 && (float)$taiKhoan['soDu'] > 0) {
    $actualDeduct = min((float)$taiKhoan['soDu'], $deltaToDeduct);

    $stmtUpTK = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = GREATEST(0, soDu - ?) WHERE maTaiKhoan = ?");
    $stmtUpTK->execute([$actualDeduct, $maTaiKhoan]);

    $stmtUpSD = $pdo->prepare("UPDATE CHI_TIET_SU_DUNG_MAY SET thanhTien = thanhTien + ? WHERE maSuDung = ?");
    $stmtUpSD->execute([$actualDeduct, $session['maSuDung']]);

    $stmtUpPC = $pdo->prepare("
        UPDATE PHIEN_CHOI 
        SET tongThoiGianPhut = ?, chiPhi = chiPhi + ? 
        WHERE maPhien = ?
    ");
    $stmtUpPC->execute([$totalElapsedMinutes, $actualDeduct, $maPhien]);

    // Cập nhật lại biến bộ nhớ
    $taiKhoan['soDu'] = max(0, (float)$taiKhoan['soDu'] - $actualDeduct);
    $session['thanhTienHienTai'] += $actualDeduct;
}

// Tổng chi phí toàn phiên (bao gồm các đoạn máy trước nếu đã đổi máy)
$stmtPastSD = $pdo->prepare("SELECT SUM(thanhTien) as pastCost FROM CHI_TIET_SU_DUNG_MAY WHERE maPhien = ? AND thoiGianKetThuc IS NOT NULL");
$stmtPastSD->execute([$maPhien]);
$pastCost = (float)($stmtPastSD->fetch()['pastCost'] ?? 0);
$totalCurrentCost = $pastCost + (float)$session['thanhTienHienTai'];

// Số dư thực tế hiện tại trong CSDL
$currentBalance = (float)$taiKhoan['soDu'];

// Ước tính số phút chơi còn lại
$remainingMinutes = $donGiaGio > 0 ? floor(($currentBalance / $donGiaGio) * 60) : 999;

// Trạng thái cảnh báo số dư
$isOutOfMoney = ($currentBalance <= 0);
$isCriticalWarning = ($remainingMinutes <= 5 && $remainingMinutes > 0);
$isLowBalanceWarning = ($remainingMinutes <= 15 && $remainingMinutes > 0);

// ====================================================================
// NẾU HẾT TIỀN (SỐ DƯ = 0): TỰ ĐỘNG KHÓA PHIÊN & NGẮT KẾT NỐI MÁY TRẠM
// ====================================================================
if ($isOutOfMoney) {
    $pdo->beginTransaction();
    try {
        // Đóng đoạn sử dụng máy
        $stmtCloseSD = $pdo->prepare("UPDATE CHI_TIET_SU_DUNG_MAY SET thoiGianKetThuc = NOW(), thanhTien = ? WHERE maSuDung = ?");
        $stmtCloseSD->execute([$session['thanhTienHienTai'], $session['maSuDung']]);

        // Đóng phiên chơi
        $stmtClosePC = $pdo->prepare("UPDATE PHIEN_CHOI SET thoiGianKetThuc = NOW(), tongThoiGianPhut = ?, chiPhi = ?, trangThai = 'Đã kết thúc' WHERE maPhien = ?");
        $stmtClosePC->execute([$totalElapsedMinutes, $totalCurrentCost, $maPhien]);

        // Khóa số dư = 0
        $stmtZero = $pdo->prepare("UPDATE TAI_KHOAN SET soDu = 0 WHERE maTaiKhoan = ?");
        $stmtZero->execute([$maTaiKhoan]);

        // Trả máy trạm về trạng thái Trống
        $stmtFree = $pdo->prepare("UPDATE MAY_TRAM SET trangThai = 'Trống' WHERE maMay = ?");
        $stmtFree->execute([$session['maMay']]);

        logSystemAction(
            $pdo,
            'NV01',
            'Tự động ngắt phiên (Hết tiền)',
            'PHIEN_CHOI',
            $maPhien,
            'Đang chơi',
            "Máy {$session['maMay']} tự động ngắt kết nối do tài khoản {$taiKhoan['tenDangNhap']} hết số dư (0đ)"
        );

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }

    unset($_SESSION['gamer_user']);

    jsonResponse(false, 'Tài khoản của bạn đã hết số dư (0 đ). Phiên chơi tự động ngắt kết nối và đóng máy.', [
        'force_logout' => true,
        'out_of_money' => true,
        'remaining_balance' => 0,
        'remaining_minutes' => 0
    ]);
}

// Trả về dữ liệu phiên chơi thời gian thực
jsonResponse(true, 'Dữ liệu phiên chơi thời gian thực', [
    'station' => [
        'maMay' => $session['maMay'],
        'tenMay' => $session['tenMay'],
        'viTri' => $session['viTri'],
        'tenLoai' => $session['tenLoai'],
        'moTa' => $session['moTa'] ?? '',
        'donGiaGio' => $donGiaGio,
        'donGiaPhut' => round($donGiaPhut)
    ],
    'session' => [
        'maPhien' => $session['maPhien'],
        'thoiGianBatDau' => $session['thoiGianBatDau'],
        'totalElapsedMinutes' => $totalElapsedMinutes,
        'totalElapsedSeconds' => $totalElapsedSeconds,
        'currentCost' => $totalCurrentCost,
        'soDuHienTai' => $currentBalance,
        'remainingMinutes' => $remainingMinutes,
        'lowBalanceWarning' => $isLowBalanceWarning,
        'criticalWarning' => $isCriticalWarning,
        'lowBalanceMessage' => $isCriticalWarning 
            ? "NGUY CẤP: Thời gian chơi chỉ còn {$remainingMinutes} phút! Máy sắp tự ngắt kết nối."
            : ($isLowBalanceWarning ? "Cảnh báo: Thời gian chơi còn lại dưới 15 phút ({$remainingMinutes} phút). Vui lòng nạp thêm tiền!" : null)
    ]
]);
