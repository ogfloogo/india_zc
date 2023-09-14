<?php

namespace app\admin\model\user;

use think\Model;


class Usertask extends Model
{

    

    

    // 表名
    protected $name = 'user_task';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'category_text',
        'type_text',
        'is_receive_text'
    ];
    

    
    public function getCategoryList()
    {
        return ['1' => __('Category 1'), '2' => __('Category 2'), '3' => __('Category 3')];
    }

    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3'), '4' => __('Type 4')];
    }

    public function getIsReceiveList()
    {
        return ['0' => __('Is_receive 0'), '1' => __('Is_receive 1')];
    }


    public function getCategoryTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['category']) ? $data['category'] : '');
        $list = $this->getCategoryList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsReceiveTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_receive']) ? $data['is_receive'] : '');
        $list = $this->getIsReceiveList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
