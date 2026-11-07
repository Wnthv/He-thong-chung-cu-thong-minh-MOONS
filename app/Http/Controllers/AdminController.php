<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\CanHo;
use App\Models\NguCanhAi;
use App\Models\TaiKhoan;
use App\Services\AccountService;
use App\Services\AiService;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\BookingService;
use App\Services\CardService;
use App\Services\FeeService;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\ResidentService;
use App\Services\TicketService;
use App\Services\VehicleService;
use App\Support\Db;
use App\Support\Rbac;

/** Trang quản trị cho Ban Quản lý (Lễ tân, Kế toán, Kế toán trưởng, Điều phối viên, Kỹ thuật viên, Admin) */
class AdminController extends Controller
{
    private function render(string $view, string $title, array $data = [])
    {
        return view("admin.$view", $data + ['title' => $title, 'bell' => $this->bell()]);
    }
    /** Số việc đang chờ theo vai trò (chuông thông báo) */
    private function bell(): int
    {
        return match (Rbac::role()) {
            'le_tan' => (int)Db::val("SELECT (SELECT COUNT(*) FROM nhan_khau WHERE trang_thai_duyet='cho_duyet')
                                            + (SELECT COUNT(*) FROM the_xe WHERE trang_thai_duyet='cho_duyet')"),
            'ke_toan_truong' => (int)Db::val("SELECT COUNT(*) FROM hoa_don WHERE trang_thai_thanh_toan='cho_duyet'"),
            'ke_toan' => (int)Db::val("SELECT COUNT(*) FROM giao_dich_thanh_toan WHERE trang_thai='ngoai_le'"),
            'dieu_phoi_vien' => (int)Db::val("SELECT COUNT(*) FROM phieu_su_co WHERE trang_thai_kanban='cho_tiep_nhan'"),
            'ky_thuat_vien' => (int)Db::val("SELECT COUNT(*) FROM phieu_su_co WHERE ID_ky_thuat_vien=? AND trang_thai_kanban='da_phan_cong'", [Rbac::id()]),
            default => (int)Db::val("SELECT COUNT(*) FROM phieu_su_co WHERE trang_thai_kanban='cho_tiep_nhan' AND muc_do_uu_tien='khan_cap'"),
        };
    }
    private function back(string $route, array $q = []) { return to_route($route, $q); }

    /* -------------------- DASHBOARD -------------------- */
    public function dashboard()
    {
        return $this->render('dashboard', 'Dashboard thống kê KPI', ['k' => ReportService::kpi(), 'rev' => ReportService::revenue12(),
            'debt' => ReportService::debtByTower()]);
    }
    public function export()
    {
        $rows = array_map(fn($r) => [$r['thap_nha'], $r['ma_can_ho'], $r['ma_hoa_don'], ky_label($r['ky_cuoc']), (int)$r['tong_tien'],
            vn_date($r['han_thanh_toan']), max(0, (int)$r['qua_han'])], ReportService::debtRows());
        return ReportService::csv('cong_no_' . app_today() . '.csv', ['Tháp', 'Căn hộ', 'Mã hóa đơn', 'Kỳ cước', 'Số tiền', 'Hạn thanh toán', 'Số ngày quá hạn'], $rows);
    }

    /* -------------------- CĂN HỘ & NHÂN KHẨU -------------------- */
    public function apartments()
    {
        $tower = input('tower', 'A');
        $floor = input('floor', '');
        $state = input('state', '');
        $q = input('q', '');
        $sql = "SELECT ch.*, (SELECT COUNT(*) FROM nhan_khau n WHERE n.ID_can_ho=ch.ID_can_ho AND n.trang_thai_duyet='da_duyet') so_nguoi,
                       (SELECT COUNT(*) FROM nhan_khau n WHERE n.ID_can_ho=ch.ID_can_ho AND n.trang_thai_duyet='cho_duyet') cho_duyet,
                       (SELECT ho_ten FROM nhan_khau n WHERE n.ID_can_ho=ch.ID_can_ho AND n.trang_thai_duyet='da_duyet'
                          AND n.loai_cu_dan IN ('chu_ho','khach_thue') ORDER BY n.loai_cu_dan='khach_thue' DESC LIMIT 1) dai_dien
                  FROM can_ho ch WHERE ch.thap_nha=?";
        $p = [$tower];
        if ($floor !== '') { $sql .= " AND ch.so_tang=?"; $p[] = (int)$floor; }
        if ($state !== '') { $sql .= " AND ch.trang_thai_o=?"; $p[] = $state; }
        if ($q !== '') {
            $sql .= " AND (ch.ma_can_ho LIKE ? OR EXISTS (SELECT 1 FROM nhan_khau n WHERE n.ID_can_ho=ch.ID_can_ho AND n.ho_ten LIKE ?))";
            $p[] = "%$q%"; $p[] = "%$q%";
        }
        $apts = Db::all($sql . " ORDER BY ch.so_tang, ch.ma_can_ho", $p);
        $byFloor = [];
        foreach ($apts as $a) $byFloor[$a['so_tang']][] = $a;
        $sel = input('apt') ? CanHo::where('ma_can_ho', input('apt'))->first() : null;
        $detail = null;
        if ($sel) {
            // Hồ sơ căn hộ lấy qua các quan hệ của Model CanHo (nhanKhau, theXe, hoaDon)
            $detail = ['apt' => $sel,
                'people' => $sel->nhanKhau()->whereIn('trang_thai_duyet', ['cho_duyet', 'da_duyet'])
                    ->orderByRaw("FIELD(loai_cu_dan,'chu_ho','khach_thue','thanh_vien')")->orderBy('trang_thai_duyet')->get()->all(),
                'vehicles' => $sel->theXe()->whereIn('trang_thai_duyet', ['cho_duyet', 'da_duyet'])->get()->all(),
                'invoice' => $sel->hoaDon()->whereIn('trang_thai_thanh_toan', ['chua_thanh_toan', 'da_thanh_toan'])->orderByDesc('ky_cuoc')->first()];
        }
        $pending = (int)Db::val("SELECT COUNT(*) FROM nhan_khau WHERE trang_thai_duyet='cho_duyet'");
        return $this->render('apartments', 'Quản lý sơ đồ căn hộ và cư dân', compact('tower', 'floor', 'state', 'q', 'byFloor', 'detail', 'pending'));
    }
    public function residentApprove()
    {
        $acc = ResidentService::approve((int)input('id'));
        flash('success', $acc ? "Đã duyệt và cấp tài khoản khách thuê: {$acc['username']} / mật khẩu tạm {$acc['password']} (bắt buộc đổi khi đăng nhập)."
            : 'Đã phê duyệt nhân khẩu.');
        return $this->back('admin.apartments', ['tower' => input('tower'), 'apt' => input('apt')]);
    }
    public function residentReject()
    {
        ResidentService::reject((int)input('id'), (string)input('reason'));
        flash('success', 'Đã từ chối hồ sơ.');
        return $this->back('admin.apartments', ['tower' => input('tower'), 'apt' => input('apt')]);
    }

    /* -------------------- THẺ XE & ACCESS CONTROL -------------------- */
    public function vehicles()
    {
        $list = Db::all("SELECT x.*, ch.ma_can_ho, nk.ho_ten,
                        (SELECT COUNT(*) FROM the_xe y WHERE y.ID_can_ho=x.ID_can_ho AND y.loai_xe='o_to' AND y.trang_thai_duyet='da_duyet') oto_da_duyet,
                        (SELECT COUNT(*) FROM the_xe y WHERE y.ID_can_ho=x.ID_can_ho AND y.loai_xe='xe_may' AND y.trang_thai_duyet='da_duyet') xm_da_duyet
                      FROM the_xe x JOIN can_ho ch ON ch.ID_can_ho=x.ID_can_ho JOIN nhan_khau nk ON nk.ID_nhan_khau=x.ID_nhan_khau
                     WHERE x.trang_thai_duyet='cho_duyet' ORDER BY x.ngay_gui");
        $total = (int)Db::val("SELECT COUNT(*) FROM the_xe WHERE trang_thai_duyet='da_duyet'");
        $cards = Db::all("SELECT t.*, ch.ma_can_ho, nk.ho_ten FROM the_ra_vao t JOIN nhan_khau nk ON nk.ID_nhan_khau=t.ID_nhan_khau
                           JOIN can_ho ch ON ch.ID_can_ho=nk.ID_can_ho WHERE t.trang_thai_the<>'da_huy'
                           ORDER BY t.trang_thai_the='kich_hoat', t.ID_the DESC LIMIT 60");
        $nCards = (int)Db::val("SELECT COUNT(*) FROM the_ra_vao WHERE trang_thai_the<>'da_huy'");
        return $this->render('vehicles', 'Duyệt thẻ xe và Access Control', compact('list', 'total', 'cards', 'nCards'));
    }
    public function vehicleApprove()
    {
        VehicleService::approve((int)input('id'));
        flash('success', 'Đã duyệt thẻ xe. Phí gửi xe được tính từ kỳ hóa đơn tiếp theo.');
        return $this->back('admin.vehicles');
    }
    public function vehicleDecline()
    {
        VehicleService::decline((int)input('id'), (string)input('state'), (string)input('reason'));
        flash('success', input('state') === 'tu_choi' ? 'Đã từ chối hồ sơ.' : 'Đã yêu cầu cư dân bổ sung hồ sơ.');
        return $this->back('admin.vehicles');
    }
    public function cardIssue()
    {
        CardService::issue((string)input('ma_can_ho'), (string)input('loai_the'), (string)input('rfid'), (string)input('van_tay'));
        flash('success', 'Đã cấp mới thẻ.');
        return $this->back('admin.vehicles');
    }
    public function cardState()
    {
        CardService::setState((int)input('id'), input('state') === 'kich_hoat' ? 'kich_hoat' : 'tam_khoa');
        flash('success', 'Đã cập nhật trạng thái thẻ.');
        return $this->back('admin.vehicles');
    }

    /* -------------------- CHỈ SỐ & HÓA ĐƠN -------------------- */
    public function billing()
    {
        $periods = [];
        for ($i = 0; $i < 6; $i++) $periods[] = date('Y-m', strtotime(substr(app_today(), 0, 7) . "-01 -$i month"));
        $ky = input('ky', $periods[1]);
        $tower = input('tower', 'A');
        $tab = input('tab', Rbac::is('ke_toan_truong') ? 'duyet' : 'nhap');
        $data = compact('ky', 'tower', 'tab', 'periods');
        if ($tab === 'nhap') $data['rows'] = BillingService::sheet($tower, $ky);
        if ($tab === 'duyet') {
            $data['pending'] = Db::all("SELECT ch.thap_nha, h.ky_cuoc, COUNT(*) n, SUM(h.tong_tien) s, MIN(tk.ho_ten) nguoi_lap
                       FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho JOIN tai_khoan tk ON tk.ID_tai_khoan=h.ID_nguoi_lap
                      WHERE h.trang_thai_thanh_toan='cho_duyet' GROUP BY ch.thap_nha, h.ky_cuoc ORDER BY h.ky_cuoc, ch.thap_nha");
            $data['invoices'] = Db::all("SELECT h.*, ch.ma_can_ho FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
                       WHERE ch.thap_nha=? AND h.ky_cuoc=? ORDER BY ch.ma_can_ho", [$tower, $ky]);
        }
        if ($tab === 'bieu_phi') $data['fees'] = FeeService::all();
        return $this->render('billing', 'Chỉ số điện nước và hóa đơn', $data);
    }
    public function readingsSave()
    {
        $r = BillingService::saveReadings((string)input('ky'), request()->input('cs', []));
        if ($r['saved']) flash('success', "Đã lưu chỉ số {$r['saved']} căn hộ.");
        foreach ($r['errors'] as $err) flash('error', $err);
        session()->put('reading_errors', $r['errors']);
        session()->put('reading_input', request()->input('cs', []));
        return $this->back('admin.billing', ['tab' => 'nhap', 'ky' => input('ky'), 'tower' => input('tower')]);
    }
    public function invoicesIssue()
    {
        $r = BillingService::issueBatch((string)input('tower'), (string)input('ky'));
        flash($r['created'] ? 'success' : 'error', "Đã lập {$r['created']} hóa đơn ở trạng thái Chờ duyệt, chờ Kế toán trưởng phê duyệt phát hành.");
        foreach (array_slice($r['skipped'], 0, 8) as $s) flash('error', $s);
        return $this->back('admin.billing', ['tab' => 'nhap', 'ky' => input('ky'), 'tower' => input('tower')]);
    }
    public function invoicesApprove()
    {
        $n = BillingService::approveBatch((string)input('tower'), (string)input('ky'));
        flash('success', "Đã phê duyệt phát hành $n hóa đơn. Chỉ số kỳ " . ky_label((string)input('ky')) . " đã khóa sổ.");
        return $this->back('admin.billing', ['tab' => 'duyet', 'ky' => input('ky'), 'tower' => input('tower')]);
    }
    public function invoiceCancel()
    {
        BillingService::cancel((int)input('id'), (string)input('reason'));
        flash('success', 'Đã hủy hóa đơn. Chỉ số kỳ này đã được mở khóa để Kế toán điều chỉnh và lập lại.');
        return $this->back('admin.billing', ['tab' => 'duyet', 'ky' => input('ky'), 'tower' => input('tower')]);
    }
    public function feesSave()
    {
        FeeService::update(request()->input('fee', []));
        flash('success', 'Đã cập nhật biểu phí. Đơn giá mới áp dụng cho các hóa đơn lập sau thời điểm này.');
        return $this->back('admin.billing', ['tab' => 'bieu_phi']);
    }

    /* -------------------- ĐỐI SOÁT GIAO DỊCH -------------------- */
    private function paymentRows(int $limit = 200): array
    {
        $sql = "SELECT g.*, ch.ma_can_ho, h.ma_hoa_don FROM giao_dich_thanh_toan g JOIN hoa_don h ON h.ID_hoa_don=g.ID_hoa_don
                  JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho WHERE 1";
        $p = [];
        if (input('method')) { $sql .= " AND g.hinh_thuc=?"; $p[] = input('method'); }
        if (input('status')) { $sql .= " AND g.trang_thai=?"; $p[] = input('status'); }
        return Db::all($sql . " ORDER BY g.ngay_giao_dich DESC LIMIT $limit", $p);
    }
    public function payments()
    {
        return $this->render('payments', 'Đối soát giao dịch', ['rows' => $this->paymentRows(),
            'exceptions' => (int)Db::val("SELECT COUNT(*) FROM giao_dich_thanh_toan WHERE trang_thai='ngoai_le'")]);
    }
    public function unpaid()
    {
        return response()->json(Db::all("SELECT h.ID_hoa_don, h.ma_hoa_don, h.ky_cuoc, h.tong_tien FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
                           WHERE ch.ma_can_ho=? AND h.trang_thai_thanh_toan='chua_thanh_toan' ORDER BY h.ky_cuoc", [strtoupper((string)input('apt'))]));
    }
    public function paymentManual()
    {
        PaymentService::manual((int)input('invoice'), (string)input('method'), (float)str_replace('.', '', (string)input('amount')),
            (string)input('payer'), (string)input('voucher'));
        flash('success', 'Đã gạch nợ thủ công và lưu vết chứng từ.');
        return $this->back('admin.payments');
    }
    public function paymentsExport()
    {
        $rows = array_map(fn($g) => [$g['ma_giao_dich_ref'], $g['ma_can_ho'], $g['ma_hoa_don'], lbl('hinh_thuc', $g['hinh_thuc']), (int)$g['so_tien'],
            $g['ma_ngan_hang'], $g['ma_phan_hoi'], $g['ngay_giao_dich'], lbl('gd', $g['trang_thai']), $g['so_chung_tu'], $g['ghi_chu']], $this->paymentRows(100000));
        return ReportService::csv('nhat_ky_doi_soat_' . app_today() . '.csv', ['Mã GD', 'Căn hộ', 'Hóa đơn', 'Hình thức', 'Số tiền', 'Ngân hàng',
            'Mã phản hồi', 'Thời gian', 'Trạng thái', 'Chứng từ', 'Ghi chú'], $rows);
    }

    /* -------------------- LỊCH TIỆN ÍCH -------------------- */
    public function facilities()
    {
        $date = input('date', app_today());
        $fs = BookingService::facilities();
        $grid = [];
        foreach ($fs as $f) $grid[$f['ID_tien_ich']] = BookingService::slots((int)$f['ID_tien_ich'], $date);
        $recent = Db::all("SELECT d.*, t.ten_tien_ich, ch.ma_can_ho, nk.ho_ten FROM dat_tien_ich d JOIN dich_vu_tien_ich t ON t.ID_tien_ich=d.ID_tien_ich
                            LEFT JOIN can_ho ch ON ch.ID_can_ho=d.ID_can_ho LEFT JOIN nhan_khau nk ON nk.ID_nhan_khau=d.ID_nhan_khau
                           WHERE d.loai='dat_cho' ORDER BY d.ngay_tao DESC LIMIT 30");
        return $this->render('facilities', 'Quản lý tiện ích', compact('date', 'fs', 'grid', 'recent') + ['verified' => session()->pull('verified')]);
    }
    public function ticketVerify()
    {
        $b = BookingService::verify((string)input('code'));
        session()->put('verified', $b);
        flash('success', "Vé hợp lệ: {$b['ten_tien_ich']} – căn hộ {$b['ma_can_ho']} – " . substr($b['khung_gio_bat_dau'], 0, 5) . '. Đã ghi nhận sử dụng.');
        return $this->back('admin.facilities');
    }
    public function maintenance()
    {
        $n = BookingService::maintenance((int)input('f'), (string)input('date'), (string)input('start'), (string)input('end'));
        flash('success', "Đã khóa lịch bảo trì. Tự động hủy $n lịch đặt trùng và ghi nhận thông báo cho cư dân.");
        return $this->back('admin.facilities', ['date' => input('date')]);
    }

    /* -------------------- SỰ CỐ KANBAN -------------------- */
    public function tickets()
    {
        $where = Rbac::is('ky_thuat_vien') ? 'AND p.ID_ky_thuat_vien=' . (int)Rbac::id() : '';
        $rows = Db::all("SELECT p.*, ch.ma_can_ho, nk.ho_ten nguoi_gui, nk.so_dien_thoai sdt_gui, tk.ho_ten ktv
                           FROM phieu_su_co p JOIN can_ho ch ON ch.ID_can_ho=p.ID_can_ho JOIN nhan_khau nk ON nk.ID_nhan_khau=p.ID_nguoi_gui
                           LEFT JOIN tai_khoan tk ON tk.ID_tai_khoan=p.ID_ky_thuat_vien
                          WHERE p.trang_thai_kanban NOT IN ('tu_choi','da_huy') $where
                            AND (p.trang_thai_kanban<>'hoan_tat' OR p.ngay_hoan_thanh > ? - INTERVAL 30 DAY)
                          ORDER BY p.muc_do_uu_tien='khan_cap' DESC, p.ngay_tao DESC", [app_today()]);
        $cols = [];
        foreach (['cho_tiep_nhan', 'da_phan_cong', 'dang_xu_ly', 'cho_linh_kien', 'cho_nghiem_thu', 'hoan_tat'] as $c) $cols[$c] = [];
        foreach ($rows as $r) $cols[$r['trang_thai_kanban']][] = $r;
        // Kỹ thuật viên đang hoạt động kèm số phiếu đang xử lý (quan hệ tai_khoan → phieu_su_co.ID_ky_thuat_vien)
        $techs = TaiKhoan::where('vai_tro', 'ky_thuat_vien')->where('trang_thai', 1)
            ->withCount(['phieuDuocGiao as dang_lam' => fn($q) => $q->whereIn('trang_thai_kanban', ['da_phan_cong', 'dang_xu_ly', 'cho_linh_kien'])])
            ->get(['ID_tai_khoan', 'ho_ten'])->all();
        return $this->render('tickets', 'Phân công sự cố', compact('cols', 'techs'));
    }
    public function ticketAssign()
    {
        TicketService::assign((int)input('id'), (int)input('tech'));
        flash('success', 'Đã phân công kỹ thuật viên.');
        return $this->back('admin.tickets');
    }
    public function ticketReject()
    {
        TicketService::reject((int)input('id'), (string)input('reason'));
        flash('success', 'Đã từ chối phiếu.');
        return $this->back('admin.tickets');
    }
    public function ticketMove()
    {
        TicketService::move((int)input('id'), (string)input('to'), (string)input('note', ''));
        return response()->json(['ok' => true]);
    }
    public function ticketAccept()
    {
        TicketService::submitAcceptance((int)input('id'), save_upload('anh_nghiem_thu', 'nghiem_thu', false),
            (string)input('vat_tu'), (string)input('ghi_chu'));
        flash('success', 'Đã gửi đề xuất nghiệm thu tới cư dân.');
        return $this->back('admin.tickets');
    }

    /* -------------------- PHÂN QUYỀN & DỮ LIỆU AI -------------------- */
    public function system()
    {
        $tab = input('tab', 'accounts');
        $staff = TaiKhoan::whereIn('vai_tro', Rbac::STAFF)
            ->orderByRaw("FIELD(vai_tro,'admin','ke_toan','ke_toan_truong','le_tan','dieu_phoi_vien','ky_thuat_vien')")->orderBy('ID_tai_khoan')->get()->all();
        $docs = NguCanhAi::with('nguoiCapNhat')->orderBy('ID_ngu_canh')->get()
            ->map(fn($n) => $n->getAttributes() + ['ho_ten' => $n->nguoiCapNhat->ho_ten])->all();
        $edit = input('edit') ? NguCanhAi::find((int)input('edit')) : null;
        return $this->render('system', 'Phân quyền và dữ liệu AI', compact('tab', 'staff', 'docs', 'edit') + ['created' => session()->pull('created')]);
    }
    public function accountCreate()
    {
        session()->put('created', AccountService::createStaff((string)input('ho_ten'), (string)input('email'), (string)input('sdt'), (string)input('vai_tro')));
        flash('success', 'Đã cấp tài khoản mới.');
        return $this->back('admin.system');
    }
    public function accountLock()
    {
        flash('success', AccountService::toggleLock((int)input('id')));
        return $this->back('admin.system');
    }
    public function aiSave()
    {
        $content = (string)input('noi_dung');
        $file = request()->file('tep');
        if ($file && $file->isValid()) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['txt', 'md'], true)) throw new BusinessException('Chỉ hỗ trợ tệp .txt hoặc .md.');
            $content = trim(file_get_contents($file->getRealPath())) ?: $content;
        }
        AiService::saveDoc(input('id') ? (int)input('id') : null, (string)input('tieu_de'), (string)input('loai'), $content);
        flash('success', 'Đã lưu tài liệu ở trạng thái Nháp. Bấm "Đóng gói và cập nhật ngữ cảnh" để đưa vào sử dụng.');
        return $this->back('admin.system', ['tab' => 'ai']);
    }
    public function aiDelete()
    {
        NguCanhAi::destroy((int)input('id'));
        AuditService::log('xoa_ngu_canh', 'ngu_canh_ai', (int)input('id'));
        flash('success', 'Đã xóa tài liệu.');
        return $this->back('admin.system', ['tab' => 'ai']);
    }
    public function aiPublish()
    {
        flash('success', 'Đã cập nhật ' . AiService::publish() . ' tài liệu vào ngữ cảnh của Trợ lý AI.');
        return $this->back('admin.system', ['tab' => 'ai']);
    }
}
