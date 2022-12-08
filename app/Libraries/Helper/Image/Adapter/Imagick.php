<?php
// @todo image resize
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image\Adapter;

class Imagick extends AbstractImageAdapter
{
    public function getSupportedMimeTypeExtensions(): array
    {
        return [];
    }

    public function resize(int $width, int $height, int $mode = self::MODE_AUTO): static
    {
        return $this;
    }

    public function saveTo(
        string $target,
        int $quality = 100,
        bool $overwrite = false,
        ?string $forceOverrideExtension = null
    ): ?array {
        return null;
    }
}
