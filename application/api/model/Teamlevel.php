<?php

namespace app\api\model;
use app\api\controller\controller;
use app\api\controller\Paydemo;
use app\api\model\recharge\Pay;
use app\pay\model\Paydemo as ModelPaydemo;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\Log;
/**
 * 体验券订单
 */
class Teamlevel extends Model
{
    protected $name = 'team_level';

    /**
     * 团队人数+1
     * @param $team_recharge
     * @param $team_num
     * @param $pid
     * @return void
     * @throws Exception
     */
    public function addTeamNum($team_num,$pid){
        (new Usertotal())->where(['user_id'=>$pid])->setInc('team_num',$team_num);
        $second = (new User())->where(['id'=>$pid])->value('sid');
        if($second){
            (new Usertotal())->where(['user_id'=>$second])->setInc('team_num',$team_num);
            //改成两级
//            $third = (new User())->where(['id'=>$second])->value('sid');
//            if($third){
//                (new Usertotal())->where(['user_id'=>$third])->setInc('team_num',$team_num);
//            }
        }
    }

    /**
     * 判断条件升级(邀请人数)
     * @param $user_id 上级id
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function teamUpgradeInviteNum($user_id){
        $user_level = (new User())->where(['id'=>$user_id])->value('level');
        $teamInfo = (new Usertotal())->where(['user_id'=>$user_id])->find();
        $next_level = $user_level+1;
        $level = (new Teamlevel())->where(['level'=>$next_level])->find();
        if($level){
            //判断邀请人数、充值金额满200的人数，满足就升级
            if($teamInfo['invite_number']>=$level['need_num']&&$teamInfo['invite_recharge']>=$level['need_user_recharge']){
                (new User())->where(['id'=>$user_id])->setInc('level',1);
                //记录升级日志
                (new Teamlevellog())->addLog($user_id,$next_level,$user_level);
                if($level['cash']){
                    (new Usermoneylog())->moneyrecords($user_id, $level['cash'], 'inc', 25, "{$level['name']}");
                }
            }
        }
    }

    /**
     * 判断条件升级(邀请用户充值超过200的人数), 并且邀请用户充值达到200人数+1
     * @param $user_id 用户id
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function teamUpgradeRechargeNum($user_id){
        $pid = (new \app\admin\model\User())->where(['id'=>$user_id])->value('sid');
        if($pid){
            $teamInfo = (new Usertotal())->where(['user_id'=>$pid])->find();
            $recharge_money = (new Usertotal())->where(['user_id'=>$user_id])->value('total_recharge');
            if($recharge_money >= 200){
                $redis = new Redis();
                $redis->handler()->select(6);
                $exist = $redis->handler()->SISMEMBER("zclc:recharge200member", $user_id);
                if(!$exist){
                    $teamInfo->invite_recharge = $teamInfo->invite_recharge + 1;
                    $redis->handler()->SADD("zclc:recharge200member", $user_id);
                    $user_level = (new User())->where(['id'=>$pid])->value('level');
                    $next_level = $user_level+1;
                    $level = (new Teamlevel())->where(['level'=>$next_level])->find();
                    if($level){
                        //判断邀请人数、充值金额满200的人数，满足就升级
                        if($teamInfo['invite_number']>=$level['need_num']&&$teamInfo->invite_recharge>=$level['need_user_recharge']){
                            (new User())->where(['id'=>$pid])->setInc('level',1);
                            //记录升级日志
                            (new Teamlevellog())->addLog($pid,$next_level,$user_level);
                            if($level['cash']){
                                (new Usermoneylog())->moneyrecords($pid, $level['cash'], 'inc', 25, "{$level['name']}");
                            }
                        }
                    }
                    $teamInfo->save();
                }
            }
        }
    }

    /**
     * 判断推广激励(有上级 并且上级购买过相同的推广方案)
     * @param $user_id
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pidAward($user_id,$project_id){
        $pid = (new \app\admin\model\User())->where(['id'=>$user_id])->value('sid');
        if($pid){
            $order = (new Financeorder())->where(['user_id'=>$pid,'project_id'=>$project_id])->find();
            if($order){
                $total = (new Usertotal())->where(['user_id'=>$pid])->find();
                $promotion_project = $total['promotion_project'] + 1;
                $total->promotion_project = $promotion_project;
                if($promotion_project % 2 == 0){
                    $amount = $order['amount'];
                    (new Usermoneylog())->moneyrecords($pid, $amount, 'inc', 24, "推广激励-方案id{$project_id}");
                }
                $total->save();
            }
        }
    }

    //等级表格
    public function detail($level)
    {
        $redis = new Redis();
        $level_info = $redis->handler()->Hgetall("zclc:team_level:" . $level);
        return $level_info;
    }
}
