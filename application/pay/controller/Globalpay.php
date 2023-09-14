<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\pay\model\Globalpay as ModelGlobalpay;
use think\Log;

/**
 * Wowpay
 */
class Globalpay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Globalpayhd');
        (new ModelGlobalpay())->paynotify($data);
        exit('SUCCESS');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = $_POST;
        Log::mylog('提现回调_data', $data, 'Globalpaydfhd');
        (new ModelGlobalpay())->paydainotify($data);
        exit('SUCCESS');
    } 
}
