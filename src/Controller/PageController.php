<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\FetchStatus;
use App\Service\AccessControl;
use App\Service\FetchLogService;
use App\Service\LibraryService;
use App\Support\Security;

/**
 * PageController: tiep nhan URL + kich hoat lay noi dung (CN1D + CN2).
 * Xu ly dien ra dong bo, ket qua tra ve qua trang chi tiet.
 */
final class PageController extends BaseController
{
    public function index(int $libraryId): void
    {
        $this->requireAuth();

        if (!AccessControl::canRead($libraryId)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền truy cập thư viện này.']);
        }

        $library = LibraryService::find($libraryId);
        if ($library === null) {
            $this->backWithErrors(['general' => 'Thư viện không tồn tại.']);
        }

        $pages = LibraryService::pagesInLibrary($libraryId);

        $this->render('pages/index.php', [
            'title'    => 'Bài đã lưu · ' . (string) $library['name'],
            'library'  => $library,
            'pages'    => $pages,
            'csrf'     => $this->csrfField(),
            'canWrite' => AccessControl::canWrite($libraryId),
        ]);
    }

    /**
     * Nhan URL -> kiem tra -> lay HTML -> trich xuat -> luu.
     * Day la endpoint chinh cua CN1D + CN2.
     */
    public function store(int $libraryId): void
    {
        $this->requireAuth();
        $userId = $this->userId();

        if (!AccessControl::canWrite($libraryId)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền lưu bài trong thư viện này.']);
        }

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ. Vui lòng thử lại.']);
        }

        $url = trim($_POST['url'] ?? '');
        if ($url === '') {
            $this->backWithErrors(['url' => 'Vui lòng nhập địa chỉ URL.'], ['url' => $url]);
        }

        // Tu dong them http:// neu khong co scheme.
        if (!str_starts_with(strtolower($url), 'http://') && !str_starts_with(strtolower($url), 'https://')) {
            $url = 'https://' . $url;
        }

        // Tao fetch log truoc de theo doi trang thai.
        $logId = FetchLogService::create($libraryId, $userId, $url);

        // Kiem tra URL (CN1D).
        $validator = new \App\Security\UrlValidator();
        $result = $validator->validate($url);

        if (!$result->allowed) {
            FetchLogService::update(
                $logId,
                FetchStatus::NotAllowed,
                message: $result->message,
                httpCode: null,
            );
            $this->backWithErrors(
                ['url' => $result->message],
                ['url' => $url],
            );
        }

        // Lay HTML + trich xuat (CN2).
        $fetcher = new \App\Service\ArticleFetcher($validator->allowList());
        $fetchResult = $fetcher->fetch($url, $logId, $libraryId, $userId);

        if (!$fetchResult['success']) {
            FetchLogService::update(
                $logId,
                $fetchResult['status'],
                $fetchResult['final_url'] ?? $url,
                $fetchResult['http_code'] ?? null,
                $fetchResult['message'] ?? null,
                $fetchResult['redirect_chain'] ?? null,
                $fetchResult['duration_ms'] ?? null,
            );
            $this->backWithErrors(
                ['url' => $fetchResult['message'] ?? 'Không lấy được nội dung.'],
                ['url' => $url],
            );
        }

        // Lưu bài viết (sẽ được xây dựng ở giai đoạn C - ArticleService).
        $pageId = \App\Service\ArticleService::save(
            $libraryId,
            $userId,
            $fetchResult['final_url'] ?? $url,
            $fetchResult['title'] ?? 'Không có tiêu đề',
            $fetchResult['author'] ?? '',
            $fetchResult['content_html'] ?? '',
            $fetchResult['content_text'] ?? '',
            $fetchResult['excerpt'] ?? '',
            $fetchResult['published_at'] ?? null,
            $fetchResult['language'] ?? 'vi',
            $fetchResult['layout_used'] ?? null,
            $logId,
        );

        FetchLogService::update($logId, FetchStatus::Saved);

        // Tao snapshot ngay (CN3).
        $snapshotId = \App\Service\SnapshotService::create($pageId, $libraryId);

        $this->success('Đã lưu bài viết thành công!');
        $this->redirect("/pages/{$pageId}");
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $page = \App\Support\Database::first(
            'SELECT sp.*, l.name AS library_name, l.id AS library_id,
                    u.username AS created_by_username
               FROM saved_pages sp
               JOIN libraries l ON l.id = sp.library_id
               JOIN users u ON u.id = sp.created_by
              WHERE sp.id = :id LIMIT 1',
            [':id' => $id],
        );

        if ($page === null) {
            $this->backWithErrors(['general' => 'Bài viết không tồn tại.']);
        }

        $libraryId = (int) $page['library_id'];
        if (!AccessControl::canRead($libraryId)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền xem bài viết này.']);
        }

        $tags = \App\Service\TagService::tagsOfPage($id);
        $snapshots = \App\Support\Database::all(
            'SELECT id, version_no, text_length, image_count, missing_image_count, created_at
               FROM snapshots WHERE saved_page_id = :p ORDER BY version_no DESC',
            [':p' => $id],
        );

        $this->render('pages/show.php', [
            'title'     => (string) ($page['title'] ?: 'Bài viết'),
            'page'      => $page,
            'tags'      => $tags,
            'snapshots' => $snapshots,
            'canWrite'  => AccessControl::canWrite($libraryId),
            'csrf'      => $this->csrfField(),
            // Token tho cho <script>: $csrf la HTML <input>, khong the nhung
            // truc tiep vao JavaScript.
            'csrfToken' => Security::token(),
        ]);
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();

        if (!Security::verifyToken($_POST['_token'] ?? '')) {
            $this->backWithErrors(['general' => 'Phiên làm việc không hợp lệ.']);
        }

        $page = \App\Support\Database::first(
            'SELECT id, library_id FROM saved_pages WHERE id = :id',
            [':id' => $id],
        );

        if ($page === null) {
            $this->backWithErrors(['general' => 'Bài viết không tồn tại.']);
        }

        $libraryId = (int) $page['library_id'];
        if (!AccessControl::canWrite($libraryId)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền xóa bài viết này.']);
        }

        \App\Support\Database::execute(
            'DELETE FROM saved_pages WHERE id = :id',
            [':id' => $id],
        );

        $this->success('Đã xóa bài viết.');
        $this->redirect("/libraries/{$libraryId}");
    }
}