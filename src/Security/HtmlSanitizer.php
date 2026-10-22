<?php

declare(strict_types=1);

namespace App\Security;

/**
 * LAM SACH HTML (Chuc nang 3B).
 *
 * Dung DOMDocument + allow-list. KHONG dung regex de xoa <script>.
 *
 * CHI GIA LAI:
 *   - Cac the an toan noi dung (xem ALLOWED_TAGS)
 *   - Thuoc tinh an toan tren tung the (xem ALLOWED_ATTRIBUTES)
 *
 * LOAI BO:
 *   - script, iframe, object, embed, form, style (co the tai tai nguyen ngoai)
 *   - Moi thuoc tinh su kien (onclick, onload, onerror, ...)
 *   - javascript: / data: / vbscript: URLs
 *   - style, srcset, background,... (tai/nguon tai nguyen ngoai)
 */
final class HtmlSanitizer
{
    /** The duoc phep giu lai (cong bo). */
    public const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 'mark', 'small', 'sub', 'sup',
        'blockquote', 'q', 'cite',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'a',
        'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        'code', 'pre', 'kbd', 'samp', 'var',
        'div', 'span', 'section', 'article', 'main', 'header', 'footer',
    ];

    /** The tuy quyet dinh xoa hoan toan (ke ca noi dung ben trong). */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'option', 'textarea',
        'noscript', 'template', 'svg', 'math', 'link', 'meta', 'base',
        'audio', 'video', 'source', 'track', 'canvas', 'map', 'area',
        'frame', 'frameset', 'marquee', 'portal', 'dialog', 'slot',
    ];

    /** Thuoc tinh an toan tren moi the. */
    private const GLOBAL_ATTRIBUTES = ['title', 'lang', 'dir'];

    /** Thuoc tinh rieng cho tung the. */
    private const ALLOWED_ATTRIBUTES = [
        'a'      => ['href', 'rel', 'target'],
        'img'    => ['src', 'alt', 'width', 'height', 'data-asset-id'],
        'td'     => ['colspan', 'rowspan'],
        'th'     => ['colspan', 'rowspan', 'scope'],
        'ol'     => ['start'],
        'time'   => ['datetime'],
    ];

    /** URL schemes duoc phep. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /** @var array<string,string> Map image src (goc) -> /asset/{hash} (noi bo). */
    private array $imageRewrites = [];

    /** @var array<int,string> Danh sach duong dan anh da duoc ghi lai. */
    private array $capturedImages = [];

    public function __construct(
        private readonly array $allowedSchemes = self::ALLOWED_SCHEMES,
    ) {
    }

    /**
     * Lam sach HTML.
     *
     * @param array<string,string> $imageMap Map [originalSrc => internalPath]
     */
    public function sanitize(string $dirtyHtml, array $imageMap = []): string
    {
        $this->imageRewrites = $imageMap;

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="detai16-root">' . $dirtyHtml . '</div>',
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $root = $xpath->query('//div[@id="detai16-root"]')->item(0);

        if (!$root instanceof \DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private function cleanChildren(\DOMNode $parent): void
    {
        // Duyet nguoc de xoa an toan.
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMComment) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if ($child instanceof \DOMProcessingInstruction) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if ($child instanceof \DOMText) {
                continue;
            }

            if (!$child instanceof \DOMElement) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);

            // The nguy hiem -> xoa ca khoi ben trong.
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            // The khong duoc phep -> giai the, giu noi dung van ban.
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->cleanChildren($child);
                $this->unwrap($child);
                continue;
            }

            // The duoc phep -> lam sach thuoc tinh.
            $this->sanitizeAttributes($child, $tag);
            $this->cleanChildren($child);
        }
    }

    /** Xoa the nhung giu lai noi dung ben trong. */
    private function unwrap(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function sanitizeAttributes(\DOMElement $node, string $tag): void
    {
        // Lay danh sach thuoc tinh hop le.
        $allowed = array_merge(
            self::GLOBAL_ATTRIBUTES,
            self::ALLOWED_ATTRIBUTES[$tag] ?? [],
        );

        // Ghi lai thuoc tinh can giu.
        $attrs = [];
        foreach ($node->attributes ?? [] as $attr) {
            $name = strtolower($attr->nodeName);
            $attrs[$name] = $attr->nodeValue ?? '';
        }

        // Xoa het thuoc tinh cu.
        foreach (array_keys($attrs) as $name) {
            $node->removeAttribute($name);
        }

        foreach ($allowed as $name) {
            if (!array_key_exists($name, $attrs)) {
                continue;
            }

            $value = $attrs[$name];

            // Chan moi thuoc tinh su kien on* (du danh sach cho phep nen rat an toan).
            if (str_starts_with($name, 'on')) {
                continue;
            }

            // URL -> kiem tra scheme.
            if ($name === 'href' || $name === 'src') {
                $safe = $this->sanitizeUrl($value);
                if ($safe === null) {
                    continue;
                }

                // Anh: chuyen sang duong dan noi bo neu da luu.
                if ($name === 'src' && $tag === 'img') {
                    $rewritten = $this->rewriteImage($value, $safe);
                    if ($rewritten === null) {
                        // Khong luu duoc -> bo thuoc tinh src,
                        // KHONG de tai lai tu trang nguon.
                        continue;
                    }
                    $safe = $rewritten;
                }

                $node->setAttribute($name, $safe);
                continue;
            }

            // San hoa an toan.
            if (in_array($name, ['width', 'height', 'colspan', 'rowspan', 'start'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value) ?? '';
                if ($value === '') {
                    continue;
                }
                $node->setAttribute($name, $value);
                continue;
            }

            $node->setAttribute($name, $value);
        }

        // Lien ket ngoai -> them rel/noopener.
        if ($tag === 'a') {
            $href = $node->getAttribute('href');
            $isExternal = $href !== '' && preg_match('#^https?://#i', $href) === 1;

            if ($isExternal) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }

    /**
     * Kiem tra URL co an toan khong.
     * Tra ve null neu URL khong an toan.
     */
    private function sanitizeUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

        if ($url === '') {
            return null;
        }

        // Loai bo ký tự điều khiển.
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';
        if ($url === '') {
            return null;
        }

        // URL tuong doi (anh noi bo) -> cho phep.
        if (str_starts_with($url, '/')) {
            return $url;
        }

        // Fragment.
        if (str_starts_with($url, '#')) {
            return $url;
        }

        // Phai co scheme.
        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $url, $m) !== 1) {
            // Scheme-relative //host -> chuyen ve https
            if (str_starts_with($url, '//')) {
                return 'https:' . $url;
            }

            return null;
        }

        $scheme = strtolower($m[1]);

        // Chan rõ ràng javascript:, data:, vbscript:, file:, v.v.
        if (!in_array($scheme, $this->allowedSchemes, true)) {
            return null;
        }

        return $url;
    }

    /**
     * Chuyen tham chieu anh goc -> duong dan doc anh noi bo.
     */
    private function rewriteImage(string $original, string $sanitized): ?string
    {
        if (isset($this->imageRewrites[$original])) {
            return $this->imageRewrites[$original];
        }

        if (isset($this->imageRewrites[$sanitized])) {
            return $this->imageRewrites[$sanitized];
        }

        // Anh khong duoc luu -> tra ve null de bo thuoc tinh src
        // (KHONG de bai doc lai tai lai tu website nguon).
        return null;
    }

    /** @return array<int,string> */
    public function getAllowedTags(): array
    {
        return self::ALLOWED_TAGS;
    }

    /** @return array<int,string> */
    public function getDroppedTags(): array
    {
        return self::DROP_WITH_CONTENT;
    }
}