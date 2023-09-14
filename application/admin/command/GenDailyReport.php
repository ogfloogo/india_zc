<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\report\DailyReport;
use app\admin\model\sys\IpReport;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserLevelLog;
use app\api\model\Order;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class GenDailyReport extends Command
{
    protected $model = null;
    protected $recharge_amount = 1000;
    protected $withdraw_amount = 500;
    protected $max_user_id = 1000;
    protected function configure()
    {
        $this->setName('GenDailyReport')
            ->setDescription('GenDailyReport');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        $this->max_user_id = (new User())->where(['id' => ['GT', 0]])->max('id');
        set_time_limit(0);
        $from = strtotime('-2 day');
        for ($time = $from; $time < time(); $time = $time + 86400) {
            $date = date('Y-m-d', $time);
            echo $date . "\n";
            $data = $this->orderData($date) + $this->firstRechargeData($date) + $this->rechargeData($date)
                + $this->firstWithdrawData($date) + $this->withdrawData($date)
                + $this->vipData($date) + $this->rewardData($date) + $this->commissionData($date);
            $exist = (new DailyReport())->where(['date' => $date])->value('id');
            if ($exist) {
                $data['updatetime'] = time();
                (new DailyReport())->where(['id' => $exist])->update($data);
            } else {
                $data['date'] = $date;
                $data['createtime'] = time();
                $data['updatetime'] = time();
                (new DailyReport())->insertGetId($data);
            }
        }
    }

    protected function orderData($date)
    {
        $data['order_num'] = (new Order())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]]])->count();
        return $data;
    }

    protected function firstRechargeData($date)
    {
        $first_recharge_user_ids = [];
        $user_ids = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]]])->distinct(true)->column('user_id');
        foreach ($user_ids as $user_id) {
            $id = (new UserRecharge())->where(['paytime' => ['LT', strtotime($date . ' 00:00:00')], 'user_id' => $user_id, 'status' => 1])->value('id');
            // echo $user_id . '====' . $id . "\n";
            if ($id) {
                continue;
            }
            if (!in_array($user_id, $first_recharge_user_ids)) {
                $first_recharge_user_ids[] = $user_id;
            }
        }
        $data['first_recharge_num'] = count($first_recharge_user_ids);
        $data['first_recharge_amount'] = count($first_recharge_user_ids) ? (new UserRecharge())->where(['user_id' => ['IN', $first_recharge_user_ids], 'paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]]])->sum('price') : 0;
        return $data;
    }

    protected function inviteFirstRechargeData($date)
    {
        $data['invite_first_recharge_num'] = (new UserAward())->where(['recharge_time' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]]])->count();
        $data['invite_first_recharge_amount'] = (new UserAward())->where(['recharge_time' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]]])->sum('price');
        return $data;
    }

    protected function rechargeData($date)
    {
        $data['recharge_num_less_than'] = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'price' => ['LT', $this->recharge_amount]])->count();
        $data['recharge_num_amount_less_than'] = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'price' => ['LT', $this->recharge_amount]])->sum('price');
        return $data;
    }

    protected function firstWithdrawData($date)
    {
        $num = 0;
        $amount = 0;
        $list = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'status' => ['LT', 4]])->column('user_id');
        foreach ($list as $user_id) {
            $exist = (new UserCash())->where(['user_id' => $user_id, 'createtime' => ['LT', strtotime($date . ' 00:00:00')], 'status' => ['LT', 4]])->find();
            if ($exist) {
                continue;
            }
            $num++;
            $amount += (new UserCash())->where(['user_id' => $user_id, 'createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'status' => ['LT', 4]])->value('price');
        }
        $data['first_withdraw_num'] = $num;
        $data['first_withdraw_amount'] = $amount;
        return $data;
    }

    protected function withdrawData($date)
    {
        $data['withdraw_num_less_than'] = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'price' => ['LT', $this->withdraw_amount], 'status' => ['LT', 4]])->count();
        $data['withdraw_num_amount_less_than'] = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'price' => ['LT', $this->withdraw_amount], 'status' => ['LT', 4]])->sum('price');
        return $data;
    }

    protected function vipData($date)
    {
        $up_user_ids = (new UserLevelLog())->where(['date' => $date, 'up' => 1])->distinct(true)->column('user_id');
        $data['level_up_num'] = count($up_user_ids);
        $data['level_up_recharge_amount'] = (new UserRecharge())->where(['user_id' => ['IN', $up_user_ids], 'paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'status' => 1])->sum('price');

        $down_user_ids = (new UserLevelLog())->where(['date' => $date, 'up' => 0])->distinct(true)->column('user_id');
        $data['level_down_num'] = count($down_user_ids);
        $data['level_down_withdraw_amount'] = (new UserCash())->where(['user_id' => ['IN', $down_user_ids], 'createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'status' => ['LT', 4]])->sum('price');
        return $data;
    }

    protected function rewardData($date)
    {
        $mod = 100;
        $tb_prefix = 'fa_user_money_log_';
        $total = 0;
        $wc['createtime'] = ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]];
        $wc['type'] = ['IN', [7, 8]];
        for ($i = 1; $i <= 10; $i++) {
            $from = $mod * ($i - 1) + 1;
            if ($from > $this->max_user_id) {
                break;
            }
            $to = $mod * $i;
            $user_ids = range($from, $to);
            $table = $tb_prefix . '1_' . $i;
            $sum = Db::table($table)->where($wc)->sum('money');
            $total += floatval($sum);
        }

        $mod = 1000;
        for ($i = 2; $i <= ceil($this->max_user_id / $mod); $i++) {
            $from = $mod * ($i - 1) + 1;
            $to = $mod * $i;
            $user_ids = range($from, $to);
            $table = $tb_prefix . $i;
            $sum = Db::table($table)->where($wc)->sum('money');
            $total += floatval($sum);
        }
        $data['order_award_amount'] = $total;
        return $data;
    }

    protected function commissionData($date)
    {
        $data['level_1_commission'] = $this->commissionLevel($date, 1);
        $data['level_2_commission'] = $this->commissionLevel($date, 2);
        $data['level_3_commission'] = $this->commissionLevel($date, 3);
        return $data;
    }

    protected function commissionLevel($date, $level)
    {
        $wc['createtime'] = ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]];
        $wc['level'] = $level;

        $mod = 100;
        $tb_prefix = 'fa_commission_log_';
        $total = 0;

        for ($i = 1; $i <= 10; $i++) {
            $from = $mod * ($i - 1) + 1;
            if ($from > $this->max_user_id) {
                break;
            }
            $to = $mod * $i;
            $table = $tb_prefix . '1_' . $i;
            $sum = Db::table($table)->where($wc)->sum('commission');
            $total += floatval($sum);
        }

        $mod = 1000;
        for ($i = 2; $i <= ceil($this->max_user_id / $mod); $i++) {
            $from = $mod * ($i - 1) + 1;
            $to = $mod * $i;
            $table = $tb_prefix . $i;
            $sum = Db::table($table)->where($wc)->sum('commission');
            $total += floatval($sum);
        }
        return $total;
    }
}
