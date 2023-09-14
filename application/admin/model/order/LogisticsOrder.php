<?php

namespace app\admin\model\order;

use app\admin\model\groupbuy\Goods;
use app\admin\model\user\UserAddress;
use think\Model;
use traits\model\SoftDelete;

class LogisticsOrder extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'logistics_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'good_name',
        'order_price',
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

    public function order()
    {
        return $this->belongsTo('\app\admin\model\order\Order', 'order_id', 'order_id', [], 'LEFT')->setEagerlyType(0);
    }

    public function warehouse()
    {
        return $this->belongsTo('\app\admin\model\user\UserWarehouse', 'wid', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function address()
    {
        return $this->belongsTo('\app\admin\model\user\UserAddress', 'address_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getGoodNameAttr($value, $data)
    {
        $good_id = (new Order())->where(['id' => $data['order_id']])->value('good_id');
        $name = (new Goods())->where(['id' => $good_id])->value('name');
        return isset($name) ? $name : '';
    }

    public function getOrderPriceAttr($value, $data)
    {
        $amount = (new Order())->where(['id' => $data['order_id']])->value('amount');

        return isset($amount) ? $amount : 0;
    }

    public function getAddressInfoAttr($value, $data)
    {
        $info = (new UserAddress())->where(['id' => $data['address_id']])->find();

        return isset($info) ? $info['address'].','.$info['village'].','.$info['county'].','.$info['city'].','.$info['province'].','.$info['postcode'].','.$info['mobile'].','.$info['name'] : '';
    }
}
