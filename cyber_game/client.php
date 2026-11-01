<?php
/**
 * Gamer Client Portal (Máy trạm khách chơi)
 * Giao diện cửa sổ ứng dụng desktop tươi sáng, đồng bộ với phong cách phần mềm quản lý
 * Hỗ trợ trừ tiền theo phút, cảnh báo số dư thông minh & bảng điều khiển kiểm thử nghiệp vụ
 */
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['gamer_user'])) {
    header('Location: login.php');
    exit;
}

$gamer = $_SESSION['gamer_user'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Máy Trạm Khách Chơi - CyberNet Gaming</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #e2e8f0;
            color: #0f172a;
            padding: 16px;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .client-window {
            width: 100%;
            max-width: 1360px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
            border: 1px solid #cbd5e1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .client-metrics-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .digital-clock-pill {
            background: #0f172a;
            color: #38bdf8;
            font-family: 'JetBrains Mono', monospace;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 1px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .metric-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 14px;
            display: flex;
            flex-direction: column;
            min-width: 130px;
        }
        .metric-card.balance-card {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .test-toolbar {
            background: #fffbeb;
            border-bottom: 1px solid #fde68a;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
            color: #92400e;
            flex-wrap: wrap;
            gap: 8px;
        }

        .test-btn {
            background: #ffffff;
            border: 1px solid #d97706;
            color: #b45309;
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .test-btn:hover {
            background: #fef3c7;
            transform: translateY(-1px);
        }
        .test-btn.danger {
            border-color: #ef4444;
            color: #dc2626;
        }
        .test-btn.danger:hover {
            background: #fee2e2;
        }

        .food-card-modern {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .food-card-modern:hover {
            border-color: var(--primary);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.08);
            transform: translateY(-2px);
        }

        .food-thumb {
            height: 120px;
            background: linear-gradient(135deg, #eff6ff 0%, #f1f5f9 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            position: relative;
            overflow: hidden;
        }
    </style>
</head>
<body>

    <!-- Main Desktop Application Window -->
    <div class="client-window">

        <!-- 1. Window Header / Titlebar -->
        <header class="window-titlebar">
            <div class="window-title">
                <i class="fas fa-desktop" style="color: #60a5fa;"></i>
                <span>CYBERNET GAMING • PHẦN MỀM MÁY TRẠM KHÁCH CHƠI</span>
            </div>
            <div style="font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 8px;">
                <span class="station-status-badge badge-playing" style="margin-bottom: 0;">
                    <i class="fas fa-circle" style="font-size: 8px;"></i> Đang phục vụ
                </span>
                <span>Khách hàng: <strong><?= htmlspecialchars($gamer['hoTen']) ?></strong> (@<?= htmlspecialchars($gamer['tenDangNhap']) ?>)</span>
                <?php 
                    $rank = $gamer['nhomKhachHang'] ?? 'Hội viên';
                    $rankColor = match($rank) {
                        'VIP' => 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;',
                        'Kim Cương' => 'background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe;',
                        default => 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;'
                    };
                    $rankIcon = match($rank) {
                        'VIP' => 'fas fa-crown',
                        'Kim Cương' => 'fas fa-gem',
                        default => 'fas fa-user-check'
                    };
                ?>
                <span style="<?= $rankColor ?> padding: 2px 8px; border-radius: 999px; font-weight: 700; font-size: 11px;">
                    <i class="<?= $rankIcon ?>"></i> <?= htmlspecialchars($rank) ?>
                </span>
            </div>
        </header>

        <!-- 2. Smart Warning Banners -->
        <div id="lowBalanceBanner" style="display: none; background: #fee2e2; border-bottom: 1px solid #fca5a5; color: #b91c1c; padding: 10px 20px; font-weight: 700; font-size: 13px; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 18px; color: #dc2626; animation: pulse 1s infinite;"></i>
                <span id="lowBalanceText">Cảnh báo: Thời gian chơi của bạn sắp hết (dưới 15 phút)!</span>
            </div>
            <button class="btn-fresh btn-danger btn-sm" onclick="openModal('topupModal')">
                <i class="fas fa-bolt"></i> Nạp tiền ngay để không bị ngắt máy
            </button>
        </div>

        <!-- 3. Top Metrics Bar (2 hàng cân đối: Hàng 1 thông tin & thao tác, Hàng 2 các thẻ chỉ số trực tiếp) -->
        <section class="client-metrics-bar">
            <!-- Row 1: Station Info (Trái) & Action Buttons (Phải) -->
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <!-- Left: Station Info -->
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="background: var(--primary-light); color: var(--primary); width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                        <i class="fas fa-tv"></i>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 16px; font-weight: 800; color: #0f172a;" id="displayStationName">Đang tải máy...</span>
                            <span id="displayStationTierBadge"></span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted);" id="displayStationTier">Đang kiểm tra...</div>
                    </div>
                </div>

                <!-- Right: Action Buttons -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button class="btn-fresh btn-primary btn-sm" onclick="openModal('topupModal')">
                        <i class="fas fa-qrcode"></i> Nạp tiền VietQR
                    </button>
                    <button class="btn-fresh btn-secondary btn-sm" onclick="openProfileModal()">
                        <i class="fas fa-user-circle"></i> Tài khoản
                    </button>
                    <button class="btn-fresh btn-secondary btn-sm" onclick="openHistoryModal()">
                        <i class="fas fa-history"></i> Lịch sử
                    </button>
                    <button class="btn-fresh btn-danger btn-sm" onclick="confirmLogout()">
                        <i class="fas fa-power-off"></i> Đăng xuất
                    </button>
                </div>
            </div>

            <!-- Row 2: Live Metrics Cards (3 thẻ dàn đều 100% chiều ngang, cân đối hoàn hảo) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
                <!-- Card 1: Thời gian chơi (Đồng hồ điện tử) -->
                <div class="metric-card" style="flex-direction: row; align-items: center; justify-content: space-between; padding: 10px 16px; min-width: 0;">
                    <div>
                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            <i class="fas fa-stopwatch"></i> Thời gian chơi
                        </div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Phiên đang hoạt động</div>
                    </div>
                    <div class="digital-clock-pill" style="margin-left: auto;">
                        <i class="fas fa-clock" style="font-size: 13px; opacity: 0.8;"></i>
                        <span id="displayPlayTime">00:00:00</span>
                    </div>
                </div>

                <!-- Card 2: Tiền giờ đã dùng -->
                <div class="metric-card" style="padding: 10px 16px; min-width: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            <i class="fas fa-coins"></i> Tiền giờ đã dùng
                        </span>
                        <span style="font-size: 11px; color: #94a3b8; font-weight: 600;" id="displayRatePerMin">(133 đ/phút)</span>
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #1e293b; margin-top: 4px;" id="displayCurrentCost">0 đ</div>
                </div>

                <!-- Card 3: Số dư tài khoản -->
                <div class="metric-card balance-card" style="padding: 10px 16px; min-width: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase;">
                            <i class="fas fa-wallet"></i> Số dư tài khoản
                        </span>
                        <span style="font-size: 11px; font-weight: 700; color: #10b981;" id="displayRemainingTime">Ước tính: ~0h 0m</span>
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #059669; margin-top: 4px;" id="displayCurrentBalance">0 đ</div>
                </div>
            </div>
        </section>

        <!-- 4. Interactive Testing Toolbar (Dành riêng cho kiểm tra nghiệp vụ đồ án - Bấm F8 để bật) -->
        <div class="test-toolbar" id="testingToolbar" style="display: none;">
            <div style="display: flex; align-items: center; gap: 6px; font-weight: 700;">
                <i class="fas fa-tools"></i>
                <span>Bảng Kiểm Thử Trừ Tiền & Cảnh Báo Ngắt Phiên:</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <button class="test-btn" onclick="testDeductMinute(1)" title="Trừ tiền 1 phút ngay để kiểm tra CSDL">
                    <i class="fas fa-minus-circle"></i> Trừ 1 phút (-133 đ)
                </button>
                <button class="test-btn" onclick="testDeductMinute(10)" title="Tua 10 phút">
                    <i class="fas fa-forward"></i> Tua 10 phút
                </button>
                <button class="test-btn" onclick="testSetBalance(1500)" title="Đặt số dư còn 1.500đ (~11 phút)">
                    <i class="fas fa-exclamation-circle"></i> Test Cảnh Báo Vàng (<15p)
                </button>
                <button class="test-btn danger" onclick="testSetBalance(300)" title="Đặt số dư còn 300đ (~2 phút)">
                    <i class="fas fa-radiation"></i> Test Cảnh Báo Đỏ (<5p)
                </button>
                <button class="test-btn danger" onclick="testSetBalance(0)" title="Đặt số dư = 0 để kiểm tra tự động khóa máy">
                    <i class="fas fa-ban"></i> Test Hết Tiền (0 đ - Tự ngắt)
                </button>
                <button class="test-btn" onclick="testRefillStock()" style="background: #ecfdf5; border-color: #10b981; color: #047857; font-weight: 700;" title="Nạp đầy 100 phần cho tất cả món ăn/nước uống khi hết món">
                    <i class="fas fa-boxes"></i> Hồi Đầy Kho Món (100 phần)
                </button>
                <button class="test-btn" id="btnToggleFastSim" onclick="toggleFastSimulation()" style="background: #2563eb; color: #fff; border-color: #1d4ed8;">
                    <i class="fas fa-play"></i> Bật Mô Phỏng Nhanh (1s = 1 phút)
                </button>
                <button type="button" class="test-btn" onclick="toggleTestToolbar(false)" style="background: #fee2e2; border-color: #fca5a5; color: #b91c1c; font-weight: 800;" title="Tắt thanh công cụ này khi đã test xong (Phím tắt: F8)">
                    <i class="fas fa-times"></i> Đóng thanh Test
                </button>
            </div>
        </div>

        <!-- 5. Main Workspace -->
        <main class="app-content" style="padding: 20px; background: #f8fafc;">
            <div style="display: grid; grid-template-columns: 1fr 360px; gap: 20px;">

                <!-- Left Column: F&B Service Menu (UC8) -->
                <div>
                    <!-- Menu Header & Filters -->
                    <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h2 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 2px;">
                                <i class="fas fa-utensils" style="color: var(--primary);"></i> THỰC ĐƠN DỊCH VỤ TẠI MÁY TRẠM
                            </h2>
                            <p style="font-size: 12px; color: var(--text-muted);">Gọi đồ ăn, nước uống giao tận chỗ • Tự động trừ vào số dư tài khoản</p>
                        </div>
                        <button class="btn-fresh btn-secondary btn-sm" onclick="openModal('myOrdersModal'); loadMyOrders();">
                            <i class="fas fa-receipt"></i> Đơn của tôi
                        </button>
                    </div>

                    <!-- Category Pills Filter -->
                    <div id="menuFilter" style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;"></div>

                    <!-- Food Cards Grid -->
                    <div id="menuGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px;"></div>
                </div>

                <!-- Right Column: Sidebar (Mini Cart & Promotions) -->
                <div>
                    <!-- Mini Cart Box -->
                    <div class="wireframe-box" style="margin-bottom: 16px; background: #ffffff;">
                        <div class="wireframe-box-title" style="display: flex; justify-content: space-between; align-items: center;">
                            <span><i class="fas fa-shopping-basket" style="color: var(--primary);"></i> Khay Gọi Món</span>
                            <span id="cartCountBadge" class="station-status-badge badge-playing" style="margin-bottom: 0;">0 món</span>
                        </div>

                        <div id="miniCartItems" style="max-height: 220px; overflow-y: auto; margin-bottom: 12px;">
                            <div style="text-align: center; color: var(--text-muted); padding: 24px 10px; font-size: 13px;">
                                <i class="fas fa-shopping-cart" style="font-size: 28px; opacity: 0.3; margin-bottom: 8px; display: block;"></i>
                                Khay đang trống.<br>Hãy bấm "+ Chọn món" để thêm vào đây.
                            </div>
                        </div>

                        <!-- Mini Cart Summary -->
                        <div style="border-top: 1px dashed var(--border); padding-top: 12px; margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                                <span>Tổng tiền món:</span>
                                <strong id="miniCartTotal" style="color: var(--gold); font-size: 16px;">0 đ</strong>
                            </div>

                            <!-- Payment Method Choice -->
                            <div style="background: #f1f5f9; padding: 8px 10px; border-radius: 6px; font-size: 12px; margin-bottom: 10px;">
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin-bottom: 4px;">
                                    <input type="radio" name="orderPayMethod" value="Trừ số dư" checked style="accent-color: var(--primary);">
                                    <span>Trừ vào số dư tài khoản (Khuyên dùng)</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                    <input type="radio" name="orderPayMethod" value="Tiền mặt" style="accent-color: var(--primary);">
                                    <span>Thanh toán tiền mặt khi nhân viên giao</span>
                                </label>
                            </div>

                            <button class="btn-fresh btn-primary" id="btnSubmitCart" style="width: 100%; padding: 10px;" onclick="submitOrderFromMiniCart()" disabled>
                                <i class="fas fa-paper-plane"></i> Gửi Đơn Gọi Món
                            </button>
                        </div>
                    </div>

                    <!-- Live Orders & Status Tracker Box -->
                    <div class="wireframe-box" style="margin-bottom: 16px; background: #ffffff;">
                        <div class="wireframe-box-title" style="display: flex; justify-content: space-between; align-items: center;">
                            <span><i class="fas fa-clock-rotate-left" style="color: var(--primary);"></i> Đơn Món Đã Đặt</span>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span id="liveOrderCountBadge" class="station-status-badge badge-available" style="margin-bottom: 0; font-size: 11px;">0 đơn</span>
                                <button class="btn-fresh btn-secondary btn-sm" onclick="loadMyOrders()" title="Làm mới trạng thái đơn" style="padding: 2px 7px; font-size: 11px;">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Live Order Sidebar List -->
                        <div id="liveOrderSidebarList" style="max-height: 280px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                            <div style="text-align: center; color: var(--text-muted); padding: 18px 8px; font-size: 12.5px;">
                                <i class="fas fa-receipt" style="font-size: 26px; opacity: 0.3; margin-bottom: 6px; display: block;"></i>
                                Chưa có đơn gọi món nào.<br>Hãy đặt món từ thực đơn bên trái.
                            </div>
                        </div>
                    </div>

                    <!-- Promotions Box -->
                    <div class="wireframe-box" style="margin-bottom: 16px; background: #ffffff;">
                        <div class="wireframe-box-title">
                            <i class="fas fa-gift" style="color: var(--accent);"></i> Khuyến Mãi Nạp Tiền
                        </div>
                        <div id="promotionsListBanner">
                            <div style="text-align: center; color: var(--text-muted); padding: 10px; font-size: 12.5px;">Đang tải ưu đãi...</div>
                        </div>
                        <button class="btn-fresh btn-success btn-sm" style="width: 100%; margin-top: 10px;" onclick="openModal('topupModal')">
                            <i class="fas fa-bolt"></i> Nạp Ngay Nhận Thưởng
                        </button>
                    </div>

                    <!-- Room Rules / Info -->
                    <div class="wireframe-box" style="background: #ffffff; font-size: 12px; color: var(--text-secondary); line-height: 1.8;">
                        <div class="wireframe-box-title"><i class="fas fa-info-circle"></i> Tiện Ích Phòng Máy</div>
                        <div>✓ Hệ thống tự động trừ cước theo phút thời gian thực.</div>
                        <div>✓ Cảnh báo nhắc nhở khi tài khoản còn dưới 15 phút chơi.</div>
                        <div>✓ Tự động bảo lưu số dư tài khoản khi đăng xuất.</div>
                        <div>✓ Nạp tiền VietQR tự động cộng tiền trong 10 giây.</div>
                    </div>
                </div>

            </div>
        </main>



    </div>

    <!-- =======================================================
         MODALS
         ======================================================= -->

    <!-- MODAL 1: NẠP TIỀN TRỰC TUYẾN VIETQR (UC7) -->
    <div class="modal-overlay" id="topupModal">
        <div class="cyber-modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-qrcode"></i> NẠP TIỀN TRỰC TUYẾN VIETQR</h3>
                <button class="modal-close" onclick="closeModal('topupModal')">&times;</button>
            </div>

            <div id="topupStep1">
                <?php 
                    $userTier = $gamer['nhomKhachHang'] ?? 'Hội viên';
                    if ($userTier === 'VIP'): 
                ?>
                    <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 12.5px; color: #92400e; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-crown" style="font-size: 16px; color: #d97706;"></i>
                            <span>Đặc quyền <strong>Hội viên VIP</strong>: Tự động cộng thêm <strong>+10%</strong> tiền nạp vào số dư!</span>
                        </div>
                        <span style="background: #f59e0b; color: #fff; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 999px;">+10% BONUS</span>
                    </div>
                <?php elseif ($userTier === 'Kim Cương'): ?>
                    <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border: 1px solid #ddd6fe; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 12.5px; color: #5b21b6; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-gem" style="font-size: 16px; color: #7c3aed;"></i>
                            <span>Đặc quyền <strong>Kim Cương</strong>: Tự động cộng thêm <strong>+20%</strong> tiền nạp vào số dư!</span>
                        </div>
                        <span style="background: #7c3aed; color: #fff; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 999px;">+20% BONUS</span>
                    </div>
                <?php else: ?>
                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 12.5px; color: #0369a1; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-gift" style="font-size: 16px; color: #0284c7;"></i>
                            <span>Nạp tiền trực tuyến VietQR nhanh chóng • Tự động cộng tiền trong 10 giây</span>
                        </div>
                        <span style="background: #0284c7; color: #fff; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 999px;">VIETQR</span>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom: 8px; font-weight: 700; font-size: 13px; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-bolt" style="color: var(--primary);"></i> Chọn nhanh mệnh giá nạp:
                </div>

                <div class="quick-money-group" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin: 0 0 16px 0; width: 100%;">
                    <button type="button" class="quick-money-btn" data-amt="10000" onclick="selectTopupChip(10000)">10.000đ</button>
                    <button type="button" class="quick-money-btn" data-amt="20000" onclick="selectTopupChip(20000)">20.000đ</button>
                    <button type="button" class="quick-money-btn active" data-amt="50000" onclick="selectTopupChip(50000)">50.000đ</button>
                    <button type="button" class="quick-money-btn" data-amt="100000" onclick="selectTopupChip(100000)">100.000đ</button>
                    <button type="button" class="quick-money-btn" data-amt="200000" onclick="selectTopupChip(200000)">200.000đ</button>
                    <button type="button" class="quick-money-btn" data-amt="500000" onclick="selectTopupChip(500000)">500.000đ</button>
                </div>

                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 14px; width: 100%;">
                    <label class="field-label" style="width: 100%; font-weight: 700; color: #1e293b;">
                        <i class="fas fa-keyboard" style="color: #64748b;"></i> Số tiền nạp tùy chọn (VNĐ):
                    </label>
                    <input type="number" id="inputTopupAmount" class="form-control" value="50000" min="10000" step="10000" oninput="checkTopupBonus(this.value)" style="width: 100%; font-size: 15px; font-weight: 700; color: #0f172a;">
                </div>

                <div id="topupBonusPreview" style="background: #f8fafc; border: 1px solid var(--border); border-radius: 6px; padding: 12px; font-size: 13px; margin-bottom: 20px;">
                    Đang tính toán khuyến mãi...
                </div>

                <button class="btn-fresh btn-primary" id="btnStartTopup" style="width: 100%; padding: 12px;" onclick="startOnlineTopup()">
                    <i class="fas fa-arrow-right"></i> Tạo Mã Thanh Toán VietQR
                </button>
            </div>

            <div id="topupStep2" style="display: none; text-align: center;">
                <div style="display: inline-block; background: #fff; padding: 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 14px; box-shadow: var(--shadow-md);">
                    <img id="qrCodeImg" src="" alt="VietQR" style="width: 200px; height: 200px; display: block; object-fit: contain;">
                </div>

                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">
                    Mã tham chiếu: <strong id="qrRefCode" style="color: var(--primary);">-</strong>
                </div>

                <div style="display: flex; justify-content: center; gap: 16px; margin-bottom: 14px; font-size: 13px;">
                    <div>Nạp: <strong id="qrAmount">0 đ</strong></div>
                    <div style="color: var(--accent);">Thưởng: <strong id="qrBonus">+0 đ</strong></div>
                    <div style="color: var(--gold);">Tổng nhận: <strong id="qrTotal">0 đ</strong></div>
                </div>

                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px; margin-bottom: 16px; font-size: 12px; color: #b45309;">
                    <i class="fas fa-clock"></i> Giao dịch hết hạn trong: <strong id="qrCountdown" style="font-size: 14px;">15:00</strong>
                </div>

                <button class="btn-fresh btn-success" id="btnSimulatePay" style="width: 100%; margin-bottom: 10px; padding: 12px;" onclick="simulateOnlinePayment()">
                    <i class="fas fa-check-circle"></i> Giả Lập Quét QR Chuyển Khoản Thành Công
                </button>
                <button class="btn-fresh btn-secondary btn-sm" onclick="resetTopupView()">← Chọn lại số tiền</button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: ĐƠN ĐÃ ĐẶT -->
    <div class="modal-overlay" id="myOrdersModal">
        <div class="cyber-modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-receipt"></i> ĐƠN DỊCH VỤ CỦA BẠN</h3>
                <button class="modal-close" onclick="closeModal('myOrdersModal')">&times;</button>
            </div>
            <div id="myOrdersList" style="max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>

    <!-- MODAL 3: HỒ SƠ CÁ NHÂN & ĐỔI MẬT KHẨU (UC4) -->
    <div class="modal-overlay" id="profileModal">
        <div class="cyber-modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user"></i> THÔNG TIN CÁ NHÂN</h3>
                <button class="modal-close" onclick="closeModal('profileModal')">&times;</button>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 16px; display: grid; grid-template-columns: repeat(3, 1fr); text-align: center;">
                <div>
                    <div style="font-size: 11px; color: var(--text-muted);">Tài khoản</div>
                    <div id="profUsername" style="font-weight: 700; color: var(--primary);">-</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-muted);">Nhóm khách</div>
                    <div id="profGroup" style="font-weight: 700; color: var(--accent);">-</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--text-muted);">Tổng giờ chơi</div>
                    <div id="profTotalHours" style="font-weight: 700; color: var(--gold);">-</div>
                </div>
            </div>

            <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 12px;">
                <label class="field-label" style="width: 100%;">Họ và tên:</label>
                <input type="text" id="profFullName" class="form-control">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label class="field-label" style="width: 100%; margin-bottom: 4px;">Số điện thoại:</label>
                    <input type="text" id="profPhone" class="form-control">
                </div>
                <div>
                    <label class="field-label" style="width: 100%; margin-bottom: 4px;">Email:</label>
                    <input type="email" id="profEmail" class="form-control">
                </div>
            </div>

            <button class="btn-fresh btn-primary btn-sm" style="width: 100%; margin-bottom: 20px;" onclick="updateProfile()">
                <i class="fas fa-save"></i> Cập nhật thông tin cá nhân
            </button>

            <!-- Change Password -->
            <div style="border-top: 1px solid var(--border); padding-top: 16px;">
                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 12px;"><i class="fas fa-key"></i> Đổi Mật Khẩu</h4>
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 4px; margin-bottom: 10px;">
                    <label class="field-label" style="width: 100%;">Mật khẩu hiện tại:</label>
                    <input type="password" id="profOldPass" class="form-control" placeholder="••••••">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label class="field-label" style="width: 100%; margin-bottom: 4px;">Mật khẩu mới:</label>
                        <input type="password" id="profNewPass" class="form-control" placeholder="Tối thiểu 6 ký tự">
                    </div>
                    <div>
                        <label class="field-label" style="width: 100%; margin-bottom: 4px;">Xác nhận mật khẩu:</label>
                        <input type="password" id="profConfirmPass" class="form-control" placeholder="Nhập lại mật khẩu mới">
                    </div>
                </div>
                <button class="btn-fresh btn-secondary btn-sm" style="width: 100%;" onclick="changePassword()">
                    <i class="fas fa-shield-alt"></i> Lưu Mật Khẩu Mới
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 4: LỊCH SỬ GIAO DỊCH & PHIÊN CHƠI (UC6) -->
    <div class="modal-overlay" id="historyModal">
        <div class="cyber-modal" style="max-width: 750px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-history"></i> LỊCH SỬ HOẠT ĐỘNG</h3>
                <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
            </div>

            <div style="display: flex; gap: 8px; margin-bottom: 14px;">
                <button class="btn-fresh btn-sm" id="btnHistTopup" onclick="loadHistoryData('topup')">Lịch sử nạp tiền</button>
                <button class="btn-fresh btn-sm btn-secondary" id="btnHistSession" onclick="loadHistoryData('session')">Lịch sử phiên chơi</button>
            </div>

            <div id="historyContent" style="max-height: 420px; overflow-y: auto;"></div>
        </div>
    </div>

    <script src="assets/js/client.js?v=<?= time() ?>"></script>
</body>
</html>
