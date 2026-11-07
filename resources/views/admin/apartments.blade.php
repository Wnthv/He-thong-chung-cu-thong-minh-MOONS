@extends('layouts.admin')

@section('content')
<div class="panel"><form class="filters" method="get" action="{{ route('admin.apartments') }}">
  <select class="inp" name="tower" onchange="this.form.submit()">@foreach (['A', 'B', 'C', 'D'] as $t)<option {!! $tower === $t ? 'selected' : '' !!} value="{!! $t !!}">Tháp {!! $t !!}</option>@endforeach</select>
  <select class="inp" name="floor" onchange="this.form.submit()"><option value="">Tất cả tầng</option>@for ($i = 1; $i <= 28; $i++)<option {!! $floor == $i ? 'selected' : '' !!} value="{!! $i !!}">Tầng {!! $i !!}</option>@endfor</select>
  <select class="inp" name="state" onchange="this.form.submit()"><option value="">Tất cả trạng thái</option>@foreach (LABELS['trang_thai_o'] as $k => $v)<option {!! $state === $k ? 'selected' : '' !!} value="{!! $k !!}">{!! $v !!}</option>@endforeach</select>
  <input class="inp" name="q" value="{{ $q }}" placeholder="Tìm mã căn hộ hoặc tên cư dân..." style="min-width:260px">
  <span class="badge bg-red">{!! $pending !!} hồ sơ chờ duyệt</span>
  <div class="legend"><span><i style="background:var(--tile-green)"></i>Chủ hộ</span><span><i style="background:var(--tile-blue)"></i>Khách thuê</span><span><i style="background:var(--tile-grey)"></i>Trống</span></div>
</form></div>
@foreach ($byFloor as $fl => $apts)
  <section class="floor"><h3>Tầng {!! $fl !!} - Tháp {{ $tower }}{!! $fl == 1 ? ' (Shophouse)' : '' !!}</h3>
    <div class="tiles">
      @foreach ($apts as $a)
        <a class="tile {!! $a['trang_thai_o'] !!} {!! $detail && $detail['apt']['ID_can_ho'] == $a['ID_can_ho'] ? 'sel' : '' !!}" href="{!! route('admin.apartments', ['tower' => $tower, 'floor' => $floor, 'state' => $state, 'q' => $q, 'apt' => $a['ma_can_ho']]) !!}">
          @if ($a['cho_duyet'])<span class="new">Mới</span>@endif
          <b>{{ $a['ma_can_ho'] }}</b><span>{{ $a['dai_dien'] ?? 'Trống' }}</span><span>{!! $a['so_nguoi'] !!} người · {!! num($a['dien_tich']) !!}m²</span></a>
      @endforeach
    </div></section>
@endforeach
@if (!$byFloor)<div class="panel muted">Không có căn hộ phù hợp bộ lọc.</div>@endif

@if ($detail) @php $o = null; @endphp @php foreach ($detail['people'] as $p) if ($p['loai_cu_dan'] !== 'thanh_vien' && $p['trang_thai_duyet'] === 'da_duyet') { $o = $p; @endphp @php if ($p['loai_cu_dan'] === 'chu_ho') break; @endphp @php }; @endphp
<aside class="drawer">
  <div class="row between"><h2>Căn hộ {{ $detail['apt']['ma_can_ho'] }}</h2><a class="x" href="{!! route('admin.apartments', ['tower' => $tower, 'floor' => $floor, 'state' => $state, 'q' => $q]) !!}">{!! icon('x') !!}</a></div>
  @if ($o)<div class="owner"><small>HỒ SƠ {!! mb_strtoupper(lbl('loai_cu_dan', $o['loai_cu_dan'])) !!}</small><div class="b" style="font-size:16px">{{ $o['ho_ten'] }}</div>
    <div class="small muted">SĐT: {{ substr((string)$o['so_dien_thoai'], 0, 4) }}xxx{{ substr((string)$o['so_dien_thoai'], -3) }} · Diện tích {!! num($detail['apt']['dien_tich']) !!}m² · {{ lbl('trang_thai_o', $detail['apt']['trang_thai_o']) }}</div></div>@endif
  <h3>Nhân khẩu ({!! count($detail['people']) !!})</h3>
  @foreach ($detail['people'] as $p)
    <div class="mini"><div class="row between"><b>{{ $p['ho_ten'] }}</b><span class="badge {!! $p['trang_thai_duyet'] === 'da_duyet' ? 'bg-green' : 'bg-amber' !!}">{{ lbl('duyet', $p['trang_thai_duyet']) }}</span></div>
      <div class="xs muted">{{ $p['quan_he_chu_ho'] }} · {{ lbl('loai_cu_dan', $p['loai_cu_dan']) }}{!! $p['ngay_sinh'] ? ' · Sinh ' . date('Y', strtotime($p['ngay_sinh'])) : '' !!} · CCCD: {!! $p['so_cccd'] ? e(substr($p['so_cccd'], 0, 3) . 'xxxxxx' . substr($p['so_cccd'], -3)) : '—' !!}</div>
      @if ($p['loai_cu_dan'] === 'khach_thue')<div class="xs muted">HĐ {{ $p['so_hop_dong'] }}: {!! vn_date($p['ngay_bat_dau_hop_dong']) !!} – {!! vn_date($p['ngay_ket_thuc_hop_dong']) !!}</div>@endif
      @if ($p['anh_giay_to'])<a class="xs link" href="{!! upload_url($p['anh_giay_to']) !!}" target="_blank">Xem ảnh giấy tờ</a>@endif
      @if ($p['trang_thai_duyet'] === 'cho_duyet' && \App\Support\Rbac::is('le_tan'))
        <div class="grid2 mt8">
          <form method="post" action="{!! route('admin.resident-approve') !!}">@csrf<input type="hidden" name="id" value="{!! $p['ID_nhan_khau'] !!}"><input type="hidden" name="tower" value="{{ $tower }}"><input type="hidden" name="apt" value="{{ $detail['apt']['ma_can_ho'] }}"><button class="btn success sm" style="width:100%">Phê duyệt</button></form>
          <form method="post" action="{!! route('admin.resident-reject') !!}" onsubmit="this.reason.value=prompt('Lý do từ chối:')||'';return this.reason.value.length>=5">@csrf<input type="hidden" name="id" value="{!! $p['ID_nhan_khau'] !!}"><input type="hidden" name="reason"><input type="hidden" name="tower" value="{{ $tower }}"><input type="hidden" name="apt" value="{{ $detail['apt']['ma_can_ho'] }}"><button class="btn danger sm" style="width:100%">Từ chối</button></form>
        </div>
      @endif
    </div>
  @endforeach
  <h3>Phương tiện ({!! count($detail['vehicles']) !!})</h3>
  @foreach ($detail['vehicles'] as $v)<div class="mini row between"><span class="mono b">{{ $v['bien_so_xe'] }}</span><span>{{ lbl('loai_xe', $v['loai_xe']) }}</span><span class="badge {!! $v['trang_thai_duyet'] === 'da_duyet' ? 'bg-green' : 'bg-amber' !!}">{{ lbl('duyet', $v['trang_thai_duyet']) }}</span></div>@endforeach
  @if ($detail['invoice']) @php $i = $detail['invoice']; @endphp
    <h3>Hóa đơn {{ mb_strtolower(ky_label($i['ky_cuoc'])) }}</h3>
    <div class="mini row between"><b>{!! money($i['tong_tien']) !!}</b><span class="badge {!! $i['trang_thai_thanh_toan'] === 'da_thanh_toan' ? 'bg-green' : 'bg-red' !!}">{{ lbl('hd', $i['trang_thai_thanh_toan']) }}</span></div>
  @endif
</aside>
@endif
@endsection
