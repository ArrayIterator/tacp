<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpUnusedPrivateFieldInspection
 * @noinspection PhpPropertyOnlyWrittenInspection
 */
namespace TelkomselAggregatorTask;

use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TelkomselAggregatorTask\Libraries\Console;
use TelkomselAggregatorTask\Libraries\Database\Postgre;
use TelkomselAggregatorTask\Libraries\Database\Sqlite;
use TelkomselAggregatorTask\Libraries\Events\Simple;
use TelkomselAggregatorTask\Libraries\Services;
use Throwable;
use function get_current_user;
use function getcwd;
use function getmygid;
use function getmyinode;
use function getmypid;
use function getmyuid;
use function php_sapi_name;
use const PHP_VERSION;

/**
 * @property-read string $name
 * @property-read string $version
 * @property-read string $identity_pid
 * @property-read string $php_min_version
 * @property-read array $required_functions
 * @property-read array $required_extensions
 * @property-read array $required_binaries
 * @property-read string $which
 * @property-read string $php
 * @property-read int $cli_length
 * @property-read string $php_version
 * @property-read bool $cli
 * @property-read string $cwd
 * @property-read string $user
 * @property-read int $uid
 * @property-read int $gid
 * @property-read int $inode
 * @property-read int $pid
 * @property-read int $maxProcess
 * @property-read string $pid_dir
 * @property-read string $ps
 * @property-read string $kill
 * @property-read string $grep
 * @property-read string $ffmpeg
 * @property-read string $ffprobe
 * @property-read string $nohup
 * @property-read string $stop_file
 * @property-read SymfonyStyle $console
 * @property-read ArgvInput $input
 * @property-read Console $application
 * @property-read array $argv
 * @property-read string $file
 * @property-read Postgre $postgre
 * @property-read Sqlite $sqlite
 * @property-read int $wait
 * @property-read array $awsConfig
 * @property-read array $config
 * @property-read ?string $configFile
 * @property-read Services $services
 * @property-read int $collect_cycle_loop
 * @property-read Simple $events
 */
class Runner
{
    const DEFAULT_PROCESS = 5;
    const MIN_PROCESS = 1;
    const MAX_PROCESS = 100;
    const DEFAULT_WAIT = 5;
    const MIN_WAIT = 1;
    const MAX_WAIT = 60;
    const DEFAULT_COLLECT_CYCLES_LOOP = 10;
    const MIN_COLLECT_CYCLES_LOOP = 5;
    const MAX_COLLECT_CYCLES_LOOP = 100;

    const TABLE_CHECK = 'contents';
    const APP_NAME        = 'Telkomsel Aggregator Task Queue';
    const APP_VERSION     = '1.0.0';
    const PHP_MIN_VERSION = '8.1.0';
    const IDENTITY_PID    = 'telkomsel-pid';

    /**
     * @var string
     */
    private $name = self::APP_NAME;

    /**
     * @var string
     */
    private $version = self::APP_VERSION;

    /**
     * @var string
     */
    private $identity_pid = self::IDENTITY_PID;

    /**
     * @var string
     */
    private $php_min_version = self::PHP_MIN_VERSION;

    /**
     * CORE!
     */
    private $required_functions = [
        'shell_exec',
        'exec',
        'fopen',
        'fsockopen',
        'fwrite',
        'fread',
        'fclose',
        'curl_init',
        'curl_exec'
    ];

    /**
     * @var array<string>
     */
    private $required_extensions = [
        'pdo_pgsql',
        'pdo_sqlite',
        'json',
        'curl',
        'openssl',
        'filter',
        'hash',
        'spl',
        'zlib',
        'date',
        'zip'
    ];

    /**
     * @var array<string>
     */
    private $required_binaries = [
        'ps' => null,
        'ffmpeg' => null,
        'ffprobe' => null,
        'grep' => null,
        'kill' => null,
        'nohup' => null,
    ];

    /**
     * @var string
     */
    private $which = '/usr/bin/which';

    /**
     * @var string
     */
    private $php  = PHP_BINARY;

    /**
     * @var int
     */
    private $cli_length = 60;

    /**
     * @var array
     */
    private $argv;

    /**
     * @var string
     */
    private $php_version;

    /**
     * @var bool
     */
    private $cli;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var string
     */
    private $user;

    /**
     * @var int
     */
    private $uid;

    /**
     * @var int
     */
    private $gid;

    /**
     * @var int
     */
    private $inode;

    /**
     * @var string
     */
    private $pid;

    /**
     * @var string
     */
    private $pid_dir;

    /**
     * @var ?string
     */
    private $ps = null;

    /**
     * @var ?string
     */
    private $grep = null;

    /**
     * @var ?string
     */
    private $kill = null;

    /**
     * @var ?string
     */
    private $ffmpeg = null;

    /**
     * @var ?string
     */
    private $ffprobe = null;

    /**
     * @var ?string
     */
    private $nohup = null;

    /**
     * @var ?SymfonyStyle
     */
    private $console = null;

    /**
     * @var Console
     */
    private $application = null;

    /**
     * @var InputInterface
     */
    private $input = null;

    /**
     * @var string
     */
    private $file;

    /**
     * @var Postgre
     */
    private $postgre = null;
    /**
     * @var Sqlite
     */
    private $sqlite = null;

    /**
     * @var bool
     */
    private static $isStarted = false;

    /**
     * @var array
     */
    private $awsConfig = [];

    /**
     * @var array
     */
    private $config = [];
    /**
     * @var ?string
     */
    private $configFile = null;

    /**
     * @var ?Services $services
     */
    private $services = null;

    /**
     * @var Runner
     */
    private static $instance = null;

    /**
     * @var int
     */
    private $maxProcess = self::DEFAULT_PROCESS;

    /**
     * @var int
     */
    private $wait = self::DEFAULT_WAIT;

    /**
     * @var int
     */
    private $collect_cycle_loop = self::DEFAULT_COLLECT_CYCLES_LOOP;

    /**
     * @var string
     */
    private $stop_file;

    /**
     * @var Simple
     */
    private $events = null;

    /**
     * Construct Data
     */
    private function __construct()
    {
        self::$instance = $this;

        global $argv;

        $this->argv = $argv??[__FILE__];
        $this->file = (string) reset($this->argv);
        $this->php_version = phpversion();
        $this->cli = php_sapi_name() ==='cli';
        $this->cwd = getcwd();
        $this->user = get_current_user();
        $this->uid = (int) getmyuid();
        $this->gid = (int) getmygid();
        $this->inode = (int) getmyinode();
        $this->pid = (int) getmypid();
        $this->pid_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::IDENTITY_PID;
        $this->stop_file = $this->cwd . DIRECTORY_SEPARATOR . '.stop';
    }

    /**
     * @return Runner
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
            self::$instance->checkRequirements();
        }

        return self::$instance;
    }

    /**
     * @param $error
     *
     * @noinspection PhpNoReturnAttributeCanBeAddedInspection
     */
    public function printError($error)
    {
        $args = func_get_args();
        array_shift($args);

        $repeat = (int) ceil(($this->cli_length - strlen(self::APP_NAME)) / 2);
        $version = sprintf("Version: %s", self::APP_VERSION);
        $repeatVersion = (int) ceil(($this->cli_length - strlen($version)) / 2);
        printf("%s\n", str_repeat("-", $this->cli_length));
        printf('%1$s%2$s%1$s' . "\n", str_repeat(" ", $repeat), self::APP_NAME);
        printf('%1$s%2$s%1$s' . "\n", str_repeat(" ", $repeatVersion), $version);
        printf("%s\n", str_repeat("-", $this->cli_length));
        printf("\033[0;36mError: \033[0;0m\n");
        if (is_string($error)) {
            $error = explode("\n", $error);
        }
        $error = (array) ($error);
        $error = array_map(function ($e) {
            $e = rtrim($e);
            return "  $e\n";
        }, $error);
        foreach ($error as $err) {
            call_user_func_array('printf', array_merge([$err], $args));
        }
        printf("\n");
        printf("%s\n", str_repeat("-", $this->cli_length));
        exit(255);
    }

    /**
     * Check of required dependencies
     *
     * @return bool|void
     */
    public function checkRequirements()
    {
        if (!$this->cli) {
            $this->printError("\033[0;31mApplication only can run in cli mode\033[0m");
            exit(255);
        }

        if (DIRECTORY_SEPARATOR !== '/') {
            $this->printError("\033[0;31mApplication only support in unix / like\033[0m");
            exit(255);
        }
        if (isset($_SERVER['SUDO_USER']) || isset($_SERVER['SUDO_GID'])) {
            $this->printError(
                "\033[0;31mApplication can not run as sudo.\033[0m",
            );
            exit(255);
        }
        if (function_exists('posix_getuid') && posix_getuid() === 0 || $this->uid === 0) {
            $this->printError(
                "\033[0;31mApplication can not run as root user.\033[0m",
            );
            exit(255);
        }

        if (version_compare($this->php_version, self::PHP_MIN_VERSION, '<')) {
            $this->printError(
                "\033[0;31mMinimum Php Version is:\033[0m %s \033[0;31mCurrent version is:\033[0m %s",
                self::PHP_MIN_VERSION,
                PHP_VERSION
            );
            exit(255);
        }
        if (!file_exists(dirname(__DIR__) .'/vendor/autoload.php')) {
            $this->printError(
                "\033[0;31mComposer Dependencies not installed.\033[0m",
            );
            exit(255);
        }

        if ((!defined('UNIT_TEST') || UNIT_TEST !== true)
            && !preg_match('~([\\\/]|^)(test|tacp)(?:\.php|\.phar)?$~', $this->file)) {
            $this->printError(
                "\033[0;31mApplication file execution is invalid.\033[0m",
            );
            exit(255);
        }

        require dirname(__DIR__) .'/vendor/autoload.php';
        $this->input = new ArgvInput($this->argv);
        $this->console = $this->createNewConsoleOutput($this->input);
        $extensions_not_exists = [];
        foreach ($this->required_extensions as $needed_ex) {
            if (!extension_loaded($needed_ex)) {
                $extensions_not_exists[] = $needed_ex;
            }
        }
        if (!empty($extensions_not_exists)) {
            $this->printError(
                array_merge([
                    "\033[0;31mExtension need to be enabled : \033[0m\n",
                ], array_map(function ($e) {
                    return "  $e";
                }, $extensions_not_exists)),
            );
            exit(255);
        }

        $function_not_exists = [];
        foreach ($this->required_functions as $needed_fn) {
            if (!function_exists($needed_fn)) {
                $function_not_exists[] = $needed_fn;
            }
        }

        if (!empty($function_not_exists)) {
            $this->printError(
                array_merge([
                    "\033[0;31mFunctions need to be enabled : \033[0m\n",
                ], array_map(function ($e) {
                    return "  $e";
                }, $function_not_exists)),
            );
            exit(255);
        }

        if (!is_dir($this->pid_dir)) {
            if (!is_writable(dirname($this->pid_dir))) {
                $this->printError(
                    "\033[0;31mTemporary directory is not writable : \033[0m\n",
                    dirname($this->pid_dir)
                );
            }
            mkdir($this->pid_dir, 0755, true);
        }
        if (!is_dir($this->pid_dir) || !is_writable($this->pid_dir)) {
            $this->printError(
                "\033[0;31mPID directory is not writable : \033[0m\n",
                dirname($this->pid_dir)
            );
        }

        $binary_not_exists = [];
        foreach ($this->required_binaries as $binary => $status) {
            $binaryShell = null;
            if (is_executable($this->which)) {
                $binaryShell = $this->shellString(sprintf("%s $binary", $this->which));
            }
            $binaryShell = $binaryShell ?: null;
            if (!$binaryShell) {
                foreach ([
                    "/usr/local/bin/$binary",
                    "/opt/local/bin/$binary",
                    "/usr/bin/$binary",
                    "/bin/$binary",
                ] as $p_search) {
                    if (!file_exists($p_search) && is_executable($p_search)) {
                        $binaryShell = $p_search;
                        break;
                    }
                }
            }
            if ($binaryShell) {
                $this->required_binaries[$binary] = $binaryShell;
                continue;
            }
            $binary_not_exists[] = $binary;
        }

        if (!empty($binary_not_exists)) {
            $this->printError(
                array_merge([
                    "\033[0;31mRequired Binaries not found : \033[0m\n",
                ], array_map(function ($e) {
                    return "  $e";
                }, $binary_not_exists)),
            );
            exit(255);
        }
        $this->ps     = $this->required_binaries['ps'];
        $this->ffmpeg = $this->required_binaries['ffmpeg'];
        $this->ffprobe = $this->required_binaries['ffprobe'];
        $this->grep = $this->required_binaries['grep'];
        $this->kill = $this->required_binaries['kill'];
        $this->nohup = $this->required_binaries['nohup'];
        $this->events = new Simple($this);
        $this->createDefaultObjectInstance();
        return true;
    }

    public function readConfigurations()
    {
        $this->config = [];
        /**
         * Setup AWS
         */
        try {
            $files = [
                $this->cwd . '/config.yaml',
                dirname(realpath($this->file)??$this->file).'/config.yaml'
            ];
            $current = null;
            foreach ($files as $configFile) {
                if (is_file($configFile) && is_readable($configFile)) {
                    $current = $configFile;
                    break;
                }
            }
            $config = null;
            if ($current) {
                $this->configFile = $current;
                $config = Yaml::parseFile($current);
            }
            if (is_array($config)) {
                $this->config = $config;
                $aws = $this->config['aws']??[];
                if (is_array($aws)) {
                    $this->awsConfig = $aws;
                }
            }
        } catch (Throwable) {
        }
        return $this->config;
    }

    public function checkServices()
    {
        $aws = $this->services->getService('aws');
        if (!$this->console) {
            $this->console = $this->createNewConsoleOutput();
        }
        if (!$aws instanceof Services\Aws) {
            $this->console->error('AWS Service is not installed.');
            exit(255);
        }
        $check = $aws->configurationCheck();
        if ($check !== true) {
            $this->console->error(
                sprintf(
                    'AWS Service Error: %s',
                    $aws->error?->getMessage()?:'Unknown Error.'
                )
            );
            exit(255);
        }
    }

    /**
     * @param string $command command could not contain rm|del|ln|remove
     * @param bool $asString
     *
     * @return array|false|string
     * @noinspection PhpMissingParamTypeInspection
     */
    public function shell($command, $asString = true)
    {
        if (preg_match('~^(.+[\\\/]?)?(del[^\s]*|rm[^\s]*|rem[^\s]*|ln)(\s+|\s*$)~i', $command)) {
            throw new RuntimeException(
                'Can not execute command: '. $command
            );
        }

        $res = shell_exec($command);
        if (is_string($res)) {
            $res = trim($res);
        }
        if (!is_string($res)) {
            return false;
        }
        if ($asString) {
            return $res;
        }
        return array_map('trim', explode("\n", $res));
    }

    public function shellArray($command)
    {
        return $this->shell($command, false);
    }

    public function shellString($command)
    {
        return $this->shell($command);
    }

    public function createNewConsoleOutput($input = null)
    {
        $input = $input instanceof InputInterface ? $input : $this->input;
        $this->console = new SymfonyStyle($input, new ConsoleOutput());
        return $this->console;
    }

    public function __set(string $name, $value): void
    {
        // return
    }

    public function __get(string $name)
    {
        return property_exists($this, $name) ? $this->$name : null;
    }

    public function getProcess()
    {
        $ps = $this->ps;
        $user = $this->user;
        $grep = $this->grep;
        $file = basename($this->file);
        $fileEscape = preg_quote($file, "'");
        $grepEscape = preg_quote($grep, "'");
        $userEscape = preg_quote($user, "'");
        //$uid = $this->uid;
        $result = $this->shellArray(
            "$ps ax -U '$userEscape' | $grep '$fileEscape' | $grep -v $grepEscape | $grep -v grep"
        );
        if (!$result) {
            return false;
        }
        $baseFile = basename($file);
        $processes = [];
        foreach ($result as $item) {
            preg_match(
                '~^(?P<pid>[0-9]+)\s+(?P<tty>[^\s]+)\s+(?P<status>[^\s]+)
                \s+(?P<time>[^\s]+)\s+(?P<command>.+)$~x',
                $item,
                $match
            );
            if (empty($match)) {
                continue;
            }
            if (!preg_match('~'.preg_quote($baseFile, '~').'~', $match['command'])) {
                continue;
            }
            $processes[$match['pid']] = [
                'pid' => (int) $match['pid'],
                'tty' => $match['tty'],
                'status' => $match['status'],
                'time' => $match['time'],
                'command' => $match['command'],
                'current' => (int) $match['pid'] === $this->pid
            ];
        }
        uasort($processes, function ($a, $b) {
            return $a['pid'] > $b['pid'];
        });

        return $processes;
    }

    /**
     * Kill all process, include current
     *
     * @return void
     * @noinspection PhpNoReturnAttributeCanBeAddedInspection
     * @noinspection PhpUnused
     */
    public function killAllProcess()
    {
        $processes = $this->getProcess();
        if (empty($processes)) {
            exit(0);
        }

        $kill = $this->kill;
        $process = implode(' ', array_keys($processes));
        $this->shellArray(
            "$kill -9 $process"
        );
        exit(0);
    }

    /**
     * @return array
     * @noinspection PhpUnused
     */
    public function killUntilMaximumAllowed()
    {
        $processes = $this->getProcess();
        if (empty($processes)) {
            return [];
        }
        if (is_array($processes)) {
            $processes = array_filter($processes, function ($e) {
                return !$e['current'];
            });
        }
        $count = count($processes);
        if ($count < $this->maxProcess) {
            return [];
        }

        $theProcessPid = [];
        foreach ($processes as $process) {
            if (($count - count($theProcessPid)) > $this->maxProcess) {
                $theProcessPid[$process['pid']] = $process['pid'];
            }
        }

        if (empty($theProcessPid)) {
            return $theProcessPid;
        }

        $kill = $this->kill;
        $process = implode(' ', $theProcessPid);
        $this->shellArray(
            "$kill -9 $process"
        );
        return $theProcessPid;
    }

    private function createDefaultObjectInstance()
    {
        $this->readConfigurations();
        $this->console = $this->createNewConsoleOutput();
        $this->application = new Console($this);
        $this->postgre  = new Postgre($this);
        $this->sqlite   = new Sqlite($this);
        $this->services = new Services($this);
    }

    /**
     * Collect the redundant collections
     *  & clear object cached services
     *
     * @uses gc_collect_cycles()
     */
    public function collectCycles()
    {
        $this->events->dispatch('on:before:clearCycles');

        $this->postgre?->close();
        $this->sqlite?->close();
        $this->services?->clearActiveService();
        $this->config = [];
        // clear the garbage collections
        gc_collect_cycles();

        $this->createDefaultObjectInstance();

        $this->events->dispatch('on:after:clearCycles');
    }

    /**
     * @param int|numeric-string $maxProcess
     * @param int|numeric-string $wait
     * @param int|numeric-string $collect_cycle_loop
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function start(
        $maxProcess = self::DEFAULT_PROCESS,
        $wait = self::DEFAULT_WAIT,
        $collect_cycle_loop = self::DEFAULT_COLLECT_CYCLES_LOOP
    ) {
        if (self::$isStarted) {
            return;
        }
        $this->events->dispatch('on:before:applicationStart');
        $maxProcess           = is_numeric($maxProcess) ? (int) $maxProcess : self::DEFAULT_PROCESS;
        $maxProcess           = $maxProcess > self::MAX_PROCESS
            ? self::MAX_PROCESS
            : ($maxProcess < self::MIN_PROCESS ? self::MIN_PROCESS : $maxProcess);
        $collect_cycle_loop   = is_numeric($collect_cycle_loop)
            ? (int) $collect_cycle_loop
            : self::DEFAULT_COLLECT_CYCLES_LOOP;
        $collect_cycle_loop   = $collect_cycle_loop > self::MAX_COLLECT_CYCLES_LOOP
            ? self::MAX_COLLECT_CYCLES_LOOP : (
                $collect_cycle_loop < self::MIN_COLLECT_CYCLES_LOOP
                    ? self::MIN_COLLECT_CYCLES_LOOP
                    : $collect_cycle_loop
            );

        $wait = is_numeric($wait) ? (int) $wait : self::DEFAULT_WAIT;
        $wait = $wait < self::MIN_WAIT ? self::MIN_WAIT : (
            $wait > self::MAX_WAIT ? self::MAX_WAIT : $wait
        );

        $this->maxProcess = $maxProcess;
        $this->wait       = $wait;
        $this->collect_cycle_loop = $collect_cycle_loop;

        $this->application->start();
        $this->events->dispatch('on:after:applicationStart');
    }
}
