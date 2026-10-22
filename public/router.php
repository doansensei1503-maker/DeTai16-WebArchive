<?php

declare(strict_types=1);

/**
 * PHP built-in server router.
 *
 * Chay: php -S 127.0.0.1:8000 -t public public/router.php
 *
 * Tren Windows + PHP CLI server, URL dau "/" khong tu dong nhay vao
 * index.php nen ta xu ly truong hop dac biet: chi dinh include luon
 * index.php de chao mung nguoi dung (HomeController::index), cong them
 * phan thuong: neu khoa thu muc co file that, tra false de PHP phuc vu.
 */

// Loi favicon de tranh loi 404 dang so trong development log.
if ($_SERVER['REQUEST_URI'] === '/favicon.ico') {
    http_response_code(204);
    exit;
}

$file = __DIR__ . $_SERVER['REQUEST_URI'];

if ($_SERVER['REQUEST_URI'] !== '/' && is_file($file) && !str_starts_with($file, __DIR__ . '/..')) {
    return false;
}

// Tat ca request con lai (ke ca "/") di qua front controller.
require __DIR__ . '/index.php';