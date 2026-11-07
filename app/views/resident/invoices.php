<?php if (!$cur): ?>
  <div class="card center muted">Chưa có hóa đơn<?= $me['loai_cu_dan'] === 'khach_thue' ? ' trong thời hạn hợp đồng thuê của bạn' : '' ?>.</div>
<?php else: $paid = $cur['trang_thai_thanh_toan'] === 'da_thanh_toan'; ?>
<section class="card row between">
  <b>Kỳ cước</b>
  <form><input type="hidden" name="r" value="invoices">
    <select class="inp" name="id" onchange="this.form.submit()" style="width:auto">
      <?php foreach ($list as $h): ?><option value="<?= $h['ID_hoa_don'] ?>" <?= $h['ID_hoa_don'] == $cur['ID_hoa_don'] ? 'selected' : '' ?>><?= e(ky_label($h['ky_cuoc'])) ?></option><?php endforeach ?>
    </select></form>
</section>
<?php if ($me['loai_cu_dan'] === 'khach_thue'): ?><p class="xs muted">Chỉ hiển thị kỳ cước trong thời hạn hợp đồng thuê (<?= vn_date($me['ngay_bat_dau_hop_dong']) ?> – <?= vn_date($me['ngay_ket_thuc_hop_dong']) ?>).</p><?php endif ?>
<section class="card">
  <div class="row between" style="border-bottom:1px solid var(--line);padding-bottom:10px">
    <b>CHI TIẾT CƯỚC CĂN HỘ <?= e($me['ma_can_ho']) ?></b>
    <b style="color:<?= $paid ? 'var(--green-ink)' : 'var(--red)' ?>"><?= mb_strtoupper(lbl('hd', $cur['trang_thai_thanh_toan'])) ?></b>
  </div>
  <?php
    $groups = [];
    foreach ($lines as $l) $groups[$l['loai_cuoc'] === 'dien' ? 'dien' : $l['loai_cuoc']][] = $l;
    $n = 0;
    $title = ['dien' => 'Cước điện (bậc thang EVN)', 'nuoc' => 'Cước nước sinh hoạt', 'quan_ly' => 'Phí quản lý tòa nhà',
              'gui_xe_o_to' => 'Phí gửi ô tô', 'gui_xe_may' => 'Phí gửi xe máy'];
    foreach ($groups as $k => $ls): $n++; $sum = array_sum(array_column($ls, 'thanh_tien')); ?>
    <div class="inv-line"><div><div class="t"><?= $n ?>. <?= $title[$k] ?></div>
      <div class="s"><?php if ($k === 'dien' && $reading): ?>Chỉ số: <?= $reading['chi_so_dien_cu'] ?> → <?= $reading['chi_so_dien_moi'] ?> (<?= $reading['chi_so_dien_moi'] - $reading['chi_so_dien_cu'] ?> kWh)<br>
        <?php foreach ($ls as $l): ?><?= num($l['so_luong']) ?> kWh × <?= num($l['don_gia']) ?> đ<?= $l !== end($ls) ? ' + ' : '' ?><?php endforeach ?>
      <?php elseif ($k === 'nuoc'): ?>Chỉ số: <?= e(preg_replace('/.*\((.*)\)/', '$1', $ls[0]['dien_giai'])) ?> (<?= num($ls[0]['so_luong']) ?> m³ × <?= num($ls[0]['don_gia']) ?> đ)
      <?php elseif ($k === 'quan_ly'): ?><?= num($ls[0]['so_luong']) ?> m² × <?= num($ls[0]['don_gia']) ?> đ/m²
      <?php else: ?><?= num($ls[0]['so_luong']) ?> xe × <?= num($ls[0]['don_gia']) ?> đ<?php endif ?></div></div>
      <b class="nowrap"><?= money($sum) ?></b></div>
  <?php endforeach ?>
  <div class="total"><span class="b">Tổng tiền cước</span><b><?= money($cur['tong_tien']) ?></b></div>
  <p class="xs muted">Mã hóa đơn <?= e($cur['ma_hoa_don']) ?> · Phát hành <?= vn_date($cur['ngay_phat_hanh']) ?>
    <?= $paid ? ' · Thanh toán ' . vn_date($cur['ngay_thanh_toan'], true) . ' qua ' . lbl('hinh_thuc', $cur['phuong_thuc_thanh_toan']) : ' · Hạn thanh toán ' . vn_date($cur['han_thanh_toan']) ?></p>
  <?php if (!$paid): ?>
    <button class="btn block" id="payBtn" data-id="<?= $cur['ID_hoa_don'] ?>" data-url="<?= url('pay/create') ?>" style="font-size:15px"><?= icon('qr') ?> Thanh toán qua VNPay</button>
  <?php endif ?>
</section>
<?php endif ?>
<div class="modal" id="vnpModal"><div class="sheet">
  <div class="sheet-h"><h3>Thanh toán VNPay QR</h3><button class="x" data-close><?= icon('x') ?></button></div>
  <div class="center"><div class="ticket-qr" style="background:#F3F4F6;border-color:#E5E7EB" data-qr-target></div>
    <b style="font-size:18px"><?= $cur ? money($cur['tong_tien']) : '' ?></b>
    <p class="small muted">Quét bằng ứng dụng ngân hàng hoặc bấm Thanh toán để mở cổng VNPay. Hệ thống tự gạch nợ khi nhận tín hiệu IPN thành công.</p></div>
  <button class="btn block" id="vnpGo">Thanh toán</button>
  <?php if (PaymentService::mockMode()): ?><p class="xs muted center">Môi trường demo: cổng VNPay mô phỏng (chưa cấu hình TMN code).</p><?php endif ?>
</div></div>
