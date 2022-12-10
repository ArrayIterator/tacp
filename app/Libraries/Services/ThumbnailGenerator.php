<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Services;

use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use TelkomselAggregatorTask\Libraries\AbstractService;
use TelkomselAggregatorTask\Libraries\Helper\Ffmpeg\FrameAncestor;
use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\ImageAdapterInterface;
use TelkomselAggregatorTask\Libraries\Helper\Image\ResizerFactory;
use TelkomselAggregatorTask\Libraries\Services;
use Throwable;

class ThumbnailGenerator extends AbstractService
{
    #[Pure] public function __construct(Services $services, ?array $config = null)
    {
        $config ??= $services->runner->screen_shot_config;
        $configurations = $services->runner->screen_shot_config;
        $width = $screenShots['width']??null;
        $height = $screenShots['width']??null;
        $second = $screenShots['second']??null;
        if (is_numeric($width)) {
            $configurations['width'] = (int) $width;
        }
        if (is_numeric($height)) {
            $configurations['height'] = (int) $height;
        }
        if (is_numeric($second)) {
            $configurations['second'] = (int) $second;
        }
        if ($configurations['second'] < 0) {
            $configurations['second'] = 1;
        }
        if ($configurations['height'] < 100) {
            $configurations['height'] = 100;
        }
        if ($configurations['width'] < 100) {
            $configurations['width'] = 100;
        }
        parent::__construct($services, $config);
    }

    /**
     * @param array{"video":string} $arguments
     *
     * @return ?array{"width":int,"height":int,"path":string,"type":string}
     * @throws Throwable
     */
    public function process(array $arguments): ?array
    {
        $videoInput = $arguments['video']??null;
        if (!is_string($videoInput)) {
            return null;
        }
        $frame = new FrameAncestor($this->services->runner);
        $meta = $frame->createVideoMeta($videoInput);
        $image = $meta->getFrameInSecond($this->config['second']);
        if (!$image || !file_exists($image)) {
            throw new InvalidArgumentException(
                'Can not generate image'
            );
        }

        $cacheImageDirectory = $frame->image_cache_directory;
        $fileName = sprintf(
            '%s/%dx%d-%s',
            $cacheImageDirectory,
            $this->config['width'],
            $this->config['height'],
            basename($image)
        );
        $resizeFactory = (new ResizerFactory())->create($image);
        $result = $resizeFactory->resize(
            $this->config['width'],
            $this->config['height'],
            ImageAdapterInterface::MODE_CROP
        )->saveTo($fileName, 90, true, 'jpg');
        unset($frame, $meta);
        return $result;
    }
}
