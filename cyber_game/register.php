<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Hội Viên - Quản Lý Quán Net</title>
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
            padding: 30px 20px;
        }
        .register-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 600px;
            padding: 30px;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) {
            .form-grid-2 { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: #ecfdf5; color: var(--accent); border-radius: 10px; font-size: 22px; margin-bottom: 10px;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">ĐĂNG KÝ HỘI VIÊN MỚI</h1>
            <p style="color: var(--text-muted); font-size: 13px;">Tạo tài khoản chơi máy & nhận ngay ưu đãi tiền thưởng</p>
            <div style="display: inline-block; margin-top: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                <i class="fas fa-gift"></i> Tặng 10.000 VNĐ vào tài khoản sau khi kích hoạt!
            </div>
        </div>

        <form id="registerForm" onsubmit="handleRegister(event)">
            <!-- Alert Box for visual Vietnamese error / success messages -->
            <div id="regAlertBox" style="display: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; font-weight: 600;"></div>

            <div class="form-grid-2">
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 14px;">
                    <label class="field-label" style="width: 100%;">Tên đăng nhập (*):</label>
                    <input type="text" id="regUsername" class="form-control" placeholder="Ví dụ: gamer_pro" required>
                </div>
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 14px;">
                    <label class="field-label" style="width: 100%;">Họ và tên (*):</label>
                    <input type="text" id="regFullName" class="form-control" placeholder="Nguyễn Văn A" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 14px;">
                    <label class="field-label" style="width: 100%;">Số điện thoại (*):</label>
                    <input type="tel" id="regPhone" class="form-control" placeholder="0987654321" pattern="[0-9]{10,11}" required>
                </div>
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 14px;">
                    <label class="field-label" style="width: 100%;">Email (*):</label>
                    <input type="email" id="regEmail" class="form-control" placeholder="an@example.com" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 14px;">
                    <label class="field-label" style="width: 100%;">Ngày sinh (*):</label>
                    <input type="date" id="regBirthday" class="form-control" value="2004-01-01" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 14px;">
                    <label class="field-label" style="width: 100%;">Giới tính:</label>
                    <select id="regGender" class="form-control">
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                        <option value="Khác">Khác</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 20px;">
                    <label class="field-label" style="width: 100%;">Mật khẩu (*):</label>
                    <input type="password" id="regPassword" class="form-control" placeholder="Tối thiểu 6 ký tự" minlength="6" required>
                </div>
                <div class="field-row" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 20px;">
                    <label class="field-label" style="width: 100%;">Xác nhận mật khẩu (*):</label>
                    <input type="password" id="regConfirmPassword" class="form-control" placeholder="Nhập lại mật khẩu" minlength="6" required>
                </div>
            </div>

            <button type="submit" id="btnSubmitRegister" class="btn-fresh btn-success" style="width: 100%; padding: 12px; font-size: 14px;">
                <i class="fas fa-check-circle"></i> Đăng Ký Tài Khoản Ngay
            </button>
        </form>

        <div style="text-align: center; margin-top: 16px; font-size: 13px;">
            <span style="color: var(--text-muted);">Đã có tài khoản?</span>
            <a href="login.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Đăng nhập để vào máy</a>
        </div>
    </div>

    <script>
        // Tự động gán thông báo lỗi tiếng Việt chuẩn cho toàn bộ các trường nhập liệu
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('input, select');

            inputs.forEach(input => {
                input.addEventListener('invalid', function (e) {
                    this.setCustomValidity('');

                    if (this.validity.valueMissing) {
                        if (this.id === 'regUsername') {
                            this.setCustomValidity('Vui lòng nhập tên đăng nhập.');
                        } else if (this.id === 'regFullName') {
                            this.setCustomValidity('Vui lòng nhập họ và tên.');
                        } else if (this.id === 'regPhone') {
                            this.setCustomValidity('Vui lòng nhập số điện thoại.');
                        } else if (this.id === 'regEmail') {
                            this.setCustomValidity('Vui lòng nhập địa chỉ email.');
                        } else if (this.id === 'regBirthday') {
                            this.setCustomValidity('Vui lòng chọn ngày sinh.');
                        } else if (this.id === 'regPassword') {
                            this.setCustomValidity('Vui lòng nhập mật khẩu.');
                        } else if (this.id === 'regConfirmPassword') {
                            this.setCustomValidity('Vui lòng nhập lại mật khẩu xác nhận.');
                        } else {
                            this.setCustomValidity('Vui lòng không để trống trường này.');
                        }
                    } else if (this.validity.patternMismatch) {
                        if (this.id === 'regPhone') {
                            this.setCustomValidity('Vui lòng nhập đúng định dạng số điện thoại (10 hoặc 11 chữ số, ví dụ: 0987654321).');
                        } else {
                            this.setCustomValidity('Dữ liệu không đúng định dạng yêu cầu.');
                        }
                    } else if (this.validity.typeMismatch) {
                        if (this.type === 'email') {
                            this.setCustomValidity('Vui lòng nhập đúng định dạng email (ví dụ: an@example.com).');
                        }
                    } else if (this.validity.tooShort) {
                        this.setCustomValidity(`Mật khẩu phải có tối thiểu ${this.minLength} ký tự (hiện có ${this.value.length} ký tự).`);
                    }
                });

                // Tự động xóa thông báo lỗi khi người dùng bắt đầu nhập lại
                input.addEventListener('input', function () {
                    this.setCustomValidity('');
                    const alertBox = document.getElementById('regAlertBox');
                    if (alertBox) alertBox.style.display = 'none';
                });
            });
        });

        function showRegAlert(msg, type = 'error') {
            const box = document.getElementById('regAlertBox');
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

        async function handleRegister(e) {
            e.preventDefault();

            const username = document.getElementById('regUsername').value.trim();
            const fullName = document.getElementById('regFullName').value.trim();
            const phone = document.getElementById('regPhone').value.trim();
            const email = document.getElementById('regEmail').value.trim();
            const birthday = document.getElementById('regBirthday').value;
            const gender = document.getElementById('regGender').value;
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('regConfirmPassword').value;

            // Kiểm tra khớp mật khẩu
            if (password !== confirmPassword) {
                const confirmInput = document.getElementById('regConfirmPassword');
                confirmInput.setCustomValidity('Mật khẩu xác nhận không trùng khớp!');
                confirmInput.reportValidity();
                showRegAlert('Mật khẩu xác nhận không trùng khớp!', 'error');
                return;
            }

            const btn = document.getElementById('btnSubmitRegister');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang đăng ký...';

            try {
                const formData = new FormData();
                formData.append('tenDangNhap', username);
                formData.append('matKhau', password);
                formData.append('xacNhanMatKhau', confirmPassword);
                formData.append('hoTen', fullName);
                formData.append('soDienThoai', phone);
                formData.append('email', email);
                formData.append('ngaySinh', birthday);
                formData.append('gioiTinh', gender);

                const res = await fetch('api/auth.php?action=gamer_register', { method: 'POST', body: formData });
                const result = await res.json();

                if (result.success) {
                    showRegAlert(result.message + ' Đang chuyển về trang đăng nhập...', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1200);
                } else {
                    showRegAlert(result.message, 'error');
                }
            } catch (err) {
                showRegAlert('Lỗi kết nối máy chủ: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Đăng Ký Tài Khoản Ngay';
            }
        }
    </script>
</body>
</html>
