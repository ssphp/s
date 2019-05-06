<?php

include_once __DIR__ . "/Manager.php";

use Swoole\Process;

class HttpServer
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ssw:{action : start|stop|restart|reload|infos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Swoole HTTP Server controller.';

    /**
     * The console command action. start|stop|restart|reload.
     *
     * @var string
     */
    protected $action;

    /**
     * The pid.
     *
     * @var int
     */
    protected $pid;

    /**
     * The configs for this package.
     *
     * @var array
     */
    protected $configs;

    /**
     * Execute the console command.
     */
    public function __construct($argv, $configFile)
    {
        $this->loadConfigs($configFile);
        $this->checkAction($argv[0]);
        $this->runAction();
    }

    protected function registerManager()
    {
        $this->manager = new Manager($this->configs);
    }

    /**
     * Load configs.
     */
    protected function loadConfigs($configFile)
    {
        if (!is_file($configFile)) {
            return false;
        }

        require_once $configFile;
        $this->configs = $configs;
    }

    /**
     * Run action.
     */
    protected function runAction()
    {
        $this->{$this->action}();
    }

    /**
     * Run swoole_http_server.
     */
    protected function start()
    {
        if ($this->isRunning($this->getPid())) {
            $this->error('Failed! swoole_http_server process is already running.');
            exit(1);
        }

        $host = $this->configs['server']['host'];
        $port = $this->configs['server']['port'];

        $this->info('Starting swoole http server...');
        $this->info("Swoole http server started: <http://{$host}:{$port}>");
        if ($this->isDaemon()) {
            $this->info('> (You can run this command to ensure the swoole_http_server process is running: ps aux|grep "swoole")');
        }

        

        $this->registerManager();
        $this->manager->run();
    }

    
    /**
     * Stop swoole_http_server.
     */
    protected function stop()
    {
        $pid = $this->getPid();
        $this->isRunning($pid);
        if (!$this->isRunning($pid)) {
            $this->error('Failed! There is no swoole_http_server process running.');
            exit(1);
        }

        $this->info('Stopping swoole http server...');

        $isRunning = $this->killProcess($pid, SIGTERM, 15);

        if ($isRunning) {
            $this->error('Unable to stop the swoole_http_server process.');
            exit(1);
        }

        $this->removePidFile();
        $this->info('> success');
    }

    /**
     * Restart swoole http server.
     */
    protected function restart()
    {
        $pid = $this->getPid();

        if ($this->isRunning($pid)) {
            $this->stop();
        }

        $this->start();
    }

    /**
     * Reload.
     */
    protected function reload()
    {
        $pid = $this->getPid();

        if (!$this->isRunning($pid)) {
            $this->error('Failed! There is no swoole_http_server process running');
            exit(1);
        }

        $this->info('Reloading swoole_http_server...');

        $isRunning = $this->killProcess($pid, SIGUSR1);

        if (!$isRunning) {
            $this->error('> failure');
            exit(1);
        }

        $this->info('> success');
    }

    /**
     * Display PHP and Swoole miscs infos.
     */
    protected function infos()
    {
        $pid = $this->getPid();
        $isRunning = $this->isRunning($pid);

        $data = [
            'PHP Version' => phpversion(),
            'Swoole Version' => swoole_version(),
            'Server Status' => $isRunning ? 'Online' : 'Offline',
            'PID' => $isRunning ? $pid : 'None',
        ];

        foreach ($data as $k => $v) {
            $this->info($k . " : " . $v);
        }
    }

    /**
     * Initialize command action.
     */
    protected function checkAction($action)
    {
        $action = trim(str_replace("ssw:", "", $action));
        if (!in_array($action, ['start', 'stop', 'restart', 'reload', 'infos'])) {
            exit(1);
        }

        $this->action = $action;
    }

    /**
     * If Swoole process is running.
     *
     * @param int $pid
     *
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (!$pid) {
            return false;
        }
        try {
            return Process::kill($pid, 0);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Kill process.
     *
     * @param int $pid
     * @param int $sig
     * @param int $wait
     *
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (!$this->isRunning($pid)) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning($pid);
    }

    /**
     * Get pid.
     *
     * @return int|null
     */
    protected function getPid()
    {
        if ($this->pid) {
            return $this->pid;
        }

        $pid = null;
        $path = $this->getPidPath();
        // var_dump($path);
        if (file_exists($path)) {
            $pid = (int) file_get_contents($path);

            if (!$pid) {
                $this->removePidFile();
            } else {
                $this->pid = $pid;
            }
        }

        return $this->pid;
    }

    /**
     * Get Pid file path.
     *
     * @return string
     */
    protected function getPidPath()
    {
        return $this->configs['server']['options']['pid_file'];
    }

    /**
     * Remove Pid file.
     */
    protected function removePidFile()
    {
        if (file_exists($this->getPidPath())) {
            unlink($this->getPidPath());
        }
    }

    /**
     * Return daemonize config.
     */
    protected function isDaemon()
    {
        return $this->configs['server']['options']['daemonize'];
    }

    /**
     * echo message
     */
    protected function info($message)
    {
        echo $message . PHP_EOL;
    }

    /**
     * echo message
     */
    protected function error($message)
    {
        echo $message . PHP_EOL;
    }
}
