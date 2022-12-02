<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

/**
 * @property-read Services $services
 */
interface ServiceInterface
{
    public function __construct(Services $services);
    public function getName() : string;
    public function process(array $arguments);
    public function __toString(): string;
}
