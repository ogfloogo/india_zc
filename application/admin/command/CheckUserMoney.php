<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\sys\CheckReport;
use app\admin\model\User;
use app\admin\model\user\CommissionLog;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog;
use think\cache\driver\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;

class CheckUserMoney extends Command
{
    protected $model = null;
    protected $tableList = [];
    protected $redisInstance = null;
    protected $key = 'last_user_id';
    protected function configure()
    {
        $this->setName('CheckUserMoney')
            ->setDescription('check money');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        $this->redisInstance = ((new Redis())->handler());
        set_time_limit(0);
        $this->runUserCommission();
    }
    protected function runMoneyLog()
    {
        $mod = 100;
        $tb_prefix = 'fa_user_money_log_';
        $total = 0;
        for ($i = 1; $i <= 10; $i++) {
            $from = $mod * ($i - 1) + 1;
            $to = $mod * $i;
            $user_ids = range($from, $to);
            $userIds = Db::table('fa_user')->where(['id' => ['IN', $user_ids], 'level' => ['GT', 1]])->column('id');
            if ($userIds) {
                $table = $tb_prefix . '1_' . $i;
                $sum = Db::table($table)->where(['createtime' => ['BETWEEN', [1667923200, 1668009599]], 'type' => ['IN', [7, 8]], 'user_id' => ['IN', $userIds]])->sum('money');
                echo Db::getLastSql() . '===' . $sum . "\n";
                $total += floatval($sum);
            }
        }
        echo 'table1:' . $total;
        echo "\n";

        $mod = 1000;

        for ($i = 2; $i <= 233; $i++) {
            $from = $mod * ($i - 1) + 1;
            $to = $mod * $i;
            $user_ids = range($from, $to);
            $userIds = Db::table('fa_user')->where(['id' => ['IN', $user_ids], 'level' => ['GT', 1]])->column('id');
            if ($userIds) {
                $table = $tb_prefix . $i;
                $sum = Db::table($table)->where(['createtime' => ['BETWEEN', [1667923200, 1668009599]], 'type' => ['IN', [7, 8]], 'user_id' => ['IN', $userIds]])->sum('money');
                $total += floatval($sum);
                echo Db::getLastSql() . '===' . $sum . "\n";
            }
        }
        echo 'total:' . $total;
        echo "\n";
    }

    protected function runCommission()
    {
        $tb_prefix = 'fa_commission_log_';
        $total = 0;
        for ($i = 1; $i <= 10; $i++) {
            $table = $tb_prefix . '1_' . $i;
            $sum = Db::table($table)->where(['createtime' => ['BETWEEN', [1667923200, 1668009599]]])->sum('commission');
            echo Db::getLastSql() . '===' . $sum . "\n";
            $total += floatval($sum);
        }
        echo 'table1:' . $total;
        echo "\n";
        for ($i = 2; $i <= 233; $i++) {
            $table = $tb_prefix . $i;
            $sum = Db::table($table)->where(['createtime' => ['BETWEEN', [1667923200, 1668009599]]])->sum('commission');
            $total += floatval($sum);
            echo Db::getLastSql() . '===' . $sum . "\n";
        }
        echo 'total:' . $total;
        echo "\n";
    }

    protected function runTeam()
    {
        $userList = Db::table('fa_tmp_1')->where(['id' => ['GT', 0]])->column('user_id');
        Log::mylog('here1:', Db::getLastSql(), 'chk_sql');
        $step = 1000;
        foreach ($userList as $user_id) {
            $data = [];
            $teamUserIds = Db::table('fa_user_team')->where(['user_id' => $user_id, 'level' => 1])->column('team');
            Log::mylog('here2:', $teamUserIds, 'chk_sql');
            if (count($teamUserIds) > 1000) {
                $team_recharge = 0;
                $team_recharge_num  = 0;
                $num = count($teamUserIds);
                if ($num > $step) {
                    for ($i = 0; $i < ceil($num / $step); $i++) {
                        $orderIds = array_slice($teamUserIds, $i * $step, $step);
                        $team_recharge += Db::table('fa_tmp_2_recharge')->where(['user_id' => ['IN', $orderIds]])->sum('recharge_amount');
                        Log::mylog('here31:', Db::getLastSql(), 'chk_sql');
                        $team_recharge_num += Db::table('fa_tmp_2_recharge')->where(['user_id' => ['IN', $orderIds]])->count();
                        Log::mylog('here41:', Db::getLastSql(), 'chk_sql');
                    }
                    $data['team_recharge'] = $team_recharge;
                    $data['team_recharge_num'] = $team_recharge_num;
                }
            } else {
                $data['team_recharge'] = Db::table('fa_tmp_2_recharge')->where(['user_id' => ['IN', $teamUserIds]])->sum('recharge_amount');
                Log::mylog('here3:', Db::getLastSql(), 'chk_sql');
                $data['team_recharge_num'] = Db::table('fa_tmp_2_recharge')->where(['user_id' => ['IN', $teamUserIds]])->count();
                Log::mylog('here4:', Db::getLastSql(), 'chk_sql');
            }


            Db::table('fa_tmp_1')->where(['user_id' => $user_id])->update($data);
            Log::mylog('here5:', Db::getLastSql(), 'chk_sql');
        }
    }

    protected function runUser()
    {
        $mod = 1000; //用户1000一个表
        $step = 20000; //一次check数量
        $last_user_id = $this->redisInstance->get($this->key);
        $last_user_id = intval($last_user_id);
        $from = $last_user_id + 1;
        $to = $last_user_id + $step;
        $userList = (new User())->where(['id' => ['BETWEEN', [$from, $to]]])->select();
        $moneyLog = (new UserMoneyLog());
        $data['date'] = date('Y-m-d');

        foreach ($userList as $userInfo) {
            $user_id = $userInfo['id'];
            $money = $userInfo['money'];
            $moneyLog->setTableName($user_id);
            $data['user_id'] = $user_id;
            $data['mobile'] = $userInfo['mobile'];
            $data['admin_inc'] = $row['后台增加'] = $moneyLog->where(['user_id' => $user_id, 'type' => 10, 'mold' => 'inc'])->sum('money');
            $data['admin_dec'] = $row['后台减少'] = $moneyLog->where(['user_id' => $user_id, 'type' => 10, 'mold' => 'dec'])->sum('money');

            $data['order_amount'] = $row['下单总额'] = $moneyLog->where(['user_id' => $user_id, 'type' => 5])->sum('money');
            $data['order_back'] = $row['下单返回'] = $moneyLog->where(['user_id' => $user_id, 'type' => 12])->sum('money');
            $data['group'] = $row['团购奖励'] = $moneyLog->where(['user_id' => $user_id, 'type' => 7])->sum('money');
            $data['head'] = $row['团长奖励'] = $moneyLog->where(['user_id' => $user_id, 'type' => 8])->sum('money');
            $data['commission'] = $row['佣金奖励'] = $moneyLog->where(['user_id' => $user_id, 'type' => 4])->sum('money');
            $data['invite'] = $row['邀请奖励'] = (new UserAward())->where(['user_id' => $user_id, 'status' => 1])->sum('moneys');
            $data['new_login'] = $row['新用户奖励'] = $moneyLog->where(['user_id' => $user_id, 'type' => 9])->value('money');
            $data['withdraw_waiting'] = $row['提现待审核'] = (new UserCash())->where(['user_id' => $user_id, 'status' => ['IN', [0, 1, 2]]])->sum('price');
            $data['withdraw'] = $row['提现已到账'] = (new UserCash())->where(['user_id' => $user_id, 'status' => 3])->sum('price');
            $data['recharge'] = $row['充值总额'] = (new UserRecharge())->where(['user_id' => $user_id, 'status' => 1])->sum('price');

            $s1 = bcadd($row['新用户奖励'], $row['充值总额'], 2);
            $s2 = bcadd($row['佣金奖励'], $row['团长奖励'], 2);
            $s3 = bcadd($row['团购奖励'], $row['下单返回'], 2);
            $s = bcadd($s1, $s2, 2);
            $s = bcadd($s, $s3, 2);
            $s_new = bcadd($s, $row['邀请奖励'], 2);
            $s = bcadd($s, $row['后台增加'], 2);

            $d_new = bcadd($row['提现已到账'], $row['提现待审核'], 2);
            $d_new = bcadd($d_new, $row['下单总额'], 2);
            $d = bcadd($d_new, $row['后台减少'], 2);

            $is_not_equal = bccomp($money, bcsub($s_new, $d_new, 2), 2);
            if ($is_not_equal) {
                $data['money'] = $money;
                $data['cal_new'] = bcsub($s_new, $d_new, 2);
                $data['cal'] = bcsub($s, $d, 2);
                $data['diff_new'] = bcsub($money, bcsub($s_new, $d_new, 2), 2);
                $data['diff'] = bcsub($money, bcsub($s, $d, 2), 2);
                $exist = (new CheckReport())->where(['date' => $data['date'], 'user_id' => $user_id])->find();
                if ($exist) {
                    (new CheckReport())->where(['id' => $exist['id']])->update($data);
                } else {
                    (new CheckReport())->insert($data);
                }
                echo "\n=====begin== 手机号:(" . $userInfo['mobile'] . ') | id:(' . $userInfo['id'] . ') | status:(' . $userInfo['status'] . ')' . "======\n" . '当前余额:(' . $money . ') |' . "\n" . '计算余额:(' . bcsub($s, $d, 2) . ') ' . "\n" . 'info:' . print_r($row, true) . "\n=====end====\n";
            }
        }
        $this->redisInstance->set($this->key, $user_id);
    }

    protected function runUserCommission()
    {
        // $mod =  100  ;
        $tb_prefix = 'fa_commission_log_';
        $date_range = [1668009600, 1668095999];
        $total = 0;
        for ($i = 1; $i <= 10; $i++) {

            $table = $tb_prefix . '1_' . $i;

            $sum = Db::table($table)->where(['createtime' => ['BETWEEN', $date_range], 'level' => 3])->sum('commission');
            echo Db::getLastSql() . '===' . $sum . "\n";
            $total += floatval($sum);
        }
        echo 'table1:' . $total;
        echo "\n";

        $mod = 1000;

        for ($i = 2; $i <= 233; $i++) {
            $table = $tb_prefix . $i;
            $sum = Db::table($table)->where(['createtime' => ['BETWEEN', $date_range], 'level' => 3])->sum('commission');
            echo Db::getLastSql() . '===' . $sum . "\n";
            $total += floatval($sum);
        }
        echo 'total:' . $total;
        echo "\n";
    }
}
