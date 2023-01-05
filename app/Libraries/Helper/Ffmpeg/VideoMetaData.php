<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Ffmpeg;

use InvalidArgumentException;

class VideoMetaData
{
    private ?int $frameCount = null;
    private ?int $duration = null;
    private array $fileNames = [];

    /**
     * @param FrameAncestor $frameAncestor
     * @param string $sourceOriginalFileName
     * @param string $sourceVideoFile
     * @internal
     */
    public function __construct(
        public readonly FrameAncestor $frameAncestor,
        public readonly string $sourceOriginalFileName,
        public readonly string $sourceVideoFile
    ) {
        if ((debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class']??null) !== FrameAncestor::class) {
            throw new InvalidArgumentException(
                'Video meta data only allowed via'
            );
        }
    }

    /**
     * @return int
     */
    public function getFrameCount(): int
    {
        if (is_int($this->frameCount)) {
            return $this->frameCount;
        }
        $this->frameCount = 0;
        $command = $this->frameAncestor->generateFrameCountCommand($this->sourceVideoFile);
        $count = $this->frameAncestor->runner->shellString($command);
        $count = trim((string) $count);
        if (is_numeric($count)) {
            $this->frameCount = (int) $count;
        }
        return $this->frameCount;
    }

    public function getDuration(): int
    {
        if (is_int($this->duration)) {
            return $this->duration;
        }
        $this->duration = 0;
        $command = $this->frameAncestor->generateDurationCommand($this->sourceVideoFile);
        $count = $this->frameAncestor->runner->shellString($command);
        $count = trim((string) $count);
        if (is_numeric($count)) {
            $this->duration = (int) $count;
        }
        return $this->duration;
    }

    /**
     * @param int $second
     *
     * @return ?string
     */
    public function getFrameInSecond(int $second) : ?string
    {
        if (isset($this->fileNames[$second])) {
            return $this->fileNames[$second]?:null;
        }
        $duration = $this->getDuration();
        // get last
        $second = min($duration, $second);
        $fileName = $this->frameAncestor->generateImageFileName();
        $command = $this->frameAncestor->generateFrameCommand(
            $this->sourceVideoFile,
            $second,
            1,
            $fileName
        );

        $command = "$command &> /dev/null";
        $this->frameAncestor->runner->shellString($command);
        $this->fileNames[$second] = false;
        if (is_file($fileName)) {
            $this->fileNames[$second] = $fileName;
        }
        return $this->fileNames[$second]?:null;
    }

    /**
     * @return array<int, string>
     */
    public function getGeneratedFileNames(): array
    {
        return $this->fileNames;
    }

    public function __destruct()
    {
        if (str_starts_with($this->sourceVideoFile, $this->frameAncestor->video_cache_directory)) {
            unlink($this->sourceVideoFile);
        }
        $this->fileNames = [];

        /*
        foreach ($this->getGeneratedFileNames() as $file) {
            if (is_string($file) && is_file($file) && is_writable($file)) {
                 unlink($file);
            }
        }*/
    }
}
