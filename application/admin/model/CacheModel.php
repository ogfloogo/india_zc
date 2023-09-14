<?php

namespace app\admin\model;

use think\cache\driver\Redis;
use think\Model;

class CacheModel extends Model
{
    public $cache_prefix = '';
    public $redisInstance = null;

    public function initialize()
    {
        $this->redisInstance = new Redis();
    }

    public function setUserLevelCache($name,$ids, $level, $params = [], $is_del = false)
    {
//        if (empty($level)) {
//            return false;
//        }
        if (is_array($level)) {            
            return false;
        } else {
            $key = $this->buildIdKey($level,$name);
            $params['id'] = $ids;
            $params['level'] = $level;
            return $this->redisInstance->handler()->hMSet($key, $params);
        }
    }

    public function setLevelCache($name,$ids, $params = [], $is_del = false)
    {
        if (empty($ids)) {
            return false;
        }
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $key = $this->buildIdKey($id,$name);
                $params['id'] = $id;
                $this->redisInstance->handler()->hMSet($key, $params);
            }
            return true;
        } else {
            $key = $this->buildIdKey($ids,$name);
            $params['id'] = $ids;
            return $this->redisInstance->handler()->hMSet($key, $params);
        }
    }

    public function setLevelCacheIncludeDel($name,$ids, $params = [])
    {
        if (empty($ids)) {
            return false;
        }
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $key = $this->buildIdKey($id,$name);
                $params['id'] = $id;
                $this->redisInstance->handler()->hMSet($key, $params);
            }
            return true;
        } else {
            $key = $this->buildIdKey($ids,$name);
            $params['id'] = $ids;
            return $this->redisInstance->handler()->hMSet($key, $params);
        }
    }

    public function setSortedSetCache($name,$ids, $params = [], $set_id = '', $weigh = 0, $is_del = false)
    {
        $key = $this->buildSortedSetKey('set:' . $set_id,$name);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $this->redisInstance->handler()->zAdd($key, $weigh, $id);
            }
            return true;
        } else {
            $this->redisInstance->handler()->zAdd($key, $weigh, $ids);
        }
    }

    public function setRecommendSortedSetCache($name,$ids, $params = [], $set_id = '', $weigh = 0, $is_del = false)
    {
        $key = $this->buildSortedSetKey('rec:' . $set_id,$name);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $this->redisInstance->handler()->zAdd($key, $weigh, $id);
            }
            return true;
        } else {
            $this->redisInstance->handler()->zAdd($key, $weigh, $ids);
        }
    }

    public function setListCache()
    {
        $key = $this->buildListKey();
        $list = $this->where(['status' => 1, 'deletetime' => null])->select();
        $this->redisInstance->handler()->set($key, json_encode($list));
    }

    public function setSetCache($ids, $is_del = false)
    {
        $key = $this->buildSetKey();
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $this->redisInstance->handler()->sAdd($key, $id);
            }
            return true;
        } else {
            $this->redisInstance->handler()->sAdd($key, $ids);
        }
    }

    protected function buildIdKey($id,$name)
    {
        return "zclc:".$name.":". $id;
    }

    protected function buildSortedSetKey($id,$name)
    {
        return "zclc:".$name.':' . $id;
    }

    protected function buildSortedSetKeys($id,$name)
    {
        return "zclc:".$name.':set:' . $id;
    }

    protected function buildSortedRecKeys($id,$name)
    {
        return "zclc:".$name.':rec:' . $id;
    }

    protected function buildListKey()
    {
        return $this->cache_prefix . 'list';
    }

    protected function buildSetKey()
    {
        return $this->cache_prefix . 'set';
    }

    /**
     * 单个key删除
     */
    public function delkeys($name, $ids)
    {
        if (empty($ids)) {
            return false;
        }
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $key = $this->buildIdKey($id,$name);
                $this->redisInstance->handler()->del($key);
            }
            return true;
        } else {
            $key = $this->buildIdKey($ids,$name);
            $this->redisInstance->handler()->del($key);
        }
    }
    /**
     * 有序集合删除
     */
    public function delsetkeys($name,$ids, $params = [], $set_id = '', $weigh = 0, $is_del = false)
    {
        $key = $this->buildSortedSetKeys($set_id,$name);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if ((isset($params['status']) && !$params['status']) || (isset($params['deletetime']) && $params['deletetime']) || $is_del) {
                    return $this->redisInstance->handler()->zRem($key, $id);
                }
                $this->redisInstance->handler()->zAdd($key, $weigh, $id);
            }
            return true;
        } else {
            if ((isset($params['status']) && !$params['status']) || (isset($params['deletetime']) && $params['deletetime']) || $is_del) {
                return $this->redisInstance->handler()->zRem($key, $ids);
            }
            $this->redisInstance->handler()->zAdd($key, $weigh, $ids);
        }
    }

    /**
     * 有序集合删除
     */
    public function delreckeys($name,$ids, $params = [], $set_id = '', $weigh = 0, $is_del = false)
    {
        $key = $this->buildSortedRecKeys($set_id,$name);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if ((isset($params['status']) && !$params['status']) || (isset($params['deletetime']) && $params['deletetime']) || $is_del) {
                    return $this->redisInstance->handler()->zRem($key, $id);
                }
                $this->redisInstance->handler()->zAdd($key, $weigh, $id);
            }
            return true;
        } else {
            if ((isset($params['status']) && !$params['status']) || (isset($params['deletetime']) && $params['deletetime']) || $is_del) {
                return $this->redisInstance->handler()->zRem($key, $ids);
            }
            $this->redisInstance->handler()->zAdd($key, $weigh, $ids);
        }
    }
    
}
