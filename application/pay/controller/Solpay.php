<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\pay\model\Solpay as ModelSolpay;
use think\Log;

/**
 * Wowpay
 */
class Solpay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'solpayhd');
        (new ModelSolpay())->paynotify($data);
        exit('SUCCESS');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = $_POST;
        Log::mylog('提现回调_data', $data, 'solpaydfhd');
        (new ModelSolpay())->paydainotify($data);
        exit('SUCCESS');
    } 
}
