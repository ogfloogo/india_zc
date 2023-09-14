<?php

namespace app\admin\model\user;

use think\Model;


class AllUserMoneyLog extends Model
{





    // 表名
    protected $name = 'user_money_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text'
    ];



    public function getTypeList()
    {
        return [
            '1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3'), '4' => __('Type 4'), '5' => __('Type 5'), '6' => __('Type 6'), '7' => __('Type 7'), '8' => __('Type 8'), '9' => __('Type 9'), '10' => __('Type 10'), '11' => __('Type 11'), '12' => __('Type 12'), '13' => __('Type 13'), '14' => __('Type 14'), '18' => __('Type 18'), '19' => __('Type 19'), '20' => __('Type 20'), '21' => __('Type 21'), '22' => __('Type 22')
        ];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('\app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
