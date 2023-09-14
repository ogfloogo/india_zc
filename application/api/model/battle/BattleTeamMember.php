<?php

namespace app\api\model\battle;

use think\Model;
use think\cache\driver\Redis;
use think\Db;


class BattleTeamMember extends Model
{
    public function add($team_id, $user_id, $status = 1, $is_head = 0)
    {

        $wc['team_id'] = $data['team_id'] = $team_id;
        $wc['user_id'] = $data['user_id'] = $user_id;
        $memberInfo = $this->where($wc)->count();
        if ($memberInfo) {
            return $memberInfo['id'];
        }
        $data['is_head'] = $is_head;
        $data['status'] = $status;
        $data['createtime'] = time();
        $data['updatetime'] = time();
        return $this->insertGetId($data);
    }
}
