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


class UserLuckyDrawMoney extends BaseModel
{
    protected $name = 'user_lucky_draw_money';

    public function list($id)
    {
        $common_set_key = $this->set_prefix . '0';
        $level_set_key = $this->set_prefix . $id;
        $set_num = $this->redisInstance->zunionstore($this->union_set_prefix . $id, [$level_set_key, $common_set_key]);
        $list = [];
        if ($set_num) {
            $level_list = $this->redisInstance->zRevRange($this->union_set_prefix . $id, 0, -1, true);
            foreach ($level_list as $a_id => $seq) {
                // $item = $this->info($a_id);
                // $list[] = $item;
            }
        }
        return $list;
    }


    public function getWithdrawMoney($user_id)
    {
        $this->setTableName($user_id);
        $wc['date'] = date('Y-m-d');
        $wc['user_id'] = $user_id;
        return floatval($this->where($wc)->sum('amount'));
    }


    public function getUserWithdrawMoney($user_id)
    {
        try {
            return $this->getWithdrawMoney($user_id);
        } catch (\Exception $e) {
            $this->createTable($user_id);
            return $this->getWithdrawMoney($user_id);
        }
    }

    public function doReceive($user_id, $user_info, $amount)
    {
        $data['date'] = date('Y-m-d');
        $data['user_id'] = $user_id;
        $data['level'] = $user_info['level'];
        $data['agent_id'] = $user_info['agent_id'];
        $data['amount'] = $amount;
        $data['activity_id'] = (new LuckyDraw())->getActivityId();
        $data['status'] = 0;
        $data['createtime'] = time();
        $data['updatetime'] = time();


        Db::startTrans();
        try {
            $id = $this->insertGetId($data);
            if ($id) {
                $money_res = (new Usermoneylog())->moneyrecords($user_id, $amount, 'inc', 18);
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
}
