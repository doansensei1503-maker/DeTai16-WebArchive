<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\FetchStatus;
use App\Service\AccessControl;
use App\Service\FetchLogService;

/** API tra ve trang thai fetch log (CN1E). */
final class ApiController extends BaseController
{
    public function fetchStatus(int $logId): void
    {
        $log = FetchLogService::find($logId);
        if ($log === null) {
            $this->json(['error' => 'Không tìm thấy nhật ký.'], 404);
        }

        // Chi user thuoc thu vien moi duoc xem.
        if (!AccessControl::canRead((int) $log['library_id'])) {
            $this->json(['error' => 'Không có quyền.'], 403);
        }

        $status = FetchStatus::tryFrom((string) $log['status']);
        $label = $status?->label() ?? $log['status'];

        $this->json([
            'id'        => $log['id'],
            'status'    => $log['status'],
            'label'     => $label,
            'message'   => $log['message'],
            'http_code' => $log['http_code'],
            'css_class' => $status?->cssClass() ?? 'status-info',
        ]);
    }
}