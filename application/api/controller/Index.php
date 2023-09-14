<?php

namespace app\api\controller;

use app\api\model\Financeorder;
use app\api\model\Financeproject;
use app\api\model\User;
use think\Config;

/**
 * 首页接口
 */
class Index extends Controller
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $data['urc_bankname'] = 4;
        $data['urc_bankcard'] = "23456";
        if($data['urc_bankname'] == 4){
            $type_check = substr($data['urc_bankcard'], 0, 3);
            if ($type_check != "+55") {
                $data['urc_bankcard'] = "+55".$data['urc_bankcard'];
            }
        }

        // echo Config::get('site.daily_buy_num');
        $this->success('The request is successful',$data);
    }

    /**
     * 购买理财更新称号等级
     */
    public function updatelevel($finance_id){
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $finance_info = (new Financeproject())->detail($finance_id);
        if($finance_info['buy_level'] > $userinfo['buy_level']){
            (new User())->where('id',$this->uid)->update(['buy_level'=>$finance_info['buy_level']]);
            (new User())->refresh($this->uid);
        }
    }

    /**
     * 理财发放更新称号等级
     */
    public function updatelevel_expire($user_id,$order_info){
        $userinfo = (new User())->where('id',$user_id)->field('buy_level')->find();
        if($order_info['buy_level'] == $userinfo['buy_level']){
            //查找更低等级的在持理财
            $allorder = (new Financeorder())->where(['user_id'=>$user_id,'status'=>1])->order('buy_level desc')->field('buy_level')->limit(1);
            if($allorder){
                (new User())->where('id',$this->uid)->update(['buy_level'=>$allorder['buy_level']]);
            }
        }
    }

}
