<?php

namespace app\api\model;

use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

/**
 * 团队统计
 */
class Usertotal extends Model
{
    protected $name = 'user_total';

    public function addlog($type, $user_id, $amount)
    {
        $is_total_table = (new Usertotal())->where('user_id', $user_id)->find();
        if (!$is_total_table) {
            (new Usertotal())->insert([
                'user_id' => $user_id,
                'createtime' => time(),
                'updatetime' => time(),
            ]);
        }
        switch ($type) {
            case 3: //邀请奖励
                $upd = $this->where('user_id', $user_id)->setInc('invite_commission', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 4: //佣金收入
                $upd = $this->where('user_id', $user_id)->setInc('total_commission', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 7: //团购奖励
                $upd = $this->where('user_id', $user_id)->setInc('group_buying_commission', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 8: //团长奖励
                $upd = $this->where('user_id', $user_id)->setInc('head_of_the_reward', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 26: //月薪
                $upd = $this->where('user_id', $user_id)->setInc('salary', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 23: //众筹收益
                $upd = $this->where('user_id', $user_id)->setInc('crowdfunding_income', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 24: //推广激励
                $upd = $this->where('user_id', $user_id)->setInc('promotion_award', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 25: //现金奖励
                $upd = $this->where('user_id', $user_id)->setInc('cash_award', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            default:
                # code...
                break;
        }
    }

    public function getLoginDateKey($date = '')
    {
        return $date ? 'bit:' . $date : 'bit:' . date('ymd');
    }

    public function getAgentUserListKey($agent_id)
    {
        return $agent_id ? 'user:bit:' . $agent_id : 'user:bit:0';
    }

    public function getAgentUserLoginKey($agent_id)
    {
        return $agent_id ? 'login:bit:' . $agent_id : 'login:bit:0';
    }

    public function setLogin($user_id)
    {
        $redis = new Redis();
        $key = $this->getLoginDateKey();
        $is_login = $redis->handler()->getbit($key, $user_id);
        $redis->handler()->setbit($key, $user_id, 1);
        if (!$is_login) {
            (new User())->where(['id' => $user_id])->update(['logintime' => time()]);
        }
    }

    public function getLoginCount($date = '')
    {
        $redis = new Redis();
        $key = $this->getLoginDateKey($date);
        return $redis->handler()->bitcount($key);
    }

    public function getAgentLoginCount($agent_id = 0, $date = '')
    {
        if (!$agent_id) {
            return 0;
        }
        $this->setAgentUserList($agent_id);
        $user_list_key = $this->getAgentUserListKey($agent_id);
        $user_login_key = $this->getAgentUserLoginKey($agent_id);
        $key = $this->getLoginDateKey($date);
        $redisInstance = (new Redis())->handler();
        $redisInstance->bitOp("AND", $user_login_key, $user_list_key, $key);
        return $redisInstance->bitcount($user_login_key);
    }

    public function setAgentUserList($agent_id = 0)
    {
        if ($agent_id) {
            $user_list_key = $this->getAgentUserListKey($agent_id);
            $redisInstance = (new Redis())->handler();
            $list = (new User())->where(['agent_id' => $agent_id])->column('id');
            foreach ($list as $user_id) {
                if ($redisInstance->getBit($user_list_key, $user_id)) {
                    continue;
                }
                $redisInstance->setBit($user_list_key, $user_id, 1);
            }
        }
    }

    public function getLogin($user_id)
    {
        $redis = new Redis();
        $key = $this->getLoginDateKey();
        $is_login = $redis->handler()->getbit($key, $user_id);
        return $is_login;
    }
}
