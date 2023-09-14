<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\api\model\payment\Mpays as PaymentMpays;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Mpays as ModelMpays;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * Mpays
 */
class Mpays extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Mpayshd');
        (new ModelMpays())->paynotify($data);
        exit('success');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = $_POST;
        Log::mylog('提现回调_data', $data, 'Mpaysdfhd');
        (new ModelMpays())->paydainotify($data);
        exit('success');
    }

    /**
     * 代收回调
     */
    public function paynotifytest()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Mpayshd');
        (new ModelMpays())->paynotifytest($data);
        exit('success');
    }
}
