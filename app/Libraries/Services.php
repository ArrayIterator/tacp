<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

use DirectoryIterator;
use TelkomselAggregatorTask\Runner;

class Services
{
    /**
     * @var Runner
     */
    public readonly Runner $runner;

    /**
     * @var ?array
     */
    private static ?array $serviceList = null;

    /**
     * @var ?array
     */
    private static ?array $serviceListLower = null;

    /**
     * @var array<string, AbstractService>
     */
    private array $activeServices = [];

    /**
     * @param Runner $runner
     */
    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @return array
     */
    public function getServicesCollectionsClassBase(): array
    {
        if (!is_array(self::$serviceListLower)) {
            $this->getServicesCollections();
        }
        return self::$serviceListLower;
    }

    /**
     * @return array
     */
    public function getServicesCollections(): array
    {
        if (is_array(self::$serviceList)) {
            return self::$serviceList;
        }

        self::$serviceList = [];
        self::$serviceListLower = [];
        $namespace = __NAMESPACE__ .'\\Services';
        foreach (new DirectoryIterator(__DIR__.'/Services') as $directory) {
            if (!$directory->isFile() || $directory->getExtension() !== 'php') {
                continue;
            }
            $baseName = substr($directory->getBasename(), 0, -4);
            $className = "$namespace\\$baseName";
            if (!class_exists($className) || !is_subclass_of($className, ServiceInterface::class, true)) {
                continue;
            }
            self::$serviceList[strtolower($baseName)] = $className;
            self::$serviceListLower[strtolower($className)] = $className;
        }

        return self::$serviceList;
    }

    /**
     * @param string $name
     *
     * @return ?class-string<ServiceInterface>
     */
    public function getServiceClassName(string $name) : ?string
    {
        $lower = strtolower(trim($name));
        return $this->getServicesCollections()[$lower]??
             $this->getServicesCollectionsClassBase()[$lower]??null;
    }

    /**
     * Clear cached active services
     */
    public function clearActiveService()
    {
        $this->activeServices = [];
        self::$serviceList = null;
        self::$serviceListLower = null;
    }

    /**
     * @return array<string, AbstractService>
     */
    public function getActiveServices() : array
    {
        return $this->activeServices;
    }

    /**
     * @template T<AbstractService>
     * @param class-string<T>|string $name
     * @param bool $reuse
     *
     * @return ?T
     */
    public function getService(string $name, bool $reuse = true) : ?AbstractService
    {
        $serviceClass = $this->getServiceClassName($name);
        if (!$serviceClass) {
            return null;
        }
        if (!isset($this->activeServices[$serviceClass]) || !$reuse) {
            $this->activeServices[$serviceClass] = new $serviceClass($this);
        }
        return $this->activeServices[$serviceClass];
    }
}
