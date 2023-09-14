<?php

namespace app\admin\model\activity;

use think\Model;
use traits\model\SoftDelete;
use app\admin\model\CacheModel;

class LuckyDrawPrize extends CacheModel
{

    use SoftDelete;

    

    // 表名
    protected $name = 'lucky_draw_prize';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text'
    ];
    public $cache_prefix = 'new:draw_prize:';


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


    public function Userlevel()
    {
        return $this->belongsTo('\app\admin\model\userlevel\UserLevel', 'level', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
