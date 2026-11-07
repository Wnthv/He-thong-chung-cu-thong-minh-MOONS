@extends('layouts.admin')

@section('content')
<div class="cols" style="grid-template-columns:2fr 1fr">
<div class="panel">
  <div class="panel-h"><h2>Nhật ký đối soát giao dịch</h2>
    <form class="filters" method="get" action="{{ route('admin.payments') }}">
      <select class="inp" name="method" onchange="this.form.submit()"><option value="">Mọi hình thức</option>@foreach (LABELS['hinh_thuc'] as $k => $v)<option value="{!! $k !!}" {!! input('method') === $k ? 'selected' : '' !!}>{!! $v !!}</option>@endforeach</select>
      <select class="inp" name="status" onchange="this.form.submit()"><option value="">Mọi trạng thái</option>@foreach (LABELS['gd'] as $k => $v)<option value="{!! $k !!}" {!! input('status') === $k ? 'selected' : '' !!}>{!! $v !!}</option>@endforeach</select>
    </form></div>
  @if ($exceptions)<div class="flash error">{!! icon('alert') !!}<span>{!! $exceptions !!} giao dịch ngoại lệ (sai chữ ký, sai số tiền hoặc hóa đơn đã thanh toán bằng hình thức khác) cần đối soát thủ công với sao kê ngân hàng.</span></div>@endif
  <div class="scroll" style="max-height:600px"><table class="t"><thead><tr><th>Mã GD</th><th>Căn hộ</th><th class="right">Số tiền</th><th>Hình thức</th><th>Ngân hàng</th><th>Mã phản hồi</th><th>Thời gian</th><th>Trạng thái</th></tr></thead>
  @foreach ($rows as $g)<tr><td class="mono small">{{ $g['ma_giao_dich_ref'] }}{!! $g['so_chung_tu'] ? '<div class="xs muted">CT: ' . e($g['so_chung_tu']) . '</div>' : '' !!}</td>
    <td class="apt-code">{{ $g['ma_can_ho'] }}</td><td class="right b">{!! money($g['so_tien']) !!}</td><td>{{ lbl('hinh_thuc', $g['hinh_thuc']) }}</td>
    <td>{{ $g['ma_ngan_hang'] ?? '—' }}</td><td class="mono">{{ $g['ma_phan_hoi'] ?? '—' }}</td><td class="small nowrap">{!! date('H:i d-m-Y', strtotime($g['ngay_giao_dich'])) !!}</td>
    <td><span class="badge {!! ['thanh_cong' => 'bg-green', 'that_bai' => 'bg-red', 'ngoai_le' => 'bg-amber', 'khoi_tao' => 'bg-grey'][$g['trang_thai']] !!}" title="{{ $g['ghi_chu'] }}">{{ lbl('gd', $g['trang_thai']) }}</span></td></tr>@endforeach
  </table></div>
</div>
<div class="panel">
  <h2 style="margin:0 0 4px;font-size:15px">Khung gạch nợ thủ công</h2>
  @if (\App\Support\Rbac::is('ke_toan'))
  <form method="post" action="{!! route('admin.payment-manual') !!}">@csrf
    <label class="lb">Mã căn hộ nộp tiền</label><input class="inp" id="manualApt" placeholder="VD: A-1001" required>
    <label class="lb">Hóa đơn chưa thanh toán</label><select class="inp" id="manualInvoice" name="invoice" required><option value="">Nhập mã căn hộ để tải</option></select>
    <label class="lb">Hình thức</label><select class="inp" name="method"><option value="tien_mat">Tiền mặt tại quầy</option><option value="chuyen_khoan">Chuyển khoản trực tiếp</option></select>
    <label class="lb">Số tiền thu</label><input class="inp right mono" id="manualAmount" name="amount" required>
    <label class="lb">Người nộp tiền</label><input class="inp" name="payer" required>
    <label class="lb">Số phiếu thu / biên lai</label><input class="inp" name="voucher" required placeholder="VD: PT-2026-1105">
    <button class="btn block mt12">Xóa nợ thủ công</button></form>
  @else<p class="small muted">Chỉ Kế toán được gạch nợ thủ công.</p>@endif
  <a class="btn ghost block mt8" href="{!! route('admin.payments-export', ['method' => input('method'), 'status' => input('status')]) !!}">{!! icon('download') !!} Xuất nhật ký đối soát</a>
  <p class="note">Số tiền thu phải bằng tổng tiền hóa đơn. CSDL chặn gạch nợ khi chưa có giao dịch thành công khớp số tiền, và chặn gạch nợ hai lần cho cùng một hóa đơn.</p>
</div></div>
@endsection
