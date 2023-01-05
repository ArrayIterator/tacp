<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

use Aws\Result;
use GuzzleHttp\Psr7\Stream;
use PDO;
use RuntimeException;
use TelkomselAggregatorTask\Libraries\Services\Aws;
use TelkomselAggregatorTask\Libraries\Services\Cleaner;
use TelkomselAggregatorTask\Libraries\Services\ThumbnailGenerator;
use TelkomselAggregatorTask\Runner;
use Throwable;

class Worker
{
    public readonly Runner $runner;
    public readonly int $days;

    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
        $days = $this->runner->screen_shot_config['days']??3;
        $days = is_numeric($days) ? (int) $days : 3;
        $this->days = $days < 1 ? 1 : (min($days, 30));
    }

    public function start()
    {
        /*
-- index
--  "contentUrl", "coverImage", "updatedAt", task_status, retry_status, generate_thumbnail_status
         */
        $maxRetry = 3;
        $days = $this->days;
        // regex video
        $regex = '^\s*https?:\/\/[^\/]+\/.+\.(mp4|jpg|png|jpeg|mov|avi|mkv|webm|flv|wmv|m4v)(\?.*)?$';
        $query = <<<SQL
SELECT
    id,
    "name" as name,
    "contentUrl" as content_url,
    task_status,
    retry_status,
    source_url,
    source_type
FROM
    contents
WHERE
    (
        "contentUrl" IS NOT NULL
        AND
            trim("contentUrl") ~ '$regex'
    )
  AND (
        "updatedAt" >=  (now() - INTERVAL '$days day')
    )
    AND ("task_status" IS NULL OR LOWER("task_status") NOT LIKE '%error%')
    AND (
        ("task_status" IS NULL)
        OR (TRIM("task_status") = '')
        OR (
            LOWER("task_status") LIKE '%fail%'
            AND (
                retry_status IS NULL OR (retry_status < $maxRetry AND retry_status > -1)
            )
        )
    )
    AND (
        ("task_status" IS NULL) OR (
            LOWER(task_status) !~ '(skipped|process|download|finish|upload|success)'
        )
    )
    AND (
        generate_thumbnail_status IS NULL
        OR generate_thumbnail_status = false
    )
  AND (
        ("coverImage" IS NULL) OR (TRIM("coverImage") = '')
    )
ORDER
    BY
    "updatedAt" ASC,
    retry_status ASC
LIMIT 1
SQL;

        try {
            $stmt = $this->runner->postgre->query($query);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            unset($stmt);
        } catch (Throwable $e) {
            $this->runner->console->error((string) $e);
            return;
        }

        if (empty($data)) {
            return;
        }
        $id = $data['id']??null;
        if (!is_numeric($id)) {
            return;
        }
        $id = (int) $id;
        $this->runner->console->writeln(
            sprintf('<fg=blue>[i] Processing</> Content id [<fg=red>%d</>]', $id)
        );

        $url = trim($data['content_url']);
        $retry_status = (int) ($data['retry_status']??0);
        $retry_status = $retry_status < 1 ? 0 : $retry_status + 1;
        $retry = $retry_status;
        $serviceGenerator = $this->runner->services->getService(ThumbnailGenerator::class);
        try {
            $this->runner->postgre->query(
                "UPDATE contents
                SET
                    task_status = 'Processing',
                    retry_status = '$retry',
                    generate_thumbnail_status = false
                WHERE id = '$id'
            "
            );
        } catch (Throwable $e) {
            $this->runner->console->error((string) $e);
            $this->runner->postgre->query(
                "UPDATE contents SET task_status='Failed' WHERE id='$id'"
            );
            return;
        }

        $callback = static function (
            $param,
            $message,
            Runner $runner,
            $size,
            $count,
            $length
        ) use ($id) {
            if (( $count % 2000 ) === 0 || $count === ((int) $size)) {
                $percent = $size > 0 ? round((int) $size / $length * 100, 2) : 0;
                if ($count === $size) {
                    $percent = 100;
                }
                try {
                    $runner->postgre->query(
                        "UPDATE contents SET task_status='Downloading {$percent}%' WHERE id='$id'"
                    );
                } catch (Throwable) {
                    // pass
                }
                $runner
                    ->console
                    ->writeln("<fg=blue>[i] $message</> [<fg=red>$percent%</>]");
                if ($count === $size) {
                    $runner->createNewConsoleOutput();
                }
            }
        };

        $this->runner->events->add('ancestor:download', $callback);
        try {
            if (preg_match('~\.(jpg|jpeg|png)(?:[?]|$)~i', $url)) {
                $sock = fopen($url, 'rb');
                if (!$sock) {
                    $this->runner->postgre->query(
                        "UPDATE 
                        contents
                            SET task_status='Error : Can not download image.'
                        WHERE id='$id'"
                    );
                    return;
                }
                $fileName = tempnam(sys_get_temp_dir(), 'image-task');
                $stream = new Stream(fopen($fileName, 'wb+'));
                $size = 0;
                $length = 0;
                $meta = stream_get_meta_data($sock);
                if (is_array($meta['wrapper_data']??null)) {
                    $wrapper =  implode("\n", $meta['wrapper_data']);
                    preg_match('~Content-Length\s*:\s*([0-9]+)(?:\s|$)~', $wrapper, $match);
                    $length = (int) $match[1];
                }
                $count = 0;
                $this->runner->events->dispatch(
                    'ancestor:download',
                    'Downloading Image',
                    $size,
                    $count,
                    $length,
                    $meta
                );

                while (!feof($sock)) {
                    $read = fread($sock, 4096);
                    $size += strlen($read);
                    $this->runner->events->dispatch(
                        'ancestor:download',
                        'Downloading Image',
                        $size,
                        $count,
                        $length,
                        $meta
                    );
                    if (!$read) {
                        break;
                    }
                    $stream->write($read);
                }
                $stream->rewind();
                fclose($sock);
                $stream->close();
                $this->runner->events->dispatch(
                    'ancestor:download',
                    'Downloading Image',
                    $size,
                    $count,
                    $size,
                    $meta
                );
                $result = $serviceGenerator->resize($fileName);
            } else {
                $result = $serviceGenerator->process([
                    'video' => $url
                ]);
            }
        } catch (RuntimeException $e) {
            $this->runner->postgre->query("UPDATE contents SET task_status='Failed' WHERE id='$id'");
            return;
        } catch (Throwable $e) {
            $this->runner->postgre->query("UPDATE contents SET task_status='Error: {$e->getMessage()}' WHERE id='$id'");
            return;
        } finally {
            $this->runner->events->remove('ancestor:download');
            if (isset($e)) {
                $this->runner->console->error((string)$e);
            } elseif (!empty($result['path']) && file_exists($result['path'])) {
                $this->processUpload($result['path'], $data);
            } else {
                $this->runner->postgre->query("UPDATE contents SET task_status='Failed' WHERE id='$id'");
            }
            if (isset($fileName) && file_exists($fileName)) {
                unlink($fileName);
            }
        }
    }

    private function processUpload(string $file, array $data): void
    {
        try {
            $id       = (int)$data['id'];
            $service  = $this->runner->services->getService(Aws::class);
            $baseName = basename($file);
            $result   = $service->process([
                'source' => $file,
                'target' => "content/thumbnails/$id/$baseName"
            ]);
            if (!$result instanceof Result) {
                $this->runner->postgre->query(
                    "UPDATE contents SET task_status='Error: Error upload to aws' WHERE id='$id'"
                );
                return;
            }

            $location = $result->hasKey('ObjectURL')
                ? $result->get('ObjectURL')
                : ($result->hasKey('Location') ? $result->hasKey('Location') : null);
            if ($location) {
                $location = $this->runner->postgre->quote($location);
                $this->runner->postgre->query(
                    "UPDATE contents 
                        SET
                            task_status = 'Success',
                            \"coverImage\" = $location,
                            generate_thumbnail_status = true
                        WHERE id='$id'"
                );
            } else {
                $this->runner->postgre->query(
                    "UPDATE contents SET task_status='Error to get object url' WHERE id='$id'"
                );
            }
        } finally {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    /** @noinspection SqlResolve */
    public function doClean(): void
    {
        $clean = $this->runner->config['clean'];
        $clean = is_numeric($clean) ? (int) $clean : 3;
        $clean = $clean < 1 ? 1 : (min($clean, 12));
        $six_hour = 3600 * $clean;
        $this->runner->console->writeln(
            '<fg=blue>[i] Clean Check Every '.($clean).' Hours</>'
        );
        $timestamp = time();
        try {
            $lastClean = $this->runner->sqlite->query(
                "SELECT result FROM meta where task_name = 'cleaning'"
            )->fetch(PDO::FETCH_ASSOC);
            $exist = !empty($lastClean);
            $lastClean = $lastClean['result']??null;
            $lastClean = $lastClean ?: 0;
            $lastClean = is_numeric($lastClean) ? (int) $lastClean : $lastClean;
            $do = (!is_int($lastClean) || $lastClean && (
                ($lastClean + $six_hour) < $timestamp)
                || ($lastClean-3600) > $timestamp
            );
            if (!$do) {
                $this->runner->console->writeln(
                    '<fg=blue>[i] Skipped. Last clean is:</> '
                    . '('
                    . date('Y-m-d H:i:s T', $lastClean)
                    . ')'
                );
            }
        } catch (Throwable $e) {
            $this->runner->console->error('Clean: '.$e->getMessage());
            return;
        }
        if (!$do) {
            return;
        }

        $query = !$exist ? "
            INSERT INTO meta (task_name, result) VALUES ('cleaning', '$timestamp')
        " : "UPDATE meta set result='$timestamp' WHERE task_name = 'cleaning'";
        $this->runner->sqlite->query($query);
        $this->runner->services->getService(Cleaner::class)?->process([]);
    }

    public function __destruct()
    {
        $this->doClean();
    }
}
