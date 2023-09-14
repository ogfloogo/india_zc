<?php

namespace app\api\controller;

use app\api\model\Banner;
use app\api\model\Level;
use app\api\model\Order;
use app\api\model\Useraward as ModelUseraward;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Log;

/**
 * 用户等级特权
 */
class Userlevel extends Controller
{

    /**
     * 等级列表
     */
    public function list(){
        $list = (new Level())->tablelist();
        $this->success(__('The request is successful'),$list);
    }

    /**
     * 等级卡片
     */
    public function levelcard(){
        $this->verifyUser();
        $userinfo = $this->userInfo;
        if($userinfo){
            $balance = $userinfo['money'];
        }else{
            $balance = 0;
        }
        $list = (new Level())->levelcard($balance);
        $this->success(__('The request is successful'),$list);
    }
}
