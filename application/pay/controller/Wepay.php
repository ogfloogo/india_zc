<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\api\model\payment\Wepay as PaymentWepay;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Wepay as ModelWepay;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * wepay
 */
class Wepay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'wepayhd');
        (new ModelWepay())->paynotify($data);
        exit('success');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = $_POST;
        Log::mylog('提现回调_data', $data, 'wepaydfhd');
        (new ModelWepay())->paydainotify($data);
        exit('success');
    }

    /**
     * 代收回调
     */
    // public function paynotifytest()
    // {
    //     $data = $_POST;
    //     Log::mylog('支付回调_data', $data, 'wepayhd');
    //     (new ModelWepay())->paynotifytest($data);
    //     exit('success');
    // }
}
