<?php
namespace MediaParser\Parsers;

final class LvzhouParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlContent() ?? '';
    }

    public function getRealVideoUrl(): ?string
    {
        return preg_match('/<video\b[^>]*\bsrc=(["\'])(.*?)\1/is', (string)$this->data, $m) ? html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    public function getCoverPhotoUrl(): ?string
    {
        return preg_match('/background-image\s*:\s*url\((.*?)\)/i', (string)$this->data, $m) ? trim($m[1], '"\' ') : null;
    }

    public function getTitleContent(): ?string
    {
        if (preg_match('/<div[^>]*class=(["\'])(?:(?!\1).)*\bstatus-title\b(?:(?!\1).)*\1[^>]*>(.*?)<\/div>/is', (string)$this->data, $m)) {
            return $this->cleanHtml($m[2]);
        }
        return null;
    }
}

