<?php

namespace app\common\model;

use think\Model;
use traits\model\SoftDelete;

class Floptimes extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'flop_times';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [

    ];
    

    







    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
