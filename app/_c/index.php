<?php

class c_index
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
        return ['code' => 200, 'message' => "this is msvc-swoole"];
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
