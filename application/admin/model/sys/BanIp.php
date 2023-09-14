<?php

namespace app\admin\model\sys;

use app\admin\model\CacheModel;
use think\Model;


class BanIp extends CacheModel
{

    

    public $cache_prefix = 'ban_ip:';


    // 表名
    protected $name = 'ban_ip';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







}
