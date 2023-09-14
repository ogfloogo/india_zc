<?php

namespace app\admin\model\robot;

use app\admin\model\user\UserTeam;
use think\Model;


class UserTarget extends Model
{





    // 表名
    protected $name = 'user_target';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'addrobottime_text',
        'addordertime_text',
        'group_number'
    ];



    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getAddrobottimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['addrobottime']) ? $data['addrobottime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getAddordertimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['addordertime']) ? $data['addordertime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setAddrobottimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setAddordertimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function user()
    {
        return $this->belongsTo('\app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getGroupNumberAttr($value, $data)
    {
        $count = (new UserTeam())->where(['user_id' => $data['user_id'], 'level' => ['GT', 0]])->count();
        return $count;
    }
}
