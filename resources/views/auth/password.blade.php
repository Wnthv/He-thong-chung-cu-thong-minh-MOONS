@extends(\App\Support\Rbac::isResident() ? 'layouts.resident' : 'layouts.admin')

@section('content')
<div class="{!! \App\Support\Rbac::isResident() ? 'card' : 'panel' !!}" style="max-width:480px">
  <h2 class="card-t">Đổi mật khẩu</h2>
  @if (!empty(\App\Support\Rbac::user()['phai_doi_mat_khau']))<div class="warnbox">Bạn đang dùng mật khẩu tạm do Ban Quản lý cấp. Vui lòng đổi mật khẩu để tiếp tục.</div>@endif
  <form method="post">@csrf
    <label class="lb">Mật khẩu hiện tại</label><input class="inp" type="password" name="old" required>
    <label class="lb">Mật khẩu mới <small>(≥ 8 ký tự, gồm chữ và số)</small></label><input class="inp" type="password" name="new" required minlength="8">
    <label class="lb">Nhập lại mật khẩu mới</label><input class="inp" type="password" name="confirm" required>
    <button class="btn block mt16">Lưu mật khẩu</button>
  </form>
</div>
@endsection
