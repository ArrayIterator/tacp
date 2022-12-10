<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Services;

use TelkomselAggregatorTask\Libraries\AbstractService;
use TelkomselAggregatorTask\Libraries\Helper\Ffmpeg\FrameAncestor;

/**
 * Clean temporary directories
 */
final class Cleaner extends AbstractService
{
    public function process(array $arguments)
    {
        $frameAncestor = new FrameAncestor($this->services->runner);
        $imageTemp = $frameAncestor->image_cache_directory;
        $videoTemp = $frameAncestor->video_cache_directory;
        $resizeTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'image-resize';
        $this->clear($imageTemp);
        $this->clear($videoTemp);
        $this->clear($resizeTemp);
    }

    private function clear(string $directory)
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            return;
        }
        $directory = realpath($directory)?:$directory;
        set_error_handler(static function () {
        });
        $dir = @opendir($directory);
        restore_error_handler();
        if (!$dir) {
            return;
        }
        // 2 days clean
        $two_days = strtotime('+2 days');
        while (($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $file = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_file($file)) {
                $time = filemtime($file);
                $owner = fileowner($file);
                if ($owner === $this->services->runner->uid && ($time - $two_days) > 0) {
                    //unlink($file);
                    echo $file."\n";
                }
                continue;
            }
            if (is_dir($file)) {
                $this->clearDir($file, $two_days);
            }
        }
        closedir($dir);
    }

    /**
     * @param string $directory
     * @param int $timestamp
     */
    private function clearDir(string $directory, int $timestamp)
    {
        $dir = @opendir($directory);
        if (!$dir) {
            return;
        }

        $skipped = false;
        while (($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $file = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_file($file)) {
                $time = filemtime($file);
                $owner = fileowner($file);
                if ($owner === $this->services->runner->uid && ($time - $timestamp) > 0) {
                    unlink($file);
                    continue;
                }
                $skipped = true;
                continue;
            }
            if (is_dir($file)) {
                $this->clearDir($file, $timestamp);
            }
        }
        closedir($dir);
        set_error_handler(static function () {
        });
        $skipped === false && @rmdir($directory);
        restore_error_handler();
    }
}
