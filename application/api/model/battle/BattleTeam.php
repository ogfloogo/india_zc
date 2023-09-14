<?php

namespace app\api\model\battle;

use app\api\model\User;
use think\Model;
use think\cache\driver\Redis;
use think\Db;


class BattleTeam extends Model
{
    public function getInfo($id)
    {
        $info =  $this->where(['id' => $id])->find();
        $newItem['logo_image'] = format_image($info['logo_image']);
        $newItem['name'] = $info['name'];
        $newItem['team_url'] = $info['team_url'];
        $newItem['announcement'] = $info['announcement'];
        $newItem['contribution'] = $info['contribution'];
        $newItem['team_people_num'] = $info['team_people_num'];
        $newItem['team_power'] = $info['team_power'];
        $newItem['head'] = $this->getHeadInfo($info['user_id']);
        return $newItem;
    }

    public function getHeadInfo($user_id)
    {
        $info =  (new User())->where(['id' => $user_id])->field('id,level,nickname')->find();
        return $info;
    }

    public function getUserInfo($user_id)
    {
        $info =  (new User())->where(['id' => $user_id])->field('id,avatar,level,nickname')->find();
        $info['avatar'] = format_image($info['avatar']);
        return $info;
    }

    public function updateInfo($id, $data)
    {
        return $this->where(['id' => $id])->update($data);
    }
}
