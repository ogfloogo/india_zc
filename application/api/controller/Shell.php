<?php

namespace app\api\controller;

use app\api\model\Banner;
use app\api\model\Order;
use app\api\model\Report;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Log;

/**
 * 
 */
class Shell extends Controller
{
    /**
     * 每日报表
     * 
     */
    public function addreport(){
        $report = (new Report())->where('date',date('Y-m-d',time()))->find();
        if(empty($report)){
            (new Report())->insert([
                'date' => date('Y-m-d',time()),
                'createtime' => time(),
                'updatetime' => time(),
            ]);
        }
    }
}
