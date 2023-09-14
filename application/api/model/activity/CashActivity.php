<?php

namespace app\api\model\activity;

use app\api\model\activity\BaseModel;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;


class CashActivity extends BaseModel
{
    protected function initialize()
    {
        parent::initialize();
        $prefix_config = Config::get('activity.redis_key_prefix')['cash_activity'];
        $this->info_prefix = $prefix_config['info'];
        $this->set_prefix = $prefix_config['set'];
        $this->union_set_prefix = $prefix_config['set'] . 'union:';
        $this->activity_id = $prefix_config['id'];
    }

    public function list($id, $user_info = '')
    {
        $common_set_key = $this->set_prefix . '0';
        $list = [];
        $level_list = $this->redisInstance->zRevRange($common_set_key, 0, -1, true);
        foreach ($level_list as $a_id => $seq) {
            $item = $this->info($a_id, $user_info);
            $list[] = $item;
        }
        return $list;
    }


    public function info($id, $user_info = '')
    {
        $info = [];
        $info_key = $this->info_prefix . $id;
        $detail_info = $this->redisInstance->hGetAll($info_key);
        if ($detail_info) {
            $info['name'] = $detail_info['name'];
            $task_info = (new ActivityTask())->info($detail_info['task_id']);
            $prize_info = (new ActivityPrize())->info($detail_info['prize_id']);
            $info['prize_info'] = (new ActivityPrize())->formatInfo($detail_info['prize_id']);
            $info['task_info'] = (new UserActivityTask())->getUserTaskInfo($this->activity_id, $user_info, $task_info);
            $info['my_prize_info'] = (new UserActivityPrize())->getUserPrizeInfo($this->activity_id, $user_info, $info['task_info']['user_task_id'], $info['task_info']['status'], $task_info, $prize_info);
        }

        return $info;
    }

    public function getPrizeInfo($task_id, $level = 0)
    {
        $wc['task_id'] = $task_id;
        if ($level) {
            $wc['level'] = $level;
        }
        $prize_id = $this->where($wc)->value('prize_id');
        $prize_info = (new ActivityPrize())->info($prize_id);
        return $prize_info;
    }
}
