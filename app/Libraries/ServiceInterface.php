<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

/**
 * @property-read Services $services
 */
interface ServiceInterface
{
    /**
     * @param Services $services
     * @param array|null $config
     */
    public function __construct(Services $services, ?array $config = null);

    /**
     * @return string
     */
    public function getName() : string;

    /**
     * @param array $arguments
     */
    public function process(array $arguments);

    /**
     * @return string
     */
    public function __toString(): string;
}
