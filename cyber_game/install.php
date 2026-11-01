<?php
/**
 * Trình cài đặt CSDL tự động 1-Click (XAMPP Auto-installer)
 * Hệ thống Quản lý Khách chơi Game - CyberNet Gaming
 */

$host = 'localhost';
$port = '3306';
$user = 'root';
$pass = '';

$message = '';
$status = '';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['auto']) || (php_sapi_name() === 'cli' && in_array('auto=1', $argv ?? []));

if ($isPost) {
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $sqlPath = __DIR__ . '/sql/database.sql';
        if (!file_exists($sqlPath)) {
            throw new Exception("Không tìm thấy file sql/database.sql");
        }
        
        $sql = file_get_contents($sqlPath);
        
        // Execute multi queries
        $pdo->exec($sql);
        
        $message = "Khởi tạo CSDL 'quan_net_db' và 16 bảng dữ liệu mẫu thành công!";
        $status = "success";
    } catch (Exception $e) {
        $message = "Lỗi cài đặt CSDL: " . $e->getMessage();
        $status = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt CSDL - CyberNet Gaming System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Orbitron:wght@600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #090d16;
            --card-bg: rgba(18, 24, 38, 0.85);
            --primary: #00f2fe;
            --secondary: #4facfe;
            --accent: #10b981;
            --danger: #ef4444;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --border: rgba(255, 255, 255, 0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body {
            background: radial-gradient(circle at 50% 10%, #172554, var(--bg-dark));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            padding: 20px;
        }
        .container {
            max-width: 650px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            backdrop-filter: blur(12px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
        }
        .logo-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            color: var(--primary);
            text-align: center;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 24px;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .alert.success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--accent);
            color: #34d399;
        }
        .alert.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: #f87171;
        }
        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
        }
        .info-box h4 {
            color: var(--secondary);
            margin-bottom: 10px;
            font-size: 14px;
        }
        .info-box ul {
            list-style: none;
            padding-left: 0;
        }
        .info-box li {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-box li::before {
            content: "✓";
            color: var(--accent);
            font-weight: bold;
        }
        .btn-install {
            width: 100%;
            background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
            border: none;
            color: #050b14;
            font-weight: 700;
            padding: 14px;
            font-size: 16px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 242, 254, 0.3);
        }
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 242, 254, 0.5);
        }
        .nav-links {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 14px;
        }
        .nav-links a {
            color: var(--primary);
            text-decoration: none;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="logo-title">CYBERNET GAMING</h1>
        <p class="subtitle">Khởi tạo và cấu hình cơ sở dữ liệu XAMPP (16 Bảng dữ liệu chuẩn)</p>

        <?php if ($message): ?>
            <div class="alert <?= $status ?>">
                <strong><?= $status === 'success' ? 'Thành công:' : 'Lỗi:' ?></strong> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h4>Hệ thống CSDL bao gồm:</h4>
            <ul>
                <li>16 Bảng quan hệ chuẩn 3NF từ phân tích đồ án (KHACH_CHOI, TAI_KHOAN, PHIEN_CHOI, MAY_TRAM, BANG_GIA, GIAO_DICH_NAP_TIEN, MON_DICH_VU, DON_DICH_VU, NHAN_VIEN...)</li>
                <li>Dữ liệu 20 máy trạm thuộc 3 phân khu: Tiêu Chuẩn (8k/h), VIP Gaming (12k/h), Thi Đấu Pro (18k/h)</li>
                <li>Tài khoản nhân viên: <code>admin</code> (Quản lý), <code>thungan1</code>, <code>thungan2</code> (Mật khẩu: <code>123456</code>)</li>
                <li>Tài khoản khách mẫu: <code>gamer_an</code>, <code>hoang_pro</code>, <code>long_sniper</code>, <code>huy_faker</code> (Mật khẩu: <code>123456</code>)</li>
                <li>Menu món ăn & nước uống kèm ảnh sống động</li>
            </ul>
        </div>

        <form method="POST">
            <button type="submit" class="btn-install">
                <?= $status === 'success' ? 'Cài đặt lại CSDL (Reset Data)' : 'Khởi tạo Cơ Sở Dữ Liệu Ngay' ?>
            </button>
        </form>

        <div class="nav-links">
            <a href="index.html">← Trang chủ điều hướng</a>
            <a href="login.php">Màn hình đăng nhập</a>
            <a href="admin.php">Màn hình quản lý thu ngân</a>
        </div>
    </div>
</body>
</html>
