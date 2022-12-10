<?php
// @todo ffpmeg get scene
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Helper\Ffmpeg;

use InvalidArgumentException;
use RuntimeException;
use TelkomselAggregatorTask\Runner;
use Throwable;

class FrameAncestor
{
    const GENERATE_COMMANDS = '%ffmpeg% -i %input% -movflags +faststart -ss %second% -vframes %count% %out%';

    const MAX_READ_BYTES = 4096 * 1024 * 1024;

    public readonly string $video_cache_directory;
    public readonly string $image_cache_directory;

    public function __construct(public readonly Runner $runner)
    {
        $videoCacheDir = $this->runner->cache_directory .'/ffmpeg-video';
        $imageCacheDir = $this->runner->cache_directory .'/ffmpeg-image';
        if (!is_dir($imageCacheDir)) {
            mkdir($imageCacheDir, 0755, true);
        }
        if (!is_dir($imageCacheDir)) {
            mkdir($imageCacheDir, 0755, true);
        }

        $this->video_cache_directory = realpath($videoCacheDir)?:$videoCacheDir;
        $this->image_cache_directory = realpath($imageCacheDir)?:$imageCacheDir;
    }

    public function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ??
               'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36';
    }

    public function generateFrameCountCommand(string $videoFile): string
    {
        $videoFile = trim($videoFile);
        if (str_contains($videoFile, "\n")) {
            throw new InvalidArgumentException(
                'Input could not contain new line'
            );
        }
        return sprintf(
            "'%s' -v error -select_streams v:0 -count_packets -show_entries stream=nb_read_packets -of csv=p=0 '%s'",
            addcslashes($this->runner->ffprobe, "\\'"),
            addcslashes($videoFile, "\\'")
        );
    }

    public function generateDurationCommand(string $videoFile): string
    {
        $videoFile = trim($videoFile);
        if (str_contains($videoFile, "\n")) {
            throw new InvalidArgumentException(
                'Input could not contain new line'
            );
        }
        return sprintf(
            "'%s' -v error -select_streams v:0 -count_packets -show_entries stream=duration -of csv=p=0 '%s'",
            addcslashes($this->runner->ffprobe, "\\'"),
            addcslashes($videoFile, "\\'")
        );
    }

    /**
     * @param string $inputVideo
     * @param int $seconds
     * @param int $frame
     * @param string $output
     *
     * @return string
     */
    public function generateFrameCommand(
        string $inputVideo,
        int $seconds,
        int $frame,
        string $output
    ): string {
        $inputVideo = trim($inputVideo);
        if (str_contains($inputVideo, "\n")) {
            throw new InvalidArgumentException(
                'Input could not contain new line'
            );
        }
        return str_replace(
            [
                '%ffmpeg%',
                '%input%',
                '%second%',
                '%count%',
                '%out%',
            ],
            [
                sprintf("'%s'", addcslashes($this->runner->ffmpeg, "\\'")),
                sprintf("'%s'", addcslashes($inputVideo, "\\'")),
                $seconds,
                $frame,
                sprintf("'%s'", addcslashes($output, "\\'")),
            ],
            self::GENERATE_COMMANDS
        );
    }

    public function generateImageFileName(): string
    {
        $random = sha1(microtime());
        $imageDir = $this->image_cache_directory . DIRECTORY_SEPARATOR;
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }
        $cacheFile = $imageDir . $random . '.jpg';
        while (file_exists($cacheFile)) {
            $random = sha1(microtime() . mt_rand(1000, 10000));
            $cacheFile = $imageDir . $random . '.jpg';
        }
        return $cacheFile;
    }

    public function generateVideoFileName(string $name): string
    {
        $name = basename($name);
        $random = sha1(microtime());
        $ext = pathinfo($name, PATHINFO_EXTENSION)??'video';
        $videoCacheDirectory = $this->video_cache_directory . DIRECTORY_SEPARATOR;
        $cacheFile = $videoCacheDirectory . $random . '.' . $ext;
        while (file_exists($cacheFile)) {
            $random = sha1(microtime() . mt_rand(1000, 10000));
            $cacheFile = $videoCacheDirectory . $random . '.' . $ext;
        }
        return $cacheFile;
    }

    /**
     * @param string $inputVideo
     *
     * @return VideoMetaData
     */
    public function createVideoMeta(
        string $inputVideo
    ): VideoMetaData {
        $originalInput = $inputVideo;
        if (preg_match('~^(https?)://~i', $inputVideo, $match)) {
            $header = "Accept-language: en-US,en;q=0.9,id;q=0.8,es;q=0.7\r\n"
                  . "Cache-Control: no-cache\r\n"
                  . "Pragma: no-cache\r\n"
                  . "Upgrade-Insecure-Requests: 1\r\n"
                  . "user-agent: ".trim($this->getUserAgent())."\r\n";
            if (strtolower($match[1]) === 'https') {
                $args = [
                    'https' => [
                        'method' => 'GET',
                        'header'=> $header,
                        'max_redirects' => 10,
                        'user_agent' => $this->getUserAgent(),
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ]
                ];
            } else {
                $args = [
                    'http' => [
                        'method' => 'GET',
                        'header' => $header,
                        'max_redirects' => 10,
                        'user_agent' => $this->getUserAgent()
                    ]
                ];
            }
            $context = stream_context_create($args);
            try {
                set_error_handler(function ($errno, $errstr) use ($inputVideo) {
                    throw new RuntimeException(
                        sprintf(
                            'Can not get requested url %s with error: %s',
                            $inputVideo,
                            $errstr
                        ),
                        $errno
                    );
                });
                $socketURL = fopen($inputVideo, 'rb', false, $context);
            } catch (Throwable $e) {
                restore_error_handler();
                throw $e;
            }
            restore_error_handler();
            $tempFileName = $this->generateVideoFileName($inputVideo);
            try {
                set_error_handler(function ($errno, $errstr) use ($tempFileName) {
                    throw new RuntimeException(
                        sprintf(
                            'Can not create file %s with error: %s',
                            $tempFileName,
                            $errstr
                        ),
                        $errno
                    );
                });
                $videoSocket = fopen($tempFileName, 'wb');
            } catch (Throwable $e) {
                restore_error_handler();
                throw $e;
            }
            $size = 0;
            while (!feof($socketURL) && $size < self::MAX_READ_BYTES) {
                $content = fread($socketURL, 4096);
                $written = fwrite($videoSocket, $content);
                if ($written === false) {
                    break;
                }
                $size += $written;
            }
            fclose($socketURL);
            fclose($videoSocket);
            $inputVideo = realpath($tempFileName)?:$tempFileName;
        }
        if (!file_exists($inputVideo)) {
            throw new InvalidArgumentException(
                sprintf('File %s has not found', $inputVideo)
            );
        }
        if (!is_file($inputVideo)) {
            throw new InvalidArgumentException(
                sprintf('File %s is not a file', $inputVideo)
            );
        }

        return new VideoMetaData(
            $this,
            $originalInput,
            $inputVideo
        );
    }
}
