<?php

namespace app\api\model\payment;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

/**
 * Paydemo
 */
class Paydemo extends Model
{
    public function pay($price, $userinfo, $channel_info){
        return [
            'code' => 1,
            'payurl' => "https://www.baidu.com"
        ];
    }
}
