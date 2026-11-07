<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>VNPay – Cổng thanh toán mô phỏng</title><link rel="stylesheet" href="<?= asset('css/app.css') ?>"></head>
<body style="background:#EEF2F7">
<div class="vn-sandbox">
  <header>VNPAY · Cổng thanh toán (môi trường mô phỏng)</header>
  <div style="padding:18px">
  <?php if (!$valid): ?>
    <div class="flash error">Chữ ký yêu cầu thanh toán (vnp_SecureHash) không hợp lệ. Giao dịch bị từ chối.</div>
  <?php else: ?>
    <div class="kv"><span class="muted">Đơn vị chấp nhận</span><b>Ban Quản lý Chung cư Moons</b></div>
    <div class="kv"><span class="muted">Mã giao dịch</span><b class="mono"><?= e($q['vnp_TxnRef']) ?></b></div>
    <div class="kv"><span class="muted">Nội dung</span><b><?= e($q['vnp_OrderInfo']) ?></b></div>
    <div class="kv"><span class="muted">Số tiền</span><b style="font-size:20px;color:#005BAA"><?= money($q['vnp_Amount'] / 100) ?></b></div>
    <form method="post"><input type="hidden" name="_csrf" value="<?= Csrf::token() ?>">
      <label class="lb">Ngân hàng</label>
      <select class="inp" name="bank"><option>NCB</option><option>VCB</option><option>BIDV</option><option>TCB</option></select>
      <div class="grid2 mt16"><button class="btn ghost" name="action" value="cancel">Hủy giao dịch</button><button class="btn" name="action" value="pay" style="background:#005BAA">Xác nhận thanh toán</button></div>
    </form>
    <p class="xs muted mt12">Trang này thay cho cổng VNPay Sandbox khi chưa có mã TMN. Hệ thống vẫn ký HMAC SHA512, gửi IPN và chuyển hướng ReturnUrl đúng trình tự của VNPay.</p>
  <?php endif ?>
  </div>
</div></body></html>
