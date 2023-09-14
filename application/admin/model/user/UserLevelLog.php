<?php

namespace app\admin\model\user;

use think\Model;


class UserLevelLog extends Model
{

    

    

    // 表名
    protected $name = 'user_level_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'up_text'
    ];
    

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3'), '4' => __('Type 4'), '5' => __('Type 5'), '6' => __('Type 6'), '7' => __('Type 7'), '8' => __('Type 8'), '9' => __('Type 9'), '10' => __('Type 10'), '11' => __('Type 11'), '12' => __('Type 12')];
    }

    public function getUpList()
    {
        return ['0' => __('Up 0'), '1' => __('Up 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getUpTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['up']) ? $data['up'] : '');
        $list = $this->getUpList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
