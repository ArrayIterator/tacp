<?php
declare(strict_types=1);

namespace TelkomselAggregatorTaskTest\Libraries;

use TelkomselAggregatorTask\Libraries\AbstractService;
use TelkomselAggregatorTask\Libraries\Services;
use PHPUnit\Framework\TestCase;
use TelkomselAggregatorTask\Runner;

class ServicesTest extends TestCase
{
    private Services $services;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->services = new Services(Runner::instance());
    }

    /**
     * @covers
     */
    public function testGetService()
    {
        $services = [
            'AWS' => Services\Aws::class,
            'googleDrive' => Services\GoogleDrive::class,
            'googleDriveDirect' => Services\GoogleDriveDirect::class,
        ];
        foreach ($services as $key => $className) {
            $this->assertInstanceOf(
                $className,
                $this->services->getService($key),
                sprintf('Get service "%s" must instance of "%s"', $key, AbstractService::class)
            );
            $this->assertInstanceOf(
                $className,
                $this->services->getService($key),
                sprintf('Get service "%s" must instance of "%s"', $key, $className)
            );
            $this->assertInstanceOf(
                $className,
                $this->services->getService($className),
                sprintf('Get service "%s" must instance of "%s"', $className, $className)
            );
            $this->assertEquals(
                $this->services->getService($key),
                $this->services->getService($className),
                sprintf(
                    'Get services "%s" & "%s" must be equals',
                    $key,
                    $className
                )
            );
            $this->assertEquals(
                $this->services->getService($key),
                $this->services->getService(strtolower($key)),
                sprintf(
                    'Get services "%s" & "%s" must be equals',
                    $key,
                    strtolower($key)
                )
            );
            $this->assertEquals(
                $this->services->getService($className),
                $this->services->getService(strtolower($className)),
                sprintf(
                    'Get services "%s" & "%s" must be equals',
                    $className,
                    strtolower($className)
                )
            );
        }
        $this->assertNull(
            $this->services->getService('notExists'),
            'Get service "notExists" must return null',
        );
    }

    /**
     * @covers
     */
    public function testGetServiceClassName()
    {
        $services = [
            'AWS' => Services\Aws::class,
            'googleDrive' => Services\GoogleDrive::class,
            'googleDriveDirect' => Services\GoogleDriveDirect::class,
        ];
        foreach ($services as $key => $className) {
            $this->assertEquals(
                $className,
                $this->services->getServiceClassName($key),
                sprintf(
                    'Get Services::getServiceClassName(%s) must return class string %s',
                    $key,
                    $className
                )
            );
            $this->assertEquals(
                $className,
                $this->services->getServiceClassName($className),
                sprintf(
                    'Get Services::getServiceClassName(%s) must return class string %s',
                    $className,
                    $className
                )
            );
            $this->assertEquals(
                $className,
                $this->services->getServiceClassName(strtolower($key)),
                sprintf(
                    'Get Services::getServiceClassName(%s) must return class string %s',
                    strtolower($key),
                    $className
                )
            );
            $this->assertEquals(
                $className,
                $this->services->getServiceClassName(strtolower($className)),
                sprintf(
                    'Get Services::getServiceClassName(%s) must return class string %s',
                    strtolower($key),
                    $className
                )
            );
        }
    }

    /**
     * @covers
     */
    public function testGetServicesCollectionsClassBase()
    {
        $this->assertIsArray(
            $this->services->getServicesCollectionsClassBase(),
            'Services::getServicesCollectionsClassBase() must return array'
        );
        $this->assertNotEmpty(
            $this->services->getServicesCollectionsClassBase(),
            'Services::getServicesCollectionsClassBase() must return not empty array'
        );
    }

    /**
     * @covers
     */
    public function testGetServicesCollections()
    {
        $this->assertIsArray(
            $this->services->getServicesCollections(),
            'Services::getServicesCollections() must return array'
        );
        $this->assertNotEmpty(
            $this->services->getServicesCollections(),
            'Services::getServicesCollections() must return not empty array'
        );
    }

    /**
     * @covers
     */
    public function testClearActiveService()
    {
        $this->services->clearActiveService();
        $this->assertEmpty(
            $this->services->getActiveServices(),
            'Services::getActiveServices() must empty after cleared.'
        );
        // call the aws
        $this->services->getService('aws');
        $this->assertNotEmpty(
            $this->services->getActiveServices(),
            'Services::getActiveServices() must not empty after get.'
        );
        $this->services->clearActiveService();
        $this->assertEmpty(
            $this->services->getActiveServices(),
            'Services::getActiveServices() must empty after cleared.'
        );
    }
}
