<?php

namespace app\admin\model\robot;

use think\Model;


class UserTargetLevel extends Model
{

    

    

    // 表名
    protected $name = 'user_target_level';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'up_text'
    ];
    

    
    public function getUpList()
    {
        return ['0' => __('Up 0'), '1' => __('Up 1')];
    }


    public function getUpTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['up']) ? $data['up'] : '');
        $list = $this->getUpList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
