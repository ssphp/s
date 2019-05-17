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
     * @var \MSVC
     */
    protected $application;

    /**
     * 与其他service的http2长连
     */
    public $caller;

    /**
     * 服务发现&注册中心
     */
    public $discover;

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
        /**
         * 服务注册&发现启动
         */
        if ($this->configs['service_enable']) {
            // var_dump('服务注册&发现启动');
            $host = $this->configs['server']['host'];
            $port = $this->configs['server']['port'];
            //服务注册
            $this->_serviceStart($host, $port);
            include __DIR__ . '/Caller.php';
            //加载Caller
            $this->caller = new Caller($this->discover);
        }
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
        // echo "\n this is initialize \n ";
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
                // echo "\n $event:$listener\n";
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

        $serverKeys = [];
        if (!empty($server = $request->server)) {
            foreach ($server as $key => $val) {
                $key = strtoupper($key);
                $serverKeys[] = $key;
                $_SERVER[$key] = $val;
            }
        }

        $_SERVER['HTTP_HOST'] = $_SERVER['REMOTE_ADDR'];

        $app = $this->getApplication();


        //将swoole的response传递到框架中
        $app::$swoole_response = &$response;

        $data = $app::start('MSVC');
        // $app::log(['request ' => (array)$request, ' data ' => $data]);



        if (!$this->server->exist($request->fd)) {
            var_dump('$request->fd is null');
            var_dump($request->fd);

            return;
        }

        unset($_GET, $_POST, $_COOKIE, $_FILES);

        if (!empty($serverKeys)) {
            foreach ($serverKeys as $key) {
                unset($_SERVER[$key]);
            }
        }

        if (!empty(json_decode($data, true))) {
            var_dump('Content-Type: ' . 'application/json;charset=UTF-8');
            $response->header('Content-Type',  'application/json;charset=UTF-8');
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
        include __DIR__ . '/MSVC.php';
        $app = new MSVC();
        $app::init("");
        $app::$is_swoole = true;
        $app::$caller = $this->caller;
        return $this->application = $app;
    }

    /**
     * Get application.
     *
     * @return \MSVC
     */
    protected function getApplication()
    {
        // if (!is_object($this->application)) {
        //     $this->createApplication();
        // }

        return $this->application;
    }

    /**
     * Set onShutdown listener.
     */
    public function onShutdown()
    {
        $this->removePidFile();
        //微服务注册信息释放
        $this->_destroy();
    }

    /**
     * 微服务注册信息注销
     */
    private function _destroy()
    {
        $host  = $this->configs['server']['host'];
        $port = $this->configs['server']['port'];
        $this->discover->destroy($host, $port);
    }

    /**
     * 服务注册&发现
     *
     * @param $ip 服务监听ip
     */
    private function _serviceStart(string $ip, int $port)
    {
        include   __DIR__ .   '/Discover.php';
        $this->discover = new Discover();
        $this->discover->register($ip, $port);
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
        $serverName =  'swoole_http_server';
        $appName =  'msvc:swoole';

        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);

        swoole_set_process_name($name);
    }
}
