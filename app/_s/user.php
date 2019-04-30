<?php

class s_user
{
    function index()
    {
    	$list = M("fuwu.student")->query("select * from student limit ?", [10])->all();
    	return $list;
    }
}
