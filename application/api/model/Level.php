<?php

namespace app\api\model;

use app\admin\model\User as ModelUser;
use think\Model;
use app\api\model\User;
use think\Log;
use think\cache\driver\Redis;
use app\api\controller\Controller as base;

/**
 * 用户等级
 */
class Level extends Model
{
    protected $name = 'team_level';

    //等级变更
    public function updatelevel($userinfo, $extra = [])
    {
        //余额
        $balance = $userinfo['money'];
        //所有等级
        $level_info = $this->tablelist();
        //用户当前等级
        $level = $userinfo['level'];
        $mylevel = $level;
        foreach ($level_info as $value) {
            if ($balance >= $value['become_balance']) {
                $mylevel = $value['level'];
            }
        }
        //等级改变
        if ($mylevel != $level) {
            $upd = (new User())->where('id', $userinfo['id'])->update(['level' => $mylevel]);
            if (!$upd) {
                return false;
            }
            if ($extra) {
                $extra['new_user_info'] = $userinfo;
                $extra['new_user_info']['level'] = $mylevel;
                (new UserLevelLog())->addLog($extra);
            }
        }
        return true;
    }


    //等级表格
    public function tablelist()
    {
        $redis = new Redis();
        $keys = $redis->handler()->keys("zclc:team_level:" . "*");
        $level_info = [];
        foreach ($keys as $key => $value) {
            $level_info[] = $redis->handler()->Hgetall($value);
        }
        $edit = array_column($level_info, 'level');
        array_multisort($edit, SORT_ASC, $level_info);
        return $level_info;
    }

    //等级表格-筛选
    public function levelscreenlist()
    {
        $redis = new Redis();
        $keys = $redis->handler()->keys("zclc:team_level:" . "*");
        $level_info = [];
        foreach ($keys as $key => $value) {
            $level_info[] = $redis->handler()->hMget($value,['id', 'level', 'name']);
        }
        $edit = array_column($level_info, 'level');
        array_multisort($edit, SORT_ASC, $level_info);
        return $level_info;
    }

    //等级卡片
    public function levelcard($balance)
    {
        $redis = new Redis();
        $keys = $redis->handler()->keys("zclc:level:" . "*");
        $level_info = [];
        foreach ($keys as $key => $value) {
            $level_info[] = $redis->handler()->hMget($value, ['name', 'become_balance', 'level', 'icon_image']);
        }
        $edit = array_column($level_info, 'level');
        array_multisort($edit, SORT_ASC, $level_info);
        foreach ($level_info as $k => $v) {
            if ($balance >= intval($v['become_balance'])) {
                $level_info[$k]['if_more_than'] = 1;
            } else {
                $level_info[$k]['if_more_than'] = 0;
                $level_info[$k]['differ'] = bcsub($v['become_balance'], $balance, 2);
            }
            $level_info[$k]['icon_image'] = format_image($v['icon_image']);
        }
        return $level_info;
    }


    //等级表格
    public function detail($level)
    {
        $redis = new Redis();
        $level_info = $redis->handler()->Hgetall("zclc:team_level:" . $level);
        return $level_info;
    }
}
