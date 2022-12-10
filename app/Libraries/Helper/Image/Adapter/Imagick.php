<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image\Adapter;

use Imagick as ImagickAlias;
use ImagickException;
use RuntimeException;
use TelkomselAggregatorTask\Libraries\Helper\Image\Exceptions\ImageIsNotSupported;
use TelkomselAggregatorTask\Libraries\Helper\Image\Exceptions\UnsupportedAdapter;

class Imagick extends AbstractImageAdapter
{
    /**
     * @var ?array
     */
    private ?array $last_set_image = null;

    public function getSupportedMimeTypeExtensions(): array
    {
        return array_keys(self::MIME_TYPES);
    }

    /**
     * @throws ImagickException
     */
    protected function getImageMagicK() : ?ImagickAlias
    {
        $this->resource?->destroy();
        if (!class_exists(ImagickAlias::class)) {
            throw new UnsupportedAdapter(
                'Extension Imagick has not loaded.'
            );
        }

        $this->resource = new ImagickAlias($this->getFileSource());
        return $this->resource;
    }

    /**
     * @throws ImagickException
     */
    public function resize(int $width, int $height, int $mode = self::MODE_AUTO): static
    {
        $this->resource = $this->getImageMagicK();
        $this->last_set_image = [
            'height' => $width,
            'width' => $height,
            'mode' => $mode
        ];
        $optimize = true;
        $dimensions = $this->getDimensions($width, $height, $mode);
        if (($mode & self::MODE_CROP) === self::MODE_CROP
            || (!$this->isSquare()
                && ($mode & self::MODE_ORIENTATION_SQUARE) === self::MODE_ORIENTATION_SQUARE
            )
        ) {
            [
                $tempWidth,
                $tempHeight,
                $offsetX,
                $offsetY
                ] = $this->calculateOffset(
                    $this->resource->getImageWidth(),
                    $this->resource->getImageHeight(),
                    $width,
                    $height
                );
            $this->resource->resizeImage(
                (int) $tempWidth,
                (int) $tempHeight,
                ImagickAlias::FILTER_LANCZOS,
                1,
                false
            );
            $this->resource->cropImage(
                $dimensions['width'],
                $dimensions['height'],
                (int) $offsetX,
                (int) $offsetY
            );
        } else {
            $this->resource->resizeImage(
                $dimensions['width'],
                $dimensions['height'],
                ImagickAlias::FILTER_LANCZOS,
                1,
                $optimize
            );
        }
        return $this;
    }

    /**
     * @throws ImagickException
     */
    public function saveTo(
        string $target,
        int $quality = 100,
        bool $overwrite = false,
        ?string $forceOverrideExtension = null,
        bool $strip = true
    ): ?array {
        // check if it has on cropProcess
        if (! $this->resource instanceof ImagickAlias) {
            if (!($this->last_set_image['width']??null)) {
                $this->resource = $this->getImageMagicK();
            } else {
                // set from last result
                $this->resize(
                    $this->last_set_image['width'],
                    $this->last_set_image['height'],
                    $this->last_set_image['mode']
                );
            }
        }

        // Get extension
        $extension = pathinfo($target, PATHINFO_EXTENSION)?:'';
        // file exist
        if (file_exists($target)) {
            if (!$overwrite) {
                return null;
            }
            if (!is_writable($target)) {
                $this->clearResource();
                throw new RuntimeException(
                    'File exist! And could not to be replace',
                    E_USER_WARNING
                );
            }
        }

        $fn = null;
        if ($forceOverrideExtension) {
            $fn = strtolower(trim($forceOverrideExtension));
            $fn = $fn ?: null;
        }

        // check if image output type allowed
        if (!$fn) {
            $fn = $this->getOriginalStandardExtension();
        }
        if (!$fn) {
            $this->clearResource();
            throw new ImageIsNotSupported(
                sprintf('Image extension %s is not supported', $extension)
            );
        }

        $dir_name = dirname($target);
        if (!is_dir($dir_name)) {
            if (!@mkdir($dir_name, 0755, true)) {
                $dir_name = null;
            }
        }

        if (!$dir_name) {
            $this->clearResource();
            throw new RuntimeException(
                'Directory Target Does not exist. Resource image resize cleared.',
                E_USER_WARNING
            );
        }
        if (!is_writable($dir_name)) {
            $this->clearResource();
            throw new RuntimeException(
                'Directory Target is not writable. Please check directory permission.',
                E_USER_WARNING
            );
        }
        // normalize
        $quality = $quality < 10
            ? $quality * 100
            : $quality;
        $strip && $this->resource->stripImage();
        $this->resource->setImageFormat($fn);
        $this->resource->setCompressionQuality($quality);
        $width  = $this->resource->getImageWidth();
        $height = $this->resource->getImageHeight();
        $path   = is_file($target) ? realpath($target) : $target;
        $ret_val = $this->resource->writeImage($path);
        $this->clearResource();
        if ($ret_val && is_file($path)) {
            $ret_val = (bool) getimagesize($path);
            if (!$ret_val) {
                unlink($path);
            }
        }
        return ! $ret_val ? null : [
            'width' => $width,
            'height' => $height,
            'path' => $path,
            'type' => $fn,
        ];
    }

    protected function clearResource()
    {
        $this->resource?->destroy();
        $this->resource = null;
    }
}
