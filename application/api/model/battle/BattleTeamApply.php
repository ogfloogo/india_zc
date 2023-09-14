<?php

namespace app\api\model\battle;

use think\Model;
use think\cache\driver\Redis;
use think\Db;


class BattleTeamApply extends Model
{
    public function addApply($user_id, $team_id)
    {
        $data['team_id'] = $team_id;
        $data['user_id'] = $user_id;
        $data['status'] = 0;
        $data['createtime'] = time();
        $data['updatetime'] = time();
        return $this->insertGetId($data);
    }

    public function dealApply($apply_id, $status, $exist)
    {
        $data['status'] = $status;
        $wc['id'] = $apply_id;
        $res = $this->where($wc)->update($data);
        if ($res) {
            if ($status == 1) {
                return (new BattleTeamMember())->add($exist['team_id'], $exist['user_id']);
            }
        }
        return $res;
    }
}
