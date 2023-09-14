<?php

namespace app\api\controller;

use app\api\model\Rechargechannel;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\api\model\Userrecharge as ModelUserrecharge;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * 代收订单
 */
class Userrecharge extends Controller
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     *代收订单创建
     *
     * @ApiMethod (POST)
     * @param string $price 代收金额
     * @param string $channel_id 渠道ID
     */
    public function addorder()
    {
        $this->verifyUser();
        $post = $this->request->post();
        if (!$post['price'] || !$post['channel_id']) {
            $this->error(__('parameter error'));
        }
        //代收渠道
        $channel = (new Rechargechannel())->where('id',$post['channel_id'])->find();
        if(!$channel){
            $this->error(__('Payment channels do not exist'));
        }
        //代收渠道是否开启
        if($channel['status'] == 0){
            $this->error(__('The payment channel is not opened'));
        }
        //代收金额最小限制
        if($post['price'] < $channel['minprice']){
            $this->error(__('Minimum recharge amount')." ".$channel['minprice']);
        }
        //代收金额最大限制
        if($post['price'] > $channel['maxprice']){
            $this->error(__('Maximum recharge amount')." ".$channel['maxprice']);
        }
        //创建代收订单
        $payinfo = (new ModelUserrecharge())->addorder($post,$channel,$this->userInfo);
        if(!$payinfo){
            $this->error(__('支付失败'));
        }
        // if($payinfo['code'] == 0){
        //     $this->error($payinfo['msg']);
        // }
        $this->success('The request is successful',$payinfo);
    }

    /**
     * 检测验证码-用户注册
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     * @param string $captcha 验证码
     */
    public function check()
    {
        $mobile = $this->request->post("mobile");
        $event = $this->request->post("event");
        $event = $event ? $event : 'register';
        $captcha = $this->request->post("captcha");

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机号不正确'));
        }
        if ($event) {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('已被注册'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('已被占用'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Smslib::check($mobile, $captcha, $event);
        if ($ret) {
            $this->success(__('成功'));
        } else {
            $this->error(__('验证码不正确'));
        }
    }
}
