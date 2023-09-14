<?php

namespace app\api\controller;

use app\api\model\Banner;
use app\api\model\Order;
use app\api\model\Useraward as ModelUseraward;
use app\common\model\Redpacket as ModelRedpacket;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Log;

/**
 * 红包
 */
class Redpacket extends Controller
{
    /**
     *开红包
     *
     */
    public function openredpacket(){
        $this->verifyUser();
        //是否领取过
        $is_get = (new ModelRedpacket())->where(['user_id'=>$this->uid])->find();
        if(!empty($is_get)){
            $this->error("can only be received once");
        }
        $price = $this->randomFloat(config('site.redpacket_start'),config('site.redpacket_end'));
        $res = (new ModelRedpacket())->openredpacket($this->uid,$price);
        if(!$res){
            $this->error("Failed to receive");
        }
        $this->success(__('Receive success'),$price);
    }

    /**
     * 播报
     */
    public function broadcast(){
        $list = (new ModelRedpacket())->order('id desc')->limit(100)->select();
        foreach($list as &$value){
            $value['nickname'] = db('user')->where(['id'=>$value['user_id']])->value("nickname");
        }
        $this->success(__('Request successful'),$list);
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
     * 获取随机小数保存小数点
     * @param int $min 最小值
     * @param int $max 最大值
     * @return string
     */
    function randomFloat($min, $max)
    {
        $num =  $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return bcadd($num,0,1);
    }
}
