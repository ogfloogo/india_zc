<?php

namespace app\admin\model\groupbuy;

use think\Model;
use traits\model\SoftDelete;

class UserPopupMessage extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'user_popup_message';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'sendtime_text',
        'status_text',
        'read_status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getReadStatusList()
    {
        return ['0' => __('Read_status 0'), '1' => __('Read_status 1')];
    }


    public function getSendtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['sendtime']) ? $data['sendtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getReadStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['read_status']) ? $data['read_status'] : '');
        $list = $this->getReadStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setSendtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }
    
    public function user()
    {
        return $this->belongsTo('\app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
