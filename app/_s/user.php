<?php

class s_user
{
    /**
     * get user
     *
     * @param    array     $param
     *
     * @return   array
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    public function getUser($param)
    {
        $list = M("fuwu.student")->query("SELECT student_intention_id,NAME,phone FROM student LIMIT ?", [$param['num']])->all();

        if (empty($list)) {
            return [
                'code' => '0x000000',
                'data' => [],
            ];
        }

        foreach ($list as $v) {
            $this->setCacheData("student:" . $v['student_intention_id'], json_encode($v));
        }

        $this->logTest($list);
        return [
            'code' => '0x000000',
            'data' => [
                'num' => $param['num'],
                'student' => json_decode($this->getCacheData("student:" . $v['student_intention_id'])),
            ],
        ];
    }

    /**
     * redis
     *
     * @param    array      $param
     *
     * @return   array
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    public function redis($param = [])
    {
        $key = isset($param['key']) ? $param['key'] : 'key';
        $value = isset($param['value']) ? $param['value'] : 'value' . rand(1, 100);

        $this->setCacheData($key, $value);
        $value = $this->getCacheData($key);

        return [
            'code' => '0x000000',
            'data' => [
                'value' => $value,
            ],
        ];
    }

    /**
     * connect redis
     *
     * @return   object
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    private function connectRedis()
    {
        if (!empty($this->redis)) {
            return $this->redis;
        }

        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        $redis->auth(REDIS_PWD);
        $redis_prefix = 'ssw:';
        $redis->select(REDIS_DATABASE);

        return $this->redis = $redis;
    }

    /**
     * set redis key-value
     *
     * @param    string     $key
     * @param    string     $value
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    private function setCacheData($key, $value)
    {
        $redis = $this->connectRedis();
        return $redis->set($key, $value);
    }

    /**
     * get redis value
     *
     * @param    string     $key
     *
     * @return   string
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    private function getCacheData($key)
    {
        $redis = $this->connectRedis();
        return $redis->get($key);
    }

    /**
     * log test data
     *
     * @return   null
     *
     * @Author   齐少博
     *
     * @DateTime 2019-05-06
     */
    protected function logTest($list)
    {
        $pid = getmypid();
        $meo = memory_get_usage();
        $dateTime = date("Y-m-d H:i:s");
        file_put_contents("/var/www/ssw.log", $dateTime . ' ' . $pid . ' ' . $meo . ' ' . json_encode($list) . "\n", FILE_APPEND);
    }
}
