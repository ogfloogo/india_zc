<?php

namespace app\admin\model\finance;

use app\admin\model\User;
use app\admin\model\userlevel\UserTotal;
use think\Model;
use traits\model\SoftDelete;

class UserCash extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'user_cash';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'sid_name',
        'status_text',
        'user_recharge',
        'total_withdrawals'
    ];
    

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'),'1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4'), '5' => __('Status 5')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getSidNameAttr($value, $data)
    {
        $sid = (new User())->where(['id' => $data['user_id']])->value('sid');
        $name = (new User())->where(['id' => $sid])->value('mobile');
        return $name;
    }

    public function getUserRechargeAttr($value, $data)
    {
        $total_recharge = (new UserTotal())->where(['user_id' => $data['user_id']])->value('total_recharge');
        return $total_recharge;
    }

    public function getTotalWithdrawalsAttr($value, $data)
    {
        $total_withdrawals = (new UserTotal())->where(['user_id' => $data['user_id']])->value('total_withdrawals');
        return $total_withdrawals;
    }

    
    public function User()
    {
        return $this->belongsTo('\app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
