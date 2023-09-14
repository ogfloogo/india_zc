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


class UserDrawLog extends BaseModel
{
    protected $name = 'user_draw_log';

    protected function initialize()
    {
        parent::initialize();
        $prefix_config = Config::get('activity.redis_key_prefix')['lucky_draw_activity'];
        $this->info_prefix = $prefix_config['info'];
        $this->set_prefix = $prefix_config['set'];
        $this->union_set_prefix = $prefix_config['set'] . 'union:';
    }

    public function getDrawInfo($user_id, $user_info)
    {
        $info['num'] = $this->getDailyDrawNum($user_id, $user_info);
        $info['today_prize_cash'] = $this->getTodayPrizeCash($user_id);
        return $info;
    }

    public function getMyDrawInfo($user_id, $user_info)
    {
        try {
            return $this->getDrawInfo($user_id, $user_info);
        } catch (\Exception $e) {
            $this->createTable($user_info['id']);
            return $this->getDrawInfo($user_id, $user_info);
        }
    }

    //总抽奖次数
    public function getDailyDrawNum($user_id, $user_info)
    {
        $config = (new LuckyDraw())->config();
        $init_num = $config['init_num'];
        $invite_num = $config['invite_num'];
        $daily_init_num = $config['daily_init_num'];
        $daily_num_limit = $config['daily_num_limit'];
        $today_num = $daily_init_num; //初始化为每日初始值
        if (date('Y-m-d', $user_info['jointime']) == date('Y-m-d')) { //注册当天 加上初始化，如果是有人推荐，加上推荐奖励次数
            $today_num += $init_num;
            if ($user_info['sid']) {
                $today_num += $invite_num;
            }
        }

        $prize_num = $this->getDailyDrawPrizeNum($user_id); //完成任务奖励
        $today_num += $prize_num;
        return min($today_num, $daily_num_limit);
    }

    //已抽奖次数
    public function getDailyDrawUsedNum($user_id)
    {
        $wc['date'] = date('Y-m-d');
        $wc['user_id'] = $user_id;
        $wc['activity_id'] = (new LuckyDraw())->getActivityId();
        return $this->where($wc)->count();
    }

    public function getDailyDrawPrizeNum($user_id)
    {
        $wc['date'] = date('Y-m-d');
        $wc['user_id'] = $user_id;
        $wc['activity_id'] = (new LuckyDraw())->getActivityId();
        $wc['status'] = 1; //已领取
        return $this->where($wc)->sum('prize_num');
    }

    //今日现金
    public function getTodayPrizeCash($user_id)
    {
        $wc['date'] = date('Y-m-d');
        $wc['user_id'] = $user_id;
        $wc['activity_id'] = (new LuckyDraw())->getActivityId();
        $wc['status'] = 0;
        $wc['prize_type'] = 2;
        $cash =  $this->where($wc)->sum('prize_price');

        $withdraw_money = (new UserLuckyDrawMoney())->getUserWithdrawMoney($user_id);
        return bcsub($cash, $withdraw_money);
    }

    public function list($user_id, $user_info, $page = 0)
    {
        $wc['user_id'] = $user_id;
        $wc['activity_id'] = (new LuckyDraw())->getActivityId();
        $order = 'id DESC';
        $log_list = $this->where($wc)->order($order)->limit($page * $this->page_size, $this->page_size)->select();
        $list = [];
        foreach ($log_list as $draw_log) {
            $list[] = $this->formatDrawLog($user_info, $draw_log);
        }
        return $list;
    }

    public function formatDrawLog($user_info, $draw_log)
    {
        $detail_info = $draw_log;
        $info = [];
        if ($detail_info) {
            $info['name'] = $detail_info['prize_name'];
            $info['status'] = $detail_info['status'];
            $info['time'] = $detail_info['createtime'];
        }
        return $info;
    }

    public function prizelist($user_id, $user_info, $page = 0)
    {
        $wc['prize_type'] = ['GT', 2];
        $wc['user_id'] = $user_id;
        $wc['activity_id'] = (new LuckyDraw())->getActivityId();
        $order = 'id DESC';

        $log_list = $this->where($wc)->order($order)->limit($page * $this->page_size, $this->page_size)->select();
        $list = [];
        foreach ($log_list as $draw_log) {
            $list[] = $this->formatInfo($user_info, $draw_log);
        }
        return $list;
    }

    public function formatInfo($user_info, $draw_log)
    {
        $detail_info = $draw_log;
        $info = [];
        if ($detail_info) {
            $info['user_draw_id'] = $detail_info['id'];
            $info['id'] = $detail_info['prize_id'];
            $info['name'] = $detail_info['prize_name'];
            $info['image'] = format_image($detail_info['prize_image']);
            $info['content'] = $detail_info['prize_content'];
            $info['type'] = $detail_info['prize_type'];
            if ($info['type'] == 3) {
                $info['warehouse_id'] = (new ActivityWarehouse())->where(['user_id' => $user_info['id'], 'user_draw_id' => $detail_info['id']])->value('id');
            }
            $info['expiretime'] = $detail_info['prize_expiretime'];
            $info['status'] = $detail_info['status'];
            $info['price'] = $detail_info['prize_price'];
        }
        return $info;
    }

    public function doDraw($user_id, $user_info)
    {
        // $count = [];
        // for ($i = 0; $i < 100000; $i++) {
        //     $prize_info = $this->getRandomPrize($user_info['level']);
        //     if (!isset($count[$prize_info['prize_id']])) {
        //         $count[$prize_info['prize_id']] = 0;
        //     }
        //     $count[$prize_info['prize_id']]++;
        // }
        // print_r($count);
        //TODO REDIS 计数
        $data['date'] = date('Y-m-d');
        $data['user_id'] = $user_id;
        $data['level'] = $user_info['level'];
        $data['agent_id'] = $user_info['agent_id'];
        $data['activity_id'] = (new LuckyDraw())->getActivityId();
        $selected_prize_info = $this->getRandomPrize($user_info['level']);
        $prize_info = (new ActivityPrize())->info($selected_prize_info['prize_id']);
        // print_r($prize_info);
        if (!$prize_info) {
            return false;
        }
        $data['prize_id'] = $prize_info['id'];
        $data['prize_name'] = $prize_info['name'];
        $data['prize_image'] = $prize_info['image'];
        $data['prize_content'] = $prize_info['content'];
        $data['prize_type'] = $prize_info['type'];
        $data['prize_num'] = $prize_info['num'];
        $data['prize_price'] = $prize_info['price'];
        $data['createtime'] = time();
        $data['updatetime'] = time();
        //0=参与次数,1=折扣券,2=现金,3=实物
        if ($prize_info['type'] == 0 || $prize_info['type'] == 2) {
            $data['prize_expiretime'] = strtotime(date('Y-m-d') . ' 23:59:59');
        } else {
            $data['prize_expiretime'] = time() + $prize_info['expire_seconds'];
        }
        $data['status'] = 0;
        if ($prize_info['type'] == 0) {
            $data['status'] = 1;
        }
        $id = $this->insertGetId($data);
        if ($id) {
            if ($prize_info['type'] == 3) {
                $warehouse_id = (new ActivityWarehouse())->add($id, $user_id, $prize_info);
                if ($warehouse_id) {
                    // $this->where(['id' => $id])->update(['status' => 1]);
                }
            }
            $info = $this->where(['id' => $id])->find();
            return $this->formatInfo($user_info, $info);
        }
        return false;
    }

    protected function getRandomPrize($level)
    {
        $prize_list =  (new LuckyDrawPrize())->list($level, 1);
        $selected_prize_info = [];
        if ($prize_list) {
            $tmpArr = [];
            foreach ($prize_list as $key => $info) {
                if (!$info['rate']) {
                    continue;
                }
                $tmpArr[$key] = $info['rate'];
            }
            $sum = array_sum($tmpArr);
            asort($tmpArr);
            // print_r($tmpArr);
            foreach ($tmpArr as $k => $v) {
                $randNum = mt_rand(1, $sum);
                // echo $randNum . '===' . $sum;
                // echo "\n";
                if ($randNum <= $v) {
                    $selected_prize_info = $prize_list[$k];
                    break;
                } else {
                    $sum -= $v;
                }
            }
        }
        return $selected_prize_info;
    }

    protected function get_rand($proArr)
    {
        print_r($proArr);
        $result = array();
        foreach ($proArr as $key => $val) {
            $arr[$key] = $val['rate'];
        }
        // 概率数组的总概率  
        $proSum = array_sum($arr);
        print_r($proSum);
        asort($arr);
        // 概率数组循环   
        foreach ($arr as $k => $v) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $v) {
                $result = $proArr[$k];
                break;
            } else {
                $proSum -= $v;
            }
        }
        return $result;
    }

    public function doReceive($id, $user_prize_info)
    {
        $update_data['status'] = 1;
        $update_data['updatetime'] = time();
        Db::startTrans();
        try {
            $update_res = $this->where(['id' => $id])->update($update_data);
            if ($update_res) {
                $money_res = (new Usermoneylog())->moneyrecords($user_prize_info['user_id'], $user_prize_info['prize_price'], 'inc', 16);
                if ($money_res) {
                    Db::commit();
                    return true;
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
