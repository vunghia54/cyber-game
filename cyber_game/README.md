# CYBERNET GAMING - HỆ THỐNG QUẢN LÝ PHÒNG MÁY & GAME TRỰC TUYẾN
> **Đồ án môn học:** Thực tập cơ sở ngành (K19 - Nhóm 05)  
> **Chủ đề:** Xây dựng modun quản lý khách chơi game trong cửa hàng cung cấp dịch vụ internet và trò chơi trực tuyến  
> **Sinh viên thực hiện:** Lưu Gia Bảo, Bùi Hữu Hoạt, Vũ Ngọc Bảo Long, Vũ Trung Nghĩa, Phạm Công Tâm  
> **Giảng viên hướng dẫn:** ThS. Vũ Thị Dương  
> **Môi trường chạy:** XAMPP (Apache + MariaDB/MySQL 10.4+ + PHP 8.2+)

---

## 1. TỔNG QUAN HỆ THỐNG
Dự án được thiết kế chuẩn mực bởi kỹ sư có kinh nghiệm chuyên sâu về web, bám sát **100% dữ liệu khảo sát thực tế**, **15 Use Case UML** và **16 bảng quan hệ chuẩn 3NF trong biểu đồ ERD** của nhóm.

Đặc biệt, giao diện được xây dựng **bám sát 100% bản vẽ phác thảo Wireframe (image2.png & image3.png)** từ tài liệu đồ án Tuần 5, kết hợp với **phong cách thiết kế tươi sáng (Fresh & Bright Light UI)**:
- **Tông màu chủ đạo:** Trắng sứ (`#ffffff`), xám nhạt (`#f1f5f9` / `#f8fafc`), xanh dương hoàng gia (`#2563eb`), xanh ngọc (`#10b981`) và cam hổ phách (`#f59e0b`).
- **Khung giao diện cửa sổ ứng dụng:** Mang phong cách phần mềm quản lý quán net chuyên nghiệp với thanh tiêu đề Windows `_ [] X`, thanh bên điều hướng (Sidebar), thanh hành động dưới cùng và thanh trạng thái (Status Bar) thời gian thực.
- **Tái hiện chuẩn xác 2 màn hình phác thảo:**
  1. **Màn hình Nạp tiền (image2.png):** Hộp tìm kiếm khách hàng tự động, hiển thị số dư, nhập số tiền nạp với 4 phím tắt nhanh (50k, 100k, 200k, 500k), lựa chọn phương thức thanh toán linh hoạt, nút Hủy / Xác nhận nạp tiền.
  2. **Màn hình Thông tin tài khoản khách hàng (image3.png):** Thanh tìm kiếm trên đầu, lưới thông tin 2 cột (Mã KH, Họ tên, SĐT, Email, Ngày đăng ký, Trạng thái checkbox), ô hiển thị số dư to rõ nét, bảng lịch sử giao dịch chi tiết và các nút thao tác Chỉnh sửa / Khóa tài khoản / Đóng.

Hệ thống cung cấp 2 phân hệ chuyên biệt:
1. **Phân hệ Máy trạm Khách chơi (Gamer Client Portal):** Giao diện chạy trên máy trạm dành cho game thủ, theo dõi giờ chơi và chi phí thời gian thực, nạp tiền trực tuyến VietQR, gọi dịch vụ F&B tại chỗ và quản lý thông tin tài khoản.
2. **Phân hệ Thu ngân & Quản trị (Cashier & Management Portal):** Bàn làm việc thu ngân quản lý sơ đồ 20 máy trạm, mở máy, chuyển máy thông minh (UC12), nạp tiền tại quầy in phiếu POS, duyệt đơn bếp và hệ thống báo cáo doanh thu theo chuẩn SQL Query 6, 7, 8.

---

## 2. ÁNH XẠ 15 USE CASE ĐẶC TẢ VÀO HỆ THỐNG
| Mã UC | Tên Use Case | Phân hệ | File xử lý chính | Mô tả chức năng & Nghiệp vụ đã hoàn thiện |
|---|---|---|---|---|
| **UC_01** | Đăng ký tài khoản | Khách | `register.php`, `api/auth.php` | Tạo tài khoản hội viên, kiểm tra trùng lặp SĐT/Email/Username, tặng ngay 10.000đ tân thủ. |
| **UC_02** | Đăng nhập | Khách/NV | `login.php`, `api/auth.php` | Xác thực mật khẩu bcrypt, gắn máy trạm, khởi tạo `PHIEN_CHOI` và `CHI_TIET_SU_DUNG_MAY`. |
| **UC_03** | Đăng xuất | Khách/NV | `client.php`, `api/auth.php` | Chốt thời gian kết thúc, tính chi phí theo bảng giá, trừ số dư tài khoản, giải phóng máy về Trống. |
| **UC_04** | Quản lý thông tin cá nhân | Khách | `api/client_profile.php` | Xem/sửa thông tin họ tên, ngày sinh, SĐT, email; đổi mật khẩu an toàn. |
| **UC_05** | Xem phiên chơi & Số dư | Khách | `client.php`, `api/client_session.php` | Real-time Heartbeat mỗi 4s: đếm phút chơi, tính tiền giờ tạm tính, **cảnh báo thông minh khi dưới 15 phút** (theo 75% khảo sát). |
| **UC_06** | Xem lịch sử giao dịch & Phiên | Khách | `api/client_profile.php` | Tra cứu chi tiết lịch sử nạp tiền, biến động số dư và các phiên chơi trước đó. |
| **UC_07** | Nạp tiền trực tuyến | Khách | `api/client_topup.php` | Sinh mã VietQR động, tự động áp dụng chương trình khuyến mãi, đếm ngược 15 phút, nút giả lập chuyển khoản thành công. |
| **UC_08** | Gọi đồ ăn / nước uống | Khách | `api/client_order.php` | Thực đơn F&B phân loại, khay món ăn, hỗ trợ thanh toán trừ số dư tài khoản hoặc tiền mặt, theo dõi tiến độ bếp. |
| **UC_09** | Quản lý tài khoản khách hàng | Quản lý | `api/admin_customers.php` | Danh sách phân trang, sửa thông tin, đổi nhóm VIP/Hội viên, đặt lại mật khẩu, khóa/mở khóa tài khoản. |
| **UC_10** | Tìm kiếm & tra cứu khách | Quản lý | `api/admin_customers.php` | Tìm kiếm tức thì (Search-as-you-type) theo Tên tài khoản, SĐT, Họ tên, xem máy đang ngồi. |
| **UC_11** | Nạp tiền tại quầy | Thu ngân | `api/admin_topup.php` | Thu ngân nạp tiền mặt/QR, tự động áp dụng khuyến mãi, cập nhật số dư ngay, **in hóa đơn nhiệt POS**. |
| **UC_12** | Quản lý phiên & Sơ đồ máy | Thu ngân | `api/admin_stations.php` | Sơ đồ 20 máy (Standard, VIP, Pro), mở máy tại quầy, **Chuyển máy bảo lưu phiên**, cưỡng chế ngắt phiên, bảo trì máy. |
| **UC_13** | Xử lý đơn dịch vụ | Thu ngân | `api/admin_orders.php` | Hàng đợi đơn bếp: Tiếp nhận -> Đang làm -> Hoàn thành giao đồ; **Hủy đơn tự động hoàn tiền vào số dư**. |
| **UC_14** | Quản lý khuyến mãi | Quản lý | `api/admin_promotions.php` | Xem, thêm, sửa, bật/tắt khuyến mãi; ghi vết vào `LICH_SU_KHUYEN_MAI`. |
| **UC_15** | Xem thống kê & báo cáo | Quản lý | `api/admin_reports.php` | Tổng hợp KPI, doanh thu nạp, giờ chơi, dịch vụ; thực thi trực tiếp **Query 6, 7, 8 trong Tuần 5**. |

---

## 3. CƠ SỞ DỮ LIỆU (16 BẢNG CHUẨN 3NF)
Hệ thống cài đặt đầy đủ 16 bảng CSDL trong file `sql/database.sql`:
1. `LOAI_MAY`: Phân loại máy (Standard, VIP Gaming, Pro Stage).
2. `MAY_TRAM`: Danh mục 20 máy trạm, địa chỉ IP LAN, trạng thái hoạt động.
3. `BANG_GIA`: Đơn giá giờ chơi theo loại máy và khung giờ áp dụng.
4. `KHACH_CHOI`: Thông tin định danh khách chơi (Họ tên, ngày sinh, SĐT, email).
5. `TAI_KHOAN`: Tài khoản hội viên, mật khẩu mã hóa, số dư, nhóm khách hàng.
6. `NHAN_VIEN`: Tài khoản quản trị viên và thu ngân ca trực.
7. `CHUONG_TRINH_KHUYEN_MAI`: Chính sách thưởng nạp theo mốc tiền.
8. `LICH_SU_KHUYEN_MAI`: Nhật ký biến động cấu hình khuyến mãi.
9. `GIAO_DICH_NAP_TIEN`: Lịch sử nạp tiền tại quầy và trực tuyến VietQR.
10. `PHIEN_CHOI`: Thông tin phiên chơi từ lúc mở đến lúc đóng.
11. `CHI_TIET_SU_DUNG_MAY`: Chi tiết từng chặng ngồi máy trong phiên (phục vụ chuyển máy).
12. `MON_DICH_VU`: Menu đồ ăn, thức uống, thẻ game, tồn kho, đơn giá.
13. `DON_DICH_VU`: Đơn đặt hàng dịch vụ tại máy trạm.
14. `CHI_TIET_DON_DICH_VU`: Chi tiết số lượng và đơn giá từng món trong đơn.
15. `NHAT_KY_HE_THONG`: Audit trail ghi lại toàn bộ thao tác nhạy cảm của nhân viên.
16. `BAO_CAO_THONG_KE`: Bảng tổng hợp số liệu báo cáo doanh thu định kỳ.

---

## 4. HƯỚNG DẪN CÀI ĐẶT & CHẠY BẰNG XAMPP

### Bước 1: Khởi động XAMPP
- Mở **XAMPP Control Panel**.
- Bấm **Start** tại 2 dịch vụ: **Apache** và **MySQL**.

### Bước 2: Truy cập ứng dụng
- Thư mục dự án đã được tạo liên kết trực tiếp vào `C:\xampp\htdocs\cyber_game` và đồng bộ cùng `D:\TTCSN\cyber_game`.
- Mở trình duyệt (Chrome/Edge/Cốc Cốc) và truy cập đường dẫn:
  👉 **http://localhost/cyber_game/**

### Bước 3: Khởi tạo dữ liệu (Nếu cần Reset dữ liệu gốc)
- Truy cập: **http://localhost/cyber_game/install.php**
- Bấm nút **"Khởi tạo Cơ Sở Dữ Liệu Ngay"** để tạo CSDL `quan_net_db` và nạp toàn bộ 16 bảng dữ liệu mẫu chỉ với 1 cú click.

---

## 5. TÀI KHOẢN MẪU ĐỂ BẢO VỆ ĐỒ ÁN / DEMO
Toàn bộ tài khoản có mật khẩu mặc định là: **`123456`**

### A. Tài khoản Khách chơi (Gamer Portal):
| Tên đăng nhập | Mật khẩu | Số dư mẫu | Máy ngồi demo | Đặc điểm |
|---|---|---|---|---|
| `gamer_an` | `123456` | 85.000 VNĐ | PC01 (Standard) | Hội viên VIP, có phiên đang chơi |
| `hoang_pro` | `123456` | 145.000 VNĐ | PC09 (VIP Gaming) | Khách nạp nhiều, có phiên đang chơi |
| `long_sniper` | `123456` | 52.000 VNĐ | PC02 (Standard) | Hội viên thường |
| `huy_faker` | `123456` | 210.000 VNĐ | PC17 (Pro Stage) | Khách Kim Cương |
| `nam_dota` | `123456` | 0 VNĐ | - | Tài khoản mẫu đang bị khóa (kiểm tra UC9) |

### B. Tài khoản Thu ngân & Quản trị (Admin Portal):
| Tên đăng nhập | Mật khẩu | Họ và tên | Vai trò |
|---|---|---|---|
| `admin` | `123456` | Lưu Gia Bảo | Quản trị viên hệ thống |
| `thungan1` | `123456` | Bùi Hữu Hoạt | Thu ngân ca sáng |
| `thungan2` | `123456` | Vũ Trung Nghĩa | Thu ngân ca tối |
