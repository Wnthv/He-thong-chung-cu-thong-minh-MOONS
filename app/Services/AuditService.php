<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\NhatKyThaoTac;
use App\Support\Db;
use App\Support\Rbac;

/* ======================= NHẬT KÝ THAO TÁC (Audit Log) ======================= */
final class AuditService
{
    public static function log(string $action, string $object, $objectId = null, ?string $content = null, ?int $userId = null): void
    {
        NhatKyThaoTac::create([
            'hanh_dong' => $action,
            'doi_tuong' => $object,
            'ID_doi_tuong' => $objectId === null ? null : (string)$objectId,
            'noi_dung' => $content === null ? null : mb_substr($content, 0, 1000),
            'dia_chi_ip' => client_ip(),
            'ID_tai_khoan' => $userId ?? Rbac::id(),
        ]);
    }
}
