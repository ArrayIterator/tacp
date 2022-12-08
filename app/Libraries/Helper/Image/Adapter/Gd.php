<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image\Adapter;

use GdImage;
use RuntimeException;
use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\Exceptions\ImageIsNotSupported;

class Gd extends AbstractImageAdapter
{
    /**
     * @var ?array
     */
    private static ?array $supportedMimeTypes = null;

    /**
     * @var ?array
     */
    private ?array $last_set_image = null;

    /**
     * @var ?GdImage
     */
    private ?GdImage $image_resized = null;

    public function getSupportedMimeTypeExtensions(): array
    {
        return array_keys(self::getSupportedMimeTypeExtensionsFunctions());
    }

    private function getSupportedMimeTypeExtensionsFunctions(): array
    {
        if (self::$supportedMimeTypes === null) {
            self::$supportedMimeTypes = [];
            $mime = [
                'gif' => 'gif',
                'jpeg' => [
                    'jpeg',
                    'x-icon',
                    'ico',
                    'jpg',
                ],
                'xbm' => 'xbm',
                'png' => 'png',
                'webp' => 'webp',
                'bmp' => 'bmp',
                'wbmp' => 'wbmp',
            ];

            foreach ($mime as $fn => $image) {
                if (function_exists("image$fn") && function_exists("imagecreatefrom$fn")) {
                    if (is_array($image)) {
                        foreach ($image as $img) {
                            self::$supportedMimeTypes[$img] = $fn;
                        }
                        continue;
                    }
                }
                self::$supportedMimeTypes[$image] = $fn;
            }
        }

        return self::$supportedMimeTypes;
    }

    /**
     * @return GdImage
     */
    protected function getResource() : GdImage
    {
        if ($this->resource instanceof GdImage) {
            return $this->resource;
        }

        $extension = $this->getOriginalStandardExtension();
        $fn = $this->getSupportedMimeTypeExtensionsFunctions()[$extension]??null;
        if (!$fn) {
            throw new ImageIsNotSupported(
                sprintf('Image extension %s is not supported', $extension)
            );
        }
        $fn = "imagecreatefrom$fn";

        /**
         * @var GdImage
         */
        $this->resource = $fn($this->getFileSource());
        /**
         * if image type PNG save Alpha Blending
         */
        if ($this->getImageType() == IMAGETYPE_PNG) {
            imagealphablending($this->resource, true); // setting alpha blending on
            imagesavealpha($this->resource, true); // save alpha blending setting (important)
        }
        $this->width  = imagesx($this->resource)?:$this->width;
        $this->height = imagesy($this->resource)?:$this->height;
        return $this->resource;
    }

    /**
     * @param GdImage $sourceGD
     * @param int $height
     * @param int $width
     *
     * @return ?GdImage
     */
    private function cropProcess(
        GdImage $sourceGD,
        int $width,
        int $height
    ): ?GdImage {
        $source_width  = imagesx($sourceGD);
        $source_height = imagesy($sourceGD);
        $source_aspect_ratio = $source_width / $source_height;
        $desired_aspect_ratio = $width / $height;
        if ($source_aspect_ratio > $desired_aspect_ratio) {
            /*
             * Triggered when source image is wider
             */
            $temp_height = $height;
            $temp_width = ( int ) ($height * $source_aspect_ratio);
        } else {
            /*
             * Triggered otherwise (i.e. source image is similar or taller)
             */
            $temp_width = $width;
            $temp_height = ( int ) ($width / $source_aspect_ratio);
        }

        /*
         * Resize the image into a temporary GD image
         */

        $temporaryGD = imagecreatetruecolor($temp_width, $temp_height);
        imagecopyresampled(
            $temporaryGD,
            $sourceGD,
            0,
            0,
            0,
            0,
            $temp_width,
            $temp_height,
            $source_width,
            $source_height
        );
        /*
         * Copy cropped region from temporary image into the desired GD image
         */
        $x0 = (($temp_width - $width) / 2);
        $y0 = ( int ) (($temp_height - $height) / 2);
        $resultGD = imagecreatetruecolor($width, $height);
        imagecopy(
            $resultGD,
            $temporaryGD,
            0,
            0,
            $x0,
            $y0,
            $width,
            $height
        );
        imagedestroy($temporaryGD);
        return $resultGD;
    }

    /**
     * @param int $width
     * @param int $height
     * @param int $mode
     *
     * @return Gd
     */
    public function resize(int $width, int $height, int $mode = self::MODE_AUTO): static
    {
        $this->resource = $this->getResource();
        $srcWidth = imagesx($this->resource);
        $srcHeight = imagesy($this->resource);
        $this->last_set_image = [
            'height' => $width,
            'width' => $height,
            'mode' => $mode
        ];
        $dimensions = $this->getDimensions($width, $height, $mode);
        if ($this->image_resized instanceof GdImage) {
            imagedestroy($this->image_resized);
        }
        if (($mode & self::MODE_CROP) === self::MODE_CROP
            || (!$this->isSquare()
                && ($mode & self::MODE_ORIENTATION_SQUARE) === self::MODE_ORIENTATION_SQUARE
            )
        ) {
            $this->image_resized = $this->cropProcess(
                $this->resource,
                $dimensions['width'],
                $dimensions['height']
            );
        } else {
            $this->image_resized = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
            imagecopyresampled(
                $this->image_resized,
                $this->resource,
                0,
                0,
                0,
                0,
                $dimensions['width'],
                $dimensions['height'],
                $srcWidth,
                $srcHeight,
            );
        }

        $this->width  = imagesx($this->image_resized);
        $this->height = imagesy($this->image_resized);

        imagedestroy($this->resource);
        $this->resource = null;
        return $this;
    }

    /**
     * Save The image result reSized
     *
     * @param string $target Full path of file name eg [/path/of/dir/image/image.jpg]
     * @param integer $quality image quality [1 - 100]
     * @param bool $overwrite force rewrite existing image if there was path exists
     * @param string|null $forceOverrideExtension force using extensions with certain output
     *
     * @return ?array                null if on fail otherwise array
     */
    public function saveTo(
        string $target,
        int $quality = 100,
        bool $overwrite = false,
        ?string $forceOverrideExtension = null
    ): ?array {
        // check if it has on cropProcess
        if (! $this->image_resized instanceof GdImage) {
            if (!($this->last_set_image['width']??null)) {
                $this->image_resized = $this->getResource();
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
            $forceOverrideExtension = strtolower(trim($forceOverrideExtension));
            $fn = $this->getSupportedMimeTypeExtensionsFunctions()[$forceOverrideExtension]??null;
        }
        $extensionLower = strtolower($extension);
        $fn = $fn??$this->getSupportedMimeTypeExtensionsFunctions()[$extensionLower]??null;
        // check if image output type allowed
        if (!$fn) {
            $ext = $this->getOriginalStandardExtension();
            $fn = $this->getSupportedMimeTypeExtensionsFunctions()[$ext]??null;
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
        $ret_val = false;
        switch ($fn) {
            case 'jpeg':
                $ret_val = imagejpeg($this->image_resized, $target, $quality);
                break;
            case 'wbmp':
                $ret_val = imagewbmp($this->image_resized, $target);
                break;
            case 'bmp':
                $ret_val = imagebmp($this->image_resized, $target);
                break;
            case 'gif':
                $ret_val = imagegif($this->image_resized, $target);
                break;
            case 'xbm':
                $ret_val = imagexbm($this->image_resized, $target);
                break;
            case 'webp':
                $ret_val = imagewebp($this->image_resized, $target, $quality);
                break;
            case 'png':
                $scaleQuality = $quality > 9
                    ? round(($quality / 100) * 9)
                    : $quality;
                $invertScaleQuality = 9 - $scaleQuality;
                $ret_val = imagepng(
                    $this->image_resized,
                    $target,
                    (int) $invertScaleQuality
                );
                break;
        }

        $width  = imagesx($this->image_resized);
        $height = imagesy($this->image_resized);
        $path   = is_file($target) ? realpath($target) : $target;

        // destroy resource to make memory free
        imagedestroy($this->image_resized);
        $this->image_resized = null;

        return ! $ret_val ? null : [
            'width' => $width,
            'height' => $height,
            'path' => $path,
            'type' => $fn,
        ];
    }

    protected function clearResource()
    {
        if ($this->resource instanceof GdImage) {
            imagedestroy($this->resource);
        }
        if ($this->image_resized instanceof GdImage) {
            imagedestroy($this->image_resized);
        }

        $this->resource = null;
        $this->image_resized = null;
    }

    public function __destruct()
    {
        $this->clearResource();
        $debug = (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
        if (count($debug) === 1
            && count($debug[0]) === 3
            && $this->isUseTemp() && is_file($this->getFileSource())
        ) {
            unlink($this->getFileSource());
        }
    }
}
