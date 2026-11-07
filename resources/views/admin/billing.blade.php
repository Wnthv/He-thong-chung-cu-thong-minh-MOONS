@extends('layouts.admin')

@section('content')
<div class="tabs2">
  @foreach (['nhap' => 'Nhập chỉ số & lập hóa đơn', 'duyet' => 'Duyệt phát hành & hủy', 'bieu_phi' => 'Biểu phí'] as $k => $v)
    <a class="{!! $tab === $k ? 'on' : '' !!}" href="{!! route('admin.billing', ['tab' => $k, 'ky' => $ky, 'tower' => $tower]) !!}">{!! $v !!}</a>
  @endforeach
</div>
@if ($tab !== 'bieu_phi')
<div class="panel"><form class="filters" method="get" action="{{ route('admin.billing') }}"><input type="hidden" name="tab" value="{{ $tab }}">
  <b>Kỳ cước:</b><select class="inp" name="ky" onchange="this.form.submit()">@foreach ($periods as $p)<option value="{!! $p !!}" {!! $p === $ky ? 'selected' : '' !!}>{!! ky_label($p) !!}</option>@endforeach</select>
  <select class="inp" name="tower" onchange="this.form.submit()">@foreach (['A', 'B', 'C', 'D'] as $t)<option {!! $tower === $t ? 'selected' : '' !!} value="{!! $t !!}">Tháp {!! $t !!}</option>@endforeach</select>
  @if ($tab === 'nhap' && \App\Support\Rbac::is('ke_toan'))
    <span class="grow"></span>
    <button form="readForm" class="btn ghost">{!! icon('check') !!} Lưu chỉ số</button>
    <button form="issueForm" class="btn" onclick="return confirm('Lập hóa đơn cho các căn hộ đã có chỉ số hợp lệ? Hóa đơn sẽ ở trạng thái Chờ duyệt.')">{!! icon('send') !!} Tính phí và lập hóa đơn hàng loạt</button>
  @endif
</form></div>
@endif

@if ($tab === 'nhap') @php $errs = session()->pull('reading_errors', []); @endphp @php $old = session()->pull('reading_input', []); @endphp @php $locked = count(array_filter($rows, fn($r) => $r['da_khoa_so'])); @endphp
<form id="issueForm" method="post" action="{!! route('admin.invoices-issue') !!}">@csrf<input type="hidden" name="ky" value="{{ $ky }}"><input type="hidden" name="tower" value="{{ $tower }}"></form>
<form id="readForm" method="post" action="{!! route('admin.readings-save') !!}">@csrf<input type="hidden" name="ky" value="{{ $ky }}"><input type="hidden" name="tower" value="{{ $tower }}">
<div class="panel" style="padding:0"><div class="scroll" style="max-height:620px"><table class="t dark">
  <thead><tr><th>Căn hộ</th><th>Cư dân</th><th class="right">Điện cũ</th><th>Điện mới</th><th class="right">Nước cũ</th><th>Nước mới</th><th>Cảnh báo / Hóa đơn</th></tr></thead>
  @foreach ($rows as $r) @php $id = $r['ID_can_ho']; @endphp @php $lock = (bool)$r['da_khoa_so']; @endphp @php $vd = $old[$id]['dien'] ?? $r['chi_so_dien_moi']; @endphp @php $vw = $old[$id]['nuoc'] ?? $r['chi_so_nuoc_moi']; @endphp
    <tr data-reading data-old-d="{!! (int)$r['old_d'] !!}" data-old-w="{!! (int)$r['old_w'] !!}" data-avg-d="{!! $r['avg'] ? round($r['avg']['d'], 1) : 0 !!}" data-avg-w="{!! $r['avg'] ? round($r['avg']['w'], 1) : 0 !!}" class="{!! isset($errs[$id]) ? 'err' : '' !!}">
      <td class="b">{{ $r['ma_can_ho'] }}</td><td>{{ $r['cu_dan'] }}</td>
      <td class="right">{!! num($r['old_d']) !!}<div class="xs muted">kỳ trước: {!! $r['prev_use_d'] !== null ? num($r['prev_use_d']) . ' kWh' : '—' !!}</div></td>
      <td><input class="inp mono" style="width:96px;text-align:center" data-k="dien" name="cs[{!! $id !!}][dien]" value="{{ $vd }}" inputmode="numeric" {!! $lock || !\App\Support\Rbac::is('ke_toan') ? 'readonly' : '' !!}></td>
      <td class="right">{!! num($r['old_w']) !!}<div class="xs muted">kỳ trước: {!! $r['prev_use_w'] !== null ? num($r['prev_use_w']) . ' m³' : '—' !!}</div></td>
      <td><input class="inp mono" style="width:86px;text-align:center" data-k="nuoc" name="cs[{!! $id !!}][nuoc]" value="{{ $vw }}" inputmode="numeric" {!! $lock || !\App\Support\Rbac::is('ke_toan') ? 'readonly' : '' !!}></td>
      <td style="min-width:240px"><div class="warn small b" style="color:var(--red-ink)"></div>
        <input class="inp small hidden mt4" data-k="ghi_chu" name="cs[{!! $id !!}][ghi_chu]" value="{{ $old[$id]['ghi_chu'] ?? $r['ghi_chu_xac_minh'] }}" placeholder="Ghi chú xác nhận đã kiểm tra công tơ">
        @if (isset($errs[$id]))<div class="xs" style="color:var(--red-ink)">{{ $errs[$id] }}</div>@endif
        @if ($r['ma_hoa_don'])<span class="badge {!! ['cho_duyet' => 'bg-amber', 'chua_thanh_toan' => 'bg-red', 'da_thanh_toan' => 'bg-green'][$r['trang_thai_thanh_toan']] !!}">{{ lbl('hd', $r['trang_thai_thanh_toan']) }} · {!! money($r['tong_tien']) !!}</span>@endif
        @if ($lock)<span class="badge bg-grey">{!! icon('lock') !!} Khóa sổ</span>@endif</td></tr>
  @endforeach
</table></div></div></form>
<p class="note">{!! count($rows) !!} căn hộ đang có người ở · {!! $locked !!} căn đã khóa sổ kỳ này. Chỉ số cũ lấy tự động từ chỉ số mới kỳ trước. Dòng tô đỏ: chỉ số mới nhỏ hơn chỉ số cũ hoặc tiêu thụ lớn hơn 3 lần trung bình 3 kỳ liền trước (bắt buộc ghi chú xác minh mới lập được hóa đơn).</p>

@elseif ($tab === 'duyet')
<div class="panel"><div class="panel-h"><h2>Lô hóa đơn chờ phê duyệt phát hành</h2></div>
  <table class="t"><tr><th>Kỳ cước</th><th>Tháp</th><th class="right">Số hóa đơn</th><th class="right">Tổng tiền</th><th>Người lập</th><th></th></tr>
  @foreach ($pending as $p)<tr><td>{!! ky_label($p['ky_cuoc']) !!}</td><td>Tháp {!! $p['thap_nha'] !!}</td><td class="right">{!! $p['n'] !!}</td><td class="right b">{!! money($p['s']) !!}</td><td>{{ $p['nguoi_lap'] }}</td>
    <td class="right">@if (\App\Support\Rbac::is('ke_toan_truong'))<form method="post" action="{!! route('admin.invoices-approve') !!}" onsubmit="return confirm('Phê duyệt phát hành và khóa sổ chỉ số kỳ này?')">@csrf<input type="hidden" name="ky" value="{!! $p['ky_cuoc'] !!}"><input type="hidden" name="tower" value="{!! $p['thap_nha'] !!}"><button class="btn success sm">Phê duyệt phát hành</button></form>@endif</td></tr>@endforeach
  @if (!$pending)<tr><td colspan="6" class="muted center">Không có lô hóa đơn chờ duyệt.</td></tr>@endif</table></div>
<div class="panel"><div class="panel-h"><h2>Hóa đơn {!! ky_label($ky) !!} – Tháp {{ $tower }} ({!! count($invoices) !!})</h2></div>
  <div class="scroll" style="max-height:480px"><table class="t"><thead><tr><th>Mã hóa đơn</th><th>Căn hộ</th><th class="right">Tổng tiền</th><th>Trạng thái</th><th>Hạn TT</th><th>Lý do hủy</th><th></th></tr></thead>
  @foreach ($invoices as $h) @php $st = $h['trang_thai_thanh_toan']; @endphp<tr><td class="mono small">{{ $h['ma_hoa_don'] }}</td><td class="apt-code">{{ $h['ma_can_ho'] }}</td><td class="right b">{!! money($h['tong_tien']) !!}</td>
    <td><span class="badge {!! ['cho_duyet' => 'bg-amber', 'chua_thanh_toan' => 'bg-red', 'da_thanh_toan' => 'bg-green', 'da_huy' => 'bg-grey'][$st] !!}">{{ lbl('hd', $st) }}</span></td>
    <td>{!! vn_date($h['han_thanh_toan']) !!}</td><td class="small muted">{{ $h['ly_do_huy'] }}</td>
    <td>@if (\App\Support\Rbac::is('ke_toan_truong') && in_array($st, ['cho_duyet', 'chua_thanh_toan']))<button class="btn danger sm" data-modal="cancelModal" data-fill='{"id":"{!! $h['ID_hoa_don'] !!}","code":"{{ $h['ma_hoa_don'] }}"}'>Hủy</button>@endif</td></tr>@endforeach
  </table></div></div>
<div class="modal center" id="cancelModal"><div class="sheet"><div class="sheet-h"><h3>Hủy hóa đơn <span data-f="code"></span></h3><button class="x" data-close>{!! icon('x') !!}</button></div>
  <form method="post" action="{!! route('admin.invoice-cancel') !!}">@csrf<input type="hidden" name="id" data-f="id"><input type="hidden" name="ky" value="{{ $ky }}"><input type="hidden" name="tower" value="{{ $tower }}">
    <label class="lb">Lý do hủy <small>(tối thiểu 20 ký tự, ghi vào nhật ký không thể xóa)</small></label><textarea class="inp" name="reason" required minlength="20"></textarea>
    <p class="small muted">Sau khi hủy, chỉ số kỳ này được mở khóa để Kế toán điều chỉnh và lập hóa đơn thay thế (mã có hậu tố -R).</p>
    <button class="btn danger block">Xác nhận hủy</button></form></div></div>

@else
<div class="panel" style="max-width:760px"><div class="panel-h"><h2>Biểu phí dịch vụ</h2>@if (!\App\Support\Rbac::is('ke_toan_truong'))<span class="badge bg-grey">Chỉ xem – Kế toán trưởng phê duyệt thay đổi</span>@endif</div>
  <form method="post" action="{!! route('admin.fees-save') !!}">@csrf
  <table class="t"><tr><th>Mã</th><th>Khoản phí</th><th>Phạm vi</th><th class="right">Đơn giá (đ)</th><th>Đơn vị</th><th>Cập nhật</th></tr>
  @foreach ($fees as $f)<tr><td class="mono small">{{ $f['ma_phi'] }}</td><td>{{ $f['ten_phi'] }}</td>
    <td class="small">{!! $f['tu_muc'] !== null ? $f['tu_muc'] . ($f['den_muc'] ? '–' . $f['den_muc'] : '+') : '' !!}</td>
    <td class="right"><input class="inp mono right" style="width:130px" name="fee[{{ $f['ma_phi'] }}]" value="{!! num($f['don_gia']) !!}" {!! \App\Support\Rbac::is('ke_toan_truong') ? '' : 'readonly' !!}></td>
    <td>{{ $f['don_vi'] }}</td><td class="small muted">{!! vn_date($f['ngay_cap_nhat']) !!}</td></tr>@endforeach</table>
  @if (\App\Support\Rbac::is('ke_toan_truong'))<button class="btn mt12">Lưu biểu phí</button>@endif</form>
  <p class="note">Đơn giá được chụp vào từng dòng chi tiết hóa đơn tại thời điểm lập, nên thay đổi biểu phí không làm sai các hóa đơn đã lập.</p></div>
@endif
@endsection
