<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Image;

use TelkomselAggregatorTask\Libraries\Helper\Image\Adapter\ImageAdapterInterface;

interface ResizerFactoryInterface
{
    /**
     * @param mixed $source
     *
     * @return ImageAdapterInterface
     */
    public function create(mixed $source) : ImageAdapterInterface;
}
