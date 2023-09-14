<?php

namespace app\api\model;

use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;

/**
 * 理财活动
 */
class Recommend extends Model
{
    protected $name = 'recommend';



    public function detail($id)
    {
        $redis = new Redis();
        $redis->handler()->select(0);
        return $redis->handler()->Hgetall("zclc:financejgq:" . $id);
    }

    /**
     * 项目列表
     */
    public function getRecommendList($language){
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:financejgq:set:0','-inf','+inf',['withscores'=>true]);
        $left = [];
        foreach($categorylist as $k=>$v){
            $reward = $this->detail(intval($k));
                if($reward['status'] == 1){
                    unset($reward['status_text']);
                    $title = json_decode($reward['title_json'],true);
                    $reward['title'] = isset($title[$language])?$title[$language]:'';
                    unset($reward['title_json']);
                    $reward['image'] = format_image($reward['image']);
                    $left[] = $reward;
            }
        }
        return $left;
    }
}
