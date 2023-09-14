<?php

namespace app\admin\model\user;

use think\Model;


class UserMoneyLog extends Model
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


    public function setTableName($user_id)
    {
        $mod = 1000;
        $table_number = ceil($user_id / $mod);
        if ($user_id <= 1000) {
            $tb_num = ceil($user_id / 100);
            $table_name = "fa_user_money_log_1_" . $tb_num;
        } else {
            $table_name = "fa_user_money_log_" . $table_number;
        }
        // $table_name = "fa_user_money_log_" . $table_number;

        $this->setTable($table_name);
    }

    public function getTypeList()
    {
        return [
            '1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3'), '4' => __('Type 4'), '5' => __('Type 5'), '6' => __('Type 6'), '7' => __('Type 7'), '8' => __('Type 8'), '9' => __('Type 9'), '10' => __('Type 10'), '11' => __('Type 11'), '12' => __('Type 12'), '13' => __('Type 13'), '14' => __('Type 14'), '18' => __('Type 18'), '19' => __('Type 19'), '20' => __('Type 20'), '21' => __('Type 21'), '22' => __('Type 22'), '23' => __('Type 23'), '24' => __('Type 24'), '25' => __('Type 25'), '26' => __('Type 26')
            ,'27' => __('Type 27'),'28' => __('Type 28'),'29' => __('Type 29'),
        ];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }
}
