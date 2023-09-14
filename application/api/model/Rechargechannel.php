<?php

namespace app\api\model;

use app\pay\model\Ppay;
use app\pay\model\Rpay;
use app\pay\model\Shpay;
use app\pay\model\Wepay;
use app\pay\model\Wowpay;

use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

/**
 * 代收渠道
 */
class Rechargechannel extends Model
{
    protected $name = 'recharge_channel';

    /**
     * 代收渠道
     */
    public function findchannel($order_id, $price, $userinfo, $channel_info)
    {
        $str = "\app\pay\model"."\\".ucfirst($channel_info['model']);
        $model = new $str;
        return $model->pay($order_id, $price, $userinfo, $channel_info);
    }
}
