<?php
namespace MediaParser\Parsers;

use MediaParser\Support\YtDlp;

final class TwitterParser extends BaseParser
{
    private array $postData = [];

    public function __construct(string $realUrl)
    {
        parent::__construct($realUrl);
        $this->postData = YtDlp::extract($this->realUrl);
    }

    public function getRealVideoUrl(): ?string
    {
        $url = $this->postData['url'] ?? null;
        if ($url && (($this->postData['ext'] ?? '') === 'mp4' || (($this->postData['vcodec'] ?? 'none') !== 'none'))) {
            return $url;
        }
        $best = null;
        $maxHeight = -1;
        foreach (($this->postData['formats'] ?? []) as $fmt) {
            if (($fmt['ext'] ?? '') === 'mp4' && (($fmt['vcodec'] ?? 'none') !== 'none')) {
                $height = (int)($fmt['height'] ?? 0);
                if ($height > $maxHeight) {
                    $maxHeight = $height;
                    $best = $fmt['url'] ?? null;
                }
            }
        }
        return $best;
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
        return $this->postData['thumbnail'] ?? null;
    }

    public function getImageList(): array
    {
        $images = [];
        foreach (($this->postData['entries'] ?? []) as $entry) {
            if (($entry['ext'] ?? '') !== 'mp4' && !empty($entry['url'])) {
                $images[] = $entry['url'];
            }
        }
        if (!$images && !empty($this->postData['url']) && in_array(($this->postData['ext'] ?? ''), ['jpg', 'png', 'jpeg'], true)) {
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
