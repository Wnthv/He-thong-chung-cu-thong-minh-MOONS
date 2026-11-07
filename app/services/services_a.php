<?php
/* ======================= NHẬT KÝ THAO TÁC (Audit Log) ======================= */
final class AuditService
{
    public static function log(string $action, string $object, $objectId = null, ?string $content = null, ?int $userId = null): void
    {
        DB::q("INSERT INTO nhat_ky_thao_tac(hanh_dong, doi_tuong, ID_doi_tuong, noi_dung, dia_chi_ip, ID_tai_khoan)
               VALUES (?,?,?,?,?,?)",
            [$action, $object, $objectId === null ? null : (string)$objectId,
             $content === null ? null : mb_substr($content, 0, 1000), client_ip(), $userId ?? Auth::id()]);
    }
}

/* ======================= XÁC THỰC (UC-ALL01, UC-ALL02) ======================= */
final class AuthService
{
    const MAX_FAIL = 5;
    const LOCK_MINUTES = 15;

    private static function findByLogin(string $login): ?array
    {
        return DB::one("SELECT * FROM tai_khoan WHERE ten_dang_nhap = ? OR email = ? OR so_dien_thoai = ? LIMIT 1",
            [$login, $login, $login]);
    }

    /** Kiểm tra các điều kiện chung trước khi cho đăng nhập (khóa, hợp đồng thuê) */
    private static function assertCanLogin(array $u): void
    {
        if ((int)$u['trang_thai'] === 0) {
            throw new BusinessException('Tài khoản của bạn đã bị khóa, vui lòng liên hệ Ban Quản lý.');
        }
        if ($u['khoa_den'] && strtotime($u['khoa_den']) > time()) {
            $m = (int)ceil((strtotime($u['khoa_den']) - time()) / 60);
            throw new BusinessException("Bạn đã nhập sai 5 lần. Tài khoản tạm khóa, thử lại sau {$m} phút.");
        }
        if ($u['vai_tro'] === 'khach_thue') {
            $nk = DB::one("SELECT * FROM nhan_khau WHERE ID_tai_khoan = ?", [$u['ID_tai_khoan']]);
            if ($nk && $nk['ngay_ket_thuc_hop_dong'] < today()) {
                ResidentService::expireTenant((int)$nk['ID_nhan_khau']);
                throw new BusinessException('Hợp đồng thuê đã hết hạn. Tài khoản khách thuê đã được thu hồi.');
            }
        }
    }

    public static function attempt(string $login, string $password): array
    {
        $u = self::findByLogin($login);
        if (!$u) {
            AuditService::log('dang_nhap_that_bai', 'tai_khoan', null, "Không tồn tại: $login", null);
            throw new BusinessException('Tên đăng nhập hoặc mật khẩu không chính xác.');
        }
        self::assertCanLogin($u);
        if (!password_verify($password, $u['mat_khau_bam'])) {
            $fail = min(self::MAX_FAIL, (int)$u['so_lan_sai'] + 1);
            $lock = $fail >= self::MAX_FAIL ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
            DB::q("UPDATE tai_khoan SET so_lan_sai = ?, khoa_den = ? WHERE ID_tai_khoan = ?",
                [$lock ? 0 : $fail, $lock, $u['ID_tai_khoan']]);
            AuditService::log('dang_nhap_that_bai', 'tai_khoan', $u['ID_tai_khoan'], "Sai mật khẩu lần $fail", (int)$u['ID_tai_khoan']);
            if ($lock) throw new BusinessException('Bạn đã nhập sai 5 lần. Tài khoản tạm khóa 15 phút.');
            throw new BusinessException('Tên đăng nhập hoặc mật khẩu không chính xác.');
        }
        DB::q("UPDATE tai_khoan SET so_lan_sai = 0, khoa_den = NULL WHERE ID_tai_khoan = ?", [$u['ID_tai_khoan']]);
        AuditService::log('dang_nhap', 'tai_khoan', $u['ID_tai_khoan'], 'Mật khẩu', (int)$u['ID_tai_khoan']);
        return $u;
    }

    /** Gửi OTP 6 chữ số (bản demo hiển thị trên màn hình vì chưa tích hợp nhà mạng SMS) */
    public static function sendOtp(string $login): array
    {
        $u = self::findByLogin($login);
        if (!$u || !in_array($u['vai_tro'], Auth::RESIDENT, true)) {
            throw new BusinessException('Không tìm thấy cư dân với số điện thoại hoặc email này.');
        }
        self::assertCanLogin($u);
        $otp = (string)random_int(100000, 999999);
        $_SESSION['otp'] = ['uid' => (int)$u['ID_tai_khoan'], 'hash' => password_hash($otp, PASSWORD_DEFAULT),
            'exp' => time() + cfg('otp_ttl'), 'tries' => 0];
        AuditService::log('gui_otp', 'tai_khoan', $u['ID_tai_khoan'], null, (int)$u['ID_tai_khoan']);
        $masked = $u['so_dien_thoai'] ? substr($u['so_dien_thoai'], 0, 6) . 'xxxx' : $u['email'];
        return ['otp' => cfg('otp_demo') ? $otp : null, 'to' => $masked];
    }

    public static function verifyOtp(string $code): array
    {
        $o = $_SESSION['otp'] ?? null;
        if (!$o) throw new BusinessException('Vui lòng yêu cầu gửi mã OTP.');
        if (time() > $o['exp']) { unset($_SESSION['otp']); throw new BusinessException('Mã OTP đã hết hiệu lực. Vui lòng gửi lại mã.'); }
        if (++$_SESSION['otp']['tries'] > 5) { unset($_SESSION['otp']); throw new BusinessException('Nhập sai OTP quá nhiều lần.'); }
        if (!password_verify($code, $o['hash'])) throw new BusinessException('Mã OTP không đúng.');
        unset($_SESSION['otp']);
        $u = DB::one("SELECT * FROM tai_khoan WHERE ID_tai_khoan = ?", [$o['uid']]);
        self::assertCanLogin($u);
        AuditService::log('dang_nhap', 'tai_khoan', $u['ID_tai_khoan'], 'OTP', (int)$u['ID_tai_khoan']);
        return $u;
    }
}

/* ======================= TÀI KHOẢN & PHÂN QUYỀN (UC-ADM01) ======================= */
final class AccountService
{
    public static function tempPassword(): string
    {
        return 'Mn' . random_int(1000, 9999) . substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 3) . '!';
    }
    public static function validatePassword(string $p): void
    {
        if (mb_strlen($p) < 8 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
            throw new BusinessException('Mật khẩu phải có ít nhất 8 ký tự, gồm cả chữ và số.');
        }
    }
    public static function createStaff(string $name, string $email, string $phone, string $role): array
    {
        if (!in_array($role, Auth::STAFF, true)) throw new BusinessException('Vai trò không hợp lệ.');
        if (mb_strlen($name) < 3) throw new BusinessException('Vui lòng nhập họ tên.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new BusinessException('Email không hợp lệ.');
        if (!preg_match('/^0\d{9}$/', $phone)) throw new BusinessException('Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.');
        $username = strtolower(strstr($email, '@', true));
        $pw = self::tempPassword();
        DB::q("INSERT INTO tai_khoan(ten_dang_nhap, mat_khau_bam, ho_ten, email, so_dien_thoai, vai_tro, phai_doi_mat_khau)
               VALUES (?,?,?,?,?,?,1)", [$username, password_hash($pw, PASSWORD_BCRYPT), $name, $email, $phone, $role]);
        $id = DB::id();
        AuditService::log('cap_tai_khoan', 'tai_khoan', $id, "$username - $role");
        return ['id' => $id, 'username' => $username, 'password' => $pw];
    }
    public static function toggleLock(int $id): string
    {
        if ($id === Auth::id()) throw new BusinessException('Không thể tự khóa tài khoản đang đăng nhập.');
        $u = DB::one("SELECT * FROM tai_khoan WHERE ID_tai_khoan = ?", [$id]);
        if (!$u || !in_array($u['vai_tro'], Auth::STAFF, true)) throw new BusinessException('Không tìm thấy tài khoản nhân viên.');
        $new = (int)$u['trang_thai'] === 1 ? 0 : 1;
        DB::q("UPDATE tai_khoan SET trang_thai = ? WHERE ID_tai_khoan = ?", [$new, $id]);
        AuditService::log($new ? 'mo_khoa_tai_khoan' : 'khoa_tai_khoan', 'tai_khoan', $id);
        return $new ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.';
    }
    public static function changePassword(int $uid, string $old, string $new, string $confirm): void
    {
        $u = DB::one("SELECT * FROM tai_khoan WHERE ID_tai_khoan = ?", [$uid]);
        if (!password_verify($old, $u['mat_khau_bam'])) throw new BusinessException('Mật khẩu hiện tại không đúng.');
        if ($new !== $confirm) throw new BusinessException('Xác nhận mật khẩu không khớp.');
        if ($new === $old) throw new BusinessException('Mật khẩu mới phải khác mật khẩu cũ.');
        self::validatePassword($new);
        DB::q("UPDATE tai_khoan SET mat_khau_bam = ?, phai_doi_mat_khau = 0 WHERE ID_tai_khoan = ?",
            [password_hash($new, PASSWORD_BCRYPT), $uid]);
        AuditService::log('doi_mat_khau', 'tai_khoan', $uid);
    }
}

/* ======================= CĂN HỘ & NHÂN KHẨU (UC-RES01/02, UC-REC01) ======================= */
final class ResidentService
{
    /** Đồng bộ trạng thái ở của căn hộ theo nhân khẩu đã duyệt (dữ liệu dẫn xuất) */
    public static function syncOccupancy(int $canHo): string
    {
        $t = today();
        $tenant = DB::val("SELECT COUNT(*) FROM nhan_khau WHERE ID_can_ho=? AND loai_cu_dan='khach_thue'
                AND trang_thai_duyet='da_duyet' AND ? BETWEEN ngay_bat_dau_hop_dong AND ngay_ket_thuc_hop_dong", [$canHo, $t]);
        $owner = DB::val("SELECT COUNT(*) FROM nhan_khau WHERE ID_can_ho=? AND loai_cu_dan='chu_ho' AND trang_thai_duyet='da_duyet'", [$canHo]);
        $state = $tenant ? 'cho_thue' : ($owner ? 'chu_ho_o' : 'trong');
        DB::q("UPDATE can_ho SET trang_thai_o = ? WHERE ID_can_ho = ?", [$state, $canHo]);
        return $state;
    }

    /** Khai báo thành viên (Chủ hộ/Khách thuê) hoặc khai báo Khách thuê (chỉ Chủ hộ) */
    public static function declare(array $me, array $d, ?string $photo): int
    {
        $type = $d['loai_cu_dan'] ?? 'thanh_vien';
        if (!in_array($type, ['thanh_vien', 'khach_thue'], true)) throw new BusinessException('Loại cư dân không hợp lệ.');
        if ($type === 'khach_thue' && $me['loai_cu_dan'] !== 'chu_ho') throw new BusinessException('Chỉ Chủ hộ được khai báo Khách thuê.');
        if (mb_strlen($d['ho_ten'] ?? '') < 3) throw new BusinessException('Vui lòng nhập họ và tên.');
        if (empty($d['ngay_sinh']) || $d['ngay_sinh'] > today()) throw new BusinessException('Ngày sinh không hợp lệ.');
        if (empty($d['quan_he_chu_ho'])) throw new BusinessException('Vui lòng chọn quan hệ với chủ hộ.');
        $cccd = $d['so_cccd'] ?: null;
        $age = (int)date_diff(date_create($d['ngay_sinh']), date_create(today()))->y;
        if ($age >= 14 && !$cccd) throw new BusinessException('Người từ 14 tuổi trở lên bắt buộc khai báo số CCCD.');
        if ($cccd && !$photo) throw new BusinessException('Vui lòng đính kèm ảnh chụp CCCD.');
        if ($type === 'khach_thue') {
            if (!preg_match('/^0\d{9}$/', $d['so_dien_thoai'] ?? '')) throw new BusinessException('Khách thuê phải có số điện thoại hợp lệ để cấp tài khoản.');
            if (empty($d['ngay_bat_dau_hop_dong']) || empty($d['ngay_ket_thuc_hop_dong']) || $d['ngay_ket_thuc_hop_dong'] < $d['ngay_bat_dau_hop_dong'])
                throw new BusinessException('Thời hạn hợp đồng thuê không hợp lệ.');
            if (!$photo) throw new BusinessException('Vui lòng đính kèm ảnh hợp đồng thuê.');
        }
        try {
            DB::q("INSERT INTO nhan_khau(ho_ten, ngay_sinh, gioi_tinh, so_dien_thoai, email, so_cccd, quan_he_chu_ho, loai_cu_dan,
                     so_hop_dong, ngay_bat_dau_hop_dong, ngay_ket_thuc_hop_dong, anh_giay_to, ID_can_ho)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$d['ho_ten'], $d['ngay_sinh'], $d['gioi_tinh'] ?: null, $d['so_dien_thoai'] ?: null, $d['email'] ?: null, $cccd,
                 $type === 'khach_thue' ? 'Khách thuê' : $d['quan_he_chu_ho'], $type,
                 $type === 'khach_thue' ? ($d['so_hop_dong'] ?: null) : null,
                 $type === 'khach_thue' ? $d['ngay_bat_dau_hop_dong'] : null,
                 $type === 'khach_thue' ? $d['ngay_ket_thuc_hop_dong'] : null, $photo, $me['ID_can_ho']]);
        } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
        $id = DB::id();
        AuditService::log('khai_bao_nhan_khau', 'nhan_khau', $id, $d['ho_ten']);
        return $id;
    }

    /** Lễ tân duyệt hồ sơ. Khách thuê được cấp tài khoản (mật khẩu tạm, bắt buộc đổi) */
    public static function approve(int $id): ?array
    {
        return DB::tx(function () use ($id) {
            $nk = DB::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau = ? FOR UPDATE", [$id]);
            if (!$nk || $nk['trang_thai_duyet'] !== 'cho_duyet') throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
            $acc = null;
            if ($nk['loai_cu_dan'] === 'khach_thue') {
                $pw = AccountService::tempPassword();
                try {
                    DB::q("INSERT INTO tai_khoan(ten_dang_nhap, mat_khau_bam, ho_ten, email, so_dien_thoai, vai_tro, phai_doi_mat_khau)
                           VALUES (?,?,?,?,?,'khach_thue',1)",
                        [$nk['so_dien_thoai'], password_hash($pw, PASSWORD_BCRYPT), $nk['ho_ten'], $nk['email'], $nk['so_dien_thoai']]);
                } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
                $uid = DB::id();
                DB::q("UPDATE nhan_khau SET ID_tai_khoan = ? WHERE ID_nhan_khau = ?", [$uid, $id]);
                $acc = ['username' => $nk['so_dien_thoai'], 'password' => $pw];
            }
            DB::q("UPDATE nhan_khau SET trang_thai_duyet='da_duyet', ngay_duyet=?, ID_nguoi_duyet=? WHERE ID_nhan_khau=?",
                [now(), Auth::id(), $id]);
            self::syncOccupancy((int)$nk['ID_can_ho']);
            AuditService::log('duyet_nhan_khau', 'nhan_khau', $id, $nk['ho_ten']);
            return $acc;
        });
    }
    public static function reject(int $id, string $reason): void
    {
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng nhập lý do từ chối.');
        $n = DB::q("UPDATE nhan_khau SET trang_thai_duyet='tu_choi', ly_do_tu_choi=?, ngay_duyet=?, ID_nguoi_duyet=?
                    WHERE ID_nhan_khau=? AND trang_thai_duyet='cho_duyet'", [$reason, now(), Auth::id(), $id])->rowCount();
        if (!$n) throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
        AuditService::log('tu_choi_nhan_khau', 'nhan_khau', $id, $reason);
    }
    /** Chủ hộ báo thành viên chuyển đi (không xóa cứng để giữ lịch sử) */
    public static function remove(array $me, int $id): void
    {
        if ($me['loai_cu_dan'] !== 'chu_ho') throw new BusinessException('Chỉ Chủ hộ được cập nhật thành viên chuyển đi.');
        $nk = DB::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau=? AND ID_can_ho=?", [$id, $me['ID_can_ho']]);
        if (!$nk || $nk['loai_cu_dan'] !== 'thanh_vien') throw new BusinessException('Chỉ cập nhật được thành viên trong hộ.');
        DB::q("UPDATE nhan_khau SET trang_thai_duyet='da_roi_di' WHERE ID_nhan_khau=?", [$id]);
        DB::q("UPDATE the_ra_vao SET trang_thai_the='da_huy' WHERE ID_nhan_khau=?", [$id]);
        AuditService::log('thanh_vien_chuyen_di', 'nhan_khau', $id, $nk['ho_ten']);
    }
    /** Hết hạn hợp đồng: khóa tài khoản khách thuê, hủy thẻ, cập nhật trạng thái căn hộ */
    public static function expireTenant(int $nkId): void
    {
        DB::tx(function () use ($nkId) {
            $nk = DB::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau=?", [$nkId]);
            DB::q("UPDATE nhan_khau SET trang_thai_duyet='da_roi_di' WHERE ID_nhan_khau=?", [$nkId]);
            if ($nk['ID_tai_khoan']) DB::q("UPDATE tai_khoan SET trang_thai=0 WHERE ID_tai_khoan=?", [$nk['ID_tai_khoan']]);
            DB::q("UPDATE the_ra_vao SET trang_thai_the='da_huy' WHERE ID_nhan_khau=?", [$nkId]);
            self::syncOccupancy((int)$nk['ID_can_ho']);
            AuditService::log('het_han_hop_dong', 'nhan_khau', $nkId, $nk['ho_ten'], $nk['ID_tai_khoan'] ? (int)$nk['ID_tai_khoan'] : null);
        });
    }
}

/* ======================= THẺ TỪ & VÂN TAY (UC-RES03, UC-REC02) ======================= */
final class CardService
{
    public static function issue(string $maCanHo, string $loai, string $rfid, string $vanTay, ?int $nkId = null): int
    {
        $ch = DB::one("SELECT * FROM can_ho WHERE ma_can_ho = ?", [strtoupper($maCanHo)]);
        if (!$ch) throw new BusinessException('Không tìm thấy mã căn hộ.');
        $nk = $nkId ? DB::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau=? AND ID_can_ho=? AND trang_thai_duyet='da_duyet'", [$nkId, $ch['ID_can_ho']])
            : DB::one("SELECT * FROM nhan_khau WHERE ID_can_ho=? AND trang_thai_duyet='da_duyet' AND ID_tai_khoan IS NOT NULL
                        ORDER BY loai_cu_dan='khach_thue' DESC LIMIT 1", [$ch['ID_can_ho']]);
        if (!$nk) throw new BusinessException('Căn hộ chưa có cư dân đã duyệt để cấp thẻ.');
        if (!isset(LABELS['loai_the'][$loai])) throw new BusinessException('Loại thẻ không hợp lệ.');
        $rfid = strtoupper(trim($rfid)) ?: null;
        $vanTay = strtoupper(trim($vanTay)) ?: null;
        if ($loai === 'van_tay' && !$vanTay) throw new BusinessException('Vui lòng nạp mã ID vân tay từ thiết bị đọc.');
        if ($loai !== 'van_tay' && !$rfid) throw new BusinessException('Vui lòng quẹt thẻ để nạp mã chip RFID.');
        if ($loai === 'van_tay') $rfid = null; else $vanTay = null;
        try {
            DB::q("INSERT INTO the_ra_vao(ma_chip_rfid, ma_id_van_tay, loai_the, ID_nhan_khau) VALUES (?,?,?,?)",
                [$rfid, $vanTay, $loai, $nk['ID_nhan_khau']]);
        } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
        $id = DB::id();
        AuditService::log('cap_the', 'the_ra_vao', $id, "{$ch['ma_can_ho']} - " . ($rfid ?? $vanTay));
        return $id;
    }
    public static function setState(int $id, string $state, ?int $canHoOwner = null): void
    {
        $c = DB::one("SELECT t.*, nk.ID_can_ho FROM the_ra_vao t JOIN nhan_khau nk ON nk.ID_nhan_khau=t.ID_nhan_khau WHERE ID_the=?", [$id]);
        if (!$c || ($canHoOwner && (int)$c['ID_can_ho'] !== $canHoOwner)) throw new BusinessException('Không tìm thấy thẻ.');
        if ($c['trang_thai_the'] === 'da_huy') throw new BusinessException('Thẻ đã hủy.');
        DB::q("UPDATE the_ra_vao SET trang_thai_the=? WHERE ID_the=?", [$state, $id]);
        AuditService::log('cap_nhat_the', 'the_ra_vao', $id, $state);
    }
}

/* ======================= THẺ XE (UC-RES04, UC-REC03) ======================= */
final class VehicleService
{
    const LIMIT = ['o_to' => 2, 'xe_may' => 4];

    public static function normalizePlate(string $p): string
    {
        return strtoupper(preg_replace('/\s+/', '', $p));
    }
    /** Số xe đang chiếm định mức (chờ duyệt + yêu cầu bổ sung + đã duyệt) */
    public static function used(int $canHo, string $type, ?int $exclude = null, bool $approvedOnly = false): int
    {
        $st = $approvedOnly ? "('da_duyet')" : "('cho_duyet','yeu_cau_bo_sung','da_duyet')";
        return (int)DB::val("SELECT COUNT(*) FROM the_xe WHERE ID_can_ho=? AND loai_xe=? AND trang_thai_duyet IN $st
                             AND ID_the_xe <> ?", [$canHo, $type, $exclude ?? 0]);
    }
    public static function register(array $me, array $d, ?string $photo): int
    {
        $type = $d['loai_xe'] ?? '';
        if (!isset(self::LIMIT[$type])) throw new BusinessException('Vui lòng chọn loại xe.');
        $plate = self::normalizePlate($d['bien_so_xe'] ?? '');
        if (!preg_match('/^\d{2}[A-Z]{1,2}\d?-(\d{3}\.?\d{2}|\d{4})$/', $plate))
            throw new BusinessException('Biển số không đúng định dạng biển số xe Việt Nam (VD: 51G-102.24, 59X3-343.41).');
        if (mb_strlen($d['nhan_hieu'] ?? '') < 2 || mb_strlen($d['mau_xe'] ?? '') < 2) throw new BusinessException('Vui lòng nhập nhãn hiệu và màu xe.');
        if (!$photo) throw new BusinessException('Vui lòng đính kèm ảnh cà vẹt xe.');
        return DB::tx(function () use ($me, $d, $type, $plate, $photo) {
            DB::q("SELECT ID_can_ho FROM can_ho WHERE ID_can_ho=? FOR UPDATE", [$me['ID_can_ho']]);   // tuần tự hóa theo căn hộ
            if (self::used((int)$me['ID_can_ho'], $type) >= self::LIMIT[$type])
                throw new BusinessException('Căn hộ đã đạt định mức tối đa ' . self::LIMIT[$type] . ' ' . lbl('loai_xe', $type) . '.');
            try {
                DB::q("INSERT INTO the_xe(bien_so_xe, loai_xe, nhan_hieu, mau_xe, anh_ca_vet_xe, ID_can_ho, ID_nhan_khau)
                       VALUES (?,?,?,?,?,?,?)", [$plate, $type, $d['nhan_hieu'], $d['mau_xe'], $photo, $me['ID_can_ho'], $me['ID_nhan_khau']]);
            } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
            $id = DB::id();
            AuditService::log('dang_ky_the_xe', 'the_xe', $id, $plate);
            return $id;
        });
    }
    /** Cư dân bổ sung hồ sơ khi Lễ tân yêu cầu (giữ nguyên bản ghi để lưu lịch sử) */
    public static function resubmit(array $me, int $id, ?string $photo): void
    {
        $x = DB::one("SELECT * FROM the_xe WHERE ID_the_xe=? AND ID_can_ho=?", [$id, $me['ID_can_ho']]);
        if (!$x || $x['trang_thai_duyet'] !== 'yeu_cau_bo_sung') throw new BusinessException('Hồ sơ không ở trạng thái yêu cầu bổ sung.');
        DB::q("UPDATE the_xe SET trang_thai_duyet='cho_duyet', anh_ca_vet_xe=COALESCE(?, anh_ca_vet_xe), ngay_gui=? WHERE ID_the_xe=?",
            [$photo, now(), $id]);
        AuditService::log('bo_sung_the_xe', 'the_xe', $id);
    }
    public static function approve(int $id): void
    {
        DB::tx(function () use ($id) {
            $x = DB::one("SELECT * FROM the_xe WHERE ID_the_xe=?", [$id]);
            if (!$x || $x['trang_thai_duyet'] !== 'cho_duyet') throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
            DB::q("SELECT ID_can_ho FROM can_ho WHERE ID_can_ho=? FOR UPDATE", [$x['ID_can_ho']]);
            if (self::used((int)$x['ID_can_ho'], $x['loai_xe'], $id, true) >= self::LIMIT[$x['loai_xe']])
                throw new BusinessException('Căn hộ này đã đạt định mức tối đa ' . self::LIMIT[$x['loai_xe']] . ' ' . lbl('loai_xe', $x['loai_xe']) . '.');
            DB::q("UPDATE the_xe SET trang_thai_duyet='da_duyet', ngay_duyet=?, ID_nguoi_duyet=?, ly_do=NULL WHERE ID_the_xe=?",
                [now(), Auth::id(), $id]);
            AuditService::log('duyet_the_xe', 'the_xe', $id, $x['bien_so_xe']);
        });
    }
    public static function decline(int $id, string $state, string $reason): void
    {
        if (!in_array($state, ['tu_choi', 'yeu_cau_bo_sung'], true)) throw new BusinessException('Thao tác không hợp lệ.');
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng nhập lý do.');
        $n = DB::q("UPDATE the_xe SET trang_thai_duyet=?, ly_do=?, ngay_duyet=?, ID_nguoi_duyet=? WHERE ID_the_xe=? AND trang_thai_duyet='cho_duyet'",
            [$state, $reason, now(), Auth::id(), $id])->rowCount();
        if (!$n) throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
        AuditService::log($state === 'tu_choi' ? 'tu_choi_the_xe' : 'yeu_cau_bo_sung', 'the_xe', $id, $reason);
    }
    public static function cancel(array $me, int $id): void
    {
        $n = DB::q("UPDATE the_xe SET trang_thai_duyet='da_huy', ly_do='Cư dân hủy thẻ' WHERE ID_the_xe=? AND ID_can_ho=?
                    AND trang_thai_duyet IN ('cho_duyet','yeu_cau_bo_sung','da_duyet')", [$id, $me['ID_can_ho']])->rowCount();
        if (!$n) throw new BusinessException('Không thể hủy thẻ xe này.');
        AuditService::log('huy_the_xe', 'the_xe', $id);
    }
}
