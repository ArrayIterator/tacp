<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image\Adapter;

use Psr\Http\Message\StreamInterface;
use TelkomselAggregatorTask\Libraries\Helper\Image\ResizerFactory;

interface ImageAdapterInterface
{
    const MODE_AUTO = 1;
    const MODE_CROP = 2;
    const MODE_ORIENTATION_LANDSCAPE = 3;
    const MODE_ORIENTATION_PORTRAIT = 4;
    const MODE_ORIENTATION_SQUARE = 5;

    const IMAGE_TYPE_LIST = [
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_JPEG2000 => 'jpc',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_SWF => 'swf',
        IMAGETYPE_PSD => 'psd',
        IMAGETYPE_BMP => 'bmp',
        IMAGETYPE_TIFF_II => 'tiff',
        IMAGETYPE_TIFF_MM => 'tiff',
        IMAGETYPE_JP2 => 'jp2',
        IMAGETYPE_JPX => 'jpx',
        IMAGETYPE_JB2 => 'jb2',
        IMAGETYPE_SWC => 'swc',
        IMAGETYPE_IFF => 'iff',
        IMAGETYPE_WBMP => 'wbmp',
        IMAGETYPE_XBM => 'xbm',
        IMAGETYPE_ICO => 'ico',
        IMAGETYPE_WEBP => 'webp'
    ];

    /**
     * @return int new width
     */
    public function getWidth() : int;

    /**
     * @return int new height
     */
    public function getHeight() : int;

    /**
     * @return array<string>
     */
    public function getSupportedMimeTypeExtensions() : array;

    /**
     * @param string $mimeType
     *
     * @return bool
     */
    public function isMimeTypeSupported(string $mimeType) : bool;

    /**
     * @return int returning null if file / resource invalid
     */
    public function getOriginalWidth() : int;

    /**
     * @return int returning null if file / resource invalid
     */
    public function getOriginalHeight() : int;

    /**
     * @return array{"width":float,"height":float}
     */
    public function getRatio() : array;

    /**
     * @return array
     */
    public function getOriginalRatio() : array;

    /**
     * @return int MODE_ORIENTATION_LANDSCAPE|MODE_ORIENTATION_PORTRAIT|MODE_ORIENTATION_SQUARE
     */
    public function getOrientation() : int;

    /**
     * @return int MODE_ORIENTATION_LANDSCAPE|MODE_ORIENTATION_PORTRAIT|MODE_ORIENTATION_SQUARE
     */
    public function getOriginalOrientation() : int;

    /**
     * @param int $width
     * @param int $height
     * @param int $mode
     *
     * @return array{"width":int,"height":int}
     */
    public function getDimensions(int $width, int $height, int $mode = self::MODE_AUTO) : array;

    /**
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    public function getPortraitDimension(int $height) : array;

    /**
     * @param int $width
     *
     * @return array{"width":int,"height":int}
     */
    public function getLandscapeDimension(int $width) : array;

    /**
     * @param int $width
     * @param int $height
     *
     * @return array{"width":int,"height":int}
     */
    public function getAutoDimension(int $width, int $height) : array;

    /**
     * @param int $width
     * @param int $height
     * @param int $mode
     *
     * @return static
     */
    public function resize(int $width, int $height, int $mode = self::MODE_AUTO) : static;

    /**
     * @param string $target
     * @param int $quality
     * @param false $overwrite
     * @param string $forceOverrideExtension
     *
     * @return ?array
     */
    public function saveTo(
        string $target,
        int $quality = 100,
        bool $overwrite = false,
        ?string $forceOverrideExtension = null
    ) : ?array;

    /**
     * @param resource $imageResource fopen()
     * @param ResizerFactory $resizer
     *
     * @return static
     */
    public static function fromStreamResource($imageResource, ResizerFactory $resizer) : static;

    /**
     * @param StreamInterface $stream
     * @param ResizerFactory $resizer
     *
     * @return static
     */
    public static function fromSteamInterface(StreamInterface $stream, ResizerFactory $resizer) : static;

    /**
     * @param string $imageFile
     * @param ResizerFactory $resizer
     *
     * @return static
     */
    public static function fromFile(string $imageFile, ResizerFactory $resizer) : static;
}
