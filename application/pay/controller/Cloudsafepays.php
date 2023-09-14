<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\pay\model\Cloudsafepays as ModelCloudsafepays;
use app\pay\model\Wowpay as ModelWowpay;
use think\Log;

/**
 * Cloudsafepay
 */
class Cloudsafepays extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Cloudsafepayshd');
        (new ModelCloudsafepays())->paynotify($data);
        exit('success');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = $_POST;
        Log::mylog('提现回调_data', $data, 'Cloudsafepaysdfhd');
        (new ModelCloudsafepays())->paydainotify($data);
        exit('success');
    } 
}
