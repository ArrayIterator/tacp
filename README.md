# TASK WORKER

Purpose for internal company only

### PREREQUISITES

```
php 8.1.x (or later) - CLI
ext-curl (*)
ext-pdo_sqlite (*)
ext-json (*)
ext-gd (*)
ffmpeg & ffprobe (binary)
```

### INSTALL AS SOURCE

```bash
php8.1 composer.phar install
```

### BUILD PHAR (SINGLE ARCHIVE)

```bash
bash build/deploy.bash
```

phar archive placed in `build/tacp.phar`.

Use: [box.json](box.json)

### CHANGING LOOP WAIT (SLEEP) & PROCESS

Please refer on [tacp.php](tacp.php)

```php
<?php
use TelkomselAggregatorTask\Runner;

require __DIR__ . '/app/Runner.php';

$instance = Runner::instance();
// maximum process as daemon
$maxProcess = 5;
// sleep on before execute next loop
$sleepWait = 5;

$instance->start($maxProcess, $sleepWait);

```


### AVAILABLE COMMANDS

> Starting the daemon

```bash
php8.1 tacp.phar daemon:start
```

> Checking the daemon

```bash
php8.1 tacp.phar daemon:check
```

> Stopping the daemon

```bash
php8.1 tacp.phar daemon:stop
```


> Restarting the daemon

```bash
php8.1 tacp.phar daemon:restart
```

> As optional can be called as starting application 

```bash
php8.1 tacp.phar application:start
```

### SAFE APPLICATION STOP

Add file `.stop` to `CWD` _(Current Working Directory)._

This will stop application when loop process done.

When `.stop` file exist, this will prevent application to start.  

### CODING STANDARD

Coding standard followed PSR-2 Compatible:

[https://www.php-fig.org/psr/psr-2/](https://www.php-fig.org/psr/psr-2/)


### NOTE

The daemon using sqlite database.

SQLite database placed in `$HOME/.sqlite/runner.sqlite`
