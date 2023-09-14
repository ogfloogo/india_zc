<?php

namespace app\api\model;

use app\admin\model\Admin;
use think\cache\driver\Redis;
use think\Model;
use think\Config;

/**
 * 首页轮播图
 */
class Agent extends Model
{
    public function getIdByCode($code)
    {
        if (!$code) {
            return false;
        }
        return $this->where(['code' => $code])->value('id');
    }

    public function getCodeById($id)
    {
        if (!$id) {
            return false;
        }
        return $this->where(['id' => $id])->value('code');
    }

    public function getInfoById($id)
    {
        if (!$id) {
            return false;
        }
        return $this->where(['id' => $id])->find();
    }

    public function checkAssign()
    {
        $rate = intval(Config::get("site.agent_rate"));
        $total = $this->getTotalUserCount();
        if (($total % 100) < $rate) {
            return true;
        }
        return false;
    }

    public function getAssignAgentId()
    {
        if (!$this->checkAssign()) {
            return 0;
        }
        $assignAgentIds = (new Admin())->where(['status' => 'normal', 'agent_id' => ['GT', 0], 'auto_assign' => 1])->column('agent_id');
        // print_r($assignAgentIds);
        sort($assignAgentIds);
        // print_r($assignAgentIds);

        if ($assignAgentIds) {
            $idx = $this->getAssignCount() % count($assignAgentIds);
            // print_r($idx);

            return $assignAgentIds[$idx];
        }
        return 0;
    }

    public function getTotalUserCountKey($date = '')
    {
        return 'total_count';
    }

    public function getAssignCountKey($date = '')
    {
        return 'assgin_count';
    }

    public function getAssignCount($date = '')
    {
        $redis = new Redis();
        $key = $this->getAssignCountKey($date);
        return $redis->handler()->incr($key);
    }

    public function getTotalUserCount($date = '')
    {
        $redis = new Redis();
        $key = $this->getTotalUserCountKey($date);
        return $redis->handler()->incr($key);
    }
}
