<?php

namespace app\api\model;

use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Log;

/**
 * 资金记录
 */
class Rtmoney extends Model
{
    protected $name = 'user_money_log';
    /**
     * 资金类型
     */
    const TYPEENGLIST = [
        "english" => [
            1 => "Recharge",
            2 => "Withdraw",
            3 => "Invitation bonus",
            4 => "Commission",
            5 => "Group-buying",
            6 => "Withdrawal failed",
            7 => "Cashback",
            8 => "Leader bonus",
            9 => "Newbie Rewards",
            10 => "System operation",
            11 => "Recycle",
            12 => "Return principal"
        ],
    ];

    //余额增减
    public function updbalance($mold, $user_id, $amount)
    {
        if ($mold == "inc") {
            $balance = (new User())->where('id', $user_id)->setInc('money', $amount);
            if (!$balance) {
                return false;
            } else {
                return true;
            }
        } else {
            $balance = (new User())->where('id', $user_id)->where('money', '>=', $amount)->setDec('money', $amount);
            if (!$balance) {
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * 我要开团
     * @param string $userinfo  用户信息
     * @param string $category_info  商品区
     * @param string $order_id  订单ID
     */
    public function opengrouprewards($user_id, $category_info, $order_id)
    {
        $userinfo = (new User())->where('id', $user_id)->field('money,level')->find();
        //开启事务 
        Db::startTrans();
        $this->settables($user_id);
        try {
            //返回本金
            $after = bcadd($userinfo['money'], $category_info['price'], 2);
            $inset_money_log_bj = $this->addmoneylog(12, 'inc', $user_id, $category_info['price'], $userinfo['money'], $after, $order_id);
            if (!$inset_money_log_bj) {
                Db::rollback();
                Log::mylog('返回本金-资金记录创建失败', $inset_money_log_bj, 'moneylog');
                return false;
            } else {
                //退还本金余额操作
                $updbalance = $this->updbalance('inc', $user_id, $category_info['price']);
                if (!$updbalance) {
                    Db::rollback();
                    Log::mylog('退还本金-余额操作', $updbalance, 'balancelog');
                    return false;
                }
                $userinfo_new = (new User())->where('id', $user_id)->find();
                //更新用户等级
                $updatelevel = (new Level())->updatelevel($userinfo_new);
                if (!$updatelevel) {
                    Db::rollback();
                    return false;
                }
            }
            $bouns1 = $category_info['reward']; //团购奖励
            //团购奖励
            $after_tg = bcadd($after, $bouns1, 2);
            $inset_money_log_kt = $this->addmoneylog(7, 'inc', $user_id, $bouns1, $after, $after_tg, $order_id);
            if (!$inset_money_log_kt) {
                Db::rollback();
                Log::mylog('团购奖励-资金记录创建失败', $inset_money_log_kt, 'moneylog');
                return false;
            } else {
                //团购奖励余额操作
                $updbalance = $this->updbalance('inc', $user_id, $bouns1);
                if (!$updbalance) {
                    Db::rollback();
                    Log::mylog('团购奖励-余额操作', $inset_money_log_kt, 'balancelog');
                    return false;
                }

                //上一次查的就是旧信息
                $extra = [];
                $extra['old_user_info'] = $userinfo_new;
                $extra['type'] = 7; //团购奖励
                $extra['time'] = time();
                $extra['user_id'] = $user_id;

                $userinfo_new = (new User())->where('id', $user_id)->find();
                //更新用户等级
                $updatelevel = (new Level())->updatelevel($userinfo_new, $extra);
                if (!$updatelevel) {
                    Db::rollback();
                    return false;
                }
                //统计当日报表
                (new Usercategory())->addlog(7, $user_id, $bouns1);
                //统计用户总报表
                (new Usertotal())->addlog(7, $user_id, $bouns1);
            }
            //团长奖励
            $group_head_reward = Config::get('site.group_head_reward');
            $bouns2 = bcmul($bouns1, (intval($group_head_reward) / 100), 2); //团长奖励
            $after_tz = bcadd($after_tg, $bouns2, 2);
            $inset_money_log_kt = $this->addmoneylog(8, 'inc', $user_id, $bouns2, $after_tg, $after_tz, $order_id);
            if (!$inset_money_log_kt) {
                Db::rollback();
                Log::mylog('团长奖励-资金记录创建失败', $inset_money_log_kt, 'moneylog');
                return false;
            } else {
                //团购奖励余额操作
                $updbalance = $this->updbalance('inc', $user_id, $bouns2);
                if (!$updbalance) {
                    Db::rollback();
                    Log::mylog('退还本金-余额操作', $updbalance, 'balancelog');
                    return false;
                }

                //上一次查的就是旧信息
                $extra = [];
                $extra['old_user_info'] = $userinfo_new;
                $extra['type'] = 8; //团长奖励
                $extra['time'] = time();
                $extra['user_id'] = $user_id;

                $userinfo_new = (new User())->where('id', $user_id)->find();
                //更新用户等级
                $updatelevel = (new Level())->updatelevel($userinfo_new, $extra);
                if (!$updatelevel) {
                    Db::rollback();
                    return false;
                }
                //统计当日报表
                (new Usercategory())->addlog(8, $user_id, $bouns2);
                //统计用户总报表
                (new Usertotal())->addlog(8, $user_id, $bouns2);
            }
            //佣金发放
            //团购奖励+团长奖励
            // $bouns = bcadd($bouns1, $bouns2, 2);
            // (new Commission())->commissionissued($user_id, $bouns, $order_id, $userinfo['level']);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::mylog('未中奖operation failure', $e, 'draw');
            return false;
        }
    }

    /**
     * 一键开团
     * @param string $userinfo  用户信息
     * @param string $category_info  商品区
     * @param string $order_id  订单ID
     */
    public function akeytoopen($user_id, $category_info, $order_id)
    {
        $userinfo = (new User())->where('id', $user_id)->field('money,level')->find();
        //开启事务 
        Db::startTrans();
        $this->settables($user_id);
        try {
            //退还本金
            $after = bcadd($userinfo['money'], $category_info['price'], 2);
            $inset_money_log_bj = $this->addmoneylog(12, 'inc', $user_id, $category_info['price'], $userinfo['money'], $after, $order_id);
            if (!$inset_money_log_bj) {
                Db::rollback();
                Log::mylog('返回本金-资金记录创建失败', $inset_money_log_bj, 'moneylog');
                return false;
            } else {
                //退还本金余额操作
                $updbalance = $this->updbalance('inc', $user_id, $category_info['price']);
                if (!$updbalance) {
                    Db::rollback();
                    Log::mylog('退还本金-余额操作', $updbalance, 'balancelog');
                    return false;
                }
                $userinfo_new = (new User())->where('id', $user_id)->find();
                //更新用户等级
                $updatelevel = (new Level())->updatelevel($userinfo_new);
                if (!$updatelevel) {
                    Db::rollback();
                    return false;
                }
            }
            $bouns1 = $category_info['reward']; //团购奖励
            //团购奖励
            $after_tg = bcadd($after, $bouns1, 2);
            $inset_money_log_tg = $this->addmoneylog(7, 'inc', $user_id, $bouns1, $after, $after_tg, $order_id);
            if (!$inset_money_log_tg) {
                Db::rollback();
                Log::mylog('团购奖励-资金记录创建失败', $inset_money_log_tg, 'moneylog');
                return false;
            } else {
                //团购奖励余额操作
                $updbalance = $this->updbalance('inc', $user_id, $bouns1);
                if (!$updbalance) {
                    Db::rollback();
                    Log::mylog('退还本金-余额操作', $updbalance, 'balancelog');
                    return false;
                }

                //上一次查的就是旧信息
                $extra = [];
                $extra['old_user_info'] = $userinfo_new;
                $extra['type'] = 7; //团购奖励
                $extra['time'] = time();
                $extra['user_id'] = $user_id;

                $userinfo_new = (new User())->where('id', $user_id)->find();
                //更新用户等级
                $updatelevel = (new Level())->updatelevel($userinfo_new, $extra);
                if (!$updatelevel) {
                    Db::rollback();
                    return false;
                }
                //统计当日报表
                (new Usercategory())->addlog(7, $user_id, $bouns1);
                //统计用户总报表
                (new Usertotal())->addlog(7, $user_id, $bouns1);
            }
            //佣金发放
            //团购奖励
            // $bouns = $bouns1;
            // (new Commission())->commissionissued($user_id, $bouns, $order_id, $userinfo['level']);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::mylog('未中奖operation failure', $e, 'draw');
            return false;
        }
    }

    /**
     * 资金记录
     *@param string $user_id  用户ID
     * @param string $amount  操作金额
     * @param string $mold  inc加 dec减
     * @param string $type  操作类型 1，充值，2提现，3邀请奖励，4佣金收入，5团购下单，6拒绝提现，7团购奖励，8团长奖励，9新用户注册奖励，10管理员操作，11兑换现金，12团购未中奖返还
     * @param string $before  操作前余额
     * @param string $after  操作后余额
     * @param string $remark  备注
     */
    public function addmoneylog($type, $mold, $user_id, $amount, $before, $after, $remark)
    {
        $insert = [
            "user_id" => $user_id, //用户ID
            "money" => $amount, //变动余额
            "before" => $before, //变动前余额
            "after" => $after, //变动后余额
            "type" => $type,
            "mold" => $mold,
            "remark" => $remark,
            "createtime" => time()
        ];
        return $this->insert($insert);
    }

    /**
     * 找表
     */
    public function settables($user_id)
    {
        $mod = 1000;
        $table_number = ceil($user_id / $mod);
        if ($user_id <= 1000) {
            $tb_num = ceil($user_id / 100);
            $table_name = "fa_user_money_log_1_" . $tb_num;
        } else {
            $table_name = "fa_user_money_log_" . $table_number;
        }
        $this->setTable($table_name);
    }
}
