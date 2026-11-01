/**
 * CyberNet Gaming - Client Station Controller
 * Hỗ trợ toàn diện các Use Case khách chơi: UC2, UC3, UC4, UC5, UC6, UC7, UC8
 * Cơ chế tính cước theo phút thời gian thực, đồng hồ LED chạy từng giây & Bảng kiểm thử nghiệp vụ
 */

let localSessionSeconds = 0;
let sessionTimerInterval = null;
let heartbeatInterval = null;
let fastSimInterval = null;

let miniCart = [];
let allMenuItems = [];
let topupCountdownInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    initClientSession();
    setupEventListeners();
    initTestToolbar();
    loadMenu();
    loadPromotionsBanner();
    loadMyOrders(true);
});

// ====================================================================
// 1. REAL-TIME SESSION HEARTBEAT & PER-MINUTE DEDUCTION (UC5)
// ====================================================================
function showLockoutScreen(message) {
    if (sessionTimerInterval) clearInterval(sessionTimerInterval);
    if (heartbeatInterval) clearInterval(heartbeatInterval);
    if (fastSimInterval) clearInterval(fastSimInterval);

    let overlay = document.getElementById('lockoutOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'lockoutOverlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(15,23,42,0.92);z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;backdrop-filter:blur(10px);text-align:center;padding:20px;';
        overlay.innerHTML = `
            <div style="background:#ffffff;color:#0f172a;padding:36px 32px;border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);max-width:480px;width:90%;animation:fadeIn 0.3s ease;">
                <div style="width:72px;height:72px;background:#fee2e2;color:#dc2626;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 18px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h2 style="font-size:20px;font-weight:800;color:#991b1b;margin-bottom:8px;">HẾT SỐ DƯ - ĐÃ TỰ ĐỘNG KHÓA MÁY</h2>
                <p style="font-size:14px;color:#475569;margin-bottom:18px;line-height:1.5;">${message || 'Tài khoản của bạn đã hết số dư (0 đ). Phiên chơi đã tự động chốt tiền và ngắt kết nối.'}</p>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:20px;font-size:12.5px;color:#64748b;">
                    <i class="fas fa-info-circle" style="color:var(--primary);"></i> Vui lòng liên hệ quầy thu ngân hoặc nạp tiền để tiếp tục sử dụng máy trạm.
                </div>
                <div style="font-size:13px;color:#dc2626;font-weight:700;">
                    Tự động chuyển về màn hình chính sau <span id="lockoutCount" style="font-size:16px;">3</span> giây...
                </div>
                <button class="btn-fresh" style="margin-top:16px;width:100%;" onclick="window.location.href='login.php?msg=out_of_money'">
                    <i class="fas fa-arrow-right"></i> Về màn hình đăng nhập ngay
                </button>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    let count = 3;
    const interval = setInterval(() => {
        count--;
        const cntEl = document.getElementById('lockoutCount');
        if (cntEl) cntEl.textContent = count;
        if (count <= 0) {
            clearInterval(interval);
            window.location.href = 'login.php?msg=out_of_money';
        }
    }, 1000);
}

async function initClientSession() {
    try {
        const res = await fetch('api/client_session.php');
        const result = await res.json();

        if (!result.success) {
            if (result.data && result.data.force_logout) {
                showLockoutScreen(result.message);
                return;
            }
        }

        if (result.success && result.data && result.data.session) {
            localSessionSeconds = parseInt(result.data.session.totalElapsedSeconds) || 0;
            const timerEl = document.getElementById('displayPlayTime');
            if (timerEl) {
                timerEl.textContent = formatDuration(localSessionSeconds);
            }
        }

        updateSessionUI(result.data);

        // Khởi động đồng hồ bấm giờ chạy từng giây (Local Timer)
        if (!sessionTimerInterval) {
            sessionTimerInterval = setInterval(() => {
                localSessionSeconds++;
                const timerEl = document.getElementById('displayPlayTime');
                if (timerEl) {
                    timerEl.textContent = formatDuration(localSessionSeconds);
                }
            }, 1000);
        }

        // Heartbeat đồng bộ dữ liệu số dư và cước phí định kỳ mỗi 10 giây
        if (!heartbeatInterval) {
            heartbeatInterval = setInterval(fetchHeartbeat, 10000);
        }
    } catch (e) {
        console.error('Lỗi khởi tạo phiên chơi:', e);
    }
}

async function fetchHeartbeat() {
    try {
        const res = await fetch('api/client_session.php');
        const result = await res.json();

        if (!result.success && result.data && result.data.force_logout) {
            showLockoutScreen(result.message);
            return;
        }

        if (result.success && result.data) {
            updateSessionUI(result.data);
            loadMyOrders(true);
        }
    } catch (e) {
        console.warn('Lỗi Heartbeat:', e);
    }
}

function updateSessionUI(data) {
    if (!data || !data.session || !data.station) return;

    const { session, station } = data;

    // Cập nhật số giây phiên chơi thực tế từ máy chủ
    if (session.totalElapsedSeconds !== undefined) {
        const serverSecs = parseInt(session.totalElapsedSeconds) || 0;
        // Chỉ điều chỉnh đồng bộ lại nếu lệch quá 5 giây (ví dụ khi tua thời gian, hoặc tab bị browser ngủ)
        if (Math.abs(localSessionSeconds - serverSecs) > 5 || localSessionSeconds === 0) {
            localSessionSeconds = serverSecs;
        }
        document.getElementById('displayPlayTime').textContent = formatDuration(localSessionSeconds);
    }

    // Cập nhật thông tin máy & loại máy
    document.getElementById('displayStationName').textContent = station.tenMay;
    const configDetail = station.moTa ? ` • <span title="Cấu hình: ${escapeHtml(station.moTa)}" style="color: #64748b; font-size: 11.5px;"><i class="fas fa-microchip"></i> ${escapeHtml(station.moTa)}</span>` : '';
    document.getElementById('displayStationTier').innerHTML = `<strong>${escapeHtml(station.tenLoai)}</strong> (${formatMoney(station.donGiaGio)}/h)${configDetail}`;
    document.getElementById('displayRatePerMin').textContent = `(${station.donGiaPhut || Math.round(station.donGiaGio / 60)} đ/phút)`;

    const tierBadge = document.getElementById('displayStationTierBadge');
    if (tierBadge) {
        const isVip = (station.tenLoai && station.tenLoai.includes('VIP')) || (station.tenMay && station.tenMay.includes('VIP'));
        const isPro = (station.tenLoai && station.tenLoai.includes('Thi Đấu')) || (station.tenMay && station.tenMay.includes('Stage')) || (station.tenMay && station.tenMay.includes('Pro'));
        if (isVip) {
            tierBadge.innerHTML = '<span style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 11px; padding: 2px 8px; border-radius: 6px; font-weight: 800;"><i class="fas fa-crown"></i> KHU VIP GAMING</span>';
        } else if (isPro) {
            tierBadge.innerHTML = '<span style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; font-size: 11px; padding: 2px 8px; border-radius: 6px; font-weight: 800;"><i class="fas fa-bolt"></i> KHU THI ĐẤU PRO</span>';
        } else {
            tierBadge.innerHTML = '<span style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 11px; padding: 2px 8px; border-radius: 6px; font-weight: 800;"><i class="fas fa-desktop"></i> KHU TIÊU CHUẨN</span>';
        }
    }

    // Cập nhật số dư & chi phí đã dùng
    document.getElementById('displayCurrentBalance').textContent = formatMoney(session.soDuHienTai);
    document.getElementById('displayCurrentCost').textContent = formatMoney(session.currentCost);

    // Cập nhật ước tính thời gian chơi còn lại
    const remMin = session.remainingMinutes;
    const hours = Math.floor(remMin / 60);
    const mins = remMin % 60;
    document.getElementById('displayRemainingTime').textContent = `Ước tính: ~${hours}h ${mins}m`;

    // Cảnh báo số dư thông minh (< 15 phút hoặc < 5 phút)
    const alertBox = document.getElementById('lowBalanceBanner');
    const alertText = document.getElementById('lowBalanceText');

    if (session.criticalWarning) {
        alertBox.style.display = 'flex';
        alertBox.style.background = '#fee2e2';
        alertBox.style.borderColor = '#f87171';
        alertBox.style.color = '#991b1b';
        alertText.innerHTML = `<strong>⚠️ NGUY CẤP:</strong> Tài khoản chỉ còn <strong>${session.remainingMinutes} phút</strong> chơi! Nạp ngay để không bị ngắt máy.`;
    } else if (session.lowBalanceWarning) {
        alertBox.style.display = 'flex';
        alertBox.style.background = '#fef3c7';
        alertBox.style.borderColor = '#fcd34d';
        alertBox.style.color = '#92400e';
        alertText.innerHTML = `<strong>⚠️ CẢNH BÁO SỐ DƯ:</strong> Thời gian chơi còn lại dưới 15 phút (còn khoảng <strong>${session.remainingMinutes} phút</strong>). Vui lòng nạp thêm tiền.`;
    } else {
        alertBox.style.display = 'none';
    }
}

// ====================================================================
// 2. BẢNG KIỂM THỬ NGHIỆP VỤ (TESTING & DEMO CONTROLS)
// ====================================================================
async function testDeductMinute(mins = 1) {
    try {
        const res = await fetch(`api/client_session.php?action=simulate_fast_forward&minutes=${mins}`);
        const result = await res.json();

        if (!result.success && result.data && result.data.force_logout) {
            alert(result.message);
            window.location.href = 'login.php';
            return;
        }

        if (result.success && result.data) {
            if (result.data.session && result.data.session.totalElapsedSeconds !== undefined) {
                localSessionSeconds = parseInt(result.data.session.totalElapsedSeconds) || 0;
            } else {
                localSessionSeconds += (mins * 60);
            }
            updateSessionUI(result.data);
            if (!fastSimInterval) {
                showToast(`⚡ Đã trừ ${mins} phút tiền giờ. Thời gian chơi đã cộng thêm ${mins} phút!`);
            }
        }
    } catch (e) {
        alert('Lỗi kiểm thử: ' + e.message);
    }
}

async function testSetBalance(amount) {
    if (!confirm(`Bạn có chắc muốn đặt số dư tài khoản về ${formatMoney(amount)} để kiểm tra phản ứng của hệ thống?`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('balance', amount);

        const res = await fetch('api/client_session.php?action=set_test_balance', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (!result.success && result.data && result.data.force_logout) {
            showLockoutScreen(result.message || 'Tài khoản đã về 0 đ. Máy trạm tự động khóa và đăng xuất!');
            return;
        }

        if (result.success && result.data) {
            updateSessionUI(result.data);
            showToast(`⚡ Số dư đã cập nhật về ${formatMoney(amount)}.`);
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

async function testRefillStock() {
    try {
        const res = await fetch('api/client_order.php?action=test_refill_stock');
        const result = await res.json();
        if (result.success) {
            showToast('📦 ' + result.message);
            loadMenu();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (e) {
        alert('Lỗi kết nối máy chủ: ' + e.message);
    }
}

function toggleFastSimulation() {
    const btn = document.getElementById('btnToggleFastSim');
    if (fastSimInterval) {
        clearInterval(fastSimInterval);
        fastSimInterval = null;
        btn.innerHTML = '<i class="fas fa-play"></i> Bật Mô Phỏng Nhanh (1s = 1 phút)';
        btn.style.background = '#2563eb';
        showToast('Đã dừng chế độ mô phỏng nhanh.');
    } else {
        fastSimInterval = setInterval(() => {
            testDeductMinute(1);
        }, 1000);
        btn.innerHTML = '<i class="fas fa-pause"></i> ⏸ Dừng Mô Phỏng (Đang chạy 1s=1p)';
        btn.style.background = '#dc2626';
        showToast('▶ Đang chạy mô phỏng nhanh: Cứ mỗi 1 giây trôi qua tương đương 1 phút chơi!');
    }
}

let isTogglingTest = false;
function toggleTestToolbar(forceState) {
    if (isTogglingTest) return;
    isTogglingTest = true;
    setTimeout(() => { isTogglingTest = false; }, 250);

    const bar = document.getElementById('testingToolbar');
    if (!bar) return;

    const isVisible = (bar.style.display !== 'none');
    const shouldShow = (typeof forceState === 'boolean') ? forceState : !isVisible;

    if (shouldShow) {
        bar.style.display = 'flex';
        showToast('🛠️ Đã bật Bảng Kiểm Thử (Bấm F8 để ẩn)');
    } else {
        bar.style.display = 'none';
        if (fastSimInterval) {
            toggleFastSimulation();
        }
        showToast('Đã ẩn Bảng Kiểm Thử');
    }
}

function initTestToolbar() {
    const bar = document.getElementById('testingToolbar');
    if (bar) {
        bar.style.display = 'none';
    }
}

// ====================================================================
// 3. F&B SERVICE MENU & MINI CART (UC8)
// ====================================================================
async function loadMenu(category = '') {
    const container = document.getElementById('menuGrid');
    if (!container) return;

    container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Đang tải thực đơn...</div>';

    try {
        const url = category ? `api/client_order.php?action=get_menu&category=${encodeURIComponent(category)}` : 'api/client_order.php?action=get_menu';
        const res = await fetch(url);
        const result = await res.json();

        if (result.success && result.data) {
            allMenuItems = result.data.items;
            renderCategories(result.data.categories, category);
            renderMenuItems(allMenuItems);
        }
    } catch (e) {
        container.innerHTML = '<div style="grid-column: 1/-1; color: var(--danger); text-align: center;">Không thể tải thực đơn.</div>';
    }
}

function renderCategories(categories, currentCategory) {
    const filterContainer = document.getElementById('menuFilter');
    if (!filterContainer) return;

    let html = `<button class="btn-fresh btn-sm ${!currentCategory ? 'btn-primary' : 'btn-secondary'}" onclick="loadMenu('')">Tất cả</button>`;
    categories.forEach(cat => {
        const active = currentCategory === cat ? 'btn-primary' : 'btn-secondary';
        html += `<button class="btn-fresh btn-sm ${active}" onclick="loadMenu('${cat}')">${cat}</button>`;
    });
    filterContainer.innerHTML = html;
}

function getFoodIcon(tenMon, loaiMon) {
    const lower = (tenMon + ' ' + loaiMon).toLowerCase();
    if (lower.includes('cơm')) return 'fa-bowl-rice';
    if (lower.includes('mì') || lower.includes('mi')) return 'fa-bowl-food';
    if (lower.includes('sting') || lower.includes('nước') || lower.includes('bò húc')) return 'fa-wine-bottle';
    if (lower.includes('cà phê') || lower.includes('cafe')) return 'fa-mug-hot';
    if (lower.includes('thẻ')) return 'fa-gamepad';
    if (lower.includes('bánh') || lower.includes('snack') || lower.includes('xúc xích')) return 'fa-cookie-bite';
    return 'fa-utensils';
}

function renderMenuItems(items) {
    const container = document.getElementById('menuGrid');
    if (!items.length) {
        container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">Không có món nào trong phân loại này.</div>';
        return;
    }

    container.innerHTML = items.map(it => {
        const icon = getFoodIcon(it.tenMon, it.loaiMon);
        const imgHtml = it.hinhAnh ? `
            <img src="${it.hinhAnh}?v=2" alt="${escapeHtml(it.tenMon)}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px; transition: transform 0.3s;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'">
            <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center;">
                <i class="fas ${icon}" style="font-size: 36px; color: var(--primary); opacity: 0.85;"></i>
            </div>
        ` : `
            <i class="fas ${icon}" style="font-size: 36px; color: var(--primary); opacity: 0.85;"></i>
        `;

        return `
            <div class="food-card-modern">
                <div>
                    <div class="food-thumb" style="overflow: hidden; position: relative;">
                        ${imgHtml}
                        <span style="position: absolute; top: 6px; right: 6px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(4px); border: 1px solid #cbd5e1; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; color: #334155; box-shadow: 0 1px 3px rgba(0,0,0,0.12); z-index: 2;">
                            Còn ${it.soLuongTon}
                        </span>
                    </div>
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 2px; color: #0f172a; line-height: 1.3;">${escapeHtml(it.tenMon)}</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 10px;">${escapeHtml(it.loaiMon)}</div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px dashed var(--border); padding-top: 8px;">
                    <div style="font-weight: 800; color: var(--gold); font-size: 14px;">${formatMoney(it.donGia)}</div>
                    <button class="btn-fresh btn-primary btn-sm" onclick="addToMiniCart('${it.maMon}')" style="padding: 4px 10px; font-size: 11.5px;">
                        <i class="fas fa-plus"></i> Chọn món
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function addToMiniCart(maMon) {
    const item = allMenuItems.find(i => i.maMon === maMon);
    if (!item) return;

    const existing = miniCart.find(i => i.maMon === maMon);
    if (existing) {
        if (existing.soLuong < item.soLuongTon) {
            existing.soLuong++;
        } else {
            alert(`Món '${item.tenMon}' chỉ còn tối đa ${item.soLuongTon} phần.`);
            return;
        }
    } else {
        miniCart.push({
            maMon: item.maMon,
            tenMon: item.tenMon,
            donGia: parseFloat(item.donGia),
            soLuong: 1,
            donViTinh: item.donViTinh,
            soLuongTon: item.soLuongTon
        });
    }

    renderMiniCart();
    showToast(`Đã thêm "${item.tenMon}" vào khay món ăn!`);
}

function changeMiniCartQty(idx, delta) {
    if (!miniCart[idx]) return;
    const item = miniCart[idx];
    const newQty = item.soLuong + delta;

    if (newQty <= 0) {
        miniCart.splice(idx, 1);
    } else if (newQty > item.soLuongTon) {
        alert(`Số lượng tồn chỉ còn tối đa ${item.soLuongTon} phần.`);
    } else {
        item.soLuong = newQty;
    }
    renderMiniCart();
}

function removeMiniCartItem(idx) {
    miniCart.splice(idx, 1);
    renderMiniCart();
}

function renderMiniCart() {
    const container = document.getElementById('miniCartItems');
    const badge = document.getElementById('cartCountBadge');
    const totalEl = document.getElementById('miniCartTotal');
    const submitBtn = document.getElementById('btnSubmitCart');

    const totalQty = miniCart.reduce((sum, i) => sum + i.soLuong, 0);
    const totalMoney = miniCart.reduce((sum, i) => sum + (i.soLuong * i.donGia), 0);

    badge.textContent = `${totalQty} món`;
    totalEl.textContent = formatMoney(totalMoney);
    submitBtn.disabled = (miniCart.length === 0);

    if (miniCart.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; color: var(--text-muted); padding: 24px 10px; font-size: 13px;">
                <i class="fas fa-shopping-cart" style="font-size: 28px; opacity: 0.3; margin-bottom: 8px; display: block;"></i>
                Khay đang trống.<br>Hãy bấm "+ Chọn món" để thêm vào đây.
            </div>
        `;
        return;
    }

    container.innerHTML = miniCart.map((item, idx) => `
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 12.5px;">
            <div style="flex: 1; padding-right: 8px;">
                <div style="font-weight: 700; color: #0f172a;">${escapeHtml(item.tenMon)}</div>
                <div style="color: var(--gold); font-size: 11.5px;">${formatMoney(item.donGia)}</div>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <button type="button" class="test-btn" style="padding: 2px 7px;" onclick="changeMiniCartQty(${idx}, -1)">-</button>
                <strong style="min-width: 18px; text-align: center;">${item.soLuong}</strong>
                <button type="button" class="test-btn" style="padding: 2px 7px;" onclick="changeMiniCartQty(${idx}, 1)">+</button>
                <button type="button" class="test-btn danger" style="padding: 2px 7px;" onclick="removeMiniCartItem(${idx})" title="Xóa"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}

async function submitOrderFromMiniCart() {
    if (!miniCart.length) {
        alert('Khay gọi món đang trống.');
        return;
    }

    const method = document.querySelector('input[name="orderPayMethod"]:checked')?.value || 'Trừ số dư';
    const totalMoney = miniCart.reduce((sum, i) => sum + (i.soLuong * i.donGia), 0);

    if (!confirm(`Xác nhận đặt ${miniCart.length} món với tổng thanh toán ${formatMoney(totalMoney)}?\nHình thức: ${method}`)) {
        return;
    }

    const btn = document.getElementById('btnSubmitCart');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi đơn...';

    try {
        const formData = new FormData();
        formData.append('hinhThucThanhToan', method);
        formData.append('items', JSON.stringify(miniCart.map(i => ({ maMon: i.maMon, soLuong: i.soLuong }))));

        const res = await fetch('api/client_order.php?action=create_order', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.success) {
            alert(`🎉 ${result.message}\nNhân viên bếp sẽ chuẩn bị và giao tới máy cho bạn.`);
            miniCart = [];
            renderMiniCart();
            // Làm mới số dư tài khoản & lịch sử đơn
            fetchHeartbeat();
            loadMyOrders(true);
        } else {
            alert(result.message);
        }
    } catch (e) {
        alert('Lỗi đặt món: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi Đơn Gọi Món';
    }
}

async function loadMyOrders(silent = false) {
    const sidebarContainer = document.getElementById('liveOrderSidebarList');
    const modalContainer = document.getElementById('myOrdersList');
    const badgeCount = document.getElementById('liveOrderCountBadge');

    if (!silent && sidebarContainer) {
        sidebarContainer.innerHTML = '<div style="text-align: center; padding: 15px; color: var(--text-muted); font-size: 12px;"><i class="fas fa-spinner fa-spin"></i> Đang cập nhật đơn món...</div>';
    }

    try {
        const res = await fetch('api/client_order.php?action=my_orders');
        const result = await res.json();

        if (result.success && result.data && result.data.length) {
            const orders = result.data;
            if (badgeCount) badgeCount.textContent = `${orders.length} đơn`;

            // Helper tạo huy hiệu trạng thái đơn món
            const getStatusBadge = (st) => {
                switch (st) {
                    case 'Chờ xử lý':
                        return '<span style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-hourglass-half"></i> Chờ xử lý</span>';
                    case 'Đang chuẩn bị':
                    case 'Đang chế biến':
                        return '<span style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-fire"></i> Đang chuẩn bị</span>';
                    case 'Đang giao':
                        return '<span style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-motorcycle"></i> Đang giao món</span>';
                    case 'Đã hoàn thành':
                    case 'Đã giao':
                        return '<span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-check-circle"></i> Đã giao món</span>';
                    case 'Đã hủy':
                        return '<span style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-times-circle"></i> Đã hủy</span>';
                    default:
                        return `<span class="station-status-badge badge-playing" style="margin-bottom:0;">${escapeHtml(st)}</span>`;
                }
            };

            // 1. Hiển thị ở Sidebar bên phải (Trực quan dưới Khay gọi món)
            if (sidebarContainer) {
                sidebarContainer.innerHTML = orders.map(o => {
                    const itemsSummary = (o.items && o.items.length) 
                        ? o.items.map(it => `${it.soLuong}x ${escapeHtml(it.tenMon)}`).join(', ') 
                        : `${o.soMon || 1} món`;
                    const isCancelled = (o.trangThaiDon === 'Đã hủy');
                    const cardBorderColor = isCancelled ? '#ef4444' : (o.trangThaiDon === 'Đã hoàn thành' ? '#10b981' : 'var(--primary)');
                    const cancelBox = isCancelled ? `
                        <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; border-radius: 6px; padding: 5px 8px; font-size: 11px; margin-top: 6px; line-height: 1.4;">
                            <div style="font-weight: 700;"><i class="fas fa-ban"></i> Lý do: ${escapeHtml(o.lyDoHuy || 'Hết nguyên liệu món này')}</div>
                            ${o.hinhThucThanhToan === 'Trừ số dư' ? '<div style="color: #047857; font-weight: 700; margin-top: 2px;"><i class="fas fa-check-circle"></i> Đã hoàn tiền vào tài khoản</div>' : ''}
                        </div>
                    ` : '';

                    return `
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 12px; transition: all 0.15s; border-left: 4px solid ${cardBorderColor}; cursor: pointer;" 
                             onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='#f8fafc'"
                             onclick="openModal('myOrdersModal'); loadMyOrders();">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <strong style="color: var(--primary); font-size: 12px;">#${o.maDon}</strong>
                                ${getStatusBadge(o.trangThaiDon)}
                            </div>
                            <div style="color: #334155; font-weight: 600; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(itemsSummary)}">
                                <i class="fas fa-utensils" style="color: #64748b; font-size: 11px; margin-right: 4px;"></i>${escapeHtml(itemsSummary)}
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 5px; margin-top: 4px;">
                                <span>${(o.thoiGianDat || '').substring(11, 16)} • ${escapeHtml(o.hinhThucThanhToan)}</span>
                                <strong style="color: var(--gold); font-size: 12.5px;">${formatMoney(o.tongTien)}</strong>
                            </div>
                            ${cancelBox}
                        </div>
                    `;
                }).join('');
            }

            // 2. Hiển thị chi tiết trong Modal khi mở
            if (modalContainer) {
                modalContainer.innerHTML = orders.map(o => {
                    const itemsHtml = (o.items && o.items.length) ? `
                        <div style="background: #f8fafc; border-radius: 6px; padding: 10px; margin: 8px 0; font-size: 12.5px; border: 1px solid #e2e8f0;">
                            ${o.items.map(it => `
                                <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span><strong>${it.soLuong}x</strong> ${escapeHtml(it.tenMon)} ${it.ghiChu ? `<em style="color:#64748b;">(${escapeHtml(it.ghiChu)})</em>` : ''}</span>
                                    <span style="font-weight: 600; color: #334155;">${formatMoney(it.thanhTien)}</span>
                                </div>
                            `).join('')}
                        </div>
                    ` : '';

                    const modalCancelHtml = (o.trangThaiDon === 'Đã hủy') ? `
                        <div style="background: #fee2e2; border: 1px solid #f87171; color: #991b1b; border-radius: 6px; padding: 10px 12px; margin-top: 8px; font-size: 12.5px;">
                            <div style="font-weight: 700; margin-bottom: 3px;"><i class="fas fa-exclamation-triangle"></i> Đơn hàng đã bị hủy bởi nhà bếp</div>
                            <div>Lý do hủy: <strong style="color: #7f1d1d;">${escapeHtml(o.lyDoHuy || 'Hết nguyên liệu món này')}</strong></div>
                            ${o.hinhThucThanhToan === 'Trừ số dư' ? '<div style="color: #047857; font-weight: 700; margin-top: 4px;"><i class="fas fa-check-circle"></i> Tiền món (' + formatMoney(o.tongTien) + ') đã được hoàn trả tự động vào số dư của bạn.</div>' : '<div style="color: #64748b; margin-top: 2px;">(Thanh toán tiền mặt - chưa thu tiền)</div>'}
                        </div>
                    ` : '';

                    return `
                        <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <strong style="color: var(--primary); font-size: 15px;">Đơn #${o.maDon}</strong>
                                    <span style="font-size: 12px; color: #64748b;">${o.thoiGianDat}</span>
                                </div>
                                ${getStatusBadge(o.trangThaiDon)}
                            </div>
                            ${itemsHtml}
                            ${modalCancelHtml}
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--border);">
                                <div>Thanh toán: <strong>${o.hinhThucThanhToan}</strong> (<span style="color: ${o.trangThaiThanhToan === 'Đã thanh toán' ? '#059669' : '#d97706'}; font-weight: 600;">${o.trangThaiThanhToan}</span>)</div>
                                <div style="font-size: 14px; font-weight: 800; color: var(--gold);">Tổng cộng: ${formatMoney(o.tongTien)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        } else {
            if (badgeCount) badgeCount.textContent = '0 đơn';
            if (sidebarContainer) {
                sidebarContainer.innerHTML = `
                    <div style="text-align: center; color: var(--text-muted); padding: 20px 8px; font-size: 12.5px;">
                        <i class="fas fa-receipt" style="font-size: 26px; opacity: 0.3; margin-bottom: 6px; display: block;"></i>
                        Chưa có đơn gọi món nào.<br>Hãy đặt món từ thực đơn bên trái.
                    </div>
                `;
            }
            if (modalContainer) {
                modalContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">Bạn chưa đặt đơn món nào trong phiên chơi này.</div>';
            }
        }
    } catch (e) {
        console.error('Lỗi tải danh sách đơn:', e);
    }
}

// ====================================================================
// 4. KHUYẾN MÃI BANNER & NẠP TIỀN VIETQR (UC7)
// ====================================================================
async function loadPromotionsBanner() {
    const container = document.getElementById('promotionsListBanner');
    if (!container) return;

    try {
        const res = await fetch('api/client_topup.php?action=get_promotions');
        const result = await res.json();

        if (result.success && result.data) {
            const promotions = result.data.promotions || [];
            const userRank = result.data.userRank || 'Hội viên';
            const rankBonusPercent = result.data.rankBonusPercent || 0;

            let rankPerkHtml = '';
            if (userRank === 'VIP') {
                rankPerkHtml = `
                    <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fde68a; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="font-weight: 800; color: #b45309; display: flex; align-items: center; gap: 6px; font-size: 13px; margin-bottom: 3px;">
                            <i class="fas fa-crown" style="color: #d97706;"></i> ĐẶC QUYỀN HỘI VIÊN VIP
                        </div>
                        <div style="color: #78350f; line-height: 1.4;">
                            Mỗi lần nạp tiền bạn được tặng thêm <strong>+10%</strong> giá trị nạp (ngoài khuyến mãi sự kiện)!
                        </div>
                    </div>
                `;
            } else if (userRank === 'Kim Cương') {
                rankPerkHtml = `
                    <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border: 1px solid #ddd6fe; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-size: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="font-weight: 800; color: #6d28d9; display: flex; align-items: center; gap: 6px; font-size: 13px; margin-bottom: 3px;">
                            <i class="fas fa-gem" style="color: #7c3aed;"></i> ĐẶC QUYỀN KIM CƯƠNG
                        </div>
                        <div style="color: #4c1d95; line-height: 1.4;">
                            Mỗi lần nạp tiền bạn được tặng thêm <strong>+20%</strong> giá trị nạp (ngoài khuyến mãi sự kiện)!
                        </div>
                    </div>
                `;
            } else {
                rankPerkHtml = `
                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-size: 12px;">
                        <div style="font-weight: 700; color: #0369a1; display: flex; align-items: center; gap: 6px; font-size: 12.5px; margin-bottom: 3px;">
                            <i class="fas fa-user-check"></i> Hạng: Hội viên Chuẩn
                        </div>
                        <div style="color: #0c4a6e; line-height: 1.4;">
                            Nâng cấp lên <strong>VIP (+10%)</strong> hoặc <strong>Kim Cương (+20%)</strong> để nhận thêm nhiều tiền nạp hơn!
                        </div>
                    </div>
                `;
            }

            if (promotions.length > 0) {
                const promosHtml = promotions.map(p => {
                    const minAmt = parseFloat(p.mucNapToiThieu);
                    const baseBonus = parseFloat(p.soTienKhuyenMai);
                    const rankBonus = minAmt * (rankBonusPercent / 100);
                    const totalBonus = baseBonus + rankBonus;
                    const totalReceive = minAmt + totalBonus;

                    let bonusBreakdown = `Tặng sự kiện: <strong>+${formatMoney(baseBonus)}</strong>`;
                    if (rankBonus > 0) {
                        bonusBreakdown += `<br><span style="color: #b45309; font-weight: 700;"><i class="fas fa-crown"></i> ${userRank} (+${rankBonusPercent}%): +${formatMoney(rankBonus)}</span>`;
                    }

                    return `
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span style="font-weight: 800; font-size: 12.5px; color: #0f172a;">${escapeHtml(p.tenChuongTrinh)}</span>
                                <span style="background: #dbeafe; color: #1e40af; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px;">${p.maKhuyenMai}</span>
                            </div>
                            <div style="font-size: 11.5px; color: #475569; margin-bottom: 6px; line-height: 1.5;">
                                Nạp từ <strong>${formatMoney(minAmt)}</strong> ➔ ${bonusBreakdown}
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 6px; font-size: 12px;">
                                <span>Tổng nhận: <strong style="color: var(--gold); font-size: 13.5px;">${formatMoney(totalReceive)}</strong></span>
                                <button class="btn-fresh btn-primary btn-sm" onclick="selectTopupChip(${minAmt}); openModal('topupModal');" style="padding: 3px 9px; font-size: 11px;">
                                    <i class="fas fa-bolt"></i> Nạp gói này
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');

                container.innerHTML = rankPerkHtml + promosHtml;
            } else {
                container.innerHTML = rankPerkHtml + '<div style="font-size: 12px; color: var(--text-muted); padding: 8px;">Hiện không có chương trình khuyến mãi sự kiện nào.</div>';
            }
        } else {
            container.innerHTML = '<div style="font-size: 12px; color: var(--text-muted); padding: 8px;">Không có dữ liệu khuyến mãi.</div>';
        }
    } catch (e) {
        console.error('Lỗi tải khuyến mãi:', e);
        container.innerHTML = '<div style="font-size: 12px; color: var(--text-muted); padding: 8px;">Không thể kết nối tải khuyến mãi.</div>';
    }
}

let activeTopupData = null;

function selectTopupChip(amt) {
    document.getElementById('inputTopupAmount').value = amt;
    document.querySelectorAll('.quick-money-btn').forEach(btn => {
        if (btn.getAttribute('data-amt') == amt) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    checkTopupBonus(amt);
}

async function checkTopupBonus(amt) {
    const preview = document.getElementById('topupBonusPreview');
    if (!preview) return;
    const amount = parseFloat(amt) || 0;

    document.querySelectorAll('.quick-money-btn').forEach(btn => {
        if (btn.getAttribute('data-amt') == amount) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    if (amount < 10000) {
        preview.innerHTML = '<span style="color: var(--danger);"><i class="fas fa-exclamation-circle"></i> Số tiền nạp tối thiểu là 10.000 VNĐ</span>';
        return;
    }

    try {
        const res = await fetch(`api/client_topup.php?action=calculate_promotion&soTienNap=${amount}`);
        const result = await res.json();
        if (result.success && result.data) {
            const d = result.data;
            const rankBonusHtml = d.rankBonus > 0 ? `
                <div style="color: #b45309; font-size: 12.5px;">
                    <i class="fas fa-crown"></i> Thưởng đặc quyền <strong>${escapeHtml(d.userRank)} (+${d.rankBonusPercent}%)</strong>: 
                    <strong>+${formatMoney(d.rankBonus)}</strong>
                </div>
            ` : '';

            const promoHtml = d.baseBonus > 0 ? `
                <div style="color: var(--accent); font-size: 12.5px;">
                    <i class="fas fa-gift"></i> Khuyến mãi sự kiện (${escapeHtml(d.tenKhuyenMai || 'Chương trình')}): 
                    <strong>+${formatMoney(d.baseBonus)}</strong>
                </div>
            ` : '<div style="color: #64748b; font-size: 12px;">(Không có khuyến mãi sự kiện cho mốc này)</div>';

            preview.innerHTML = `
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div style="font-size: 13px;">Mệnh giá nạp: <strong>${formatMoney(amount)}</strong></div>
                    ${promoHtml}
                    ${rankBonusHtml}
                    <div style="font-weight: 800; color: var(--gold); font-size: 15px; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; display: flex; justify-content: space-between;">
                        <span>TỔNG SỐ DƯ NHẬN ĐƯỢC:</span>
                        <span>${formatMoney(d.tongNhan)}</span>
                    </div>
                </div>
            `;
        }
    } catch (e) {
        console.warn('Lỗi tính thưởng:', e);
    }
}

async function startOnlineTopup() {
    const amount = parseFloat(document.getElementById('inputTopupAmount').value) || 0;
    if (amount < 10000) {
        alert('Số tiền nạp tối thiểu là 10.000 VNĐ.');
        return;
    }

    const btn = document.getElementById('btnStartTopup');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tạo mã VietQR...';

    try {
        const formData = new FormData();
        formData.append('soTienNap', amount);

        const res = await fetch('api/client_topup.php?action=create_topup_request', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.success && result.data) {
            activeTopupData = result.data;
            document.getElementById('topupStep1').style.display = 'none';
            document.getElementById('topupStep2').style.display = 'block';

            document.getElementById('qrCodeImg').src = result.data.qrCodeUrl;
            document.getElementById('qrRefCode').textContent = result.data.maThamChieu;
            document.getElementById('qrAmount').textContent = formatMoney(result.data.soTienNap);

            let bonusText = `+${formatMoney(result.data.tienKhuyenMai)}`;
            if (result.data.rankBonus > 0) {
                bonusText += ` (${result.data.userRank} +${formatMoney(result.data.rankBonus)})`;
            }
            document.getElementById('qrBonus').textContent = bonusText;
            document.getElementById('qrTotal').textContent = formatMoney(result.data.tongNhan);

            startCountdown(15 * 60);
        } else {
            alert(result.message);
        }
    } catch (e) {
        alert('Lỗi tạo mã nạp: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-arrow-right"></i> Tạo Mã Thanh Toán VietQR';
    }
}

function startCountdown(seconds) {
    clearInterval(topupCountdownInterval);
    let rem = seconds;
    const el = document.getElementById('qrCountdown');

    topupCountdownInterval = setInterval(() => {
        rem--;
        if (rem <= 0) {
            clearInterval(topupCountdownInterval);
            el.textContent = 'HẾT HẠN';
            alert('Mã giao dịch VietQR này đã hết hạn. Vui lòng tạo mã mới.');
            resetTopupView();
        } else {
            const m = Math.floor(rem / 60);
            const s = rem % 60;
            el.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }
    }, 1000);
}

function resetTopupView() {
    clearInterval(topupCountdownInterval);
    document.getElementById('topupStep2').style.display = 'none';
    document.getElementById('topupStep1').style.display = 'block';
    checkTopupBonus(document.getElementById('inputTopupAmount').value);
}

async function simulateOnlinePayment() {
    if (!activeTopupData) return;

    const btn = document.getElementById('btnSimulatePay');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xác thực chuyển khoản...';

    try {
        const formData = new FormData();
        formData.append('maGiaoDich', activeTopupData.maGiaoDich);

        const res = await fetch('api/client_topup.php?action=simulate_payment_success', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.success) {
            const addedAmount = result.data?.tongNhan || result.data?.tongNhanDuoc || (parseFloat(result.data?.soTienNap || 0) + parseFloat(result.data?.tienKhuyenMai || 0));
            alert(`🎉 Nạp tiền thành công!\nTài khoản đã được cộng ${formatMoney(addedAmount)} (Số dư hiện tại: ${formatMoney(result.data?.soDuMoi)}).`);
            closeModal('topupModal');
            resetTopupView();
            fetchHeartbeat();
        } else {
            alert(result.message);
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Giả Lập Quét QR Chuyển Khoản Thành Công';
    }
}

// ====================================================================
// 5. PROFILE & CHANGE PASSWORD (UC4)
// ====================================================================
async function openProfileModal() {
    try {
        const res = await fetch('api/client_profile.php?action=get_profile');
        const result = await res.json();

        if (result.success && result.data) {
            const u = result.data.user || result.data;
            const stats = result.data.stats || {};
            document.getElementById('profUsername').textContent = u.tenDangNhap || '';
            document.getElementById('profGroup').textContent = u.nhomKhachHang || 'Hội viên';
            const totalH = stats.totalHours ?? Math.round((u.tongThoiGianPhut || 0) / 60);
            document.getElementById('profTotalHours').textContent = `${totalH} giờ`;

            document.getElementById('profFullName').value = u.hoTen || '';
            document.getElementById('profPhone').value = u.soDienThoai || '';
            document.getElementById('profEmail').value = u.email || '';
            openModal('profileModal');
        } else {
            alert(result.message || 'Không thể tải thông tin cá nhân.');
        }
    } catch (e) {
        alert('Lỗi tải thông tin cá nhân: ' + e.message);
    }
}

async function updateProfile() {
    const hoTen = document.getElementById('profFullName').value.trim();
    const phone = document.getElementById('profPhone').value.trim();
    const email = document.getElementById('profEmail').value.trim();

    if (!hoTen || !phone || !email) {
        alert('Vui lòng điền đầy đủ họ tên, số điện thoại và email.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('hoTen', hoTen);
        formData.append('soDienThoai', phone);
        formData.append('email', email);

        const res = await fetch('api/client_profile.php?action=update_profile', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        alert(result.message);
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

async function changePassword() {
    const oldPass = document.getElementById('profOldPass').value;
    const newPass = document.getElementById('profNewPass').value;
    const confirmPass = document.getElementById('profConfirmPass').value;

    if (!oldPass || !newPass) {
        alert('Vui lòng nhập mật khẩu hiện tại và mật khẩu mới.');
        return;
    }

    if (newPass !== confirmPass) {
        alert('Mật khẩu xác nhận không khớp.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('matKhauCu', oldPass);
        formData.append('matKhauMoi', newPass);

        const res = await fetch('api/client_profile.php?action=change_password', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            document.getElementById('profOldPass').value = '';
            document.getElementById('profNewPass').value = '';
            document.getElementById('profConfirmPass').value = '';
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

// ====================================================================
// 6. HISTORY MODAL (UC6)
// ====================================================================
function openHistoryModal() {
    openModal('historyModal');
    loadHistoryData('topup');
}

async function loadHistoryData(type) {
    const container = document.getElementById('historyContent');
    const btnTopup = document.getElementById('btnHistTopup');
    const btnSession = document.getElementById('btnHistSession');

    if (type === 'topup') {
        btnTopup.className = 'btn-fresh btn-sm';
        btnSession.className = 'btn-fresh btn-sm btn-secondary';
        container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

        try {
            const res = await fetch('api/client_profile.php?action=topup_history');
            const result = await res.json();
            if (result.success && result.data && result.data.length) {
                container.innerHTML = `
                    <table class="wireframe-table">
                        <thead>
                            <tr>
                                <th>Mã GD</th>
                                <th>Thời gian</th>
                                <th>Phương thức</th>
                                <th>Số tiền nạp</th>
                                <th>Khuyến mãi</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${result.data.map(t => `
                                <tr>
                                    <td style="font-weight: 700; color: var(--primary);">${t.maGiaoDich}</td>
                                    <td>${t.thoiGianTao}</td>
                                    <td>${escapeHtml(t.phuongThuc)}</td>
                                    <td style="font-weight: 700; color: var(--accent);">${formatMoney(t.soTienNap)}</td>
                                    <td style="color: var(--gold);">+${formatMoney(t.tienKhuyenMai)}</td>
                                    <td><span class="station-status-badge badge-available" style="margin-bottom:0;">${t.trangThai}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 30px;">Chưa có giao dịch nạp tiền nào.</div>';
            }
        } catch (e) {
            container.innerHTML = '<div style="text-align: center; color: var(--danger);">Lỗi tải dữ liệu.</div>';
        }
    } else {
        btnTopup.className = 'btn-fresh btn-sm btn-secondary';
        btnSession.className = 'btn-fresh btn-sm';
        container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

        try {
            const res = await fetch('api/client_profile.php?action=session_history');
            const result = await res.json();
            if (result.success && result.data && result.data.length) {
                container.innerHTML = `
                    <table class="wireframe-table">
                        <thead>
                            <tr>
                                <th>Mã phiên</th>
                                <th>Bắt đầu</th>
                                <th>Kết thúc</th>
                                <th>Tổng phút</th>
                                <th>Chi phí</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${result.data.map(s => `
                                <tr>
                                    <td style="font-weight: 700; color: var(--primary);">${s.maPhien}</td>
                                    <td>${s.thoiGianBatDau}</td>
                                    <td>${s.thoiGianKetThuc || 'Đang chơi'}</td>
                                    <td>${s.tongThoiGianPhut} phút</td>
                                    <td style="font-weight: 700; color: var(--gold);">${formatMoney(s.chiPhi)}</td>
                                    <td><span class="station-status-badge ${s.trangThai === 'Đã kết thúc' ? 'badge-available' : 'badge-playing'}" style="margin-bottom:0;">${s.trangThai}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 30px;">Chưa có lịch sử phiên chơi nào.</div>';
            }
        } catch (e) {
            container.innerHTML = '<div style="text-align: center; color: var(--danger);">Lỗi tải dữ liệu.</div>';
        }
    }
}

// ====================================================================
// 7. LOGOUT & HELPERS
// ====================================================================
async function confirmLogout() {
    if (!confirm('Bạn có chắc chắn muốn kết thúc phiên chơi và đăng xuất khỏi máy trạm này?')) {
        return;
    }

    try {
        const res = await fetch('api/auth.php?action=gamer_logout', { method: 'POST' });
        const result = await res.json();
        alert(result.message || 'Đã kết thúc phiên chơi.');
        window.location.href = 'login.php';
    } catch (e) {
        window.location.href = 'login.php';
    }
}

function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
}

function formatDuration(totalSeconds) {
    const s = Math.max(0, parseInt(totalSeconds) || 0);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('active');
        if (id === 'topupModal') {
            const val = document.getElementById('inputTopupAmount')?.value || 50000;
            checkTopupBonus(val);
        }
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

function showToast(msg) {
    const toast = document.createElement('div');
    toast.style.position = 'fixed';
    toast.style.bottom = '24px';
    toast.style.right = '24px';
    toast.style.background = '#0f172a';
    toast.style.color = '#ffffff';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '8px';
    toast.style.zIndex = '99999';
    toast.style.fontWeight = '600';
    toast.style.boxShadow = '0 10px 25px rgba(0,0,0,0.3)';
    toast.style.border = '1px solid #38bdf8';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

function setupEventListeners() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });

    // Phím tắt F8 & Ctrl+Shift+T để bật/tắt nhanh thanh kiểm thử đồ án
    window.addEventListener('keydown', (e) => {
        if (e.repeat) return;
        if (e.key === 'F8' || e.code === 'F8' || (e.ctrlKey && e.shiftKey && (e.key === 'T' || e.key === 't'))) {
            e.preventDefault();
            toggleTestToolbar();
        }
    });
}
