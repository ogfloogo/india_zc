<?php

namespace app\api\model;

use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;

/**
 * 用户等级日志
 */
class UserLevelLog extends Model
{
    public function addLog($extra)
    {
        if (empty($extra)) {
            return false;
        }
        if (!isset($extra['type']) || !isset($extra['user_id']) || !isset($extra['old_user_info']) || !isset($extra['new_user_info'])) {
            return false;
        }

        $data['date'] = isset($extra['time']) ? date('Y-m-d', $extra['time']) : date('Y-m-d');
        $data['createtime'] = isset($extra['time']) ? $extra['time'] : time();
        $data['type'] = $extra['type'];
        $data['user_id'] = $extra['user_id'];
        $data['money'] = $extra['new_user_info']['money'];
        $data['old_money'] = $extra['old_user_info']['money'];
        $data['level'] = $extra['new_user_info']['level'];
        $data['old_level'] = $extra['old_user_info']['level'];
        $data['up'] = ($extra['new_user_info']['level'] > $extra['old_user_info']['level']) ? 1 : 0;
        return $this->insertGetId($data);
    }
}
