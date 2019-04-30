<?php

use Swoole\Http\Server;

class Manager
{
    const MAC_OSX = 'Darwin';

    /**
     * @var \Swoole\Http\Server
     */
    protected $server;

    /**
     * @var \SwooleTW\Http\Server\Application
     */
    protected $application;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start', 'shutDown', 'workerStart', 'workerStop', 'packet', 'close',
        'bufferFull', 'bufferEmpty', 'task', 'finish', 'pipeMessage',
        'workerError', 'managerStart', 'managerStop', 'request',
    ];

    /**
     * HTTP server manager constructor.
     *
     * @param object $container
     */
    public function __construct($configs)
    {
        $this->configs = $configs;
        $this->initialize();
    }

    /**
     * Run swoole_http_server.
     */
    public function run()
    {
        $this->server->start();
    }

    /**
     * Stop swoole_http_server.
     */
    public function stop()
    {
        $this->server->shutdown();
    }

    /**
     * Initialize.
     */
    protected function initialize()
    {
        $this->setProcessName('manager process');

        $this->createSwooleHttpServer();
        $this->configureSwooleHttpServer();
        $this->setSwooleHttpServerListeners();
    }

    /**
     * Creates swoole_http_server.
     */
    protected function createSwooleHttpServer()
    {
        $host = $this->configs['server']['host'];

        $port = $this->configs['server']['port'];
        if (isset($_SERVER['swoole_port'])) {
            $port = $_SERVER['swoole_port'];
        }

        $this->server = new Server($host, $port);
    }

    /**
     * Sets swoole_http_server configurations.
     */
    protected function configureSwooleHttpServer()
    {
        $config = $this->configs['server']['options'];

        $this->server->set($config);
    }

    /**
     * Sets swoole_http_server listeners.
     */
    protected function setSwooleHttpServerListeners()
    {
        foreach ($this->events as $event) {
            $listener = 'on' . ucfirst($event);

            if (method_exists($this, $listener)) {
                $this->server->on($event, [$this, $listener]);
            } else {
                $this->server->on($event, function () use ($event) {
                    $event = sprintf('http.%s', $event);

                });
            }
        }
    }

    /**
     * "onStart" listener.
     */
    public function onStart()
    {
        $this->setProcessName('master process');
        $this->createPidFile();
    }

    /**
     * "onWorkerStart" listener.
     */
    public function onWorkerStart()
    {
        $this->clearCache();
        $this->setProcessName('worker process');
        $this->createApplication();
    }

    /**
     * "onRequest" listener.
     *
     * @param \Swoole\Http\Request  $swooleRequest
     * @param \Swoole\Http\Response $swooleResponse
     */
    public function onRequest($request, $response)
    {
        if (!empty($request->get)) {
            $_GET = $request->get;
        }

        if (!empty($request->post)) {
            $_POST = $request->post;
        }

        if (!empty($request->cookie)) {
            $_COOKIE = $request->cookie;
        }

        if (!empty($request->files)) {
            $_FILES = $request->files;
        }

        $server = $request->server;
        foreach ($server as $key => $val) {
            $_SERVER[strtoupper($key)] = $val;
        }
        $_SERVER['HTTP_HOST'] = $_SERVER['REMOTE_ADDR'];
        $app = $this->getApplication();
        $data = $app::start('MSVC');

        if (!$this->server->exist($request->fd)) {
            return;
        }

        $response->end($data);
        $response = null;
        $swooleRequest = null;
        $swooleResponse = null;
    }

    /**
     * Create application.
     */
    protected function createApplication()
    {
        date_default_timezone_set("PRC");
        include __DIR__ . '/MSVC.php';
        $app = new MSVC();
        $app::init("");
        return $this->application = $app;
    }

    /**
     * Get application.
     *
     * @return \SwooleTW\Http\Server\Application
     */
    protected function getApplication()
    {
        if (!is_object($this->application)) {
            $this->createApplication();
        }

        return $this->application;
    }

    /**
     * Set onShutdown listener.
     */
    public function onShutdown()
    {
        $this->removePidFile();
    }

    /**
     * Gets pid file path.
     *
     * @return string
     */
    protected function getPidFile()
    {
        return $this->configs['server']['options']['pid_file'];
    }

    /**
     * Create pid file.
     */
    protected function createPidFile()
    {
        $pidFile = $this->getPidFile();
        $pid = $this->server->master_pid;

        $dirname = dirname($pidFile);
        if (!is_dir($dirname)) {
           mkdir($dirname, 0755, true);
        }

        file_put_contents($pidFile, $pid);
    }

    /**
     * Remove pid file.
     */
    protected function removePidFile()
    {
        unlink($this->getPidFile());
    }

    /**
     * Clear APC or OPCache.
     */
    protected function clearCache()
    {
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * Sets process name.
     *
     * @param $process
     */
    protected function setProcessName($process)
    {
        if (PHP_OS === static::MAC_OSX) {
            return;
        }
        $serverName = 'swoole_http_server';
        $appName = 'msvc:swoole';

        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);

        swoole_set_process_name($name);
    }
}
