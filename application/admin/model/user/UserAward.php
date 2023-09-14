<?php

namespace app\admin\model\user;

use think\Model;
use traits\model\SoftDelete;

class UserAward extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'user_award';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'recharge_time_text',
        'status_text'
    ];



    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getRechargeTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['recharge_time']) ? $data['recharge_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setRechargeTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function user()
    {
        return $this->belongsTo('\app\admin\model\User', 'source', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function userTotal()
    {
        return $this->belongsTo('\app\admin\model\userlevel\UserTotal', 'source', 'user_id', [], 'LEFT')->setEagerlyType(0);
    }
}
