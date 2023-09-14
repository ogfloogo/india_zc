<?php

namespace app\admin\model\groupbuy;

use app\admin\model\CacheModel;
use think\Model;
use traits\model\SoftDelete;

class Goods extends CacheModel
{

    use SoftDelete;

    

    // 表名
    protected $name = 'goods';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'is_recommend_text'
    ];
    
    public $cache_prefix = 'zclc:good:';

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

    public function getIsRecommendList()
    {
        return ['0' => __('Is_recommend 0'), '1' => __('Is_recommend 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getIsRecommendTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_recommend']) ? $data['is_recommend'] : '');
        $list = $this->getIsRecommendList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function category()
    {
        return $this->belongsTo('\app\admin\model\groupbuy\GoodsCategory', 'category_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
