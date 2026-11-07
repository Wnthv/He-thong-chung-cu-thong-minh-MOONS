<div class="<?= Auth::isResident() ? 'card' : 'panel' ?> center">
  <h2>Không có quyền truy cập</h2>
  <p class="muted">Vai trò <b><?= e(lbl('vai_tro', Auth::role())) ?></b> không được phép truy cập chức năng này. Thao tác đã được ghi vào nhật ký hệ thống.</p>
  <a class="btn" href="<?= url('') ?>">Về trang chính</a>
</div>
