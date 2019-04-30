<?php

class s_user
{
    function index()
    {
        $num = isset($_POST['num']) ? $_POST['num'] : 5;
        $list = M("fuwu.student")->query("SELECT student_intention_id,NAME,phone FROM student LIMIT ?", [$num])->all();

        if (empty($list)) {
                return [
                        'code' => '0x000000',
                        'data' => []
                ];
        }

        foreach ($list as $v) {
                $this->setCacheData("student:".$v['student_intention_id'], json_encode($v));
        }

        return [
                'code' => '0x000000',
                'data' => [
                    'num' => $num,
                    'student' => json_decode($this->getCacheData("student:".$v['student_intention_id']))
                ]
        ];
    }


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

    private function setCacheData($key, $value)
    {
    	$redis = $this->connectRedis();
    	return $redis->set($key, $value);
    }

    private function getCacheData($key)
    {
    	$redis = $this->connectRedis();
    	return $redis->get($key);
    }
}
