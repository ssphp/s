<?php

/**
 * SSGO - Discover
 */
class Discover
{
    private $config;
    private $redis;

    public function __construct($service_config = __DIR__ . '/../service_config.php')
    {
        $this->loadConfigs($service_config);
        if (!$this->config) {
            throw new \Exception('无法加载 ' . $service_config);
        }
        //连接服务注册中心的 Redis 服务
        $this->redis = new Redis();
        $this->redis->connect($this->config['redis']['address'], $this->config['redis']['port']);

        //授权
        $this->redis->auth($this->config['redis']['password']);

        $this->redis->select($this->config['redis']['database']);
    }
    /**
     * Load configs.
     *
     * @param $configFile
     */
    protected function loadConfigs($configFile)
    {
        if (!is_file($configFile)) {
            return false;
        }

        require_once $configFile;
        $this->config = $configs;
    }
    /**
     * 注册服务
     *
     * @param $serviceIp 服务器ip
     * @param $port 服务端口
     * @param $weight
     *
     */
    public function register(string $serviceIp, int $port)
    {

        //在服务注册中心的redis中注册服务
        $this->redis->hSet($this->config['service']['appName'], $serviceIp . ':' . $port, $this->config['service']['weight']);

        $this->push($this->config['service']['appName'], $serviceIp, $this->config['service']['weight']);

        // echo "Connection to server successfully";
        // //查看服务是否运行
        // echo "Server is running: " . $redis->ping();
    }

    /**
     * 注销服务
     *
     * @param $serviceIp
     * @param $port
     */
    public function destroy(string $serviceIp, int $port)
    {
        $this->redis->hDel($this->config['service']['appName'], $serviceIp . ':' . $port);

    }

    /**
     * 队列通知其他所有服务
     *
     * @param $appName
     * @param $addr
     * @param $weight
     */
    private function push($appName, $addr, $weight)
    {
        //serverRedisPool.Do("PUBLISH", config.RegistryPrefix+"CH_"+config.App, fmt.Sprintf("%s %d", addr, config.Weight))

        $this->redis->publish($this->config['service']['registryPrefix'] . 'CH_' . $appName, $addr . ' ' . $weight);
    }

}
