<?php

namespace app\api\command;

use app\api\model\User;
use app\api\model\Usercategory;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;


class Monthrank extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Monthrank')
            ->setDescription('月排行');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $crowdfunding_income = (new Usercategory())->field('user_id,sum(crowdfunding_income) crowdfunding_income')->whereTime('createtime','last month')
            ->where(['crowdfunding_income'=>['>',0]])->group('user_id')->order('crowdfunding_income desc')->limit(1000)->select();
        foreach ($crowdfunding_income as $value){
            $user = (new User())->field('mobile')->where(['id'=>$value['user_id']])->find();
            if($user){
                $key = $user['mobile'];
                $redis->handler()->zadd('zclc:rank:month:crowdfunding_income_'.date('Y-m'),$value['crowdfunding_income'],$key);
            }else{
                $key = phonenumber();
                $redis->handler()->zadd('zclc:rank:month:crowdfunding_income_'.date('Y-m'),$value['crowdfunding_income'],$key);
            }
        }

        $promotion_award = (new Usercategory())->field('user_id,sum(promotion_award) promotion_award')->whereTime('createtime','last month')
            ->where(['promotion_award'=>['>',0]])->group('user_id')->order('promotion_award desc')->limit(1000)->select();
        foreach ($promotion_award as $value){
            $user = (new User())->field('mobile')->where(['id'=>$value['user_id']])->find();
            if($user){
                $key = $user['mobile'];
                $redis->handler()->zadd('zclc:rank:month:promotion_award_'.date('Y-m'),$value['promotion_award'],$key);
            }else{
                $key = phonenumber();
                $redis->handler()->zadd('zclc:rank:month:promotion_award_'.date('Y-m'),$value['promotion_award'],$key);
            }
        }

        $total_commission = (new Usercategory())->field('user_id,sum(total_commission) total_commission')->whereTime('createtime','last month')
            ->where(['total_commission'=>['>',0]])->group('user_id')->order('total_commission desc')->limit(1000)->select();
        foreach ($total_commission as $value){
            $user = (new User())->field('mobile')->where(['id'=>$value['user_id']])->find();
            if($user){
                $key = $user['mobile'];
                $redis->handler()->zadd('zclc:rank:month:total_commission_'.date('Y-m'),$value['total_commission'],$key);
            }else{
                $key = phonenumber();
                $redis->handler()->zadd('zclc:rank:month:total_commission_'.date('Y-m'),$value['total_commission'],$key);
            }
        }
    }
}