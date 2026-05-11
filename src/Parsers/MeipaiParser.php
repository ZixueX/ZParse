<?php
namespace MediaParser\Parsers;

final class MeipaiParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlContent() ?? '';
    }

    public function getRealVideoUrl(): ?string
    {
        if (preg_match('/data-video=(["\'])(.*?)\1/i', (string)$this->data, $m)) {
            $decoded = base64_decode($m[2], true);
            return $decoded !== false ? 'https:' . $decoded : null;
        }
        return null;
    }

    public function getCoverPhotoUrl(): ?string
    {
        $html = (string)$this->data;
        $pos = stripos($html, 'detailVideo');
        $scope = $pos === false ? $html : substr($html, $pos, 3000);
        return preg_match('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/is', $scope, $m) ? html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    public function getTitleContent(): ?string
    {
        if (preg_match('/class=(["\'])(?:(?!\1).)*\bdetail-cover-title\b(?:(?!\1).)*\1[^>]*>(.*?)<\/[^>]+>/is', (string)$this->data, $m)) {
            return $this->cleanHtml($m[2]);
        }
        return null;
    }
}

