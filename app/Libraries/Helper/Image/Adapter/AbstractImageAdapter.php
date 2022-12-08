<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image\Adapter;

use GdImage;
use Imagick as ImagickAlias;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\StreamInterface;
use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\Exceptions\ImageFileNotFoundException;
use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\Exceptions\ImageIsNotSupported;
use TelkomselAggregatorTask\Libraries\Helper\Image\Resizer;

abstract class AbstractImageAdapter implements ImageAdapterInterface
{
    /**
     * @var int 10MB Default
     */
    protected int $maximumSourceSize = 10485760;

    /**
     * @var Resizer
     */
    public readonly Resizer $resizer;

    /**
     * @var int
     */
    private int $imageType;

    /**
     * @var int
     */
    private int $originalHeight;

    /**
     * @var int
     */
    private int $originalWidth;

    /**
     * @var string
     */
    private string $originalMimeType;

    /**
     * @var string
     */
    private string $originalStandardExtension;

    /**
     * @var int
     */
    protected int $width;

    /**
     * @var int
     */
    protected int $height;

    /**
     * @var resource|GdImage|ImagickAlias
     */
    protected $resource = null;

    /**
     * @var string
     */
    private string $file_source;

    /**
     * @var bool
     */
    private bool $use_temp = false;

    /**
     * @param Resizer $resizer
     * @param resource|string|StreamInterface $source
     */
    final public function __construct(Resizer $resizer, mixed $source)
    {
        $this->resizer = $resizer;
        if (is_string($source)) {
            if (!is_file($source) || !is_readable($source)) {
                throw new ImageFileNotFoundException(
                    str_contains($source, "\n") || $this->isBinary($source)
                    ? null
                    : $source
                );
            }
            $this->file_source = realpath($source)??$source;
        } elseif (is_resource($source)) {
            if (get_resource_type($source) !== 'stream') {
                throw new InvalidArgumentException(
                    sprintf(
                        'Source must be string file name, file resource or instance of %s',
                        StreamInterface::class
                    )
                );
            }
            // default as unknown/type
            $meta = stream_get_meta_data($source);
            $headers = self::parseHeaderFromWrapper($meta['wrapper_data']??[]);
            if (!preg_match(
                '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/',
                $meta['mode']
            )) {
                throw new ImageIsNotSupported(
                    'Source stream is not readable.'
                );
            }
            $offset = ftell($source);
            if ($offset !== false && $offset > 0) {
                if (!$meta['seekable']) {
                    throw new ImageIsNotSupported(
                        'Source stream source is not seekable but going to offset : '.$offset
                    );
                }
                fseek($source, 0);
            }
            $size = fstat($source)['size']??$headers['content-length']??null;
            if (!$size) {
                throw new ImageIsNotSupported(
                    'Could not determine source size.'
                );
            }
            unset($size);
            $this->file_source = tempnam(sys_get_temp_dir(), 'tmp-image-');
            $fopen = fopen($this->file_source, 'rb+');
            $size = 0;
            while (!feof($source)) {
                $content = fread($source, 4096);
                if ($content === false) {
                    break;
                }
                $size += fputs($fopen, $content);
            }
            fclose($fopen);
            if (!$size) {
                unlink($this->file_source);
                throw new ImageIsNotSupported(
                    'Could not determine source size or size zero value.'
                );
            }
            if ($size > $this->maximumSourceSize) {
                unlink($this->file_source);
                throw new ImageIsNotSupported(
                    sprintf(
                        'Image size too large than allowed is: %d bytes',
                        $this->maximumSourceSize
                    )
                );
            }
            $this->use_temp = true;
            unset($source, $content);
        } elseif ($source instanceof StreamInterface) {
            if (!$source->isReadable()) {
                throw new ImageIsNotSupported(
                    'Source stream is not readable.'
                );
            }
            $offset = $source->tell();
            if ($offset > 0) {
                if (!$source->isSeekable()) {
                    throw new ImageIsNotSupported(
                        'Source stream source is not seekable but going to offset : '.$offset
                    );
                }
                $source->rewind();
            }

            $this->file_source = tempnam(sys_get_temp_dir(), 'tmp-image-');
            $fopen = fopen($this->file_source, 'rb');
            $size = 0;
            while ($source->eof()) {
                $content = $source->read(4096);
                if ($content === false) {
                    return;
                }
                $size += fputs($fopen, $content);
            }
            fclose($fopen);
            if (!$size) {
                unlink($this->file_source);
                throw new ImageIsNotSupported(
                    'Could not determine source size or size zero value.'
                );
            }
            if ($size > $this->maximumSourceSize) {
                unlink($this->file_source);
                throw new ImageIsNotSupported(
                    sprintf(
                        'Image size too large than allowed is: %d bytes',
                        $this->maximumSourceSize
                    )
                );
            }
            $this->use_temp = true;
            unset($source);
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Source must be string file name, file resource or instance of %s',
                    StreamInterface::class
                )
            );
        }
        if (!isset($size) && filesize($this->file_source) > $this->maximumSourceSize) {
            throw new ImageIsNotSupported(
                sprintf(
                    'Image size too large than allowed is: %d bytes',
                    $this->maximumSourceSize
                )
            );
        }

        $size = getimagesize($this->file_source);
        if ($size === false) {
            throw new ImageIsNotSupported(
                sprintf('%s is not valid image file.', $size)
            );
        }
        $this->originalWidth = $size[0];
        $this->originalHeight = $size[1];
        $mimeType = $size['mime'];

        if ($size[2] === IMAGETYPE_UNKNOWN) {
            throw new ImageIsNotSupported(
                sprintf('%s is not known image type.', $size)
            );
        }

        if (!$this->isMimeTypeSupported($mimeType)) {
            throw new InvalidArgumentException(
                sprintf('%s is not supported', $mimeType)
            );
        }

        $this->originalWidth = $size[0];
        $this->originalHeight = $size[1];
        $this->originalMimeType = $mimeType;
        $this->imageType = $size[2];
        $this->width = $this->originalWidth;
        $this->height = $this->originalHeight;
        $this->originalStandardExtension = self::IMAGE_TYPE_LIST[$this->imageType]??(
            explode('/', $this->originalMimeType)[1]
        );
    }

    /**
     * @return bool
     */
    public function isUseTemp(): bool
    {
        return $this->use_temp;
    }

    /**
     * @return string
     */
    public function getOriginalStandardExtension(): string
    {
        return $this->originalStandardExtension;
    }

    /**
     * @return int
     */
    public function getImageType(): mixed
    {
        return $this->imageType;
    }

    /**
     * @return string
     */
    public function getOriginalMimeType(): mixed
    {
        return $this->originalMimeType;
    }

    /**
     * @return int
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * @return int
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @param string $text
     * @return bool
     */
    public function isBinary(string $text): bool
    {
        return (bool)preg_match('|[^\x20-\x7E]|', $text); // chr(32)..chr(127)
    }

    /**
     * @param array $wrapper_data
     *
     * @return array
     */
    private static function parseHeaderFromWrapper(array $wrapper_data) : array
    {
        $headers = [];
        foreach ($wrapper_data as $wrapper) {
            if (trim($wrapper) === '') {
                continue;
            }
            $wrapper = explode(':', $wrapper);
            $key   = strtolower(array_shift($wrapper));
            $value = trim(implode(':', $wrapper));
            if (is_numeric($value)) {
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            }
            $headers[$key] = $value;
        }
        return $headers;
    }

    /**
     * @param string $mimeType image/png, image/jpeg ... etc or jpg|png|jpeg
     *
     * @return bool
     */
    public function isMimeTypeSupported(string $mimeType): bool
    {
        $mimeType = strtolower(trim($mimeType));
        if (strpos($mimeType, '/')) {
            preg_match('~^image/([^;\s]+)(;|$)~i', $mimeType, $match);
            if (!$match) {
                return false;
            }
            $mimeType = $match[1];
            if (!$mimeType) {
                return false;
            }
        }

        return in_array(
            $mimeType,
            $this->getSupportedMimeTypeExtensions(),
            true
        );
    }

    /**
     * @return string
     */
    public function getFileSource(): string
    {
        return $this->file_source;
    }

    /**
     * @return array{"width":float,"height":float}
     */
    public function getRatio(): array
    {
        $width = $this->getWidth();
        $height = $this->getHeight();
        return [
            'width' => (float) ($height / $width),
            'height' => (float) ($width / $height),
        ];
    }

    /**
     * @return array{"width":float,"height":float}
     */
    public function getOriginalRatio(): array
    {
        $width = $this->getOriginalWidth();
        $height = $this->getOriginalHeight();
        return [
            'width' => (float) ($height / $width),
            'height' => (float) ($width / $height),
        ];
    }

    /**
     * @param int $width
     * @param int $height
     * @param int $mode
     *
     * @return array{"width":int,"height":int}
     */
    public function getDimensions(int $width, int $height, int $mode = self::MODE_AUTO): array
    {
        return match ($mode) {
            self::MODE_AUTO => $this->getAutoDimension($width, $height),
            self::MODE_ORIENTATION_LANDSCAPE => $this->getLandscapeDimension($width),
            self::MODE_ORIENTATION_PORTRAIT => $this->getPortraitDimension($height),
            self::MODE_ORIENTATION_SQUARE => [
                'width'  => $width,
                'height' => $width
            ],
            default => [
                'width' => $width,
                'height' => $height
            ]
        };
    }

    /**
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    #[ArrayShape(['height' => "int", 'width' => "int"])]
    public function getPortraitDimension(int $height): array
    {
        return [
            'height' => $height,
            'width' => (int) ceil($height * $this->getOriginalRatio()['height'])
        ];
    }

    #[ArrayShape(['height' => "int", 'width' => "int"])]
    public function getLandscapeDimension(int $width): array
    {
        return [
            'height' => (int) ceil($width * $this->getOriginalRatio()['width']),
            'width' => $width
        ];
    }

    /**
     * @return int
     */
    public function getOrientation(): int
    {
        $width = $this->getHeight();
        $height = $this->getWidth();
        if ($width === $height) {
            return self::MODE_ORIENTATION_SQUARE;
        }

        return $width < $height
            ? self::MODE_ORIENTATION_LANDSCAPE
            : self::MODE_ORIENTATION_PORTRAIT;
    }

    #[Pure] public function getOriginalOrientation(): int
    {
        $originalHeight = $this->getOriginalHeight();
        $originalWidth = $this->getOriginalWidth();
        if ($originalHeight === $originalWidth) {
            return self::MODE_ORIENTATION_SQUARE;
        }

        return $originalHeight < $originalWidth
            ? self::MODE_ORIENTATION_LANDSCAPE
            : self::MODE_ORIENTATION_PORTRAIT;
    }

    public function isSquare(): bool
    {
        return $this->getRatio() === self::MODE_ORIENTATION_SQUARE;
    }

    public function isLandscape() : bool
    {
        return $this->getRatio() === self::MODE_ORIENTATION_LANDSCAPE;
    }

    public function isPortrait() : bool
    {
        return $this->getRatio() === self::MODE_ORIENTATION_PORTRAIT;
    }

    /**
     * @param int $width
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    public function getAutoDimension(int $width, int $height): array
    {
        $originalOrientations = $this->getOriginalOrientation();
        if ($originalOrientations === self::MODE_ORIENTATION_LANDSCAPE) {
            return $this->getLandscapeDimension($width);
        }
        if ($originalOrientations === self::MODE_ORIENTATION_PORTRAIT) {
            return $this->getPortraitDimension($width);
        }

        $orientation = $this->getOrientation();
        if ($orientation === self::MODE_ORIENTATION_LANDSCAPE) {
            return $this->getLandscapeDimension($width);
        }
        if ($orientation === self::MODE_ORIENTATION_PORTRAIT) {
            return $this->getPortraitDimension($width);
        }
        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    public static function fromStreamResource($imageResource, Resizer $resizer): static
    {
        return new static($resizer, $imageResource);
    }

    public static function fromSteamInterface(StreamInterface $stream, Resizer $resizer): static
    {
        return new static($resizer, $stream);
    }

    public static function fromFile(string $imageFile, Resizer $resizer): static
    {
        return new static($resizer, $imageFile);
    }
}