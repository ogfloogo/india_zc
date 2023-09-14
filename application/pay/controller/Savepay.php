<?php

namespace app\pay\controller;

use app\api\controller\Controller;
use app\pay\model\Savepay as ModelSavepay;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\model\User;
use app\pay\model\Ppay as ModelPpay;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * Ppay
 */
class Savepay extends Controller
{

    /**
     * 代收回调
     */
    public function paynotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('支付回调_data', $data, 'Savepayhd');
        (new ModelSavepay())->paynotify(json_decode($data,true));
        exit('ok');
    }

    /**
     * 代付回调
     */
    public function paydainotify()
    {
        $data = file_get_contents("php://input");
        Log::mylog('提现回调_data', $data, 'Savepaydfhd');
        (new ModelSavepay())->paydainotify(json_decode($data,true));
        exit('ok');
    }

    /**
     * 代收回调
     */
    public function paynotifytest()
    {
        $data = $_POST;
        Log::mylog('支付回调_data', $data, 'Ppayhd');
        (new ModelPpay())->paynotifytest($data);
        exit('success');
    }
}
