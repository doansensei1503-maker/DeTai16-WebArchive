<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\Auth;

final class HomeController extends BaseController
{
    public function index(): void
    {
        if (Auth::check()) {
            $this->redirect('/libraries');
        }

        $this->render('home.php', [
            // Title KHONG chua ten san pham — layout tu noi them "· DeTai16".
            'title' => 'Lưu trang Web để đọc lại và tìm kiếm',
        ]);
    }
}