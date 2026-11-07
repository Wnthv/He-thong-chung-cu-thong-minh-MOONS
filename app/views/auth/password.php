<div class="<?= Auth::isResident() ? 'card' : 'panel' ?>" style="max-width:480px">
  <h2 class="card-t">Đổi mật khẩu</h2>
  <?php if (!empty(Auth::user()['phai_doi_mat_khau'])): ?><div class="warnbox">Bạn đang dùng mật khẩu tạm do Ban Quản lý cấp. Vui lòng đổi mật khẩu để tiếp tục.</div><?php endif ?>
  <form method="post"><?= Csrf::field() ?>
    <label class="lb">Mật khẩu hiện tại</label><input class="inp" type="password" name="old" required>
    <label class="lb">Mật khẩu mới <small>(≥ 8 ký tự, gồm chữ và số)</small></label><input class="inp" type="password" name="new" required minlength="8">
    <label class="lb">Nhập lại mật khẩu mới</label><input class="inp" type="password" name="confirm" required>
    <button class="btn block mt16">Lưu mật khẩu</button>
  </form>
</div>
