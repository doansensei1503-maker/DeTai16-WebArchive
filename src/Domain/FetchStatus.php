<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Trang thai thuc te cua mot lan lay noi dung.
 * Dùng chung cho fetch_logs va saved_pages.
 */
enum FetchStatus: string
{
    case Pending      = 'pending';
    case Processing   = 'processing';
    case Saved        = 'saved';
    case InvalidUrl   = 'invalid_url';
    case NotAllowed   = 'not_allowed';
    case HttpError    = 'http_error';
    case Timeout      = 'timeout';
    case NoArticle    = 'no_article';
    case FetchFailed  = 'fetch_failed';
    case Rejected     = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending     => 'Đang chờ',
            self::Processing  => 'Đang xử lý',
            self::Saved       => 'Lưu thành công',
            self::InvalidUrl  => 'URL không hợp lệ',
            self::NotAllowed  => 'URL ngoài danh sách cho phép',
            self::HttpError   => 'Lỗi HTTP',
            self::Timeout     => 'Hết thời gian chờ',
            self::NoArticle   => 'Không tìm thấy nội dung bài viết hợp lệ',
            self::FetchFailed => 'Không lấy được nội dung',
            self::Rejected    => 'Đã từ chối',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::Saved;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess() && $this !== self::Pending && $this !== self::Processing;
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Saved       => 'status-success',
            self::Pending, self::Processing => 'status-info',
            self::Rejected    => 'status-muted',
            default           => 'status-error',
        };
    }
}