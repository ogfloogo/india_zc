<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\api\model\payment\metapay as Paymentmetapay;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Metapay as ModelMetapay;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * Metapay
 */
class Metapay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('支付回调_data', $data, 'metapayhd');
        (new ModelMetapay())->paynotify(json_decode($data,true));
        exit('SUCCESS');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('提现回调_data', $data, 'metapaydfhd');
        (new ModelMetapay())->paydainotify(json_decode($data,true));
        exit('SUCCESS');
    }

    /**
     * 代收回调
     */
    // public function paynotifytest()
    // {
    //     $data = $_POST;
    //     Log::mylog('支付回调_data', $data, 'metapayhd');
    //     (new Modelmetapay())->paynotifytest($data);
    //     exit('success');
    // }
}
