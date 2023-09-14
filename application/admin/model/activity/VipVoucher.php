<?php

namespace app\admin\model\activity;

use app\admin\model\CacheModel;
use app\admin\model\userlevel\UserLevel;
use think\Model;
use traits\model\SoftDelete;

class VipVoucher extends CacheModel
{

    use SoftDelete;



    // 表名
    protected $name = 'vip_voucher';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'vip_level',
        'activity_name',
        'max_level'
    ];

    public $cache_prefix = 'new:voucher:';

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }


    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getVipLevelAttr($value, $data)
    {
        $key = (new UserLevel())->cache_prefix . $data['level'];
        $name = $this->redisInstance->handler()->hGet($key, 'name');
        return ($name);
    }

    public function getActivityNameAttr($value, $data)
    {
        $key = (new Activity())->cache_prefix . $data['activity_id'];
        $name = $this->redisInstance->handler()->hGet($key, 'name');
        return ($name);
    }

    public function getMaxLevelAttr($value, $data)
    {
        $key = (new UserLevel())->cache_prefix . $data['buyer_level_id'];
        $name = $this->redisInstance->handler()->hGet($key, 'name');
        return ($name);
    }

    public function Userlevel()
    {
        return $this->belongsTo('\app\admin\model\userlevel\UserLevel', 'level', 'level', [], 'LEFT')->setEagerlyType(0);
    }

    public function Buyerlevel()
    {
        return $this->belongsTo('\app\admin\model\userlevel\UserLevel', 'buyer_level_id', 'level', [], 'LEFT')->setEagerlyType(0);
    }

    public function Activity()
    {
        return $this->belongsTo('\app\admin\model\activity\Activity', 'activity_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
