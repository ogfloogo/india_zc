<?php

namespace app\api\model;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Cache;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

class Appversion extends Model
{
    protected $name = 'app_version';
}
