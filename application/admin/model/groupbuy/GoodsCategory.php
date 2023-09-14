<?php

namespace app\admin\model\groupbuy;

use app\admin\model\CacheModel;
use app\admin\model\userlevel\UserLevel;
use app\api\model\Usercategory;
use think\cache\driver\Redis;
use think\Model;
use traits\model\SoftDelete;

class GoodsCategory extends CacheModel
{

    use SoftDelete;



    // 表名
    protected $name = 'goods_category';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'now_pool_num',
        'daily_win_man',
        'level_name'
    ];

    public $cache_prefix = 'zclc:category:';
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

    public function getNowPoolNumAttr($value, $data)
    {
        $id= $data['id'];
        $pool_key = (new Usercategory())->getPoolKey($id);
        $num = $this->redisInstance->handler()->sCard($pool_key);
        return intval($num);
    }

    public function getDailyWinManAttr($value, $data)
    {
        $id= $data['id'];
        $win_pool_key = (new Usercategory())->getWinPoolKey($id);
        $num = $this->redisInstance->handler()->sCard($win_pool_key);
        return intval($num);
    }

    public function getLevelNameAttr($value, $data)
    {
        return (new UserLevel())->where(['id'=>$data['level_id']])->value('name');
    }
}
