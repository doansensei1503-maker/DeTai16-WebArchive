<?php

declare(strict_types=1);

/**
 * KIEM THU WEB APP PHAI DIEN THAT SU — DeTai16
 *
 * Mo phong trinh duyet: luu cookie, lay CSRF token, dang nhap, xem libraries.
 */

$BASE = 'http://127.0.0.1:8000';

$jar = __DIR__ . '/tmp_cookies.txt';
@unlink($jar);

$pass = 0;
$fail = 0;
$failures = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        $failures[] = $label . ($detail !== '' ? ' — ' . $detail : '');
        echo "  [FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

// ----- GET /login: cookies va CSRF -----
echo "\n=== 1. GET /login ===\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $BASE . '/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => false,
]);
$resp = curl_exec($ch);
$headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr((string) $resp, 0, (int) $headerLen);
$body = substr((string) $resp, (int) $headerLen);
curl_close($ch);

check('GET /login HTTP 200', str_contains($headers, '200'), substr($headers, 0, 200));
check('Cookie session duoc set', str_contains($headers, 'detai16_session'), '');
check('Co CSRF token trong HTML', preg_match('/name="_token"\s+value="([a-f0-9]+)"/i', $body, $m) === 1, '');
$csrfToken = $m[1] ?? '';
check('CSRF token dai 64 hex ky tu', strlen($csrfToken) === 64, strlen($csrfToken) . ' ky tu');

// ----- POST /login -----
echo "\n=== 2. POST /login voi admin/admin1234 ===\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $BASE . '/login',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        '_token'     => $csrfToken,
        'identifier' => 'admin',
        'password'    => 'admin1234',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER     => ['Referer: ' . $BASE . '/login'],
]);
$resp = curl_exec($ch);
$headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr((string) $resp, 0, (int) $headerLen);
$body = substr((string) $resp, (int) $headerLen);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

check('POST /login thanh cong (302 hoac 200 redirect)', $code === 302 || $code === 200, "HTTP $code");
check('Location header hop le (sau dang nhap)', preg_match('/Location:\s*([^\r\n]+)/i', $headers, $lm) === 1, $lm[1] ?? '(none)');

// ----- GET /libraries (auth required) -----
echo "\n=== 3. GET /libraries (da dang nhap) ===\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $BASE . '/libraries',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_HEADER         => false,
    CURLOPT_FOLLOWLOCATION => true,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

check('GET /libraries HTTP 200', $code === 200, "HTTP $code final=$finalUrl");
check('Tieu de "Thư viện của tôi" xuat hien', str_contains($body, 'Thư viện') || str_contains($body, 'thu vien'), '');
check('It nhat 1 trong 3 thu vien seed (CNTT & Lap Trinh, Khoa Hoc, Thu Vien Rieng)',
    str_contains($body, 'CNTT') || str_contains($body, 'Lap Trinh') || str_contains($body, 'Khoa Hoc'),
    '');

// ----- GET /libraries/1 -----
echo "\n=== 4. GET /libraries/1 ===\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $BASE . '/libraries/1',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_FOLLOWLOCATION => true,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

check('GET /libraries/1 HTTP 200 hoac redirect 302 (CNTT)', in_array($code, [200, 302], true), "HTTP $code");
check('Ten thu vien xuat hien', str_contains($body, 'CNTT') || str_contains($body, 'Lap Trinh'), '');

// ----- GET /asset/{hash} - public asset endpoint (404, 403 la cac phan hoi chinh xac) -----
echo "\n=== 5. GET /asset/missing (404 expected) ===\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $BASE . '/asset/invalid_hash_xyz',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

// AssetController tra ve 404 + anh placeholder neu hash khong ton tai
// hoac 403 neu khong co quyen. Ca hai deu chinh xac.
check('GET /asset/invalid_hash tra ve 404 (cho missing) hoac 403 (cho permission)', in_array($code, [403, 404], true), "HTTP $code");

// ----- POST logout -----
echo "\n=== 6. GET /logout (cleanup) ===\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $BASE . '/logout',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_FOLLOWLOCATION => false,
]);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

check('GET /logout clear session', $code === 200 || $code === 302, "HTTP $code");

@unlink($jar);

echo "\n========== KET QUA ==========\n";
echo "Tong: " . ($pass + $fail) . " | PASS: {$pass} | FAIL: {$fail}\n";

if ($failures !== []) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}

exit($fail === 0 ? 0 : 1);