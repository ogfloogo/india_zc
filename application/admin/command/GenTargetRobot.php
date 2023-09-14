<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\robot\UserTarget;
use app\admin\model\robot\UserTargetLevel;
use app\admin\model\sys\CheckReport;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog;
use app\admin\model\user\UserTeam;
use app\admin\model\userlevel\UserLevel;
use app\api\model\User as ModelUser;
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

class GenTargetRobot extends Command
{
    protected $model = null;
    protected $userNum = 3;
    protected $userNumLimit = 3;
    protected $expiretime = 2 * 86400;
    protected $frozen_time = 3600;
    protected $redisInstance = null;
    protected $lock_key = 'target_robot';

    protected function configure()
    {
        $this->redisInstance = ((new Redis())->handler());
        $this->setName('GenTargetRobot')
            ->setDescription('GenTargetRobot');
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
        try {
            $this->runGen();
        } catch (\Throwable $e) {
            Log::myLog('error:', $e, 'check');
        }
        $this->redisInstance->del($this->lock_key);
    }

    protected function runGen()
    {
        $maxLevel = (new UserLevel())->where(['id' => ['GT', 0]])->max('level');
        //处理中的才取
        $targetList = (new UserTarget())->where(['id' => ['GT', 0], 'status' => 1])->select();
        echo (new UserTarget())->getLastSql();
        echo "\n";

        print_r($maxLevel);
        echo "\n";

        foreach ($targetList as $targetInfo) {
            // $targetInfo = is_array($targetInfo) ? $targetInfo : $targetInfo->toArray();
            echo $targetInfo['user_id'] . "\n";
            //添加robot时间超过 1小时 60*60
            $frozen_time = rand(2 * $this->frozen_time, 5 * $this->frozen_time);
            echo $targetInfo['addrobottime'] . '====' . $frozen_time . '====' . (time() - $frozen_time) . "\n";

            if ($targetInfo['addrobottime'] >= (time() - $frozen_time)) {
                echo "1hour ago\n";
                continue;
            }
            $userInfo = (new User())->where(['id' => $targetInfo['user_id']])->find();
            if (empty($userInfo)) {
                continue;
            }
            // $userInfo = is_array($userInfo) ? $userInfo : $userInfo->toArray();
            if ($maxLevel == $userInfo['level']) { //已经最高级，不处理
                continue;
            }
            if ($userInfo['level'] == $targetInfo['level']) { //存的级别相同

            } else {
                //更新级别，增加更新日志
                $levelData = [];
                $levelData['old_level'] =  $targetInfo['level'];
                $levelData['level'] =  $userInfo['level'];
                $levelData['user_id'] = $targetInfo['user_id'];
                $levelData['up'] = (intval($userInfo['level']) - intval($targetInfo['level'])) > 0 ? 1 : 0;
                $levelData['createtime'] =  time();
                $level_res = (new UserTargetLevel())->insertGetId($levelData);
                if ($level_res) {
                    (new UserTarget())->where(['id' => $targetInfo['id']])->update(['level' => $userInfo['level']]);
                }
            }

            //取下级是1级的robot
            $firstRobot = (new User())->where(['sid' => $targetInfo['user_id'], 'level' => 1, 'status' => 1, 'is_robot' => 1])->field('id,mobile')->find();
            // echo 'first1:';
            // echo $firstRobot ? $firstRobot->mobile : 'no robot';
            // echo "\n";

            echo 'empty:' . empty($firstRobot) . "\n";
            if (empty($firstRobot)) {
                $first_robot_id = $this->addRobot($targetInfo['user_id'], 1);
                echo 'order:' . $first_robot_id . "\n";

                if ($first_robot_id) {
                    //添加用户奖励记录
                    $this->addReward($first_robot_id, $targetInfo['user_id']);
                    $firstRobot = (new User())->where(['id' => $first_robot_id, 'level' => 1, 'status' => 1, 'is_robot' => 1])->find();
                    // $firstRobot = is_array($firstRobot) ? $firstRobot : $firstRobot->toArray();
                    echo 'first2:';
                    print_r($firstRobot['mobile']);
                    if ($first_robot_id) {
                        //一级的添加后也缓两分钟
                        (new UserTarget())->where(['id' => $targetInfo['id']])->update(['addrobottime' => time()]);
                        break;
                    }
                }
            }
            if (empty($firstRobot)) {
                continue;
            }
            // $firstRobot = is_array($firstRobot) ? $firstRobot : $firstRobot->toArray();
            print_r($firstRobot['mobile']);

            $lowLevelUserNum = (new User())->where(['sid' => $firstRobot['id'], 'level' => ['ELT', $userInfo['level']], 'status' => 1, 'is_robot' => 1, 'createtime' => ['EGT', (time() - $this->expiretime)]])->count();
            if ($lowLevelUserNum >= $this->userNumLimit) {
                continue;
            }

            //直接下级是robot了
            //判断下下级比自己高一级的人数 等级高
            // $userNum = (new User())->where(['sid' => $firstRobot['id'], 'level' => intval($userInfo['level'] + 1), 'status' => 1, 'is_robot' => 1])->count();
            $userNum = (new User())->where(['sid' => $firstRobot['id'], 'level' => ['GT', $userInfo['level']], 'status' => 1, 'is_robot' => 1])->count();
            echo $userNum . "\n";
            echo $this->userNumLimit . "\n";
            if ($userNum < $this->userNumLimit) {
                for ($i = 0; $i < ($this->userNumLimit - $userNum - $lowLevelUserNum); $i++) {
                    //添加比自己目前高一级的robot
                    $target_level = intval($userInfo['level']) <= 4 ? rand(intval($userInfo['level'] + 1), intval($userInfo['level'] + 3)) : intval($userInfo['level'] + 1);
                    if ($target_level >= $maxLevel) {
                        $target_level = $maxLevel;
                    }
                    $add_res = $this->addRobot($firstRobot['id'], $target_level);
                    if (!$add_res) {
                        continue;
                    }
                    if ($add_res) {
                        (new UserTarget())->where(['id' => $targetInfo['id']])->update(['addrobottime' => time()]);
                        //加完一个就换人加，下一个等其他时间添加
                        break;
                    }
                }
            }
        }
    }

    protected function addReward($souce_user_id, $to_user_id)
    {
        $data = [
            'source' => $souce_user_id, //来源ID
            'user_id' => $to_user_id, //奖励用户ID
            'recharge' => 0, //来源充值金额
            'moneys' => Config::get('site.invite_reward'),
            'createtime' => time(),
            'updatetime' => time()
        ];
        (new UserAward())->insert($data);
    }

    protected function addRobot($user_id, $level)
    {
        $userData['sid'] = $user_id;
        $userData['level'] = $level;
        $userData['mobile'] = $this->genMobile($level);
        $userData['nickname'] = substr($userData['mobile'], 0, 3) . '****' . substr($userData['mobile'], -2);
        $userData['avatar'] = "/uploads/avatar.png";
        $userData['createtime'] = time();
        $userData['status'] = 1;
        $userData['is_robot'] = 1;
        print_r($userData);
        $robot_user_id = (new User())->insertGetId($userData);
        if ($robot_user_id) {
            $data = [];
            $data['user_id'] = $user_id;
            $data['team'] = $robot_user_id;
            $data['level'] = 1;
            $data['createtime'] = time();
            $add_res = (new UserTeam())->insertGetId($data);
            $sid = (new User())->where(['id' => $user_id])->value('sid');
            if ($sid) {
                $data = [];
                $data['user_id'] = $sid;
                $data['team'] = $robot_user_id;
                $data['level'] = 2;
                $data['createtime'] = time();
                (new UserTeam())->insertGetId($data);
                $ssid = (new User())->where(['id' => $sid])->value('sid');
                if ($ssid) {
                    $data = [];
                    $data['user_id'] = $ssid;
                    $data['team'] = $robot_user_id;
                    $data['level'] = 3;
                    $data['createtime'] = time();
                    (new UserTeam())->insertGetId($data);
                }
            }
        }
        return $robot_user_id;
    }

    private function genMobile($level)
    {
        //生成手机号，9 000 级别 0000 0000（一共13位）
        $arr = [9];
        $mobile = $arr[array_rand($arr)];
        $mobile .= mt_rand(100, 999) . intval($level) . mt_rand(1000, 9999) . mt_rand(1000, 9999);
        return $mobile;
    }
}
