<?php
final class ResidentController
{
    /** Hồ sơ cư dân mới nhất (làm mới mỗi request để phản ánh thay đổi trạng thái) */
    public static function me(): array
    {
        $me = DB::one("SELECT nk.*, ch.ma_can_ho, ch.dien_tich, ch.thap_nha, ch.trang_thai_o, tk.vai_tro, tk.ID_tai_khoan
                         FROM nhan_khau nk JOIN can_ho ch ON ch.ID_can_ho=nk.ID_can_ho JOIN tai_khoan tk ON tk.ID_tai_khoan=nk.ID_tai_khoan
                        WHERE nk.ID_tai_khoan=?", [Auth::id()]);
        if (!$me || $me['trang_thai_duyet'] !== 'da_duyet') { Auth::logout(); redirect('auth/login'); }
        return $me;
    }
    private function render(string $view, array $data = []): void
    {
        $me = $data['me'] ?? self::me();
        $unpaid = array_filter(BillingService::visibleInvoices($me), fn($h) => $h['trang_thai_thanh_toan'] === 'chua_thanh_toan');
        $pendingAccept = (int)DB::val("SELECT COUNT(*) FROM phieu_su_co WHERE ID_can_ho=? AND trang_thai_kanban='cho_nghiem_thu'", [$me['ID_can_ho']]);
        View::render("resident/$view", $data + ['me' => $me, 'bell' => count($unpaid) + $pendingAccept], 'resident');
    }

    public function home(): void
    {
        $me = self::me();
        $inv = BillingService::visibleInvoices($me);
        $cards = DB::all("SELECT t.* FROM the_ra_vao t JOIN nhan_khau nk ON nk.ID_nhan_khau=t.ID_nhan_khau
                           WHERE nk.ID_can_ho=? AND t.trang_thai_the='kich_hoat' ORDER BY t.loai_the", [$me['ID_can_ho']]);
        $count = (int)DB::val("SELECT COUNT(*) FROM nhan_khau WHERE ID_can_ho=? AND trang_thai_duyet='da_duyet'
                                AND (loai_cu_dan<>'chu_ho' OR ?<>'cho_thue')", [$me['ID_can_ho'], $me['trang_thai_o']]);
        $this->render('home', ['me' => $me, 'latest' => $inv[0] ?? null, 'cards' => $cards, 'count' => $count,
            'blocked' => BillingService::overdue30((int)$me['ID_can_ho'])]);
    }

    public function residents(): void
    {
        $me = self::me();
        $members = DB::all("SELECT * FROM nhan_khau WHERE ID_can_ho=? AND trang_thai_duyet IN ('cho_duyet','da_duyet','tu_choi')
                             ORDER BY FIELD(loai_cu_dan,'chu_ho','khach_thue','thanh_vien'), ID_nhan_khau", [$me['ID_can_ho']]);
        $cards = DB::all("SELECT t.*, nk.ho_ten FROM the_ra_vao t JOIN nhan_khau nk ON nk.ID_nhan_khau=t.ID_nhan_khau
                           WHERE nk.ID_can_ho=? AND t.trang_thai_the<>'da_huy' ORDER BY t.ID_the", [$me['ID_can_ho']]);
        $this->render('residents', ['me' => $me, 'members' => $members, 'cards' => $cards, 'tab' => input('tab', 'members')]);
    }
    public function declare(): void
    {
        $me = self::me();
        ResidentService::declare($me, $_POST, save_upload('anh_giay_to', 'giay_to', false));
        flash('success', 'Đã gửi khai báo. Lễ tân sẽ xác minh và phê duyệt.');
        redirect('residents');
    }
    public function removeMember(): void
    {
        ResidentService::remove(self::me(), (int)input('id'));
        flash('success', 'Đã cập nhật thành viên chuyển đi.');
        redirect('residents');
    }
    public function reportLost(): void
    {
        $me = self::me();
        CardService::setState((int)input('id'), 'bao_mat', (int)$me['ID_can_ho']);
        flash('success', 'Đã khóa thẻ báo mất. Vui lòng liên hệ Lễ tân để cấp lại.');
        redirect('residents', ['tab' => 'cards']);
    }

    public function vehicles(): void
    {
        $me = self::me();
        $list = DB::all("SELECT * FROM the_xe WHERE ID_can_ho=? AND trang_thai_duyet<>'da_huy' ORDER BY ID_the_xe DESC", [$me['ID_can_ho']]);
        $used = ['o_to' => VehicleService::used((int)$me['ID_can_ho'], 'o_to'), 'xe_may' => VehicleService::used((int)$me['ID_can_ho'], 'xe_may')];
        $this->render('vehicles', ['me' => $me, 'list' => $list, 'used' => $used]);
    }
    public function vehicleRegister(): void
    {
        $me = self::me();
        VehicleService::register($me, $_POST, save_upload('anh_ca_vet_xe', 'ca_vet'));
        flash('success', 'Đã gửi đơn đăng ký thẻ xe. Lễ tân sẽ xét duyệt.');
        redirect('vehicles');
    }
    public function vehicleResubmit(): void
    {
        VehicleService::resubmit(self::me(), (int)input('id'), save_upload('anh_ca_vet_xe', 'ca_vet', false));
        flash('success', 'Đã gửi lại hồ sơ bổ sung.');
        redirect('vehicles');
    }
    public function vehicleCancel(): void
    {
        VehicleService::cancel(self::me(), (int)input('id'));
        flash('success', 'Đã hủy thẻ xe.');
        redirect('vehicles');
    }

    public function invoices(): void
    {
        $me = self::me();
        $list = BillingService::visibleInvoices($me);
        $cur = null;
        foreach ($list as $h) if ((string)$h['ID_hoa_don'] === (string)input('id')) $cur = $h;
        $cur = $cur ?? ($list[0] ?? null);
        $lines = $cur ? DB::all("SELECT * FROM chi_tiet_hoa_don WHERE ID_hoa_don=? ORDER BY FIELD(loai_cuoc,'dien','nuoc','quan_ly','gui_xe_o_to','gui_xe_may'), ID_chi_tiet",
            [$cur['ID_hoa_don']]) : [];
        $reading = $cur ? DB::one("SELECT * FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc=?", [$me['ID_can_ho'], $cur['ky_cuoc']]) : null;
        $this->render('invoices', ['me' => $me, 'list' => $list, 'cur' => $cur, 'lines' => $lines, 'reading' => $reading]);
    }

    public function facilities(): void
    {
        $me = self::me();
        $fs = BookingService::facilities();
        $fid = (int)input('f', $fs[0]['ID_tien_ich']);
        $date = input('date', today());
        $f = DB::one("SELECT * FROM dich_vu_tien_ich WHERE ID_tien_ich=?", [$fid]);
        $mine = DB::all("SELECT d.*, t.ten_tien_ich FROM dat_tien_ich d JOIN dich_vu_tien_ich t ON t.ID_tien_ich=d.ID_tien_ich
                          WHERE d.ID_can_ho=? AND d.loai='dat_cho' AND d.ngay_dat>=? AND d.trang_thai_dat IN ('da_xac_nhan')
                          ORDER BY d.ngay_dat, d.khung_gio_bat_dau", [$me['ID_can_ho'], today()]);
        $this->render('facilities', ['me' => $me, 'fs' => $fs, 'f' => $f, 'date' => $date, 'slots' => BookingService::slots($fid, $date),
            'mine' => $mine, 'blocked' => BillingService::overdue30((int)$me['ID_can_ho']), 'newTicket' => $_SESSION['new_ticket'] ?? null]);
        unset($_SESSION['new_ticket']);
    }
    public function book(): void
    {
        $me = self::me();
        $r = BookingService::book($me, (int)input('f'), (string)input('date'), (string)input('start'), (int)input('so_nguoi'));
        $_SESSION['new_ticket'] = $r['id'];
        flash('success', 'Đặt tiện ích thành công.');
        redirect('facilities', ['f' => input('f'), 'date' => input('date')]);
    }
    public function bookingCancel(): void
    {
        flash('success', BookingService::cancel(self::me(), (int)input('id')));
        redirect('facilities');
    }

    public function tickets(): void
    {
        $me = self::me();
        $list = DB::all("SELECT p.*, tk.ho_ten ktv_ten, tk.so_dien_thoai ktv_sdt FROM phieu_su_co p
                          LEFT JOIN tai_khoan tk ON tk.ID_tai_khoan=p.ID_ky_thuat_vien
                          WHERE p.ID_can_ho=? ORDER BY p.trang_thai_kanban IN ('hoan_tat','tu_choi','da_huy'), p.ngay_tao DESC", [$me['ID_can_ho']]);
        $this->render('tickets', ['me' => $me, 'list' => $list]);
    }
    public function ticketCreate(): void
    {
        $me = self::me();
        TicketService::create($me, $_POST, save_upload('anh_hien_truong', 'su_co'));
        flash('success', 'Đã gửi phiếu báo hỏng. Bạn có thể theo dõi tiến độ bên dưới.');
        redirect('tickets');
    }
    public function ticketConfirm(): void
    {
        TicketService::confirm(self::me(), (int)input('id'), (int)input('stars'));
        flash('success', 'Cảm ơn bạn đã nghiệm thu và đánh giá.');
        redirect('tickets');
    }
    public function ticketReopen(): void
    {
        TicketService::reopen(self::me(), (int)input('id'), (string)input('reason'));
        flash('success', 'Đã gửi yêu cầu sửa lại cho kỹ thuật viên.');
        redirect('tickets');
    }
    public function ticketCancel(): void
    {
        TicketService::cancel(self::me(), (int)input('id'));
        flash('success', 'Đã hủy phiếu.');
        redirect('tickets');
    }
    public function aiAsk(): void
    {
        $me = Auth::isResident() ? self::me() : null;
        try {
            json_out(['ok' => true] + AiService::ask((string)input('q'), $me));
        } catch (BusinessException $e) { json_out(['ok' => false, 'error' => $e->getMessage()], 422); }
    }
}
