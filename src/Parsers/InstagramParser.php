<?php
namespace MediaParser\Parsers;

use MediaParser\Support\YtDlp;

final class InstagramParser extends BaseParser
{
    private array $postData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->postData = YtDlp::extract($this->realUrl);
    }

    public function getRealVideoUrl(): ?string
    {
        foreach (($this->postData['entries'] ?? []) as $entry) {
            if (($entry['ext'] ?? '') === 'mp4' || (($entry['vcodec'] ?? 'none') !== 'none')) {
                return $entry['url'] ?? null;
            }
        }
        $url = $this->postData['url'] ?? null;
        return ($url && ($this->postData['ext'] ?? '') === 'mp4') ? $url : null;
    }

    public function getTitleContent(): ?string
    {
        $title = $this->postData['title'] ?? '';
        $desc = $this->postData['description'] ?? '';
        if ($desc && !str_contains($desc, $title)) {
            return $title . "\n" . $this->textSlice($desc, 0, 200);
        }
        return $title ?: $desc;
    }

    public function getCoverPhotoUrl(): ?string
    {
        $entries = $this->postData['entries'] ?? [];
        return $entries ? ($entries[0]['thumbnail'] ?? null) : ($this->postData['thumbnail'] ?? null);
    }

    public function getImageList(): array
    {
        $images = [];
        $entries = $this->postData['entries'] ?? [];
        if ($entries) {
            foreach ($entries as $entry) {
                if (($entry['ext'] ?? '') !== 'mp4' && !empty($entry['url'])) {
                    $images[] = $entry['url'];
                }
            }
        } elseif (($this->postData['ext'] ?? '') !== 'mp4' && !empty($this->postData['url'])) {
            $images[] = $this->postData['url'];
        }
        return $images;
    }

    public function getAuthorInfo(): ?array
    {
        return [
            'nickname' => $this->postData['uploader'] ?? '',
            'author_id' => $this->postData['uploader_id'] ?? '',
            'avatar' => null,
        ];
    }
}
