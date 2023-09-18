<?php

namespace app\api\controller;


use app\api\model\Dayreward;
use app\api\model\Monthreward;
use app\api\model\Protocol;
use app\api\model\Signin;
use app\api\model\Teamlevel;
use app\api\model\Usermoneylog;
use app\api\model\Usertask;
use app\api\model\Usertotal;
use think\Config;
use think\Db;

class Reward extends Controller
{
    /**
     * 用户签到
     * @return void
     * @throws \think\Exception
     */
    public function signin(){
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $signin = (new \app\api\model\Signin())->where(['user_id'=>$userinfo['id']])->whereTime('createtime','today')->count();
        if($signin){
            $this->error(__("Signed in"));
        }
        $create = [
            'user_id' => $userinfo['id'],
            'createtime' => time()
        ];
        Db::startTrans();
        $rs = (new \app\api\model\Signin())->create($create);
        if(!$rs){
            Db::rollback();
            $this->error(__("operation failure"));
        }
        $money = $userinfo['buy_level']==0?config('site.normal_signin'):config('site.promoter_signin');
        $rs2 = (new Usermoneylog())->moneyrecords($userinfo['id'], $money, 'inc', 27, "签到奖励");
        if(!$rs2){
            Db::rollback();
            $this->error(__("operation failure"));
        }
        Db::commit();
        $this->success(__("operate successfully"));
    }

    /**
     * 奖励中心页面
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rewardPage(){
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $listTypeReward = (new Usermoneylog())->listTypeReward(1,20,'english',$userinfo['id'],[27,28,29]);
        $accumulated_rewards = $listTypeReward['total'];
        $accumulated_invitations = (new Usertotal())->where(['user_id'=>$userinfo['id']])->value('invite_number');
        $return = [];
        $level = (new Teamlevel())->detail($userinfo['buy_level']);
        $return['userinfo'] = [
            'nickname' => $userinfo['nickname'],
            'avatar' => $userinfo['avatar'],
            'buy_level' => $userinfo['buy_level'],
            'buy_level_name' => $level['name']??'',
            'buy_level_image' => !empty($level['image'])?format_image($level['image']):''
        ];
        $return['statistics'] = [
            'accumulated_rewards' => $accumulated_rewards,
            'accumulated_invitations' => $accumulated_invitations
        ];
        $signed_in = (new Signin())->where(['user_id'=>$userinfo['id']])->whereTime('createtime','today')->count();
        $return['signin'] = [
            'normal_signin' => config('site.normal_signin'),
            'promoter_signin' => config('site.promoter_signin'),
            'signin_text' => config('site.signin_text'),
            'signed_in' => $signed_in?1:0
        ];
        $month_reward = (new Monthreward())->order('num asc')->select();
        $user_month_task = (new Usertask())->where(['user_id'=>$userinfo['id'],'category'=>1,'type'=>1])->whereTime('createtime','month')->find();
        $user_month_invite = !empty($user_month_task)?$user_month_task['num']:0;
        $need_num = 0;
        $reward = 0;
        foreach ($month_reward as &$value){
            if($user_month_invite >= $value['num']){
                $value['is_receive'] = 1;
            }else{
                $value['is_receive'] = 0;
                if($need_num == 0){
                    $need_num = $value['num'] - $user_month_invite;
                    $reward = $value['reward'];
                }
            }
        }
        $return['month_task']['list'] = $month_reward;
        $return['month_task']['condition'] = [
            'need_num' => $need_num,
            'reward' => $reward
        ];
        $return['month_task']['status'] = $month_reward?1:0;
        $language = $this->language;
        $day_reward = (new Dayreward())->field('id,num,reward,createtime,type,title_json')->order('type asc')->select();
        $success = 1;
        foreach ($day_reward as &$value){
            $day_task = (new Usertask())->where(['user_id'=>$userinfo['id'],'category'=>2,'type'=>$value['type']])->whereTime('createtime','today')->find();
            $value['rate'] = $day_task?"{$day_task['num']}/{$value['num']}":"0/{$value['num']}";
            $value['is_receive'] = $day_task?$day_task['is_receive']:0;
            $value['is_condition'] = $day_task?$day_task['is_condition']:0;
            if($day_task&&$day_task['is_condition']==1){
                $success++;
            }
            $title = json_decode($value['title_json'],true);
            $value['title'] = $title[$language];
        }
        $return['day_task']['list'] = $day_reward;
        $exist = (new Usertask())->where(['user_id'=>$userinfo['id'],'category'=>3,'is_receive'=>1])->whereTime('createtime','today')->find();
        $return['day_task']['course'] = [
            'total' => 4,
            'success' => $success,
            'is_receive' => $exist?1:0
        ];
        $return['day_task']['day_reward_total'] = config('site.day_reward_total');
        $return['day_task']['count_down'] = strtotime('23:59:59')-time();
        $this->success(__("operate successfully"),$return);
    }

    /**
     * 领取每日奖励
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dayRewardReceive(){
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $type = $this->request->post("type");
        if (!$type) {
            $this->error(__('parameter error'));
        }
        $day_task = (new Usertask())->where(['user_id'=>$userinfo['id'],'category'=>2,'type'=>$type,'is_condition'=>1])->whereTime('createtime','today')->find();
        //检查是否符合领取条件
        if($day_task){
            if($day_task['is_receive'] == 1){
                $this->error(__("You have already claimed it"));
            }
            Db::startTrans();
            $day_task->is_receive = 1;
            $rs = $day_task->save();
            if(!$rs){
                Db::rollback();
                $this->error(__("operation failure"));
            }
            $rs2 = (new Usermoneylog())->moneyrecords($userinfo['id'], $day_task['money'], 'inc', 28, "日任务，类型{$type}");
            if(!$rs2){
                Db::rollback();
                $this->error(__("operation failure"));
            }
            Db::commit();
            $this->success(__("operate successfully"));
        }else{
            $this->error(__("operation failure"));
        }
    }

    /**
     * 领取每日额外奖励
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dayRewardTotalReceive(){
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $exist = (new Usertask())->where(['user_id'=>$userinfo['id'],'category'=>3,'is_receive'=>1])->whereTime('createtime','today')->find();
        if($exist){
            //判断当日领取过没有
            $this->error(__("You have already claimed it"));
        }
        $success = 1;
        $num = 0;
        $day_reward = (new Dayreward())->field('id,num,reward,createtime,type')->order('type asc')->select();
        foreach ($day_reward as $value){
            $day_task = (new Usertask())->where(['user_id'=>$userinfo['id'],'category'=>2,'type'=>$value['type']])->whereTime('createtime','today')->find();
            if(!$day_task){
                $success = 0;
                break;
            }else{
                if($day_task['is_condition'] == 0){
                    $success = 0;
                    break;
                }
            }
            $num += $day_task['num'];
        }
        if($success == 0){
            //未完成所有每日任务
            $this->error(__("operation failure"));
        }
        Db::startTrans();
        $create = [
            'user_id' => $userinfo['id'],
            'category' => 3,
            'type' => 0,
            'createtime' => time(),
            'num' => $num,
            'is_receive' => 1,
            'is_condition' => 1,
            'money' => config('site.day_reward_total')
        ];
        $rs = (new Usertask())->create($create);
        if(!$rs){
            Db::rollback();
            $this->error(__("operation failure"));
        }
        $rs2 = (new Usermoneylog())->moneyrecords($userinfo['id'], config('site.day_reward_total'), 'inc', 28, "日任务，类型完成所有任务");
        if(!$rs2){
            Db::rollback();
            $this->error(__("operation failure"));
        }
        Db::commit();
        $this->success(__("operate successfully"));
    }

    /**
     * 奖励资金记录
     * @return void
     */
    public function rewardList(){
        $this->verifyUser();
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        if(!$page||!$pageSize){
            $this->error(__('parameter error'));
        }
        $list = (new Usermoneylog())->listTypeReward($page,$pageSize,$this->language,$this->uid,[27,28,29]);
        $this->success(__("operate successfully"),$list);
    }

    /**
     * 每日、每月邀请奖励规则
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rewardRule(){
        $rule = $this->request->param('rule');
        if(!$rule){
            $this->error(__('parameter error'));
        }
        $rule = (new Protocol())->field('id,content')->where(['name'=>$rule,'language'=>$this->language])->find();
        $this->success(__("operate successfully"),$rule?$rule['content']:'');
    }
}
