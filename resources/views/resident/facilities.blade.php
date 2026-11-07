@extends('layouts.resident')

@section('content')
@php $icons = ['BBQ' => 'flame', 'POOL' => 'waves', 'SPORT' => 'dumbbell', 'HALL' => 'door']; @endphp
<section class="card">
  <h2 class="card-t">Chọn tiện ích nội khu</h2>
  <div class="slots" style="grid-template-columns:repeat({!! min(4, count($fs)) !!},1fr)">
    @foreach ($fs as $x)
      <a class="slot {!! $x['ID_tien_ich'] == $f['ID_tien_ich'] ? 'free sel' : '' !!}" style="text-decoration:none" href="{!! route('facilities', ['f' => $x['ID_tien_ich'], 'date' => $date]) !!}">{{ str_replace([' ngoài trời', 'Phòng sinh hoạt cộng đồng'], ['', 'Phòng SHCĐ'], $x['ten_tien_ich']) }}</a>
    @endforeach
  </div>
  <p class="xs muted">Giờ mở cửa {!! substr($f['gio_mo_cua'], 0, 5) !!} - {!! substr($f['gio_dong_cua'], 0, 5) !!} • Tối đa {!! $f['suc_chua_toi_da'] !!} người{!! $f['doc_quyen'] ? '/lượt' : '/khung giờ' !!}{!! $f['han_muc_tuan'] ? " • {$f['han_muc_tuan']} lượt/căn hộ/tuần" : '' !!}</p>
  @if ($blocked && $f['doc_quyen'])<div class="warnbox">Căn hộ có hóa đơn quá hạn trên 30 ngày nên tạm khóa quyền đặt tiện ích này.</div>@endif
  <form method="get" action="{{ route('facilities') }}"><input type="hidden" name="f" value="{!! $f['ID_tien_ich'] !!}">
    <label class="lb">Ngày sử dụng</label><input class="inp" type="date" name="date" value="{{ $date }}" min="{!! app_today() !!}" max="{!! date('Y-m-d', strtotime(app_today() . ' +30 days')) !!}" onchange="this.form.submit()"></form>
  <form method="post" action="{!! route('facilities.book') !!}">@csrf
    <input type="hidden" name="f" value="{!! $f['ID_tien_ich'] !!}"><input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="start" id="startInput">
    <label class="lb">Khung giờ <small>(xám: đã có người đặt/đã qua, xanh: còn trống)</small></label>
    <div class="slots">
      @foreach ($slots as $s) @php $free = $s['state'] === 'trong'; @endphp
        <button type="button" class="slot {!! $free ? 'free' : ($s['state'] === 'bao_tri' ? 'maint' : '') !!}" data-start="{!! $s['start'] !!}" {!! $free ? '' : 'disabled' !!}
          title="{!! ['trong' => 'Còn trống', 'da_dat' => 'Đã có người đặt', 'bao_tri' => 'Khóa bảo trì', 'het_cho' => 'Hết chỗ', 'qua_gio' => 'Đã qua'][$s['state']] !!}{!! !$f['doc_quyen'] ? " · {$s['people']}/{$f['suc_chua_toi_da']} người" : '' !!}">
          {!! substr($s['start'], 0, 5) !!} - {!! substr($s['end'], 0, 5) !!}</button>
      @endforeach
    </div>
    <label class="lb">Số người tham gia</label><input class="inp" type="number" name="so_nguoi" min="1" max="{!! $f['suc_chua_toi_da'] !!}" value="4" required>
    <p class="xs muted mt4">Tối đa {!! $f['suc_chua_toi_da'] !!} người. Hủy trước 2 giờ để không bị tính vào hạn mức tuần.</p>
    <button class="btn block mt8" id="bookBtn" disabled>Đặt lịch ngay</button>
  </form>
</section>
<section class="card">
  <h2 class="card-t">Lịch đã đặt của tôi ({!! count($mine) !!})</h2>
  @foreach ($mine as $b)
    <div class="mini row between">
      <div><b>{{ $b['ten_tien_ich'] }}</b><div class="xs muted">{!! vn_date($b['ngay_dat']) !!} • {!! substr($b['khung_gio_bat_dau'], 0, 5) !!}-{!! substr($b['khung_gio_ket_thuc'], 0, 5) !!} • {!! $b['so_nguoi'] !!} người</div></div>
      <div class="row">
        <button class="link small" data-modal="qrModal" data-fill='{{ json_encode(['qr' => $b['ma_ve'], 'code' => $b['ma_ve'], 'name' => $b['ten_tien_ich'] . ' • Căn hộ ' . $me['ma_can_ho'],
          'time' => vn_date($b['ngay_dat']) . ' • ' . substr($b['khung_gio_bat_dau'], 0, 5) . ' - ' . substr($b['khung_gio_ket_thuc'], 0, 5) . ' • ' . $b['so_nguoi'] . ' người'], JSON_UNESCAPED_UNICODE) }}'>{!! icon('qr') !!} Xem vé</button>
        <form method="post" action="{!! route('facilities.cancel') !!}" onsubmit="return confirm('Hủy lịch đặt này?')">@csrf<input type="hidden" name="id" value="{!! $b['ID_dat_lich'] !!}"><button class="x" title="Hủy lịch">{!! icon('x') !!}</button></form>
      </div>
    </div>
  @endforeach
  @if (!$mine)<p class="small muted">Bạn chưa có lịch đặt sắp tới.</p>@endif
</section>
<div class="modal center" id="qrModal"><div class="sheet">
  <div class="sheet-h"><h3>Vé điện tử QR Pass</h3><button class="x" data-close>{!! icon('x') !!}</button></div>
  <div class="center">
    <div class="ticket-qr" data-qr-target></div>
    <div class="mono muted small" data-f="code"></div>
    <p class="b mt8 mb0" data-f="name"></p><p class="mt4 mb0" data-f="time"></p>
    <p class="small muted">Quét mã này tại quầy Lễ tân khi vào cửa.</p>
  </div>
  <button class="btn block" data-close>Đã hiểu</button>
</div></div>
@if ($newBooking) @php $b = $newBooking; @endphp
<script>document.addEventListener('DOMContentLoaded',()=>{const m=document.getElementById('qrModal');m.classList.add('open');document.body.classList.add('modal-open');
m.querySelector('[data-f=code]').textContent={!! json_encode($b['ma_ve']) !!};m.querySelector('[data-f=name]').textContent={!! json_encode($b['ten_tien_ich'] . ' • Căn hộ ' . $me['ma_can_ho'], JSON_UNESCAPED_UNICODE) !!};
m.querySelector('[data-f=time]').textContent={!! json_encode(vn_date($b['ngay_dat']) . ' • ' . substr($b['khung_gio_bat_dau'], 0, 5) . ' - ' . substr($b['khung_gio_ket_thuc'], 0, 5) . ' • ' . $b['so_nguoi'] . ' người', JSON_UNESCAPED_UNICODE) !!};
renderQR(m.querySelector('[data-qr-target]'),{!! json_encode($b['ma_ve']) !!});});</script>
@endif
@endsection
