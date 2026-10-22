<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\FetchStatus;

/**
 * TRICH XUAT BAI VIET (Chuc nang 2B + 2C).
 *
 * Phan biet noi dung bai chinh voi thanh phan phu (menu, sidebar, footer).
 * Khong lay toan bo HTML roi gia dinh do la noi dung.
 *
 * BO CUC HTML DUOC HO TRO (cong bo ro rang):
 *   1. <article>            - vung noi dung bai viet
 *   2. <main>               - vung chinh cua trang
 *   3. [itemprop="articleBody"]
 *   4. .post-content, .entry-content, .article-content, .article-body
 *   5. [role="main"]
 *
 * Cac bố cục khác se bi bao loi "khong tim thay noi dung bai viet hop le"
 * thay vi gia dinh toan bo trang la bai viet.
 */
final class ArticleExtractor
{
    /**
     * Cac bo cuc HTML duoc ho tro, theo thu tu uu tien.
     * Key = ten bo cuc (dung de luu vao DB / cong bo).
     * Value = bieu thuc XPath tuong ung.
     */
    private const LAYOUTS = [
        'article'              => '//article',
        'main'                 => '//main',
        'itemprop-articleBody' => '//*[@itemprop="articleBody"]',
        'post-content'         => "//*[contains(concat(' ', normalize-space(@class), ' '), ' post-content ')]",
        'entry-content'        => "//*[contains(concat(' ', normalize-space(@class), ' '), ' entry-content ')]",
        'article-content'      => "//*[contains(concat(' ', normalize-space(@class), ' '), ' article-content ')]",
        'article-body'         => "//*[contains(concat(' ', normalize-space(@class), ' '), ' article-body ')]",
        'role-main'            => '//*[@role="main"]',
    ];

    /** So ky tu toi thieu de coi la bai viet hop le. */
    private const MIN_TEXT_LENGTH = 120;

    /** So ky tu toi da toi da. */
    private const MIN_BLOCK_LENGTH = 200;

    public static function extract(string $html, string $url): array
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return [
                'success' => false,
                'status'  => FetchStatus::FetchFailed,
                'message' => 'Không phân tích được HTML (HTML không hợp lệ).',
            ];
        }

        $xpath = new \DOMXPath($dom);

        // Tim vung noi dung bai viet.
        [$contentNode, $layoutUsed] = self::findContentNode($xpath);

        if ($contentNode === null) {
            return [
                'success' => false,
                'status'  => FetchStatus::NoArticle,
                'message' => 'Không tìm thấy vùng nội dung bài viết hợp lệ. '
                    . 'Trang này không dùng bố cục HTML được hỗ trợ '
                    . '(article, main, [itemprop="articleBody"], .post-content, .entry-content, .article-content).',
            ];
        }

        // Loai bo thanh phan phu trong vung noi dung.
        self::stripNoise($contentNode);

        // Tach tieu de.
        $title = self::extractTitle($xpath, $contentNode, $dom);

        if ($title === '') {
            return [
                'success' => false,
                'status'  => FetchStatus::NoArticle,
                'message' => 'Không tìm thấy tiêu đề bài viết.',
            ];
        }

        // Tieu de da tach rieng -> thoi <h1> khoi than bai
        // de ban doc lai khong hien thi trung tieu de.
        self::removeTitleHeading($contentNode, $title);

        // Kiem tra noi dung co that su la bai viet hay khong.
        if (!self::hasArticleBody($contentNode)) {
            return [
                'success' => false,
                'status'  => FetchStatus::NoArticle,
                'message' => 'Không tìm thấy vùng nội dung bài viết hợp lệ. '
                    . 'Trang này không dùng bố cục HTML được hỗ trợ '
                    . '(article, main, [itemprop="articleBody"], .post-content, .entry-content, .article-content).',
            ];
        }

        // Phat hien trang loi HTTP tra ve kem noi dung HTML (vi du: CDN tra ve trang loi).
        if (self::looksLikeErrorPage($dom)) {
            return [
                'success' => false,
                'status'  => FetchStatus::NoArticle,
                'message' => 'Trả về trang thông báo lỗi, không phải bài viết.',
            ];
        }

        // Lay HTML + text noi dung.
        $contentHtml = '';
        foreach ($contentNode->childNodes as $child) {
            $contentHtml .= $dom->saveHTML($child);
        }
        $contentHtml = trim($contentHtml);

        $contentText = self::extractText($contentNode);
        $wordCount = self::countWords($contentText);

        if (mb_strlen($contentText, 'UTF-8') < self::MIN_TEXT_LENGTH) {
            return [
                'success' => false,
                'status'  => FetchStatus::NoArticle,
                'message' => 'Nội dung bài viết quá ngắn ('
                    . mb_strlen($contentText, 'UTF-8') . ' ký tự, tối thiểu '
                    . self::MIN_TEXT_LENGTH . ') nên không được coi là bài viết hợp lệ.',
            ];
        }

        // Du lieu mo ta.
        $author = self::extractMeta($xpath, ['author', 'article:author', 'byl', 'twitter:creator']);
        $publishedAt = self::extractDate($xpath);
        $language = self::extractLanguage($xpath, $dom);
        $excerpt = self::buildExcerpt($xpath, $contentText);

        return [
            'success'       => true,
            'status'        => FetchStatus::Processing,
            'message'       => 'Trích xuất thành công.',
            'title'         => $title,
            'content_html'  => $contentHtml,
            'content_text'  => $contentText,
            'excerpt'       => $excerpt,
            'author'        => $author,
            'published_at'  => $publishedAt,
            'language'      => $language,
            'layout_used'   => $layoutUsed,
            'word_count'    => $wordCount,
        ];
    }

    /**
     * Kiem tra vung noi dung co that su chua "than bai" hay khong.
     * Chi mot khung lon, hay nhieu doan van / tieu de con lai moi la bai viet.
     */
    private static function hasArticleBody(\DOMElement $node): bool
    {
        $paragraphs = $node->getElementsByTagName('p');
        $headings = $node->getElementsByTagName('h2')
            ->length + $node->getElementsByTagName('h3')->length
            + $node->getElementsByTagName('h4')->length;
        $lists = $node->getElementsByTagName('ul')->length
            + $node->getElementsByTagName('ol')->length;

        // Nhieu doan van = co kha nang la bai viet that.
        if ($paragraphs->length >= 2) {
            return true;
        }

        // It doan van nhung nhieu tieu de con lai.
        if ($headings >= 2) {
            return true;
        }

        // It doan van nhung co danh sach.
        if ($lists >= 1 && $paragraphs->length >= 1) {
            return true;
        }

        return false;
    }

    /** Thoi the tieu de khoi than bai (neu no trung voi tieu de bai viet). */
    private static function removeTitleHeading(\DOMElement $contentNode, string $title): void
    {
        $xpath = new \DOMXPath($contentNode->ownerDocument ?? new \DOMDocument());

        foreach (['h1', 'h2'] as $tag) {
            $nodes = $xpath->query('.//' . $tag, $contentNode);
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

                if ($text !== '' && self::isSameTitle($text, $title)) {
                    $node->parentNode?->removeChild($node);
                    break;
                }
            }
        }
    }

    private static function isSameTitle(string $a, string $b): bool
    {
        $normalize = static function (string $s): string {
            $s = \App\Support\Str::toAscii($s);
            $s = mb_strtolower(trim($s), 'UTF-8');
            $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? '';

            return $s;
        };

        $na = $normalize($a);
        $nb = $normalize($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        return $na === $nb || str_contains($nb, $na) || str_contains($na, $nb);
    }

    /**
     * Tim vung chua noi dung bai viet theo thu tu uu tien.
     *
     * @return array{0:\DOMElement|null, 1:string|null}
     */
    private static function findContentNode(\DOMXPath $xpath): array
    {
        foreach (self::LAYOUTS as $layoutName => $xpathExpr) {
            $nodes = $xpath->query($xpathExpr);

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            // Chon node co do dai van ban lon nhat.
            $best = null;
            $bestLength = 0;

            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $text = self::extractText($node);
                $length = mb_strlen($text, 'UTF-8');

                if ($length > $bestLength) {
                    $best = $node;
                    $bestLength = $length;
                }
            }

            if ($best instanceof \DOMElement && $bestLength >= self::MIN_TEXT_LENGTH) {
                return [$best, $layoutName];
            }

            // Neu khong node nao du dai, giu node dai nhat de kiem tra tiep.
            if ($best instanceof \DOMElement && $bestLength > 0) {
                $fallback = [$best, $layoutName];
            }
        }

        // Khong co bố cuc nao du kieu -> that bai (khong gia dinh toan bo trang).
        return [null, null];
    }

    /** Loai bo thanh phan phu khoi vung noi dung. */
    private static function stripNoise(\DOMElement $node): void
    {
        $xpath = new \DOMXPath($node->ownerDocument ?? new \DOMDocument());

        $noiseSelectors = [
            '//script', '//style', '//noscript', '//iframe', '//object', '//embed',
            '//form', '//nav', '//aside', '//footer', '//header',
            '//*[contains(@class, "sidebar")]',
            '//*[contains(@class, "comment")]',
            '//*[contains(@class, "advert")]',
            '//*[contains(@class, "ads")]',
            '//*[contains(@class, "related")]',
            '//*[contains(@class, "breadcrumb")]',
            '//*[contains(@class, "social")]',
            '//*[contains(@class, "share")]',
            '//*[contains(@class, "newsletter")]',
            '//*[contains(@class, "cookie")]',
            '//*[contains(@class, "author-box")]',
        ];

        foreach ($noiseSelectors as $selector) {
            // QUAN TRONG: dung ".//" de chi tim trong vung noi dung,
            // khong phai "//" tim toan bo tai lieu (se xoa nham phan ngoai bai viet).
            // ".//p" + "//" + selector -> "./" . "//nav" ...
            $matches = $xpath->query($selector, $node);
            if ($matches === false) {
                continue;
            }

            $toRemove = [];
            foreach ($matches as $match) {
                if ($match instanceof \DOMElement) {
                    $toRemove[] = $match;
                }
            }

            foreach ($toRemove as $victim) {
                if ($victim->parentNode !== null) {
                    $victim->parentNode->removeChild($victim);
                }
            }
        }

        // Loai bo comment HTML.
        $comments = $xpath->query('.//comment()');
        if ($comments !== false) {
            $toRemove = [];
            foreach ($comments as $comment) {
                $toRemove[] = $comment;
            }
            foreach ($toRemove as $node) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    /** Tach tieu de theo thu tu uu tien. */
    private static function extractTitle(\DOMXPath $xpath, \DOMElement $content, \DOMDocument $dom): string
    {
        // 1. Tieu de trong vung noi dung.
        $inContent = $xpath->query('.//h1', $content);
        if ($inContent !== false && $inContent->length > 0) {
            $node = $inContent->item(0);
            if ($node !== null) {
                $title = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
                if ($title !== '') {
                    return mb_substr($title, 0, 500, 'UTF-8');
                }
            }
        }

        // 2. <meta property="og:title">
        $ogTitle = self::metaContent($xpath, 'og:title');
        if ($ogTitle !== '') {
            return mb_substr($ogTitle, 0, 500, 'UTF-8');
        }

        // 3. <meta name="twitter:title">
        $twTitle = self::metaContent($xpath, 'twitter:title');
        if ($twTitle !== '') {
            return mb_substr($twTitle, 0, 500, 'UTF-8');
        }

        // 4. The <title>.
        $titles = $xpath->query('//head/title');
        if ($titles !== false && $titles->length > 0) {
            $node = $titles->item(0);
            if ($node !== null) {
                $title = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
                if ($title !== '') {
                    return mb_substr($title, 0, 500, 'UTF-8');
                }
            }
        }

        return '';
    }

    private static function extractMeta(\DOMXPath $xpath, array $names): string
    {
        foreach ($names as $name) {
            $value = self::metaContent($xpath, $name);
            if ($value !== '') {
                return mb_substr($value, 0, 190, 'UTF-8');
            }
        }

        // Thuoc tinh itemprop author.
        $nodes = $xpath->query('//*[@itemprop="author"]');
        if ($nodes !== false && $nodes->length > 0) {
            $node = $nodes->item(0);
            if ($node !== null) {
                $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
                if ($text !== '') {
                    return mb_substr($text, 0, 190, 'UTF-8');
                }
            }
        }

        return '';
    }

    private static function metaContent(\DOMXPath $xpath, string $name): string
    {
        $queries = [
            sprintf('//meta[@property="%s"]/@content', $name),
            sprintf('//meta[@name="%s"]/@content', $name),
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                $node = $nodes->item(0);
                if ($node !== null) {
                    $value = trim($node->nodeValue ?? '');
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    private static function extractDate(\DOMXPath $xpath): ?string
    {
        foreach (['article:published_time', 'datePublished', 'publish_date', 'date'] as $name) {
            $value = self::metaContent($xpath, $name);
            if ($value === '') {
                $nodes = $xpath->query(sprintf('//time[@datetime]/@datetime'));
                if ($nodes !== false && $nodes->length > 0) {
                    $node = $nodes->item(0);
                    $value = $node !== null ? trim($node->nodeValue ?? '') : '';
                }
            }

            if ($value !== '') {
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return date('Y-m-d H:i:s', $timestamp);
                }
            }
        }

        return null;
    }

    private static function extractLanguage(\DOMXPath $xpath, \DOMDocument $dom): string
    {
        $nodes = $xpath->query('//html/@lang');
        if ($nodes !== false && $nodes->length > 0) {
            $node = $nodes->item(0);
            $lang = strtolower(trim($node?->nodeValue ?? ''));
            if (str_starts_with($lang, 'vi')) {
                return 'vi';
            }
            if ($lang !== '') {
                return substr($lang, 0, 2);
            }
        }

        return 'vi';
    }

    private static function buildExcerpt(\DOMXPath $xpath, string $contentText): string
    {
        $meta = self::metaContent($xpath, 'og:description');
        if ($meta === '') {
            $meta = self::metaContent($xpath, 'description');
        }

        if ($meta !== '') {
            return \App\Support\Str::excerpt($meta, 250);
        }

        return \App\Support\Str::excerpt($contentText, 200);
    }

    private static function extractText(\DOMNode $node): string
    {
        $text = $node->textContent;
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    private static function countWords(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($words, static fn (string $w): bool => $w !== ''));
    }

    /**
     * Phat hien trang loi duoc tra ve voi HTTP 200.
     * Vi du: trang 404 cua CDN, trang thong bao loi, etc.
     *
     * KHONG dua vao do dai trang, vi trang ngan hop le van bi loai
     * o buoc kiem tra noi dung phia truoc.
     */
    private static function looksLikeErrorPage(\DOMDocument $dom): bool
    {
        // 1. Tieu de co tu khoa loi.
        $titleNodes = $dom->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $title = mb_strtolower(trim($titleNodes->item(0)?->textContent ?? ''), 'UTF-8');
            if (preg_match('/\b(404|403|500|502|503)\b/u', $title) === 1) {
                return true;
            }
            foreach (['not found', 'không tìm thấy', 'khong tim thay', 'page error',
                'internal server error', 'bad gateway', 'service unavailable'] as $pattern) {
                if (str_contains($title, mb_strtolower($pattern, 'UTF-8'))) {
                    return true;
                }
            }
        }

        // 2. Co the so bao loi HTTP ro rang.
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*[@class or @id]') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $marker = mb_strtolower(($node->getAttribute('class') . ' ' . $node->getAttribute('id')), 'UTF-8');
            if ($marker === '') {
                continue;
            }

            foreach (['error-404', 'error404', 'page-404', 'status-404',
                'http-error', 'errormessage', 'not-found', 'nfpage'] as $marker2) {
                if (str_contains($marker, $marker2)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<int,string> Danh sach selector duoc ho tro (cong bo). */
    public static function supportedLayouts(): array
    {
        return array_values(self::LAYOUTS);
    }
}