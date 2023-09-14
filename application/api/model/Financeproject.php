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
class Financeproject extends Model
{
    protected $name = 'finance_project';

    //活动详情
    public function detail($id, $field = null)
    {
        $redis = new Redis();
        $redis->handler()->select(0);
        if ($field) {
            return $redis->handler()->hMget("zclc:financeproject:" . $id, $field);
        } else {
            return $redis->handler()->Hgetall("zclc:financeproject:" . $id);
        }
    }

    /**
     * 项目方案列表
     */
    public function list($id, $field = null)
    {
        $category_id_key = "zclc:financeproject:set:" . $id;
        $redis = new Redis();
        $goodslist = $redis->handler()->ZRANGEBYSCORE($category_id_key, '-inf', '+inf', ['withscores' => true]);
        $res = [];
        foreach ($goodslist as $key => $value) {
            $detail = $this->detail(intval($key), $field);
            if ($detail['status'] == 1) {
                $res[] = $detail;
            }
        }
        return $res;
    }

    /**
     * 项目方案列表
     */
    public function lists($id, $user_id, $field = null)
    {
        $category_id_key = "zclc:financeproject:set:" . $id;
        $redis = new Redis();
        $goodslist = $redis->handler()->ZRANGEBYSCORE($category_id_key, '-inf', '+inf', ['withscores' => true]);
        $res = [];
        foreach ($goodslist as $key => $value) {
            $detail = $this->detail(intval($key), $field);
            $is_look = true;
            if ($detail['is_new_hand'] == 1) {
                //是否购买过新手方案
                $is_buy = db('finance_order')->where(['user_id' => $user_id, 'is_new_hand' => 1,'is_robot'=>0])->field('id')->find();
                if (!empty($is_buy)) {
                    $is_look = false;
                }
            }
            if ($detail['status'] == 1 && $is_look) {
                $res[] = $detail;
            }
        }
        return $res;
    }

    public function details($id, $user_id)
    {
        $finance_issue_info = $this->where('id', $id)->field('id,name,finance_id,presell_start_time,presell_end_time,start_time,end_time,day,status')->find();
        $finance_issue_info['end_days'] = $finance_issue_info["end_time"] + 60 * 60 * 24;
        $redis = new Redis();
        $redis->handler()->select(0);
        $finance_info = $redis->handler()->Hgetall("new:finance:" . $finance_issue_info['finance_id']);
        $finance_issue_info['finance_name'] = $finance_info['name'];
        $finance_issue_info['price'] = $finance_info['price'];
        $finance_issue_info['user_max_buy'] = $finance_info['user_max_buy'];
        $finance_issue_info['user_min_buy'] = $finance_info['user_min_buy'];
        //当前收益率
        $now_rate = (new Financeorder())->getrate($finance_issue_info['finance_id'], $finance_issue_info['id']);
        $finance_issue_info['rate'] = $now_rate['rate'];
        //购买人数
        $finance_issue_info['buyers'] = (new Financeorder())->getbuyers($finance_issue_info['id']);
        //我的购买
        $my_purchase = (new Financeorder())->where('finance_id', $finance_issue_info['finance_id'])->where('issue_id', $id)->where('user_id', $user_id)->where('status', 1)->sum('amount');
        $finance_issue_info['my_purchase'] = $my_purchase;
        //已购买用户
        $orderlist = (new Financeorder())->where('finance_id', $finance_issue_info['finance_id'])->where('issue_id', $id)->where('status', 1)->order('createtime desc')->field('id,user_id,createtime,is_robot')->limit(100)->select();
        foreach ($orderlist as $key => $value) {
            if ($value['is_robot'] == 0) {
                $userinfo = (new User())->where('id', $value['user_id'])->field('nickname,avatar')->find();
                $orderlist[$key]['nickname'] = $userinfo['nickname'];
                $orderlist[$key]['avatar'] = format_image($userinfo['avatar']);
                $orderlist[$key]['createtime'] = format_time($value['createtime']);
                $orderlist[$key]['money'] = (new Financeorder())->where('finance_id', $finance_issue_info['finance_id'])->where('issue_id', $id)->where('status', 1)->where('is_robot', 0)->where('user_id', $value['user_id'])->sum('amount');
            } else {
                $userinfo = db('user_robot')->where('id', $value['user_id'])->field('name,avatar')->find();
                $orderlist[$key]['nickname'] = $userinfo['name'];
                $orderlist[$key]['avatar'] = format_image($userinfo['avatar']);
                $orderlist[$key]['createtime'] = format_time($value['createtime']);
                $orderlist[$key]['money'] = (new Financeorder())->where('finance_id', $finance_issue_info['finance_id'])->where('issue_id', $id)->where('status', 1)->where('is_robot', 1)->where('user_id', $value['user_id'])->sum('amount');
            }
        }
        $finance_issue_info['buyers_list'] = $orderlist;
        //收益率规则列表
        $ratelist = (new Financerate())->detail($finance_issue_info['finance_id']);
        $finance_issue_info['ratelist'] = $ratelist;
        $rate_info = $this->getnextrate($ratelist, $now_rate);
        if ($rate_info) {
            $finance_issue_info['adding'] = $rate_info['start'] - $now_rate['start'];
            $finance_issue_info['yield_by_rate'] = bcsub($rate_info['rate'], $now_rate['rate'], 1);
        } else {
            $finance_issue_info['adding'] = 0;
            $finance_issue_info['yield_by_rate'] = 0;
        }
        //活动是否结束
        $this->updatestatus($finance_issue_info['end_time'], $finance_issue_info['presell_end_time'], $finance_issue_info['status'], $finance_issue_info['id'], $finance_issue_info['finance_id']);
        return $finance_issue_info;
    }

    /**
     * 当前这一期是否已结束
     */
    public function updatestatus($end_time, $presell_end_time, $status, $id, $finance_id)
    {
        if ($presell_end_time < time() && $status == 0) {
            (new Financeproject())->where('id', $id)->update(['status' => 1]);
            //是否自动下一期
            $finance_info = (new Finance())->detail($finance_id);
            if ($finance_info['auto_open'] == 1 && $finance_info['status'] == 1) {
                (new Financeproject())->add($finance_info);
            }
        }
        if ($end_time < time() && $status == 1) {
            (new Financeproject())->where('id', $id)->update(['status' => 2]);
        }
    }

    /**
     * 开启下一期
     */
    public function add($item)
    {
        $is_exit = $this->where(['finance_id' => $item['id']])->where('status', 0)->find();
        if (!$is_exit) {
            $lastIssue = $this->where(['finance_id' => $item['id']])->order('name DESC')->find();
            $startTime = $lastIssue ? ($lastIssue['presell_end_time']) : time();
            $issueNo = $lastIssue ? ($lastIssue['name'] + 1) : 1001;
            $data = [];
            $data['finance_id'] = $item['id'];
            $data['name'] = $issueNo;
            $data['day'] = $item['day'];
            $presell_start_time = $startTime + 2;
            $data['presell_start_time'] = $presell_start_time;
            $presell_end_time = strtotime(date("Y-m-d", $presell_start_time) . " 23:59:59");
            $data['presell_end_time'] = $presell_end_time;
            //开始收益时间
            $earning_start_time = strtotime(date("Y-m-d", $presell_end_time + 86400) . " 00:00:00");
            //结束收益时间-发放时间
            $earning_end_time = $earning_start_time + 86400 * $lastIssue['day'] - 1;
            $data['start_time'] = $earning_start_time;
            $data['end_time'] = $earning_end_time;
            $data['status'] = 0;
            $data['createtime'] = time();
            $data['updatetime'] = time();
            $is_exits = $this->where(['finance_id' => $item['id']])->where('status', 0)->find();
            if (!$is_exits) {
                $this->insertGetId($data);
            }
        }
    }

    /**
     * 获取下一档收益率
     */
    public function getnextrate($ratelist, $now_rate)
    {
        $rate_info = [];
        foreach ($ratelist as $k => $v) {
            if ($v['id'] == $now_rate['id'] + 1) {
                $rate_info[] = $v;
            }
        }
        if (!empty($rate_info)) {
            return $rate_info[0];
        } else {
            return false;
        }
    }

    /**
     * 新增第一期
     * finance_id 理财ID
     */
    public function addnewissue($finance_id)
    {
        $finance_info = (new Finance())->detail($finance_id);
        //开始预售时间
        $startTime = time();
        //结束预售时间
        $endTime = strtotime(date("Y-m-d", time()) . " 23:59:59");
        //开始收益时间
        $earning_start_time = strtotime(date("Y-m-d", $endTime + 3600) . " 00:00:00");
        //结束收益时间-发放时间
        $earning_end_time = $earning_start_time + 60 * 60 * 24 * $finance_info['day'] - 1;
        $issueNo = 1001;
        $data = [];
        $data['finance_id'] = $finance_info['id'];
        $data['name'] = $issueNo;
        $data['day'] = $finance_info['day'];
        $data['presell_start_time'] = $startTime;
        $data['presell_end_time'] = $endTime;
        $data['start_time'] = $earning_start_time;
        $data['end_time'] = $earning_end_time;
        $data['status'] = 0;
        $data['createtime'] = time();
        $data['updatetime'] = time();
        $this->insertGetId($data);
    }

    public function getPlanList($field,$where,$user_id = 0,$is_experience = 0){
        $redis = new Redis();
        $planlist = $redis->handler()->ZRANGEBYSCORE('zclc:financeproject:set:0','-inf','+inf',['withscores'=>true]);
        $return = [];
        $return2 = [];
        foreach($planlist as $k=>$v1){
            $v = $this->detail($k,$field);
            //已经购买过新手方案就不显示
            if($v['is_new_hand'] == 1&&$user_id){
                $is_buy = db('finance_order')->where(['user_id' => $user_id, 'is_new_hand' => 1,'is_robot'=>0])->field('id')->find();
                if($is_buy){
                    continue;
                }
            }
            if($is_experience == 1){
                if($v['popularize'] == 2){
                    continue;
                }
            }
            if($v['status'] == 1){
                if($where['level'] == 'recommend'){
                    if($v['recommend'] == 1){
                        $return[] = $v;
                    }
                }elseif($where['level'] == 'all'){
                    $return[] = $v;
                }else{
                    if($v['buy_level'] == $where['level']){
                        $return[] = $v;
                    }
                }
            }
        }
        foreach($return as $k=>$v){
            if(isset($where['label_ids'])){
                $label_array = $where['label_ids'];
                foreach ($label_array as $value){
                    if(in_array($value,explode(',',$v['label_ids']))){
                        $return2[] = $v;
                        break;
                    }
                }
            }else{
                $return2 = $return;
            }
        }
        return $return2;
    }

    public function getLabel($ids){
        $return = [];
        if($ids){
            $redis = new Redis();
            $redis->handler()->select(0);

            $arr = explode(',',$ids);
            foreach ($arr as $value){
                $finance_info = $redis->handler()->Hgetall("zclc:financelabel:" . $value);
                $return[] = $finance_info['name'];
            }
        }
        return $return;
    }

    public function getSelectLabel($ids){
        $return = [];
        if($ids){
            $redis = new Redis();
            $redis->handler()->select(0);

            $arr = explode(',',$ids);
            foreach ($arr as $value){
                $finance_info = $redis->handler()->Hgetall("zclc:financelabel:" . $value);
                if($finance_info['status'] == 1){
                    $return[$finance_info['id']] = $finance_info['name'];
                }
            }
        }
        return $return;
    }

    public function getLabelList(){
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:financelabel:set:0','-inf','+inf',['withscores'=>true]);
        $return = [];
        if($categorylist){
            $categorylist = implode(',',$categorylist);
            $return = $this->getSelectLabel($categorylist);
        }
        return $return;
    }
}
