<?php
/*
|--------------------------------------------------------------------------
| Hàm tiện ích dùng chung (nạp tự động qua composer.json → autoload.files)
|--------------------------------------------------------------------------
*/

use App\Exceptions\BusinessException;
use Illuminate\Http\UploadedFile;

/** Đọc cấu hình nghiệp vụ trong config/moons.php */
function cfg(string $key, $default = null)
{
    return config('moons.' . $key, $default);
}

/** Ngày hệ thống dạng Y-m-d (cho phép cố định ngày khi chạy demo/kiểm thử) */
function app_today(): string
{
    return cfg('fixed_today') ?: date('Y-m-d');
}

/** Thời điểm hệ thống dạng Y-m-d H:i:s */
function app_now(): string
{
    return cfg('fixed_today') ? cfg('fixed_today') . date(' H:i:s') : date('Y-m-d H:i:s');
}

function money($v): string { return number_format((float) $v, 0, ',', '.') . ' đ'; }
function num($v): string { return number_format((float) $v, 0, ',', '.'); }
function vn_date($d, bool $time = false): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $time ? date('H:i d/m/Y', $t) : date('d/m/Y', $t);
}
function ky_label(string $ky): string { [$y, $m] = explode('-', $ky); return 'Tháng ' . (int) $m . '/' . $y; }
function upload_url(?string $p): string { return $p ? asset('uploads/' . $p) : ''; }

/** Thông báo một lần (hiển thị ở đầu trang kế tiếp) */
function flash(string $type, string $msg): void { session()->push('flash', [$type, $msg]); }
function flashes(): array { return session()->pull('flash', []); }

/** Lấy tham số từ request (GET/POST), tự cắt khoảng trắng */
function input(string $k, $d = null)
{
    $v = request()->input($k, $d);
    return is_string($v) ? trim($v) : $v;
}
function client_ip(): string { return app()->runningInConsole() ? 'cli' : (string) request()->ip(); }

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
function lbl(string $group, ?string $key): string { return LABELS[$group][$key] ?? (string) $key; }

/* ---------------- Biểu tượng SVG (Lucide, giấy phép ISC) ---------------- */
const ICONS = [
  'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /> <polyline points="9 22 9 12 15 12 15 22" />',
  'pie' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83" /> <path d="M22 12A10 10 0 0 0 12 2v10z" />',
  'idcard' => '<path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2" /> <rect width="18" height="18" x="3" y="4" rx="2" /> <circle cx="12" cy="10" r="2" /> <line x1="8" x2="8" y1="2" y2="4" /> <line x1="16" x2="16" y1="2" y2="4" />',
  'doc' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /> <path d="M14 2v4a2 2 0 0 0 2 2h4" /> <path d="M8 18v-2" /> <path d="M12 18v-4" /> <path d="M16 18v-6" />',
  'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <circle cx="9" cy="7" r="4" /> <path d="M22 21v-2a4 4 0 0 0-3-3.87" /> <path d="M16 3.13a4 4 0 0 1 0 7.75" />',
  'file' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" /> <path d="M14 2v4a2 2 0 0 0 2 2h4" /> <path d="M10 9H8" /> <path d="M16 13H8" /> <path d="M16 17H8" />',
  'calendar' => '<path d="M8 2v4" /> <path d="M16 2v4" /> <rect width="18" height="18" x="3" y="4" rx="2" /> <path d="M3 10h18" /> <path d="m9 16 2 2 4-4" />',
  'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /> <path d="M12 9v4" /> <path d="M12 17h.01" />',
  'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /> <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />',
  'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /> <polyline points="16 17 21 12 16 7" /> <line x1="21" x2="9" y1="12" y2="12" />',
  'bot' => '<path d="M12 8V4H8" /> <rect width="16" height="12" x="4" y="8" rx="2" /> <path d="M2 14h2" /> <path d="M20 14h2" /> <path d="M15 13v2" /> <path d="M9 13v2" />',
  'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" /> <circle cx="7" cy="17" r="2" /> <path d="M9 17h6" /> <circle cx="17" cy="17" r="2" />',
  'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />',
  'chev' => '<path d="m6 9 6 6 6-6" />',
  'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" /> <circle cx="12" cy="13" r="3" />',
  'qr' => '<rect width="5" height="5" x="3" y="3" rx="1" /> <rect width="5" height="5" x="16" y="3" rx="1" /> <rect width="5" height="5" x="3" y="16" rx="1" /> <path d="M21 16h-3a2 2 0 0 0-2 2v3" /> <path d="M21 21v.01" /> <path d="M12 7v3a2 2 0 0 1-2 2H7" /> <path d="M3 12h.01" /> <path d="M12 3h.01" /> <path d="M12 16v.01" /> <path d="M16 12h1" /> <path d="M21 12v.01" /> <path d="M12 21v-1" />',
  'send' => '<path d="m3 3 3 9-3 9 19-9Z" /> <path d="M6 12h16" />',
  'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" /> <circle cx="12" cy="12" r="3" />',
  'x' => '<path d="M18 6 6 18" /> <path d="m6 6 12 12" />',
  'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" /> <circle cx="12" cy="12" r="3" />',
  'card' => '<rect width="20" height="14" x="2" y="5" rx="2" /> <line x1="2" x2="22" y1="10" y2="10" />',
  'building' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z" /> <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" /> <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2" /> <path d="M10 6h4" /> <path d="M10 10h4" /> <path d="M10 14h4" /> <path d="M10 18h4" />',
  'gauge' => '<path d="m12 14 4-4" /> <path d="M3.34 19a10 10 0 1 1 17.32 0" />',
  'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /> <path d="m9 12 2 2 4-4" />',
  'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 10 0v4" />',
  'unlock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2" /> <path d="M7 11V7a5 5 0 0 1 9.9-1" />',
  'check' => '<path d="M20 6 9 17l-5-5" />',
  'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /> <polyline points="7 10 12 15 17 10" /> <line x1="12" x2="12" y1="15" y2="3" />',
  'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /> <polyline points="17 8 12 3 7 8" /> <line x1="12" x2="12" y1="3" y2="15" />',
  'finger' => '<path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4" /> <path d="M14 13.12c0 2.38 0 6.38-1 8.88" /> <path d="M17.29 21.02c.12-.6.43-2.3.5-3.02" /> <path d="M2 12a10 10 0 0 1 18-6" /> <path d="M2 16h.01" /> <path d="M21.8 16c.2-2 .131-5.354 0-6" /> <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2" /> <path d="M8.65 22c.21-.66.45-1.32.57-2" /> <path d="M9 6.8a6 6 0 0 1 9 5.2v2" />',
  'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />',
  'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /> <circle cx="12" cy="7" r="4" />',
  'plus' => '<path d="M5 12h14" /> <path d="M12 5v14" />',
  'search' => '<circle cx="11" cy="11" r="8" /> <path d="m21 21-4.3-4.3" />',
  'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />',
  'money' => '<rect width="20" height="12" x="2" y="6" rx="2" /> <circle cx="12" cy="12" r="2" /> <path d="M6 12h.01M18 12h.01" />',
  'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" /> <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8" /> <path d="M12 17.5v-11" />',
  'clock' => '<circle cx="12" cy="12" r="10" /> <polyline points="12 6 12 12 16 14" />',
  'scan' => '<path d="M3 7V5a2 2 0 0 1 2-2h2" /> <path d="M17 3h2a2 2 0 0 1 2 2v2" /> <path d="M21 17v2a2 2 0 0 1-2 2h-2" /> <path d="M7 21H5a2 2 0 0 1-2-2v-2" /> <path d="M7 12h10" />',
  'userplus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /> <circle cx="9" cy="7" r="4" /> <line x1="19" x2="19" y1="8" y2="14" /> <line x1="22" x2="16" y1="11" y2="11" />',
  'brain' => '<path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z" /> <path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z" /> <path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4" /> <path d="M17.599 6.5a3 3 0 0 0 .399-1.375" /> <path d="M6.003 5.125A3 3 0 0 0 6.401 6.5" /> <path d="M3.477 10.896a4 4 0 0 1 .585-.396" /> <path d="M19.938 10.5a4 4 0 0 1 .585.396" /> <path d="M6 18a4 4 0 0 1-1.967-.516" /> <path d="M19.967 17.484A4 4 0 0 1 18 18" />',
  'toolbox' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /> <rect width="20" height="14" x="2" y="6" rx="2" />',
  'bike' => '<circle cx="18.5" cy="17.5" r="3.5" /> <circle cx="5.5" cy="17.5" r="3.5" /> <circle cx="15" cy="5" r="1" /> <path d="M12 17.5V14l-3-3 4-3 2 3h2" />',
  'info' => '<circle cx="12" cy="12" r="10" /> <path d="M12 16v-4" /> <path d="M12 8h.01" />',
  'arrowleft' => '<path d="m12 19-7-7 7-7" /> <path d="M19 12H5" />',
  'grip' => '<circle cx="9" cy="12" r="1" /> <circle cx="9" cy="5" r="1" /> <circle cx="9" cy="19" r="1" /> <circle cx="15" cy="12" r="1" /> <circle cx="15" cy="5" r="1" /> <circle cx="15" cy="19" r="1" />',
  'user-cog' => '<circle cx="18" cy="15" r="3" /> <circle cx="9" cy="7" r="4" /> <path d="M10 15H6a4 4 0 0 0-4 4v2" /> <path d="m21.7 16.4-.9-.3" /> <path d="m15.2 13.9-.9-.3" /> <path d="m16.6 18.7.3-.9" /> <path d="m19.1 12.2.3-.9" /> <path d="m19.6 18.7-.4-1" /> <path d="m16.8 12.3-.4-1" /> <path d="m14.3 16.6 1-.4" /> <path d="m20.7 13.8 1-.4" />',
  'list' => '<path d="m3 17 2 2 4-4" /> <path d="m3 7 2 2 4-4" /> <path d="M13 6h8" /> <path d="M13 12h8" /> <path d="M13 18h8" />',
  'calendar2' => '<path d="M8 2v4" /> <path d="M16 2v4" /> <rect width="18" height="18" x="3" y="4" rx="2" /> <path d="M3 10h18" /> <path d="M8 14h.01" /> <path d="M12 14h.01" /> <path d="M16 14h.01" /> <path d="M8 18h.01" /> <path d="M12 18h.01" /> <path d="M16 18h.01" />',
  'waves' => '<path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1" /> <path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1" /> <path d="M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1" />',
  'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" />',
  'dumbbell' => '<path d="M14.4 14.4 9.6 9.6" /> <path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z" /> <path d="m21.5 21.5-1.4-1.4" /> <path d="M3.9 3.9 2.5 2.5" /> <path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z" />',
  'door' => '<path d="M13 4h3a2 2 0 0 1 2 2v14" /> <path d="M2 20h3" /> <path d="M13 20h9" /> <path d="M10 12v.01" /> <path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5 20V5.562a2 2 0 0 1 1.515-1.94l4-1A2 2 0 0 1 13 4.561Z" />',
  'printer' => '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /> <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6" /> <rect x="6" y="14" width="12" height="8" rx="1" />',
  'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /> <path d="M21 3v5h-5" /> <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /> <path d="M8 16H3v5" />',
];

function icon(string $n, string $cls = ''): string
{
    return '<svg class="ic ' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . (ICONS[$n] ?? '') . '</svg>';
}

/** Lưu ảnh tải lên: kiểm tra MIME thật, giới hạn 5MB, tự thu nhỏ ≤1600px (tối ưu dung lượng) */
function save_upload(string $field, string $folder, bool $required = true): ?string
{
    /** @var UploadedFile|null $f */
    $f = request()->file($field);
    if (!$f) {
        if ($required) throw new BusinessException('Vui lòng đính kèm ảnh.');
        return null;
    }
    if (!$f->isValid()) throw new BusinessException('Tải ảnh thất bại (mã lỗi ' . $f->getError() . ').');
    if ($f->getSize() > cfg('upload_max_bytes')) throw new BusinessException('Ảnh vượt quá dung lượng tối đa 5MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f->getRealPath());
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new BusinessException('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.');
    $dir = public_path('uploads/' . $folder);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = $folder . '/' . date('Ymd_His_') . bin2hex(random_bytes(4)) . '.jpg';
    $src = @imagecreatefromstring(file_get_contents($f->getRealPath()));
    if (!$src) throw new BusinessException('Tệp ảnh không hợp lệ.');
    [$w, $h] = [imagesx($src), imagesy($src)];
    $scale = min(1, 1600 / max($w, $h));
    $dst = imagecreatetruecolor((int) ($w * $scale), (int) ($h * $scale));
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
    imagejpeg($dst, public_path('uploads/' . $name), 82);
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
