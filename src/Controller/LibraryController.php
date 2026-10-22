<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AccessControl;
use App\Service\LibraryService;
use App\Support\Auth;
use App\Support\Security;

final class LibraryController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $libs = AccessControl::librariesForUser();

        $this->render('libraries/index.php', [
            'title'    => 'Thư viện của tôi',
            'libraries' => $libs,
            'csrf'     => $this->csrfField(),
        ]);
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        if (!AccessControl::canRead($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền truy cập thư viện này.']);
        }

        $library = LibraryService::find($id);
        if ($library === null) {
            $this->backWithErrors(['general' => 'Thư viện không tồn tại.']);
        }

        $pages = LibraryService::pagesInLibrary($id);
        $stats = LibraryService::libraryStats($id);
        $csrf = Security::token();

        $this->render('libraries/show.php', [
            // Title phai la DU LIEU THO — layout tu escape khi in ra <title>.
            // Truoc day truyen san $this->h() gay escape 2 lan (& -> &amp;amp;).
            'title'    => (string) $library['name'],
            'library'  => $library,
            'pages'    => $pages,
            'stats'    => $stats,
            'csrf'     => $this->csrfField(),
            'canWrite' => AccessControl::canWrite($id),
            'canManage' => AccessControl::canManage($id),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $userId = $this->userId();

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ. Vui lòng thử lại.']);
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $this->backWithErrors(['name' => 'Tên thư viện không được để trống.'], ['name' => $name]);
        }

        try {
            $libraryId = LibraryService::create($userId, $name, $description);
            $this->success('Tạo thư viện thành công!');
            $this->redirect("/libraries/{$libraryId}");
        } catch (\InvalidArgumentException $e) {
            $this->backWithErrors(['general' => $e->getMessage()], ['name' => $name, 'description' => $description]);
        }
    }

    public function update(int $id): void
    {
        $this->requireAuth();

        if (!AccessControl::canManage($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền chỉnh sửa thư viện này.']);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ.']);
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $this->backWithErrors(['name' => 'Tên thư viện không được để trống.'], ['name' => $name]);
        }

        try {
            LibraryService::update($id, $name, $description);
            $this->success('Cập nhật thư viện thành công!');
        } catch (\InvalidArgumentException $e) {
            $this->backWithErrors(['general' => $e->getMessage()]);
        }

        $this->redirect("/libraries/{$id}");
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();

        if (!AccessControl::canManage($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền xóa thư viện này.']);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ.']);
        }

        // Verify owner.
        if (!AccessControl::isOwner($id)) {
            $this->backWithErrors(['general' => 'Chỉ chủ sở hữu mới có thể xóa thư viện.']);
        }

        LibraryService::delete($id);
        $this->success('Xóa thư viện thành công!');
        $this->redirect('/libraries');
    }
}