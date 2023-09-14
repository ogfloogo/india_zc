<?php

namespace app\api\controller;

use app\api\model\Activity;
use app\api\model\Rechargechannel;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\api\model\Userrecharge as ModelUserrecharge;
use app\api\model\Voucher as ModelVoucher;
use app\api\model\Voucherorder;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * 体验券
 */
class Voucher extends Controller
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     *体验券订单创建
     *
     * @ApiMethod (POST)
     * @param string $voucher_id 体验券ID
     */
    public function addorder()
    {
        $this->verifyUser();
        $post = $this->request->post();
        $voucher_id = $this->request->post('voucher_id');
        if (!$voucher_id) {
            $this->error(__('parameter error'));
        }
        //体验券
        $voucher_data = (new ModelVoucher())->where('id',$voucher_id)->find();
        if(!$voucher_data){
            $this->error(__('体验券不存在'));
        }
        //活动是否开启
        $activity = (new Activity())->where('id',$voucher_data['activity_id'])->field('status')->find();
        if($activity['status'] == 0){
            $this->error(__('活动未开启'));
        }
        //创建体验券订单
        $payinfo = (new Voucherorder())->addorder($post,$voucher_data,$this->userInfo);
        if(!$payinfo){
            $this->error(__('支付失败'));
        }
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
