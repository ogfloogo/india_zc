<?php

namespace app\api\model;
use app\api\controller\controller;
use app\api\controller\Paydemo;
use app\api\model\recharge\Pay;
use app\pay\model\Paydemo as ModelPaydemo;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\Log;
/**
 * 体验券订单
 */
class Voucher extends Model
{
    protected $name = 'vip_voucher';
}
