<?php

namespace App\Http\Controllers;

use App\Models\ChiSoDienNuoc;
use App\Models\ChiTietHoaDon;
use App\Models\DatTienIch;
use App\Models\DichVuTienIch;
use App\Models\NhanKhau;
use App\Models\TheRaVao;
use App\Models\TheXe;
use App\Services\AiService;
use App\Services\BillingService;
use App\Services\BookingService;
use App\Services\CardService;
use App\Services\ResidentService;
use App\Services\TicketService;
use App\Services\VehicleService;
use App\Support\Db;
use App\Support\Rbac;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/** Cổng thông tin Cư dân (Chủ hộ / Khách thuê) – UC-RES01..09 */
class ResidentController extends Controller
{
    /** Hồ sơ cư dân mới nhất (làm mới mỗi request để phản ánh thay đổi trạng thái) */
    public static function me(): array
    {
        $me = Db::one("SELECT nk.*, ch.ma_can_ho, ch.dien_tich, ch.thap_nha, ch.trang_thai_o, tk.vai_tro, tk.ID_tai_khoan
                         FROM nhan_khau nk JOIN can_ho ch ON ch.ID_can_ho=nk.ID_can_ho JOIN tai_khoan tk ON tk.ID_tai_khoan=nk.ID_tai_khoan
                        WHERE nk.ID_tai_khoan=?", [Rbac::id()]);
        if (!$me || $me['trang_thai_duyet'] !== 'da_duyet') {
            Rbac::logout();
            throw new HttpResponseException(to_route('auth.login'));
        }
        return $me;
    }

    /** Hiển thị view cư dân kèm số thông báo trên chuông */
    private function render(string $view, array $data = [])
    {
        $me = $data['me'] ?? self::me();
        $unpaid = array_filter(BillingService::visibleInvoices($me), fn($h) => $h['trang_thai_thanh_toan'] === 'chua_thanh_toan');
        $pendingAccept = (int) Db::val("SELECT COUNT(*) FROM phieu_su_co WHERE ID_can_ho=? AND trang_thai_kanban='cho_nghiem_thu'", [$me['ID_can_ho']]);
        return view("resident.$view", $data + ['me' => $me, 'bell' => count($unpaid) + $pendingAccept]);
    }

    public function home()
    {
        $me = self::me();
        $inv = BillingService::visibleInvoices($me);
        $cards = Db::all("SELECT t.* FROM the_ra_vao t JOIN nhan_khau nk ON nk.ID_nhan_khau=t.ID_nhan_khau
                           WHERE nk.ID_can_ho=? AND t.trang_thai_the='kich_hoat' ORDER BY t.loai_the", [$me['ID_can_ho']]);
        $count = (int) Db::val("SELECT COUNT(*) FROM nhan_khau WHERE ID_can_ho=? AND trang_thai_duyet='da_duyet'
                                AND (loai_cu_dan<>'chu_ho' OR ?<>'cho_thue')", [$me['ID_can_ho'], $me['trang_thai_o']]);
        return $this->render('home', ['me' => $me, 'latest' => $inv[0] ?? null, 'cards' => $cards, 'count' => $count,
            'blocked' => BillingService::overdue30((int) $me['ID_can_ho'])]);
    }

    /* -------------------- NHÂN KHẨU & THẺ -------------------- */
    public function residents()
    {
        $me = self::me();
        $members = NhanKhau::where('ID_can_ho', $me['ID_can_ho'])->whereIn('trang_thai_duyet', ['cho_duyet', 'da_duyet', 'tu_choi'])
            ->orderByRaw("FIELD(loai_cu_dan,'chu_ho','khach_thue','thanh_vien')")->orderBy('ID_nhan_khau')->get()->all();
        // Thẻ của mọi nhân khẩu trong căn hộ (quan hệ the_ra_vao → nhan_khau), kèm họ tên chủ thẻ
        $cards = TheRaVao::with('nhanKhau')->whereHas('nhanKhau', fn($q) => $q->where('ID_can_ho', $me['ID_can_ho']))
            ->where('trang_thai_the', '<>', 'da_huy')->orderBy('ID_the')->get()
            ->map(fn($t) => $t->getAttributes() + ['ho_ten' => $t->nhanKhau->ho_ten])->all();
        return $this->render('residents', ['me' => $me, 'members' => $members, 'cards' => $cards, 'tab' => input('tab', 'members')]);
    }

    public function declare(Request $request)
    {
        $me = self::me();
        ResidentService::declare($me, $request->all(), save_upload('anh_giay_to', 'giay_to', false));
        flash('success', 'Đã gửi khai báo. Lễ tân sẽ xác minh và phê duyệt.');
        return to_route('residents');
    }

    public function removeMember()
    {
        ResidentService::remove(self::me(), (int) input('id'));
        flash('success', 'Đã cập nhật thành viên chuyển đi.');
        return to_route('residents');
    }

    public function reportLost()
    {
        $me = self::me();
        CardService::setState((int) input('id'), 'bao_mat', (int) $me['ID_can_ho']);
        flash('success', 'Đã khóa thẻ báo mất. Vui lòng liên hệ Lễ tân để cấp lại.');
        return to_route('residents', ['tab' => 'cards']);
    }

    /* -------------------- THẺ XE -------------------- */
    public function vehicles()
    {
        $me = self::me();
        $list = TheXe::where('ID_can_ho', $me['ID_can_ho'])->where('trang_thai_duyet', '<>', 'da_huy')->orderByDesc('ID_the_xe')->get()->all();
        $used = ['o_to' => VehicleService::used((int) $me['ID_can_ho'], 'o_to'), 'xe_may' => VehicleService::used((int) $me['ID_can_ho'], 'xe_may')];
        return $this->render('vehicles', ['me' => $me, 'list' => $list, 'used' => $used]);
    }

    public function vehicleRegister(Request $request)
    {
        $me = self::me();
        VehicleService::register($me, $request->all(), save_upload('anh_ca_vet_xe', 'ca_vet'));
        flash('success', 'Đã gửi đơn đăng ký thẻ xe. Lễ tân sẽ xét duyệt.');
        return to_route('vehicles');
    }

    public function vehicleResubmit()
    {
        VehicleService::resubmit(self::me(), (int) input('id'), save_upload('anh_ca_vet_xe', 'ca_vet', false));
        flash('success', 'Đã gửi lại hồ sơ bổ sung.');
        return to_route('vehicles');
    }

    public function vehicleCancel()
    {
        VehicleService::cancel(self::me(), (int) input('id'));
        flash('success', 'Đã hủy thẻ xe.');
        return to_route('vehicles');
    }

    /* -------------------- HÓA ĐƠN -------------------- */
    public function invoices()
    {
        $me = self::me();
        $list = BillingService::visibleInvoices($me);
        $cur = null;
        foreach ($list as $h) if ((string) $h['ID_hoa_don'] === (string) input('id')) $cur = $h;
        $cur = $cur ?? ($list[0] ?? null);
        $lines = $cur ? ChiTietHoaDon::where('ID_hoa_don', $cur['ID_hoa_don'])
            ->orderByRaw("FIELD(loai_cuoc,'dien','nuoc','quan_ly','gui_xe_o_to','gui_xe_may')")->orderBy('ID_chi_tiet')->get()->all() : [];
        $reading = $cur ? ChiSoDienNuoc::where('ID_can_ho', $me['ID_can_ho'])->where('ky_cuoc', $cur['ky_cuoc'])->first() : null;
        return $this->render('invoices', ['me' => $me, 'list' => $list, 'cur' => $cur, 'lines' => $lines, 'reading' => $reading]);
    }

    /* -------------------- TIỆN ÍCH -------------------- */
    public function facilities()
    {
        $me = self::me();
        $fs = BookingService::facilities();
        $fid = (int) input('f', $fs[0]['ID_tien_ich']);
        $date = input('date', app_today());
        $f = DichVuTienIch::findOrFail($fid)->getAttributes();
        $mine = Db::all("SELECT d.*, t.ten_tien_ich FROM dat_tien_ich d JOIN dich_vu_tien_ich t ON t.ID_tien_ich=d.ID_tien_ich
                          WHERE d.ID_can_ho=? AND d.loai='dat_cho' AND d.ngay_dat>=? AND d.trang_thai_dat IN ('da_xac_nhan')
                          ORDER BY d.ngay_dat, d.khung_gio_bat_dau", [$me['ID_can_ho'], app_today()]);
        $newTicket = session()->pull('new_ticket');
        $b = $newTicket ? DatTienIch::with('tienIch')->find($newTicket) : null;
        $newBooking = $b ? $b->getAttributes() + ['ten_tien_ich' => $b->tienIch->ten_tien_ich] : null;
        return $this->render('facilities', ['me' => $me, 'fs' => $fs, 'f' => $f, 'date' => $date, 'slots' => BookingService::slots($fid, $date),
            'mine' => $mine, 'blocked' => BillingService::overdue30((int) $me['ID_can_ho']), 'newTicket' => $newTicket, 'newBooking' => $newBooking]);
    }

    public function book()
    {
        $me = self::me();
        $r = BookingService::book($me, (int) input('f'), (string) input('date'), (string) input('start'), (int) input('so_nguoi'));
        session()->put('new_ticket', $r['id']);
        flash('success', 'Đặt tiện ích thành công.');
        return to_route('facilities', ['f' => input('f'), 'date' => input('date')]);
    }

    public function bookingCancel()
    {
        flash('success', BookingService::cancel(self::me(), (int) input('id')));
        return to_route('facilities');
    }

    /* -------------------- BÁO HỎNG / SỰ CỐ -------------------- */
    public function tickets()
    {
        $me = self::me();
        $list = Db::all("SELECT p.*, tk.ho_ten ktv_ten, tk.so_dien_thoai ktv_sdt FROM phieu_su_co p
                          LEFT JOIN tai_khoan tk ON tk.ID_tai_khoan=p.ID_ky_thuat_vien
                          WHERE p.ID_can_ho=? ORDER BY p.trang_thai_kanban IN ('hoan_tat','tu_choi','da_huy'), p.ngay_tao DESC", [$me['ID_can_ho']]);
        return $this->render('tickets', ['me' => $me, 'list' => $list]);
    }

    public function ticketCreate(Request $request)
    {
        $me = self::me();
        TicketService::create($me, $request->all(), save_upload('anh_hien_truong', 'su_co'));
        flash('success', 'Đã gửi phiếu báo hỏng. Bạn có thể theo dõi tiến độ bên dưới.');
        return to_route('tickets');
    }

    public function ticketConfirm()
    {
        TicketService::confirm(self::me(), (int) input('id'), (int) input('stars'));
        flash('success', 'Cảm ơn bạn đã nghiệm thu và đánh giá.');
        return to_route('tickets');
    }

    public function ticketReopen()
    {
        TicketService::reopen(self::me(), (int) input('id'), (string) input('reason'));
        flash('success', 'Đã gửi yêu cầu sửa lại cho kỹ thuật viên.');
        return to_route('tickets');
    }

    public function ticketCancel()
    {
        TicketService::cancel(self::me(), (int) input('id'));
        flash('success', 'Đã hủy phiếu.');
        return to_route('tickets');
    }

    /* -------------------- TRỢ LÝ AI -------------------- */
    public function aiAsk()
    {
        $me = Rbac::isResident() ? self::me() : null;
        return response()->json(['ok' => true] + AiService::ask((string) input('q'), $me));
    }
}
