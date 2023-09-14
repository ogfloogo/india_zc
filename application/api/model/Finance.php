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
class Finance extends Model
{
    protected $name = 'finance';

    //理财列表
    public function homelist()
    {
        $list = (new Financeissue())
            ->where('deletetime', null)
            ->order('weigh desc')
            ->order('id desc')
            ->where('status',0)
            ->field('id,name,finance_id,presell_end_time,day,status,end_time')
            ->limit(2)
            ->select();
        foreach($list as $key=>$value){
            (new Financeissue())->updatestatus($value['end_time'],$value['presell_end_time'],$value['status'],$value['id'],$value['finance_id']);
            $finance_info = $this->detail($value['finance_id']);
            $list[$key]['finance_name'] = $finance_info['name'];
            $list[$key]['price'] = $finance_info['price'];
            //当前收益率
            $list[$key]['rate'] = ((new Financeorder())->getrate($value['finance_id'],$value['id']))['rate'];
            //购买人数
            $list[$key]['buyers'] = (new Financeorder())->getbuyers($value['id']);
        }
        return $list;
    }

    //理财列表
    public function getlist($page)
    {
        $pageCount = 10;
        $startNum = ($page - 1) * $pageCount;
        $list = (new Financeissue())
            ->where('deletetime', null)
            // ->order('weigh desc')
            ->order('id desc')
            ->field('id,name,finance_id,presell_end_time,day,status,end_time')
            ->limit($startNum, $pageCount)
            ->select();
        foreach($list as $key=>$value){
            (new Financeissue())->updatestatus($value['end_time'],$value['presell_end_time'],$value['status'],$value['id'],$value['finance_id']);
            $finance_info = $this->detail($value['finance_id']);
            $list[$key]['finance_name'] = $finance_info['name'];
            $list[$key]['price'] = $finance_info['price'];
            //当前收益率
            $now_rate = (new Financeorder())->getrate($value['finance_id'],$value['id']);
            $list[$key]['rate'] = $now_rate['rate'];
            //购买人数-收益率
            $rate = (new Financerate())->detail($value['finance_id']);
            $list[$key]['ratelist'] = $rate;
            //购买人数
            $list[$key]['buyers'] = (new Financeorder())->getbuyers($value['id']);
        }
        return $list;
    }
    //项目详情
    public function detail($id,$field = null)
    {
        $redis = new Redis();
        $redis->handler()->select(0);
        if($field){
            return $redis->handler()->hMget("zclc:finance:" . $id,$field);
        }else{
            return $redis->handler()->Hgetall("zclc:finance:" . $id);
        }

    }

    /**
     * 项目列表
     */
    public function getfinanceyList($field,$popularize = null){
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:finance:set:0','-inf','+inf',['withscores'=>true]);
        $left = [];
        foreach($categorylist as $k=>$v){
            $reward = $this->detail(intval($k),$field);
            if($popularize){
                if($reward['status'] == 1&&$reward['popularize']==1){
                    $left[] = $reward;
                }
            }else{
                if($reward['status'] == 1){
                    $left[] = $reward;
                }
            }
        }
        return $left;
    }

/**
     * 项目列表
     */
    public function hometop(){
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:finance:set:0','-inf','+inf',['withscores'=>true]);
        $left = [];
        foreach($categorylist as $k=>$v){
            $reward = $this->detail(intval($k));
            if($reward['popularize'] == 2){
                $left[] = $reward;
            }
        }
        return $left;
    }

    public function gettab($tablist){
        if(empty($tablist)){
            return [];
        }
        $tab_array = [];
        foreach ($tablist as $key => $value) {
            $child = explode(',', $value);
            foreach ($child as $k => $v) {
                $st = explode(':', $v);
                $li[$st[0]] = $st[1];
            }
            $tab_array[$key] = $li;
        }
        return $tab_array;
    }

}
