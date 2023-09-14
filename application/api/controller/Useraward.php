<?php

namespace app\api\controller;

use app\api\model\Banner;
use app\api\model\Order;
use app\api\model\Useraward as ModelUseraward;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Log;

/**
 * 邀请奖励
 */
class Useraward extends Controller
{

    /**
     * 用户信息
     */
    public function userinfo(){
        $this->verifyUser();
        $list = (new ModelUseraward())->userinfo($this->uid);
        $this->success(__('The request is successful'),$list);
    }
    /**
     *好友邀请奖励列表
     *
     */
    public function rewardlist(){
        $this->verifyUser();
        $post = $this->request->post();
        $list = (new ModelUseraward())->rewardlist($post,$this->uid);
        $this->success(__('The request is successful'),$list);
    }

    /**
     *规则
     *
     */
    public function rule(){
        $list = (new ModelUseraward())->rule();
        $this->success(__('The request is successful'),$list);
    }


    /**
     *奖励领取
     *
     */
    public function rewardfor(){
        $this->verifyUser();
        $post = $this->request->post();
        $list = (new ModelUseraward())->rewardfor($post,$this->uid);
        $this->success(__('The request is successful'),$list);
    }
}
