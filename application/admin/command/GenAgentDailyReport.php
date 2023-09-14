<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\order\Order;
use app\admin\model\report\AgentDailyReport;
use app\admin\model\report\DailyReport;
use app\admin\model\sys\Agent;
use app\admin\model\sys\IpReport;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserLevelLog;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class GenAgentDailyReport extends Command
{
    protected $model = null;
    protected $recharge_amount = 1000;
    protected $withdraw_amount = 500;
    protected $max_user_id = 1000;
    protected function configure()
    {
        $this->setName('GenAgentDailyReport')
            ->setDescription('GenAgentDailyReport');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        $list = (new Agent())->where(['id' => ['GT', 0]])->column('id');
        foreach ($list as $id) {
            $this->buildAgentDailyReport($id);
        }
    }

    protected function buildAgentDailyReport($agent_id)
    {
        if (!$agent_id) {
            return false;
        }
        $this->max_user_id = (new User())->where(['id' => ['GT', 0], 'agent_id' => $agent_id])->max('id');
        $from = strtotime('-2 day');
        for ($time = $from; $time < time(); $time = $time + 86400) {
            $date = date('Y-m-d', $time);
            echo $date . "\n";
            $data = $this->firstRechargeData($date, $agent_id) + $this->rechargeData($date, $agent_id)
                + $this->firstWithdrawData($date, $agent_id) + $this->withdrawData($date, $agent_id)
                + $this->vipData($date, $agent_id) + $this->rewardData($date, $agent_id) + $this->commissionData($date, $agent_id)
                + $this->orderData($date, $agent_id) + $this->userData($date, $agent_id);
            $exist = (new AgentDailyReport())->where(['date' => $date, 'agent_id' => $agent_id])->value('id');
            if ($exist) {
                $data['updatetime'] = time();
                (new AgentDailyReport())->where(['id' => $exist])->update($data);
            } else {
                $data['agent_id'] = $agent_id;
                $data['date'] = $date;
                $data['createtime'] = time();
                $data['updatetime'] = time();
                (new AgentDailyReport())->insertGetId($data);
            }
        }
    }

    protected function firstRechargeData($date, $agent_id)
    {
        $first_recharge_user_ids = [];
        $user_ids = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->distinct(true)->column('user_id');
        foreach ($user_ids as $user_id) {
            $id = (new UserRecharge())->where(['paytime' => ['LT', strtotime($date . ' 00:00:00')], 'user_id' => $user_id, 'agent_id' => $agent_id, 'status' => 1])->value('id');
            if ($id) {
                continue;
            }
            if (!in_array($user_id, $first_recharge_user_ids)) {
                $first_recharge_user_ids[] = $user_id;
            }
        }
        $data['first_recharge_num'] = count($first_recharge_user_ids);
        $data['first_recharge_amount'] = count($first_recharge_user_ids) ? (new UserRecharge())->where(['user_id' => ['IN', $first_recharge_user_ids], 'paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->sum('price') : 0;
        return $data;
    }


    protected function inviteFirstRechargeData($date, $agent_id)
    {
        $data['invite_first_recharge_num'] = (new UserAward())->where(['recharge_time' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->count();
        $data['invite_first_recharge_amount'] = (new UserAward())->where(['recharge_time' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->sum('price');
        return $data;
    }

    protected function rechargeData($date, $agent_id)
    {
        $data['recharge_num_less_than'] = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'price' => ['LT', $this->recharge_amount], 'agent_id' => $agent_id])->count();
        $data['recharge_num_amount_less_than'] = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'price' => ['LT', $this->recharge_amount], 'agent_id' => $agent_id])->sum('price');
        $data['recharge_user_num'] = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->distinct(true)->field('user_id')->count();
        $data['recharge_order_num'] = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->count();
        $data['recharge_amount'] = (new UserRecharge())->where(['paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->sum('price');

        return $data;
    }

    protected function firstWithdrawData($date, $agent_id)
    {
        $num = 0;
        $amount = 0;
        $list = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id, 'status' => ['LT', 4]])->column('user_id');
        foreach ($list as $user_id) {
            $exist = (new UserCash())->where(['user_id' => $user_id, 'createtime' => ['LT', strtotime($date . ' 00:00:00')], 'agent_id' => $agent_id, 'status' => ['LT', 4]])->find();
            if ($exist) {
                continue;
            }
            $num++;
            $amount += (new UserCash())->where(['user_id' => $user_id, 'agent_id' => $agent_id, 'createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'status' => ['LT', 4]])->value('price');
        }
        $data['first_withdraw_num'] = $num;
        $data['first_withdraw_amount'] = $amount;
        return $data;
    }

    protected function withdrawData($date, $agent_id)
    {
        $data['withdraw_num_less_than'] = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id, 'price' => ['LT', $this->withdraw_amount], 'status' => ['LT', 4]])->count();
        $data['withdraw_num_amount_less_than'] = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id, 'price' => ['LT', $this->withdraw_amount], 'status' => ['LT', 4]])->sum('price');
        $data['withdraw_user_num'] = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id, 'status' => ['LT', 4]])->distinct(true)->field('user_id')->count();
        $data['withdraw_order_num'] = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id, 'status' => ['LT', 4]])->count();
        $data['withdraw_amount'] = (new UserCash())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id, 'status' => ['LT', 4]])->sum('price');
        return $data;
    }

    protected function vipData($date, $agent_id)
    {
        $up_user_ids = (new UserLevelLog())->where(['date' => $date, 'up' => 1, 'agent_id' => $agent_id])->distinct(true)->column('user_id');
        $data['level_up_num'] = count($up_user_ids);
        $data['level_up_recharge_amount'] = 0;
        if (count($up_user_ids)) {
            $data['level_up_recharge_amount'] = (new UserRecharge())->where(['user_id' => ['IN', $up_user_ids], 'agent_id' => $agent_id, 'paytime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'status' => 1])->sum('price');
        }

        $down_user_ids = (new UserLevelLog())->where(['date' => $date, 'up' => 0, 'agent_id' => $agent_id])->distinct(true)->column('user_id');
        $data['level_down_num'] = count($down_user_ids);
        $data['level_down_withdraw_amount'] = count($down_user_ids);
        if (count($down_user_ids)) {
            $data['level_down_withdraw_amount'] = (new UserCash())->where(['user_id' => ['IN', $down_user_ids], 'agent_id' => $agent_id, 'createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'status' => ['LT', 4]])->sum('price');
        }
        return $data;
    }

    protected function orderData($date, $agent_id)
    {
        $data['order_num'] = (new Order())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id, 'pay_status' => 1])->count();
        $data['order_amount'] = (new Order())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id,  'pay_status' => 1])->sum('amount');
        return $data;
    }

    protected function userData($date, $agent_id)
    {
        $data['register_num'] = (new User())->where(['createtime' => ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]], 'agent_id' => $agent_id])->count();
        return $data;
    }

    protected function rewardData($date, $agent_id)
    {
        $mod = 100;
        $tb_prefix = 'fa_user_money_log_';
        $total = 0;
        $wc['createtime'] = ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]];
        $wc['type'] = ['IN', [7, 8]];
        $wc['agent_id'] = $agent_id;
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

    protected function commissionData($date, $agent_id)
    {
        $data['level_1_commission'] = $this->commissionLevel($date, 1, $agent_id);
        $data['level_2_commission'] = $this->commissionLevel($date, 2, $agent_id);
        $data['level_3_commission'] = $this->commissionLevel($date, 3, $agent_id);
        return $data;
    }

    protected function commissionLevel($date, $level, $agent_id)
    {
        $wc['createtime'] = ['BETWEEN', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]];
        $wc['level'] = $level;
        $wc['agent_id'] = $agent_id;

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
