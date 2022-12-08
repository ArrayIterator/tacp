<?php
// @todo ffpmeg get scene
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Ffmpeg;

use TelkomselAggregatorTask\Runner;

class FrameAncestor
{
    public function __construct(public readonly Runner $runner)
    {
    }

    public function getFirstFrame()
    {
        // $this->runner->shellArray();
    }
}
