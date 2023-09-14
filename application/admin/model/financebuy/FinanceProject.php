<?php

namespace app\admin\model\financebuy;

use app\admin\model\sys\TeamLevel;
use think\Model;


class FinanceProject extends Model
{

    

    

    // 表名
    protected $name = 'finance_project';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'status_text',
        'popularize_text',
        'endtime_text',
        'robot_status_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }

    public function getPopularizeList()
    {
        return ['0' => __('Popularize 0'), '1' => __('Popularize 1'), '2' => __('Popularize 2')];
    }

    public function getProjecttypeList()
    {
        return ['0' => "否", '1' => "是"];
    }

    public function getRobotStatusList()
    {
        return ['1' => __('Robot_status 1'), '0' => __('Robot_status 0')];
    }

    public function getRecommendList()
    {
        return ['1' => __('Recommend 1'), '0' => __('Recommend 0')];
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


    public function getPopularizeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['popularize']) ? $data['popularize'] : '');
        $list = $this->getPopularizeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getEndtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['endtime']) ? $data['endtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getRobotStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['robot_status']) ? $data['robot_status'] : '');
        $list = $this->getRobotStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setEndtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function level()
    {
        return $this->belongsTo(TeamLevel::class, 'buy_level', 'level', [], 'LEFT')->setEagerlyType(0);
    }
}
