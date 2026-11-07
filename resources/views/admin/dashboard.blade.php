@extends('layouts.admin')

@section('content')
<div class="row between" style="margin-bottom:14px">
  <span class="muted">Báo cáo vận hành tổng quan {{ ky_label($k['month']) }} · {!! num($k['residents']) !!} nhân khẩu, {!! num($k['vehicles']) !!} phương tiện</span>
  <div class="row noprint">
    <a class="btn" href="{!! route('admin.export') !!}">{!! icon('download') !!} Trích xuất Excel</a>
    <button class="btn ghost" onclick="window.print()">{!! icon('printer') !!} In / PDF</button>
  </div>
</div>
<div class="kpis">
  <div class="kpi"><div class="ki" style="background:#E0ECFF;color:#2563EB">{!! icon('building') !!}</div><div><small>Tỷ lệ lấp đầy</small><b>{!! num($k['occupied']) !!}</b> <span>/ {!! num($k['total']) !!} căn ({!! round($k['occupied'] * 100 / max(1, $k['total']), 1) !!}%)</span></div></div>
  <div class="kpi"><div class="ki" style="background:#DCFCE7;color:#15803D">{!! icon('money') !!}</div><div><small>Thực thu trong tháng</small><b>{!! number_format($k['collected'] / 1e6, 1, ',', '.') !!}</b> <span>triệu VNĐ</span></div></div>
  <div class="kpi"><div class="ki" style="background:#FEE2E2;color:#DC2626">{!! icon('receipt') !!}</div><div><small>Công nợ chưa thu</small><b>{!! number_format($k['debt'] / 1e6, 1, ',', '.') !!}</b> <span>triệu VNĐ</span></div></div>
  <div class="kpi"><div class="ki" style="background:#FEF3C7;color:#B45309">{!! icon('toolbox') !!}</div><div><small>Sự cố đã hoàn tất</small><b>{!! num($k['tickets_done']) !!}</b> <span>/ {!! num($k['tickets_total']) !!} phiếu · ★ {!! number_format($k['rating'], 1, ',', '') !!}</span></div></div>
</div>
<div class="cols">
  <div class="panel"><div class="panel-h"><h2>Doanh thu thực thu 12 tháng liên tiếp</h2></div>
    <div style="height:300px"><canvas id="revChart" data-labels='{{ json_encode(array_map(fn($m) => 'T' . (int)substr($m, 5) . (substr($m, 0, 4) !== substr($k['month'], 0, 4) ? '/' . substr($m, 2, 2) : ''), array_keys($rev))) }}'
      data-values='{{ json_encode(array_map(fn($v) => round($v / 1e9, 3), array_values($rev))) }}'></canvas></div></div>
  <div class="panel"><div class="panel-h"><h2>Cơ cấu công nợ theo tháp</h2></div>
    <div style="height:220px"><canvas id="debtChart" data-labels='{{ json_encode(array_map(fn($d) => 'Tháp ' . $d['thap_nha'], $debt), JSON_UNESCAPED_UNICODE) }}'
      data-values='{{ json_encode(array_map(fn($d) => round($d['s'] / 1e6, 1), $debt)) }}'></canvas></div>
    <table class="t mt12"><tr><th>Tháp</th><th class="right">Hóa đơn nợ</th><th class="right">Số tiền</th></tr>
      @foreach ($debt as $d)<tr><td>Tháp {!! $d['thap_nha'] !!}</td><td class="right">{!! $d['n'] !!}</td><td class="right">{!! money($d['s']) !!}</td></tr>@endforeach</table>
  </div>
</div>
<div class="panel"><div class="panel-h"><h2>Hiệu suất xử lý sự cố</h2></div>
  <p class="mb0">Phiếu khẩn cấp được phân công trong 15 phút: <b>{!! (int)$k['sla']['ok'] !!}/{!! (int)$k['sla']['n'] !!}</b>
  ({!! $k['sla']['n'] ? round($k['sla']['ok'] * 100 / $k['sla']['n']) : 0 !!}%) · Điểm hài lòng trung bình: <b>{!! number_format($k['rating'], 2, ',', '') !!}/5</b></p>
  <p class="note">Doanh thu tính theo giao dịch thành công (VNPay IPN và gạch nợ thủ công). Công nợ chỉ tính hóa đơn đã phát hành chưa thanh toán; hóa đơn Chờ duyệt và Đã hủy không được tính.</p>
</div>
@endsection
