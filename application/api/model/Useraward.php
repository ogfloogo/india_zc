<?php

namespace app\api\model;
use app\api\controller\controller;
use Symfony\Component\VarExporter\Internal\Exporter;

use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;

/**
 * 邀请好友奖励
 */
class Useraward extends Model
{
    protected $name = 'user_award';

    /**
     * 用户信息
     */
    public function userinfo($user_id){
        //待领取总金额
        $will_get_bonus = $this->where('user_id',$user_id)->where('status',2)->sum('moneys');
        //总奖励金额
        $total_bonus = $this->where('user_id',$user_id)->where('status',1)->sum('moneys');
        //总人数
        $total_invite = $this->where('user_id',$user_id)->count();
        //邀请奖励金额
        $invite_bonus = Config::get('site.invite_reward');
        return [
            'will_get_bonus' => $will_get_bonus,
            'total_bonus' => $total_bonus,
            'total_invite' => $total_invite,
            'invite_bonus' => $invite_bonus,
        ];
    }
    
    /**
     * 好友邀请奖励列表
     */
    public function rewardlist($post,$user_id){
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount;
        return $this->alias('a')
        ->join('user b','a.source=b.id')
        ->where('a.user_id',$user_id)
        ->order('a.status','desc')
        ->order('a.createtime','desc')
        ->field('a.*,b.nickname,b.avatar,b.level')
        ->limit($startNum, $pageCount)
        ->select();
    }

    /**
     * 规则
     */
    public function rule(){
        return (new Sysrule())->where('title','邀请奖励')->field('content')->find();
    }

    /**
     * 奖励领取
     */
    public function rewardfor($post,$user_id){
        $is_reward = $this->where('id',$post['id'])->field('status,moneys,source')->find();
        if($is_reward['status'] != 2){
            return false;
        }
        //开启事务
        Db::startTrans();
        try {
            //状态改变
            $res = $this->where('id',$post['id'])->where('status',2)->update(['status'=>1,'updatetime'=>time()]);
            if(!$res){
                Db::rollback();
                return false;
            }
            //金额变动
            $usermoneylog = (new Usermoneylog())->moneyrecords($user_id, $is_reward['moneys'], 'inc', 3, "被邀请用户ID".$is_reward['source']);
            if(!$usermoneylog){
                Db::rollback();
                return false;
            }
            //提交
            Db::commit();
        }catch(Exception $e){
            Log::mylog('奖励领取失败', $e, 'useraward');
            Db::rollback();
            return false;
        }
    }
}
