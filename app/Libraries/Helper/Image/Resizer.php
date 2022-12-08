<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\Gd;
use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\ImageAdapterInterface;
use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\Imagick;

class Resizer
{
    const USE_GD = 1;
    const USE_IMAGICK = 2;

    private static int|null|false $imageGenerationMode = null;

    public function __construct()
    {
        if (self::$imageGenerationMode === null) {
            self::$imageGenerationMode = extension_loaded('imagick')
                ? self::USE_IMAGICK
                : (extension_loaded('gd') ? self::USE_GD : false);
        }

        if (self::$imageGenerationMode === false) {
            throw new RuntimeException(
                'Extension gd or imagick has not been installed on the system.'
            );
        }
    }

    /**
     * @param resource|string|StreamInterface $source
     *
     * @return ImageAdapterInterface
     */
    public function create(mixed $source) : ImageAdapterInterface
    {
        return self::$imageGenerationMode === self::USE_IMAGICK
            ? new Imagick($this, $source)
            : new Gd($this, $source);
    }
}
