<?php

namespace app\admin\model\financebuy;

use think\Model;


class FinanceOrder extends Model
{

    

    

    // 表名
    protected $name = 'finance_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'buy_time_text',
        'earning_start_time_text',
        'earning_end_time_text',
        'status_text',
        'type_text'
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getIsRobotList()
    {
        return ['1' => __('Is_robot 1'), '0' => __('Is_robot 0')];
    }

    public function getPopularizeList()
    {
        return ['0' => __('Popularize 0'), '1' => __('Popularize 1'), '2' => __('Popularize 2')];
    }
    public function getBuyTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['buy_time']) ? $data['buy_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getEarningStartTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['earning_start_time']) ? $data['earning_start_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getEarningEndTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['earning_end_time']) ? $data['earning_end_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setBuyTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setEarningStartTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setEarningEndTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
