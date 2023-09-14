<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\financebuy\FinanceOrder;
use app\admin\model\sys\CheckReport;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog;
use app\api\model\Usermoneylog as ModelUsermoneylog;
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

class RecalUserMoney extends Command
{
    protected $model = null;
    protected $tableList = [];
    protected $redisInstance = null;
    protected $key = 'last_recal_user_id';
    protected function configure()
    {
        $this->setName('RecalUserMoney')
            ->setDescription('RecalUserMoney');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        // $this->redisInstance = ((new Redis())->handler());
        // set_time_limit(0);
        // try {
        //     $this->runUser();
        // } catch (\Throwable $e) {
        //     Log::myLog('error:', $e, 'check');
        // }
        $this->check();
    }

    protected function check()
    {
        $issue_id = 6;
        $userIds = (new FinanceOrder())->where(['is_robot' => 0, 'issue_id' => $issue_id])->distinct(true)->column('user_id');
        foreach ($userIds as  $user_id) {
            // if ($user_id == 19906) {
            //     continue;
            // }
            $money = (new User())->where(['id' => $user_id])->value('money');
            $licai_model = (new ModelUsermoneylog());
            $licai_model->settables($user_id);
            $count = $licai_model->where(['user_id' => $user_id, 'type' => 19])->count();
            $first_licai = $licai_model->where(['user_id' => $user_id, 'type' => 19])->order('createtime asc')->find();

            $total_licai = $licai_model->where(['user_id' => $user_id, 'type' => 19])->sum('money');

            $last_log = $licai_model->where(['user_id' => $user_id])->order('id DESC')->find();
            if (!$count) {
                continue;
            }
            echo 'user_id:' . $user_id .
                "\tmoney:" . $money . "\t count:" .  $count . "\t sub all+1: " . bcsub($money, bcsub($total_licai, $first_licai['money'], 2), 2) . "\t sub all: " . bcsub($money, $total_licai, 2);
            if ($first_licai) {
                echo "\tbefore:" . $first_licai['before'] . '===' . $first_licai['after']. '===' . $first_licai['money'] . '====total:' . $total_licai;
            }
            // if ($last_log) {
            //     echo "\tbefore:" . $last_log['before'] . '===' . $last_log['after'] . '===type:' . $last_log['type'];
            // }
            echo "\n";

            // $wc = [];
            // $wc['user_id'] = $user_id;
            // $wc['type'] = 19;
            // $wc['id'] = ['gt', $first_licai['id']];
            // $licai_model->where($wc)->delete();
            // echo (new User())->getLastSql() . "\n";
            // break;
            // $wc=[];
            // $wc['id'] = $user_id;
            // (new User())->where($wc)->update(['money'=>bcsub($money, bcsub($total_licai, $first_licai['money'], 2), 2)]);
            // echo (new User())->getLastSql()."\n";            
        }
    }

    protected function runUser()
    {
        $step = 20000; //一次check数量
        $last_user_id = $this->redisInstance->get($this->key);
        $last_user_id = intval($last_user_id);
        $from = $last_user_id + 1;
        $to = $last_user_id + $step;
        $reportList = (new CheckReport())->where(['id' => ['BETWEEN', [$from, $to]]])->select();
        $money_model = (new UserMoneyLog());

        foreach ($reportList as $item) {
            $user_id = $item['user_id'];
            if ($user_id) {
                $userInfo = (new User())->where(['id' => $user_id])->find();
                if (!$userInfo) {
                    continue;
                }
                $money_model->setTableName($user_id);

                $data['date'] = date('Y-m-d');

                $user_id = $userInfo['id'];
                $money = $userInfo['money'];
                $data['user_id'] = $user_id;
                $data['mobile'] = $userInfo['mobile'];
                $data['admin_inc'] = $row['后台增加'] = $money_model->where(['user_id' => $user_id, 'type' => 10, 'mold' => 'inc'])->sum('money');
                $data['admin_dec'] = $row['后台减少'] = $money_model->where(['user_id' => $user_id, 'type' => 10, 'mold' => 'dec'])->sum('money');

                $data['group'] = $row['团购奖励'] = $money_model->where(['user_id' => $user_id, 'type' => 7])->sum('money');
                $data['head'] = $row['团长奖励'] = $money_model->where(['user_id' => $user_id, 'type' => 8])->sum('money');
                $data['commission'] = $row['佣金奖励'] = $money_model->where(['user_id' => $user_id, 'type' => 4])->sum('money');
                $data['invite'] = $row['邀请奖励'] = (new UserAward())->where(['user_id' => $user_id, 'status' => 1])->sum('moneys');
                $data['new_login'] = $row['新用户奖励'] = $money_model->where(['user_id' => $user_id, 'type' => 9])->value('money');
                $data['withdraw_waiting'] = $row['提现待审核'] = (new UserCash())->where(['user_id' => $user_id, 'status' => ['IN', [0, 1, 2]]])->sum('price');
                $data['withdraw'] = $row['提现已到账'] = (new UserCash())->where(['user_id' => $user_id, 'status' => 3])->sum('price');
                $data['recharge'] = $row['充值总额'] = (new UserRecharge())->where(['user_id' => $user_id, 'status' => 1])->sum('price');

                $s1 = bcadd($row['新用户奖励'], $row['充值总额'], 2);
                $s2 = bcadd($row['佣金奖励'], $row['团长奖励'], 2);
                $s3 = bcadd($row['团购奖励'], $row['后台增加'], 2);
                $s = bcadd($s1, $s2, 2);
                $s = bcadd($s, $s3, 2);
                $s = bcadd($s, $row['邀请奖励'], 2);

                $d1 = bcadd($row['提现已到账'], $row['提现待审核'], 2);
                $d = bcadd($d1, $row['后台减少'], 2);

                $data['money'] = $money;
                $data['cal'] = bcsub($s, $d, 2);
                $data['diff'] = bcsub($money, bcsub($s, $d, 2), 2);
                $exist = (new CheckReport())->where(['date' => $data['date'], 'user_id' => $user_id])->find();

                if ($exist) {
                    (new CheckReport())->where(['id' => $exist['id']])->update($data);
                }
            }
        }
        $this->redisInstance->set($this->key, $user_id);
        (new CheckReport())->where(['id' => ['BETWEEN', [$from, $to]], 'diff' => 0])->delete();
    }
}
