<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\api\model\payment\rpay as Paymentrpay;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Startpay as ModelStartpay;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * Startpay
 */
class Startpay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'startpayhd');
        (new ModelStartpay())->paynotify($data);
        exit('SUCCESS');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = $_POST;
        Log::mylog('提现回调_data', $data, 'startpaydfhd');
        (new ModelStartpay())->paydainotify($data);
        exit('SUCCESS');
    }

    /**
     * 代收回调
     */
    // public function paynotifytest()
    // {
    //     $data = $_POST;
    //     Log::mylog('支付回调_data', $data, 'startpayhd');
    //     (new Modelrpay())->paynotifytest($data);
    //     exit('success');
    // }
}
