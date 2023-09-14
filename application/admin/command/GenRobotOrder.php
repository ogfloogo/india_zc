<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\robot\RobotOrder;
use app\admin\model\robot\UserTarget;
use app\admin\model\robot\UserTargetLevel;
use app\admin\model\sys\CheckReport;
use app\admin\model\User;
use app\admin\model\user\CommissionLog;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog;
use app\admin\model\user\UserTeam;
use app\admin\model\userlevel\UserLevel;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog as ModelUsermoneylog;
use app\api\model\Usertotal;
use think\cache\driver\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Loader;
use think\Log;

class GenRobotOrder extends Command
{
    protected $model = null;
    protected $userNum = 3;
    // protected $expiretime = 2* 86400;
    protected $expiretime = 300;
    protected $daily_buy_num = 12;
    protected $frozen_time = 60;
    protected $lock_key = 'robot_order';
    protected $redisInstance = null;

    protected function configure()
    {
        $this->redisInstance = ((new Redis())->handler());
        $this->setName('GenRobotOrder')
            ->setDescription('GenRobotOrder');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {

        set_time_limit(0);
        $is_lock = $this->redisInstance->get($this->lock_key);
        if ($is_lock) {
            echo 'lock';
            return false;
        }
        $this->redisInstance->set($this->lock_key, time());
        $this->redisInstance->expire($this->lock_key, 60 * 60);

        $this->daily_buy_num = Config::get('site.daily_buy_num');

        try {
            $this->runGen();
        } catch (\Throwable $e) {
            Log::myLog('error:', $e, 'check');
        }
        $this->redisInstance->del($this->lock_key);
    }

    protected function runGen()
    {
        //0-6点不下单
        if(date('H')>=0 && date('H')<6){
            return ;
        }
        $maxLevel = (new UserLevel())->where(['id' => ['GT', 0]])->max('level');
        //处理中的才取
        $targetList = (new UserTarget())->where(['id' => ['GT', 0], 'status' => 1])->select();
        echo (new UserTarget())->getLastSql();
        echo "\n";


        print_r($maxLevel);
        echo "\n";
        $todayTime = Time::today();
        $daily_buy_num = $this->daily_buy_num;
        foreach ($targetList as $targetInfo) {
            // $targetInfo = is_array($targetInfo) ? $targetInfo : $targetInfo->toArray();

            echo 'user_id:' . $targetInfo['user_id'] . "\n";
            //添加订单时间超过 2-15分钟随机冷冻时间
            $frozen_time = rand(2 * $this->frozen_time, 15 * $this->frozen_time);
            if ($targetInfo['addordertime'] >= (time() - $this->frozen_time)) {
                echo "1hour ago\n";
                continue;
            }

            $userInfo = (new User())->where(['id' => $targetInfo['user_id']])->find();
            if (empty($userInfo)) {
                continue;
            }
            // $userInfo = is_array($userInfo) ? $userInfo : $userInfo->toArray();
            if ($userInfo['level'] == $targetInfo['level']) {
                if ($maxLevel == $userInfo['level']) {
                    // continue;//如果已经达到最高级，不处理
                }
            }
            //获取最新userInfo
            // if ($userInfo['token']) {
            //     $this->redisInstance->select(1);
            //     $user_info = $this->redisInstance->get("token:" . $this->token);
            //     if ($user_info) {
            //         $userInfo = json_decode($user_info, true);
            //     }
            // }

            //取下级是1级的robot
            $firstRobot = (new User())->where(['sid' => $targetInfo['user_id'], 'level' => 1, 'status' => 1, 'is_robot' => 1])->field('id,mobile')->find();
            // echo $firstRobot ? $firstRobot->mobile : 'no robot';

            if (empty($firstRobot)) {
                continue;
            }

            // $firstRobot = is_array($firstRobot) ? $firstRobot :$firstRobot->toArray();

            //获取目标用户底下，（通过一级的robot当中介）取 比自己级别小的robot
            $robotList = (new User())->where(['sid' => $firstRobot['id'], 'level' => ['ELT', $userInfo['level']], 'status' => 1, 'is_robot' => 1])->field('id,level,createtime')->select();
            echo 'rocou:' . count($robotList) . "\n";
            if (count($robotList)) {
                shuffle($robotList);
                foreach ($robotList as $robotInfo) {
                    $robot_id = $robotInfo['id'];
                    //如果超过两天，判断robot继续升级还是不下单
                    if (time() > ($robotInfo['createtime'] + $this->expiretime)) {
                        //如果还能升一级就升，超过目标用户
                        if ((intval($userInfo['level']) + 1) <= $maxLevel) {
                            // (new User())->where(['id' => $robot_id])->update(['level' => (intval($userInfo['level']) + 1)]);
                        } elseif (intval($userInfo['level']) == $maxLevel) {
                            (new User())->where(['id' => $robot_id])->update(['level' =>  1, 'status' => 0]);
                        }
                        continue;
                    }
                    //检查是否达到12次，达到就跳到下一个robot
                    $order_num = (new RobotOrder())->where(['user_id' => $robot_id, 'createtime' => ['BETWEEN', [$todayTime[0], $todayTime[1]]]])->count();
                    echo 'num:' . $order_num . '-----' . $daily_buy_num . "\n";
                    if ($order_num >= $daily_buy_num) {
                        continue;
                    }
                    //下单发佣金
                    $add_res = $this->addOrder($robotInfo);
                    if ($add_res) {
                        //更新上次下单时间
                        (new UserTarget())->where(['id' => $targetInfo['id']])->update(['addordertime' => time()]);
                    }
                    break; //一个目标用户，一次任务只下一单，就退出
                }
            }
        }
    }

    protected function addOrder($robotInfo)
    {
        $userteam = (new UserTeam());

        //第一级 ,如果没有上级，直接退出
        $first = $userteam->where('team', $robotInfo['id'])->where('level', 1)->field('user_id')->find();
        if (!$first) {
            return false;
        }
        echo $first['user_id'] . "\n";
        //用户等级和商品区默认对应，不能随便更改id
        $categoryInfo = $this->redisInstance->Hgetall("zclc:category:" . $robotInfo['level']);
        $orderData['user_id'] = $robotInfo['id'];
        $orderData['level'] = $robotInfo['level'];
        $orderData['category_id'] = $robotInfo['level'];
        $orderData['amount'] = $categoryInfo['price'];
        $orderData['reward'] = $categoryInfo['reward']; //商品区收益
        $orderData['createtime'] = time();
        $orderData['updatetime'] = time();
        $reward = $categoryInfo['reward'];
        print_r($orderData);
        //添加机器人订单
        $order_id = (new RobotOrder())->insertGetId($orderData);

        if ($first) {
            //上级的等级
            $userLevel = (new User())->where('id', $first['user_id'])->field('level,is_robot')->find();
            if ($userLevel && $userLevel['level'] >= $robotInfo['level'] && !$userLevel['is_robot']) {
                $levelInfo = $this->redisInstance->hMget("zclc:level:" . $userLevel['level'], ['commission_fee_level_1']);
                $commission = bcmul($reward, $levelInfo['commission_fee_level_1'] / 100, 2);
                $this->addCommission($first['user_id'], $robotInfo['id'], 1, $order_id, $levelInfo['commission_fee_level_1'], $commission);
            }
            //二级
            $second = $userteam->where('team', $robotInfo['id'])->where('level', 2)->field('user_id')->find();
            if ($second) {
                //我的上上级
                $userLevel = (new User())->where('id', $second['user_id'])->field('level,is_robot')->find();
                if ($userLevel && $userLevel['level'] >= $robotInfo['level'] && !$userLevel['is_robot']) {
                    $levelInfo = $this->redisInstance->hMget("zclc:level:" . $userLevel['level'], ['commission_fee_level_2']);
                    $commission = bcmul($reward, $levelInfo['commission_fee_level_2'] / 100, 2);
                    $this->addCommission($second['user_id'], $robotInfo['id'], 2, $order_id, $levelInfo['commission_fee_level_2'], $commission);
                }
                //三级
                $third = $userteam->where('team', $robotInfo['id'])->where('level', 3)->field('user_id')->find();
                if ($third) {
                    //我的上上上级
                    $userLevel = (new User())->where('id', $third['user_id'])->field('level,is_robot')->find();
                    if ($userLevel && $userLevel['level'] >= $robotInfo['level'] && !$userLevel['is_robot']) {
                        $levelInfo = $this->redisInstance->hMget("zclc:level:" . $userLevel['level'], ['commission_fee_level_3']);
                        $commission = bcmul($reward, $levelInfo['commission_fee_level_3'] / 100, 2);
                        $this->addCommission($third['user_id'], $robotInfo['id'], 3, $order_id, $levelInfo['commission_fee_level_3'], $commission);
                    }
                }
            }
        }
    }

    private function addCommission($to_id, $from_id, $level, $order_id, $commission_fee, $commissions)
    {
        $commission_model = (new CommissionLog());
        $commission_model->setTableName($to_id);
        $usertotal = new Usertotal();
        $order = new RobotOrder();
        //佣金log
        $comData = [
            'to_id' => $to_id,
            'from_id' => $from_id,
            'level' => $level,
            'order_id' => $order_id,
            'commission' => $commissions,
            'commission_fee' => $commission_fee,
            'createtime' => time(),
            'updatetime' => time(),
        ];
        $commission_model->insertGetId($comData);
        //余额变动
        $isok = (new ModelUsermoneylog())->moneyrecords($to_id, $commissions, 'inc', 4, "来源RID:" . $from_id);
        if (!$isok) {
            return false;
        }
        switch ($level) {
            case 1:
                //上级佣金统计
                $usertotal->where('user_id', $to_id)->setInc('first_commission', $commissions);
                //更新订单佣金
                $order->where('id', $order_id)->update(["level1" => $commissions]);
                break;
            case 2:
                //上上级佣金统计
                $usertotal->where('user_id', $to_id)->setInc('second_commission', $commissions);
                //更新订单佣金
                $order->where('id', $order_id)->update(["level2" => $commissions]);
                break;
            case 3:
                //上上上级佣金统计
                $usertotal->where('user_id', $to_id)->setInc('third_commission', $commissions);
                //更新订单佣金
                $order->where('id', $order_id)->update(["level3" => $commissions]);
                break;
            default:
                break;
        }
        return true;
    }
}
