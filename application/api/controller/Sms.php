<?php

namespace app\api\controller;

use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * 手机短信接口
 */
class Sms extends Controller
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    const appid = 'jP6WU9kL'; //应用ID
    const account = 'XsEnerM6';
    const KEY = 'u3M2z8fW';
    /**
     *发送验证码-用户注册
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     */
    public function send()
    {
        $redis = new Redis();
        $mobile = $this->request->post("mobile");
        if (!$mobile) {
            $this->error(__('The phone number cannot be empty'));
        }
        $hmd = $this->hmd($mobile);
        if(!$hmd){
            $this->success(__('send successfully'));
        }
        $userinfo = (new ModelUser())->where('mobile', $mobile)->find();
        if ($userinfo) {
            $this->error(__('User already exists'));
        }
        //生成验证码
        $mobile_check = substr($mobile, 0, 1);
        if ($mobile_check == 0) {
            $this->error(__('The first digit of the phone number cannot be 0'));
        }
        $redis->handler()->select(1);
        // $last = $redis->handler()->get("zclc:register:" . $mobile);
        // if ($last) {
        //     $this->error(__('Sending frequent'));
        // }
        $code = Random::numeric(6);
        $send = $this->sendCodeV3($mobile, $code);
        if (!$send) {
            $this->error(__('send failed'));
        }
        $last = $redis->handler()->set("zclc:register:" . $mobile, $code, 600);
        $this->success(__('send successfully'));
    }

    /**
     * 身份认证
     */
    public function authentication()
    {
        $redis = new Redis();
        $mobile = $this->request->post("mobile");
        if (!$mobile) {
            $this->error(__('The phone number cannot be empty'));
        }
        $hmd = $this->hmd($mobile);
        if(!$hmd){
            $this->success(__('send successfully'));
        }
        $userinfo = (new ModelUser())->where('mobile', $mobile)->find();
        if (!$userinfo) {
            $this->error(__('The phone number is not registered'));
        }
        $mobile_check = substr($mobile, 0, 1);
        if ($mobile_check == 0) {
            $this->error(__('The first digit of the phone number cannot be 0'));
        }
        $redis->handler()->select(1);
        $last = $redis->handler()->get("zclc:authentication:" . $mobile);
        if ($last) {
            $this->error(__('Sending frequent'));
        }
        $code = Random::numeric(6);
        $send = $this->sendCodeV3($mobile, $code);
        if (!$send) {
            $this->error(__('send failed'));
        }
        $last = $redis->handler()->set("zclc:authentication:" . $mobile, $code, 600);
        $this->success(__('send successfully'));
    }

    /**
     *发送验证码-重置密码
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     */
    public function sendreset()
    {
        // $this->verifyUser();
        $redis = new Redis();
        $mobile = $this->request->post("mobile");
        if (!$mobile) {
            $this->error(__('The phone number cannot be empty'));
        }
        $hmd = $this->hmd($mobile);
        if(!$hmd){
            $this->success(__('send successfully'));
        }
        $userinfo = (new ModelUser())->where('mobile', $mobile)->find();
        if (!$userinfo) {
            $this->error(__('The user does not exist'));
        }
        //生成验证码
        $mobile_check = substr($mobile, 0, 1);
        if ($mobile_check == 0) {
            $this->error(__('The first digit of the phone number cannot be 0'));
        }
        $redis->handler()->select(1);
        $last = $redis->handler()->get("zclc:resetpassword:" . $mobile);
        if ($last) {
            $this->error(__('Sending frequent'));
        }
        $code = Random::numeric(6);
        $send = $this->sendCodeV3($mobile, $code);
        if (!$send) {
            $this->error(__('send failed'));
        }
        $last = $redis->handler()->set("zclc:resetpassword:" . $mobile, $code, 600);
        $this->success(__('send successfully'));
    }

    /**
     *提现-重置密码
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     */
    public function sendwithdraw()
    {
        // $this->verifyUser();
        $redis = new Redis();
        $mobile = $this->request->post("mobile");
        if (!$mobile) {
            $this->error(__('The phone number cannot be empty'));
        }
        $hmd = $this->hmd($mobile);
        if(!$hmd){
            $this->success(__('send successfully'));
        }
        $userinfo = (new ModelUser())->where('mobile', $mobile)->find();
        if (!$userinfo) {
            $this->error(__('The user does not exist'));
        }
        //生成验证码
        $mobile_check = substr($mobile, 0, 1);
        if ($mobile_check == 0) {
            $this->error(__('The first digit of the phone number cannot be 0'));
        }
        $redis->handler()->select(1);
        $last = $redis->handler()->get("zclc:resetwithdraw:" . $mobile);
        if ($last) {
            $this->error(__('Sending frequent'));
        }
        $code = Random::numeric(6);
        $send = $this->sendCodeV($mobile, $code);
        if (!$send) {
            $this->error(__('send failed'));
        }
        $last = $redis->handler()->set("zclc:resetwithdraw:" . $mobile, $code, 600);
        $this->success(__('send successfully'));
    }

    protected function sendCodeV3($phone, $code)
    {
        // $text = "[ALPHA] Your OTP  is " . $code . ". The OTP is valid for 10 min.";
        $text = "[ALPHA]Your otp is ".$code.", valid within 10 minutes. do not tell others the otp.";
        $r = $this->sendV3($phone, $text);
        $r = json_decode($r, true);
        if ($r['status'] != 0) {
            return false;
        }
        return $r;
    }

    protected function sendCodeV($phone, $code)
    {
        $text = "[ALPHA] You are applying for withdrawal verification code " . $code . ",Please do not share this verification code with others to avoid losses.";
        $r = $this->sendV3($phone, $text);
        $r = json_decode($r, true);
        if ($r['status'] != 0) {
            return false;
        }
        return $r;
    }

    /**
     * 
     * v3接口
     */
    protected function sendV3($phone, $text)
    {
        $time  = time();
        $data = [
            'appId' => $this::appid,
            'numbers' => '91' . $phone,
            'content' => $text,
        ];
        $url = 'https://api.onbuka.com/v3/sendSms';

        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8;',
            'Sign:' . $this->makesign($time),
            'Timestamp:' . $time,
            'Api-Key:' . self::account
        ];
        $return = $this->vRequestV3($url, json_encode($data), 1, $header);
        Log::mylog('return', $return, 'sms');
        return $return;
    }

    /**
     * 
     * @return intval
     */
    protected function makesign($time)
    {
        $str = $this::account . $this::KEY . $time;
        return md5($str);
    }

    /**
     * 模拟提交数据函数
     */
    protected function vRequestV3($url, $data = '', $ispost = 0, $headerArr = [], $showHeader = false)
    {

        // $cookiePath = dirname(APPLICATION_PATH) . '/log/app/cookie_'.md5(basename(__FILE__)).'.txt';

        $curl = curl_init(); // 启动一个CURL会话    
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址                
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查    
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // 从证书中检查SSL加密算法是否存在
        // 模拟用户使用的浏览器    
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转    
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        // curl_setopt($curl, CURLOPT_COOKIEJAR, $cookiePath); 
        // curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded;charset=utf-8;'));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArr);

        if ($ispost) {
            curl_setopt($curl, CURLOPT_POST, $ispost); // 发送一个常规的Post请求
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        }
        // curl_setopt($curl, CURLOPT_COOKIEFILE, $cookiePath); // 读取上面所储存的Cookie信息    
        curl_setopt($curl, CURLOPT_TIMEOUT, 100); // 设置超时限制防止死循环   
        curl_setopt($curl, CURLOPT_HEADER, $showHeader); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回    
        $tmpInfo = curl_exec($curl); // 执行操作    
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);
        }
        curl_close($curl); // 关键CURL会话    
        return $tmpInfo; // 返回数据    
    }

    /**
     * 黑名单
     * 
     */
    public function hmd($mobile){
        $array = ["9771666120","9997900975","9162130032","9064144055","9614746436","9104328794","9086943481","9276322922"];
        if(in_array($mobile,$array)){
            return false;
        }
        return true;
    }
}
