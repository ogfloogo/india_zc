<?php

namespace app\admin\model\activity;

use app\admin\model\CacheModel;
use think\Model;
use traits\model\SoftDelete;

class ActivityTask extends CacheModel
{

    use SoftDelete;



    // 表名
    protected $name = 'activity_task';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'date_type_text',
        'is_auto_get_text',
        'is_auto_prize_text',
        'status_text'
    ];

    public $cache_prefix = 'new:task:';


    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3'), '4' => __('Type 4'), '5' => __('Type 5'), '6' => __('Type 6'), '7' => __('Type 7'), '8' => __('Type 8'), '9' => __('Type 9'), '10' => __('Type 10')];
    }

    public function getDateTypeList()
    {
        return ['0' => __('Date_type 0'), '1' => __('Date_type 1')];
    }

    public function getIsAutoGetList()
    {
        return ['0' => __('Is_auto_get 0'), '1' => __('Is_auto_get 1')];
    }

    public function getIsAutoPrizeList()
    {
        return ['0' => __('Is_auto_prize 0'), '1' => __('Is_auto_prize 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDateTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['date_type']) ? $data['date_type'] : '');
        $list = $this->getDateTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsAutoGetTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_auto_get']) ? $data['is_auto_get'] : '');
        $list = $this->getIsAutoGetList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsAutoPrizeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_auto_prize']) ? $data['is_auto_prize'] : '');
        $list = $this->getIsAutoPrizeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function Userlevel()
    {
        return $this->belongsTo('\app\admin\model\userlevel\UserLevel', 'level', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
