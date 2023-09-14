<?php

namespace app\admin\model\finance;

use app\admin\model\User;
use app\admin\model\userlevel\UserTotal;
use think\Model;
use traits\model\SoftDelete;

class UserRecharge extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'user_recharge';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'paytime_text',
        'sid_name',
        'status_text',
        'total_withdrawals'
    ];

    public function getTotalWithdrawalsAttr($value, $data)
    {
        $total_withdrawals = (new UserTotal())->where(['user_id' => $data['user_id']])->value('total_withdrawals');
        return $total_withdrawals;
    }
    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paytime']) ? $data['paytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function rechargeChannel()
    {
        return $this->belongsTo('\app\admin\model\finance\RechargeChannel', 'channel', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo('\app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getSidNameAttr($value, $data)
    {
        $sid = (new User())->where(['id' => $data['user_id']])->value('sid');
        $name = (new User())->where(['id' => $sid])->value('mobile');
        return $name;
    }


}
