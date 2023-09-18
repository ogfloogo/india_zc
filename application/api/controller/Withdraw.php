<?php

namespace app\api\controller;

use app\api\model\User;
use app\api\model\Usercash;
use app\api\model\Userteam;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Log;
use app\common\library\Sms;
use think\Config;

/**
 * 提现
 */
class Withdraw extends Controller
{

    /**
     *用户提现
     *
     * @ApiMethod (POST)
     * @param string $amount 提现金额
     * @param string $bank_id 银行卡ID
     */
    public function userwithdraw()
    {
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $post = $this->request->post();
        $price = $this->request->post('price');
        $bank_id = $this->request->post('bank_id');
        $password = $this->request->post('password');
        if (!$price || !$bank_id || !$password) {
            $this->error(__('parameter error'));
        }
        //提现密码验证
        if (md5($password) != $userinfo['withdraw_password']) {
            $this->error(__('Wrong withdrawal password'));
        }
        //余额判断
        $balance = $userinfo['money'];
        if ($price > $balance) {
            $this->error(__('Insufficient withdrawable balance'));
        }
        //最低提现金额
        $min_withdraw = Config::get("site.min_withdraw");
        if ($price < $min_withdraw) {
            $this->error(__('The Min withdrawal amount is') . $min_withdraw);
        }
        //每日提现次数
        // $time = Time::today();
        // $daily_withdraw_number = Config::get("site.daily_withdraw_number");
        // $my_withdraw_number = (new Usercash())->where('user_id', $this->uid)->where('createtime', 'between', [$time[0], $time[1]])->where('deletetime', null)->count();
        // if($userinfo['mobile'] != "968968968"){
        //     if ($my_withdraw_number >= $daily_withdraw_number) {
        //         $this->error(__('Withdraw up to 3 times a day') . $daily_withdraw_number);
        //     }
        // }
        // $user_withdraw_count = (new Usercash())->where('user_id', $this->uid)->where('deletetime', null)->count();
        // if (!$user_withdraw_count) {
        //     // ($userinfo['money']-$price) < 99
        //     if (bccomp(bcsub($userinfo['money'], $price, 2), Config::get('site.user_register_reward'), 2) == -1) {
        //         $this->error(__('Your remaining balance needs to be greater than 99 pesos for the first withdrawal'));
        //     }
        // }

        $return = (new Usercash())->userwithdraw($post, $userinfo);
        if (!$return) {
            $this->error(__('operation failure'));
        }
        $this->success(__('operation successfully'));
    }

    /**
     *提现参数
     *
     * @ApiMethod (POST)
     * @param string $amount 提现金额
     */
    public function setting()
    {
        $this->verifyUser();
        //提现手续费
        $list["withdraw_fee"] = Config::get("site.withdraw_fee");
        //最低提现金额
        $list["min_withdraw"] = Config::get("site.min_withdraw");
        //每日提现次数
        $list["daily_withdraw_number"] = Config::get("site.daily_withdraw_number");
        //可提现余额
        $list["balance"] = ($this->userInfo)['money'];
        //提现规则
        $list["withdraw_rule"] = config('site.withdraw_rule');
        //审核中金额
        // $list["audit_money"] = (new Usercash())->where('user_id', $this->uid)->where('status', 0)->sum('price');
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 提现密码修改
     *
     */
    public function resetpassword()
    {
        $this->verifyUser();
        $mobile = $this->request->post('mobile');
        $password = $this->request->post('password');
        $code = $this->request->post('code');
        if (!is_numeric($password)) {
            $this->error(__('The password must be the number'));
        }
        if (!$mobile || !$code) {
            $this->error(__('parameter error'));
        }
        //检测验证码
        $ret = Sms::resetwithdrawcode($mobile, $code);
        if (!$ret) {
            $this->error(__('OTP is incorrect'));
        }
        //密码修改
        (new User())->where('mobile', $mobile)->update(['withdraw_password' => md5($password)]);
        //更新用户信息
        (new User())->refresh($this->uid);
        $this->success(__('operate successfully'));
    }

    /**
     * 提现银行编码列表
     */
    public function bankcodelist()
    {
        $list = Config::get("site.bank_code");
        $this->success(__('The request is successful'), json_decode($list, true));
    }

    /**
     * 提现记录
     */
    public function withdrawlog()
    {
        $this->verifyUser();
        $post = $this->request->post();
        $page = $this->request->post('page');
        if (!$page) {
            $this->error(__('parameter error'));
        }
        $list = (new Usercash())->withdrawlog($post, $this->uid, $this->language);
        $this->success(__('The request is successful'), $list);
    }
}
