<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 16 trigger bảo đảm các quy tắc mà CHECK/FK không biểu diễn được (Bảng 3.20 trong báo cáo):
 * tong_tien luôn bằng tổng chi tiết, chỉ gạch nợ khi có giao dịch thành công khớp tiền,
 * hóa đơn chỉ được hủy không được xóa, chỉ số đã khóa sổ không được sửa, nhật ký chỉ ghi thêm...
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->triggers() as $sql) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        foreach (['trg_cthd_bi', 'trg_cthd_bu', 'trg_cthd_bd', 'trg_cthd_ai', 'trg_cthd_au', 'trg_cthd_ad', 'trg_hd_bi', 'trg_hd_bu', 'trg_hd_bd', 'trg_gd_bi', 'trg_gd_bu', 'trg_gd_bd', 'trg_cs_bu', 'trg_cs_bd', 'trg_nk_bu', 'trg_nk_bd'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS $name");
        }
    }

    private function triggers(): array
    {
        return [
            // T1. Chỉ được thêm/sửa/xóa chi tiết khi hóa đơn còn ở trạng thái "Chờ duyệt"
            <<<'SQL'
            CREATE TRIGGER trg_cthd_bi BEFORE INSERT ON chi_tiet_hoa_don FOR EACH ROW
            BEGIN
              IF (SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don = NEW.ID_hoa_don) <> 'cho_duyet' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_PHAT_HANH: khong duoc thay doi chi tiet hoa don da phat hanh';
              END IF;
            END
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_cthd_bu BEFORE UPDATE ON chi_tiet_hoa_don FOR EACH ROW
            BEGIN
              IF (SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don = OLD.ID_hoa_don) <> 'cho_duyet'
                 OR NEW.ID_hoa_don <> OLD.ID_hoa_don THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_PHAT_HANH: khong duoc thay doi chi tiet hoa don da phat hanh';
              END IF;
            END
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_cthd_bd BEFORE DELETE ON chi_tiet_hoa_don FOR EACH ROW
            BEGIN
              IF (SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don = OLD.ID_hoa_don) <> 'cho_duyet' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_DA_PHAT_HANH: khong duoc thay doi chi tiet hoa don da phat hanh';
              END IF;
            END
            SQL,
            // T2. tong_tien luôn được tính lại từ chi tiết (không thể ghi tay)
            <<<'SQL'
            CREATE TRIGGER trg_cthd_ai AFTER INSERT ON chi_tiet_hoa_don FOR EACH ROW
              UPDATE hoa_don SET tong_tien = 0 WHERE ID_hoa_don = NEW.ID_hoa_don
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_cthd_au AFTER UPDATE ON chi_tiet_hoa_don FOR EACH ROW
              UPDATE hoa_don SET tong_tien = 0 WHERE ID_hoa_don = NEW.ID_hoa_don
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_cthd_ad AFTER DELETE ON chi_tiet_hoa_don FOR EACH ROW
              UPDATE hoa_don SET tong_tien = 0 WHERE ID_hoa_don = OLD.ID_hoa_don
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_hd_bi BEFORE INSERT ON hoa_don FOR EACH ROW
            BEGIN
              SET NEW.tong_tien = 0;
              IF NEW.trang_thai_thanh_toan <> 'cho_duyet' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_TRANG_THAI: hoa don moi phai o trang thai cho duyet';
              END IF;
            END
            SQL,
            // T3. Ràng buộc chuyển trạng thái hóa đơn + chống gạch nợ trực tiếp
            <<<'SQL'
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
            END
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_hd_bd BEFORE DELETE ON hoa_don FOR EACH ROW
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HD_KHONG_XOA: hoa don chi duoc huy, khong duoc xoa'
            SQL,
            // T4. Giao dịch "thành công" phải khớp đúng số tiền của hóa đơn đang chưa thanh toán;
            // giao dịch đã kết thúc là bất biến; không xóa giao dịch
            <<<'SQL'
            CREATE TRIGGER trg_gd_bi BEFORE INSERT ON giao_dich_thanh_toan FOR EACH ROW
            BEGIN
              IF NEW.trang_thai = 'thanh_cong' AND NOT EXISTS (SELECT 1 FROM hoa_don h WHERE h.ID_hoa_don = NEW.ID_hoa_don
                   AND h.trang_thai_thanh_toan = 'chua_thanh_toan' AND h.tong_tien = NEW.so_tien) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_KHONG_HOP_LE: giao dich thanh cong phai khop so tien hoa don chua thanh toan';
              END IF;
            END
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_gd_bu BEFORE UPDATE ON giao_dich_thanh_toan FOR EACH ROW
            BEGIN
              IF OLD.trang_thai IN ('thanh_cong','that_bai') THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_BAT_BIEN: giao dich da ket thuc khong duoc sua';
              END IF;
              IF NEW.trang_thai = 'thanh_cong' AND NOT EXISTS (SELECT 1 FROM hoa_don h WHERE h.ID_hoa_don = NEW.ID_hoa_don
                   AND h.trang_thai_thanh_toan = 'chua_thanh_toan' AND h.tong_tien = NEW.so_tien) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_KHONG_HOP_LE: giao dich thanh cong phai khop so tien hoa don chua thanh toan';
              END IF;
            END
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_gd_bd BEFORE DELETE ON giao_dich_thanh_toan FOR EACH ROW
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GD_KHONG_XOA: khong duoc xoa giao dich'
            SQL,
            // T5. Chỉ số đã khóa sổ (sau khi phê duyệt phát hành) không được sửa/xóa
            // Chỉ cho phép đúng 1 thao tác trên dòng đã khóa: mở khóa (da_khoa_so 1->0) mà KHÔNG đổi số liệu.
            // Thao tác này chỉ được Service gọi khi Kế toán trưởng hủy hóa đơn của kỳ (phiếu điều chỉnh).
            <<<'SQL'
            CREATE TRIGGER trg_cs_bu BEFORE UPDATE ON chi_so_dien_nuoc FOR EACH ROW
            BEGIN
              IF OLD.da_khoa_so = 1 AND NOT (NEW.da_khoa_so = 0
                   AND NEW.chi_so_dien_cu = OLD.chi_so_dien_cu AND NEW.chi_so_dien_moi = OLD.chi_so_dien_moi
                   AND NEW.chi_so_nuoc_cu = OLD.chi_so_nuoc_cu AND NEW.chi_so_nuoc_moi = OLD.chi_so_nuoc_moi
                   AND NEW.ky_cuoc = OLD.ky_cuoc AND NEW.ID_can_ho = OLD.ID_can_ho) THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CS_KHOA_SO: chi so da khoa so';
              END IF;
            END
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_cs_bd BEFORE DELETE ON chi_so_dien_nuoc FOR EACH ROW
            BEGIN
              IF OLD.da_khoa_so = 1 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CS_KHOA_SO: chi so da khoa so';
              END IF;
            END
            SQL,
            // T6. Nhật ký thao tác chỉ được ghi thêm
            <<<'SQL'
            CREATE TRIGGER trg_nk_bu BEFORE UPDATE ON nhat_ky_thao_tac FOR EACH ROW
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NK_CHI_GHI_THEM: nhat ky khong duoc sua'
            SQL,
            <<<'SQL'
            CREATE TRIGGER trg_nk_bd BEFORE DELETE ON nhat_ky_thao_tac FOR EACH ROW
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NK_CHI_GHI_THEM: nhat ky khong duoc xoa'
            SQL,
        ];
    }
};
