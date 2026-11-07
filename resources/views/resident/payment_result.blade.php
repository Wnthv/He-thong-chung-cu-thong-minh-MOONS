@extends('layouts.resident')

@section('content')
@php $ok = $valid && $gd && $gd['trang_thai'] === 'thanh_cong'; @endphp
<section class="card center">
  <div style="width:64px;height:64px;border-radius:50%;margin:6px auto 10px;display:grid;place-items:center;background:{!! $ok ? 'var(--green-bg)' : 'var(--red-bg)' !!};color:{!! $ok ? 'var(--green-ink)' : 'var(--red-ink)' !!}">{!! icon($ok ? 'check' : 'x', '') !!}</div>
  <h2 style="margin:0 0 6px">{!! $ok ? 'Thanh toán thành công' : ($code === '24' ? 'Giao dịch bị hủy bỏ' : 'Thanh toán chưa thành công') !!}</h2>
  <p class="muted">{!! $ok ? 'Hóa đơn đã được gạch nợ tự động.' : ($code === '24' ? 'Giao dịch bị hủy bỏ, hóa đơn của bạn chưa được thanh toán.' : ($valid ? 'Hóa đơn của bạn chưa được thanh toán.' : 'Chữ ký phản hồi không hợp lệ. Giao dịch được chuyển kế toán đối soát.')) !!}</p>
  @if ($gd)
    <div class="kv"><span class="muted">Mã giao dịch</span><b class="mono">{{ $gd['ma_giao_dich_ref'] }}</b></div>
    <div class="kv"><span class="muted">Hóa đơn</span><b>{{ $gd['ma_hoa_don'] }}</b></div>
    <div class="kv"><span class="muted">Số tiền</span><b>{!! money($gd['so_tien']) !!}</b></div>
    <div class="kv"><span class="muted">Ngân hàng</span><b>{{ $gd['ma_ngan_hang'] ?? '—' }}</b></div>
    <div class="kv"><span class="muted">Trạng thái hóa đơn</span><b>{{ lbl('hd', $gd['trang_thai_thanh_toan']) }}</b></div>
  @endif
  <a class="btn block mt12" href="{!! route('invoices') !!}">Về danh sách hóa đơn</a>
</section>
@endsection
