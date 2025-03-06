<?php

namespace Crwlr\Crawler\Loader\Http\Browser;

use Crwlr\Utils\Microseconds;
use HeadlessChromium\Clip;
use HeadlessChromium\Exception\CommunicationException\CannotReadResponse;
use HeadlessChromium\Exception\CommunicationException\InvalidResponse;
use HeadlessChromium\Page;

class ScreenshotConfig
{
    public function __construct(
        public string $storePath,
        public string $fileType = 'png',
        public ?int $quality = null,
        public bool $fullPage = false,
    ) {}

    public static function make(string $storePath): self
    {
        return new self($storePath);
    }

    /**
     * @throws CannotReadResponse
     * @throws InvalidResponse
     */
    public function getFullPath(Page $page): string
    {
        $filename = md5($page->getCurrentUrl()) . '-' . Microseconds::now()->value . '.' . $this->fileType;

        return $this->storePath . (!str_ends_with($this->storePath, '/') ? '/' : '') . $filename;
    }

    public function setImageFileType(string $type): self
    {
        if (in_array($type, ['jpeg', 'png', 'webp'], true)) {
            $this->fileType = $type;

            if (in_array($type, ['jpeg', 'webp'], true) && $this->quality === null) {
                $this->quality = 80;
            } elseif ($type === 'png' && $this->quality !== null) {
                $this->quality = null;
            }
        }

        return $this;
    }

    public function setQuality(int $quality): self
    {
        if (in_array($this->fileType, ['jpeg', 'webp'], true) && $quality > 0 && $quality <= 100) {
            $this->quality = $quality;
        }

        return $this;
    }

    public function setFullPage(): self
    {
        $this->fullPage = true;

        return $this;
    }

    /**
     * @return array<string, int|string|bool|Clip>
     */
    public function toChromePhpScreenshotConfig(Page $page): array
    {
        $config = ['format' => $this->fileType];

        if ($this->quality && in_array($this->fileType, ['jpeg', 'webp'], true)) {
            $config['quality'] = $this->quality;
        }

        if ($this->fullPage) {
            $config['captureBeyondViewport'] = true;

            $config['clip'] = $page->getFullPageClip();
        }

        return $config;
    }
}
