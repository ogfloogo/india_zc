<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\pay\model\Wowpays as ModelWowpays;
use think\Log;

/**
 * Wowpays
 */
class Wowpays extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'wowpayshd');
        (new ModelWowpays())->paynotify($data);
        exit('success');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = $_POST;
        Log::mylog('提现回调_data', $data, 'wowpaysdfhd');
        (new ModelWowpays())->paydainotify($data);
        exit('success');
    } 
}
