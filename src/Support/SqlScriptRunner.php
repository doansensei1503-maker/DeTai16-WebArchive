<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tach va chay cac file SQL (schema / seed) mot cach an toan.
 *
 * Khong dung regex don gian de tach cau lenh vi se hong khi:
 *   - cau lenh chua dau phay trong chuoi (dien thoai, gia tri mac dinh)
 *   - comment "--" chua dau phay
 *   - chuoi nhieu dong
 *
 * Cach nay dung state machine theo tung ky tu.
 */
final class SqlScriptRunner
{
    /**
     * Doc file SQL va tra ve danh sach cau lenh da tach.
     *
     * @return array<int,string>
     */
    public static function parse(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Het comment mot dong.
            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            // Het comment nhieu dong.
            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                } elseif ($char === "\n") {
                    $buffer .= $char; // giu newline de statement moi tach dung
                }
                continue;
            }

            // Trong chuoi '...' (escape bang \' hoac '').
            if ($inSingle) {
                $buffer .= $char;
                if ($char === '\\') {
                    if ($next !== '') {
                        $buffer .= $next;
                        $i++;
                    }
                } elseif ($char === "'") {
                    if ($next === "'") {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $inSingle = false;
                    }
                }
                continue;
            }

            // Trong chuoi "..." hoac `...`.
            if ($inDouble || $inBacktick) {
                $buffer .= $char;
                if ($char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                } elseif ($char === '"' && $inDouble) {
                    $inDouble = false;
                } elseif ($char === '`' && $inBacktick) {
                    $inBacktick = false;
                }
                continue;
            }

            // Bat dau comment.
            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }

            if ($char === '#') {
                $inLineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }

            // Bat dau / ket thuc chuoi.
            if ($char === "'") {
                $inSingle = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '"') {
                $inDouble = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '`') {
                $inBacktick = true;
                $buffer .= $char;
                continue;
            }

            // Ket thuc cau lenh.
            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * Doc file va chay cac cau lenh.
     *
     * @param  callable(string):void|null $onError
     * @return array{ok:int, failed:int, errors:array<int,string>}
     */
    public static function runFile(string $path, \PDO $pdo, ?callable $onError = null): array
    {
        if (!is_file($path)) {
            return ['ok' => 0, 'failed' => 0, 'errors' => ["Khong tim thay file: {$path}"]];
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            return ['ok' => 0, 'failed' => 0, 'errors' => ["Khong doc duoc file: {$path}"]];
        }

        return self::run($sql, $pdo, $onError);
    }

    /**
     * @param  callable(string):void|null $onError
     * @return array{ok:int, failed:int, errors:array<int,string>}
     */
    public static function run(string $sql, \PDO $pdo, ?callable $onError = null): array
    {
        $statements = self::parse($sql);
        $ok = 0;
        $failed = 0;
        $errors = [];

        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $preview = substr(preg_replace('/\s+/', ' ', $stmt) ?? '', 0, 90);
                $msg = $preview . ' -> ' . $e->getMessage();
                $errors[] = $msg;
                if ($onError !== null) {
                    $onError($msg);
                }
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'errors' => $errors];
    }
}