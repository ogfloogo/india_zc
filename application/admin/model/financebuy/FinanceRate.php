<?php

namespace app\admin\model\financebuy;

use app\admin\model\CacheModel;
use think\Model;


class FinanceRate extends CacheModel
{





    // 表名
    protected $name = 'finance_rate';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [];

    public $cache_prefix = 'new:finance_rate:';
}
