<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

use JetBrains\PhpStorm\Pure;
use Throwable;

abstract class AbstractService implements ServiceInterface
{
    const SERVICE_INVALID_ARGUMENT = 1;
    const SERVICE_INVALID_CONFIG = 2;
    const SERVICE_FAILED = 3;
    const SERVICE_UNAUTHENTICATED = 4;
    const SERVICE_SUCCESS = true;

    /**
     * @var Services
     */
    public readonly Services $services;

    /**
     * @var string The service name
     */
    protected string $name = '';

    /**
     * @var array
     */
    public readonly array $config;

    /**
     * @var ?Throwable
     */
    protected ?Throwable $error = null;

    /**
     * @param Services $services
     * @param array|null $config
     */
    public function __construct(Services $services, ?array $config = null)
    {
        $this->services = $services;
        if ($this->name === '') {
            $this->name = trim(strrchr(get_class($this), '\\'));
        }
        $this->config = $config??[];
    }

    public function getConfig() : array
    {
        return $this->config;
    }

    /**
     * @return bool|int returning true if succeed
     */
    public function configurationCheck() : bool|int
    {
        return true;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getError() : ?Throwable
    {
        return $this->error;
    }

    /**
     * @param array $arguments
     */
    abstract public function process(array $arguments);

    /**
     * @return string
     */
    #[Pure] public function __toString(): string
    {
        return $this->getName();
    }
}
