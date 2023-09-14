<?php

namespace app\api\model\activity;

use app\api\model\activity\BaseModel;
use app\api\model\Financeorder;
use app\api\model\Level;
use app\api\model\Order;
use app\api\model\User;
use app\api\model\Useraward;
use app\api\model\UserLevelLog;
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


class UserActivityTask extends BaseModel
{
    protected $name = 'user_activity_task';


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

    public function formatInfo($activity_id, $user_info, $task_info)
    {
        $detail_info = $this->info($activity_id, $user_info, $task_info);
        $info = [];

        if ($detail_info) {
            $info['id'] = $task_info['id'];
            $info['task_name'] = $task_info['name'];
            $level = $task_info['level'] ? $task_info['level'] : $user_info['level'];
            $level_info = (new Level())->mylevel_commission_rates($level);
            $info['image'] = format_image($level_info['icon_image']);
            $info['image'] = format_image($task_info['image']);
            $info['user_task_id'] = $detail_info['id'];
            $info['task_type'] = $detail_info['task_type'];
            $info['status'] = $detail_info['status'];
            $info['task_url'] = $task_info['url'];

            $info['target_num'] = $detail_info['target_num'];
            $info['finish_num'] = $detail_info['finish_num'];
            if ($info['task_type'] == 8) { //be vip
                $info['target_num'] = 1;
                $info['finish_num'] = $info['status'] == 2 ? 1 : 0;
            }
        }
        return $info;
    }

    public function info($activity_id, $user_info, $task_info)
    {
        $this->setTableName($user_info['id']);
        if ($task_info['date_type']) {
            $wc['date'] = date('Y-m-d');
        }
        $wc['user_id'] = $user_info['id'];
        $wc['activity_id'] = $activity_id;
        $wc['task_id'] = $task_info['id'];
        //    类型:0=签到(登录),1=充值次数,2=充值金额,3=VIP升级,4=邀请好友,5=VIP保持天数,6=转发,7=完成下单,8=VIP等级达成,9=下级充值总额,10=购买理财次数

        $user_task_info = $this->where($wc)->find();

        if (!$user_task_info) {
            $data['date'] = date('Y-m-d');
            $data['user_id'] = $user_info['id'];
            $data['level'] = $user_info['level'];
            $data['agent_id'] = $user_info['agent_id'];
            $data['activity_id'] = $activity_id;
            $data['task_id'] = $task_info['id'];
            $data['task_name'] = $task_info['name'];
            $data['date_type'] = $task_info['date_type'];
            $data['task_type'] = $task_info['type'];
            $data['target_num'] = $task_info['num'];

            $data['status'] = $task_info['is_auto_get'] ? 1 : 0;
            $data['createtime'] = time();
            $data['updatetime'] = time();
            if ($task_info['date_type'] == 1) {
                $data['begintime'] = strtotime(date('Y-m-d') . ' 00:00:00');
                $data['endtime'] = strtotime(date('Y-m-d') . ' 23:59:59');
            } else {
                $data['begintime'] = 0;
                $data['endtime'] = strtotime('+10 years');
            }
            if ($task_info['type'] == 5) {
                $data['begintime'] = time();
                $data['endtime'] = strtotime('+' . $task_info['num'] . ' days');
            }
            $data['finish_num'] = $this->getFinishNum($task_info['type'], $user_info['id'], $task_info['date_type'], $user_info['level'], $data['begintime'], $data['endtime'], $task_info['num']);
            Log::mylog('test:', $data['finish_num'] . '===' . $task_info['type'] . '===' . $user_info['id'] . '===' . $task_info['date_type'] . '===' . $user_info['level'] . '===' . $data['begintime'] . '===' . $data['endtime'] . '===' . $task_info['num'], 'acti');
            $id = $this->insertGetId($data);
            if ($id) {
                $user_task_info = $this->where(['id' => $id])->find();
            }
        }
        if ($user_task_info['status'] < 2) {
            $finish_num = $this->getFinishNum($task_info['type'], $user_info['id'], $task_info['date_type'], $user_info['level'], $user_task_info['begintime'], $user_task_info['endtime'], $task_info['num']);
            $this->finishTaskAndPrize($finish_num, $user_task_info);
        }
        $user_task_info = $this->where(['id' => $user_task_info['id']])->find();
        return $user_task_info;
    }

    public function getUserTaskInfo($activity_id, $user_info, $task_info)
    {
        try {
            return $this->formatInfo($activity_id, $user_info, $task_info);
        } catch (\Exception $e) {
            $this->createTable($user_info['id']);
            return $this->formatInfo($activity_id, $user_info, $task_info);
        }
    }


    public function updateTaskResult($task_type, $user_id)
    {
        $this->setTableName($user_id);
        $list = $this->where(['task_type' => $task_type, 'user_id' => $user_id, 'status' => ['LT', 2]])->select();
        foreach ($list as $item) {
            if ($item['date_type']) {
                if ($item['date'] != date('Y-m-d')) {
                    continue;
                }
            }
            $finish_num = $this->getFinishNum($task_type, $user_id, $item['date_type'], $item['level'], $item['begintime'], $item['endtime'], $item['target_num']);
            return $this->finishTaskAndPrize($finish_num, $item);
        }
    }

    public function finishTaskAndPrize($finish_num, $item)
    {
        if ($finish_num >= $item['target_num']) {
            $data = [];
            $data['finish_num'] = $finish_num;
            $data['status'] = 2;
            $data['updatetime'] = time();
            $prize_info = $this->getTaskPrizeInfo($item['activity_id'], $item['task_id'], 0);
            Db::startTrans();
            try {
                $res = $this->where(['id' => $item['id']])->update($data); //修改状态
                //增加奖品记录
                $user_prize_id = (new UserActivityPrize())->finishTaskAndPrize($item['user_id'], $item['id'], $item['activity_id'], $item['task_id'], $prize_info);
                if ($res && $user_prize_id) {
                    Db::commit();
                    return true;
                }
                Db::rollback();
                return false;
            } catch (\Exception $e) {
                Log::mylog('finish:', $e->getMessage(), 'task');
                Db::rollback();
                // throw $e->getMessage();
                return false;
            }
        } else {
            if ($item['finish_num'] != $finish_num) {
                $data = [];
                $data['finish_num'] = $finish_num;
                $data['status'] = 1;
                $data['updatetime'] = time();
                $res = $this->where(['id' => $item['id']])->update($data); //修改状态
                return $res;
            }
            return true;
        }
    }

    public function getFinishNum($task_type, $user_id, $date_type, $user_level, $begintime, $endtime, $task_num)
    {
        //    类型:0=签到(登录),1=充值次数,2=充值金额,3=VIP升级,4=邀请好友,5=VIP保持天数,6=转发,7=完成下单,8=VIP等级达成,9=下级充值总额,10=购买理财次数

        switch ($task_type) {
            case 0:
                $is_login = (new Usertotal())->getLogin($user_id);
                if ($is_login) {
                    return 1;
                }
                return 0;
                break;
            case 1:
                $wc = [];
                if ($date_type == 1) {
                    $wc['updatetime'] = ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]];
                }
                $wc['user_id'] = $user_id;
                $wc['status'] = 1;
                $num = (new Userrecharge())->where($wc)->count();
                if ($num >= $task_num) {
                    return $task_num;
                }
                return intval($num);
                break;
            case 2:
                $wc = [];
                if ($date_type == 1) {
                    $wc['updatetime'] = ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]];
                }
                $wc['user_id'] = $user_id;
                $wc['status'] = 1;
                $total =  (new Userrecharge())->where($wc)->sum('price');
                if (bccomp($total, $task_num) >= (-1)) {
                    return $task_num;
                }
                return $total;
                break;
            case 3:
                $wc = [];
                if ($date_type == 1) {
                    $wc['date'] = date('Y-m-d');
                }
                $wc['user_id'] = $user_id;
                $wc['old_level'] = $user_level;
                $wc['up'] = 1;
                $num = (new UserLevelLog())->where($wc)->count();
                if (intval($num) >= $task_num) {
                    return $task_num;
                } else {
                    return intval($num);
                }
                break;
            case 4:
                $wc = [];
                if ($date_type == 1) {
                    $wc['createtime'] = ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]];
                }
                $wc['sid'] = $user_id;
                $num  = (new User())->where($wc)->count();
                if (intval($num) >= $task_num) {
                    return $task_num;
                } else {
                    return intval($num);
                }
                break;
            case 5:
                $wc = [];
                $wc['createtime'] = ['between', [$begintime, $endtime]];
                $wc['user_id'] = $user_id;
                $wc['old_level'] = $user_level;
                $wc['up'] = 0;
                $num = (new UserLevelLog())->where($wc)->count();
                if (intval($num)) {
                    return 0;
                }
                if (time() >= $endtime) {
                    return $task_num;
                } else {
                    return floor((time() - $begintime) / 86400);
                }
                break;
            case 6:
                return 0;
            case 7:
                $wc = [];
                $wc['createtime'] = ['between', [$begintime, $endtime]];
                $wc['user_id'] = $user_id;
                $num = (new Order())->where($wc)->count();
                if (intval($num) >= $task_num) {
                    return $task_num;
                } else {
                    return intval($num);
                }
            case 8:
                $wc = [];
                $wc['id'] = $user_id;
                $level = (new User())->where($wc)->value('level');
                if ($level >= $task_num) {
                    return $task_num;
                }
                return 0;
            case 9:
                $wc = [];
                $wc['sid'] = $user_id;
                $userIds = (new User())->where($wc)->column('id');
                $total_recharge = (new Userrecharge())->where(['user_id' => ['IN', $userIds, 'status' => 1]])->sum('price');
                if (bccomp($total_recharge, $task_num) > (-1)) {
                    return $task_num;
                } else {
                    return number_format($total_recharge, 2, '.', '');
                }
            case 10:
                $wc = [];
                $wc['user_id'] = $user_id;
                $wc['status'] = ['GT', 0];
                $wc['is_robot'] = 0;
                $num = (new Financeorder())->where($wc)->count();
                if (intval($num) >= $task_num) {
                    return $task_num;
                } else {
                    return intval($num);
                }
            default:
                return 0;
                break;
        }
    }


    public function getTaskPrizeInfo($activity_id, $task_id, $level = 0)
    {
        //2:lucky_draw,3:vip_activity,4:cash_activity
        switch ($activity_id) {
            case 2:
                return (new LuckyDrawTask())->getPrizeInfo($task_id, $level);
                break;
            case 3:
                return (new VipActivity())->getPrizeInfo($task_id, $level);
                break;
            case 4:
                return (new CashActivity())->getPrizeInfo($task_id, $level);
                break;
            default:
                # code...
                break;
        }
    }
}
