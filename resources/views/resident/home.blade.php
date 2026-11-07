@extends('layouts.resident')

@section('content')
@php $paid = $latest && $latest['trang_thai_thanh_toan'] === 'da_thanh_toan'; @endphp
<section class="hero">
  @if ($latest)
    <div class="sub">Kỳ cước mới nhất: {{ ky_label($latest['ky_cuoc']) }}</div>
    <div class="amt">{!! money($latest['tong_tien']) !!}</div>
    <div class="sub">Điện, nước, phí quản lý và gửi xe{!! $paid ? '' : ' · Hạn ' . vn_date($latest['han_thanh_toan']) !!}</div>
    <div class="row between mt12">
      <span class="{!! $paid ? 'pill-green' : 'pill-red' !!}">{{ lbl('hd', $latest['trang_thai_thanh_toan']) }}</span>
      <a class="btn white sm" style="padding:8px 14px" href="{!! route('invoices', ['id' => $latest['ID_hoa_don']]) !!}">{!! $paid ? 'Xem chi tiết' : 'Thanh toán ngay' !!}</a>
    </div>
  @else
    <div class="sub">Chưa có hóa đơn được phát hành trong thời hạn hợp đồng của bạn.</div>
  @endif
</section>
@if ($blocked)<div class="warnbox">{!! icon('alert') !!} Căn hộ có hóa đơn quá hạn trên 30 ngày nên tạm khóa quyền đặt Khu BBQ, sân thể thao và phòng sinh hoạt cộng đồng.</div>@endif
<div class="qgrid">
  <a href="{!! route('invoices') !!}"><span class="qi">{!! icon('file') !!}</span>Tra cứu hóa đơn</a>
  <a href="{!! route('facilities') !!}"><span class="qi">{!! icon('calendar') !!}</span>Đặt tiện ích nội khu</a>
  <a href="{!! route('vehicles') !!}"><span class="qi">{!! icon('car') !!}</span>Đăng ký thẻ xe</a>
  <a href="{!! route('tickets') !!}"><span class="qi">{!! icon('wrench') !!}</span>Gửi báo hỏng sự cố</a>
</div>
<section class="card">
  <h2 class="card-t">Trạng thái căn hộ {{ $me['ma_can_ho'] }}</h2>
  <div class="kv"><span class="muted">Nhân khẩu đang lưu trú</span><b>{!! $count !!} người</b></div>
  <div class="kv"><span class="muted">Diện tích</span><b>{!! num($me['dien_tich']) !!} m²</b></div>
  @if ($me['loai_cu_dan'] === 'khach_thue')
    <div class="kv"><span class="muted">Hợp đồng thuê</span><b>{!! vn_date($me['ngay_bat_dau_hop_dong']) !!} – {!! vn_date($me['ngay_ket_thuc_hop_dong']) !!}</b></div>
  @endif
  <div class="kv"><span class="muted">Thẻ đang hoạt động ({!! count($cards) !!})</span></div>
  @foreach ($cards as $c)
    <div class="chip-row"><span>{!! icon($c['loai_the'] === 'van_tay' ? 'finger' : ($c['loai_the'] === 'rfid_xe' ? 'card' : 'idcard')) !!} {{ lbl('loai_the', $c['loai_the']) }}</span><span class="mono muted">{{ $c['ma_chip_rfid'] ?? $c['ma_id_van_tay'] }}</span></div>
  @endforeach
</section>
@endsection
