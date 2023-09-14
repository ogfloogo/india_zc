<?php

namespace app\api\model\activity;

use app\api\model\activity\BaseModel;
use app\api\model\User;
use app\api\model\Useraward;
use app\api\model\UserLevelLog;
use app\api\model\Usermoneylog;
use app\api\model\Userrecharge;
use app\api\model\Usertotal;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\helper\Time;
use think\Log;


class UserActivityMoneyLog extends BaseModel
{
    protected $name = 'user_activity_money_log';

    protected function initialize()
    {
        parent::initialize();
        $prefix_config = Config::get('activity.redis_key_prefix')['cash_activity'];
        $this->info_prefix = $prefix_config['info'];
        $this->set_prefix = $prefix_config['set'];
        $this->union_set_prefix = $prefix_config['set'] . 'union:';
    }

    public function getLogInfo($user_id, $activity_id)
    {
        $wc['activity_id'] = $activity_id;
        $wc['user_id'] = $user_id;
        $logInfo = $this->where($wc)->find();
        return $logInfo;
    }

    public function getCashInfo($user_id, $activity_id, $cash_limit)
    {
        $total_cash = (new UserActivityPrize())->getTotalCashPrizeAndCheck($user_id, $activity_id);
        $logInfo = $this->getLogInfo($user_id, $activity_id);
        if (empty($logInfo)) { //还没有领取记录，做判断是否可以领取
            if ($cash_limit && bccomp($total_cash, $cash_limit) > (-1)) {
                $res['receivable'] = 1;
                $res['my_cash'] = $cash_limit;
            } else {
                $res['receivable'] = 0;
                $res['my_cash'] = $total_cash;
            }
        } else {
            if (bccomp($logInfo['amount'], $cash_limit) > (-1)) {
                if ($logInfo['status']) { //已领过或者已过期 
                    $res['receivable'] = 2; //不可领取
                } else {
                    $res['receivable'] = 1; //未领取
                }
                $res['my_cash'] = $cash_limit;
            } else {
                $res['receivable'] = 0;
                $res['my_cash'] = $cash_limit;
            }
        }
        return $res;
    }


    public function doReceiveCash($user_id, $user_info, $activity_id, $amount)
    {
        $data = $this->buildData($user_id, $user_info, $activity_id, $amount);
        Db::startTrans();
        try {
            $id = $this->insertGetId($data);
            if ($id) {
                $money_res = (new Usermoneylog())->moneyrecords($user_id, $amount, 'inc', 21);
                if ($money_res) {
                    $update_data['status'] = 1;
                    $update_data['updatetime'] = time();
                    $res = $this->where(['id' => $id])->update($update_data);
                    if ($res) {
                        Db::commit();
                        return true;
                    }
                }
                Db::rollback();
                return false;
            }
        } catch (\Exception $e) {
            print_r($e);
            Db::rollback();
            return false;
        }
    }

    public function buildData($user_id, $user_info, $activity_id, $amount)
    {
        $data['date'] = date('Y-m-d');
        $data['user_id'] = $user_id;
        $data['level'] = $user_info['level'];
        $data['activity_id'] = $activity_id;
        $data['amount'] = $amount;
        $data['remark'] = '';
        $data['status'] = 0;
        $data['createtime'] = time();
        $data['updatetime'] = time();
        $data['agent_id'] =  $user_info['agent_id'];
        return $data;
    }
}
