<?php

namespace app\admin\model\user;

use think\Model;
use traits\model\SoftDelete;

class UserAddress extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'user_address';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'is_default_text',
        'status_text'
    ];
    

    
    public function getIsDefaultList()
    {
        return ['0' => __('Is_default 0'), '1' => __('Is_default 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getIsDefaultTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_default']) ? $data['is_default'] : '');
        $list = $this->getIsDefaultList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
