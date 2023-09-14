<?php

namespace app\api\model;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

/**
 * FAQ
 */
class Usertask extends Model
{
    protected $name = 'user_task';

    /**
     * 月任务、日任务 判断邀请人数
     * @param $pid
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function taskRewardType($user_id,$type,$task_type = 2){
//        return true;
        $pid = (new User())->where(['id'=>$user_id])->value('sid');
        if(!$pid){
            return true;
        }
        if($type == 3){
            if($task_type == 1){
                $month = self::where(['user_id'=>$pid,'category'=>1,'type'=>1])->whereTime('createtime','month')->find();
                if($month){
                    self::where(['user_id'=>$pid,'category'=>1,'type'=>1])->whereTime('createtime','month')->setInc('num',1);
                    $num = $month['num']+1;
                }else{
                    $create = [
                        'user_id' => $pid,
                        'category' => 1,
                        'type' => 1,
                        'createtime' => time(),
                        'num' => 1,
                        'is_receive' => 1,
                        'is_condition' => 1,
                    ];
                    self::create($create);
                    $num = 1;
                }
                $month_reward = (new Monthreward())->where(['num'=>$num])->find();
                if($month_reward){
                    //发放奖励
                    self::where(['user_id'=>$pid,'category'=>1,'type'=>1])->whereTime('createtime','month')->update(['money'=>$month_reward['reward']]);
                    (new Usermoneylog())->moneyrecords($pid, $month_reward['reward'], 'inc', 29, "月任务，拉取{$num}人");
                }
                return true;
            }
//            $count = (new Financeorder())->where(['user_id'=>$user_id,'popularize'=>1,'buy_level'=>['>=',2],'is_robot'=>0])->count();
//            if($count != 1){
//                return true;
//            }
        }
        $day = self::where(['user_id'=>$pid,'category'=>2,'type'=>$type])->whereTime('createtime','today')->find();
        if($day){
            self::where(['user_id'=>$pid,'category'=>2,'type'=>$type])->whereTime('createtime','today')->setInc('num',1);
            $num = $day['num']+1;
        }else{
            $create = [
                'user_id' => $pid,
                'category' => 2,
                'type' => $type,
                'createtime' => time(),
                'num' => 1,
                'is_receive' => 0
            ];
            self::create($create);
            $num = 1;
        }
        //日奖励手动领取
        $day_reward = (new Dayreward())->where(['type'=>$type,'num'=>$num])->find();
        if($day_reward){
            //修改为可领取
            self::where(['user_id'=>$pid,'category'=>2,'type'=>$type])->whereTime('createtime','today')->update(['is_condition'=>1,'money'=>$day_reward['reward']]);
        }
    }
}
