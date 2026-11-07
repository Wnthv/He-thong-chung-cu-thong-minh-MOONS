<?php

namespace App\Support;

use Illuminate\Support\Facades\DB as LaravelDB;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Lớp truy vấn SQL gọn nhẹ chạy trên kết nối CSDL của Laravel (config/database.php, file .env).
 * Dùng câu lệnh chuẩn bị sẵn (prepared statement) để chống SQL Injection; kết quả trả về mảng kết hợp.
 */
final class Db
{
    public static function pdo(): PDO
    {
        return LaravelDB::connection()->getPdo();
    }

    public static function q(string $sql, array $p = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->setFetchMode(PDO::FETCH_ASSOC);
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
}
