<?php
namespace MediaParser\Parsers;

final class XinpianchangParser extends BaseParser
{
    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->data = $this->fetchHtmlData();
    }

    private function fetchHtmlData(): array
    {
        $html = $this->fetchHtmlContent() ?? '';
        if (preg_match('/<script\s+id=(["\'])__NEXT_DATA__\1\s+type=(["\'])application\/json\2>(.*?)<\/script>/is', $html, $m)) {
            $data = json_decode($m[3], true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    public function getRealVideoUrl(): ?string { return $this->arr($this->data, ['props', 'pageProps', 'detail', 'media_info', 'source', 'progressive', 0, 'url']); }
    public function getCoverPhotoUrl(): ?string { return $this->arr($this->data, ['props', 'pageProps', 'detail', 'cover']); }
    public function getTitleContent(): ?string { return $this->arr($this->data, ['props', 'pageProps', 'detail', 'title']); }
}

