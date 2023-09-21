<?php

namespace app\api\command;


use app\api\model\User;
use app\api\model\Usertotal;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;


class Historyrank extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Historyrank')
            ->setDescription('总排行');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $crowdfunding_income = (new Usertotal())->field('user_id,crowdfunding_income')
            ->where(['crowdfunding_income'=>['>',0]])->order('crowdfunding_income desc')->limit(1000)->select();
        foreach ($crowdfunding_income as $value){
            $user = (new User())->field('mobile')->where(['id'=>$value['user_id']])->find();
            if($user){
                $key = $user['mobile'];
                $redis->handler()->zadd('zclc:rank:history:crowdfunding_income_'.date('Y-m-d'),$value['crowdfunding_income'],$key);
            }else{
                $key = phonenumber();
                $redis->handler()->zadd('zclc:rank:history:crowdfunding_income_'.date('Y-m-d'),$value['crowdfunding_income'],$key);
            }
        }

        $promotion_award = (new Usertotal())->field('user_id,promotion_award')
            ->where(['promotion_award'=>['>',0]])->order('promotion_award desc')->limit(1000)->select();
        foreach ($promotion_award as $value){
            $user = (new User())->field('mobile')->where(['id'=>$value['user_id']])->find();
            if($user){
                $key = $user['mobile'];
                $redis->handler()->zadd('zclc:rank:history:promotion_award_'.date('Y-m-d'),$value['promotion_award'],$key);
            }else{
                $key = phonenumber();
                $redis->handler()->zadd('zclc:rank:history:promotion_award_'.date('Y-m-d'),$value['promotion_award'],$key);
            }
        }

        $total_commission = (new Usertotal())->field('user_id,total_commission')
            ->where(['total_commission'=>['>',0]])->order('total_commission desc')->limit(1000)->select();
        foreach ($total_commission as $value){
            $user = (new User())->field('mobile')->where(['id'=>$value['user_id']])->find();
            if($user){
                $key = $user['mobile'];
                $redis->handler()->zadd('zclc:rank:history:total_commission_'.date('Y-m-d'),$value['total_commission'],$key);
            }else{
                $key = phonenumber();
                $redis->handler()->zadd('zclc:rank:history:total_commission_'.date('Y-m-d'),$value['total_commission'],$key);
            }
        }
    }
}