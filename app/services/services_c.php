<?php
/* ======================= ĐẶT TIỆN ÍCH (UC-RES07, UC-REC05) ======================= */
final class BookingService
{
    const CANCEL_HOURS = 2;
    /** Dùng trong kiểm thử đồng thời: giữ khóa thêm N micro-giây trước khi ghi */
    public static int $testDelayUs = 0;

    public static function facilities(): array
    {
        return DB::all("SELECT * FROM dich_vu_tien_ich ORDER BY ID_tien_ich");
    }
    public static function slotList(array $f): array
    {
        $r = [];
        for ($t = strtotime($f['gio_mo_cua']); $t + $f['thoi_luong_khung'] * 60 <= strtotime($f['gio_dong_cua']); $t += $f['thoi_luong_khung'] * 60) {
            $r[] = [date('H:i:s', $t), date('H:i:s', $t + $f['thoi_luong_khung'] * 60)];
        }
        return $r;
    }
    /** Trạng thái từng khung giờ trong ngày của 1 tiện ích */
    public static function slots(int $facilityId, string $date): array
    {
        $f = DB::one("SELECT * FROM dich_vu_tien_ich WHERE ID_tien_ich=?", [$facilityId]);
        $bk = DB::all("SELECT d.*, ch.ma_can_ho, nk.ho_ten FROM dat_tien_ich d LEFT JOIN can_ho ch ON ch.ID_can_ho=d.ID_can_ho
                         LEFT JOIN nhan_khau nk ON nk.ID_nhan_khau=d.ID_nhan_khau
                        WHERE d.ID_tien_ich=? AND d.ngay_dat=? AND d.trang_thai_dat IN ('da_xac_nhan','da_su_dung')", [$facilityId, $date]);
        $out = [];
        foreach (self::slotList($f) as [$s, $e]) {
            $people = 0; $state = 'trong'; $who = null;
            foreach ($bk as $b) {
                if ($b['khung_gio_bat_dau'] < $e && $b['khung_gio_ket_thuc'] > $s) {
                    if ($b['loai'] === 'bao_tri') { $state = 'bao_tri'; break; }
                    $people += (int)$b['so_nguoi'];
                    $who = $b;
                    if ($f['doc_quyen']) $state = 'da_dat';
                }
            }
            if ($state === 'trong' && !$f['doc_quyen'] && $people >= $f['suc_chua_toi_da']) $state = 'het_cho';
            $past = $date < today() || ($date === today() && $s <= date('H:i:s'));
            $out[] = ['start' => $s, 'end' => $e, 'state' => $past && $state === 'trong' ? 'qua_gio' : $state,
                'people' => $people, 'booking' => $who];
        }
        return $out;
    }

    public static function book(array $me, int $facilityId, string $date, string $start, int $people): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new BusinessException('Ngày sử dụng không hợp lệ.');
        if ($date < today()) throw new BusinessException('Không được chọn ngày trong quá khứ.');
        if ($date > date('Y-m-d', strtotime(today() . ' +30 days'))) throw new BusinessException('Chỉ được đặt trước tối đa 30 ngày.');
        if ($people < 1) throw new BusinessException('Số người tham gia phải lớn hơn 0.');
        return DB::tx(function () use ($me, $facilityId, $date, $start, $people) {
            // Khóa dòng tiện ích: mọi giao dịch đặt cùng tiện ích được xử lý tuần tự (chống trùng lịch khi đặt đồng thời)
            $f = DB::one("SELECT * FROM dich_vu_tien_ich WHERE ID_tien_ich=? FOR UPDATE", [$facilityId]);
            if (!$f || $f['trang_thai_hoat_dong'] !== 'hoat_dong') throw new BusinessException('Tiện ích đang tạm dừng hoạt động.');
            $slot = null;
            foreach (self::slotList($f) as $s) if (substr($s[0], 0, 5) === substr($start, 0, 5)) $slot = $s;
            if (!$slot) throw new BusinessException('Khung giờ không hợp lệ.');
            if ($date === today() && $slot[0] <= date('H:i:s')) throw new BusinessException('Khung giờ này đã qua.');
            if ($people > $f['suc_chua_toi_da']) throw new BusinessException("Vượt quá sức chứa cho phép ({$f['suc_chua_toi_da']} người).");
            if ($f['doc_quyen'] && BillingService::overdue30((int)$me['ID_can_ho']))
                throw new BusinessException('Căn hộ có hóa đơn quá hạn trên 30 ngày. Vui lòng thanh toán để mở lại quyền đặt tiện ích.');
            if ($f['han_muc_tuan']) {
                $used = (int)DB::val("SELECT COUNT(*) FROM dat_tien_ich WHERE ID_can_ho=? AND ID_tien_ich=? AND loai='dat_cho'
                        AND trang_thai_dat IN ('da_xac_nhan','da_su_dung','huy_muon') AND YEARWEEK(ngay_dat,3)=YEARWEEK(?,3)",
                    [$me['ID_can_ho'], $facilityId, $date]);
                if ($used >= $f['han_muc_tuan'])
                    throw new BusinessException("Căn hộ đã dùng hết hạn mức {$f['han_muc_tuan']} lượt {$f['ten_tien_ich']} trong tuần này.");
            }
            $overlap = DB::all("SELECT loai, so_nguoi FROM dat_tien_ich WHERE ID_tien_ich=? AND ngay_dat=?
                     AND trang_thai_dat IN ('da_xac_nhan','da_su_dung') AND khung_gio_bat_dau < ? AND khung_gio_ket_thuc > ?",
                [$facilityId, $date, $slot[1], $slot[0]]);
            foreach ($overlap as $o) if ($o['loai'] === 'bao_tri') throw new BusinessException('Khung giờ này đang khóa để bảo trì.');
            if ($f['doc_quyen'] && $overlap)
                throw new BusinessException('Khung giờ này vừa được đăng ký bởi cư dân khác, vui lòng chọn khung giờ khác.');
            if (!$f['doc_quyen'] && array_sum(array_column($overlap, 'so_nguoi')) + $people > $f['suc_chua_toi_da'])
                throw new BusinessException('Khung giờ này không còn đủ chỗ.');
            if (self::$testDelayUs) usleep(self::$testDelayUs);
            $code = sprintf('MOONS-%s-%s-%s-%s-%s', $f['ma_tien_ich'], str_replace('-', '', $me['ma_can_ho']),
                str_replace('-', '', $date), substr($slot[0], 0, 2), strtoupper(bin2hex(random_bytes(2))));
            DB::q("INSERT INTO dat_tien_ich(ma_ve, ngay_dat, khung_gio_bat_dau, khung_gio_ket_thuc, so_nguoi, ID_tien_ich, ID_nhan_khau, ID_can_ho, ID_nguoi_tao)
                   VALUES (?,?,?,?,?,?,?,?,?)", [$code, $date, $slot[0], $slot[1], $people, $facilityId, $me['ID_nhan_khau'], $me['ID_can_ho'],
                $me['ID_tai_khoan'] ?? Auth::id()]);
            $id = DB::id();
            AuditService::log('dat_tien_ich', 'dat_tien_ich', $id, $code);
            return ['id' => $id, 'code' => $code];
        });
    }

    public static function cancel(array $me, int $id): string
    {
        $b = DB::one("SELECT * FROM dat_tien_ich WHERE ID_dat_lich=? AND ID_can_ho=? AND loai='dat_cho'", [$id, $me['ID_can_ho']]);
        if (!$b || $b['trang_thai_dat'] !== 'da_xac_nhan') throw new BusinessException('Không thể hủy lịch này.');
        $startTs = strtotime("{$b['ngay_dat']} {$b['khung_gio_bat_dau']}");
        $late = $startTs - strtotime(now()) < self::CANCEL_HOURS * 3600;
        DB::q("UPDATE dat_tien_ich SET trang_thai_dat=?, ngay_huy=? WHERE ID_dat_lich=?", [$late ? 'huy_muon' : 'da_huy', now(), $id]);
        AuditService::log($late ? 'huy_muon_tien_ich' : 'huy_tien_ich', 'dat_tien_ich', $id);
        return $late ? 'Đã hủy. Do hủy trong vòng 2 giờ trước giờ sử dụng, lượt này vẫn được tính vào hạn mức tuần.'
            : 'Đã hủy lịch và mở lại khung giờ cho cư dân khác.';
    }

    /** Lễ tân xác thực vé QR khi cư dân đến sử dụng */
    public static function verify(string $code): array
    {
        $b = DB::one("SELECT d.*, t.ten_tien_ich, ch.ma_can_ho, nk.ho_ten FROM dat_tien_ich d JOIN dich_vu_tien_ich t ON t.ID_tien_ich=d.ID_tien_ich
                       LEFT JOIN can_ho ch ON ch.ID_can_ho=d.ID_can_ho LEFT JOIN nhan_khau nk ON nk.ID_nhan_khau=d.ID_nhan_khau
                      WHERE d.ma_ve=? AND d.loai='dat_cho'", [strtoupper(trim($code))]);
        if (!$b) throw new BusinessException('Mã vé không tồn tại.');
        if ($b['trang_thai_dat'] !== 'da_xac_nhan') throw new BusinessException('Vé ở trạng thái "' . lbl('ve', $b['trang_thai_dat']) . '", không hợp lệ.');
        if ($b['ngay_dat'] !== today()) throw new BusinessException('Vé dùng cho ngày ' . vn_date($b['ngay_dat']) . ', không phải hôm nay.');
        DB::q("UPDATE dat_tien_ich SET trang_thai_dat='da_su_dung' WHERE ID_dat_lich=?", [$b['ID_dat_lich']]);
        AuditService::log('xac_thuc_ve', 'dat_tien_ich', $b['ID_dat_lich'], $b['ma_ve']);
        return $b;
    }

    /** Khóa lịch bảo trì: tự động hủy các lịch đã đặt trùng khoảng thời gian */
    public static function maintenance(int $facilityId, string $date, string $start, string $end, string $note = ''): int
    {
        if (!Auth::is('ke_toan_truong', 'admin')) throw new BusinessException('Chỉ Ban Quản lý/Admin được khóa lịch bảo trì.');
        if ($date < today() || $end <= $start) throw new BusinessException('Khoảng thời gian bảo trì không hợp lệ.');
        return DB::tx(function () use ($facilityId, $date, $start, $end) {
            DB::q("SELECT ID_tien_ich FROM dich_vu_tien_ich WHERE ID_tien_ich=? FOR UPDATE", [$facilityId]);
            $n = DB::q("UPDATE dat_tien_ich SET trang_thai_dat='da_huy', ngay_huy=? WHERE ID_tien_ich=? AND ngay_dat=? AND loai='dat_cho'
                        AND trang_thai_dat='da_xac_nhan' AND khung_gio_bat_dau < ? AND khung_gio_ket_thuc > ?",
                [now(), $facilityId, $date, $end, $start])->rowCount();
            DB::q("INSERT INTO dat_tien_ich(ma_ve, loai, ngay_dat, khung_gio_bat_dau, khung_gio_ket_thuc, ID_tien_ich, ID_nguoi_tao)
                   VALUES (?, 'bao_tri', ?, ?, ?, ?, ?)", ['BT-' . date('ymdHis') . random_int(10, 99), $date, $start, $end, $facilityId, Auth::id()]);
            AuditService::log('khoa_bao_tri', 'dich_vu_tien_ich', $facilityId, "$date $start-$end, hủy $n lịch");
            return $n;
        });
    }
}

/* ======================= SỰ CỐ & KANBAN (UC-RES08, UC-DPV01, UC-TEC01/02) ======================= */
final class TicketService
{
    /** Chuyển trạng thái hợp lệ cho kỹ thuật viên trên bảng Kanban */
    const TECH_MOVES = ['da_phan_cong' => ['dang_xu_ly'], 'dang_xu_ly' => ['cho_linh_kien', 'cho_nghiem_thu'],
        'cho_linh_kien' => ['dang_xu_ly']];

    public static function create(array $me, array $d, string $photo): int
    {
        if (mb_strlen($d['tieu_de'] ?? '') < 5) throw new BusinessException('Tiêu đề sự cố tối thiểu 5 ký tự.');
        if (mb_strlen($d['mo_ta_chi_tiet'] ?? '') < 10) throw new BusinessException('Mô tả chi tiết tối thiểu 10 ký tự.');
        if (!isset(LABELS['pham_vi'][$d['pham_vi'] ?? '']) || !isset(LABELS['muc_do'][$d['muc_do_uu_tien'] ?? '']))
            throw new BusinessException('Vui lòng chọn phạm vi và mức độ.');
        DB::q("INSERT INTO phieu_su_co(ma_phieu, tieu_de, mo_ta_chi_tiet, pham_vi, vi_tri_su_co, muc_do_uu_tien, anh_hien_truong, ID_can_ho, ID_nguoi_gui)
               VALUES (?,?,?,?,?,?,?,?,?)", ['TMP' . bin2hex(random_bytes(6)), $d['tieu_de'], $d['mo_ta_chi_tiet'], $d['pham_vi'],
            $d['vi_tri_su_co'] ?: null, $d['muc_do_uu_tien'], $photo, $me['ID_can_ho'], $me['ID_nhan_khau']]);
        $id = DB::id();
        DB::q("UPDATE phieu_su_co SET ma_phieu=? WHERE ID_phieu=?", ['TK-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), $id]);
        AuditService::log('tao_phieu_su_co', 'phieu_su_co', $id, $d['tieu_de']);
        return $id;
    }
    private static function get(int $id): array
    {
        $t = DB::one("SELECT * FROM phieu_su_co WHERE ID_phieu=?", [$id]);
        if (!$t) throw new BusinessException('Không tìm thấy phiếu sự cố.');
        return $t;
    }
    public static function assign(int $id, int $tech): void
    {
        if (!Auth::is('dieu_phoi_vien')) throw new BusinessException('Chỉ Điều phối viên được phân công phiếu.');
        $t = self::get($id);
        if ($t['trang_thai_kanban'] !== 'cho_tiep_nhan') throw new BusinessException('Phiếu không ở trạng thái chờ tiếp nhận.');
        if (!DB::val("SELECT COUNT(*) FROM tai_khoan WHERE ID_tai_khoan=? AND vai_tro='ky_thuat_vien' AND trang_thai=1", [$tech]))
            throw new BusinessException('Kỹ thuật viên không hợp lệ.');
        DB::q("UPDATE phieu_su_co SET trang_thai_kanban='da_phan_cong', ID_ky_thuat_vien=?, ID_nguoi_phan_cong=?, ngay_phan_cong=? WHERE ID_phieu=?",
            [$tech, Auth::id(), now(), $id]);
        AuditService::log('phan_cong_su_co', 'phieu_su_co', $id, "KTV #$tech");
    }
    public static function reject(int $id, string $reason): void
    {
        if (!Auth::is('dieu_phoi_vien')) throw new BusinessException('Chỉ Điều phối viên được từ chối phiếu.');
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng nhập lý do từ chối.');
        if (self::get($id)['trang_thai_kanban'] !== 'cho_tiep_nhan') throw new BusinessException('Phiếu không ở trạng thái chờ tiếp nhận.');
        DB::q("UPDATE phieu_su_co SET trang_thai_kanban='tu_choi', ly_do_tu_choi=? WHERE ID_phieu=?", [$reason, $id]);
        AuditService::log('tu_choi_su_co', 'phieu_su_co', $id, $reason);
    }
    /** Kỹ thuật viên kéo thả thẻ – chỉ trên phiếu được giao cho chính mình */
    public static function move(int $id, string $to, string $note = ''): void
    {
        $t = self::get($id);
        if (!Auth::is('ky_thuat_vien') || (int)$t['ID_ky_thuat_vien'] !== Auth::id())
            throw new BusinessException('Bạn chỉ được cập nhật phiếu được phân công cho chính mình.');
        if (!in_array($to, self::TECH_MOVES[$t['trang_thai_kanban']] ?? [], true))
            throw new BusinessException('Không thể chuyển từ "' . lbl('kanban', $t['trang_thai_kanban']) . '" sang "' . lbl('kanban', $to) . '".');
        if ($to === 'cho_nghiem_thu') throw new BusinessException('Vui lòng dùng khung nghiệm thu (bắt buộc ảnh kết quả).');
        if ($to === 'cho_linh_kien' && mb_strlen($note) < 3) throw new BusinessException('Vui lòng ghi tên vật tư cần chờ.');
        DB::q("UPDATE phieu_su_co SET trang_thai_kanban=?, ghi_chu_ky_thuat=COALESCE(?, ghi_chu_ky_thuat) WHERE ID_phieu=?",
            [$to, $note ?: null, $id]);
        AuditService::log('cap_nhat_su_co', 'phieu_su_co', $id, $to);
    }
    /** Kỹ thuật viên đề xuất nghiệm thu: bắt buộc ≥1 ảnh kết quả (CHECK ck_psc_anh ở CSDL) */
    public static function submitAcceptance(int $id, ?string $photo, string $materials, string $note): void
    {
        $t = self::get($id);
        if (!Auth::is('ky_thuat_vien') || (int)$t['ID_ky_thuat_vien'] !== Auth::id())
            throw new BusinessException('Bạn chỉ được cập nhật phiếu được phân công cho chính mình.');
        if ($t['trang_thai_kanban'] !== 'dang_xu_ly') throw new BusinessException('Chỉ đề xuất nghiệm thu khi phiếu đang xử lý.');
        if (mb_strlen($note) < 5) throw new BusinessException('Vui lòng nhập ghi chú kỹ thuật.');
        try {
            DB::q("UPDATE phieu_su_co SET trang_thai_kanban='cho_nghiem_thu', anh_nghiem_thu=?, vat_tu_tieu_hao=?, ghi_chu_ky_thuat=? WHERE ID_phieu=?",
                [$photo, $materials ?: null, $note, $id]);
        } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
        AuditService::log('de_xuat_nghiem_thu', 'phieu_su_co', $id);
    }
    public static function confirm(array $me, int $id, int $stars): void
    {
        $t = self::get($id);
        if ((int)$t['ID_can_ho'] !== (int)$me['ID_can_ho'] || $t['trang_thai_kanban'] !== 'cho_nghiem_thu')
            throw new BusinessException('Phiếu không ở trạng thái chờ nghiệm thu.');
        if ($stars < 1 || $stars > 5) throw new BusinessException('Vui lòng chấm điểm từ 1 đến 5 sao.');
        DB::q("UPDATE phieu_su_co SET trang_thai_kanban='hoan_tat', diem_danh_gia=?, ngay_hoan_thanh=? WHERE ID_phieu=?", [$stars, now(), $id]);
        AuditService::log('nghiem_thu_su_co', 'phieu_su_co', $id, "$stars sao");
    }
    public static function reopen(array $me, int $id, string $reason): void
    {
        $t = self::get($id);
        if ((int)$t['ID_can_ho'] !== (int)$me['ID_can_ho'] || $t['trang_thai_kanban'] !== 'cho_nghiem_thu')
            throw new BusinessException('Phiếu không ở trạng thái chờ nghiệm thu.');
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng mô tả điểm chưa đạt.');
        DB::q("UPDATE phieu_su_co SET trang_thai_kanban='dang_xu_ly', so_lan_mo_lai=so_lan_mo_lai+1,
               ghi_chu_ky_thuat=CONCAT('[Cư dân yêu cầu sửa lại] ', ?) WHERE ID_phieu=?", [$reason, $id]);
        AuditService::log('mo_lai_su_co', 'phieu_su_co', $id, $reason);
    }
    public static function cancel(array $me, int $id): void
    {
        $n = DB::q("UPDATE phieu_su_co SET trang_thai_kanban='da_huy' WHERE ID_phieu=? AND ID_can_ho=? AND trang_thai_kanban='cho_tiep_nhan'",
            [$id, $me['ID_can_ho']])->rowCount();
        if (!$n) throw new BusinessException('Chỉ hủy được phiếu đang chờ tiếp nhận.');
        AuditService::log('huy_su_co', 'phieu_su_co', $id);
    }
    /** Phiếu khẩn cấp chưa phân công quá 15 phút (vi phạm SLA) */
    public static function slaBreached(array $t): bool
    {
        return $t['muc_do_uu_tien'] === 'khan_cap' && $t['trang_thai_kanban'] === 'cho_tiep_nhan'
            && strtotime(now()) - strtotime($t['ngay_tao']) > 15 * 60;
    }
}

/* ======================= TRỢ LÝ AI – RAG (UC-RES09, UC-ADM03) ======================= */
final class AiService
{
    const FALLBACK = 'Hệ thống AI hiện đang bảo trì hoặc bận, vui lòng liên hệ trực tiếp Lễ tân qua số Hotline tòa nhà 028 3838 6868.';

    public static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['đ' => 'd']);
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        return preg_replace('/[\x{0300}-\x{036f}]/u', '', $s);
    }
    private static function tokens(string $s): array
    {
        $stop = ['la', 'cua', 'toi', 'co', 'khong', 'the', 'nao', 'gi', 'bao', 'nhieu', 'va', 'cho', 'duoc', 'o', 'thi', 'may', 'ban', 'minh', 'a', 'oi', 'nhe', 'voi', 've', 'can', 'muon', 'hoi'];
        $w = preg_split('/[^a-z0-9]+/', self::fold($s), -1, PREG_SPLIT_NO_EMPTY);
        $w = array_values(array_filter($w, fn($x) => !in_array($x, $stop, true)));
        $bi = [];
        for ($i = 0; $i + 1 < count($w); $i++) $bi[] = $w[$i] . '_' . $w[$i + 1];
        return [$w, $bi];
    }
    /** Truy xuất (Retrieval): chấm điểm đoạn văn theo từ khóa và cụm 2 từ, lấy top-k */
    public static function retrieve(string $q, int $k = 3): array
    {
        [$qw, $qb] = self::tokens($q);
        if (!$qw) return [];
        $chunks = [];
        foreach (DB::all("SELECT * FROM ngu_canh_ai WHERE trang_thai='dang_su_dung'") as $d) {
            foreach (preg_split('/\n\s*\n/', $d['noi_dung_ngu_canh']) as $p) {
                [$pw, $pb] = self::tokens($d['tieu_de_tai_lieu'] . ' ' . $p);
                $hitW = count(array_intersect(array_unique($qw), $pw));
                $hitB = count(array_intersect($qb, $pb));
                $score = $hitW + 2 * $hitB;
                // Ngưỡng liên quan: khớp ≥ 50% từ khóa của câu hỏi hoặc khớp ít nhất 1 cụm 2 từ
                if ($hitB > 0 || $hitW / count(array_unique($qw)) >= 0.5) $chunks[] = ['score' => $score, 'title' => $d['tieu_de_tai_lieu'], 'text' => trim($p)];
            }
        }
        usort($chunks, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($chunks, 0, $k);
    }
    /** Câu hỏi về dữ liệu riêng (hóa đơn của tôi) – trả lời từ CSDL, KHÔNG gửi cho mô hình AI */
    private static function personal(string $q, ?array $me): ?array
    {
        $f = self::fold($q);
        if (!$me || !preg_match('/(hoa don|cuoc|tien).*(cua toi|nha toi|thang nay|can ho toi|cua minh)|(no|con no)/', $f)) return null;
        $inv = BillingService::visibleInvoices($me);
        if (!$inv) return ['answer' => 'Căn hộ của bạn chưa có hóa đơn nào được phát hành trong phạm vi bạn được xem.', 'source' => 'Dữ liệu hóa đơn căn hộ'];
        $h = $inv[0];
        $parts = DB::all("SELECT loai_cuoc, SUM(thanh_tien) t FROM chi_tiet_hoa_don WHERE ID_hoa_don=? GROUP BY loai_cuoc", [$h['ID_hoa_don']]);
        $names = ['dien' => 'điện', 'nuoc' => 'nước', 'quan_ly' => 'phí quản lý', 'gui_xe_o_to' => 'gửi ô tô', 'gui_xe_may' => 'gửi xe máy'];
        $detail = implode(', ', array_map(fn($p) => $names[$p['loai_cuoc']] . ' ' . money($p['t']), $parts));
        return ['answer' => sprintf('Hóa đơn %s của căn hộ %s: %s (%s). Trạng thái: %s.', mb_strtolower(ky_label($h['ky_cuoc'])),
            $me['ma_can_ho'], money($h['tong_tien']), $detail, mb_strtolower(lbl('hd', $h['trang_thai_thanh_toan']))),
            'source' => 'Dữ liệu hóa đơn căn hộ (không gửi tới AI)'];
    }

    public static function ask(string $q, ?array $me): array
    {
        $q = trim(mb_substr($q, 0, 500));
        if ($q === '') throw new BusinessException('Vui lòng nhập câu hỏi.');
        if ($p = self::personal($q, $me)) return $p + ['mode' => 'du_lieu_rieng'];
        $ctx = self::retrieve($q);
        if (!$ctx) return ['answer' => 'Tôi chưa tìm thấy quy định liên quan trong tài liệu của Ban Quản lý. Bạn vui lòng liên hệ Lễ tân để được hỗ trợ.',
            'source' => null, 'mode' => 'khong_tim_thay'];
        $source = implode('; ', array_unique(array_column($ctx, 'title')));
        $key = cfg('gemini')['api_key'];
        if ($key === '') {   // Chế độ tra cứu nội quy: trả nguyên văn đoạn liên quan nhất
            return ['answer' => $ctx[0]['text'], 'source' => $ctx[0]['title'], 'mode' => 'tra_cuu'];
        }
        $system = "Bạn là Moons Bot, trợ lý của Ban Quản lý chung cư Moons. Chỉ trả lời dựa trên NGỮ CẢNH được cung cấp, "
            . "văn phong lịch sự, ngắn gọn (tối đa 4 câu). Không bịa số liệu. Nếu ngữ cảnh không có thông tin, hãy nói chưa có thông tin và đề nghị liên hệ Lễ tân.";
        $context = implode("\n\n", array_map(fn($c) => "[{$c['title']}]\n{$c['text']}", $ctx));
        $body = json_encode(['systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => "NGỮ CẢNH:\n$context\n\nCÂU HỎI: $q"]]]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 400]], JSON_UNESCAPED_UNICODE);
        $g = cfg('gemini');
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$g['model']}:generateContent");
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $g['timeout'], CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key]]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $text = $res ? (json_decode($res, true)['candidates'][0]['content']['parts'][0]['text'] ?? null) : null;
        if ($code !== 200 || !$text) {
            AuditService::log('ai_loi', 'ngu_canh_ai', null, "HTTP $code");
            return ['answer' => self::FALLBACK, 'source' => null, 'mode' => 'du_phong'];
        }
        return ['answer' => trim($text), 'source' => $source, 'mode' => 'gemini'];
    }

    public static function saveDoc(?int $id, string $title, string $type, string $content): void
    {
        if (mb_strlen($title) < 5) throw new BusinessException('Tiêu đề tài liệu tối thiểu 5 ký tự.');
        if (mb_strlen($content) < 20) throw new BusinessException('Nội dung ngữ cảnh tối thiểu 20 ký tự.');
        if ($id) DB::q("UPDATE ngu_canh_ai SET tieu_de_tai_lieu=?, loai_tai_lieu=?, noi_dung_ngu_canh=?, trang_thai='nhap', ngay_cap_nhat=?, ID_nguoi_cap_nhat=?
                        WHERE ID_ngu_canh=?", [$title, $type, $content, now(), Auth::id(), $id]);
        else DB::q("INSERT INTO ngu_canh_ai(tieu_de_tai_lieu, loai_tai_lieu, noi_dung_ngu_canh, ID_nguoi_cap_nhat) VALUES (?,?,?,?)",
            [$title, $type, $content, Auth::id()]);
        AuditService::log($id ? 'sua_ngu_canh' : 'them_ngu_canh', 'ngu_canh_ai', $id ?: DB::id(), $title);
    }
    /** "Đóng gói và cập nhật ngữ cảnh": đưa tài liệu nháp vào sử dụng (không huấn luyện lại mô hình) */
    public static function publish(): int
    {
        $n = DB::q("UPDATE ngu_canh_ai SET trang_thai='dang_su_dung' WHERE trang_thai='nhap'")->rowCount();
        AuditService::log('cap_nhat_ngu_canh_ai', 'ngu_canh_ai', null, "$n tài liệu");
        return $n;
    }
}

/* ======================= BÁO CÁO & DASHBOARD (UC-ADM04, UC-ACC05) ======================= */
final class ReportService
{
    /** Mọi chỉ tiêu tài chính đều loại trừ hóa đơn "Đã hủy" và "Chờ duyệt" */
    public static function kpi(): array
    {
        $month = substr(today(), 0, 7);
        return [
            'occupied' => (int)DB::val("SELECT COUNT(*) FROM can_ho WHERE trang_thai_o<>'trong'"),
            'total' => (int)DB::val("SELECT COUNT(*) FROM can_ho"),
            'residents' => (int)DB::val("SELECT COUNT(*) FROM nhan_khau WHERE trang_thai_duyet='da_duyet'"),
            'vehicles' => (int)DB::val("SELECT COUNT(*) FROM the_xe WHERE trang_thai_duyet='da_duyet'"),
            'collected' => (float)DB::val("SELECT COALESCE(SUM(so_tien),0) FROM giao_dich_thanh_toan WHERE trang_thai='thanh_cong'
                                AND DATE_FORMAT(ngay_giao_dich,'%Y-%m')=?", [$month]),
            'debt' => (float)DB::val("SELECT COALESCE(SUM(tong_tien),0) FROM hoa_don WHERE trang_thai_thanh_toan='chua_thanh_toan'"),
            'tickets_done' => (int)DB::val("SELECT COUNT(*) FROM phieu_su_co WHERE trang_thai_kanban='hoan_tat'"),
            'tickets_total' => (int)DB::val("SELECT COUNT(*) FROM phieu_su_co WHERE trang_thai_kanban NOT IN ('tu_choi','da_huy')"),
            'sla' => DB::one("SELECT COUNT(*) n, SUM(TIMESTAMPDIFF(MINUTE, ngay_tao, ngay_phan_cong) <= 15) ok FROM phieu_su_co
                               WHERE muc_do_uu_tien='khan_cap' AND ngay_phan_cong IS NOT NULL"),
            'rating' => (float)DB::val("SELECT COALESCE(AVG(diem_danh_gia),0) FROM phieu_su_co WHERE diem_danh_gia IS NOT NULL"),
            'month' => $month,
        ];
    }
    /** Doanh thu thực thu 12 tháng gần nhất (theo ngày giao dịch thành công) */
    public static function revenue12(): array
    {
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = date('Y-m', strtotime(substr(today(), 0, 7) . "-01 -$i month"));
            $out[$m] = 0.0;
        }
        foreach (DB::all("SELECT DATE_FORMAT(ngay_giao_dich,'%Y-%m') m, SUM(so_tien) s FROM giao_dich_thanh_toan
                          WHERE trang_thai='thanh_cong' GROUP BY m") as $r) if (isset($out[$r['m']])) $out[$r['m']] = (float)$r['s'];
        return $out;
    }
    public static function debtByTower(): array
    {
        return DB::all("SELECT ch.thap_nha, COALESCE(SUM(h.tong_tien),0) s, COUNT(h.ID_hoa_don) n FROM can_ho ch
                        LEFT JOIN hoa_don h ON h.ID_can_ho=ch.ID_can_ho AND h.trang_thai_thanh_toan='chua_thanh_toan'
                        GROUP BY ch.thap_nha ORDER BY ch.thap_nha");
    }
    public static function debtRows(): array
    {
        return DB::all("SELECT ch.thap_nha, ch.ma_can_ho, h.ma_hoa_don, h.ky_cuoc, h.tong_tien, h.han_thanh_toan,
                               DATEDIFF(?, h.han_thanh_toan) qua_han
                          FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
                         WHERE h.trang_thai_thanh_toan='chua_thanh_toan' ORDER BY ch.thap_nha, ch.ma_can_ho, h.ky_cuoc", [today()]);
    }
    public static function csv(string $filename, array $header, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM để Excel đọc đúng tiếng Việt
        fputcsv($out, $header);
        foreach ($rows as $r) fputcsv($out, array_values($r));
        fclose($out);
        AuditService::log('xuat_bao_cao', 'bao_cao', $filename);
        exit;
    }
}
