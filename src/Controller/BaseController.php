<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Auth;
use App\Support\Security;

/**
 * Lop co so cho tat ca controller.
 */
abstract class BaseController
{
    protected function auth(): \App\Domain\User|null
    {
        return Auth::user();
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
    }

    protected function userId(): int|null
    {
        return Auth::id();
    }

    /**
     * Render mot template con, boc trong layout dung chung.
     *
     * Luu y: $template la duong dan TUONG DOI so voi views/ (vi du
     * 'libraries/show.php'). Layout duoc render truoc, sau do noi dung
     * template con duoc ghi vao bien $content roi layout moi duoc include.
     *
     * Cac template ban mo hop le doc lap (vi du read/show.php) co the bo
     * qua layout bang cach tu chua <!DOCTYPE html>.
     */
    protected function render(string $template, array $data = []): never
    {
        extract($data, EXTR_SKIP);

        // Token duoc tao ben trong Security::token() (lazy) — neu controller
        // chua truyen san 'csrf' (HTML <input>) thi tu tao token raw, va
        // template se goi $this->csrfField() de boc thanh input. Neu controller
        // truyen san 'csrf' (HTML <input>) thi khong ghi de.
        if (!isset($csrf)) {
            $csrf = Security::token();
        }
        $user = $this->auth();
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $templateFile = __DIR__ . '/../../views/' . ltrim($template, '/');

        // Template doc lap (da co <!DOCTYPE html>) — khong boc layout.
        $head = (string) @file_get_contents($templateFile, false, null, 0, 512);
        if (str_contains($head, '<!DOCTYPE html>') || str_contains($head, '<!doctype html>')) {
            include $templateFile;
            exit;
        }

        // Boc trong layout: render template con truoc, giu output vao $content.
        ob_start();
        include $templateFile;
        $content = (string) ob_get_clean();

        include __DIR__ . '/../../views/layout.php';
        exit;
    }

    /** @param array<string,string> $errors */
    protected function backWithErrors(array $errors, array $old = []): never
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $old;
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(Security::token(), ENT_QUOTES) . '">';
    }

    protected function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }

    /** @return array<string,string> */
    protected function errors(string $key = ''): array
    {
        $all = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        return $key === '' ? $all : ($all[$key] ?? '');
    }

    protected function success(string $message): void
    {
        $_SESSION['_success'] = $message;
    }

    protected function flashSuccess(): string
    {
        $msg = $_SESSION['_success'] ?? '';
        unset($_SESSION['_success']);

        return $msg;
    }

    protected function h(string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}