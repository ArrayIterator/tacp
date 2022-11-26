<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask;

use PDO;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * @property-read string $name
 * @property-read string $version
 * @property-read string $identity_pid
 * @property-read string $php_min_version
 *
 * @property-read array $required_functions
 * @property-read array $required_extensions
 * @property-read array $required_binaries
 * @property-read string $which
 * @property-read string $php
 * @property-read int $cli_length
 * @property-read int $php_version
 * @property-read bool $cli
 * @property-read string $cwd
 * @property-read string $user
 * @property-read int $uid
 * @property-read int $gid
 * @property-read int $pid
 * @property-read int $maxProcess
 * @property-read string $pid_dir
 * @property-read string $ps
 * @property-read string $kill
 * @property-read string $grep
 * @property-read string $ffmpeg
 * @property-read string $ffprobe
 * @property-read string $nohup
 * @property-read SymfonyStyle $console
 * @property-read ArgvInput $input
 * @property-read Console $application
 * @property-read array $argv
 * @property-read string $file
 * @property-read Database $connection
 * @property-read Sqlite $sqlite
 * @property-read int $wait
 * @property-read array $aws
 * @property-read array $config
 * @property-read ?string $configFile
 */
class Runner
{
    const APP_NAME        = 'Telkomsel Aggregator Task Queue';
    const APP_VERSION     = '1.0.0';
    const PHP_MIN_VERSION = '8.1.0';
    const IDENTITY_PID    = 'telkomsel-pid';
    private $name = self::APP_NAME;
    private $version = self::APP_VERSION;
    private $identity_pid = self::IDENTITY_PID;
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
    private $required_binaries = [
        'ps' => null,
        'ffmpeg' => null,
        'ffprobe' => null,
        'grep' => null,
        'kill' => null,
        'nohup' => null,
    ];

    private $which = '/usr/bin/which';
    private $php  = PHP_BINARY;
    private $cli_length = 60;
    private $argv;

    private $php_version;
    private $cli;
    private $cwd;
    private $user;
    private $uid;
    private $gid;
    private $inode;
    private $pid;
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
    private $ffmpeg = null;
    private $ffprobe = null;
    private $nohup = null;
    private $console = null;
    private $application = null;
    private $maxProcess = 5;
    private $input = null;
    private $file;
    /**
     * @var Database
     */
    private $connection = null;
    /**
     * @var Sqlite
     */
    private $sqlite = null;

    private static $isStarted = false;
    private $aws = [];
    private $config = [];
    private $configFile = null;

    /**
     * @var Runner
     */
    private static $instance = null;
    private int $wait = 5;
    private function __construct()
    {
        self::$instance = $this;
        global $argv;
        $this->argv = $argv;
        $this->file = reset($this->argv);
        $this->php_version = phpversion();
        $this->cli = \php_sapi_name() ==='cli';
        $this->cwd = \getcwd();
        $this->user = \get_current_user();
        $this->uid = (int) \getmyuid();
        $this->gid = (int) \getmygid();
        $this->inode = (int) \getmyinode();
        $this->pid = (int) \getmypid();
        $this->pid_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::IDENTITY_PID;
    }

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
            self::$instance->checkRequirements();
        }
        return self::$instance;
    }

    public function printError(string|array $error, ...$arg)
    {
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
            printf($err, ...$arg);
        }
        printf("\n");
        printf("%s\n", str_repeat("-", $this->cli_length));
        exit(255);
    }

    private function checkRequirements()
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
                \PHP_VERSION
            );
            exit(255);
        }
        if (!file_exists(dirname(__DIR__) .'/vendor/autoload.php')) {
            $this->printError(
                "\033[0;31mComposer Dependencies not installed.\033[0m",
            );
            exit(255);
        }

        if (!preg_match('~([\\\/]|^)tacp(?:\.php|\.phar)?$~', $this->file)) {
            $this->printError(
                "\033[0;31mApplication file execution is invalid.\033[0m",
            );
            exit(255);
        }

        require dirname(__DIR__) .'/vendor/autoload.php';
        $this->input = new ArgvInput($this->argv);
        $this->doResetConsole();
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
        $this->application = new Console($this);
        $this->connection = new Database($this);
        $this->sqlite = new Sqlite($this);

        $this->config = [];
        /**
         * Setup AWS
         */
        try {
            $files = [
                $this->cwd.'/config.yaml',
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
            }
        } catch (Throwable) {
        }
        return true;
    }

    /**
     * @param $command
     * @param bool $asString
     *
     * @return array|false|string|
     */
    public function shell($command, $asString = true)
    {
        if (preg_match('~^(.+[a-z][\\\/])?rm\s+~i', $command)) {
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
        return $this->shell($command, true);
    }
    public function doResetConsole()
    {
        $this->console = new SymfonyStyle($this->input, new ConsoleOutput());
        return $this;
    }

    public function __set(string $name, $value): void
    {
        // return
    }

    public function __get(string $name)
    {
        return $this->$name;
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
        $uid = $this->uid;
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
                '~^(?P<pid>[0-9]+)\s+(?P<tty>[^\s]+)\s+(?P<status>[^\s]+)\s+(?P<time>[^\s]+)\s+(?P<command>.+)$~',
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

    public function killAll()
    {
        $processes = $this->getProcess();
        if (empty($processes)) {
            return [];
        }
        $kill = $this->kill;
        $process = implode(' ', array_keys($processes));
        return $this->console->runner->shellArray(
            "$kill -9 $process"
        );
    }

    /**
     * @return array
     */
    public function killUntillMax()
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
        $this->console->runner->shellArray(
            "$kill -9 $process"
        );
        return $theProcessPid;
    }

    public function start(int $maxProcess = 5, int $wait = 5)
    {
        if (self::$isStarted) {
            return;
        }

        $this->maxProcess = $maxProcess;
        $this->wait = $wait;
        $this->application->start();
    }
}
