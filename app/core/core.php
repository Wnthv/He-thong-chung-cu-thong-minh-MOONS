<?php
/* ====================== LÕI ỨNG DỤNG (MVC tối giản) ====================== */

/** Lỗi nghiệp vụ: thông điệp hiển thị trực tiếp cho người dùng */
class BusinessException extends RuntimeException {}

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = cfg('db');
            self::$pdo = new PDO(
                "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
                $c['user'], $c['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
            self::$pdo->exec("SET time_zone = '+07:00'");
        }
        return self::$pdo;
    }
    public static function q(string $sql, array $p = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st;
    }
    public static function all(string $sql, array $p = []): array { return self::q($sql, $p)->fetchAll(); }
    public static function one(string $sql, array $p = []): ?array { $r = self::q($sql, $p)->fetch(); return $r ?: null; }
    public static function val(string $sql, array $p = []) { $r = self::q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
    public static function id(): int { return (int) self::pdo()->lastInsertId(); }

    /** Chạy trong một giao dịch CSDL; tự hoàn tác khi có lỗi */
    public static function tx(callable $fn)
    {
        $pdo = self::pdo();
        $outer = !$pdo->inTransaction();
        if ($outer) $pdo->beginTransaction();
        try {
            $r = $fn();
            if ($outer) $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($outer && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    public static function setPdo(PDO $pdo): void { self::$pdo = $pdo; }
}

function cfg(string $key, $default = null)
{
    static $c = null;
    if ($c === null) $c = require __DIR__ . '/../../config/config.php';
    return $c[$key] ?? $default;
}
function today(): string { return cfg('fixed_today') ?: date('Y-m-d'); }
function now(): string { return cfg('fixed_today') ? cfg('fixed_today') . date(' H:i:s') : date('Y-m-d H:i:s'); }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v): string { return number_format((float)$v, 0, ',', '.') . ' đ'; }
function num($v): string { return number_format((float)$v, 0, ',', '.'); }
function vn_date($d, bool $time = false): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $time ? date('H:i d/m/Y', $t) : date('d/m/Y', $t);
}
function ky_label(string $ky): string { [$y, $m] = explode('-', $ky); return 'Tháng ' . (int)$m . '/' . $y; }
function url(string $route = '', array $q = []): string
{
    $base = strtok($_SERVER['SCRIPT_NAME'] ?? '/index.php', '?');
    $q = $route !== '' ? array_merge(['r' => $route], $q) : $q;
    return $base . ($q ? '?' . http_build_query($q) : '');
}
function asset(string $p): string { return rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/assets/' . $p; }
function upload_url(?string $p): string { return $p ? rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/uploads/' . $p : ''; }
function redirect(string $route, array $q = []): never { header('Location: ' . url($route, $q)); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function input(string $k, $d = null) { $v = $_POST[$k] ?? $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'cli'; }
function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- Nhãn hiển thị cho các giá trị ENUM ---------------- */
const LABELS = [
    'vai_tro' => ['chu_ho' => 'Chủ hộ', 'khach_thue' => 'Khách thuê', 'le_tan' => 'Lễ tân', 'ke_toan' => 'Kế toán',
        'ke_toan_truong' => 'Kế toán trưởng', 'dieu_phoi_vien' => 'Điều phối viên', 'ky_thuat_vien' => 'Kỹ thuật viên', 'admin' => 'Admin'],
    'loai_cu_dan' => ['chu_ho' => 'Chủ hộ', 'khach_thue' => 'Khách thuê', 'thanh_vien' => 'Thành viên'],
    'duyet' => ['cho_duyet' => 'Chờ duyệt', 'da_duyet' => 'Đã duyệt', 'tu_choi' => 'Bị từ chối', 'da_roi_di' => 'Đã rời đi',
        'yeu_cau_bo_sung' => 'Yêu cầu bổ sung', 'da_huy' => 'Đã hủy'],
    'loai_xe' => ['o_to' => 'Ô tô', 'xe_may' => 'Xe máy'],
    'hd' => ['cho_duyet' => 'Chờ duyệt', 'chua_thanh_toan' => 'Chưa thanh toán', 'da_thanh_toan' => 'Đã thanh toán', 'da_huy' => 'Đã hủy'],
    'gd' => ['khoi_tao' => 'Đang chờ', 'thanh_cong' => 'Đã gạch nợ', 'that_bai' => 'Thất bại', 'ngoai_le' => 'Ngoại lệ'],
    'hinh_thuc' => ['vnpay' => 'VNPay', 'tien_mat' => 'Tiền mặt', 'chuyen_khoan' => 'Chuyển khoản'],
    've' => ['da_xac_nhan' => 'Đã xác nhận', 'da_su_dung' => 'Đã sử dụng', 'da_huy' => 'Đã hủy', 'huy_muon' => 'Hủy muộn'],
    'kanban' => ['cho_tiep_nhan' => 'Chờ tiếp nhận', 'da_phan_cong' => 'Đã phân công', 'dang_xu_ly' => 'Đang xử lý',
        'cho_linh_kien' => 'Chờ linh kiện', 'cho_nghiem_thu' => 'Chờ nghiệm thu', 'hoan_tat' => 'Hoàn tất',
        'tu_choi' => 'Từ chối', 'da_huy' => 'Đã hủy'],
    'muc_do' => ['binh_thuong' => 'Bình thường', 'khan_cap' => 'Khẩn cấp'],
    'pham_vi' => ['can_ho' => 'Trong căn hộ', 'khu_vuc_chung' => 'Khu vực công cộng'],
    'loai_the' => ['rfid_xe' => 'Thẻ RFID gửi xe', 'thang_may' => 'Thẻ thang máy', 'van_tay' => 'Vân tay sảnh chính'],
    'the' => ['kich_hoat' => 'Đã kích hoạt', 'tam_khoa' => 'Tạm khóa', 'bao_mat' => 'Báo mất', 'da_huy' => 'Đã hủy'],
    'trang_thai_o' => ['trong' => 'Trống', 'chu_ho_o' => 'Chủ hộ đang ở', 'cho_thue' => 'Khách thuê đang ở'],
];
function lbl(string $group, ?string $key): string { return LABELS[$group][$key] ?? (string)$key; }

/* ---------------- Phiên làm việc & phân quyền (RBAC) ---------------- */
final class Auth
{
    const RESIDENT = ['chu_ho', 'khach_thue'];
    const STAFF = ['le_tan', 'ke_toan', 'ke_toan_truong', 'dieu_phoi_vien', 'ky_thuat_vien', 'admin'];

    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function id(): ?int { return isset($_SESSION['user']) ? (int)$_SESSION['user']['ID_tai_khoan'] : null; }
    public static function role(): ?string { return $_SESSION['user']['vai_tro'] ?? null; }
    public static function is(string ...$roles): bool { return in_array(self::role(), $roles, true); }
    public static function isResident(): bool { return self::is(...self::RESIDENT); }

    /** Middleware: chặn truy cập trái phép đường dẫn ngoài phạm vi vai trò */
    public static function guard(array $roles): void
    {
        if (!self::user()) { redirect('auth/login'); }
        if ($roles && !in_array(self::role(), $roles, true)) {
            AuditService::log('truy_cap_trai_phep', 'route', $_GET['r'] ?? '', 'Vai trò ' . self::role());
            http_response_code(403);
            View::render('auth/forbidden', [], self::isResident() ? 'resident' : 'admin');
            exit;
        }
        if (!empty($_SESSION['user']['phai_doi_mat_khau']) && ($_GET['r'] ?? '') !== 'auth/password') {
            redirect('auth/password');
        }
    }
    public static function login(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        if (in_array($u['vai_tro'], self::RESIDENT, true)) {
            $_SESSION['resident'] = DB::one(
                "SELECT nk.*, ch.ma_can_ho, ch.dien_tich, ch.thap_nha, ch.trang_thai_o
                   FROM nhan_khau nk JOIN can_ho ch ON ch.ID_can_ho = nk.ID_can_ho
                  WHERE nk.ID_tai_khoan = ?", [$u['ID_tai_khoan']]);
        }
    }
    /** Hồ sơ nhân khẩu của cư dân đang đăng nhập */
    public static function resident(): ?array { return $_SESSION['resident'] ?? null; }
    public static function logout(): void { $_SESSION = []; session_destroy(); }
}

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }
    public static function field(): string { return '<input type="hidden" name="_csrf" value="' . self::token() . '">'; }
    public static function check(): void
    {
        $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(self::token(), (string)$t)) {
            http_response_code(419);
            exit('Phiên làm việc đã hết hạn (CSRF). Vui lòng tải lại trang.');
        }
    }
}

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . '/../views/' . $view . '.php';
        $content = ob_get_clean();
        if ($layout) require __DIR__ . '/../views/layouts/' . $layout . '.php';
        else echo $content;
    }
}

/** Lưu ảnh tải lên: kiểm tra MIME thật, giới hạn 5MB, tự thu nhỏ ≤1600px (tối ưu dung lượng) */
function save_upload(string $field, string $folder, bool $required = true): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) throw new BusinessException('Vui lòng đính kèm ảnh.');
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) throw new BusinessException('Tải ảnh thất bại (mã lỗi ' . $f['error'] . ').');
    if ($f['size'] > cfg('upload_max_bytes')) throw new BusinessException('Ảnh vượt quá dung lượng tối đa 5MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new BusinessException('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.');
    $dir = __DIR__ . '/../../public/uploads/' . $folder;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = $folder . '/' . date('Ymd_His_') . bin2hex(random_bytes(4)) . '.jpg';
    $src = @imagecreatefromstring(file_get_contents($f['tmp_name']));
    if (!$src) throw new BusinessException('Tệp ảnh không hợp lệ.');
    [$w, $h] = [imagesx($src), imagesy($src)];
    $scale = min(1, 1600 / max($w, $h));
    $dst = imagecreatetruecolor((int)($w * $scale), (int)($h * $scale));
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
    imagejpeg($dst, __DIR__ . '/../../public/uploads/' . $name, 82);
    return $name;
}

/** Chuyển lỗi SIGNAL của trigger thành thông điệp tiếng Việt */
function db_error_message(Throwable $e): string
{
    $m = $e->getMessage();
    $map = [
        'HD_DA_PHAT_HANH' => 'Hóa đơn đã phát hành, không được sửa chi tiết. Hãy hủy và lập hóa đơn thay thế.',
        'HD_CHUA_CO_GIAO_DICH' => 'Không thể gạch nợ khi chưa có giao dịch thành công khớp số tiền.',
        'HD_DA_HUY' => 'Hóa đơn đã hủy, không thể thay đổi.',
        'HD_DA_THANH_TOAN' => 'Hóa đơn đã thanh toán, không thể thay đổi trạng thái.',
        'HD_CHUYEN_TRANG_THAI' => 'Chuyển trạng thái hóa đơn không hợp lệ.',
        'HD_KHONG_XOA' => 'Hóa đơn chỉ được hủy, không được xóa.',
        'CS_KHOA_SO' => 'Chỉ số kỳ này đã khóa sổ sau khi phát hành hóa đơn.',
        'GD_BAT_BIEN' => 'Giao dịch đã kết thúc, không được sửa.',
        'GD_KHONG_HOP_LE' => 'Giao dịch thành công phải khớp số tiền của hóa đơn đang chưa thanh toán (hóa đơn có thể đã được thanh toán bằng hình thức khác).',
        'uq_tx_bien_so' => 'Biển số xe này đã được đăng ký trên hệ thống.',
        'uq_hd_ky' => 'Căn hộ đã có hóa đơn còn hiệu lực trong kỳ này.',
        'uq_gd_hd_tc' => 'Hóa đơn đã được gạch nợ trước đó.',
        'uq_nk_cccd' => 'Số CCCD đã tồn tại trên hệ thống.',
        'uq_trv_rfid' => 'Mã chip RFID đã được cấp cho người khác.',
        'uq_trv_vt' => 'Mã ID vân tay đã được cấp cho người khác.',
        'uq_tk_ten' => 'Tên đăng nhập đã tồn tại.', 'uq_tk_email' => 'Email đã được dùng cho tài khoản khác.',
        'uq_tk_sdt' => 'Số điện thoại đã được dùng cho tài khoản khác.',
        'ck_tx_bien_so' => 'Biển số không đúng định dạng biển số xe Việt Nam (VD: 51G-102.24, 59X3-343.41).',
        'ck_cs_dien' => 'Chỉ số điện mới phải lớn hơn hoặc bằng chỉ số cũ.',
        'ck_cs_nuoc' => 'Chỉ số nước mới phải lớn hơn hoặc bằng chỉ số cũ.',
        'ck_nk_cccd' => 'Số CCCD phải gồm đúng 12 chữ số.',
        'ck_nk_hop_dong' => 'Khách thuê phải có số hợp đồng, ngày bắt đầu và ngày kết thúc hợp lệ.',
        'ck_psc_anh' => 'Phải đính kèm ít nhất 01 ảnh nghiệm thu trước khi đề xuất nghiệm thu.',
    ];
    foreach ($map as $k => $v) if (str_contains($m, $k)) return $v;
    return 'Dữ liệu không hợp lệ: ' . $m;
}
