<?php
class Caller
{
    /**
     * 存放所有与其他节点的http2长连
     * [
     *     'appName' => Coroutine\Http2\Client
     *     ...
     * ]
     */

    private $swooleHttp2Clients;



    //服务注册发现中心
    private $discover;

    public function __construct(&$discover)
    {
        // var_dump('this is Caller->__construct');
        $this->discover = &$discover;
    }
    /**
     * GET方式调用其他Service
     *
     * @param $app app名字
     * @param $path 路由
     * @param $headers header头
     */
    public function get($app, string $path, array $headers)
    {
        // var_dump('this is Caller->get');
        return $this->_do('GET', $app, $path, [], $headers);
    }

    /**
     * POST方式调用其他Service
     *
     * @param $app app名字
     * @param $path 路由
     * @param $data 发送数据
     * @param $headers header头
     */
    public function post($app, string $path, array $data, array $headers)
    {
        return $this->_do('POST', $app, $path, $data, $headers);
    }

    /**
     * PUT方式调用其他Service
     *
     * @param $app app名字
     * @param $path 路由
     * @param $data 发送数据
     * @param $headers header头
     */
    public function put($app, string $path, array $data, string ...$headers)
    {
        return $this->_do('PUT', $app, $path, $data, $headers);
    }

    /**
     * delete方式调用其他Service
     *
     * @param $app app名字
     * @param $path 路由
     * @param $data 发送数据
     * @param $headers header头
     */
    public function delete($app, string $path, array $data, string ...$headers)
    {
        return $this->_do('DELETE', $app, $path, $data, $headers);
    }

    /**
     * header方式调用其他Service
     *
     * @param $app app名字
     * @param $path 路由
     * @param $data 发送数据
     * @param $headers header头
     */
    public function head($app, string $path, array $data, string ...$headers)
    {
        return $this->_do('HEAD', $app, $path, $data, $headers);
    }

    /**
     * 负载均衡调度Service
     *
     * @param $app app名字
     * @param $path 路由
     * @param $data 发送数据
     * @param $headers header头
     */
    private function _do($method, $app, string $path, array $data, $headers)
    {
        $cli = $this->_getHttp2Client($app);
        // echo 'this is Caller->_do $cli:';
        // var_dump($cli);
        // return;
        $req = new swoole_http2_request;
        $req->method = $method;
        $req->path = $path;
        $req->headers = $headers;
        $req->data = $data;
        $cli->send($req);
        $response = $cli->recv();

        return $response;
    }

    /**
     * 获取http2长连
     */
    private function _getHttp2Client($app)
    {
        // if (isset($this->swooleHttp2Clients[$app])) {
        //更新该节点信息
        $appNodes = $this->discover->fetchApp($app);
        if (isset($appNodes[$app])) {
            $appNode = array_values($appNodes[$app]);
            // echo '节点信息:';
            // var_dump($appNode);
            //多个节点情况下随机选一个
            $i = rand(0, count($appNode) - 1);

            // $node_info = json_decode($appNode[$i], true);
            $node_info = $appNode[$i];

            // echo '节点信息:';
            // var_dump($node_info);
            // exit;

            if (isset($this->swooleHttp2Clients[$app][$i]) && $this->swooleHttp2Clients[$app][$i]->connected) {
                var_dump($this->swooleHttp2Clients[$app][$i]);
                var_dump('链接已存在，并且未失效，直接返回');
                //http2连接已存在直接返回
                return $this->swooleHttp2Clients[$app][$i];
            }
            // return;
            $this->swooleHttp2Clients[$app][$i] = $this->_newHttp2Clients($node_info);
            // echo 'this is http2Client:';
            // var_dump($this->swooleHttp2Clients[$app][$i]);

            return $this->swooleHttp2Clients[$app][$i];
        } else {
            // echo 'this is Caller ERROR';
            // var_dump($appNodes[$app]);
            // var_dump($app);
            // var_dump($appNodes);
            throw new Exception($app . '节点信息不存在');
        }

        //连接已存在
        // return $this->swooleHttp2Clients[$app][$i];
        // } else {
        //     //创建连接并保存
        //     return $this->swooleHttp2Clients[$app] = $this->_newHttp2Clients($app);
        // }
    }

    /**
     * 创建http2长连
     */
    private function _newHttp2Clients($node_info)
    {

        $domain = $node_info['addr'];
        $port = $node_info['port'];
        $cli = new Swoole\Coroutine\Http2\Client($domain, $port);
        $cli->set([
            'timeout' => -1,
        ]);
        $cli->connect();
        return $cli;
    }
}
