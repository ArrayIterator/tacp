<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

use JetBrains\PhpStorm\Pure;

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
     * @param Services $services
     */
    public function __construct(Services $services)
    {
        $this->services = $services;
        if ($this->name === '') {
            $this->name = trim(strrchr(get_class($this), '\\'));
        }
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

    /**
     * @param array $arguments
     */
    abstract public function process(array $arguments);

    #[Pure] public function __toString(): string
    {
        return $this->getName();
    }
}
