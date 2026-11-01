<?php
/**
 * Unified Login Page (Khách chơi & Thu ngân)
 * Giao diện tươi sáng, hiện đại, thân thiện
 */
require_once __DIR__ . '/config/db.php';

// Tự động quét và giải phóng các máy đã hết tiền ngay khi tải trang đăng nhập
autoCloseExpiredSessions($pdo);

$stationsStmt = $pdo->query("
    SELECT mt.maMay, mt.tenMay, mt.trangThai, lm.tenLoai, lm.moTa, bg.donGiaGio 
    FROM MAY_TRAM mt
    JOIN LOAI_MAY lm ON mt.maLoaiMay = lm.maLoaiMay
    LEFT JOIN BANG_GIA bg ON mt.maLoaiMay = bg.maLoaiMay
    ORDER BY mt.maMay ASC
");
$stations = $stationsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Phần Mềm Quản Lý Quán Net</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }
        .role-switch-row {
            display: flex;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        .role-tab-btn {
            flex: 1;
            padding: 14px 16px;
            text-align: center;
            font-weight: 700;
            font-size: 13.5px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s;
            border-bottom: 2px solid transparent;
        }
        .role-tab-btn.active {
            background: #ffffff;
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .demo-chip {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 4px 12px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            color: #334155;
            transition: all 0.15s;
        }
        .demo-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #eff6ff;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div style="background: #ffffff; padding: 24px 28px 16px; text-align: center; border-bottom: 1px solid #f1f5f9;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: #eff6ff; color: var(--primary); border-radius: 10px; font-size: 22px; margin-bottom: 12px;">
                <i class="fas fa-desktop"></i>
            </div>
            <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">PHẦN MỀM QUẢN LÝ QUÁN NET</h1>
            <p style="color: var(--text-muted); font-size: 13px;">Hệ thống đăng nhập máy trạm & quầy thu ngân</p>
        </div>

        <!-- Role Toggle Tabs -->
        <div class="role-switch-row">
            <div class="role-tab-btn active" id="btnRoleGamer" onclick="setRole('gamer')">
                <i class="fas fa-gamepad"></i> Khách chơi (Máy trạm)
            </div>
            <div class="role-tab-btn" id="btnRoleStaff" onclick="setRole('staff')">
                <i class="fas fa-cash-register"></i> Thu ngân / Quản trị
            </div>
        </div>

        <div style="padding: 24px 28px;">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'out_of_money'): ?>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 13px; color: #991b1b;">
                    <i class="fas fa-exclamation-circle" style="font-size: 18px; color: #dc2626; flex-shrink: 0;"></i>
                    <div>
                        <strong>Phiên chơi đã tự động ngắt kết nối:</strong> Tài khoản đã hết số dư (0 đ). Vui lòng nạp thêm tiền tại quầy thu ngân để tiếp tục mở máy.
                    </div>
                </div>
            <?php endif; ?>
            <form id="loginForm" onsubmit="handleLogin(event)">
                <!-- Alert Box for visual Vietnamese error / success messages -->
                <div id="loginAlertBox" style="display: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; font-weight: 600;"></div>

                <!-- Station selection for gamer -->
                <div class="field-row" id="groupSelectStation" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 16px;">
                    <label class="field-label" style="width: 100%; display: flex; justify-content: space-between;">
                        <span>Máy trạm đang ngồi:</span>
                        <span style="font-size: 11px; color: var(--primary); font-weight: normal;">*Tiêu Chuẩn: 8k/h | VIP Gaming: 12k/h | Thi Đấu Pro: 18k/h</span>
                    </label>
                    <select id="loginStation" class="form-control" style="font-weight: 600;" onchange="updateStationHardwareText()">
                        <?php foreach ($stations as $st): ?>
                            <?php 
                                $isVip = (str_contains($st['tenLoai'], 'VIP') || str_contains($st['tenMay'], 'VIP'));
                                $isPro = (str_contains($st['tenLoai'], 'Thi Đấu') || str_contains($st['tenMay'], 'Thi Đấu') || str_contains($st['tenMay'], 'Pro') || str_contains($st['tenMay'], 'Stage'));
                                $tier = $isVip ? '👑 VIP Gaming' : ($isPro ? '⚡ Thi Đấu Pro' : '🖥️ Tiêu Chuẩn');
                                $price = number_format($st['donGiaGio'] ?? 8000, 0, ',', '.') . ' đ/h';
                                $statusText = match($st['trangThai']) {
                                    'Đang chơi' => '🔴 Đang có người chơi',
                                    'Bảo trì' => '⚠️ Bảo trì',
                                    default => '🟢 Trống'
                                };
                            ?>
                            <option value="<?= $st['maMay'] ?>" data-mota="<?= htmlspecialchars($st['moTa'] ?? '') ?>" <?= ($st['trangThai'] === 'Bảo trì' ? 'disabled' : '') ?>>
                                <?= $st['maMay'] ?>: <?= $st['tenMay'] ?> [<?= $tier ?> - <?= $price ?>] - <?= $statusText ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Realtime Hardware Specs Preview Box -->
                    <div id="stationHardwarePreview" style="width: 100%; margin-top: 4px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 7px 10px; font-size: 11.5px; color: #334155; display: flex; align-items: flex-start; gap: 8px;">
                        <i class="fas fa-microchip" style="color: var(--primary); font-size: 13px; margin-top: 2px;"></i>
                        <div style="flex: 1; line-height: 1.4;">
                            <span style="font-size: 10.5px; text-transform: uppercase; color: #64748b; font-weight: 700;">Cấu hình máy:</span>
                            <span id="stationHardwareText" style="color: #0f172a; font-weight: 600;">Core i5 12400F, RAM 16GB, RTX 3060, Màn hình 165Hz 24 inch</span>
                        </div>
                    </div>
                </div>

                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 16px;">
                    <label class="field-label" style="width: 100%;">Tên đăng nhập:</label>
                    <input type="text" id="loginUsername" class="form-control" placeholder="Nhập tên tài khoản" required>
                </div>

                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 20px;">
                    <label class="field-label" style="width: 100%;">Mật khẩu:</label>
                    <input type="password" id="loginPassword" class="form-control" placeholder="Nhập mật khẩu" required>
                </div>

                <button type="submit" id="btnSubmitLogin" class="btn-fresh" style="width: 100%; padding: 12px; font-size: 14px;">
                    <i class="fas fa-sign-in-alt"></i> Bắt đầu chơi máy
                </button>
            </form>

            <!-- Quick Demo Accounts -->
            <div style="margin-top: 20px; padding-top: 14px; border-top: 1px dashed #cbd5e1;">
                <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 8px;">
                    Chọn nhanh tài khoản Demo (Mật khẩu: <code>123456</code>):
                </div>
                <div id="demoGamerList" style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <span class="demo-chip" onclick="quickFill('gamer_an', '123456', 'PC01')">gamer_an (85k • 👑 VIP • Máy 01 Tiêu Chuẩn)</span>
                    <span class="demo-chip" onclick="quickFill('hoang_pro', '123456', 'PC09')">hoang_pro (145k • 👑 VIP • Máy 09 VIP Gaming)</span>
                    <span class="demo-chip" onclick="quickFill('long_sniper', '123456', 'PC02')">long_sniper (52k • ⭐ Hội viên • Máy 02 Tiêu Chuẩn)</span>
                    <span class="demo-chip" onclick="quickFill('huy_faker', '123456', 'PC17')">huy_faker (210k • 💎 Kim Cương • Máy 17 Thi Đấu Pro)</span>
                </div>
                <div id="demoStaffList" style="display: none; flex-wrap: wrap; gap: 8px;">
                    <span class="demo-chip" onclick="quickFill('admin', '123456')">admin (Quản lý)</span>
                    <span class="demo-chip" onclick="quickFill('thungan1', '123456')">thungan1 (Ca sáng)</span>
                    <span class="demo-chip" onclick="quickFill('thungan2', '123456')">thungan2 (Ca tối)</span>
                </div>
            </div>

            <div style="text-align: center; margin-top: 16px; font-size: 13px;">
                <span style="color: var(--text-muted);">Khách hàng mới?</span>
                <a href="register.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Đăng ký hội viên (+10.000đ)</a>
            </div>
        </div>
    </div>

    <script>
        let currentRole = 'gamer';

        function setRole(role) {
            currentRole = role;
            document.getElementById('btnRoleGamer').classList.toggle('active', role === 'gamer');
            document.getElementById('btnRoleStaff').classList.toggle('active', role === 'staff');

            document.getElementById('groupSelectStation').style.display = (role === 'gamer') ? 'flex' : 'none';
            document.getElementById('demoGamerList').style.display = (role === 'gamer') ? 'flex' : 'none';
            document.getElementById('demoStaffList').style.display = (role === 'staff') ? 'flex' : 'none';

            const submitBtn = document.getElementById('btnSubmitLogin');
            if (role === 'gamer') {
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Bắt đầu chơi máy';
                submitBtn.className = 'btn-fresh';
            } else {
                submitBtn.innerHTML = '<i class="fas fa-cash-register"></i> Đăng nhập quầy thu ngân';
                submitBtn.className = 'btn-fresh btn-success';
            }
        }

        function updateStationHardwareText() {
            const sel = document.getElementById('loginStation');
            if (!sel) return;
            const opt = sel.options[sel.selectedIndex];
            if (!opt) return;
            const mota = opt.getAttribute('data-mota') || '';
            const textEl = document.getElementById('stationHardwareText');
            if (textEl && mota) {
                textEl.textContent = mota;
            }
        }

        function quickFill(user, pass, pc = '') {
            document.getElementById('loginUsername').value = user;
            document.getElementById('loginPassword').value = pass;
            if (pc && document.getElementById('loginStation')) {
                document.getElementById('loginStation').value = pc;
            }
            updateStationHardwareText();
        }

        function showLoginAlert(msg, type = 'error') {
            const box = document.getElementById('loginAlertBox');
            if (!box) {
                alert(msg);
                return;
            }
            box.style.display = 'flex';
            box.style.alignItems = 'center';
            box.style.gap = '10px';
            if (type === 'success') {
                box.style.background = '#ecfdf5';
                box.style.border = '1px solid #a7f3d0';
                box.style.color = '#059669';
                box.innerHTML = `<i class="fas fa-check-circle" style="font-size: 16px;"></i><span>${msg}</span>`;
            } else {
                box.style.background = '#fef2f2';
                box.style.border = '1px solid #fecaca';
                box.style.color = '#dc2626';
                box.innerHTML = `<i class="fas fa-exclamation-triangle" style="font-size: 16px;"></i><span>${msg}</span>`;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateStationHardwareText();

            // Gán thông báo lỗi tiếng Việt chuẩn cho toàn bộ các trường trên form đăng nhập
            const loginInputs = document.querySelectorAll('#loginForm input');
            loginInputs.forEach(input => {
                input.addEventListener('invalid', function () {
                    this.setCustomValidity('');
                    if (this.validity.valueMissing) {
                        if (this.id === 'loginUsername') {
                            this.setCustomValidity('Vui lòng nhập tên đăng nhập.');
                        } else if (this.id === 'loginPassword') {
                            this.setCustomValidity('Vui lòng nhập mật khẩu.');
                        } else {
                            this.setCustomValidity('Vui lòng không để trống trường này.');
                        }
                    }
                });

                input.addEventListener('input', function () {
                    this.setCustomValidity('');
                    const alertBox = document.getElementById('loginAlertBox');
                    if (alertBox) alertBox.style.display = 'none';
                });
            });
        });

        async function handleLogin(e) {
            e.preventDefault();

            const username = document.getElementById('loginUsername').value.trim();
            const password = document.getElementById('loginPassword').value;
            const station = document.getElementById('loginStation')?.value || 'PC01';

            const btn = document.getElementById('btnSubmitLogin');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xác thực...';

            try {
                const formData = new FormData();
                formData.append('tenDangNhap', username);
                formData.append('matKhau', password);

                let endpoint = '';
                if (currentRole === 'gamer') {
                    endpoint = 'api/auth.php?action=gamer_login';
                    formData.append('maMay', station);
                } else {
                    endpoint = 'api/auth.php?action=staff_login';
                }

                const res = await fetch(endpoint, { method: 'POST', body: formData });
                const result = await res.json();

                if (result.success) {
                    if (currentRole === 'gamer') {
                        window.location.href = 'client.php';
                    } else {
                        window.location.href = 'admin.php';
                    }
                } else {
                    showLoginAlert(result.message, 'error');
                }
            } catch (err) {
                showLoginAlert('Lỗi kết nối máy chủ: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                setRole(currentRole);
            }
        }
    </script>
</body>
</html>
