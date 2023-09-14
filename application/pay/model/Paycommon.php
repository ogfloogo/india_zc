<?php

namespace app\pay\model;

use app\api\controller\Shell;
use app\api\model\Report;
use app\api\model\Teamlevel;
use app\api\model\Turntable;
use app\api\model\User;
use app\api\model\Useraward;
use app\api\model\Usermoneylog;
use app\api\model\Userrecharge;
use app\api\model\Usertask;
use app\api\model\Usertotal;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;
use think\Model;

class Paycommon extends Model
{
    /**
     * 代收回调数据处理
     *
     * @ApiMethod (POST)
     * @param string $order_id 订单号
     * @param string $order_num 三方流水号
     * @param string $amount   实际支付金额
     */
    public function paynotify($order_id, $order_num, $amount, $channel)
    {
        $userrecharge = new Userrecharge();
        //订单是否存在
        $order_info = $userrecharge->where('order_id', $order_id)->find();
        if (!$order_info) {
            Log::mylog('订单不存在', $order_id, $channel);
            return false;
        }
        $user_id = $order_info['user_id'];
        //订单是否已支付
        if ($order_info['status'] == 1) {
            Log::mylog('已支付', $order_id, $channel);
            return false;
        }
        //充值金额是否一致
        // if ($order_info['price'] != $amount) {
        //     Log::mylog('金额不一致', $order_id, $channel);
        //     return false;
        // }
        Db::startTrans();
        try {
            //平台日报表统计
            (new Shell())->addreport();
            //今日充值用户统计
            $report = new Report();
            //新增充值订单
            $report->where('date', date("Y-m-d", time()))->setInc('rechargeorder', 1);
            //新增充值金额
            $report->where('date', date("Y-m-d", time()))->setInc('recharge', $amount);
            //新增充值用户人数
            //今日首充用户统计
            $user_order = $userrecharge->where('user_id', $user_id)->where('status', 1)->find();
            if (!$user_order) {
                //更新首充订单状态
                $upd['is_first'] = 1;
                //首充人数
                $report->where('date', date("Y-m-d", time()))->setInc('first_rechargeuser', 1);
                //首充解除奖励限制
                $redis = new Redis();
                $redis->handler()->select(6);
                $end_time = $redis->handler()->zScore("zclc:sendlist", $user_id); //到期时间
                if ($end_time) {
                    $redis->handler()->zRem('zclc:sendlist', $user_id);
                }
                (new Usertask())->taskRewardType($user_id,2);
            }
            $day_recharge = config('site.day_recharge');
            if($amount >= $day_recharge){
                (new Usertask())->taskRewardType($user_id,3);
            }
            //今日充值人数
            $time = Time::today();
            $today_user_recharge = $userrecharge->where('user_id', $user_id)->where('status', 1)->where('createtime', 'between', [$time[0], $time[1]])->find();
            if (!$today_user_recharge) {
                $report->where('date', date("Y-m-d", time()))->setInc('rechargeuser', 1);
            }
            //用户充值金额统计
            (new Usertotal())->where('user_id', $user_id)->setInc('total_recharge', $amount);
            //转盘活动增加次数
            (new Turntable())->addtimes($user_id,$amount,2);
            //判断升级条件
//            (new Teamlevel())->teamUpgradeRechargeNum($user_id);
            //更新邀请奖励首充状态
//            $useraward = new Useraward();
//            $award_info = $useraward->where('source', $user_id)->where('status', 0)->find();
//            if ($award_info) {
//                //邀请奖励比例
//                $invite_rate = Config::get("site.invite_rate");
//                //奖励金额
//                $moneys = bcmul($amount, ($invite_rate / 100), 2);
//                $award_info_upd = $useraward->where('source', $user_id)->where('status', 0)->update(['status' => 2, 'moneys' => $moneys,'recharge'=> $amount,'recharge_time' => time()]);
//                if ($award_info_upd) {
//                    Log::mylog('邀请奖励，用户ID：' . $user_id, $award_info, 'useraward');
//                }
//            }
            //操作余额
            $usermoneylog = (new Usermoneylog())->moneyrecords($user_id, $amount, 'inc', 1, $order_id);
            if (!$usermoneylog) {
                Db::rollback();
                return false;
            }
            //赠送金额
            if ($order_info['givemoney'] > 0 && $order_info['price'] == $amount) {
                $usermoneylogs = (new Usermoneylog())->moneyrecords($user_id, $order_info['givemoney'], 'inc', 14, $order_id);
                if (!$usermoneylogs) {
                    Db::rollback();
                    return false;
                }
            }
            //更新订单信息
            // $upd = [
            //     'status' => 1,
            //     'order_num' => $order_num,
            //     'paytime' => time(),
            //     'updatetime' => time(),
            //     'price' => $amount
            // ];
            $upd['status'] = 1;
            $upd['order_num'] = $order_num;
            $upd['paytime'] = time();
            $upd['updatetime'] = time();
            $upd['price'] = $amount;
            $res = $userrecharge->where('order_id', $order_id)->where('status', 0)->update($upd);
            if (!$res) {
                Db::rollback();
                (new User())->refresh($user_id);
                return false;
            }
            Log::mylog('支付回调成功！', $order_id, $channel);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            (new User())->refresh($user_id);
            Log::mylog('支付回调', $e, $channel);
        }
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        exit('success');
    }

    /**
     * 充值赠送
     */
    public function setgivemoney($price,$givemoney){
        
    }
}
