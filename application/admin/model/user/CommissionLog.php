<?php

namespace app\admin\model\user;

use think\Model;
use traits\model\SoftDelete;

class CommissionLog extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'commission_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [];

    public function getLevelList()
    {
        return ['1' => __('Level 1'), '2' => __('Level 2'), '3' => __('Level 3')];
    }

    public function fromUser()
    {
        return $this->belongsTo('\app\admin\model\User', 'from_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function toUser()
    {
        return $this->belongsTo('\app\admin\model\User', 'to_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function order()
    {
        return $this->belongsTo('\app\admin\model\order\Order', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function setTableName($user_id){
        $mod = 1000;
        $table_number = ceil($user_id / $mod);
        if($user_id<=1000){
            $tb_num = ceil($user_id/100);
            $table_name = "fa_commission_log_1_" . $tb_num;
        }else{
            $table_name = "fa_commission_log_" . $table_number;
        }
        // $table_name = "fa_commission_log_" . $table_number;
        $this->setTable($table_name);
        return $this;
    }
}
