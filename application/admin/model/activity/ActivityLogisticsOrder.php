<?php

namespace app\admin\model\activity;

use app\admin\model\user\UserAddress;
use app\api\model\activity\UserDrawLog;
use think\Model;
use traits\model\SoftDelete;

class ActivityLogisticsOrder extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'activity_logistics_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'prize_name',
        'address_info'
    ];



    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('\app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function warehouse()
    {
        return $this->belongsTo('\app\admin\model\activity\ActivityWarehouse', 'warehouse_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function address()
    {
        return $this->belongsTo('\app\admin\model\user\UserAddress', 'address_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getPrizeNameAttr($value, $data)
    {
        $model =  (new UserDrawLog());
        $model->setTableName($data['user_id']);
        $draw_info = $model->where(['id' => $data['user_draw_id']])->find();
        $name = $draw_info['prize_name'];
        return isset($name) ? $name : '';
    }

    public function getAddressInfoAttr($value, $data)
    {
        $info = (new UserAddress())->where(['id' => $data['address_id']])->find();

        return isset($info) ? $info['address'] . ',' . $info['village'] . ',' . $info['county'] . ',' . $info['city'] . ',' . $info['province'] . ',' . $info['postcode'] . ',' . $info['mobile'] . ',' . $info['name'] : '';
    }
}
