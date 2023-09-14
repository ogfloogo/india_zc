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
class Usermoneylog extends Model
{
    protected $name = 'user_money_log';
    /**
     * 资金类型
     */
    const TYPEENGLIST = [
        "english" => [
            1 => "Recharge",
            2 => "Withdraw",
            3 => "Invitation Reward",
            4 => "Commission",
            6 => "Withdrawal failed",
            10 => "System Operation",
            14 => "Recharge bonus",
            18 => "Crowdfunding Investment",
            19 => "Principal Return",
            20 => "Get when invited",
            23 => "Crowdfunding income",
            24 => "Promotion Incentive",
            25 => "Cash Reward",
            26 => "Monthly Salary",
            27 => "Sign in reward",
            28 => "Daily invitation rewards",
            29 => "Monthly invitation rewards",
            30 => "Bonus",
            31 => "Spin",
            32 => "Spin",
        ],
        "india" => [
            1 => "Recharge",
            2 => "Withdraw",
            3 => "Invitation bonus",
            4 => "Commission",
            6 => "Withdrawal failed",
            10 => "System operation",
            18 => "Crowdfunding subscription",
            19 => "Return principal",
            20 => "Invitation reward",
            23 => "Crowdfunding income",
            24 => "Promotion award",
            25 => "Cash award",
            26 => "Monthly Salary",
            27 => "Sign in reward",
            28 => "Daily invitation rewards",
            29 => "Monthly invitation rewards",
            30 => "Bonus",
            31 => "Spin",
            32 => "Spin",
        ],
        "china" => [
            1 => "充值",
            2 => "提现",
            3 => "邀请奖励",
            4 => "佣金",
            6 => "拒绝提现",
            10 => "管理员操作",
            14 => "充值赠送",
            18 => "众筹认购",
            19 => "本金返还",
            20 => "邀请立即送",
            23 => "众筹收益",
            24 => "推广激励",
            25 => "现金奖励",
            26 => "月薪",
            27 => "签到奖励",
            28 => "每日邀请奖励",
            29 => "每月邀请奖励",
            30 => "奖金",
            31 => "转盘奖励",
            32 => "翻牌奖励"
        ],
    ];
    /**
     * 资金记录列表
     *
     * @ApiMethod (POST)
     * @param string $user_id  用户ID
     * @param string $page  当前页
     */
    public function list($page, $pageSize, $user_id, $language, $date)
    {
        $this->settables($user_id);
        $where = [];
        if ($date) {
            $date = explode('-', $date);
            $nian = $date[0];
            $yue = $date[1];
            $begin = mktime(0, 0, 0, $yue, 1, $nian);
            $end = mktime(23, 59, 59, ($yue + 1), 0, $nian);
            $where['createtime'] = ['between', [$begin, $end]];
        }
        $list = $this
            ->where('user_id', $user_id)
            ->where($where)
            ->field('money,after,mold,type,createtime')
            ->order('id desc')
            ->page($page, $pageSize)
            ->select();
        $statistics_pay = 0;
        $statistics_income = 0;
        foreach ($list as $key => $value) {
            $list[$key]['typename'] = self::TYPEENGLIST[$language][$value['type']];
            if ($value['mold'] == "inc") {
                $value['money'] = "+" . $value['money'];
                if ($value['type'] != 1 && $value['type'] != 10) {
                    $statistics_income += $value['money'];
                }
            } else {
                $value['money'] = "-" . $value['money'];
                if ($value['type'] != 2) {
                    $statistics_pay += $value['money'];
                }
            }
            $list[$key]['createtime'] = format_time($value['createtime']);
            $list[$key]['icon'] = format_image("/uploads/moneyrecord/" . $value['type'] . ".png");
        }
        $statistics = [
            'statistics_pay' => bcadd($statistics_pay, 0, 2),
            'statistics_income' => bcadd($statistics_income, 0, 2)
        ];
        return ['list' => $list, 'statistics' => $statistics];
    }

    public function listType($page, $pageSize, $user_id, $type)
    {
        $this->settables($user_id);
        $where['type'] = $type;
        $list = $this
            ->where('user_id', $user_id)
            ->where($where)
            ->field('money,after,mold,type,createtime,remark')
            ->order('id desc')
            ->page($page, $pageSize)
            ->select();
        foreach ($list as $key => $value) {
            $list[$key]['createtime'] = format_time($value['createtime']);
            if ($type != 25) {
                $list[$key]['remark'] = "";
            }
        }
        return ['list' => $list];
    }

    public function listTypeReward($page, $pageSize, $language, $user_id, $type)
    {
        $this->settables($user_id);
        $where['type'] = ['in', $type];
        $list = $this
            ->where('user_id', $user_id)
            ->where($where)
            ->field('money,after,mold,type,createtime')
            ->order('id desc')
            ->page($page, $pageSize)
            ->select();
        foreach ($list as $key => $value) {
            $list[$key]['typename'] = self::TYPEENGLIST[$language][$value['type']];
            if ($value['mold'] == "inc") {
                $value['money'] = "+" . $value['money'];
            } else {
                $value['money'] = "-" . $value['money'];
            }
            $list[$key]['createtime'] = format_time($value['createtime']);
            $list[$key]['icon'] = format_image("/uploads/moneyrecord/" . $value['type'] . ".png");
        }
        $total = $this
            ->where('user_id', $user_id)
            ->where($where)
            ->sum('money');
        return ['list' => $list, 'total' => $total];
    }


    /**
     * 用户收入，支出统计
     */
    public function moneytotal($user_id)
    {
        $total_commission = (new Usertotal())->where('user_id', $user_id)->sum('total_commission');
        $group_buying_commission = (new Usertotal())->where('user_id', $user_id)->sum('group_buying_commission');
        $head_of_the_reward = (new Usertotal())->where('user_id', $user_id)->sum('head_of_the_reward');
        $invite_commission = (new Usertotal())->where('user_id', $user_id)->sum('invite_commission');
        $exchangemoney = (new Usertotal())->where('user_id', $user_id)->sum('exchangemoney');
        //总收入
        $inc = bcadd(($total_commission + $group_buying_commission), ($head_of_the_reward + $invite_commission + $exchangemoney), 2);
        $dec = (new Usercash())
            ->where('user_id', $user_id)
            ->where('status', 3)
            ->sum('price');
        return ['inc' => $inc, 'dec' => $dec];
    }

    /**
     * 资金记录log
     *
     * @ApiMethod (POST)
     * @param string $user_id  用户ID
     * @param string $amount  操作金额
     * @param string $mold  inc加 dec减
     * @param string $type  操作类型 1，充值，2提现，3邀请奖励，4佣金收入，5团购下单，6拒绝提现，7团购奖励，8团长奖励，9新用户注册奖励，10管理员操作，11兑换现金，12团购未中奖返还，13体验到期
     * @param string $remark  备注
     */
    public function moneyrecords($user_id, $amount, $mold, $type, $remark = "")
    {
        //找表
        $this->settables($user_id);
        $userinfo = (new User())->where('id', $user_id)->field('money,level,agent_id')->find();
        //余额变动
        $balance = $this->updbalance($mold, $user_id, $amount);
        if (!$balance) {
            Log::mylog('资金变动失败', $balance, 'moneylog');
            return false;
        }
        if ($mold == "inc") {
            $after = bcadd($userinfo['money'], $amount, 2);
        } else {
            $after = bcsub($userinfo['money'], $amount, 2);
        }

        //新增资金记录
        $inset_money_log = $this->addmoneylog($type, $mold, $user_id, $amount, $userinfo['money'], $after, $remark, intval($userinfo['agent_id']));
        if (!$inset_money_log) {
            Log::mylog('资金记录创建失败', $inset_money_log, 'moneylog');
            return false;
        }

        // $extra = [];
        // //上一次查的就是旧信息
        // if (!in_array($type, [5, 12])) {
        //     $extra['old_user_info'] = $userinfo;
        //     $extra['type'] = $type; //团购奖励
        //     $extra['time'] = time();
        //     $extra['user_id'] = $user_id;
        //     $extra['agent_id'] = intval($userinfo['agent_id']);
        // }

        // $userinfo_new = (new User())->where('id', $user_id)->find();
        //更新用户等级
        //        $updatelevel = (new Level())->updatelevel($userinfo_new, $extra);
        //        if (!$updatelevel) {
        //            Log::mylog('等级更新失败', $updatelevel, 'levellog');
        //            return false;
        //        }
        //已读未读
        (new User())->where(['id' => $user_id])->update(['record_read' => 0]);
        //统计当日报表
        (new Usercategory())->addlog($type, $user_id, $amount);
        //统计用户总报表
        (new Usertotal())->addlog($type, $user_id, $amount);
        //刷新用户信息
        (new User())->refresh($user_id);
        return true;
    }

    /**
     * 资金记录log 团购下单 statistics
     *
     * @ApiMethod (POST)
     * @param string $user_id  用户ID
     * @param string $amount  操作金额
     * @param string $mold  inc加 dec减
     * @param string $type  操作类型 1，充值，2提现，3邀请奖励，4佣金收入，5团购下单，6拒绝提现，7团购奖励，8团长奖励，9新用户注册奖励，10管理员操作，11兑换现金，12团购未中奖返还
     * @param string $remark  备注
     */
    public function moneyrecordorder($user_id, $amount, $mold, $type, $remark = "")
    {
        $userinfo = (new User())->where('id', $user_id)->field('money,level,agent_id')->find();
        //找表
        $this->settables($user_id);
        //开启事务 
        Db::startTrans();
        try {
            //余额变动
            $balance = $this->updbalance($mold, $user_id, $amount);
            if (!$balance) {
                Db::rollback();
                Log::mylog('资金变动失败', $balance, 'moneylog');
                return false;
            }
            //新增资金记录
            $after = bcadd($userinfo['money'], $amount, 2);
            $inset_money_log = $this->addmoneylog($type, $mold, $user_id, $amount, $userinfo['money'], $after, $remark, intval($userinfo['agent_id']));
            if (!$inset_money_log) {
                Db::rollback();
                Log::mylog('资金记录创建失败', $inset_money_log, 'moneylog');
                return false;
            }

            $extra = [];
            //上一次查的就是旧信息
            if (!in_array($type, [5, 12])) {
                $extra['old_user_info'] = $userinfo;
                $extra['type'] = $type; //团购奖励
                $extra['time'] = time();
                $extra['user_id'] = $user_id;
                $extra['agent_id'] = intval($userinfo['agent_id']);
            }

            $userinfo_new = (new User())->where('id', $user_id)->find();
            //更新用户等级
            $updatelevel = (new Level())->updatelevel($userinfo_new, $extra);
            if (!$updatelevel) {
                Db::rollback();
                return false;
            }
            //提交
            Db::commit();
            //统计当日报表
            (new Usercategory())->addlog($type, $user_id, $amount);
            //统计用户总报表
            (new Usertotal())->addlog($type, $user_id, $amount);
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::mylog('资金变动失败', $e, 'moneylog');
            return false;
        }
    }

    /**
     * 资金记录log 下单
     *
     * @ApiMethod (POST)
     * @param string $user_id  用户ID
     * @param string $amount  操作金额
     * @param string $mold  inc加 dec减
     * @param string $type  操作类型 1，充值，2提现，3邀请奖励，4佣金收入，5团购下单，6拒绝提现，7团购奖励，8团长奖励，9新用户注册奖励，10管理员操作，11兑换现金，12团购未中奖返还
     * @param string $remark  备注
     */
    public function moneyrecordorders($orderinsert, $userinfo, $amount, $mold, $type, $remark = "")
    {
        $user_id = $userinfo['id'];
        $userinfo = (new User())->where('id', $user_id)->field('money,level,agent_id')->find();
        //找表
        $this->settables($user_id);
        //开启事务 
        Db::startTrans();
        try {
            //新增订单
            $order_id = (new Order())->insertGetId($orderinsert);
            if (!$order_id) {
                Db::rollback();
                Log::mylog('团购order failed', $order_id, 'addorder');
                return false;
            }
            //余额变动
            $balance = $this->updbalance($mold, $user_id, $amount);
            if (!$balance) {
                Db::rollback();
                Log::mylog('资金变动失败', $balance, 'moneylog');
                return false;
            }
            //新增资金记录
            $after = bcsub($userinfo['money'], $amount, 2);
            $inset_money_log = $this->addmoneylog($type, $mold, $user_id, $amount, $userinfo['money'], $after, $remark, intval($userinfo['agent_id']));
            if (!$inset_money_log) {
                Db::rollback();
                Log::mylog('资金记录创建失败', $inset_money_log, 'moneylog');
                return false;
            }

            $extra = [];
            //上一次查的就是旧信息
            if (!in_array($type, [5, 12])) {
                $extra['old_user_info'] = $userinfo;
                $extra['type'] = $type; //团购奖励
                $extra['time'] = time();
                $extra['user_id'] = $user_id;
                $extra['agent_id'] = intval($userinfo['agent_id']);
            }
            $userinfo_new = (new User())->where('id', $user_id)->find();
            //更新用户等级
            $updatelevel = (new Level())->updatelevel($userinfo_new, $extra);
            if (!$updatelevel) {
                Db::rollback();
                Log::mylog('等级更新失败', $balance, 'levellog');
                return false;
            }
            //提交事务
            Db::commit();
            //统计当日报表
            (new Usercategory())->addlog($type, $user_id, $amount);
            //统计用户总报表
            (new Usertotal())->addlog($type, $user_id, $amount);
            return $order_id;
        } catch (\Exception $e) {
            Db::rollback();
            Log::mylog('order failed', $e, 'order');
            return false;
        }
    }

    /**
     * 资金记录log-提现
     * 
     *
     * @ApiMethod (POST)
     * @param string $user_id  用户ID
     * @param string $amount  操作金额
     * @param string $mold  inc加 dec减
     * @param string $type  操作类型 1，充值，2提现，3邀请奖励，4佣金收入，5团购下单，6拒绝提现，7团购奖励，8团长奖励，9新用户注册奖励，10管理员操作，11兑换现金，12团购未中奖返还
     * @param string $remark  备注
     */
    public function withdraw($orderinsert, $user_id, $amount, $mold, $type, $remark = "")
    {
        //找表
        $this->settables($user_id);
        $userinfo = (new User())->where('id', $user_id)->field('money,level,agent_id')->find();
        //开启事务 
        Db::startTrans();
        try {
            $addcash = (new Usercash())->insert($orderinsert);
            if (!$addcash) {
                Db::rollback();
                Log::mylog('提现失败', $addcash, 'cash');
                return false;
            }
            //余额变动
            $balance = $this->updbalance($mold, $user_id, $amount);
            if (!$balance) {
                Db::rollback();
                Log::mylog('资金变动失败', $balance, 'moneylog');
                return false;
            }
            $after = bcsub($userinfo['money'], $amount, 2);
            $inset_money_log = $this->addmoneylog($type, $mold, $user_id, $amount, $userinfo['money'], $after, $remark, intval($userinfo['agent_id']));
            if (!$inset_money_log) {
                Log::mylog('资金记录创建失败', $inset_money_log, 'moneylog');
                return false;
            }

            //            $extra = [];
            //            //上一次查的就是旧信息
            //            if (!in_array($type, [5, 12])) {
            //                $extra['old_user_info'] = $userinfo;
            //                $extra['type'] = $type; //团购奖励
            //                $extra['time'] = time();
            //                $extra['user_id'] = $user_id;
            //                $extra['agent_id'] = intval($userinfo['agent_id']);
            //            }
            //            $userinfo_new = (new User())->where('id', $user_id)->find();
            //            //更新用户等级
            //            $updatelevel = (new Level())->updatelevel($userinfo_new, $extra);
            //            if (!$updatelevel) {
            //                Log::mylog('等级更新', $userinfo_new, 'moneylog');
            //                return false;
            //            }
            Db::commit();
            //已读未读
            (new User())->where(['id' => $user_id])->update(['record_read' => 0]);
            //统计当日报表
            (new Usercategory())->addlog($type, $user_id, $amount);
            //统计用户总报表
            (new Usertotal())->addlog($type, $user_id, $amount);
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::mylog('资金变动失败', $e, 'moneylog');
            return false;
        }
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

    public function createtb($user_id)
    {
        $table_name = $this->gettable($user_id);
        db()->query("CREATE TABLE IF NOT EXISTS " . $table_name . ' LIKE ' . 'fa_user_money_log_base');
    }

    public function gettable($user_id)
    {
        $mod = 1000;
        $table_number = ceil($user_id / $mod);
        if ($user_id <= 1000) {
            $tb_num = ceil($user_id / 100);
            $table_name = "fa_user_money_log_1_" . $tb_num;
        } else {
            $table_name = "fa_user_money_log_" . $table_number;
        }
        return $table_name;
    }

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

    public function push($type, $user_id, $amount)
    {
        $redis = new Redis();
        $value = $type . "-" . $user_id . "-" . $amount;
        $push = $redis->handler()->rpush("statistical", $value);
        if ($push !== false) {
            Log::mylog('统计消息队列', $value, 'statistical');
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
        $userinfo = (new User())->where('id', $user_id)->field('money,level,agent_id')->find();
        //开启事务 
        Db::startTrans();
        try {
            //返回本金
            $after = bcadd($userinfo['money'], $category_info['price'], 2);
            $inset_money_log_bj = $this->addmoneylog(12, 'inc', $user_id, $category_info['price'], $userinfo['money'], $after, $order_id, intval($userinfo['agent_id']));
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
            $inset_money_log_kt = $this->addmoneylog(7, 'inc', $user_id, $bouns1, $after, $after_tg, $order_id, intval($userinfo['agent_id']));
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

                $extra = [];
                //上一次查的就是旧信息
                $extra['old_user_info'] = $userinfo_new;
                $extra['type'] = 7; //团购奖励
                $extra['time'] = time();
                $extra['user_id'] = $user_id;
                $extra['agent_id'] = intval($userinfo['agent_id']);

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
            $inset_money_log_kt = $this->addmoneylog(8, 'inc', $user_id, $bouns2, $after_tg, $after_tz, $order_id, intval($userinfo['agent_id']));
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

                $extra = [];
                //上一次查的就是旧信息
                $extra['old_user_info'] = $userinfo_new;
                $extra['type'] = 8; //团长奖励
                $extra['time'] = time();
                $extra['user_id'] = $user_id;
                $extra['agent_id'] = intval($userinfo['agent_id']);


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
            $userinfo = (new User())->where('id', $user_id)->field('level,agent_id')->find();
            $bouns = bcadd($bouns1, $bouns2, 2);
            (new Commission())->commissionissued($user_id, $bouns, $order_id, $userinfo['level'], intval($userinfo['agent_id']));
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
        $userinfo = (new User())->where('id', $user_id)->field('money,level,agent_id')->find();
        //开启事务 
        Db::startTrans();
        try {
            //退还本金
            $after = bcadd($userinfo['money'], $category_info['price'], 2);
            $inset_money_log_bj = $this->addmoneylog(12, 'inc', $user_id, $category_info['price'], $userinfo['money'], $after, $order_id, intval($userinfo['agent_id']));
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
            $inset_money_log_tg = $this->addmoneylog(7, 'inc', $user_id, $bouns1, $after, $after_tg, $order_id, intval($userinfo['agent_id']));
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
                $extra['agent_id'] = intval($userinfo['agent_id']);

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
            $bouns = $bouns1;
            $userinfo = (new User())->where('id', $user_id)->field('level,agent_id')->find();
            (new Commission())->commissionissued($user_id, $bouns, $order_id, $userinfo['level'], intval($userinfo['agent_id']));
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
    public function addmoneylog($type, $mold, $user_id, $amount, $before, $after, $remark, $agent_id = 0)
    {
        $insert = [
            "user_id" => $user_id, //用户ID
            "money" => $amount, //变动余额
            "before" => $before, //变动前余额
            "after" => $after, //变动后余额
            "type" => $type,
            "mold" => $mold,
            "remark" => $remark,
            "agent_id" => $agent_id,
            "createtime" => time()
        ];
        return $this->insert($insert);
    }
}
