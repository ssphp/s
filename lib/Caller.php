<?php
class Caller
{
    /**
     * GET方式调用其他Service
     *
     * @param $app app名字
     * @param $path 路由
     * @param $headers header头
     */
    public function get($app, string $path, array $headers)
    {
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
    public function put($app, string $path, array $data, string...$headers)
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
    public function delete($app, string $path, array $data, string...$headers)
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
    public function head($app, string $path, array $data, string...$headers)
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
    }

}
