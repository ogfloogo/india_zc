<?php

namespace app\admin\model\userlevel;

use think\Model;


class UserTotal extends Model
{

    

    

    // 表名
    protected $name = 'user_total';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







}
