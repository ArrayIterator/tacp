<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Services;

use Aws\CommandInterface;
use Aws\Exception\InvalidRegionException;
use Aws\Exception\MultipartUploadException;
use Aws\Exception\TokenException;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TelkomselAggregatorTask\Libraries\AbstractService;
use TelkomselAggregatorTask\Libraries\Services;
use Throwable;

/**
 * @property-read ?Throwable $error
 * @property-read ?S3Client $s3Client
 * @property-read bool $unauthenticated
 * @property-read int $calledUnauthenticated
 */
class Aws extends AbstractService
{
    const DEFAULT_PART_SIZE = MultipartUploader::PART_MIN_SIZE;
    const DEFAULT_PART_MAX_SIZE = MultipartUploader::PART_MIN_SIZE * 512;

    const AVAILABLE_REGIONS = [
        'ap-northeast-1',
        'ap-south-1',
        'ap-southeast-1',
        'ap-southeast-2',
        'aws-global',
        'ca-central-1',
        'eu-central-1',
        'eu-north-1',
        'eu-west-1',
        'eu-west-2',
        'eu-west-3',
        'sa-east-1',
        'us-east-1',
        'us-east-2',
        'us-west-1',
        'us-west-2',
    ];


    /**
     * @var ?S3Client
     */
    private ?S3Client $s3Client = null;

    /**
     * @var ?StreamInterface
     */
    private ?StreamInterface $stream = null;

    /**
     * @var bool
     */
    private bool $unauthenticated = false;

    /**
     * @param Services $services
     * @param array|null $config
     * @see Aws::$config
     */
    public function __construct(Services $services, ?array $config = null)
    {
        $config ??= $services->runner->awsConfig;
        $region = $config['region']??null;
        $region = is_string($region) ? strtolower(trim($region)) : null;
        $secret  = $config['secret']??null;
        $secret  = is_string($secret) ? trim($secret) : null;
        $key = $config['key']??null;
        $key = is_string($key) ? trim($key) : null;
        $url = $config['url']??null;
        $url = !is_string($url) ? null : $url;
        $bucket = $config['bucket']??null;
        $bucket = is_string($bucket) ? trim($bucket) : null;
        $uploadSize = $config['chunk_size']??null;
        $uploadSize = is_numeric($uploadSize) ? (int) $uploadSize : self::DEFAULT_PART_SIZE;
        $uploadSize = max($uploadSize, self::DEFAULT_PART_SIZE);
        $uploadSize = min($uploadSize, self::DEFAULT_PART_MAX_SIZE);
        parent::__construct($services, [
            'region' => $region,
            'secret' => $secret,
            'key' => $key,
            'bucket' => $bucket,
            'uri' => $url ? new Uri($url) : null,
            'chunk_size' => $uploadSize,
        ]);
    }

    #[ArrayShape([
        'region' => 'string',
        'secret' => 'string',
        'key' => 'string',
        'bucket' => 'string',
        'uri' => '?string',
        'chunk_size' => 'int',
    ])] public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return bool|int
     */
    public function configurationCheck() : bool|int
    {
        $region = $this->config['region'];
        $secret  = $this->config['secret'];
        $key = $this->config['key'];
        $bucket = $this->config['bucket'];

        if (!$region || !in_array($region, self::AVAILABLE_REGIONS)) {
            $this->error = new InvalidRegionException(
                $region
                    ? sprintf('Region %s is not compatible', $region)
                    : 'Region is empty'
            );
            return self::SERVICE_INVALID_CONFIG;
        }

        if (!is_string($secret) || strlen($secret) !== 40) {
            $this->error = new TokenException(
                'Secret key is not valid. Secret key must be 40 Character length'
            );
            return self::SERVICE_INVALID_CONFIG;
        }
        if (!is_string($key) || !preg_match('~^[A-Z0-9]{20}$~', $key)) {
            $this->error = new TokenException(
                'Access key is not valid. Access key must be 20 Uppercase Characters length'
            );
            return self::SERVICE_INVALID_CONFIG;
        }
        if (!is_string($bucket) || trim($bucket) === '') {
            $this->error = new TokenException(
                'Bucket is invalid'
            );
            return self::SERVICE_INVALID_CONFIG;
        }

        return true;
    }

    public function getS3() : int|S3Client
    {
        if ($this->s3Client) {
            return $this->s3Client;
        }
        $check = $this->configurationCheck();
        if ($check !== true) {
            return $check;
        }
        $s3Config = [
            'region'  => $this->config['region'],
            'version' => 'latest',
            'credentials' => [
                'key'    => $this->config['key'],
                'secret' => $this->config['secret'],
            ]
        ];

        $this->s3Client = new S3Client($s3Config);
        return $this->s3Client;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return Result|int
     */
    public function process(array $arguments): Result|int
    {
        $file = $arguments['source']??null;
        $target = $arguments['target']??null;
        return $this->doUpload($file, $target);
    }

    /**
     * @param string $target
     *
     * @return Result|int
     */
    public function delete(string $target): Result|int
    {
        if ($this->unauthenticated) {
            if (!$this->error) {
                $this->error = new Exception('Unauthorized! Invalid credentials');
            }
            return self::SERVICE_UNAUTHENTICATED;
        }

        $s3Client = $this->getS3();
        if (!$s3Client instanceof S3Client) {
            return $s3Client;
        }
        $args = [
            'bucket' => $this->config['bucket'],
            'key' => $target,
        ];
        try {
            $this->services->runner->console->writeln(
                sprintf('Deleting <fg=blue>%s</>', $target)
            );
            $result = $s3Client->deleteObject($args);
        } catch (S3Exception $e) {
            $prev = $e->getPrevious();
            if ($prev instanceof BadResponseException) {
                $serviceResponse = self::SERVICE_FAILED;
                if ($prev->getResponse()->getStatusCode() === 403) {
                    $this->unauthenticated = true;
                    $serviceResponse       = self::SERVICE_UNAUTHENTICATED;
                }
                $this->error = $prev;
                $this->stream?->close();
                $this->stream = null;
                $this->services->runner->events->dispatch(
                    'on:error:awsDelete',
                    $e,
                    $serviceResponse,
                    $this,
                    $args
                );
                return $serviceResponse;
            }
            $result = self::SERVICE_FAILED;
        }

        return $result;
    }

    /**
     * @param string $fileName
     * @param string $target
     *
     * @return Result|int
     */
    public function doUpload(string $fileName, string $target): Result|int
    {
        if ($this->unauthenticated) {
            if (!$this->error) {
                $this->error = new Exception('Unauthorized! Invalid credentials');
            }
            return self::SERVICE_UNAUTHENTICATED;
        }

        $this->error = null;
        if (trim($fileName) === '' || !is_file($fileName) || !is_readable($fileName)) {
            $this->error = new FileNotFoundException(
                path: $fileName
            );
            return self::SERVICE_INVALID_ARGUMENT;
        }
        if (trim($target) === '') {
            $this->error = new FileNotFoundException(
                'Target file could not be empty'
            );
            return self::SERVICE_INVALID_ARGUMENT;
        }

        $fileName = realpath($fileName)?:$fileName;
        $s3Client = $this->getS3();
        if (!$s3Client instanceof S3Client) {
            return $s3Client;
        }

        $target = trim($target);
        $bucket = $this->config['bucket'];
        $this->stream = new Stream(fopen($fileName, 'rb'));
        $args = [
            'part_size' => $this->config['chunk_size'],
            'bucket' => $bucket,
            'key' => $target,
            'acl' => 'public-read',
            'before_upload' => function (CommandInterface $command, $key) {
                $this->services->runner->events->dispatch(
                    'on:before:awsUploadProcess',
                    $command,
                    $key,
                    $this
                );
                $this->services->runner->console->writeln(
                    sprintf('Processing <fg=blue>%s</> [%s]', $command->getName(), $key)
                );
            }
        ];
        $this->services->runner->events->dispatch(
            'on:before:awsUpload',
            $args,
            $this
        );
        $uploader = new MultipartUploader(
            $s3Client,
            $this->stream,
            $args
        );
        $maxRetry = 5;
        $lastError = null;
        do {
            $maxRetry--;
            try {
                $result = $uploader->upload();
            } catch (MultipartUploadException $e) {
                $prev = $e->getPrevious()?->getPrevious();
                if ($prev instanceof BadResponseException) {
                    $serviceResponse = self::SERVICE_FAILED;
                    if ($prev->getResponse()->getStatusCode() === 403) {
                        $this->unauthenticated = true;
                        $serviceResponse       = self::SERVICE_UNAUTHENTICATED;
                    }
                    $this->error = $prev;
                    $this->stream?->close();
                    $this->stream = null;
                    $this->services->runner->events->dispatch(
                        'on:error:awsUpload',
                        $e,
                        $serviceResponse,
                        $this,
                        $args
                    );
                    return $serviceResponse;
                }
                $this->services->runner->console->writeln(
                    sprintf('Failed Remaining Retry: %d', $maxRetry)
                );
                $this->stream->rewind();
                $uploader = new MultipartUploader($s3Client, $this->stream, $args + [
                    'state' => $e->getState(),
                ]);
            }
        } while (!isset($result) && $maxRetry > 0);
        $this->stream?->close();
        $this->stream = null;
        if (!isset($result)) {
            $this->error = $lastError?:new Exception(
                'Failed to upload'
            );
            $this->services->runner->events->dispatch(
                'on:error:awsUpload',
                $this->error,
                self::SERVICE_FAILED,
                $this,
                $args
            );
            return self::SERVICE_FAILED;
        }

        $this->services->runner->events->dispatch(
            'on:success:awsUpload',
            $result,
            $this,
            $args
        );

        return $result;
    }

    /**
     * Destruction
     */
    public function __destruct()
    {
        $this->stream?->close();
        $this->stream = null;
    }

    /**
     * Define setter
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        // pass
    }

    /**
     * Define getter
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($name === 'stream') {
            return null;
        }
        return $this?->$name;
    }
}
