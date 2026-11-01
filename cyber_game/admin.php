<?php
/**
 * Bàn Làm Việc Thu Ngân & Quản Lý - CyberNet Gaming
 * Thiết kế chuẩn theo Phác thảo giao diện Wireframe (Visual Paradigm - Tuần 5)
 * Tông màu tươi sáng, hiện đại, phong cách Desktop Application
 */
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['staff_user'])) {
    header('Location: login.php');
    exit;
}

$staff = $_SESSION['staff_user'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phần Mềm Quản Lý Quán Net - Bàn Làm Việc Thu Ngân</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-window">
        <!-- Window Titlebar (matching wireframe) -->
        <div class="window-titlebar">
            <div class="window-title">
                <i class="fas fa-desktop" style="color: var(--primary);"></i>
                <span>PHẦN MỀM QUẢN LÝ QUÁN NET</span>
                <span style="font-size: 11px; font-weight: 500; background: var(--accent-light); color: var(--accent-hover); border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 4px; text-transform: none; margin-left: 10px;">
                    <?= htmlspecialchars($staff['hoTen']) ?> (<?= htmlspecialchars($staff['vaiTro']) ?>)
                </span>
            </div>
            <div class="window-controls">
                <a href="client.php" target="_blank" class="btn-fresh btn-sm btn-secondary">
                    <i class="fas fa-external-link-alt"></i> Mở máy khách
                </a>
                <button class="btn-fresh btn-sm btn-danger" onclick="adminLogout()" title="Đăng xuất khỏi hệ thống">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </button>
            </div>
        </div>

        <div class="app-body">
            <!-- Sidebar (Replicating wireframe sidebar: Tổng quan, Quản lý khách hàng, Quản lý máy, Giao dịch, Báo cáo) -->
            <aside class="app-sidebar">
                <ul class="sidebar-menu">
                    <li class="sidebar-item">
                        <a class="sidebar-link active" data-tab="topup" onclick="switchTab('topup')">
                            <i class="fas fa-credit-card"></i>
                            <span>Giao dịch (Nạp tiền)</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" data-tab="customers" onclick="switchTab('customers')">
                            <i class="fas fa-user-friends"></i>
                            <span>Quản lý khách hàng</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" data-tab="stations" onclick="switchTab('stations')">
                            <i class="fas fa-desktop"></i>
                            <span>Quản lý máy</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" data-tab="orders" onclick="switchTab('orders')">
                            <i class="fas fa-utensils"></i>
                            <span>Dịch vụ F&B</span>
                            <span id="orderPendingBadge" class="station-status-badge badge-playing" style="display: none; padding: 1px 6px; font-size: 10px; margin-left: auto;">0</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" data-tab="promotions" onclick="switchTab('promotions')">
                            <i class="fas fa-tags"></i>
                            <span>Khuyến mãi</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" data-tab="reports" onclick="switchTab('reports')">
                            <i class="fas fa-chart-bar"></i>
                            <span>Báo cáo</span>
                        </a>
                    </li>
                </ul>

                <!-- Bottom User Details -->
                <div style="margin-top: auto; padding: 16px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text-muted);">
                    <div>Ca trực: <strong><?= date('d/m/Y') ?></strong></div>
                    <div style="margin-top: 4px; display: flex; align-items: center; gap: 6px; color: var(--accent);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent); display: inline-block;"></span>
                        <span>Đang kết nối XAMPP CSDL</span>
                    </div>
                </div>
            </aside>

            <!-- Main Workspace -->
            <main class="app-content">

                <!-- =======================================================
                     MÀN HÌNH 1: MÀN HÌNH NẠP TIỀN (REPLICATING image2.png)
                     ======================================================= -->
                <div id="tab-topup" class="tab-content-panel">
                    <div class="screen-header-block">
                        <h1 class="screen-main-title">MÀN HÌNH NẠP TIỀN</h1>
                        <h2 class="screen-sub-title">Nạp tiền tài khoản khách hàng</h2>
                    </div>

                    <!-- Box 1: Thông tin khách hàng (matching wireframe) -->
                    <div class="wireframe-box">
                        <div class="wireframe-box-title">Thông tin khách hàng</div>
                        
                        <div class="field-row">
                            <label class="field-label">Mã khách hàng</label>
                            <div style="display: flex; gap: 8px; flex: 1;">
                                <input type="text" id="posCustInput" class="form-control" placeholder="Nhập mã khách hàng (KC01) hoặc số điện thoại..." onkeyup="onSearchCustomerKeyup(this.value)">
                                <button type="button" class="btn-fresh" onclick="doSearchCustomer()"><i class="fas fa-search"></i> Tìm kiếm</button>
                            </div>
                        </div>

                        <!-- Instant Auto-Suggest Dropdown -->
                        <div id="posSearchResults" style="margin-left: 166px; margin-bottom: 12px; max-height: 140px; overflow-y: auto; background: #fff; border: 1px solid var(--border); border-radius: 6px; display: none;"></div>

                        <div class="field-row">
                            <label class="field-label">Họ tên khách hàng</label>
                            <input type="text" id="posDispFullName" class="form-control field-value-box auto-field" value="(tự động hiển thị)" readonly>
                        </div>

                        <div class="field-row">
                            <label class="field-label">Hạng thành viên</label>
                            <input type="text" id="posDispGroup" class="form-control field-value-box auto-field" value="(tự động hiển thị)" readonly style="font-weight: 700; color: #b45309;">
                        </div>

                        <div class="field-row" style="margin-bottom: 0;">
                            <label class="field-label">Số dư hiện tại</label>
                            <input type="text" id="posDispBalance" class="form-control field-value-box highlight" value="(tự động hiển thị)" readonly>
                        </div>
                    </div>

                    <!-- Box 2: Thông tin nạp tiền (matching wireframe) -->
                    <div class="wireframe-box">
                        <div class="wireframe-box-title">Thông tin nạp tiền</div>

                        <div class="field-row">
                            <label class="field-label">Số tiền nạp (VNĐ)</label>
                            <input type="number" id="posAmountInput" class="form-control" placeholder="Nhập số tiền" value="50000" min="10000" step="10000" oninput="calculatePosPromotion()">
                        </div>

                        <!-- Quick select buttons (10.000đ, 20.000đ, 50.000đ, 100.000đ, 200.000đ, 500.000đ) -->
                        <div class="quick-money-group pos-quick-money">
                            <button type="button" class="quick-money-btn" data-pos-amt="10000" onclick="setPosAmount(10000)">10.000đ</button>
                            <button type="button" class="quick-money-btn" data-pos-amt="20000" onclick="setPosAmount(20000)">20.000đ</button>
                            <button type="button" class="quick-money-btn active" data-pos-amt="50000" onclick="setPosAmount(50000)">50.000đ</button>
                            <button type="button" class="quick-money-btn" data-pos-amt="100000" onclick="setPosAmount(100000)">100.000đ</button>
                            <button type="button" class="quick-money-btn" data-pos-amt="200000" onclick="setPosAmount(200000)">200.000đ</button>
                            <button type="button" class="quick-money-btn" data-pos-amt="500000" onclick="setPosAmount(500000)">500.000đ</button>
                        </div>

                        <div class="field-row">
                            <label class="field-label">Phương thức thanh toán</label>
                            <div class="radio-options-row">
                                <label class="radio-item">
                                    <input type="radio" name="posPaymentMethod" value="Tiền mặt" checked>
                                    <span>Tiền mặt</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="posPaymentMethod" value="Chuyển khoản">
                                    <span>Chuyển khoản</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="posPaymentMethod" value="Ví điện tử">
                                    <span>Ví điện tử</span>
                                </label>
                            </div>
                        </div>

                        <div class="field-row" style="margin-bottom: 0;">
                            <label class="field-label">Ghi chú</label>
                            <input type="text" id="posNoteInput" class="form-control" placeholder="Nhập ghi chú (nếu có)">
                        </div>

                        <!-- Dynamic Promotion Notice -->
                        <div id="posPromoNotice" style="margin-top: 14px; margin-left: 166px; font-size: 13px; color: var(--accent-hover); font-weight: 600;"></div>
                    </div>

                    <!-- Bottom Buttons (matching wireframe: Hủy, Xác nhận nạp tiền) -->
                    <div class="form-actions-row">
                        <button type="button" class="btn-fresh btn-secondary" onclick="resetTopupForm()"><i class="fas fa-redo"></i> Hủy</button>
                        <button type="button" class="btn-fresh btn-success" id="btnExecuteTopup" onclick="submitPosTopup()"><i class="fas fa-check"></i> Xác nhận nạp tiền</button>
                    </div>

                    <!-- Status Bar (matching wireframe) -->
                    <div class="wireframe-statusbar">
                        <div class="status-pill-left" id="posStatusMsg">Thông báo: Sẵn sàng thực hiện giao dịch</div>
                        <div class="status-pill-right" id="posStatusState">Trạng thái: Chờ xác nhận</div>
                    </div>
                </div>

                <!-- =======================================================
                     MÀN HÌNH 2: MÀN HÌNH THÔNG TIN TÀI KHOẢN KHÁCH HÀNG (REPLICATING image3.png)
                     ======================================================= -->
                <div id="tab-customers" class="tab-content-panel" style="display: none;">
                    <div class="screen-header-block">
                        <h1 class="screen-main-title">MÀN HÌNH THÔNG TIN TÀI KHOẢN KHÁCH HÀNG</h1>
                        <h2 class="screen-sub-title">Thông tin tài khoản khách hàng</h2>
                    </div>

                    <!-- Top Search Bar (matching wireframe) -->
                    <div class="wireframe-box" style="padding: 14px 20px; margin-bottom: 20px; position: relative;">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <input type="text" id="custSearchInput" class="form-control" placeholder="Nhập mã khách hàng (KC01), SĐT, tên tài khoản hoặc họ tên..." onkeyup="onCustSearchKeyup(this.value, event)">
                            <button type="button" class="btn-fresh" onclick="searchCustomerScreen()"><i class="fas fa-search"></i> Tìm kiếm</button>
                            <button type="button" class="btn-fresh btn-secondary" onclick="resetCustomerScreen()"><i class="fas fa-sync-alt"></i> Làm mới</button>
                        </div>
                        <div id="custSearchResults" style="position: absolute; top: calc(100% - 10px); left: 20px; right: 20px; z-index: 50; max-height: 220px; overflow-y: auto; background: #ffffff; border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow-lg); display: none;"></div>
                    </div>

                    <!-- Box 1: Thông tin khách hàng (2 columns + Prominent balance field) -->
                    <div class="wireframe-box">
                        <div class="wireframe-box-title">Thông tin khách hàng</div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 16px;">
                            <!-- Column 1 -->
                            <div>
                                <div class="field-row">
                                    <label class="field-label" style="width: 120px;">Mã khách hàng</label>
                                    <input type="text" id="custDispId" class="form-control auto-field" value="(tự động hiển thị)" readonly>
                                </div>
                                <div class="field-row">
                                    <label class="field-label" style="width: 120px;">Họ và tên</label>
                                    <input type="text" id="custDispFullName" class="form-control auto-field" value="(tự động hiển thị)">
                                </div>
                                <div class="field-row" style="margin-bottom: 0;">
                                    <label class="field-label" style="width: 120px;">Số điện thoại</label>
                                    <input type="text" id="custDispPhone" class="form-control auto-field" value="(tự động hiển thị)">
                                </div>
                            </div>

                            <!-- Column 2 -->
                            <div>
                                <div class="field-row">
                                    <label class="field-label" style="width: 130px;">Hạng thành viên</label>
                                    <select id="custDispGroup" class="form-control auto-field" style="font-weight: 700; color: var(--primary);">
                                        <option value="Hội viên">⭐ Hội viên (Tiêu chuẩn)</option>
                                        <option value="VIP">👑 VIP (Khách thân thiết)</option>
                                        <option value="Kim Cương">💎 Kim Cương (Cấp cao nhất)</option>
                                    </select>
                                </div>
                                <div class="field-row">
                                    <label class="field-label" style="width: 130px;">Email</label>
                                    <input type="text" id="custDispEmail" class="form-control auto-field" value="(tự động hiển thị)">
                                </div>
                                <div class="field-row">
                                    <label class="field-label" style="width: 130px;">Ngày đăng ký</label>
                                    <input type="text" id="custDispRegDate" class="form-control auto-field" value="(tự động hiển thị)" readonly>
                                </div>
                                <div class="field-row" style="margin-bottom: 0;">
                                    <label class="field-label" style="width: 130px;">Trạng thái tài khoản</label>
                                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #1e293b; cursor: pointer;">
                                        <input type="checkbox" id="custDispStatusCheck" checked style="width: 18px; height: 18px; accent-color: var(--primary);">
                                        <span id="custDispStatusLabel">Đang hoạt động</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Prominent Balance Field (matching wireframe) -->
                        <div class="field-row" style="border-top: 1px dashed #cbd5e1; padding-top: 14px; margin-bottom: 0;">
                            <label class="field-label" style="width: 120px; font-size: 14px; font-weight: 700;">Số dư tài khoản</label>
                            <input type="text" id="custDispTotalBalance" class="form-control highlight" style="font-size: 18px; font-weight: 800; text-align: right;" value="(tự động hiển thị)" readonly>
                        </div>
                    </div>

                    <!-- Box 2: Lịch sử giao dịch (matching wireframe table) -->
                    <div class="wireframe-box">
                        <div class="wireframe-box-title">Lịch sử giao dịch</div>
                        <div style="max-height: 220px; overflow-y: auto;">
                            <table class="wireframe-table">
                                <thead>
                                    <tr>
                                        <th>Mã giao dịch</th>
                                        <th>Thời gian</th>
                                        <th>Loại giao dịch</th>
                                        <th>Số tiền</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody id="custHistoryTableBody">
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">Vui lòng tìm kiếm khách hàng để hiển thị lịch sử giao dịch.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Action Buttons (matching wireframe: Chỉnh sửa thông tin, Khóa tài khoản, Đóng) -->
                    <div class="form-actions-row">
                        <button type="button" class="btn-fresh" onclick="submitSaveCustomerDetails()"><i class="fas fa-edit"></i> Chỉnh sửa thông tin</button>
                        <button type="button" class="btn-fresh btn-danger" id="btnToggleCustLock" onclick="submitToggleCustLock()"><i class="fas fa-lock"></i> Khóa tài khoản</button>
                        <button type="button" class="btn-fresh btn-secondary" onclick="resetCustomerScreen()"><i class="fas fa-times"></i> Đóng</button>
                    </div>

                    <!-- Status Bar (matching wireframe) -->
                    <div class="wireframe-statusbar">
                        <div class="status-pill-left" id="custStatusMsg">Thông báo: Vui lòng nhập mã khách hàng hoặc số điện thoại</div>
                    </div>
                </div>

                <!-- =======================================================
                     MÀN HÌNH 3: QUẢN LÝ MÁY & SƠ ĐỒ PHÒNG MÁY (UC12)
                     ======================================================= -->
                <div id="tab-stations" class="tab-content-panel" style="display: none;">
                    <div class="screen-header-block" style="display: flex; justify-content: space-between; align-items: flex-end;">
                        <div>
                            <h1 class="screen-main-title">SƠ ĐỒ PHÒNG MÁY & PHIÊN CHƠI</h1>
                            <h2 class="screen-sub-title">Giám sát 20 máy trạm, mở máy, chuyển máy (UC12) & kết thúc phiên</h2>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-fresh btn-sm btn-secondary" onclick="loadStations()"><i class="fas fa-sync-alt"></i> Làm mới</button>
                        </div>
                    </div>

                    <!-- Station KPI Pills -->
                    <div style="display: flex; gap: 14px; margin-bottom: 20px; flex-wrap: wrap;">
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 10px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
                            <i class="fas fa-tv" style="color: var(--primary); font-size: 20px;"></i>
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Tổng máy</div>
                                <div id="statTotalPcs" style="font-size: 18px; font-weight: 800;">20</div>
                            </div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 10px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
                            <i class="fas fa-user-clock" style="color: var(--danger); font-size: 20px;"></i>
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Đang chơi</div>
                                <div id="statPlayingPcs" style="font-size: 18px; font-weight: 800; color: var(--danger);">0</div>
                            </div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 10px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
                            <i class="fas fa-check-circle" style="color: var(--accent); font-size: 20px;"></i>
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Máy trống</div>
                                <div id="statAvailablePcs" style="font-size: 18px; font-weight: 800; color: var(--accent);">0</div>
                            </div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 10px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
                            <i class="fas fa-tools" style="color: #64748b; font-size: 20px;"></i>
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Bảo trì</div>
                                <div id="statMaintenancePcs" style="font-size: 18px; font-weight: 800; color: #64748b;">0</div>
                            </div>
                        </div>
                    </div>

                    <div id="stationsGrid" class="station-grid"></div>
                </div>

                <!-- =======================================================
                     MÀN HÌNH 4: XỬ LÝ ĐƠN DỊCH VỤ F&B (UC13)
                     ======================================================= -->
                <div id="tab-orders" class="tab-content-panel" style="display: none;">
                    <div class="screen-header-block" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1 class="screen-main-title">XỬ LÝ ĐƠN DỊCH VỤ</h1>
                            <h2 class="screen-sub-title">Tiếp nhận đơn gọi món, chuẩn bị, hoàn thành & hủy hoàn tiền</h2>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <button class="btn-fresh btn-sm btn-primary" onclick="openInventoryModal()">
                                <i class="fas fa-boxes"></i> Quản lý kho món & Nhập hàng
                            </button>
                            <select id="filterOrderStatus" class="form-control" style="width: 160px;" onchange="loadOrders()">
                                <option value="">-- Tất cả đơn --</option>
                                <option value="Chờ xử lý">Chờ xử lý</option>
                                <option value="Đang chuẩn bị">Đang chuẩn bị</option>
                                <option value="Đã hoàn thành">Đã hoàn thành</option>
                                <option value="Đã hủy">Đã hủy</option>
                            </select>
                            <button class="btn-fresh btn-sm btn-secondary" onclick="loadOrders()" title="Tải lại đơn"><i class="fas fa-sync-alt"></i></button>
                        </div>
                    </div>
                    <div id="ordersContainer"></div>
                </div>

                <!-- =======================================================
                     MÀN HÌNH 5: CHƯƠNG TRÌNH KHUYẾN MÃI (UC14)
                     ======================================================= -->
                <div id="tab-promotions" class="tab-content-panel" style="display: none;">
                    <div class="screen-header-block" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1 class="screen-main-title">QUẢN LÝ CHƯƠNG TRÌNH KHUYẾN MÃI</h1>
                            <h2 class="screen-sub-title">Cấu hình khuyến mãi nạp tiền & lịch sử điều chỉnh</h2>
                        </div>
                        <button class="btn-fresh btn-success" onclick="openAddPromotionModal()"><i class="fas fa-plus"></i> Thêm khuyến mãi mới</button>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        <div class="wireframe-box">
                            <div class="wireframe-box-title">Danh sách khuyến mãi</div>
                            <table class="wireframe-table">
                                <thead>
                                    <tr>
                                        <th>Mã KM</th>
                                        <th>Tên chương trình</th>
                                        <th>Nạp tối thiểu</th>
                                        <th>Tặng thêm</th>
                                        <th>Thời hạn</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody id="promotionsListTable"></tbody>
                            </table>
                        </div>

                        <div class="wireframe-box">
                            <div class="wireframe-box-title">Lịch sử biến động</div>
                            <div id="promoHistoryList" style="max-height: 400px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                <!-- =======================================================
                     MÀN HÌNH 6: BÁO CÁO & THỐNG KÊ (UC15, Query 6, 7, 8)
                     ======================================================= -->
                <div id="tab-reports" class="tab-content-panel" style="display: none;">
                    <div class="screen-header-block" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1 class="screen-main-title">BÁO CÁO THỐNG KÊ DOANH THU</h1>
                            <h2 class="screen-sub-title">Tổng hợp số liệu nạp tiền, giờ chơi và dịch vụ theo Tuần 5</h2>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="date" id="reportFromDate" class="form-control" style="width: 150px;" value="<?= date('Y-m-01') ?>">
                            <span>-</span>
                            <input type="date" id="reportToDate" class="form-control" style="width: 150px;" value="<?= date('Y-m-d') ?>">
                            <button class="btn-fresh" onclick="loadReports()"><i class="fas fa-filter"></i> Lọc</button>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; box-shadow: var(--shadow-sm);">
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Tổng tiền nạp</div>
                            <div id="repTotalTopup" style="font-size: 20px; font-weight: 800; color: var(--accent); margin-top: 4px;">0 đ</div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; box-shadow: var(--shadow-sm);">
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Tổng thời gian chơi</div>
                            <div id="repTotalHours" style="font-size: 20px; font-weight: 800; color: var(--primary); margin-top: 4px;">0 giờ</div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; box-shadow: var(--shadow-sm);">
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Doanh thu F&B</div>
                            <div id="repTotalFnb" style="font-size: 20px; font-weight: 800; color: var(--gold); margin-top: 4px;">0 đ</div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; box-shadow: var(--shadow-sm);">
                            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Tổng doanh thu</div>
                            <div id="repTotalRevenue" style="font-size: 20px; font-weight: 800; color: #1e293b; margin-top: 4px;">0 đ</div>
                        </div>
                    </div>

                    <!-- Complex Queries from Week 5 -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="wireframe-box">
                            <div class="wireframe-box-title">Query 6: Top thời gian chơi</div>
                            <table class="wireframe-table">
                                <thead>
                                    <tr>
                                        <th>Hạng</th>
                                        <th>Khách hàng</th>
                                        <th>Số phiên</th>
                                        <th>Tổng thời gian</th>
                                    </tr>
                                </thead>
                                <tbody id="query6TableBody"></tbody>
                            </table>
                        </div>

                        <div class="wireframe-box">
                            <div class="wireframe-box-title">Query 8: Top nạp tiền nhiều nhất</div>
                            <table class="wireframe-table">
                                <thead>
                                    <tr>
                                        <th>Hạng</th>
                                        <th>Khách hàng</th>
                                        <th>Lần nạp</th>
                                        <th>Tổng tiền nạp</th>
                                    </tr>
                                </thead>
                                <tbody id="query8TableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="wireframe-box">
                        <div class="wireframe-box-title">Query 7: Doanh thu theo từng phiên chơi</div>
                        <table class="wireframe-table">
                            <thead>
                                <tr>
                                    <th>Mã phiên</th>
                                    <th>Khách hàng</th>
                                    <th>Bắt đầu</th>
                                    <th>Thời lượng</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody id="query7TableBody"></tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL: THAO TÁC MÁY TRẠM -->
    <div class="modal-overlay" id="stationActionModal">
        <div class="cyber-modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalStationTitle">Thao tác máy</h3>
                <button class="modal-close" onclick="closeModal('stationActionModal')">&times;</button>
            </div>
            <div id="modalStationDetails"></div>
            <div id="modalStationActions"></div>
        </div>
    </div>

    <!-- MODAL: IN PHIẾU THU TIỀN NHIỆT (POS THERMAL RECEIPT) -->
    <div class="modal-overlay" id="receiptModal">
        <div class="cyber-modal" style="max-width: 400px; background: #fff;">
            <div id="thermalReceiptContainer"></div>
        </div>
    </div>

    <!-- MODAL: THÊM / SỬA KHUYẾN MÃI (UC14) -->
    <div class="modal-overlay" id="promotionModal">
        <div class="cyber-modal">
            <div class="modal-header">
                <h3 class="modal-title" id="promoModalTitle">Khuyến mãi</h3>
                <button class="modal-close" onclick="closeModal('promotionModal')">&times;</button>
            </div>
            <input type="hidden" id="editPromoId">
            <div class="form-group" style="margin-bottom: 14px;">
                <label class="field-label" style="width: 100%; margin-bottom: 6px;">Tên chương trình:</label>
                <input type="text" id="editPromoName" class="form-control" placeholder="Ví dụ: Giờ vàng nạp thẻ...">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label class="field-label" style="width: 100%; margin-bottom: 6px;">Nạp tối thiểu (VNĐ):</label>
                    <input type="number" id="editPromoMin" class="form-control" step="10000">
                </div>
                <div>
                    <label class="field-label" style="width: 100%; margin-bottom: 6px;">Tặng thêm (VNĐ):</label>
                    <input type="number" id="editPromoBonus" class="form-control" step="1000">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label class="field-label" style="width: 100%; margin-bottom: 6px;">Ngày bắt đầu:</label>
                    <input type="date" id="editPromoStart" class="form-control">
                </div>
                <div>
                    <label class="field-label" style="width: 100%; margin-bottom: 6px;">Ngày kết thúc:</label>
                    <input type="date" id="editPromoEnd" class="form-control">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="field-label" style="width: 100%; margin-bottom: 6px;">Trạng thái:</label>
                <select id="editPromoStatus" class="form-control">
                    <option value="Hoạt động">Hoạt động</option>
                    <option value="Tạm dừng">Tạm dừng</option>
                </select>
            </div>
            <button class="btn-fresh btn-success" style="width: 100%;" onclick="submitSavePromotion()">
                <i class="fas fa-save"></i> Lưu chương trình
            </button>
        </div>
    </div>

    <!-- 4. Modal Quản Lý Kho Món & Nhập Hàng F&B -->
    <div class="modal-overlay" id="inventoryModal">
        <div class="cyber-modal" style="max-width: 860px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background: var(--primary-light); color: var(--primary); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <h3 class="modal-title" style="margin: 0; font-size: 16px;">QUẢN LÝ TỒN KHO & NHẬP THÊM MÓN F&B</h3>
                        <p style="margin: 0; font-size: 12px; color: var(--text-muted);">Theo dõi số lượng tồn kho của các món và nhập thêm hàng</p>
                    </div>
                </div>
                <button class="modal-close" onclick="closeModal('inventoryModal')">&times;</button>
            </div>

            <div style="padding: 16px 20px; overflow-y: auto; flex: 1;">
                <!-- Quick Info & Refill All Banner -->
                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong style="color: #0f172a; font-size: 13px;"><i class="fas fa-sync-alt" style="color: var(--primary);"></i> Đồng bộ tức thì:</strong>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Khi món về 0, hệ thống tự ẩn món tại máy khách. Khi nhập thêm hàng, thực đơn máy trạm sẽ tự động hiện lại.</div>
                    </div>
                    <button class="btn-fresh btn-success btn-sm" onclick="adminRefillAllStock(50)">
                        <i class="fas fa-bolt"></i> Nạp đầy tất cả món (+50)
                    </button>
                </div>

                <!-- Inventory Table -->
                <div id="inventoryTableContainer">
                    <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                        <i class="fas fa-spinner fa-spin"></i> Đang tải danh sách kho món...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Controller Script -->
    <script src="assets/js/admin.js?v=<?= time() ?>"></script>
</body>
</html>
