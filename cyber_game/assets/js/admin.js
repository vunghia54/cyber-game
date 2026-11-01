/**
 * CyberNet Gaming - Cashier & Admin Controller
 * Hiện thực hóa chính xác 100% Wireframe Visual Paradigm (Màn hình nạp tiền & Màn hình khách hàng)
 * Hỗ trợ giao diện tươi sáng, hiện đại, tương tác mượt mà
 */

let activeTab = 'topup';
let stationRefreshInterval = null;
let currentSelectedStation = null;
let selectedCustomerForTopup = null;
let selectedCustomerForScreen = null;

document.addEventListener('DOMContentLoaded', () => {
    switchTab('topup');
    setupAdminEvents();
    calculatePosPromotion();
});

function setupAdminEvents() {
    // Tự động làm mới mỗi 5 giây
    stationRefreshInterval = setInterval(() => {
        if (activeTab === 'stations') {
            loadStations(true);
        } else if (activeTab === 'orders') {
            loadOrders(true);
        }
    }, 5000);

    // Đóng modal khi click ra ngoài overlay
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });
}

function switchTab(tabId) {
    activeTab = tabId;

    // Cập nhật trạng thái sidebar
    document.querySelectorAll('.sidebar-link').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });

    // Hiển thị panel tương ứng
    document.querySelectorAll('.tab-content-panel').forEach(panel => {
        panel.style.display = (panel.id === `tab-${tabId}`) ? 'block' : 'none';
    });

    // Kích hoạt dữ liệu cho từng tab
    switch (tabId) {
        case 'topup':
            calculatePosPromotion();
            break;
        case 'customers':
            // Nếu chưa tìm ai, tải mặc định khách hàng đầu tiên
            if (!selectedCustomerForScreen) {
                searchCustomerScreen('KC01');
            }
            break;
        case 'stations':
            loadStations();
            break;
        case 'orders':
            loadOrders();
            break;
        case 'promotions':
            loadPromotions();
            break;
        case 'reports':
            loadReports();
            break;
    }
}

// ====================================================================
// 1. MÀN HÌNH NẠP TIỀN (REPLICATING image2.png)
// ====================================================================
let searchTopupTimeout = null;

function onSearchCustomerKeyup(val) {
    clearTimeout(searchTopupTimeout);
    searchTopupTimeout = setTimeout(() => {
        doSearchCustomer(val.trim());
    }, 250);
}

async function doSearchCustomer(term = '') {
    const inputVal = term || document.getElementById('posCustInput').value.trim();
    const dropdown = document.getElementById('posSearchResults');

    if (!inputVal) {
        dropdown.style.display = 'none';
        return;
    }

    try {
        const res = await fetch(`api/admin_topup.php?action=search_customer&query=${encodeURIComponent(inputVal)}`);
        const result = await res.json();

        if (result.success && result.data && result.data.length) {
            dropdown.innerHTML = result.data.map(c => `
                <div onclick="selectCustomerForTopup('${c.maTaiKhoan}', '${c.maKhachChoi || c.maTaiKhoan}', '${escapeHtml(c.tenDangNhap)}', '${escapeHtml(c.hoTen)}', '${c.soDienThoai}', ${c.soDu}, '${c.nhomKhachHang || 'Hội viên'}')" 
                     style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                    <div>
                        <strong>${escapeHtml(c.hoTen)}</strong> (@${escapeHtml(c.tenDangNhap)}) - SĐT: ${c.soDienThoai}
                    </div>
                    <div style="font-weight: 700; color: var(--gold);">${formatMoney(c.soDu)}</div>
                </div>
            `).join('');
            dropdown.style.display = 'block';
        } else {
            dropdown.innerHTML = '<div style="padding: 10px 14px; color: var(--text-muted); font-size: 13px;">Không tìm thấy khách hàng phù hợp.</div>';
            dropdown.style.display = 'block';
        }
    } catch (e) {
        console.error('Lỗi tìm khách:', e);
    }
}

function selectCustomerForTopup(maTaiKhoan, maKhachChoi, tenDangNhap, hoTen, sdt, soDu, nhomKhachHang = 'Hội viên') {
    selectedCustomerForTopup = { maTaiKhoan, maKhachChoi, tenDangNhap, hoTen, sdt, soDu, nhomKhachHang };

    document.getElementById('posCustInput').value = `${maKhachChoi} - ${tenDangNhap}`;
    document.getElementById('posDispFullName').value = `${hoTen} (SĐT: ${sdt})`;
    if (document.getElementById('posDispGroup')) {
        document.getElementById('posDispGroup').value = (nhomKhachHang === 'VIP') ? '👑 VIP (Khách thân thiết)' : ((nhomKhachHang === 'Kim Cương') ? '💎 Kim Cương (Cấp cao)' : '⭐ Hội viên (Tiêu chuẩn)');
    }
    document.getElementById('posDispBalance').value = formatMoney(soDu);

    document.getElementById('posSearchResults').style.display = 'none';
    document.getElementById('posStatusMsg').textContent = `Thông báo: Đã chọn khách hàng ${hoTen} (@${tenDangNhap}) - Hạng: ${nhomKhachHang}`;
    document.getElementById('posStatusState').textContent = 'Trạng thái: Đã xác định tài khoản';

    calculatePosPromotion();
}

function setPosAmount(val) {
    document.getElementById('posAmountInput').value = val;
    document.querySelectorAll('.pos-quick-money .quick-money-btn').forEach(btn => {
        if (btn.getAttribute('data-pos-amt') == val) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    calculatePosPromotion();
}

async function calculatePosPromotion() {
    const amount = parseFloat(document.getElementById('posAmountInput').value) || 0;
    document.querySelectorAll('.pos-quick-money .quick-money-btn').forEach(btn => {
        if (btn.getAttribute('data-pos-amt') == amount) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    const promoNotice = document.getElementById('posPromoNotice');
    if (!promoNotice) return;

    if (amount < 10000) {
        promoNotice.innerHTML = '<span style="color: var(--danger);">Số tiền tối thiểu là 10.000 VNĐ</span>';
        return;
    }

    try {
        const maTaiKhoan = selectedCustomerForTopup ? selectedCustomerForTopup.maTaiKhoan : '';
        const res = await fetch(`api/admin_topup.php?action=calculate_promotion&soTienNap=${amount}&maTaiKhoan=${encodeURIComponent(maTaiKhoan)}`);
        const result = await res.json();

        if (result.success && result.data) {
            const d = result.data;
            let parts = [];
            if (d.khuyenMai && d.baseBonus > 0) {
                parts.push(`KM Sự kiện: <strong>+${formatMoney(d.baseBonus)}</strong>`);
            }
            if (d.rankBonus > 0) {
                parts.push(`Đặc quyền <strong>${escapeHtml(d.nhomKhachHang)} (+${d.rankBonusPercent}%)</strong>: <strong>+${formatMoney(d.rankBonus)}</strong>`);
            }

            if (parts.length > 0) {
                promoNotice.innerHTML = `
                    <i class="fas fa-gift" style="color: var(--accent);"></i> ${parts.join(' + ')} ➔ Tổng nhận: <strong style="color: var(--primary); font-size: 13.5px;">${formatMoney(d.tongNhan)}</strong>
                `;
            } else {
                promoNotice.innerHTML = `Tổng tài khoản nhận: <strong>${formatMoney(amount)}</strong>`;
            }
        }
    } catch (e) {}
}

async function submitPosTopup() {
    if (!selectedCustomerForTopup) {
        alert('Vui lòng nhập và chọn thông tin khách hàng cần nạp.');
        document.getElementById('posCustInput').focus();
        return;
    }

    const amount = parseFloat(document.getElementById('posAmountInput').value) || 0;
    if (amount < 10000) {
        alert('Số tiền nạp tối thiểu là 10.000 VNĐ.');
        return;
    }

    const method = document.querySelector('input[name="posPaymentMethod"]:checked')?.value || 'Tiền mặt';
    const note = document.getElementById('posNoteInput').value.trim();

    const btn = document.getElementById('btnExecuteTopup');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';

    try {
        const formData = new FormData();
        formData.append('maTaiKhoan', selectedCustomerForTopup.maTaiKhoan);
        formData.append('soTienNap', amount);
        formData.append('phuongThuc', method);
        formData.append('maThamChieu', note);

        const res = await fetch('api/admin_topup.php?action=execute_topup', {
            method: 'POST',
            body: formData
        });

        const result = await res.json();
        if (result.success && result.data && result.data.receipt) {
            document.getElementById('posStatusMsg').textContent = `Thông báo: Nạp thành công ${formatMoney(amount)} cho tài khoản ${selectedCustomerForTopup.tenDangNhap}`;
            document.getElementById('posStatusState').textContent = 'Trạng thái: Hoàn tất giao dịch';

            showThermalReceipt(result.data.receipt);
            resetTopupForm();
        } else {
            alert(result.message);
            document.getElementById('posStatusMsg').textContent = `Thông báo lỗi: ${result.message}`;
        }
    } catch (e) {
        alert('Lỗi nạp tiền: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Xác nhận nạp tiền';
    }
}

function resetTopupForm() {
    selectedCustomerForTopup = null;
    document.getElementById('posCustInput').value = '';
    document.getElementById('posDispFullName').value = '(tự động hiển thị)';
    if (document.getElementById('posDispGroup')) document.getElementById('posDispGroup').value = '(tự động hiển thị)';
    document.getElementById('posDispBalance').value = '(tự động hiển thị)';
    document.getElementById('posAmountInput').value = '50000';
    document.getElementById('posNoteInput').value = '';
    document.getElementById('posSearchResults').style.display = 'none';
    document.getElementById('posStatusMsg').textContent = 'Thông báo: Sẵn sàng thực hiện giao dịch';
    document.getElementById('posStatusState').textContent = 'Trạng thái: Chờ xác nhận';
    calculatePosPromotion();
}

function showThermalReceipt(r) {
    const container = document.getElementById('thermalReceiptContainer');
    container.innerHTML = `
        <div class="thermal-receipt" id="printableReceipt">
            <div class="center bold" style="font-size: 15px;">CYBERNET GAMING</div>
            <div class="center" style="font-size: 11px;">123 Phố Game, Hà Nội • Hotline: 1900 6868</div>
            <div class="divider"></div>
            <div class="center bold">PHIẾU NẠP TIỀN TÀI KHOẢN</div>
            <div class="center" style="font-size: 11px;">Mã GD: ${r.maGiaoDich} • ${r.thoiGian}</div>
            <div class="divider"></div>
            <div class="row-line"><span>Khách hàng:</span> <span class="bold">${escapeHtml(r.hoTen)}</span></div>
            <div class="row-line"><span>Tài khoản:</span> <span class="bold">${escapeHtml(r.tenDangNhap)} (${escapeHtml(r.nhomKhachHang || 'Hội viên')})</span></div>
            <div class="row-line"><span>Số điện thoại:</span> <span>${escapeHtml(r.soDienThoai)}</span></div>
            <div class="row-line"><span>Phương thức:</span> <span>${escapeHtml(r.phuongThuc)}</span></div>
            <div class="divider"></div>
            <div class="row-line"><span>Số tiền nạp:</span> <span>${formatMoney(r.soTienNap)}</span></div>
            ${r.baseBonus > 0 ? `<div class="row-line" style="font-size: 11.5px; color: #475569;"><span>+ KM sự kiện:</span> <span>+${formatMoney(r.baseBonus)}</span></div>` : ''}
            ${r.rankBonus > 0 ? `<div class="row-line" style="font-size: 11.5px; color: #b45309;"><span>+ Đặc quyền ${escapeHtml(r.nhomKhachHang)}:</span> <span>+${formatMoney(r.rankBonus)}</span></div>` : ''}
            <div class="row-line"><span>Tổng khuyến mãi:</span> <span class="bold" style="color: var(--accent);">+${formatMoney(r.tienKhuyenMai)}</span></div>
            <div class="row-line total-line"><span>TỔNG CỘNG NHẬN:</span> <span>${formatMoney(r.tongNhan)}</span></div>
            <div class="divider"></div>
            <div class="row-line"><span>Số dư trước:</span> <span>${formatMoney(r.soDuCu)}</span></div>
            <div class="row-line"><span>Số dư sau:</span> <span class="bold">${formatMoney(r.soDuMoi)}</span></div>
            <div class="divider"></div>
            <div class="row-line"><span>Thu ngân:</span> <span>${escapeHtml(r.thuNgan)}</span></div>
            <div class="center" style="margin-top: 14px; font-size: 11px;">Cảm ơn quý khách! Chúc bạn chơi game vui vẻ!</div>
        </div>
        <div style="text-align: center; margin-top: 16px; display: flex; gap: 8px; justify-content: center;">
            <button class="btn-fresh" onclick="window.print()"><i class="fas fa-print"></i> In phiếu thu</button>
            <button class="btn-fresh btn-secondary" onclick="closeModal('receiptModal')">Đóng</button>
        </div>
    `;

    openModal('receiptModal');
}

// ====================================================================
// 2. MÀN HÌNH THÔNG TIN TÀI KHOẢN KHÁCH HÀNG (REPLICATING image3.png)
// ====================================================================
let custSearchDebounce = null;
function onCustSearchKeyup(val, event) {
    if (event && event.key === 'Enter') {
        searchCustomerScreen(val);
        return;
    }
    clearTimeout(custSearchDebounce);
    const box = document.getElementById('custSearchResults');
    if (!val || val.trim().length === 0) {
        box.style.display = 'none';
        return;
    }
    custSearchDebounce = setTimeout(async () => {
        try {
            const res = await fetch(`api/admin_customers.php?action=list_customers&keyword=${encodeURIComponent(val.trim())}`);
            const result = await res.json();
            if (result.success && result.data && result.data.length) {
                box.innerHTML = result.data.map(c => {
                    const cRank = c.nhomKhachHang || 'Hội viên';
                    const cRankBadge = cRank === 'VIP' 
                        ? '<span style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; margin-left: 6px;"><i class="fas fa-crown"></i> VIP</span>'
                        : (cRank === 'Kim Cương' 
                            ? '<span style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; margin-left: 6px;"><i class="fas fa-gem"></i> Kim Cương</span>'
                            : '<span style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; margin-left: 6px;"><i class="fas fa-user-check"></i> Hội viên</span>');
                    return `
                    <div style="padding: 10px 14px; border-bottom: 1px solid var(--border); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.15s;" 
                         onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'"
                         onclick="selectCustomerFromSearch('${c.maTaiKhoan}', '${escapeHtml(c.maKhachChoi)} - ${escapeHtml(c.hoTen)}')">
                        <div>
                            <strong style="color: #0f172a;">${escapeHtml(c.hoTen)}</strong> 
                            ${cRankBadge}
                            <span style="color: var(--primary); font-weight: 600;">[${c.maKhachChoi} • @${c.tenDangNhap}]</span>
                            <div style="font-size: 11.5px; color: var(--text-muted);">SĐT: ${c.soDienThoai || 'Chưa cập nhật'} • ${c.dangNgoiMay ? '<span style=\"color: var(--accent); font-weight: 600;\">Đang ngồi máy ' + c.dangNgoiMay + '</span>' : 'Ngoại tuyến'}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: var(--accent);">${formatMoney(c.soDu)}</div>
                            <span class="station-status-badge ${c.trangThaiTK === 'Hoạt động' ? 'badge-available' : 'badge-danger'}" style="margin-bottom: 0;">${c.trangThaiTK}</span>
                        </div>
                    </div>
                `;
                }).join('');
                box.style.display = 'block';
            } else {
                box.innerHTML = '<div style="padding: 12px; color: var(--text-muted); text-align: center;">Không tìm thấy khách hàng nào với từ khóa này.</div>';
                box.style.display = 'block';
            }
        } catch (e) {
            console.error('Search error:', e);
        }
    }, 250);
}

function selectCustomerFromSearch(maTaiKhoan, displayText) {
    const box = document.getElementById('custSearchResults');
    if (box) box.style.display = 'none';
    if (displayText) {
        document.getElementById('custSearchInput').value = displayText;
    }
    loadCustomerDetailsToScreen(maTaiKhoan);
}

async function searchCustomerScreen(queryParam = '') {
    const box = document.getElementById('custSearchResults');
    if (box) box.style.display = 'none';

    const val = queryParam || document.getElementById('custSearchInput').value.trim();
    const url = val ? `api/admin_customers.php?action=list_customers&keyword=${encodeURIComponent(val)}` : `api/admin_customers.php?action=list_customers`;

    try {
        const res = await fetch(url);
        const result = await res.json();

        if (result.success && result.data && result.data.length) {
            const c = result.data[0]; // Khách hàng khớp nhất
            loadCustomerDetailsToScreen(c.maTaiKhoan);
            document.getElementById('custSearchInput').value = `${c.maKhachChoi} - ${c.hoTen}`;
        } else {
            if (val) {
                alert(`Không tìm thấy khách hàng nào với từ khóa "${val}". Vui lòng thử tìm theo tên, SĐT hoặc mã khách hàng (ví dụ: KC01, gamer_an).`);
            }
            document.getElementById('custStatusMsg').textContent = 'Thông báo: Không tìm thấy khách hàng';
        }
    } catch (e) {
        alert('Lỗi tìm kiếm: ' + e.message);
    }
}

async function loadCustomerDetailsToScreen(maTaiKhoan) {
    try {
        const res = await fetch(`api/admin_customers.php?action=get_customer&maTaiKhoan=${maTaiKhoan}`);
        const result = await res.json();

        if (result.success && result.data) {
            selectedCustomerForScreen = result.data;
            const c = selectedCustomerForScreen;

            document.getElementById('custDispId').value = `${c.maKhachChoi} (${c.tenDangNhap})`;
            document.getElementById('custDispFullName').value = c.hoTen;
            document.getElementById('custDispPhone').value = c.soDienThoai;
            document.getElementById('custDispEmail').value = c.email;
            document.getElementById('custDispRegDate').value = c.ngayTao || '2026-09-01';
            if (document.getElementById('custDispGroup')) {
                document.getElementById('custDispGroup').value = c.nhomKhachHang || 'Hội viên';
            }

            const isActive = (c.trangThai === 'Hoạt động');
            document.getElementById('custDispStatusCheck').checked = isActive;
            document.getElementById('custDispStatusLabel').textContent = isActive ? 'Đang hoạt động' : 'Đang khóa';
            document.getElementById('btnToggleCustLock').innerHTML = isActive ? '<i class="fas fa-lock"></i> Khóa tài khoản' : '<i class="fas fa-unlock"></i> Mở khóa tài khoản';

            document.getElementById('custDispTotalBalance').value = formatMoney(c.soDu);

            // Hiển thị bảng lịch sử giao dịch (matching wireframe)
            renderCustomerHistoryTable(c.recentTopups || []);

            document.getElementById('custStatusMsg').textContent = `Thông báo: Đang xem thông tin khách hàng ${c.hoTen} (@${c.tenDangNhap}) - Hạng: ${c.nhomKhachHang || 'Hội viên'}`;
        }
    } catch (e) {
        console.error('Lỗi tải chi tiết khách:', e);
    }
}

function renderCustomerHistoryTable(list) {
    const tbody = document.getElementById('custHistoryTableBody');
    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 16px;">Chưa có giao dịch nạp tiền nào được ghi nhận.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map(g => `
        <tr>
            <td style="font-weight: 700; color: var(--primary);">${g.maGiaoDich}</td>
            <td style="color: #475569; font-size: 12.5px;">${g.thoiGianTao}</td>
            <td>${escapeHtml(g.phuongThuc)}</td>
            <td style="font-weight: 700; color: var(--accent);">${formatMoney(g.soTienNap)}</td>
            <td><span class="station-status-badge ${g.trangThai === 'Thành công' ? 'badge-available' : 'badge-playing'}">${g.trangThai}</span></td>
        </tr>
    `).join('');
}

async function submitSaveCustomerDetails() {
    if (!selectedCustomerForScreen) {
        alert('Vui lòng tra cứu khách hàng trước khi chỉnh sửa.');
        return;
    }

    const hoTen = document.getElementById('custDispFullName').value.trim();
    const phone = document.getElementById('custDispPhone').value.trim();
    const email = document.getElementById('custDispEmail').value.trim();
    const nhomKhachHang = document.getElementById('custDispGroup') ? document.getElementById('custDispGroup').value : (selectedCustomerForScreen.nhomKhachHang || 'Hội viên');

    if (!hoTen || !phone || !email) {
        alert('Vui lòng điền đầy đủ họ tên, số điện thoại và email.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('maTaiKhoan', selectedCustomerForScreen.maTaiKhoan);
        formData.append('hoTen', hoTen);
        formData.append('soDienThoai', phone);
        formData.append('email', email);
        formData.append('nhomKhachHang', nhomKhachHang);
        formData.append('ngaySinh', selectedCustomerForScreen.ngaySinh || '2004-01-01');
        formData.append('gioiTinh', selectedCustomerForScreen.gioiTinh || 'Nam');

        const res = await fetch('api/admin_customers.php?action=update_customer', {
            method: 'POST',
            body: formData
        });

        const result = await res.json();
        alert(result.message);
        if (result.success) {
            document.getElementById('custStatusMsg').textContent = `Thông báo: Đã lưu thông tin và hạng (${nhomKhachHang}) thành công!`;
            loadCustomerDetailsToScreen(selectedCustomerForScreen.maTaiKhoan);
        }
    } catch (e) {
        alert('Lỗi cập nhật: ' + e.message);
    }
}

async function submitToggleCustLock() {
    if (!selectedCustomerForScreen) {
        alert('Vui lòng tra cứu khách hàng trước.');
        return;
    }

    const c = selectedCustomerForScreen;
    const isLocking = (c.trangThai === 'Hoạt động');
    const reason = prompt(`Xác nhận ${isLocking ? 'KHÓA' : 'MỞ KHÓA'} tài khoản ${c.tenDangNhap}? Nhập lý do:`, 'Quy định cửa hàng');
    if (reason === null) return;

    try {
        const formData = new FormData();
        formData.append('maTaiKhoan', c.maTaiKhoan);
        formData.append('lyDo', reason);

        const res = await fetch('api/admin_customers.php?action=toggle_status', {
            method: 'POST',
            body: formData
        });

        const result = await res.json();
        alert(result.message);
        if (result.success) {
            loadCustomerDetailsToScreen(c.maTaiKhoan);
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

function resetCustomerScreen() {
    selectedCustomerForScreen = null;
    document.getElementById('custSearchInput').value = '';
    document.getElementById('custDispId').value = '(tự động hiển thị)';
    document.getElementById('custDispFullName').value = '(tự động hiển thị)';
    document.getElementById('custDispPhone').value = '(tự động hiển thị)';
    document.getElementById('custDispEmail').value = '(tự động hiển thị)';
    document.getElementById('custDispRegDate').value = '(tự động hiển thị)';
    document.getElementById('custDispTotalBalance').value = '(tự động hiển thị)';
    document.getElementById('custDispStatusCheck').checked = true;
    document.getElementById('custDispStatusLabel').textContent = 'Đang hoạt động';
    document.getElementById('btnToggleCustLock').innerHTML = '<i class="fas fa-lock"></i> Khóa tài khoản';
    document.getElementById('custHistoryTableBody').innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">Vui lòng tìm kiếm khách hàng để hiển thị lịch sử giao dịch.</td></tr>';
    document.getElementById('custStatusMsg').textContent = 'Thông báo: Vui lòng nhập mã khách hàng hoặc số điện thoại';
}

// ====================================================================
// 3. SƠ ĐỒ MÁY TRẠM & CHUYỂN MÁY (UC12)
// ====================================================================
let allStations = [];

async function loadStations(silent = false) {
    const container = document.getElementById('stationsGrid');
    if (!container) return;

    if (!silent) {
        container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Đang tải sơ đồ máy...</div>';
    }

    try {
        const res = await fetch('api/admin_stations.php?action=get_stations');
        const result = await res.json();

        if (result.success && result.data) {
            allStations = result.data.stations;
            renderStationStats(result.data.stats);
            renderStations(allStations);

            // Tự động đồng bộ lại modal nếu máy đang mở thao tác vừa được tự động trả về Trống do hết tiền
            const modal = document.getElementById('stationActionModal');
            if (modal && modal.classList.contains('active') && currentSelectedStation) {
                const updatedStation = allStations.find(s => s.maMay === currentSelectedStation.maMay);
                if (updatedStation && updatedStation.trangThai !== currentSelectedStation.trangThai) {
                    openStationModal(updatedStation.maMay);
                }
            }
        }
    } catch (e) {
        if (!silent) container.innerHTML = '<div style="grid-column: 1/-1; color: var(--danger); text-align: center;">Lỗi tải sơ đồ máy.</div>';
    }
}

function renderStationStats(stats) {
    if (document.getElementById('statTotalPcs')) document.getElementById('statTotalPcs').textContent = stats.total;
    if (document.getElementById('statPlayingPcs')) document.getElementById('statPlayingPcs').textContent = stats.playing;
    if (document.getElementById('statAvailablePcs')) document.getElementById('statAvailablePcs').textContent = stats.available;
    if (document.getElementById('statMaintenancePcs')) document.getElementById('statMaintenancePcs').textContent = stats.maintenance;
}

function renderStations(stations) {
    const container = document.getElementById('stationsGrid');
    if (!container) return;

    container.innerHTML = stations.map(s => {
        let statusClass = 'status-available';
        let badgeText = 'Trống';
        let badgeClass = 'badge-available';

        if (s.trangThai === 'Đang chơi') {
            statusClass = 'status-playing';
            badgeText = 'Đang chơi';
            badgeClass = 'badge-playing';
        } else if (s.trangThai === 'Bảo trì') {
            statusClass = 'status-maintenance';
            badgeText = 'Bảo trì';
            badgeClass = 'badge-maintenance';
        }

        const userInfo = (s.trangThai === 'Đang chơi' && s.tenDangNhap) ? `
            <div class="station-user-info"><i class="fas fa-user"></i> ${escapeHtml(s.tenDangNhap)}</div>
            <div class="station-timer">${s.phutDaChoi} phút (${formatMoney(s.chiPhiTamTinh)})</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Dư: ${formatMoney(s.soDuConLai)}</div>
        ` : `
            <div style="font-size: 12px; color: #94a3b8; min-height: 48px; display: flex; align-items: center; justify-content: center;">
                ${s.trangThai === 'Trống' ? 'Sẵn sàng phục vụ' : 'Đang tạm dừng'}
            </div>
        `;

        const isVip = (s.tenLoai && s.tenLoai.includes('VIP')) || (s.tenMay && s.tenMay.includes('VIP'));
        const isPro = (s.tenLoai && s.tenLoai.includes('Thi Đấu')) || (s.tenMay && s.tenMay.includes('Thi Đấu')) || (s.tenMay && s.tenMay.includes('Stage')) || (s.tenMay && s.tenMay.includes('Pro'));
        const tierTag = isVip 
            ? '<span style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 10px; padding: 1px 6px; border-radius: 4px; font-weight: 800;"><i class="fas fa-crown"></i> VIP GAMING</span>'
            : (isPro 
                ? '<span style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; font-size: 10px; padding: 1px 6px; border-radius: 4px; font-weight: 800;"><i class="fas fa-bolt"></i> THI ĐẤU PRO</span>' 
                : '<span style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 10px; padding: 1px 6px; border-radius: 4px; font-weight: 800;">TIÊU CHUẨN</span>');

        return `
            <div class="station-card ${statusClass}" onclick="openStationModal('${s.maMay}')" title="Cấu hình: ${escapeHtml(s.moTa || '')}">
                <div class="station-pc-name" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>${s.tenMay}</span>
                    ${tierTag}
                </div>
                <div class="station-type" style="font-weight: 600; color: #475569;">${escapeHtml(s.tenLoai)} • <span style="color: var(--primary); font-weight: 700;">${formatMoney(s.donGiaGio)}/h</span></div>
                <span class="station-status-badge ${badgeClass}">${badgeText}</span>
                ${userInfo}
            </div>
        `;
    }).join('');
}

function openStationModal(maMay) {
    currentSelectedStation = allStations.find(s => s.maMay === maMay);
    if (!currentSelectedStation) return;

    const s = currentSelectedStation;
    document.getElementById('modalStationTitle').textContent = `Thao tác máy: ${s.tenMay} (${s.maMay})`;
    document.getElementById('modalStationDetails').innerHTML = `
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                <span style="color: #64748b;">Vị trí:</span>
                <strong>${escapeHtml(s.viTri)} (IP: ${s.diaChiIP})</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                <span style="color: #64748b;">Phân khu & Bảng giá:</span>
                <strong>${escapeHtml(s.tenLoai)} (${formatMoney(s.donGiaGio)}/h)</strong>
            </div>
            ${s.moTa ? `
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                <span style="color: #64748b;">Cấu hình máy:</span>
                <span style="font-size: 12px; color: #0f172a; font-weight: 600; text-align: right; max-width: 65%;"><i class="fas fa-microchip" style="color: var(--primary);"></i> ${escapeHtml(s.moTa)}</span>
            </div>
            ` : ''}
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b;">Trạng thái:</span>
                <span class="station-status-badge ${s.trangThai === 'Đang chơi' ? 'badge-playing' : (s.trangThai === 'Trống' ? 'badge-available' : 'badge-maintenance')}">${s.trangThai}</span>
            </div>
        </div>
    `;

    const actionsContainer = document.getElementById('modalStationActions');

    if (s.trangThai === 'Trống') {
        actionsContainer.innerHTML = `
            <div class="form-group" style="margin-bottom: 14px;">
                <label class="field-label" style="width: 100%; margin-bottom: 6px;">Tên đăng nhập hoặc Số điện thoại khách:</label>
                <input type="text" id="inputOpenSessionCustomer" class="form-control" placeholder="Ví dụ: gamer_an hoặc 0987654321">
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn-fresh btn-success" style="flex: 1;" onclick="submitOpenSession('${s.maMay}')">
                    <i class="fas fa-play"></i> Mở máy cho khách
                </button>
                <button class="btn-fresh btn-secondary" onclick="toggleStationMaintenance('${s.maMay}')">
                    <i class="fas fa-tools"></i> Đặt bảo trì
                </button>
            </div>
        `;
    } else if (s.trangThai === 'Đang chơi') {
        const availableTargets = allStations.filter(st => st.trangThai === 'Trống' && st.maMay !== s.maMay);

        actionsContainer.innerHTML = `
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <div style="font-weight: 700; color: #dc2626; margin-bottom: 4px;">Khách đang chơi: ${escapeHtml(s.hoTen)} (@${escapeHtml(s.tenDangNhap)})</div>
                <div style="font-size: 12px; color: #475569;">Bắt đầu lúc: ${s.thoiGianBatDau} • Đã chơi: <strong>${s.phutDaChoi} phút</strong></div>
                <div style="font-size: 12px; color: #475569; margin-top: 2px;">Tạm tính: <strong style="color: var(--gold);">${formatMoney(s.chiPhiTamTinh)}</strong> • Dư: <strong>${formatMoney(s.soDuConLai)}</strong></div>
            </div>

            <!-- Chuyển máy (UC12) -->
            <div style="border-top: 1px solid var(--border); padding-top: 14px; margin-bottom: 16px;">
                <label class="field-label" style="width: 100%; margin-bottom: 6px;">Chuyển sang máy khác (UC12):</label>
                <div style="display: flex; gap: 8px;">
                    <select id="selectTransferTarget" class="form-control" style="flex: 1;">
                        <option value="">-- Chọn máy đích còn trống --</option>
                        ${availableTargets.map(t => `<option value="${t.maMay}">${t.tenMay} (${t.tenLoai} - ${formatMoney(t.donGiaGio)}/h)</option>`).join('')}
                    </select>
                    <button class="btn-fresh" onclick="submitTransferStation('${s.maMay}')">
                        <i class="fas fa-exchange-alt"></i> Chuyển
                    </button>
                </div>
            </div>

            <!-- Ngắt phiên -->
            <div style="display: flex; gap: 10px;">
                <button class="btn-fresh btn-danger" style="flex: 1;" onclick="submitStopSession('${s.maMay}')">
                    <i class="fas fa-stop"></i> Cưỡng chế ngắt phiên & Chốt tiền
                </button>
            </div>
        `;
    } else {
        actionsContainer.innerHTML = `
            <div style="text-align: center; padding: 10px 0;">
                <p style="color: #64748b; margin-bottom: 16px;">Máy hiện đang trong trạng thái bảo trì kỹ thuật.</p>
                <button class="btn-fresh btn-success" onclick="toggleStationMaintenance('${s.maMay}')">
                    <i class="fas fa-check"></i> Hoàn tất bảo trì & Mở lại máy
                </button>
            </div>
        `;
    }

    openModal('stationActionModal');
}

async function submitOpenSession(maMay) {
    const identifier = document.getElementById('inputOpenSessionCustomer').value.trim();
    if (!identifier) {
        alert('Vui lòng nhập tên đăng nhập hoặc số điện thoại khách.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('maMay', maMay);
        formData.append('identifier', identifier);

        const res = await fetch('api/admin_stations.php?action=open_session', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            closeModal('stationActionModal');
            loadStations();
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

async function submitTransferStation(maMayCu) {
    const maMayMoi = document.getElementById('selectTransferTarget').value;
    if (!maMayMoi) {
        alert('Vui lòng chọn máy đích còn trống.');
        return;
    }

    if (!confirm(`Xác nhận chuyển khách từ ${maMayCu} sang ${maMayMoi}?`)) return;

    try {
        const formData = new FormData();
        formData.append('maMayCu', maMayCu);
        formData.append('maMayMoi', maMayMoi);

        const res = await fetch('api/admin_stations.php?action=transfer_station', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            closeModal('stationActionModal');
            loadStations();
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

async function submitStopSession(maMay) {
    if (!confirm(`Xác nhận ngắt phiên tại máy ${maMay}?`)) return;

    try {
        const formData = new FormData();
        formData.append('maMay', maMay);

        const res = await fetch('api/admin_stations.php?action=stop_session', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            closeModal('stationActionModal');
            loadStations();
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

async function toggleStationMaintenance(maMay) {
    try {
        const formData = new FormData();
        formData.append('maMay', maMay);

        const res = await fetch('api/admin_stations.php?action=toggle_maintenance', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            closeModal('stationActionModal');
            loadStations();
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

// ====================================================================
// 4. XỬ LÝ ĐƠN DỊCH VỤ F&B (UC13)
// ====================================================================
async function loadOrders(silent = false) {
    const container = document.getElementById('ordersContainer');
    if (!container) return;

    if (!silent) {
        container.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Đang tải danh sách đơn dịch vụ...</div>';
    }

    const status = document.getElementById('filterOrderStatus')?.value || '';

    try {
        const url = status ? `api/admin_orders.php?action=get_orders&status=${encodeURIComponent(status)}` : 'api/admin_orders.php?action=get_orders';
        const res = await fetch(url);
        const result = await res.json();

        if (result.success && result.data) {
            const { pendingCount, orders } = result.data;
            const badge = document.getElementById('orderPendingBadge');
            if (badge) {
                badge.textContent = pendingCount;
                badge.style.display = pendingCount > 0 ? 'inline-block' : 'none';
            }
            renderOrdersList(orders);
        }
    } catch (e) {
        if (!silent) container.innerHTML = '<div style="color: var(--danger); text-align: center;">Lỗi tải đơn hàng.</div>';
    }
}

function renderOrdersList(orders) {
    const container = document.getElementById('ordersContainer');
    if (!orders.length) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted);">Không có đơn dịch vụ nào.</div>';
        return;
    }

    container.innerHTML = orders.map(ord => {
        let badgeClass = 'badge-maintenance';
        if (ord.trangThaiDon === 'Chờ xử lý') badgeClass = 'badge-playing';
        if (ord.trangThaiDon === 'Đang chuẩn bị') badgeClass = 'badge-available';
        if (ord.trangThaiDon === 'Đã hoàn thành') badgeClass = 'badge-available';

        const itemsHtml = (ord.items || []).map(i => `
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                <span><strong>${escapeHtml(i.tenMon)}</strong> x${i.soLuong} ${i.ghiChu ? `<em style="color: var(--primary);">(${escapeHtml(i.ghiChu)})</em>` : ''}</span>
                <span style="color: var(--gold);">${formatMoney(i.thanhTien)}</span>
            </div>
        `).join('');

        let actionsHtml = '';
        if (ord.trangThaiDon === 'Chờ xử lý') {
            actionsHtml = `
                <button class="btn-fresh btn-sm btn-success" onclick="updateOrderStatus('${ord.maDon}', 'Đang chuẩn bị')"><i class="fas fa-check"></i> Tiếp nhận</button>
                <button class="btn-fresh btn-sm btn-danger" onclick="cancelOrder('${ord.maDon}')"><i class="fas fa-times"></i> Hủy & hoàn tiền</button>
            `;
        } else if (ord.trangThaiDon === 'Đang chuẩn bị') {
            actionsHtml = `
                <button class="btn-fresh btn-sm btn-success" onclick="updateOrderStatus('${ord.maDon}', 'Đã hoàn thành')"><i class="fas fa-check-double"></i> Đã giao</button>
                <button class="btn-fresh btn-sm btn-danger" onclick="cancelOrder('${ord.maDon}')"><i class="fas fa-times"></i> Hủy & hoàn tiền</button>
            `;
        }

        return `
            <div style="background: #ffffff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 14px; box-shadow: var(--shadow-sm);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <div>
                        <span style="font-weight: 800; color: var(--primary); font-size: 15px;">#${ord.maDon}</span>
                        <span style="font-weight: 700; color: #0f172a; margin-left: 10px;"><i class="fas fa-desktop"></i> ${ord.tenMay} (${ord.viTri})</span>
                        <span style="color: var(--text-muted); font-size: 12px; margin-left: 8px;">• Khách: ${escapeHtml(ord.tenKhach)} (@${ord.tenDangNhap})</span>
                    </div>
                    <span class="station-status-badge ${badgeClass}">${ord.trangThaiDon}</span>
                </div>

                <div style="background: #f8fafc; border-radius: 6px; padding: 10px 14px; margin-bottom: 10px;">
                    ${itemsHtml}
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 12px; color: var(--text-muted);">
                        Đặt lúc: ${ord.thoiGianDat} • Thanh toán: <strong>${ord.hinhThucThanhToan}</strong> (${ord.trangThaiThanhToan})
                        ${ord.trangThaiDon === 'Đã hủy' ? `<div style="color: #b91c1c; font-weight: 600; margin-top: 3px;"><i class="fas fa-ban"></i> Lý do hủy: ${escapeHtml(ord.lyDoHuy || 'Hết nguyên liệu món này')}</div>` : ''}
                    </div>
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="font-size: 15px; font-weight: 800; color: var(--gold);">Tổng: ${formatMoney(ord.tongTien)}</div>
                        <div style="display: flex; gap: 6px;">
                            ${actionsHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function updateOrderStatus(maDon, newStatus) {
    try {
        const formData = new FormData();
        formData.append('maDon', maDon);
        formData.append('newStatus', newStatus);

        const res = await fetch('api/admin_orders.php?action=update_status', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) loadOrders();
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

async function cancelOrder(maDon) {
    const reason = prompt('Nhập lý do hủy đơn (tiền sẽ tự động hoàn lại vào tài khoản khách):\nVí dụ: Hết nguyên liệu món này, Bếp nghỉ ca, Khách yêu cầu hủy...', 'Hết nguyên liệu món này');
    if (reason === null) return;
    const finalReason = reason.trim() || 'Hết nguyên liệu món này';

    try {
        const formData = new FormData();
        formData.append('maDon', maDon);
        formData.append('lyDo', finalReason);

        const res = await fetch('api/admin_orders.php?action=cancel_order', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) loadOrders();
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

// ====================================================================
// 5. CHƯƠNG TRÌNH KHUYẾN MÃI (UC14)
// ====================================================================
async function loadPromotions() {
    const container = document.getElementById('promotionsListTable');
    if (!container) return;

    try {
        const res = await fetch('api/admin_promotions.php?action=list_promotions');
        const result = await res.json();

        if (result.success && result.data) {
            renderPromotionsTable(result.data.promotions);
            renderPromotionsHistory(result.data.history);
        }
    } catch (e) {}
}

function renderPromotionsTable(list) {
    const container = document.getElementById('promotionsListTable');
    if (!list.length) {
        container.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">Chưa có chương trình nào.</td></tr>';
        return;
    }

    container.innerHTML = list.map(p => `
        <tr>
            <td style="font-weight: 700; color: var(--primary);">${p.maKhuyenMai}</td>
            <td style="font-weight: 700;">${escapeHtml(p.tenChuongTrinh)}</td>
            <td>${formatMoney(p.mucNapToiThieu)}</td>
            <td style="font-weight: 700; color: var(--accent);">+${formatMoney(p.soTienKhuyenMai)}</td>
            <td style="font-size: 12px;">${p.ngayBatDau} → ${p.ngayKetThuc}</td>
            <td><span class="station-status-badge ${p.trangThai === 'Hoạt động' ? 'badge-available' : 'badge-playing'}">${p.trangThai}</span></td>
            <td>
                <button class="btn-fresh btn-sm btn-secondary" onclick="openEditPromotionModal('${p.maKhuyenMai}', '${escapeHtml(p.tenChuongTrinh)}', ${p.mucNapToiThieu}, ${p.soTienKhuyenMai}, '${p.ngayBatDau}', '${p.ngayKetThuc}', '${p.trangThai}')"><i class="fas fa-edit"></i></button>
                <button class="btn-fresh btn-sm ${p.trangThai === 'Hoạt động' ? 'btn-danger' : 'btn-success'}" onclick="togglePromotionStatus('${p.maKhuyenMai}')">
                    <i class="fas fa-${p.trangThai === 'Hoạt động' ? 'pause' : 'play'}"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function renderPromotionsHistory(history) {
    const container = document.getElementById('promoHistoryList');
    if (!container) return;

    if (!history.length) {
        container.innerHTML = '<div style="color: var(--text-muted); padding: 10px;">Chưa có lịch sử.</div>';
        return;
    }

    container.innerHTML = history.map(h => `
        <div style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 12px;">
            <div style="display: flex; justify-content: space-between;">
                <strong>${escapeHtml(h.tenNhanVien)}</strong>
                <span style="color: var(--text-muted);">${h.thoiGian}</span>
            </div>
            <div style="color: var(--primary); font-weight: 600;">[${h.thaoTac}] ${escapeHtml(h.tenChuongTrinh)}</div>
            <div style="color: var(--text-secondary); margin-top: 2px;">${escapeHtml(h.noiDungMoi || h.noiDungCu || '')}</div>
        </div>
    `).join('');
}

function openAddPromotionModal() {
    document.getElementById('promoModalTitle').textContent = 'Thêm khuyến mãi mới';
    document.getElementById('editPromoId').value = '';
    document.getElementById('editPromoName').value = '';
    document.getElementById('editPromoMin').value = '50000';
    document.getElementById('editPromoBonus').value = '15000';
    document.getElementById('editPromoStart').value = new Date().toISOString().split('T')[0];
    document.getElementById('editPromoEnd').value = '2026-12-31';
    document.getElementById('editPromoStatus').value = 'Hoạt động';

    openModal('promotionModal');
}

function openEditPromotionModal(id, name, min, bonus, start, end, status) {
    document.getElementById('promoModalTitle').textContent = 'Sửa chương trình khuyến mãi';
    document.getElementById('editPromoId').value = id;
    document.getElementById('editPromoName').value = name;
    document.getElementById('editPromoMin').value = min;
    document.getElementById('editPromoBonus').value = bonus;
    document.getElementById('editPromoStart').value = start;
    document.getElementById('editPromoEnd').value = end;
    document.getElementById('editPromoStatus').value = status;

    openModal('promotionModal');
}

async function submitSavePromotion() {
    const id = document.getElementById('editPromoId').value;
    const name = document.getElementById('editPromoName').value.trim();
    const min = document.getElementById('editPromoMin').value;
    const bonus = document.getElementById('editPromoBonus').value;
    const start = document.getElementById('editPromoStart').value;
    const end = document.getElementById('editPromoEnd').value;
    const status = document.getElementById('editPromoStatus').value;

    const formData = new FormData();
    formData.append('maKhuyenMai', id);
    formData.append('tenChuongTrinh', name);
    formData.append('mucNapToiThieu', min);
    formData.append('soTienKhuyenMai', bonus);
    formData.append('ngayBatDau', start);
    formData.append('ngayKetThuc', end);
    formData.append('trangThai', status);

    try {
        const res = await fetch('api/admin_promotions.php?action=save_promotion', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            closeModal('promotionModal');
            loadPromotions();
        }
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

async function togglePromotionStatus(maKhuyenMai) {
    try {
        const formData = new FormData();
        formData.append('maKhuyenMai', maKhuyenMai);

        const res = await fetch('api/admin_promotions.php?action=toggle_status', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) loadPromotions();
    } catch (e) {
        alert('Lỗi: ' + e.message);
    }
}

// ====================================================================
// 6. BÁO CÁO & THỐNG KÊ (UC15, Query 6, 7, 8)
// ====================================================================
async function loadReports() {
    const fromDate = document.getElementById('reportFromDate')?.value || '';
    const toDate = document.getElementById('reportToDate')?.value || '';

    try {
        const resSummary = await fetch(`api/admin_reports.php?action=summary&fromDate=${fromDate}&toDate=${toDate}`);
        const rSummary = await resSummary.json();
        if (rSummary.success && rSummary.data) {
            const s = rSummary.data;
            document.getElementById('repTotalTopup').textContent = formatMoney(s.tongTienNap);
            document.getElementById('repTotalHours').textContent = `${s.tongGioChoi} giờ`;
            document.getElementById('repTotalFnb').textContent = formatMoney(s.tongTienDichVu);
            document.getElementById('repTotalRevenue').textContent = formatMoney(s.tongDoanhThu);
        }

        // Query 6
        const resQ6 = await fetch('api/admin_reports.php?action=top_gamers');
        const rQ6 = await resQ6.json();
        if (rQ6.success && rQ6.data) {
            document.getElementById('query6TableBody').innerHTML = rQ6.data.map((g, idx) => `
                <tr>
                    <td style="font-weight: 700;">#${idx + 1}</td>
                    <td style="font-weight: 700;">${escapeHtml(g.hoTen)} (${g.maKhachChoi})</td>
                    <td>${g.soPhienChoi} phiên</td>
                    <td style="font-weight: 700; color: var(--primary);">${g.tongGioChoi} giờ (${g.tongThoiGianPhut} phút)</td>
                </tr>
            `).join('');
        }

        // Query 8
        const resQ8 = await fetch('api/admin_reports.php?action=top_spenders');
        const rQ8 = await resQ8.json();
        if (rQ8.success && rQ8.data) {
            document.getElementById('query8TableBody').innerHTML = rQ8.data.map((s, idx) => `
                <tr>
                    <td style="font-weight: 700;">#${idx + 1}</td>
                    <td style="font-weight: 700;">${escapeHtml(s.hoTen)} (${s.maKhachChoi})</td>
                    <td>${s.soLanNap} lần</td>
                    <td style="font-weight: 700; color: var(--accent);">${formatMoney(s.tongTienNap)}</td>
                </tr>
            `).join('');
        }

        // Query 7
        const resQ7 = await fetch('api/admin_reports.php?action=session_revenue');
        const rQ7 = await resQ7.json();
        if (rQ7.success && rQ7.data) {
            document.getElementById('query7TableBody').innerHTML = rQ7.data.map(s => `
                <tr>
                    <td style="font-weight: 700; color: var(--primary);">${s.maPhien}</td>
                    <td style="font-weight: 700;">${escapeHtml(s.hoTen)}</td>
                    <td style="font-size: 12px;">${s.thoiGianBatDau}</td>
                    <td>${s.tongThoiGianPhut} phút</td>
                    <td style="font-weight: 700; color: var(--gold);">${formatMoney(s.doanhThu)}</td>
                </tr>
            `).join('');
        }
    } catch (e) {}
}

// ====================================================================
// UTILITY HELPERS
// ====================================================================
function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

async function adminLogout() {
    if (!confirm('Bạn có chắc chắn muốn đăng xuất khỏi bàn làm việc thu ngân?')) return;
    try {
        await fetch('api/auth.php?action=staff_logout');
        window.location.href = 'login.php';
    } catch (e) {
        window.location.href = 'login.php';
    }
}

// ====================================================================
// F&B INVENTORY & STOCK MANAGEMENT
// ====================================================================
function openInventoryModal() {
    openModal('inventoryModal');
    loadInventory();
}

async function loadInventory() {
    const container = document.getElementById('inventoryTableContainer');
    if (!container) return;
    
    container.innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Đang tải kho món...</div>';

    try {
        const res = await fetch('api/admin_orders.php?action=get_inventory');
        const result = await res.json();

        if (!result.success || !result.data) {
            container.innerHTML = `<div style="color: var(--danger); text-align: center; padding: 20px;">Lỗi: ${escapeHtml(result.message || 'Không thể tải')}</div>`;
            return;
        }

        const items = result.data;
        if (!items.length) {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-muted);">Không có món nào trong kho.</div>';
            return;
        }

        let html = `
            <table class="wireframe-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f1f5f9; text-align: left;">
                        <th style="padding: 10px; width: 60px;">Mã</th>
                        <th style="padding: 10px;">Tên món & Phân loại</th>
                        <th style="padding: 10px; width: 100px;">Đơn giá</th>
                        <th style="padding: 10px; width: 140px; text-align: center;">Tồn kho</th>
                        <th style="padding: 10px; width: 100px; text-align: center;">Trạng thái</th>
                        <th style="padding: 10px; text-align: right;">Thao tác nhập thêm</th>
                    </tr>
                </thead>
                <tbody>
        `;

        items.forEach(it => {
            const stock = parseInt(it.soLuongTon) || 0;
            let stockBadge = '';
            if (stock <= 0) {
                stockBadge = '<span style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 3px 8px; border-radius: 999px; font-weight: 800; font-size: 11.5px;"><i class="fas fa-times-circle"></i> Hết hàng (0)</span>';
            } else if (stock <= 15) {
                stockBadge = `<span style="background: #fef3c7; color: #d97706; border: 1px solid #fde68a; padding: 3px 8px; border-radius: 999px; font-weight: 700; font-size: 11.5px;"><i class="fas fa-exclamation-triangle"></i> Sắp hết (${stock} ${escapeHtml(it.donViTinh)})</span>`;
            } else {
                stockBadge = `<span style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 999px; font-weight: 700; font-size: 11.5px;"><i class="fas fa-check-circle"></i> Còn ${stock} ${escapeHtml(it.donViTinh)}</span>`;
            }

            const imgThumb = it.hinhAnh ? `<img src="${it.hinhAnh}?v=2" style="width: 38px; height: 38px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;" onerror="this.style.display='none'">` : '';

            html += `
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px; font-weight: 700; color: #64748b;">${escapeHtml(it.maMon)}</td>
                    <td style="padding: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            ${imgThumb}
                            <div>
                                <strong style="color: #0f172a; font-size: 13.5px;">${escapeHtml(it.tenMon)}</strong>
                                <div style="font-size: 11.5px; color: var(--text-muted);">${escapeHtml(it.loaiMon)} • ĐVT: ${escapeHtml(it.donViTinh)}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 10px; font-weight: 700; color: #0284c7;">
                        ${formatMoney(it.donGia)}
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        ${stockBadge}
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <span style="font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px; ${it.trangThai === 'Còn hàng' ? 'color: #059669; background: #ecfdf5;' : 'color: #dc2626; background: #fee2e2;'}">
                            ${escapeHtml(it.trangThai)}
                        </span>
                    </td>
                    <td style="padding: 10px; text-align: right;">
                        <div style="display: inline-flex; align-items: center; gap: 4px;">
                            <button class="btn-fresh btn-secondary btn-sm" onclick="adminQuickAddStock('${it.maMon}', 10)" style="padding: 3px 8px; font-size: 11px;" title="Cộng thêm 10 phần">+10</button>
                            <button class="btn-fresh btn-secondary btn-sm" onclick="adminQuickAddStock('${it.maMon}', 50)" style="padding: 3px 8px; font-size: 11px;" title="Cộng thêm 50 phần">+50</button>
                            <button class="btn-fresh btn-primary btn-sm" onclick="adminSetCustomStock('${it.maMon}', '${escapeHtml(it.tenMon)}', ${stock})" style="padding: 3px 8px; font-size: 11px;" title="Nhập số lượng cụ thể">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-fresh ${it.trangThai === 'Còn hàng' ? 'btn-danger' : 'btn-success'} btn-sm" onclick="adminToggleItemStatus('${it.maMon}', '${it.trangThai}')" style="padding: 3px 6px; font-size: 11px;" title="${it.trangThai === 'Còn hàng' ? 'Chuyển sang Hết hàng' : 'Mở lại Còn hàng'}">
                                <i class="fas ${it.trangThai === 'Còn hàng' ? 'fa-ban' : 'fa-check'}"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = `<div style="color: var(--danger); text-align: center; padding: 20px;">Lỗi tải dữ liệu: ${e.message}</div>`;
    }
}

async function adminQuickAddStock(maMon, amount) {
    try {
        const formData = new FormData();
        formData.append('maMon', maMon);
        formData.append('addQuantity', amount);

        const res = await fetch('api/admin_orders.php?action=update_stock', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            loadInventory();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (e) {
        alert('Lỗi kết nối: ' + e.message);
    }
}

async function adminSetCustomStock(maMon, tenMon, currentStock) {
    const input = prompt(`Nhập số lượng tồn kho mới cho món "${tenMon}":`, currentStock);
    if (input === null) return;
    const newQty = parseInt(input);
    if (isNaN(newQty) || newQty < 0) {
        alert('Số lượng không hợp lệ (phải >= 0).');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('maMon', maMon);
        formData.append('newQuantity', newQty);

        const res = await fetch('api/admin_orders.php?action=update_stock', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            loadInventory();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (e) {
        alert('Lỗi kết nối: ' + e.message);
    }
}

async function adminToggleItemStatus(maMon, currentStatus) {
    const newStatus = (currentStatus === 'Còn hàng') ? 'Hết hàng' : 'Còn hàng';
    try {
        const formData = new FormData();
        formData.append('maMon', maMon);
        formData.append('trangThai', newStatus);

        const res = await fetch('api/admin_orders.php?action=update_stock', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            loadInventory();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (e) {
        alert('Lỗi kết nối: ' + e.message);
    }
}

async function adminRefillAllStock(amount = 50) {
    if (!confirm(`Bạn có chắc chắn muốn nạp thêm +${amount} phần cho TẤT CẢ các món trong kho?`)) return;
    try {
        const formData = new FormData();
        formData.append('amount', amount);

        const res = await fetch('api/admin_orders.php?action=refill_all', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            alert(result.message);
            loadInventory();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (e) {
        alert('Lỗi kết nối: ' + e.message);
    }
}
