<?php

declare(strict_types=1);

namespace App;

/**
 * Public entry point: bootstrap + front controller.
 */
require __DIR__ . '/../vendor/autoload.php';
// KHONG require .env — Bootstrap::boot() doc file nay bang Env::load() an toan.
// Require se in noi dung file (gom ca mat khau) ra man hinh.

\App\Support\Bootstrap::boot();
\App\Support\Security::headers();

$app = new App();
$app->run();