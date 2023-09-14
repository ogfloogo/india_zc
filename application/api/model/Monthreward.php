<?php

namespace app\api\model;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

/**
 * FAQ
 */
class Monthreward extends Model
{
    protected $name = 'month_reward';

}
