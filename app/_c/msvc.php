<?php

class c_msvc
{
    /**
     * index
     *
     * @return   string
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    public function index()
    {
        /**
         * GET方式调用其他Service
         *
         * @param $app app名字
         * @param $path 路由
         * @param $headers header头
         */
        // $res = MSVC::$caller->get('s1', '/Andy/fullName/fullName/fullName', []);

        // MSVC::log(['info' => '调用节点[s1]返回信息', 'data' => $res->data]);

        // MSVC::log(['info' => '调用节点[s1]返回信息', 'data' => $res->data]);

        MSVC::log(['info' => 'this is ssphp msvc->index']);

        return ['code' => 200, "FullName" => "this is ssphp"];
    }

    /**
     * test db
     *
     * @return   array
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    public function user()
    {
        $num = isset($_POST['num']) ? $_POST['num'] : 5;
        return S('user.getUser', [
            'num' => $num,
        ]);
    }

    /**
     * test redis
     *
     * @return   array
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    public function redis()
    {
        return S('user.redis', [
            'key' => 'key:ssw',
            'value' => 'num001:ssw',
        ]);
    }
}
