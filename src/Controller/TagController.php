<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AccessControl;
use App\Service\TagService;
use App\Support\Security;

final class TagController extends BaseController
{
    public function index(int $id): void
    {
        $this->requireAuth();

        if (!AccessControl::canRead($id)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền xem nhãn trong thư viện này.']);
        }

        $tags = TagService::listForLibrary($id);

        $this->json(['tags' => $tags]);
    }

    public function store(int $id): void
    {
        $this->requireAuth();

        if (!AccessControl::canWrite($id)) {
            $this->json(['error' => 'Không có quyền tạo nhãn.'], 403);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token không hợp lệ.'], 400);
        }

        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#3b6ea5');

        if ($name === '') {
            $this->json(['error' => 'Tên nhãn không được để trống.'], 400);
        }

        try {
            $tagId = TagService::create($id, $name, $color);
            $tag = \App\Support\Database::first('SELECT * FROM tags WHERE id = :id', [':id' => $tagId]);
            $this->json(['tag' => $tag, 'message' => 'Tạo nhãn thành công!']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(int $id, int $tagId): void
    {
        $this->requireAuth();

        if (!AccessControl::canWrite($id)) {
            $this->json(['error' => 'Không có quyền sửa nhãn.'], 403);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token không hợp lệ.'], 400);
        }

        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? null;

        if ($name === '') {
            $this->json(['error' => 'Tên nhãn không được để trống.'], 400);
        }

        try {
            TagService::rename($tagId, $id, $name, $color);
            $this->json(['message' => 'Cập nhật nhãn thành công!']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(int $id, int $tagId): void
    {
        $this->requireAuth();

        if (!AccessControl::canWrite($id)) {
            $this->json(['error' => 'Không có quyền xóa nhãn.'], 403);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token không hợp lệ.'], 400);
        }

        TagService::delete($tagId, $id);
        $this->json(['message' => 'Xóa nhãn thành công!']);
    }

    public function attachToPage(int $pageId): void
    {
        $this->requireAuth();

        $page = \App\Support\Database::first(
            'SELECT library_id FROM saved_pages WHERE id = :id',
            [':id' => $pageId],
        );
        if ($page === null) {
            $this->json(['error' => 'Bài viết không tồn tại.'], 404);
        }

        $libraryId = (int) $page['library_id'];
        if (!AccessControl::canWrite($libraryId)) {
            $this->json(['error' => 'Không có quyền gắn nhãn.'], 403);
        }

        $tagId = (int) ($_POST['tag_id'] ?? 0);
        if ($tagId <= 0) {
            $this->json(['error' => 'ID nhãn không hợp lệ.'], 400);
        }

        try {
            TagService::attach($pageId, $tagId, $libraryId);
            $tags = TagService::tagsOfPage($pageId);
            $this->json(['tags' => $tags, 'message' => 'Đã gắn nhãn.']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function detachFromPage(int $pageId, int $tagId): void
    {
        $this->requireAuth();

        $page = \App\Support\Database::first(
            'SELECT library_id FROM saved_pages WHERE id = :id',
            [':id' => $pageId],
        );
        if ($page === null) {
            $this->json(['error' => 'Bài viết không tồn tại.'], 404);
        }

        $libraryId = (int) $page['library_id'];
        if (!AccessControl::canWrite($libraryId)) {
            $this->json(['error' => 'Không có quyền.'], 403);
        }

        TagService::detach($pageId, $tagId);
        $tags = TagService::tagsOfPage($pageId);
        $this->json(['tags' => $tags, 'message' => 'Đã gỡ nhãn.']);
    }
}