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


class UserActivityPrize extends BaseModel
{
    protected $name = 'user_activity_prize';

    protected function initialize()
    {
        parent::initialize();
        $prefix_config = Config::get('activity.redis_key_prefix')['activity_prize'];
        $this->info_prefix = $prefix_config['info'];
        $this->set_prefix = $prefix_config['set'];
        $this->union_set_prefix = $prefix_config['set'] . 'union:';
    }

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
        print_r($list);
        return $list;
    }

    // public function info($id)
    // {
    //     $info = [];
    //     $info_key = $this->info_prefix . $id;
    //     $detail_info = $this->redisInstance->hGetAll($info_key);
    //     if ($detail_info) {
    //         $info['name'] = $detail_info['name'];
    //         $info['type'] = $detail_info['type'];
    //         $info['image'] = format_image($detail_info['image'], true);
    //         $info['price'] = $detail_info['price'];
    //     }

    //     return $info;
    // }

    public function formatInfo($activity_id, $user_info, $user_task_id, $task_status, $task_info, $prize_info)
    {
        $detail_info = $this->info($activity_id, $user_info, $user_task_id, $task_status, $task_info, $prize_info);
        $info = [];
        if ($detail_info) {
            $info['user_prize_id'] = $detail_info['id'];
            $info['name'] = $detail_info['prize_name'];
            $info['status'] = $detail_info['status'];
            $info['price'] = $detail_info['prize_price'];
            $info['num'] = $detail_info['prize_num'];
        }
        return $info;
    }

    public function info($activity_id, $user_info, $user_task_id, $task_status, $task_info, $prize_info)
    {
        $this->setTableName($user_info['id']);
        $wc['user_id'] = $user_info['id'];
        $wc['user_task_id'] = $user_task_id;
        $wc['prize_id'] = $prize_info['id'];
        //    类型:0=签到(登录),1=充值次数,2=充值金额,3=VIP升级,4=邀请好友,5=VIP保持天数,6=转发,7=完成下单,8=VIP等级达成,9=下级充值总额

        $user_prize_info = $this->where($wc)->find();

        if (!$user_prize_info && $task_status == 2) {
            $data['date'] = date('Y-m-d');
            $data['user_id'] = $user_info['id'];
            $data['level'] = $user_info['level'];
            $data['agent_id'] = $user_info['agent_id'];
            $data['user_task_id'] = $user_task_id;
            $data['activity_id'] = $activity_id;
            $data['task_id'] = $task_info['id'];
            $data['prize_id'] = $prize_info['id'];
            $data['prize_name'] = $prize_info['name'];
            $data['prize_type'] = $prize_info['type'];
            $data['prize_num'] = $prize_info['num'];
            $data['prize_price'] = $prize_info['price'];

            $data['createtime'] = time();
            $data['updatetime'] = time();
            if ($prize_info['expire_seconds']) {
                $data['prize_expiretime'] = time() + $prize_info['expire_seconds'];
            } else {
                $data['prize_expiretime'] = strtotime('+10 years');
            }
            if ($prize_info['type'] == 3) {
                $data['prize_expiretime'] = strtotime(date('Y-m-d') . ' 23:59:59');
            }

            $status = $task_info['is_auto_prize'] ? 1 : 0;
            if (time() > $data['prize_expiretime']) {
                $status = 2;
            }
            $data['status'] = $status;

            $id = $this->insertGetId($data);
            if ($id) {
                $user_prize_info = $this->where(['id' => $id])->find();
                if ($task_info['is_auto_prize'] == 1) {
                    // 类型:0=参与次数,1=折扣券,2=现金,3=实物
                    if ($prize_info['type'] == 2) {
                    } elseif ($prize_info['type'] == 3) {
                    }
                }
            }
        }
        return $user_prize_info;
    }

    public function getUserPrizeInfo($activity_id, $user_info, $user_task_id, $task_status, $task_info, $prize_info)
    {
        try {
            return $this->formatInfo($activity_id, $user_info, $user_task_id, $task_status, $task_info, $prize_info);
        } catch (\Exception $e) {
            $this->createTable($user_info['id']);
            return $this->formatInfo($activity_id, $user_info, $user_task_id, $task_status, $task_info, $prize_info);
        }
    }

    public function doReceive($id, $user_prize_info, $cash_pool_activity = false)
    {
        $update_data['status'] = 1;
        $update_data['updatetime'] = time();
        Db::startTrans();
        try {
            $update_res = $this->where(['id' => $id])->update($update_data);
            if ($update_res && !$cash_pool_activity) {
                $money_res = (new Usermoneylog())->moneyrecords($user_prize_info['user_id'], $user_prize_info['prize_price'], 'inc', 16);
                if (!$money_res) {
                    Db::rollback();
                    return false;
                }
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            print_r($e);
            Db::rollback();
            return false;
        }
    }

    public function getTotalCashPrizeAndCheck($user_id, $activity_id)
    {
        try {
            return $this->getTotalCashPrize($user_id, $activity_id);
        } catch (\Exception $e) {
            $this->createTable($user_id);
            return $this->getTotalCashPrize($user_id, $activity_id);
        }
    }

    public function getTotalCashPrize($user_id, $activity_id)
    {
        $this->setTableName($user_id);
        $wc['activity_id'] = $activity_id;
        $wc['user_id'] = $user_id;
        $wc['status'] = 1;
        return $this->where($wc)->sum('prize_price');
    }

    public function finishTaskAndPrize($user_id, $user_task_id, $activity_id, $task_id, $prize_info)
    {
        $this->setTableName($user_id);
        $user_info = (new User())->where(['id' => $user_id])->find();
        $data['date'] = date('Y-m-d');
        $data['user_id'] = $user_info['id'];
        $data['level'] = $user_info['level'];
        $data['agent_id'] = $user_info['agent_id'];
        $data['user_task_id'] = $user_task_id;
        $data['activity_id'] = $activity_id;
        $data['task_id'] = $task_id;
        $data['prize_id'] = $prize_info['id'];
        $data['prize_name'] = $prize_info['name'];
        $data['prize_type'] = $prize_info['type'];
        $data['prize_num'] = $prize_info['num'];
        $data['prize_price'] = $prize_info['price'];

        $data['createtime'] = time();
        $data['updatetime'] = time();
        if ($prize_info['expire_seconds']) {
            $data['prize_expiretime'] = time() + $prize_info['expire_seconds'];
        } else {
            $data['prize_expiretime'] = strtotime('+10 years');
        }
        if ($prize_info['type'] == 3) {
            $data['prize_expiretime'] = strtotime(date('Y-m-d') . ' 23:59:59');
        }

        // $status = $task_info['is_auto_prize'] ? 1 : 0;
        $status =  0;
        if (time() > $data['prize_expiretime']) {
            $status = 2;
        }
        $data['status'] = $status;

        $id = $this->insertGetId($data);
        return $id;
    }
}
