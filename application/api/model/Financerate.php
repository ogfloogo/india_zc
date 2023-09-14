<?php

namespace app\api\model; 
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;

/**
 * 理财回报率
 */
class Financerate extends Model
{
    protected $name = 'finance_rate';

    //活动详情
    public function detail($id){
        $redis = new Redis();
        $redis->handler()->select(0);
        $finance_rate_list = $redis->handler()->ZRANGEBYSCORE('new:finance_rate:set:'.$id, '-inf', '+inf', ['withscores' => true]);
        $return = [];
        foreach($finance_rate_list as $key=>$value){
            $rate = $redis->handler()->Hgetall("new:finance_rate:".$key);
            $return[] = $rate;
        }
        return $return;
    }
}
