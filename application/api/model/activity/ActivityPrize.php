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


class ActivityPrize extends BaseModel
{
    protected function initialize()
    {
        parent::initialize();
        $prefix_config = Config::get('activity.redis_key_prefix')['activity_prize'];
        $this->info_prefix = $prefix_config['info'];
        $this->set_prefix = $prefix_config['set'];
        $this->union_set_prefix = $prefix_config['set'] . 'union:';
    }

    public function list($id)
    {
        $common_set_key = $this->set_prefix . '0';
        $level_set_key = $this->set_prefix . $id;
        $set_num = $this->redisInstance->zunionstore($this->union_set_prefix . $id, [$level_set_key, $common_set_key]);
        $list = [];
        if ($set_num) {
            $level_list = $this->redisInstance->zRevRange($this->union_set_prefix . $id, 0, -1, true);
            foreach ($level_list as $a_id => $seq) {
                $item = $this->formatInfo($a_id);
                $list[] = $item;
            }
        }
        return $list;
    }

    public function info($id)
    {
        $info_key = $this->info_prefix . $id;
        $detail_info = $this->redisInstance->hGetAll($info_key);
        return $detail_info;
    }

    public function formatInfo($id)
    {
        $detail_info = $this->info($id);
        $info = [];
        if ($detail_info) {
            $info['num'] = $detail_info['num'];
            $info['name'] = $detail_info['name'];
            $info['id'] = $detail_info['id'];
            $info['image'] = $detail_info['image'] ? format_image($detail_info['image'], true) : '';
            $info['price'] = $detail_info['price'];
        }
        return $info;
    }
}
