<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\api\model\payment\Xdpay as PaymentXdpay;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Xdpay as ModelXdpay;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * Xdpay
 */
class Xdpay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('支付回调_data', $data, 'Xdpayhd');
        (new ModelXdpay())->paynotify(json_decode($data,true));
        exit('success');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('提现回调_data', $data, 'Xdpaydfhd');
        (new ModelXdpay())->paydainotify(json_decode($data,true));
        exit('success');
    }

    /**
     * 代收回调
     */
    public function paynotifytest()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Xdpayhd');
        (new ModelXdpay())->paynotifytest($data);
        exit('success');
    }
}
