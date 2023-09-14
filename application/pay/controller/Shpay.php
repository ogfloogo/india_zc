<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\api\model\payment\Shpay as PaymentShpay;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Shpay as ModelShpay;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * Shpay
 */
class Shpay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('支付回调_data', $data, 'Shpayhd');
        (new ModelShpay())->paynotify(json_decode($data,true));
        exit('OK');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('提现回调_data', $data, 'Shpaydfhd');
        (new ModelShpay())->paydainotify(json_decode($data,true));
        exit('OK');
    }

    /**
     * 代收回调
     */
    public function paynotifytest()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Shpayhd');
        (new ModelShpay())->paynotifytest($data);
        exit('success');
    }
}
