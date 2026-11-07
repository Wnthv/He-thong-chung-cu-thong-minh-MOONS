-- =====================================================================
--  HỆ THỐNG QUẢN LÝ CƯ DÂN VÀ DỊCH VỤ TIỆN ÍCH CHUNG CƯ MOONS
--  Lược đồ CSDL – MySQL 8.0.16+ / MariaDB 10.4+ (InnoDB, utf8mb4)
--  15 bảng: 13 thực thể nghiệp vụ + bieu_phi + nhat_ky_thao_tac
--  Quy ước: [DB]  = quy tắc do CSDL tự bảo đảm (CHECK/UNIQUE/FK/TRIGGER)
--           [SVC] = quy tắc do lớp Service bảo đảm (ghi chú để đối chiếu)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS nhat_ky_thao_tac, ngu_canh_ai, phieu_su_co, dat_tien_ich,
  dich_vu_tien_ich, giao_dich_thanh_toan, chi_tiet_hoa_don, hoa_don,
  chi_so_dien_nuoc, bieu_phi, the_xe, the_ra_vao, nhan_khau, can_ho, tai_khoan;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. TÀI KHOẢN ---------------------------------------------------------
CREATE TABLE tai_khoan (
  ID_tai_khoan      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ten_dang_nhap     VARCHAR(50)  NOT NULL,
  mat_khau_bam      VARCHAR(255) NOT NULL,              -- bcrypt
  ho_ten            VARCHAR(100) NOT NULL,
  email             VARCHAR(100) NULL,
  so_dien_thoai     VARCHAR(15)  NULL,
  vai_tro ENUM('chu_ho','khach_thue','le_tan','ke_toan','ke_toan_truong',
               'dieu_phoi_vien','ky_thuat_vien','admin') NOT NULL,
  trang_thai        TINYINT NOT NULL DEFAULT 1,
  so_lan_sai        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  khoa_den          DATETIME NULL,                      -- khóa tạm 15 phút
  phai_doi_mat_khau TINYINT NOT NULL DEFAULT 0,
  ngay_tao          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_tk_ten   UNIQUE (ten_dang_nhap),
  CONSTRAINT uq_tk_email UNIQUE (email),
  CONSTRAINT uq_tk_sdt   UNIQUE (so_dien_thoai),
  CONSTRAINT ck_tk_trang_thai CHECK (trang_thai IN (0,1)),
  CONSTRAINT ck_tk_doi_mk     CHECK (phai_doi_mat_khau IN (0,1)),
  CONSTRAINT ck_tk_so_lan_sai CHECK (so_lan_sai <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CĂN HỘ -------------------------------------------------------------
CREATE TABLE can_ho (
  ID_can_ho    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_can_ho    VARCHAR(10) NOT NULL,
  thap_nha     CHAR(1)     NOT NULL,
  so_tang      TINYINT UNSIGNED NOT NULL,
  loai_can     ENUM('can_ho','shophouse') NOT NULL DEFAULT 'can_ho',
  dien_tich    DECIMAL(6,2) NOT NULL,
  trang_thai_o ENUM('trong','chu_ho_o','cho_thue') NOT NULL DEFAULT 'trong', -- [SVC] đồng bộ theo nhân khẩu
  CONSTRAINT uq_ch_ma UNIQUE (ma_can_ho),
  CONSTRAINT ck_ch_thap CHECK (thap_nha IN ('A','B','C','D')),
  CONSTRAINT ck_ch_tang CHECK (so_tang BETWEEN 1 AND 28),
  CONSTRAINT ck_ch_dt   CHECK (dien_tich > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. NHÂN KHẨU ----------------------------------------------------------
CREATE TABLE nhan_khau (
  ID_nhan_khau  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ho_ten        VARCHAR(100) NOT NULL,
  ngay_sinh     DATE NULL,
  gioi_tinh     ENUM('nam','nu') NULL,
  so_dien_thoai VARCHAR(15) NULL,
  email         VARCHAR(100) NULL,
  so_cccd       VARCHAR(12) NULL,
  quan_he_chu_ho VARCHAR(30) NOT NULL DEFAULT 'Chủ hộ',
  loai_cu_dan   ENUM('chu_ho','khach_thue','thanh_vien') NOT NULL,
  so_hop_dong   VARCHAR(30) NULL,
  ngay_bat_dau_hop_dong  DATE NULL,
  ngay_ket_thuc_hop_dong DATE NULL,
  anh_giay_to   VARCHAR(255) NULL,
  trang_thai_duyet ENUM('cho_duyet','da_duyet','tu_choi','da_roi_di') NOT NULL DEFAULT 'cho_duyet',
  ly_do_tu_choi VARCHAR(255) NULL,
  ngay_duyet    DATETIME NULL,
  ID_can_ho     INT UNSIGNED NOT NULL,
  ID_tai_khoan  INT UNSIGNED NULL,               -- quan hệ 1-1 (thành viên không có tài khoản)
  ID_nguoi_duyet INT UNSIGNED NULL,
  CONSTRAINT uq_nk_cccd UNIQUE (so_cccd),
  CONSTRAINT uq_nk_tk   UNIQUE (ID_tai_khoan),
  CONSTRAINT fk_nk_ch FOREIGN KEY (ID_can_ho) REFERENCES can_ho(ID_can_ho) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_nk_tk FOREIGN KEY (ID_tai_khoan) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_nk_nd FOREIGN KEY (ID_nguoi_duyet) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_nk_cccd CHECK (so_cccd IS NULL OR so_cccd REGEXP '^[0-9]{12}$'),
  CONSTRAINT ck_nk_hop_dong CHECK (loai_cu_dan <> 'khach_thue' OR
      (ngay_bat_dau_hop_dong IS NOT NULL AND ngay_ket_thuc_hop_dong IS NOT NULL
       AND ngay_ket_thuc_hop_dong >= ngay_bat_dau_hop_dong AND so_hop_dong IS NOT NULL)),
  CONSTRAINT ck_nk_tu_choi CHECK (trang_thai_duyet <> 'tu_choi' OR ly_do_tu_choi IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. THẺ RA VÀO (RFID / VÂN TAY) -----------------------------------------
CREATE TABLE the_ra_vao (
  ID_the        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_chip_rfid  VARCHAR(30) NULL,
  ma_id_van_tay VARCHAR(30) NULL,               -- chỉ lưu mã ID đã mã hóa, không lưu ảnh vân tay
  loai_the      ENUM('rfid_xe','thang_may','van_tay') NOT NULL,
  trang_thai_the ENUM('kich_hoat','tam_khoa','bao_mat','da_huy') NOT NULL DEFAULT 'kich_hoat',
  ngay_cap      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ID_nhan_khau  INT UNSIGNED NOT NULL,
  CONSTRAINT uq_trv_rfid UNIQUE (ma_chip_rfid),
  CONSTRAINT uq_trv_vt   UNIQUE (ma_id_van_tay),
  CONSTRAINT fk_trv_nk FOREIGN KEY (ID_nhan_khau) REFERENCES nhan_khau(ID_nhan_khau) ON DELETE RESTRICT,
  CONSTRAINT ck_trv_ma CHECK (ma_chip_rfid IS NOT NULL OR ma_id_van_tay IS NOT NULL),
  CONSTRAINT ck_trv_loai CHECK ((loai_the = 'van_tay' AND ma_id_van_tay IS NOT NULL)
                              OR (loai_the <> 'van_tay' AND ma_chip_rfid IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. THẺ XE --------------------------------------------------------------
CREATE TABLE the_xe (
  ID_the_xe     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bien_so_xe    VARCHAR(15) NOT NULL,
  loai_xe       ENUM('o_to','xe_may') NOT NULL,
  nhan_hieu     VARCHAR(50) NOT NULL,
  mau_xe        VARCHAR(30) NOT NULL,
  anh_ca_vet_xe VARCHAR(255) NOT NULL,
  trang_thai_duyet ENUM('cho_duyet','yeu_cau_bo_sung','da_duyet','tu_choi','da_huy') NOT NULL DEFAULT 'cho_duyet',
  ly_do         VARCHAR(255) NULL,
  ngay_gui      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ngay_duyet    DATETIME NULL,
  ID_can_ho     INT UNSIGNED NOT NULL,
  ID_nhan_khau  INT UNSIGNED NOT NULL,
  ID_nguoi_duyet INT UNSIGNED NULL,
  -- [DB] biển số chỉ duy nhất trong các hồ sơ còn hiệu lực (hồ sơ đã hủy/từ chối được phép trùng)
  bien_so_hieu_luc VARCHAR(15) GENERATED ALWAYS AS
     (CASE WHEN trang_thai_duyet IN ('cho_duyet','yeu_cau_bo_sung','da_duyet') THEN bien_so_xe END) STORED,
  CONSTRAINT uq_tx_bien_so UNIQUE (bien_so_hieu_luc),
  CONSTRAINT fk_tx_ch FOREIGN KEY (ID_can_ho) REFERENCES can_ho(ID_can_ho) ON DELETE RESTRICT,
  CONSTRAINT fk_tx_nk FOREIGN KEY (ID_nhan_khau) REFERENCES nhan_khau(ID_nhan_khau) ON DELETE RESTRICT,
  CONSTRAINT fk_tx_nd FOREIGN KEY (ID_nguoi_duyet) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_tx_bien_so CHECK (bien_so_xe REGEXP '^[0-9]{2}[A-Z]{1,2}[0-9]?-([0-9]{3}\.?[0-9]{2}|[0-9]{4})$'),
  CONSTRAINT ck_tx_ly_do CHECK (trang_thai_duyet NOT IN ('tu_choi','yeu_cau_bo_sung') OR ly_do IS NOT NULL),
  CONSTRAINT ck_tx_duyet CHECK (trang_thai_duyet <> 'da_duyet' OR (ID_nguoi_duyet IS NOT NULL AND ngay_duyet IS NOT NULL))
  -- [SVC] định mức tối đa 2 ô tô + 4 xe máy / căn hộ: VehicleService (khóa dòng căn hộ FOR UPDATE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BIỂU PHÍ ------------------------------------------------------------
CREATE TABLE bieu_phi (
  ID_bieu_phi  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_phi       VARCHAR(20) NOT NULL,             -- DIEN_B1..DIEN_B6, NUOC, QUAN_LY, XE_O_TO, XE_MAY
  ten_phi      VARCHAR(100) NOT NULL,
  tu_muc       INT UNSIGNED NULL,
  den_muc      INT UNSIGNED NULL,                -- NULL = không giới hạn
  don_gia      DECIMAL(12,2) NOT NULL,
  don_vi       VARCHAR(20) NOT NULL,
  ngay_cap_nhat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ID_nguoi_cap_nhat INT UNSIGNED NULL,
  CONSTRAINT uq_bp_ma UNIQUE (ma_phi),
  CONSTRAINT fk_bp_nd FOREIGN KEY (ID_nguoi_cap_nhat) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_bp_gia CHECK (don_gia >= 0),
  CONSTRAINT ck_bp_muc CHECK (den_muc IS NULL OR tu_muc IS NULL OR den_muc >= tu_muc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. CHỈ SỐ ĐIỆN NƯỚC ------------------------------------------------------
CREATE TABLE chi_so_dien_nuoc (
  ID_chi_so       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ky_cuoc         VARCHAR(7) NOT NULL,              -- YYYY-MM
  chi_so_dien_cu  INT UNSIGNED NOT NULL,
  chi_so_dien_moi INT UNSIGNED NOT NULL,
  chi_so_nuoc_cu  INT UNSIGNED NOT NULL,
  chi_so_nuoc_moi INT UNSIGNED NOT NULL,
  ghi_chu_xac_minh VARCHAR(255) NULL,            -- [SVC] bắt buộc khi tiêu thụ > 3 lần TB 3 kỳ trước
  da_khoa_so      TINYINT NOT NULL DEFAULT 0,
  ngay_nhap       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ID_can_ho       INT UNSIGNED NOT NULL,
  ID_nguoi_nhap   INT UNSIGNED NOT NULL,
  CONSTRAINT uq_cs_ky UNIQUE (ID_can_ho, ky_cuoc),
  CONSTRAINT fk_cs_ch FOREIGN KEY (ID_can_ho) REFERENCES can_ho(ID_can_ho) ON DELETE RESTRICT,
  CONSTRAINT fk_cs_nn FOREIGN KEY (ID_nguoi_nhap) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_cs_ky   CHECK (ky_cuoc REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
  CONSTRAINT ck_cs_dien CHECK (chi_so_dien_moi >= chi_so_dien_cu),
  CONSTRAINT ck_cs_nuoc CHECK (chi_so_nuoc_moi >= chi_so_nuoc_cu),
  CONSTRAINT ck_cs_khoa CHECK (da_khoa_so IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. HÓA ĐƠN --------------------------------------------------------------
CREATE TABLE hoa_don (
  ID_hoa_don     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_hoa_don     VARCHAR(30) NOT NULL,
  ky_cuoc        VARCHAR(7) NOT NULL,
  tong_tien      DECIMAL(14,2) NOT NULL DEFAULT 0,       -- [DB] trigger luôn = SUM(chi_tiet.thanh_tien)
  trang_thai_thanh_toan ENUM('cho_duyet','chua_thanh_toan','da_thanh_toan','da_huy') NOT NULL DEFAULT 'cho_duyet',
  ngay_lap       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ngay_phat_hanh DATETIME NULL,
  han_thanh_toan DATE NULL,
  ngay_thanh_toan DATETIME NULL,
  phuong_thuc_thanh_toan ENUM('vnpay','tien_mat','chuyen_khoan') NULL,
  ly_do_huy      VARCHAR(255) NULL,
  ID_can_ho      INT UNSIGNED NOT NULL,
  ID_nguoi_lap   INT UNSIGNED NOT NULL,
  ID_nguoi_duyet INT UNSIGNED NULL,
  -- [DB] mỗi căn hộ chỉ có 1 hóa đơn còn hiệu lực trong 1 kỳ (hóa đơn đã hủy không tính)
  ky_hieu_luc VARCHAR(7) GENERATED ALWAYS AS
     (CASE WHEN trang_thai_thanh_toan <> 'da_huy' THEN ky_cuoc END) STORED,
  CONSTRAINT uq_hd_ma UNIQUE (ma_hoa_don),
  CONSTRAINT uq_hd_ky UNIQUE (ID_can_ho, ky_hieu_luc),
  CONSTRAINT fk_hd_ch FOREIGN KEY (ID_can_ho) REFERENCES can_ho(ID_can_ho) ON DELETE RESTRICT,
  CONSTRAINT fk_hd_nl FOREIGN KEY (ID_nguoi_lap) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT fk_hd_nd FOREIGN KEY (ID_nguoi_duyet) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_hd_ky   CHECK (ky_cuoc REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
  CONSTRAINT ck_hd_tien CHECK (tong_tien >= 0),
  CONSTRAINT ck_hd_duyet CHECK (trang_thai_thanh_toan NOT IN ('chua_thanh_toan','da_thanh_toan')
       OR (ID_nguoi_duyet IS NOT NULL AND ngay_phat_hanh IS NOT NULL AND han_thanh_toan IS NOT NULL)),
  CONSTRAINT ck_hd_tt CHECK (trang_thai_thanh_toan <> 'da_thanh_toan'
       OR (ngay_thanh_toan IS NOT NULL AND phuong_thuc_thanh_toan IS NOT NULL)),
  CONSTRAINT ck_hd_huy CHECK (trang_thai_thanh_toan <> 'da_huy' OR ly_do_huy IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. CHI TIẾT HÓA ĐƠN (điện: mỗi bậc thang 1 dòng) -------------------------
CREATE TABLE chi_tiet_hoa_don (
  ID_chi_tiet INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loai_cuoc   ENUM('dien','nuoc','quan_ly','gui_xe_o_to','gui_xe_may') NOT NULL,
  dien_giai   VARCHAR(150) NOT NULL,
  so_luong    DECIMAL(12,2) NOT NULL,
  don_gia     DECIMAL(12,2) NOT NULL,            -- chụp đơn giá tại thời điểm lập
  thanh_tien  DECIMAL(14,2) NOT NULL,
  ID_hoa_don  INT UNSIGNED NOT NULL,
  CONSTRAINT fk_cthd_hd FOREIGN KEY (ID_hoa_don) REFERENCES hoa_don(ID_hoa_don) ON DELETE RESTRICT,
  CONSTRAINT ck_cthd_sl  CHECK (so_luong >= 0),
  CONSTRAINT ck_cthd_gia CHECK (don_gia >= 0),
  CONSTRAINT ck_cthd_tt  CHECK (thanh_tien = ROUND(so_luong * don_gia, 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. GIAO DỊCH THANH TOÁN (VNPay IPN + gạch nợ thủ công) ---------------------
CREATE TABLE giao_dich_thanh_toan (
  ID_giao_dich      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_giao_dich_ref  VARCHAR(40) NOT NULL,        -- vnp_TxnRef hoặc số phiếu thu
  hinh_thuc         ENUM('vnpay','tien_mat','chuyen_khoan') NOT NULL,
  so_tien           DECIMAL(14,2) NOT NULL,
  ma_ngan_hang      VARCHAR(20) NULL,
  ma_gd_ngan_hang   VARCHAR(40) NULL,            -- vnp_TransactionNo
  ma_phan_hoi       VARCHAR(5)  NULL,            -- vnp_ResponseCode
  chuoi_bam_secure_hash VARCHAR(256) NULL,
  trang_thai        ENUM('khoi_tao','thanh_cong','that_bai','ngoai_le') NOT NULL DEFAULT 'khoi_tao',
  ghi_chu           VARCHAR(255) NULL,
  so_chung_tu       VARCHAR(40) NULL,            -- số biên lai/phiếu thu (thủ công)
  nguoi_nop         VARCHAR(100) NULL,
  ngay_giao_dich    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ID_hoa_don        INT UNSIGNED NOT NULL,
  ID_nguoi_thuc_hien INT UNSIGNED NULL,
  -- [DB] mỗi hóa đơn chỉ có tối đa 1 giao dịch thành công (chống gạch nợ 2 lần)
  hd_thanh_cong INT UNSIGNED GENERATED ALWAYS AS
     (CASE WHEN trang_thai = 'thanh_cong' THEN ID_hoa_don END) STORED,
  CONSTRAINT uq_gd_ref UNIQUE (ma_giao_dich_ref),
  CONSTRAINT uq_gd_hd_tc UNIQUE (hd_thanh_cong),
  CONSTRAINT fk_gd_hd FOREIGN KEY (ID_hoa_don) REFERENCES hoa_don(ID_hoa_don) ON DELETE RESTRICT,
  CONSTRAINT fk_gd_nd FOREIGN KEY (ID_nguoi_thuc_hien) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_gd_tien CHECK (so_tien > 0),
  CONSTRAINT ck_gd_thu_cong CHECK (hinh_thuc = 'vnpay'
       OR (so_chung_tu IS NOT NULL AND ID_nguoi_thuc_hien IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. DỊCH VỤ TIỆN ÍCH ------------------------------------------------------
CREATE TABLE dich_vu_tien_ich (
  ID_tien_ich     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_tien_ich     VARCHAR(10) NOT NULL,
  ten_tien_ich    VARCHAR(100) NOT NULL,
  suc_chua_toi_da SMALLINT UNSIGNED NOT NULL,
  gio_mo_cua      TIME NOT NULL,
  gio_dong_cua    TIME NOT NULL,
  thoi_luong_khung SMALLINT UNSIGNED NOT NULL DEFAULT 120,  -- phút
  doc_quyen       TINYINT NOT NULL DEFAULT 1,     -- 1: mỗi khung giờ 1 lượt đặt; 0: dùng chung theo sức chứa
  han_muc_tuan    TINYINT UNSIGNED NULL,           -- số lượt/căn hộ/tuần (NULL = không giới hạn)
  trang_thai_hoat_dong ENUM('hoat_dong','tam_dung') NOT NULL DEFAULT 'hoat_dong',
  CONSTRAINT uq_ti_ma UNIQUE (ma_tien_ich),
  CONSTRAINT ck_ti_gio CHECK (gio_dong_cua > gio_mo_cua),
  CONSTRAINT ck_ti_sc  CHECK (suc_chua_toi_da > 0),
  CONSTRAINT ck_ti_dq  CHECK (doc_quyen IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ĐẶT TIỆN ÍCH (gồm cả lịch khóa bảo trì) -------------------------------------
CREATE TABLE dat_tien_ich (
  ID_dat_lich        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_ve              VARCHAR(40) NOT NULL,          -- chuỗi mã QR vé điện tử
  loai               ENUM('dat_cho','bao_tri') NOT NULL DEFAULT 'dat_cho',
  ngay_dat           DATE NOT NULL,
  khung_gio_bat_dau  TIME NOT NULL,
  khung_gio_ket_thuc TIME NOT NULL,
  so_nguoi           SMALLINT UNSIGNED NULL,
  trang_thai_dat ENUM('da_xac_nhan','da_su_dung','da_huy','huy_muon') NOT NULL DEFAULT 'da_xac_nhan',
  ngay_tao           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ngay_huy           DATETIME NULL,
  ID_tien_ich        INT UNSIGNED NOT NULL,
  ID_nhan_khau       INT UNSIGNED NULL,
  ID_can_ho          INT UNSIGNED NULL,
  ID_nguoi_tao       INT UNSIGNED NOT NULL,
  CONSTRAINT uq_dti_ve UNIQUE (ma_ve),
  CONSTRAINT fk_dti_ti FOREIGN KEY (ID_tien_ich) REFERENCES dich_vu_tien_ich(ID_tien_ich) ON DELETE RESTRICT,
  CONSTRAINT fk_dti_nk FOREIGN KEY (ID_nhan_khau) REFERENCES nhan_khau(ID_nhan_khau) ON DELETE RESTRICT,
  CONSTRAINT fk_dti_ch FOREIGN KEY (ID_can_ho) REFERENCES can_ho(ID_can_ho) ON DELETE RESTRICT,
  CONSTRAINT fk_dti_nt FOREIGN KEY (ID_nguoi_tao) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_dti_gio CHECK (khung_gio_ket_thuc > khung_gio_bat_dau),
  CONSTRAINT ck_dti_loai CHECK (loai = 'bao_tri'
       OR (ID_nhan_khau IS NOT NULL AND ID_can_ho IS NOT NULL AND so_nguoi > 0)),
  CONSTRAINT ck_dti_huy CHECK (trang_thai_dat NOT IN ('da_huy','huy_muon') OR ngay_huy IS NOT NULL)
  -- [SVC] chống trùng khung giờ, sức chứa, hạn mức tuần, chặn nợ quá hạn: BookingService (SELECT ... FOR UPDATE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_dti_lich ON dat_tien_ich (ID_tien_ich, ngay_dat, trang_thai_dat);

-- 13. PHIẾU SỰ CỐ -----------------------------------------------------------
CREATE TABLE phieu_su_co (
  ID_phieu        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_phieu        VARCHAR(20) NOT NULL,
  tieu_de         VARCHAR(150) NOT NULL,
  mo_ta_chi_tiet  TEXT NOT NULL,
  pham_vi         ENUM('can_ho','khu_vuc_chung') NOT NULL,
  vi_tri_su_co    VARCHAR(150) NULL,
  muc_do_uu_tien  ENUM('binh_thuong','khan_cap') NOT NULL DEFAULT 'binh_thuong',
  trang_thai_kanban ENUM('cho_tiep_nhan','da_phan_cong','dang_xu_ly','cho_linh_kien',
                         'cho_nghiem_thu','hoan_tat','tu_choi','da_huy') NOT NULL DEFAULT 'cho_tiep_nhan',
  anh_hien_truong VARCHAR(255) NOT NULL,
  anh_nghiem_thu  VARCHAR(255) NULL,
  vat_tu_tieu_hao VARCHAR(255) NULL,
  ghi_chu_ky_thuat VARCHAR(500) NULL,
  ly_do_tu_choi   VARCHAR(255) NULL,
  diem_danh_gia   TINYINT UNSIGNED NULL,
  so_lan_mo_lai   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ngay_tao        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ngay_phan_cong  DATETIME NULL,
  ngay_hoan_thanh DATETIME NULL,
  ID_can_ho       INT UNSIGNED NOT NULL,
  ID_nguoi_gui    INT UNSIGNED NOT NULL,
  ID_ky_thuat_vien INT UNSIGNED NULL,
  ID_nguoi_phan_cong INT UNSIGNED NULL,
  CONSTRAINT uq_psc_ma UNIQUE (ma_phieu),
  CONSTRAINT fk_psc_ch  FOREIGN KEY (ID_can_ho) REFERENCES can_ho(ID_can_ho) ON DELETE RESTRICT,
  CONSTRAINT fk_psc_ng  FOREIGN KEY (ID_nguoi_gui) REFERENCES nhan_khau(ID_nhan_khau) ON DELETE RESTRICT,
  CONSTRAINT fk_psc_ktv FOREIGN KEY (ID_ky_thuat_vien) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT fk_psc_npc FOREIGN KEY (ID_nguoi_phan_cong) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_psc_ktv CHECK (trang_thai_kanban IN ('cho_tiep_nhan','tu_choi','da_huy') OR ID_ky_thuat_vien IS NOT NULL),
  CONSTRAINT ck_psc_anh CHECK (trang_thai_kanban NOT IN ('cho_nghiem_thu','hoan_tat') OR anh_nghiem_thu IS NOT NULL),
  CONSTRAINT ck_psc_tc  CHECK (trang_thai_kanban <> 'tu_choi' OR ly_do_tu_choi IS NOT NULL),
  CONSTRAINT ck_psc_diem CHECK (diem_danh_gia IS NULL OR (diem_danh_gia BETWEEN 1 AND 5 AND trang_thai_kanban = 'hoan_tat')),
  CONSTRAINT ck_psc_ht  CHECK (trang_thai_kanban <> 'hoan_tat' OR ngay_hoan_thanh IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. NGỮ CẢNH AI -----------------------------------------------------------
CREATE TABLE ngu_canh_ai (
  ID_ngu_canh       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tieu_de_tai_lieu  VARCHAR(150) NOT NULL,
  noi_dung_ngu_canh TEXT NOT NULL,
  loai_tai_lieu     VARCHAR(50) NOT NULL,
  trang_thai        ENUM('nhap','dang_su_dung') NOT NULL DEFAULT 'nhap',
  ngay_cap_nhat     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ID_nguoi_cap_nhat INT UNSIGNED NOT NULL,
  CONSTRAINT fk_nc_nd FOREIGN KEY (ID_nguoi_cap_nhat) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT,
  CONSTRAINT ck_nc_nd CHECK (CHAR_LENGTH(noi_dung_ngu_canh) >= 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. NHẬT KÝ THAO TÁC (Audit Log – chỉ ghi thêm) ----------------------------------
CREATE TABLE nhat_ky_thao_tac (
  ID_nhat_ky   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hanh_dong    VARCHAR(50) NOT NULL,
  doi_tuong    VARCHAR(50) NOT NULL,
  ID_doi_tuong VARCHAR(40) NULL,
  noi_dung     VARCHAR(1000) NULL,
  dia_chi_ip   VARCHAR(45) NULL,
  thoi_gian    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ID_tai_khoan INT UNSIGNED NULL,
  CONSTRAINT fk_nk_tk2 FOREIGN KEY (ID_tai_khoan) REFERENCES tai_khoan(ID_tai_khoan) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  TRIGGER – các quy tắc mà CHECK/FK không biểu diễn được
-- =====================================================================
DELIMITER $$

-- T1. Chỉ được thêm/sửa/xóa chi tiết khi hóa đơn còn ở trạng thái "Chờ duyệt"
CREATE TRIGGER trg_cthd_bi BEFORE INSERT ON chi_tiet_hoa_don FOR EACH ROW
BEGIN
  IF (SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don = NEW.ID_hoa_don) <> 'cho_duyet' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_PHAT_HANH: khong duoc thay doi chi tiet hoa don da phat hanh';
  END IF;
END$$
CREATE TRIGGER trg_cthd_bu BEFORE UPDATE ON chi_tiet_hoa_don FOR EACH ROW
BEGIN
  IF (SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don = OLD.ID_hoa_don) <> 'cho_duyet'
     OR NEW.ID_hoa_don <> OLD.ID_hoa_don THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_PHAT_HANH: khong duoc thay doi chi tiet hoa don da phat hanh';
  END IF;
END$$
CREATE TRIGGER trg_cthd_bd BEFORE DELETE ON chi_tiet_hoa_don FOR EACH ROW
BEGIN
  IF (SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don = OLD.ID_hoa_don) <> 'cho_duyet' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_PHAT_HANH: khong duoc thay doi chi tiet hoa don da phat hanh';
  END IF;
END$$

-- T2. tong_tien luôn được tính lại từ chi tiết (không thể ghi tay)
CREATE TRIGGER trg_cthd_ai AFTER INSERT ON chi_tiet_hoa_don FOR EACH ROW
  UPDATE hoa_don SET tong_tien = 0 WHERE ID_hoa_don = NEW.ID_hoa_don$$
CREATE TRIGGER trg_cthd_au AFTER UPDATE ON chi_tiet_hoa_don FOR EACH ROW
  UPDATE hoa_don SET tong_tien = 0 WHERE ID_hoa_don = NEW.ID_hoa_don$$
CREATE TRIGGER trg_cthd_ad AFTER DELETE ON chi_tiet_hoa_don FOR EACH ROW
  UPDATE hoa_don SET tong_tien = 0 WHERE ID_hoa_don = OLD.ID_hoa_don$$

CREATE TRIGGER trg_hd_bi BEFORE INSERT ON hoa_don FOR EACH ROW
BEGIN
  SET NEW.tong_tien = 0;
  IF NEW.trang_thai_thanh_toan <> 'cho_duyet' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_TRANG_THAI: hoa don moi phai o trang thai cho duyet';
  END IF;
END$$

-- T3. Ràng buộc chuyển trạng thái hóa đơn + chống gạch nợ trực tiếp
CREATE TRIGGER trg_hd_bu BEFORE UPDATE ON hoa_don FOR EACH ROW
BEGIN
  SET NEW.tong_tien = (SELECT COALESCE(SUM(thanh_tien),0) FROM chi_tiet_hoa_don WHERE ID_hoa_don = NEW.ID_hoa_don);
  IF OLD.trang_thai_thanh_toan = 'da_huy' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_HUY: hoa don da huy khong duoc thay doi';
  END IF;
  IF OLD.trang_thai_thanh_toan = 'da_thanh_toan' AND NEW.trang_thai_thanh_toan <> 'da_thanh_toan' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_THANH_TOAN: hoa don da thanh toan khong duoc doi trang thai';
  END IF;
  IF NEW.trang_thai_thanh_toan <> OLD.trang_thai_thanh_toan AND NOT (
       (OLD.trang_thai_thanh_toan = 'cho_duyet'       AND NEW.trang_thai_thanh_toan IN ('chua_thanh_toan','da_huy')) OR
       (OLD.trang_thai_thanh_toan = 'chua_thanh_toan' AND NEW.trang_thai_thanh_toan IN ('da_thanh_toan','da_huy'))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_CHUYEN_TRANG_THAI: chuyen trang thai hoa don khong hop le';
  END IF;
  IF NEW.trang_thai_thanh_toan = 'da_thanh_toan' AND OLD.trang_thai_thanh_toan <> 'da_thanh_toan'
     AND NOT EXISTS (SELECT 1 FROM giao_dich_thanh_toan g
                     WHERE g.ID_hoa_don = NEW.ID_hoa_don AND g.trang_thai = 'thanh_cong'
                       AND g.so_tien = NEW.tong_tien) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_CHUA_CO_GIAO_DICH: khong the gach no khi chua co giao dich thanh cong khop so tien';
  END IF;
  IF NEW.trang_thai_thanh_toan = 'chua_thanh_toan' AND NEW.tong_tien <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_RONG: khong phat hanh hoa don khong co chi tiet';
  END IF;
END$$
CREATE TRIGGER trg_hd_bd BEFORE DELETE ON hoa_don FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_KHONG_XOA: hoa don chi duoc huy, khong duoc xoa'$$

-- T4. Giao dịch "thành công" phải khớp đúng số tiền của hóa đơn đang chưa thanh toán;
--     giao dịch đã kết thúc là bất biến; không xóa giao dịch
CREATE TRIGGER trg_gd_bi BEFORE INSERT ON giao_dich_thanh_toan FOR EACH ROW
BEGIN
  IF NEW.trang_thai = 'thanh_cong' AND NOT EXISTS (SELECT 1 FROM hoa_don h WHERE h.ID_hoa_don = NEW.ID_hoa_don
       AND h.trang_thai_thanh_toan = 'chua_thanh_toan' AND h.tong_tien = NEW.so_tien) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_KHONG_HOP_LE: giao dich thanh cong phai khop so tien hoa don chua thanh toan';
  END IF;
END$$
CREATE TRIGGER trg_gd_bu BEFORE UPDATE ON giao_dich_thanh_toan FOR EACH ROW
BEGIN
  IF OLD.trang_thai IN ('thanh_cong','that_bai') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_BAT_BIEN: giao dich da ket thuc khong duoc sua';
  END IF;
  IF NEW.trang_thai = 'thanh_cong' AND NOT EXISTS (SELECT 1 FROM hoa_don h WHERE h.ID_hoa_don = NEW.ID_hoa_don
       AND h.trang_thai_thanh_toan = 'chua_thanh_toan' AND h.tong_tien = NEW.so_tien) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_KHONG_HOP_LE: giao dich thanh cong phai khop so tien hoa don chua thanh toan';
  END IF;
END$$
CREATE TRIGGER trg_gd_bd BEFORE DELETE ON giao_dich_thanh_toan FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_KHONG_XOA: khong duoc xoa giao dich'$$

-- T5. Chỉ số đã khóa sổ (sau khi phê duyệt phát hành) không được sửa/xóa
CREATE TRIGGER trg_cs_bu BEFORE UPDATE ON chi_so_dien_nuoc FOR EACH ROW
BEGIN
  -- Chỉ cho phép đúng 1 thao tác trên dòng đã khóa: mở khóa (da_khoa_so 1->0) mà KHÔNG đổi số liệu.
  -- Thao tác này chỉ được Service gọi khi Kế toán trưởng hủy hóa đơn của kỳ (phiếu điều chỉnh).
  IF OLD.da_khoa_so = 1 AND NOT (NEW.da_khoa_so = 0
       AND NEW.chi_so_dien_cu = OLD.chi_so_dien_cu AND NEW.chi_so_dien_moi = OLD.chi_so_dien_moi
       AND NEW.chi_so_nuoc_cu = OLD.chi_so_nuoc_cu AND NEW.chi_so_nuoc_moi = OLD.chi_so_nuoc_moi
       AND NEW.ky_cuoc = OLD.ky_cuoc AND NEW.ID_can_ho = OLD.ID_can_ho) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CS_KHOA_SO: chi so da khoa so';
  END IF;
END$$
CREATE TRIGGER trg_cs_bd BEFORE DELETE ON chi_so_dien_nuoc FOR EACH ROW
BEGIN
  IF OLD.da_khoa_so = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CS_KHOA_SO: chi so da khoa so';
  END IF;
END$$

-- T6. Nhật ký thao tác chỉ được ghi thêm
CREATE TRIGGER trg_nk_bu BEFORE UPDATE ON nhat_ky_thao_tac FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NK_CHI_GHI_THEM: nhat ky khong duoc sua'$$
CREATE TRIGGER trg_nk_bd BEFORE DELETE ON nhat_ky_thao_tac FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NK_CHI_GHI_THEM: nhat ky khong duoc xoa'$$

DELIMITER ;
