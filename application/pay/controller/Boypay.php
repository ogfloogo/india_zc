<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\pay\model\Boypay as ModelBoypay;
use think\Log;

/**
 * Wowpay
 */
class Boypay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('支付回调_data', $data, 'boypayhd');
        (new ModelBoypay())->paynotify(json_decode($data,true));
        exit('success');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('提现回调_data', $data, 'boypaydfhd');
        (new ModelBoypay())->paydainotify(json_decode($data,true));
        exit('success');
    } 
}
