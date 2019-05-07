<?php

/**
 * SSGO - Discover
 */
class Discover
{
    private $config;
    private $redis;
    /**
     * 服务发现存储服务信息
     * {
     *     "addr":"10.1.1.1",
     *     "port":"",
     *     "weight":""
     * }
     */
    private $appNodes;

    public function __construct($service_config = __DIR__ . '/../service_config.php')
    {
        $this->loadConfigs($service_config);
        if (!$this->config) {
            throw new \Exception('无法加载 ' . $service_config);
        }
        $this->_checkRedis();
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
     * 服务发现
     *
     * @param $appName service集群名字
     */
    public function fetchApp($appName)
    {
        $this->_checkRedis();

        $appNodesResults = $this->redis->hGetall($appName);
        foreach ($this->appNodes[$appName] as $key => $val) {
            if (empty($appNodesResults[$val['addr']])) {
                //logInfo("remove node", "node", node, "nodes", appNodes[app])
                //通知所有节点，该节点下线
                $this->pushNode($appName, $val['addr'], 0);
            }
        }

        foreach ($appNodesResults as $key => $val) {
            $weight = $val;
            //logInfo("update node", "nodes", appNodes[app])
            //通知所有其他节点，该服务节点更新
            $this->pushNode($appName, $val['addr'], $weight);

        }
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
        $this->_checkRedis();

        //在服务注册中心的redis中注册服务
        $this->redis->hSet($this->config['service']['appName'], $serviceIp . ':' . $port, $this->config['service']['weight']);

        $this->pushNode($this->config['service']['appName'], $serviceIp, $this->config['service']['weight']);

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
        $this->_checkRedis();

        $this->redis->hDel($this->config['service']['appName'], $serviceIp . ':' . $port);

    }

    /**
     * 队列通知其他所有服务
     *
     * @param $appName
     * @param $addr
     * @param $weight
     */
    private function pushNode($appName, $addr, $weight)
    {
        //serverRedisPool.Do("PUBLISH", config.RegistryPrefix+"CH_"+config.App, fmt.Sprintf("%s %d", addr, config.Weight))
        $this->_checkRedis();
        $this->redis->publish($this->config['service']['registryPrefix'] . 'CH_' . $appName, $addr . ' ' . $weight);
    }

    /**
     * 检查redis连接状态是否完好，如果已经失效，则重新连接
     */
    private function _checkRedis()
    {

        //重新连接时，释放上一次的实例
        if (!empty($this->redis)) {
            //测试当前连接是否有效，有效直接返回
            if ($this->redis->ping() == 'PONG') {
                return;
            }
            unset($this->redis);
        }

        //连接服务注册中心的 Redis 服务
        $this->redis = new Redis();
        $this->redis->connect($this->config['redis']['address'], $this->config['redis']['port']);

        //授权
        if (!empty($this->config['redis']['password'])) {
            $this->redis->auth($this->config['redis']['password']);
        }

        $this->redis->select($this->config['redis']['database']);
        return;
    }

}
