-- ====================================================================
-- CSDL: QUẢN LÝ KHÁCH CHƠI GAME TẠI CỬA HÀNG INTERNET & GAME TRỰC TUYẾN
-- Môn: Thực tập cơ sở ngành (K19 - Nhóm 05)
-- Chuẩn thiết kế 16 bảng CSDL & 15 Use Cases
-- Tương thích: XAMPP MariaDB / MySQL 5.7+ / MySQL 8.0+
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `quan_net_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `quan_net_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Bảng LOAI_MAY (Loại máy)
DROP TABLE IF EXISTS `CHI_TIET_DON_DICH_VU`;
DROP TABLE IF EXISTS `DON_DICH_VU`;
DROP TABLE IF EXISTS `MON_DICH_VU`;
DROP TABLE IF EXISTS `CHI_TIET_SU_DUNG_MAY`;
DROP TABLE IF EXISTS `PHIEN_CHOI`;
DROP TABLE IF EXISTS `GIAO_DICH_NAP_TIEN`;
DROP TABLE IF EXISTS `LICH_SU_KHUYEN_MAI`;
DROP TABLE IF EXISTS `CHUONG_TRINH_KHUYEN_MAI`;
DROP TABLE IF EXISTS `BANG_GIA`;
DROP TABLE IF EXISTS `MAY_TRAM`;
DROP TABLE IF EXISTS `LOAI_MAY`;
DROP TABLE IF EXISTS `BAO_CAO_THONG_KE`;
DROP TABLE IF EXISTS `NHAT_KY_HE_THONG`;
DROP TABLE IF EXISTS `NHAN_VIEN`;
DROP TABLE IF EXISTS `TAI_KHOAN`;
DROP TABLE IF EXISTS `KHACH_CHOI`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Bảng LOAI_MAY
CREATE TABLE `LOAI_MAY` (
    `maLoaiMay` VARCHAR(20) NOT NULL,
    `tenLoai` VARCHAR(100) NOT NULL,
    `moTa` VARCHAR(255) NULL,
    PRIMARY KEY (`maLoaiMay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng MAY_TRAM
CREATE TABLE `MAY_TRAM` (
    `maMay` VARCHAR(20) NOT NULL,
    `maLoaiMay` VARCHAR(20) NOT NULL,
    `tenMay` VARCHAR(100) NOT NULL,
    `diaChiIP` VARCHAR(45) NOT NULL UNIQUE,
    `viTri` VARCHAR(100) NOT NULL,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Trống', -- Trống, Đang chơi, Bảo trì
    PRIMARY KEY (`maMay`),
    FOREIGN KEY (`maLoaiMay`) REFERENCES `LOAI_MAY`(`maLoaiMay`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng BANG_GIA
CREATE TABLE `BANG_GIA` (
    `maBangGia` VARCHAR(20) NOT NULL,
    `maLoaiMay` VARCHAR(20) NOT NULL,
    `donGiaGio` DECIMAL(18, 2) NOT NULL,
    `khungGioApDung` VARCHAR(100) NOT NULL,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Áp dụng',
    PRIMARY KEY (`maBangGia`),
    FOREIGN KEY (`maLoaiMay`) REFERENCES `LOAI_MAY`(`maLoaiMay`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng KHACH_CHOI
CREATE TABLE `KHACH_CHOI` (
    `maKhachChoi` VARCHAR(20) NOT NULL,
    `hoTen` VARCHAR(255) NOT NULL,
    `ngaySinh` DATE NOT NULL,
    `soDienThoai` VARCHAR(15) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `gioiTinh` VARCHAR(10) NULL,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Hoạt động',
    PRIMARY KEY (`maKhachChoi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng TAI_KHOAN
CREATE TABLE `TAI_KHOAN` (
    `maTaiKhoan` VARCHAR(20) NOT NULL,
    `tenDangNhap` VARCHAR(100) NOT NULL UNIQUE,
    `matKhauHash` VARCHAR(255) NOT NULL,
    `soDu` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    `nhomKhachHang` VARCHAR(50) NOT NULL DEFAULT 'Hội viên',
    `ngayTao` DATE NOT NULL,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Hoạt động', -- Hoạt động, Đang khóa
    `maKhachChoi` VARCHAR(20) NOT NULL UNIQUE,
    PRIMARY KEY (`maTaiKhoan`),
    FOREIGN KEY (`maKhachChoi`) REFERENCES `KHACH_CHOI`(`maKhachChoi`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng NHAN_VIEN
CREATE TABLE `NHAN_VIEN` (
    `maNhanVien` VARCHAR(20) NOT NULL,
    `tenDangNhap` VARCHAR(100) NOT NULL UNIQUE,
    `matKhauHash` VARCHAR(255) NOT NULL,
    `hoTen` VARCHAR(100) NOT NULL,
    `soDienThoai` VARCHAR(15) NOT NULL,
    `vaiTro` VARCHAR(50) NOT NULL DEFAULT 'Thu ngân', -- Quản trị viên, Thu ngân
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Hoạt động',
    PRIMARY KEY (`maNhanVien`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bảng CHUONG_TRINH_KHUYEN_MAI
CREATE TABLE `CHUONG_TRINH_KHUYEN_MAI` (
    `maKhuyenMai` VARCHAR(20) NOT NULL,
    `tenChuongTrinh` VARCHAR(200) NOT NULL,
    `mucNapToiThieu` DECIMAL(18, 2) NOT NULL,
    `soTienKhuyenMai` DECIMAL(18, 2) NOT NULL,
    `ngayBatDau` DATE NOT NULL,
    `ngayKetThuc` DATE NOT NULL,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Hoạt động', -- Hoạt động, Tạm dừng, Hết hạn
    PRIMARY KEY (`maKhuyenMai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Bảng LICH_SU_KHUYEN_MAI
CREATE TABLE `LICH_SU_KHUYEN_MAI` (
    `maLichSu` VARCHAR(20) NOT NULL,
    `maKhuyenMai` VARCHAR(20) NOT NULL,
    `maNhanVien` VARCHAR(20) NOT NULL,
    `thaoTac` VARCHAR(100) NOT NULL,
    `noiDungCu` TEXT NULL,
    `noiDungMoi` TEXT NULL,
    `thoiGian` DATETIME NOT NULL,
    PRIMARY KEY (`maLichSu`),
    FOREIGN KEY (`maKhuyenMai`) REFERENCES `CHUONG_TRINH_KHUYEN_MAI`(`maKhuyenMai`) ON UPDATE CASCADE,
    FOREIGN KEY (`maNhanVien`) REFERENCES `NHAN_VIEN`(`maNhanVien`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Bảng GIAO_DICH_NAP_TIEN
CREATE TABLE `GIAO_DICH_NAP_TIEN` (
    `maGiaoDich` VARCHAR(20) NOT NULL,
    `maTaiKhoan` VARCHAR(20) NOT NULL,
    `maNhanVien` VARCHAR(20) NULL,
    `maKhuyenMai` VARCHAR(20) NULL,
    `soTienNap` DECIMAL(18, 2) NOT NULL,
    `tienKhuyenMai` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    `phuongThuc` VARCHAR(50) NOT NULL, -- Tiền mặt, Chuyển khoản QR, Online VietQR
    `maThamChieu` VARCHAR(100) NULL,
    `thoiGianTao` DATETIME NOT NULL,
    `thoiGianHoanTat` DATETIME NULL,
    `thoiGianHetHan` DATETIME NULL,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Thành công', -- Thành công, Đang chờ, Đã hủy
    PRIMARY KEY (`maGiaoDich`),
    FOREIGN KEY (`maTaiKhoan`) REFERENCES `TAI_KHOAN`(`maTaiKhoan`) ON UPDATE CASCADE,
    FOREIGN KEY (`maNhanVien`) REFERENCES `NHAN_VIEN`(`maNhanVien`) ON UPDATE CASCADE,
    FOREIGN KEY (`maKhuyenMai`) REFERENCES `CHUONG_TRINH_KHUYEN_MAI`(`maKhuyenMai`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Bảng PHIEN_CHOI
CREATE TABLE `PHIEN_CHOI` (
    `maPhien` VARCHAR(20) NOT NULL,
    `thoiGianBatDau` DATETIME NOT NULL,
    `thoiGianKetThuc` DATETIME NULL,
    `tongThoiGianPhut` INT NOT NULL DEFAULT 0,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Đang hoạt động', -- Đang hoạt động, Đã kết thúc
    `chiPhi` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    `maTaiKhoan` VARCHAR(20) NOT NULL,
    PRIMARY KEY (`maPhien`),
    FOREIGN KEY (`maTaiKhoan`) REFERENCES `TAI_KHOAN`(`maTaiKhoan`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Bảng CHI_TIET_SU_DUNG_MAY
CREATE TABLE `CHI_TIET_SU_DUNG_MAY` (
    `maSuDung` VARCHAR(20) NOT NULL,
    `maPhien` VARCHAR(20) NOT NULL,
    `maMay` VARCHAR(20) NOT NULL,
    `maBangGia` VARCHAR(20) NOT NULL,
    `thoiGianBatDau` DATETIME NOT NULL,
    `thoiGianKetThuc` DATETIME NULL,
    `donGiaApDung` DECIMAL(18, 2) NOT NULL,
    `thanhTien` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`maSuDung`),
    FOREIGN KEY (`maPhien`) REFERENCES `PHIEN_CHOI`(`maPhien`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`maMay`) REFERENCES `MAY_TRAM`(`maMay`) ON UPDATE CASCADE,
    FOREIGN KEY (`maBangGia`) REFERENCES `BANG_GIA`(`maBangGia`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Bảng MON_DICH_VU
CREATE TABLE `MON_DICH_VU` (
    `maMon` VARCHAR(20) NOT NULL,
    `tenMon` VARCHAR(100) NOT NULL,
    `loaiMon` VARCHAR(50) NOT NULL, -- Đồ ăn, Nước uống, Ăn vặt, Combo
    `donGia` DECIMAL(18, 2) NOT NULL,
    `donViTinh` VARCHAR(30) NOT NULL,
    `soLuongTon` INT NOT NULL DEFAULT 100,
    `hinhAnh` VARCHAR(500) NULL,
    `trangThai` VARCHAR(20) NOT NULL DEFAULT 'Còn hàng',
    PRIMARY KEY (`maMon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Bảng DON_DICH_VU
CREATE TABLE `DON_DICH_VU` (
    `maDon` VARCHAR(20) NOT NULL,
    `maTaiKhoan` VARCHAR(20) NOT NULL,
    `maPhien` VARCHAR(20) NOT NULL,
    `maMay` VARCHAR(20) NOT NULL,
    `maNhanVien` VARCHAR(20) NULL,
    `thoiGianDat` DATETIME NOT NULL,
    `tongTien` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    `hinhThucThanhToan` VARCHAR(50) NOT NULL DEFAULT 'Trừ số dư', -- Trừ số dư, Tiền mặt
    `trangThaiThanhToan` VARCHAR(20) NOT NULL DEFAULT 'Đã thanh toán', -- Đã thanh toán, Chưa thanh toán
    `trangThaiDon` VARCHAR(20) NOT NULL DEFAULT 'Chờ xử lý', -- Chờ xử lý, Đang chuẩn bị, Đã hoàn thành, Đã hủy
    `lyDoHuy` VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`maDon`),
    FOREIGN KEY (`maTaiKhoan`) REFERENCES `TAI_KHOAN`(`maTaiKhoan`) ON UPDATE CASCADE,
    FOREIGN KEY (`maPhien`) REFERENCES `PHIEN_CHOI`(`maPhien`) ON UPDATE CASCADE,
    FOREIGN KEY (`maMay`) REFERENCES `MAY_TRAM`(`maMay`) ON UPDATE CASCADE,
    FOREIGN KEY (`maNhanVien`) REFERENCES `NHAN_VIEN`(`maNhanVien`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Bảng CHI_TIET_DON_DICH_VU
CREATE TABLE `CHI_TIET_DON_DICH_VU` (
    `maDon` VARCHAR(20) NOT NULL,
    `maMon` VARCHAR(20) NOT NULL,
    `soLuong` INT NOT NULL DEFAULT 1,
    `donGia` DECIMAL(18, 2) NOT NULL,
    `ghiChu` VARCHAR(500) NULL,
    `thanhTien` DECIMAL(18, 2) NOT NULL,
    PRIMARY KEY (`maDon`, `maMon`),
    FOREIGN KEY (`maDon`) REFERENCES `DON_DICH_VU`(`maDon`) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`maMon`) REFERENCES `MON_DICH_VU`(`maMon`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Bảng NHAT_KY_HE_THONG
CREATE TABLE `NHAT_KY_HE_THONG` (
    `maNhatKy` VARCHAR(20) NOT NULL,
    `maNhanVien` VARCHAR(20) NOT NULL,
    `hanhDong` VARCHAR(100) NOT NULL,
    `doiTuong` VARCHAR(100) NOT NULL,
    `maDoiTuong` VARCHAR(20) NULL,
    `noiDungCu` TEXT NULL,
    `noiDungMoi` TEXT NULL,
    `thoiGian` DATETIME NOT NULL,
    `ketQua` VARCHAR(50) NOT NULL DEFAULT 'Thành công',
    PRIMARY KEY (`maNhatKy`),
    FOREIGN KEY (`maNhanVien`) REFERENCES `NHAN_VIEN`(`maNhanVien`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Bảng BAO_CAO_THONG_KE
CREATE TABLE `BAO_CAO_THONG_KE` (
    `maBaoCao` VARCHAR(20) NOT NULL,
    `maNhanVien` VARCHAR(20) NOT NULL,
    `loaiThongKe` VARCHAR(100) NOT NULL,
    `tuNgay` DATE NOT NULL,
    `denNgay` DATE NOT NULL,
    `tongTienNap` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    `tongSoPhien` INT NOT NULL DEFAULT 0,
    `tongThoiGianPhut` INT NOT NULL DEFAULT 0,
    `tongTienDichVu` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
    `ngayTao` DATE NOT NULL,
    PRIMARY KEY (`maBaoCao`),
    FOREIGN KEY (`maNhanVien`) REFERENCES `NHAN_VIEN`(`maNhanVien`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- DỮ LIỆU MẪU (SEED DATA)
-- Mật khẩu mặc định cho toàn bộ tài khoản demo là: 123456
-- Hash bcrypt: $2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m
-- ====================================================================

-- 1. LOAI_MAY
INSERT INTO `LOAI_MAY` (`maLoaiMay`, `tenLoai`, `moTa`) VALUES
('LM01', 'Khu Tiêu Chuẩn', 'Core i5 12400F, RAM 16GB, RTX 3060, Màn hình 165Hz 24 inch'),
('LM02', 'Khu VIP Gaming', 'Core i7 13700F, RAM 32GB, RTX 4070, Màn hình 240Hz 27 inch cong'),
('LM03', 'Khu Thi Đấu Pro', 'Core i9 14900K, RAM 64GB, RTX 4080 Super, Màn hình 360Hz ZOWIE');

-- 2. BANG_GIA
INSERT INTO `BANG_GIA` (`maBangGia`, `maLoaiMay`, `donGiaGio`, `khungGioApDung`, `trangThai`) VALUES
('BG01', 'LM01', 8000.00, 'Toàn thời gian (00:00 - 24:00)', 'Áp dụng'),
('BG02', 'LM02', 12000.00, 'Toàn thời gian (00:00 - 24:00)', 'Áp dụng'),
('BG03', 'LM03', 18000.00, 'Toàn thời gian (00:00 - 24:00)', 'Áp dụng');

-- 3. MAY_TRAM (20 Máy trạm chia thành 3 khu)
INSERT INTO `MAY_TRAM` (`maMay`, `maLoaiMay`, `tenMay`, `diaChiIP`, `viTri`, `trangThai`) VALUES
('PC01', 'LM01', 'Máy 01 (Tiêu Chuẩn)', '192.168.1.101', 'Dãy A - Bàn 01', 'Trống'),
('PC02', 'LM01', 'Máy 02 (Tiêu Chuẩn)', '192.168.1.102', 'Dãy A - Bàn 02', 'Trống'),
('PC03', 'LM01', 'Máy 03 (Tiêu Chuẩn)', '192.168.1.103', 'Dãy A - Bàn 03', 'Trống'),
('PC04', 'LM01', 'Máy 04 (Tiêu Chuẩn)', '192.168.1.104', 'Dãy A - Bàn 04', 'Trống'),
('PC05', 'LM01', 'Máy 05 (Tiêu Chuẩn)', '192.168.1.105', 'Dãy B - Bàn 05', 'Trống'),
('PC06', 'LM01', 'Máy 06 (Tiêu Chuẩn)', '192.168.1.106', 'Dãy B - Bàn 06', 'Trống'),
('PC07', 'LM01', 'Máy 07 (Tiêu Chuẩn)', '192.168.1.107', 'Dãy B - Bàn 07', 'Trống'),
('PC08', 'LM01', 'Máy 08 (Tiêu Chuẩn)', '192.168.1.108', 'Dãy B - Bàn 08', 'Bảo trì'),
('PC09', 'LM02', 'Máy 09 (VIP Gaming)', '192.168.1.109', 'Khu VIP 1 - Ghế Sofa', 'Trống'),
('PC10', 'LM02', 'Máy 10 (VIP Gaming)', '192.168.1.110', 'Khu VIP 2 - Ghế Sofa', 'Trống'),
('PC11', 'LM02', 'Máy 11 (VIP Gaming)', '192.168.1.111', 'Khu VIP 3 - Ghế Gaming', 'Trống'),
('PC12', 'LM02', 'Máy 12 (VIP Gaming)', '192.168.1.112', 'Khu VIP 4 - Ghế Gaming', 'Trống'),
('PC13', 'LM02', 'Máy 13 (VIP Gaming)', '192.168.1.113', 'Khu VIP 5 - Ghế Gaming', 'Trống'),
('PC14', 'LM02', 'Máy 14 (VIP Gaming)', '192.168.1.114', 'Khu VIP 6 - Ghế Gaming', 'Trống'),
('PC15', 'LM02', 'Máy 15 (VIP Gaming)', '192.168.1.115', 'Khu VIP 7 - Ghế Gaming', 'Trống'),
('PC16', 'LM02', 'Máy 16 (VIP Gaming)', '192.168.1.116', 'Khu VIP 8 - Ghế Gaming', 'Trống'),
('PC17', 'LM03', 'Máy 17 (Thi Đấu Pro)', '192.168.1.117', 'Phòng Đấu Đội - Máy 1', 'Trống'),
('PC18', 'LM03', 'Máy 18 (Thi Đấu Pro)', '192.168.1.118', 'Phòng Đấu Đội - Máy 2', 'Trống'),
('PC19', 'LM03', 'Máy 19 (Thi Đấu Pro)', '192.168.1.119', 'Phòng Đấu Đội - Máy 3', 'Trống'),
('PC20', 'LM03', 'Máy 20 (Thi Đấu Pro)', '192.168.1.120', 'Phòng Đấu Đội - Máy 4', 'Trống');

-- 4. NHAN_VIEN (Admin & Cashier)
INSERT INTO `NHAN_VIEN` (`maNhanVien`, `tenDangNhap`, `matKhauHash`, `hoTen`, `soDienThoai`, `vaiTro`, `trangThai`) VALUES
('NV01', 'admin', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 'Lưu Gia Bảo (Quản lý)', '0981234567', 'Quản trị viên', 'Hoạt động'),
('NV02', 'thungan1', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 'Bùi Hữu Hoạt (Ca sáng)', '0972345678', 'Thu ngân', 'Hoạt động'),
('NV03', 'thungan2', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 'Vũ Trung Nghĩa (Ca tối)', '0963456789', 'Thu ngân', 'Hoạt động');

-- 5. CHUONG_TRINH_KHUYEN_MAI
INSERT INTO `CHUONG_TRINH_KHUYEN_MAI` (`maKhuyenMai`, `tenChuongTrinh`, `mucNapToiThieu`, `soTienKhuyenMai`, `ngayBatDau`, `ngayKetThuc`, `trangThai`) VALUES
('KM00', 'Khởi Động Nhanh 10k', 10000.00, 2000.00, '2026-01-01', '2026-12-31', 'Hoạt động'),
('KM01', 'Tân Thủ Chào Sân', 20000.00, 5000.00, '2026-01-01', '2026-12-31', 'Hoạt động'),
('KM02', 'Giờ Vàng Nạp 50k Tặng 15k', 50000.00, 15000.00, '2026-09-01', '2026-11-30', 'Hoạt động'),
('KM03', 'Thần Đèn Nạp 100k Tặng 35k', 100000.00, 35000.00, '2026-10-01', '2026-12-31', 'Hoạt động'),
('KM04', 'Đại Gia Cyber Nạp 200k Tặng 80k', 200000.00, 80000.00, '2026-10-01', '2026-12-31', 'Hoạt động');

-- 6. MON_DICH_VU (Menu đồ ăn, đồ uống, combo)
INSERT INTO `MON_DICH_VU` (`maMon`, `tenMon`, `loaiMon`, `donGia`, `donViTinh`, `soLuongTon`, `hinhAnh`, `trangThai`) VALUES
('M01', 'Mì Tôm Trứng Xúc Xích', 'Đồ ăn', 25000.00, 'Tô', 80, 'assets/img/mi_tom.png', 'Còn hàng'),
('M02', 'Mì Xào Bò Rau Cải', 'Đồ ăn', 35000.00, 'Dĩa', 65, 'assets/img/mi_xao_bo.png', 'Còn hàng'),
('M03', 'Cơm Chiên Dương Châu', 'Đồ ăn', 35000.00, 'Dĩa', 50, 'assets/img/com_chien.png', 'Còn hàng'),
('M04', 'Cơm Sườn Nướng Mật Ong', 'Đồ ăn', 45000.00, 'Dĩa', 40, 'assets/img/com_suon.png', 'Còn hàng'),
('M05', 'Bò Húc Thái Ướp Lạnh', 'Nước uống', 18000.00, 'Lon', 120, 'assets/img/bo_huc.png', 'Còn hàng'),
('M06', 'Sting Dâu Đá Dĩa', 'Nước uống', 15000.00, 'Ly', 150, 'assets/img/sting.png', 'Còn hàng'),
('M07', 'Trà Đào Cam Sả Tươi', 'Nước uống', 25000.00, 'Ly', 90, 'assets/img/tra_dao.png', 'Còn hàng'),
('M08', 'Cà Phê Sữa Đá Sài Gòn', 'Nước uống', 20000.00, 'Ly', 100, 'assets/img/ca_phe.png', 'Còn hàng'),
('M09', 'Snack Khoai Tây Lay Tảo Biển', 'Ăn vặt', 12000.00, 'Gói', 110, 'assets/img/snack.png', 'Còn hàng'),
('M10', 'Combo Đêm Đầy Năng Lượng (Mì Bò + Sting)', 'Combo', 45000.00, 'Combo', 60, 'assets/img/combo.png', 'Còn hàng');

-- 7. KHACH_CHOI & TAI_KHOAN (15 Khách chơi)
INSERT INTO `KHACH_CHOI` (`maKhachChoi`, `hoTen`, `ngaySinh`, `soDienThoai`, `email`, `gioiTinh`, `trangThai`) VALUES
('KC01', 'Nguyễn Văn An', '2004-05-15', '0987654321', 'an.nguyen@gmail.com', 'Nam', 'Hoạt động'),
('KC02', 'Trần Quang Hoàng', '2003-08-22', '0978123456', 'hoang.tran@gmail.com', 'Nam', 'Hoạt động'),
('KC03', 'Phạm Bảo Long', '2005-02-10', '0969888999', 'long.gaming@gmail.com', 'Nam', 'Hoạt động'),
('KC04', 'Lê Thị Thu Thảo', '2004-11-05', '0912345678', 'thao.le@gmail.com', 'Nữ', 'Hoạt động'),
('KC05', 'Đặng Quốc Huy', '2002-09-19', '0905123987', 'huy.dang@gmail.com', 'Nam', 'Hoạt động'),
('KC06', 'Vũ Minh Đức', '2005-07-30', '0934567890', 'duc.vu@gmail.com', 'Nam', 'Hoạt động'),
('KC07', 'Hoàng Tuấn Kiệt', '2004-03-12', '0945678901', 'kiet.hoang@gmail.com', 'Nam', 'Hoạt động'),
('KC08', 'Ngô Hải Nam', '2003-12-25', '0956789012', 'nam.ngo@gmail.com', 'Nam', 'Hoạt động'),
('KC09', 'Bùi Phương Linh', '2005-06-18', '0923456789', 'linh.bui@gmail.com', 'Nữ', 'Hoạt động'),
('KC10', 'Đỗ Thành Vinh', '2004-01-08', '0918765432', 'vinh.do@gmail.com', 'Nam', 'Hoạt động');

INSERT INTO `TAI_KHOAN` (`maTaiKhoan`, `tenDangNhap`, `matKhauHash`, `soDu`, `nhomKhachHang`, `ngayTao`, `trangThai`, `maKhachChoi`) VALUES
('TK01', 'gamer_an', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 85000.00, 'VIP', '2026-09-01', 'Hoạt động', 'KC01'),
('TK02', 'hoang_pro', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 145000.00, 'VIP', '2026-09-05', 'Hoạt động', 'KC02'),
('TK03', 'long_sniper', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 52000.00, 'Hội viên', '2026-09-10', 'Hoạt động', 'KC03'),
('TK04', 'thao_cute', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 30000.00, 'Hội viên', '2026-09-12', 'Hoạt động', 'KC04'),
('TK05', 'huy_faker', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 210000.00, 'Kim Cương', '2026-09-15', 'Hoạt động', 'KC05'),
('TK06', 'duc_valorant', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 18000.00, 'Hội viên', '2026-09-20', 'Hoạt động', 'KC06'),
('TK07', 'kiet_lol', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 95000.00, 'Hội viên', '2026-09-22', 'Hoạt động', 'KC07'),
('TK08', 'nam_dota', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 0.00, 'Hội viên', '2026-09-25', 'Đang khóa', 'KC08'),
('TK09', 'linh_pubg', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 65000.00, 'Hội viên', '2026-09-28', 'Hoạt động', 'KC09'),
('TK10', 'vinh_cs2', '$2y$10$YfIGcI/28JDHELeP7GJyTeN0Q4vW2q5NoiSq24McOFlP6zFEpRw5m', 120000.00, 'VIP', '2026-10-01', 'Hoạt động', 'KC10');

-- 8. GIAO_DICH_NAP_TIEN (Lịch sử nạp tiền mẫu)
INSERT INTO `GIAO_DICH_NAP_TIEN` (`maGiaoDich`, `maTaiKhoan`, `maNhanVien`, `maKhuyenMai`, `soTienNap`, `tienKhuyenMai`, `phuongThuc`, `maThamChieu`, `thoiGianTao`, `thoiGianHoanTat`, `thoiGianHetHan`, `trangThai`) VALUES
('GD01', 'TK01', 'NV02', 'KM02', 50000.00, 15000.00, 'Tiền mặt', 'REC-001', '2026-10-10 09:30:00', '2026-10-10 09:31:00', NULL, 'Thành công'),
('GD02', 'TK02', 'NV02', 'KM03', 100000.00, 35000.00, 'Chuyển khoản QR', 'VCB-987123', '2026-10-11 14:15:00', '2026-10-11 14:16:00', NULL, 'Thành công'),
('GD03', 'TK05', 'NV01', 'KM04', 200000.00, 80000.00, 'Tiền mặt', 'REC-002', '2026-10-12 18:00:00', '2026-10-12 18:01:00', NULL, 'Thành công'),
('GD04', 'TK03', NULL, 'KM01', 20000.00, 5000.00, 'Online VietQR', 'VQR-654321', '2026-10-13 10:20:00', '2026-10-13 10:21:00', NULL, 'Thành công'),
('GD05', 'TK07', 'NV03', 'KM02', 50000.00, 15000.00, 'Tiền mặt', 'REC-003', '2026-10-14 20:00:00', '2026-10-14 20:01:00', NULL, 'Thành công');

-- 9. PHIEN_CHOI & CHI_TIET_SU_DUNG_MAY (Lịch sử phiên cũ + 1 phiên đang hoạt động)
-- Phiên cũ đã kết thúc:
INSERT INTO `PHIEN_CHOI` (`maPhien`, `thoiGianBatDau`, `thoiGianKetThuc`, `tongThoiGianPhut`, `trangThai`, `chiPhi`, `maTaiKhoan`) VALUES
('PC001', '2026-10-14 08:00:00', '2026-10-14 11:30:00', 210, 'Đã kết thúc', 28000.00, 'TK01'),
('PC002', '2026-10-14 14:00:00', '2026-10-14 18:00:00', 240, 'Đã kết thúc', 48000.00, 'TK02'),
('PC003', '2026-10-14 19:30:00', '2026-10-14 22:00:00', 150, 'Đã kết thúc', 45000.00, 'TK05');

INSERT INTO `CHI_TIET_SU_DUNG_MAY` (`maSuDung`, `maPhien`, `maMay`, `maBangGia`, `thoiGianBatDau`, `thoiGianKetThuc`, `donGiaApDung`, `thanhTien`) VALUES
('SD001', 'PC001', 'PC01', 'BG01', '2026-10-14 08:00:00', '2026-10-14 11:30:00', 8000.00, 28000.00),
('SD002', 'PC002', 'PC09', 'BG02', '2026-10-14 14:00:00', '2026-10-14 18:00:00', 12000.00, 48000.00),
('SD003', 'PC003', 'PC17', 'BG03', '2026-10-14 19:30:00', '2026-10-14 22:00:00', 18000.00, 45000.00);

-- 9. PHIEN_CHOI & CHI_TIET_SU_DUNG_MAY (Phiên mẫu PC005 trên PC09 đang hoạt động để demo)
INSERT INTO `PHIEN_CHOI` (`maPhien`, `thoiGianBatDau`, `thoiGianKetThuc`, `tongThoiGianPhut`, `trangThai`, `chiPhi`, `maTaiKhoan`) VALUES
('PC004', '2026-10-15 08:30:00', '2026-10-15 09:15:00', 45, 'Đã kết thúc', 6000.00, 'TK01'),
('PC005', NOW() - INTERVAL 45 MINUTE, NULL, 45, 'Đang hoạt động', 9000.00, 'TK02');

INSERT INTO `CHI_TIET_SU_DUNG_MAY` (`maSuDung`, `maPhien`, `maMay`, `maBangGia`, `thoiGianBatDau`, `thoiGianKetThuc`, `donGiaApDung`, `thanhTien`) VALUES
('SD004', 'PC004', 'PC01', 'BG01', '2026-10-15 08:30:00', '2026-10-15 09:15:00', 8000.00, 6000.00),
('SD005', 'PC005', 'PC09', 'BG02', NOW() - INTERVAL 45 MINUTE, NULL, 12000.00, 9000.00);

-- Đặt trạng thái Máy 09 là 'Đang chơi' để demo ngay khi cài đặt CSDL
UPDATE `MAY_TRAM` SET `trangThai` = 'Đang chơi' WHERE `maMay` = 'PC09';

-- 10. DON_DICH_VU & CHI_TIET_DON_DICH_VU (Đơn mẫu)
INSERT INTO `DON_DICH_VU` (`maDon`, `maTaiKhoan`, `maPhien`, `maMay`, `maNhanVien`, `thoiGianDat`, `tongTien`, `hinhThucThanhToan`, `trangThaiThanhToan`, `trangThaiDon`) VALUES
('DDV01', 'TK01', 'PC004', 'PC01', 'NV02', '2026-10-15 08:50:00', 40000.00, 'Trừ số dư', 'Đã thanh toán', 'Đã hoàn thành'),
('DDV02', 'TK02', 'PC005', 'PC09', 'NV02', '2026-10-15 14:00:00', 35000.00, 'Trừ số dư', 'Đã thanh toán', 'Đã hoàn thành');

INSERT INTO `CHI_TIET_DON_DICH_VU` (`maDon`, `maMon`, `soLuong`, `donGia`, `ghiChu`, `thanhTien`) VALUES
('DDV01', 'M01', 1, 25000.00, 'Trứng lòng đào nhiều ớt bột', 25000.00),
('DDV01', 'M06', 1, 15000.00, 'Nhiều đá', 15000.00),
('DDV02', 'M03', 1, 35000.00, 'Ít dầu mỡ', 35000.00);

-- 11. NHAT_KY_HE_THONG
INSERT INTO `NHAT_KY_HE_THONG` (`maNhatKy`, `maNhanVien`, `hanhDong`, `doiTuong`, `maDoiTuong`, `noiDungCu`, `noiDungMoi`, `thoiGian`, `ketQua`) VALUES
('NK01', 'NV02', 'Nạp tiền', 'TAI_KHOAN', 'TK01', 'Số dư: 20000', 'Số dư: 85000 (Nạp 50k + KM 15k)', NOW() - INTERVAL 2 HOUR, 'Thành công'),
('NK02', 'NV02', 'Mở phiên chơi', 'PHIEN_CHOI', 'PC004', 'Máy trống', 'Bắt đầu phiên tại PC01', NOW() - INTERVAL 45 MINUTE, 'Thành công'),
('NK03', 'NV01', 'Khóa tài khoản', 'TAI_KHOAN', 'TK08', 'Trạng thái: Hoạt động', 'Trạng thái: Đang khóa (Vi phạm quy định)', NOW() - INTERVAL 1 DAY, 'Thành công');
