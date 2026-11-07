@extends('layouts.admin')

@section('content')
<div class="cols" style="grid-template-columns:1fr 2.4fr">
<div>
  <div class="panel"><h2 style="margin:0;font-size:15px">Kiểm tra mã QR vé điện tử</h2>
    @if (\App\Support\Rbac::is('le_tan'))
    <form method="post" action="{!! route('admin.ticket-verify') !!}">@csrf
      <input class="inp mono mt12" name="code" placeholder="Quét mã hoặc nhập: MOONS-BBQ-..." required autofocus>
      <button class="btn block mt8">{!! icon('scan') !!} Xác thực vé</button></form>
    @else<p class="small muted">Lễ tân thực hiện xác thực vé tại quầy.</p>@endif
    @if ($verified)<div class="callout mt12"><b>{{ $verified['ten_tien_ich'] }}</b><br>{{ $verified['ma_can_ho'] }} · {{ $verified['ho_ten'] }}<br>{!! substr($verified['khung_gio_bat_dau'], 0, 5) !!}–{!! substr($verified['khung_gio_ket_thuc'], 0, 5) !!} · {!! $verified['so_nguoi'] !!} người</div>@endif
  </div>
  @if (\App\Support\Rbac::is('ke_toan_truong', 'admin'))
  <div class="panel"><h2 style="margin:0;font-size:15px">Khóa lịch bảo trì</h2>
    <form method="post" action="{!! route('admin.maintenance') !!}" onsubmit="return confirm('Khóa lịch sẽ tự động hủy các lịch đặt trùng. Tiếp tục?')">@csrf
      <label class="lb">Tiện ích</label><select class="inp" name="f">@foreach ($fs as $f)<option value="{!! $f['ID_tien_ich'] !!}">{{ $f['ten_tien_ich'] }}</option>@endforeach</select>
      <label class="lb">Ngày</label><input class="inp" type="date" name="date" value="{{ $date }}" min="{!! app_today() !!}">
      <div class="grid2"><div><label class="lb">Từ</label><input class="inp" type="time" name="start" value="08:00"></div><div><label class="lb">Đến</label><input class="inp" type="time" name="end" value="12:00"></div></div>
      <button class="btn warn block mt12">{!! icon('lock') !!} Khóa lịch bảo trì</button></form></div>
  @endif
</div>
<div class="panel">
  <div class="panel-h"><h2>Lịch tổng tiện ích (Master Calendar)</h2>
    <form method="get" action="{{ route('admin.facilities') }}"><input class="inp" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"></form></div>
  @php $times = \App\Services\BookingService::slotList(['gio_mo_cua' => '06:00:00', 'gio_dong_cua' => '22:00:00', 'thoi_luong_khung' => 120]); @endphp
  <div class="cal" style="grid-template-columns:110px repeat({!! count($fs) !!},1fr)">
    <div class="head">Khung giờ</div>@foreach ($fs as $f)<div class="head">{{ $f['ten_tien_ich'] }}</div>@endforeach
    @foreach ($times as [$s, $e2])
      <div class="time">{!! substr($s, 0, 5) !!} - {!! substr($e2, 0, 5) !!}</div>
      @foreach ($fs as $f) @php $cell = null; @endphp @php foreach ($grid[$f['ID_tien_ich']] as $g) if ($g['start'] === $s) $cell = $g; @endphp
        @if (!$cell)<div class="cell" style="background:#fff;border-style:dashed">Ngoài giờ</div>
        @elseif ($cell['state'] === 'bao_tri')<div class="cell maint">Bảo trì<span class="xs">Khóa hệ thống</span></div>
        @elseif (!$f['doc_quyen'])<div class="cell {!! $cell['people'] ? ($cell['state'] === 'het_cho' ? 'full' : 'booked') : '' !!}">{!! $cell['people'] !!}/{!! $f['suc_chua_toi_da'] !!} người</div>
        @elseif ($cell['booking']) @php $b = $cell['booking']; @endphp<div class="cell {!! $b['trang_thai_dat'] === 'da_su_dung' ? 'used' : 'booked' !!}"><b>{{ $b['ma_can_ho'] }}</b><span class="xs">{{ $b['ho_ten'] }} · {{ lbl('ve', $b['trang_thai_dat']) }}</span></div>
        @else<div class="cell">{!! $cell['state'] === 'qua_gio' ? 'Đã qua' : 'Trống' !!}</div>@endif
      @endforeach
    @endforeach
  </div>
</div></div>
<div class="panel"><div class="panel-h"><h2>Danh sách đặt lịch gần nhất</h2></div>
  <div class="scroll" style="max-height:360px"><table class="t"><thead><tr><th>Mã QR</th><th>Căn hộ</th><th>Người đặt</th><th>Tiện ích</th><th>Ngày</th><th>Khung giờ</th><th>Số người</th><th>Trạng thái</th></tr></thead>
  @foreach ($recent as $b)<tr><td class="mono small">{{ $b['ma_ve'] }}</td><td class="apt-code">{{ $b['ma_can_ho'] }}</td><td>{{ $b['ho_ten'] }}</td><td>{{ $b['ten_tien_ich'] }}</td>
    <td>{!! vn_date($b['ngay_dat']) !!}</td><td>{!! substr($b['khung_gio_bat_dau'], 0, 5) !!}-{!! substr($b['khung_gio_ket_thuc'], 0, 5) !!}</td><td>{!! $b['so_nguoi'] !!}</td>
    <td><span class="badge {!! ['da_xac_nhan' => 'bg-green', 'da_su_dung' => 'bg-blue', 'da_huy' => 'bg-grey', 'huy_muon' => 'bg-amber'][$b['trang_thai_dat']] !!}">{{ lbl('ve', $b['trang_thai_dat']) }}</span></td></tr>@endforeach
  </table></div></div>
@endsection
