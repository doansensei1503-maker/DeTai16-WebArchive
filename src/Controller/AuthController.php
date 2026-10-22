<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use App\Support\Auth;
use App\Support\Security;

final class AuthController extends BaseController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/libraries');
        }

        $this->render('auth/login.php', [
            'title' => 'Đăng nhập',
            'csrf'  => $this->csrfField(),
        ]);
    }

    public function login(): void
    {
        // CSRF: dong bo voi cac action khac (register, doi mat khau, xoa bai...).
        // Trang /login dang form POST nen can xac thuc token de tranh CSRF.
        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ. Vui lòng thử lại.']);
        }

        // Form HTML dat ten truong la `identifier` (chap nhan ca username va email,
        // xem Auth::attempt). Doc ca `email` de tuong thich nguoc voi phien ban cu.
        $identifier = trim((string) ($_POST['identifier'] ?? $_POST['email'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->backWithErrors(['general' => 'Vui lòng nhập tên đăng nhập và mật khẩu.']);
        }

        $user = Auth::attempt($identifier, $password);
        if ($user === null) {
            $this->backWithErrors(['general' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
        }

        $this->success('Đăng nhập thành công!');
        $this->redirect('/libraries');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            $this->redirect('/libraries');
        }

        $this->render('auth/register.php', [
            'title' => 'Đăng ký tài khoản',
            'csrf'  => $this->csrfField(),
        ]);
    }

    public function register(): void
    {
        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ. Vui lòng thử lại.']);
        }

        $username     = trim($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? '';
        $password2    = $_POST['password2'] ?? '';
        $displayName  = trim($_POST['display_name'] ?? '');

        $errors = [];
        if ($username === '') {
            $errors['username'] = 'Tên đăng nhập không được để trống.';
        }
        if ($email === '') {
            $errors['email'] = 'Email không được để trống.';
        }
        if ($password === '') {
            $errors['password'] = 'Mật khẩu không được để trống.';
        }
        if ($password !== $password2) {
            $errors['password2'] = 'Mật khẩu xác nhận không khớp.';
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, [
                'username'     => $username,
                'email'        => $email,
                'display_name' => $displayName,
            ]);
        }

        try {
            UserService::register($username, $email, $password, $displayName);
        } catch (\InvalidArgumentException $e) {
            $this->backWithErrors(['general' => $e->getMessage()], [
                'username'     => $username,
                'email'        => $email,
                'display_name' => $displayName,
            ]);
        }

        $this->success('Đăng ký thành công! Vui lòng đăng nhập.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->success('Đã đăng xuất.');
        $this->redirect('/login');
    }
}