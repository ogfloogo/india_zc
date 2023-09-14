<?php

namespace app\api\model;
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\Log;
/**
 * 商品分类
 */
class Goodscategory extends Model
{
    protected $name = 'goods_category';
    
    /**
     * 全部分类
     */
    public function getcategoryList(){
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:category:set:0','-inf','+inf',['withscores'=>true]);
        $left = [];
        foreach($categorylist as $k=>$v){
            $left[$k]['id'] = intval($k);
            $reward = $redis->handler()->hMget("zclc:category:".intval($k),['reward','name','price']);
            $left[$k]['name'] = $reward['name'];
        }
        array_unshift($left,['id'=>0,'name'=>"All"]);
        return $left;
    }

    public function detail($category_id){
        $redis = new Redis();
        $detail = $redis->handler()->Hgetall("zclc:category:".$category_id);
        return $detail;
    }
    
}
