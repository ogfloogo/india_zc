<?php

namespace app\admin\model\userlevel;

use app\admin\model\CacheModel;
use think\cache\driver\Redis;
use traits\model\SoftDelete;

class UserLevel extends CacheModel
{

    use SoftDelete;


    // 表名
    protected $name = 'user_level';

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

    public $cache_prefix = 'zclc:level:';


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

    // public function setLevelCache($ids, $params = [], $is_del = false)
    // {
    //     if (empty($ids)) {
    //         return false;
    //     }
    //     if (is_array($ids)) {
    //         foreach ($ids as $id) {
    //             $key = 'zclc:level:' . $id;
    //             $redisInstance = new Redis();
    //             if ((isset($params['status']) && !$params['status']) || $is_del) {
    //                 $redisInstance->handler()->del($key);
    //             }

    //             $redisInstance->handler()->hMSet($key, $params);
    //         }
    //         return true;
    //     } else {
    //         $key = 'zclc:level:' . $ids;
    //         $redisInstance = new Redis();
    //         if ((isset($params['status']) && !$params['status']) || $is_del) {
    //             return $redisInstance->handler()->del($key);
    //         }

    //         return $redisInstance->handler()->hMSet($key, $params);
    //     }
    // }
}
