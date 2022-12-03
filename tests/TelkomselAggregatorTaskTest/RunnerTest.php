<?php
declare(strict_types=1);

namespace TelkomselAggregatorTaskTest;

use ReflectionObject;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Runner;
use PHPUnit\Framework\TestCase;
use Throwable;

class RunnerTest extends TestCase
{
    private Runner $runner;
    private ReflectionObject $reflection;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->runner = Runner::instance();
        $this->reflection = new ReflectionObject($this->runner);
    }

    /**
     * @covers
     */
    public function testCheckRequirements()
    {
        $this->assertTrue(
            $this->runner->checkRequirements(),
            'Check the requirements'
        );
    }

    /**
     * @covers
     */
    public function testCheckServices()
    {
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $this->assertNull(
            $this->runner->checkServices(),
            'Check service that must no return'
        );
    }

    /**
     * @covers
     */
    public function testGetProcess()
    {
        $process = $this->runner->getProcess();
        $pid = getmypid();
        $this->assertIsArray(
            $process,
            'Check process must be returning array'
        );
        $this->assertArrayHasKey(
            $pid,
            $process,
            sprintf(
                'Check the process and must be contains current pid: %d',
                $pid
            )
        );

        $keyCheck  = [
            'pid',
            'tty',
            'status',
            'time',
            'command',
            'current'
        ];
        foreach ($keyCheck as $toCheck) {
            $this->assertArrayHasKey(
                $toCheck,
                $process[$pid],
                sprintf(
                    'Check the process and must be contains key: %s',
                    $toCheck
                )
            );
        }
        $this->assertTrue(
            $process[$pid]['current'],
            'Check the current process key must be present as current'
        );
    }

    /**
     * @covers
     */
    public function testDocBlockPropertyAndTestGet()
    {
        $properties = [];
        foreach ($this->reflection->getProperties() as $property) {
            if ($property->isPublic() || $property->isStatic()) {
                continue;
            }
            $properties[] = $property->getName();
        }

        $tags = test_get_doc_comment_properties($this->reflection);
        foreach ($properties as $property) {
            $this->assertTrue(
                isset($tags[$property]),
                sprintf('DocComment must be declare property %s', $property)
            );
            if (isset($tags[$property])) {
                $tag = $tags[$property];
                $res = $this->runner->$property;
                $this->assertTrue(
                    $tag['validation']($res),
                    sprintf(
                        'DocComment %s must be return %s. Current actual result is: %s',
                        $property,
                        $tag['return'],
                        is_object($res)
                            ? sprintf('InstanceOf -> %s', get_class($res))
                            : sprintf('Data type -> %s', gettype($res))
                    )
                );
            }
        }
        $this->assertNull(
            $this->runner->nonExistenceProperty,
            'Magic method get must return null if not exists.'
        );
    }

    /**
     * @covers
     */
    public function testCreateNewConsoleOutput()
    {
        $this->assertInstanceOf(
            SymfonyStyle::class,
            $this->runner->createNewConsoleOutput(),
            sprintf('Runner::createNewConsoleOutput() must return %s', SymfonyStyle::class)
        );
    }

    /**
     * @covers
     */
    public function testKillUntilMaximumAllowed()
    {
        $this->assertNull(
            null,
            'Runner::killUntilMaximumAllowed() must not exiting test.'
        );
        $this->runner->killUntilMaximumAllowed();
    }

    /**
     * @covers
     */
    public function testShellArray()
    {
        $this->assertFalse(
            $this->runner->shellArray($this->runner->ps . ' -a &> /dev/null'),
            'Testing shellArray must return null command "ps -a &> /dev/null"'
        );
        $this->assertIsArray(
            $this->runner->shellArray($this->runner->ps . ' -a'),
            'Testing shellArray must return array command "ps -a"'
        );

        try {
            $this->runner->shellArray('# rm');
            $e = null;
        } catch (Throwable $e) {
        }
        $this->assertInstanceOf(
            RuntimeException::class,
            $e,
            'Testing exception command "# rm"'
        );
    }

    /**
     * @covers
     */
    public function testShellString()
    {
        $this->assertIsString(
            $this->runner->shellString('ps -a')
        );
        try {
            $this->runner->shellString('# rm');
            $e = null;
        } catch (Throwable $e) {
        }
        $this->assertInstanceOf(
            RuntimeException::class,
            $e
        );
    }

    /**
     * @covers
     */
    public function testInstance()
    {
        $this->assertEquals(
            $this->runner,
            Runner::instance(),
            'Checking both instance equals'
        );
        $this->assertInstanceOf(
            Runner::class,
            $this->runner,
            sprintf(
                'Checking object instance runner instance of %s',
                Runner::class
            )
        );
        $this->assertInstanceOf(
            Runner::class,
            Runner::instance(),
            sprintf(
                'Checking object static instance runner instance of %s',
                Runner::class
            )
        );
    }

    /**
     * @covers
     */
    public function testCollectCycles()
    {
        $ref = null;
        $refEnd = null;
        $calledEnd = null;
        $this->runner->events->add('on:before:clearCycles', function () use (&$ref, &$calledEnd) {
            $ref = $this->runner;
            $calledEnd = 'on:before:clearCycles';
        });
        $this->runner->events->add('on:after:clearCycles', function () use (&$refEnd, &$calledEnd) {
            $refEnd = $this->runner;
            $calledEnd = 'on:after:clearCycles';
        });
        $this->runner->collectCycles();
        $this->assertEquals(
            $this->runner,
            $ref,
            'Add event testing for "on:before:clearCycles"'
        );
        $this->assertEquals(
            $this->runner,
            $refEnd,
            'Add event testing for "on:after:clearCycles"'
        );
        $this->assertEquals(
            'on:after:clearCycles',
            $calledEnd,
            'Add event testing for "on:after:clearCycles"'
        );
    }

    /**
     * @covers
     */
    public function testPrintError()
    {
        $this->assertNull(
            null,
            'Runner::printError() skipped prevent exiting test.'
        );
    }

    /**
     * @covers
     */
    public function testReadConfigurations()
    {
        $this->assertEquals(
            $this->runner->config,
            $this->runner->readConfigurations(),
            'Reread config must be equals.'
        );
    }

    /**
     * @covers
     */
    public function testShell()
    {
        $this->assertIsArray(
            $this->runner->shell('ps -a', false)
        );
        $this->assertIsString(
            $this->runner->shell('ps -a')
        );
        try {
            $this->assertIsArray(
                $this->runner->shell('# rm')
            );
            $e = null;
        } catch (Throwable $e) {
        }
        $this->assertInstanceOf(
            RuntimeException::class,
            $e
        );
    }

    /**
     * @covers
     */
    public function testMagicSet()
    {
        $this->runner->nonExistence = false;
        $this->assertNull(
            $this->runner->nonExistence,
            'Magic method set must not affected.'
        );
    }

    /**
     * @covers
     */
    public function testKillAllProcess()
    {
        $this->assertNull(
            null,
            'Runner::killAllProcess() skipped prevent exiting test.'
        );
    }

    /**
     * @covers
     */
    public function testStart()
    {
        $this->assertNull(
            null,
            'Runner::start() skipped please use manual test.'
        );
    }
}
