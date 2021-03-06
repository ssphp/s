<?php

/**
 * SSGO - Discover
 * 采用swoole-process进程启动
 */
class Discover
{
    private $config;
    private $redis;
    /**
     * 服务发现存储服务信息
     *
     * "appName"=>[
     *     [
     *          "addr"=>"10.1.1.1",
     *          "port"=>"",
     *          "weight"=>""
     *     ],
     * ]
     */
    private $appNodes;

    /**
     * 订阅节点信息改变的协程
     */
    private $goEntity;

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
     * 服务集群节点维护
     *
     * @param $appName service集群名字
     */
    public function fetchApp($appName)
    {
        $this->_checkRedis();

        $appNodesResults = $this->redis->hGetall($appName);
        if (empty($appNodesResults)) {
            throw new Exception($appName . '节点信息为空');
        }
        if (!empty($this->appNodes[$appName])) {
            foreach ($this->appNodes[$appName] as $key => $val) {
                if (empty($appNodesResults[$val['addr']])) {
                    //本地节点信息更新
                    $this->pushNode($appName, $val['addr'], $val['port'], 0);
                }
            }
        }


        foreach ($appNodesResults as $key => $val) {
            $weight = $val;


            $temp = explode(':', $key);
            $addr = $temp[0];
            $port = $temp[1];

            //本地节点信息更新
            $this->pushNode($appName, $addr, $port, $weight);
        }

        return $this->appNodes;
    }

    /**
     * 服务变更队列通知监听(由于会阻塞，所以建议启用协程来处理)
     */
    public function sync()
    {
        $appNodes = &$this->appNodes;
        $redis = &$this->redis;

        while (true) {

            $appNodes_sub = [];
            //针对每一个应用集群节点变更队列，开启一个监听
            foreach ($appNodes as $key => $val) {
                $appNodes_sub[] = $key;
            }
            //阻塞订阅，等待订阅消息推送
            $redis->subscribe($appNodes_sub, [$this, 'fetchApp']);
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
        $this->redis->hSet(
            $this->config['service']['appName'],
            $serviceIp . ':' . $port,
            $this->config['service']['weight']
        );

        //本地节点信息维护
        $this->pushNode(
            $this->config['service']['appName'],
            $serviceIp,
            $port,
            $this->config['service']['weight']
        );

        //通知其他节点，本节点上线
        // echo "\n 通知其他节点，本节点上线。appname:" . $this->config['service']['appName']
        //     . ",serviceIp:$serviceIp,port:$port,weight:"
        //     . $this->config['service']['weight'] . " \n";
        $this->pushAddNode(
            $this->config['service']['appName'],
            $serviceIp,
            $port,
            $this->config['service']['weight']
        );
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

        //通知其他节点，本节点下线??
        $this->pushAddNode($this->config['service']['appName'], $serviceIp, $port, 0);
    }

    /**
     * 本地节点信息维护
     *
     * @param $appName
     * @param $addr
     * @param $weight
     */
    private function pushNode($appName, $addr, $port, $weight)
    {
        if ($weight == 0) {
            //删除节点
            if (isset($this->appNodes[$appName][$addr])) {
                unset($this->appNodes[$appName][$addr]);
            }
        } else if (!isset($this->appNodes[$appName][$addr])) {
            //新节点处理
            $this->appNodes[$appName][$addr] = ['addr' => $addr, 'port' => $port, 'weight' => $weight];
        } else if ($this->appNodes[$appName][$addr] != $weight) {
            //修改权重
            $this->appNodes[$appName][$addr]['weight'] = $weight;
        }
    }

    /**
     * 通知其他节点更新节点信息
     */
    private function pushAddNode($appName, $addr, $port, $weight)
    {
        $this->_checkRedis();

        // echo "\n 推送节点信息 " . $this->config['service']['registryPrefix'] . 'CH_' . $appName
        //     . ',' . $addr . ':' . $port . ' ' . $weight . "\n";
        $this->redis->publish(
            $this->config['service']['registryPrefix'] . 'CH_' . $appName,
            $addr . ':' . $port . ' ' . $weight
        );
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
        $this->redis->pconnect($this->config['redis']['address'], $this->config['redis']['port']);
        $this->redis->setOption(Redis::OPT_READ_TIMEOUT, -1);

        //授权
        if (!empty($this->config['redis']['password'])) {
            $this->redis->auth($this->config['redis']['password']);
        }

        $this->redis->select($this->config['redis']['database']);
    }
}
