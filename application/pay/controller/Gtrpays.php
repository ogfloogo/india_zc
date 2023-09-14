<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\api\model\payment\Gtrpays as PaymentGtrpays;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Gtrpays as ModelGtrpays;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * Gtrpays
 */
class Gtrpays extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('支付回调_data', $data, 'Gtrpayshd');
        (new ModelGtrpays())->paynotify(json_decode($data,true));
        exit('success');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('提现回调_data', $data, 'Gtrpaysdfhd');
        (new ModelGtrpays())->paydainotify(json_decode($data,true));
        exit('success');
    }

    /**
     * 代收回调
     */
    public function paynotifytest()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Gtrpayshd');
        (new ModelGtrpays())->paynotifytest($data);
        exit('success');
    }
}
