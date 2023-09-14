<?php

namespace app\admin\controller\sys;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\sys\CheckReport as SysCheckReport;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog;
use app\common\controller\Backend;
use app\common\library\Log;
use think\Log as ThinkLog;

/**
 * 每日验证管理
 *
 * @icon fa fa-circle-o
 */
class CheckReport extends Backend
{

    /**
     * CheckReport模型对象
     * @var \app\admin\model\sys\CheckReport
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\sys\CheckReport;
    }

    public function recal($ids)
    {
        set_time_limit(0);
        $user_id = $this->request->param('user_id', 0);
        $money_model = (new UserMoneyLog());

        if ($user_id) {
            $userInfo = (new User())->where(['id' => $user_id])->find();
            if (!$userInfo) {
                $this->error('no exist');
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
            $s3 = bcadd($row['团购奖励'], $row['下单返回'], 2);
            $s = bcadd($s1, $s2, 2);
            $s = bcadd($s, $s3, 2);
            $s_new = bcadd($s, $row['邀请奖励'], 2);
            $s = bcadd($s, $row['后台增加'], 2);

            $d_new = bcadd($row['提现已到账'], $row['提现待审核'], 2);
            $d_new = bcadd($d_new, $row['下单总额'], 2);
            $d = bcadd($d_new, $row['后台减少'], 2);

            $data['money'] = $money;
            $data['cal_new'] = bcsub($s_new, $d_new, 2);
            $data['cal'] = bcsub($s, $d, 2);
            $data['diff_new'] = bcsub($money, bcsub($s_new, $d_new, 2), 2);

            $exist = (new SysCheckReport())->where(['date' => $data['date'], 'user_id' => $user_id])->find();
            ThinkLog::myLog('getLastSql:', (new SysCheckReport())->getLastSql(), 'check');

            if ($exist) {
                (new SysCheckReport())->where(['id' => $exist['id']])->update($data);
                ThinkLog::myLog('here:', $data, 'check');
            }
            // echo "\n=====begin== 手机号:(" . $userInfo['mobile'] . ') | id:(' . $userInfo['id'] . ') | status:(' . $userInfo['status'] . ')' . "======\n" . '当前余额:(' . $money . ') |' . "\n" . '计算余额:(' . bcsub($s, $d, 2) . ') ' . "\n" . 'info:' . print_r($row, true) . "\n=====end====\n";
            $this->success('ok');
        }
    }

    public function checklog($ids)
    {
        $step = 10;
        set_time_limit(0);
        $user_id = $this->request->param('user_id', 0);
        $money_model = (new UserMoneyLog());

        if ($user_id) {
            $money_model->setTableName($user_id);
            $logNum = $money_model->where(['user_id' => $user_id])->count();
            $loop_time = ceil($logNum / $step);
            $lastLog = [];
            $errorIds = [];
            for ($i = 0; $i < $loop_time; $i++) {
                $offset = $i * $step;
                $logList = $money_model->where(['user_id' => $user_id])->field('id,before,after')->limit($offset, $step)->select();
                foreach ($logList as $logItem) {

                    if (!$lastLog) {
                        $lastLog = $logItem;
                        continue;
                    }
                    // echo 'last:'.$lastLog['id'].'==='.$lastLog['after'].'==='.$lastLog['before']."\n";
                    // echo 'now:'.$logItem['id'].'==='.$logItem['after'].'==='.$logItem['before']."\n";

                    // echo $lastLog['after'].'==='.$logItem['before']."\n";
                    if (bccomp($lastLog['after'], $logItem['before'])) {
                        $errorIds[] = $logItem['id'];
                    }
                    $lastLog = $logItem;
                }
            }
            if (count($errorIds)) {
                $data['diff_log_ids'] = json_encode($errorIds);
                $this->model->where(['id' => $ids])->update($data);
            }
            $this->success('ok');
        }
    }
}
