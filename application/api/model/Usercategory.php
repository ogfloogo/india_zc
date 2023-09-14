<?php

namespace app\api\model;

use app\admin\model\groupbuy\GoodsCategory;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Log;

/**
 * 商品区域-用户统计
 */
class Usercategory extends Model
{
    protected $name = 'user_category';
    protected $redisInstance = null;

    public function statistics($post, $userinfo, $id, $category_info)
    {
        $usermerchandise = new Usermerchandise();
        $usermerchandise->addlog($userinfo['id'], $post['category_id']);
        //必获商品所需次数
        $this->redisInstance = (new Redis())->handler();
        //该商品区已购买次数
        $num = $usermerchandise->where('user_id', $userinfo['id'])->where('category_id', $post['category_id'])->find();

        //是否被选中
        $is_win = $this->checkIsWin($userinfo['id'], $category_info);
        if ($is_win) {
            //如果已在今日中奖集合中，修改为未选中
            if ($this->isAddedWinPool($userinfo['id'], $category_info)) {
                $is_win = false;
            } else {
                //先加入中奖集合，失败也标记为未选中
                $is_added = $this->addWinPool($userinfo['id'], $category_info);
                if (!$is_added) {
                    $is_win = false;
                }
            }
        }
        //被选中中奖
        if ($is_win) {
            //开奖
            (new Userwarehouse())->drawwinning($post, $userinfo, $id, $category_info);
            Log::mylog("is win:", $num, 'win');
            $num_data = [];
            Log::mylog("is win:", $num['num'], 'win');

            //重置次数
            $num_data['num'] =  Db::raw('num+1');
            $num_data['win_num'] = Db::raw('win_num+1');
            Log::mylog("is win: num_data ", $num_data, 'win');

            //新增用户当天中奖次数，新增用户当天团购次数
            $this->where(['user_id' => $userinfo['id'], 'date' => date('Y-m-d')])->update($num_data);
            Log::mylog("is win: userinfo ", $userinfo['id'], 'win');

            //新增用户该商品区中奖次数，新增用户该商品区团购次数，修改当前中奖次数
            $num_data['num'] = isset($num['num']) ? $num['num'] + 1 : 1;
            $num_data['last_win_num'] = isset($num['num']) ? $num['num'] + 1 : 1;
            $usermerchandise->where(['user_id' => $userinfo['id'], 'category_id' => $category_info['id']])->update($num_data);
            if ($post['type'] == 1) {
                $group_head_reward = Config::get('site.group_head_reward');
                $bouns = (bcmul($category_info['reward'], intval($group_head_reward), 2)) / 100;
                //团长奖励
                $usermoneylog = (new Usermoneylog())->moneyrecordorder($userinfo, $bouns, 'inc', 8, $id);
                //更新订单
                $update_order = (new Order())->where('id', $id)->update(["income" => $bouns]);
                //佣金发放
                $bouns = bcsub($category_info['buyback'], $category_info['price'], 2);
                (new Commission())->commissionissued($userinfo['id'], $bouns, $id, $userinfo['level'], intval($userinfo['agent_id']));
            }
            //用户统计表
            (new Usertotal())->where('user_id', $userinfo['id'])->setInc('winning_number', 1);
            return ['code' => 1];
        } else {
            //未中奖奖励
            if ($post['type'] == 1) { //我要开团
                $tz = (new Usermoneylog())->opengrouprewards($userinfo['id'], $category_info, $id);
                if (!$tz) {
                    return false;
                }
            } else { //一键开团
                $tg = (new Usermoneylog())->akeytoopen($userinfo['id'], $category_info, $id);
                if (!$tg) {
                    return false;
                }
            }
            //未中奖，新增用户该商品下单次数
            $user_good_category = $usermerchandise->where('user_id', $userinfo['id'])->where('category_id', $post['category_id'])->setInc('num', 1);
            //未中奖，新增用户当天下单次数
            $user_today_addtimes = $this->where('user_id', $userinfo['id'])->where('date', date('Y-m-d', time()))->setInc('num', 1);
            //更新订单
            $update_order = (new Order())->where('id', $id)->update(["earnings" => $category_info['reward']]);
            //下单后检查是否可以进池
            $this->checkPoolIn($userinfo['id'], $category_info);
            return ['code' => 0];
        }
    }

    public function getPoolKey($id)
    {
        return (new GoodsCategory())->cache_prefix . 'pool:' . $id;
    }

    public function getWinPoolKey($id, $date = '')
    {
        if (!$date) {
            $date = date('ymd');
        }
        return (new GoodsCategory())->cache_prefix . 'pool:win:' . $id . ':' . $date;
    }

    protected function checkIsWin($user_id, $category_info)
    {
        Log::mylog('checkIsWin: category_info ', $category_info, 'win');
        Log::mylog('checkIsWin: user_id ', $user_id, 'win');
        // return $num && (($num['num'] + 1) >= intval($category_info['win_must_num']));
        $id = $category_info['id'];
        $pool_key = $this->getPoolKey($id);
        $win_pool_key = $this->getWinPoolKey($id);
        Log::mylog('checkIsWin: pool_key ', $pool_key . '===' . $win_pool_key, 'win');

        //如果今日额度没配不选
        if (!$category_info['daily_win_limit']) {
            return false;
        }
        Log::mylog('checkIsWin: sCard ', $this->redisInstance->sCard($win_pool_key) . '===' . $category_info['daily_win_limit'] . '---' . $this->redisInstance->sIsMember($win_pool_key, $user_id), 'win');

        //如果今日额度已到不选
        if ($this->redisInstance->sCard($win_pool_key) >= $category_info['daily_win_limit']) {
            return false;
        }


        //已在中奖集合中直接不选
        if ($this->redisInstance->sIsMember($win_pool_key, $user_id)) {
            return false;
        }

        $win_user_ids = $this->redisInstance->sRandMember($pool_key, $category_info['daily_win_limit']);
        //随机选择
        Log::mylog('checkIsWin: $win_user_ids ', $win_user_ids, 'win');

        if (in_array($user_id, $win_user_ids)) {
            //选中再踢出集合
            $this->redisInstance->sRem($pool_key, $user_id);
            Log::mylog('checkIsWin: sRem ', $user_id, 'win');

            return true;
        } else {

            return false;
        }
    }

    public function checkPoolIn($user_id, $category_info)
    {
        Log::mylog('checkPoolIn: category_info ', $category_info, 'win');
        Log::mylog('checkPoolIn: user_id ', $user_id, 'win');
        //今日已中不进池
        $id = $category_info['id'];
        $pool_key = $this->getPoolKey($id);

        if ($this->isAddedWinPool($user_id, $category_info)) {
            $is_in_pool = $this->redisInstance->sIsMember($pool_key, $user_id);
            if ($is_in_pool) {
                $this->redisInstance->sRem($pool_key, $user_id);
            }
            return false;
        }
        $userItem = (new Usermerchandise())->where(['user_id' => $user_id, 'category_id' => $category_info['id']])->find();
        Log::mylog('checkPoolIn: userItem ', $userItem, 'win');

        //入池条件
        $begin_time = $category_info['pool_in_num'];
        //冷冻条件
        $frozen_time = $category_info['win_must_num'];

        //总共次数
        $now_time = $userItem['num'];
        $is_wined = $userItem['win_num']; //中奖次数，是否中奖
        $last_win_num = $userItem['last_win_num']; //上次中奖次数顺序
        Log::mylog('checkPoolIn: is_wined ', $is_wined, 'win');

        if ($is_wined) {
            Log::mylog('checkPoolIn: now_time ', $now_time . '---' . ($last_win_num + $frozen_time) . '==' . $last_win_num . '==' . $frozen_time, 'win');

            //已中过的判断是否当前大于冷冻次数的
            if ($now_time >= ($last_win_num + $frozen_time)) {
                //add
                $this->redisInstance->sAdd($pool_key, $userItem['user_id']);
            } else {
                Log::mylog('checkPoolIn: is_wined is_in_pool ', $this->redisInstance->sIsMember($pool_key, $userItem['user_id']), 'win');

                //检查万一又在池中踢出
                $is_in_pool = $this->redisInstance->sIsMember($pool_key, $userItem['user_id']);
                if ($is_in_pool) {
                    $this->redisInstance->sRem($pool_key, $userItem['user_id']);
                }
            }
        } else {
            //未中过的判断是否达到入池条件
            if ($now_time >= $begin_time) {
                //add
                $this->redisInstance->sAdd($pool_key, $userItem['user_id']);
            } else {
                Log::mylog('checkPoolIn: is_in_pool ', $this->redisInstance->sIsMember($pool_key, $userItem['user_id']), 'win');

                //检查万一又在池中踢出
                $is_in_pool = $this->redisInstance->sIsMember($pool_key, $userItem['user_id']);
                if ($is_in_pool) {
                    $this->redisInstance->sRem($pool_key, $userItem['user_id']);
                }
            }
        }
    }

    protected function isAddedWinPool($user_id, $category_info)
    {
        $id = $category_info['id'];
        $win_pool_key = $this->getWinPoolKey($id);
        Log::mylog('isAddedWinPool: win_pool_key ', $win_pool_key, 'win');
        Log::mylog('isAddedWinPool: sIsMember ', $this->redisInstance->sIsMember($win_pool_key, $user_id), 'win');

        return $this->redisInstance->sIsMember($win_pool_key, $user_id);
    }

    protected function addWinPool($user_id, $category_info)
    {
        $redisInstance = (new Redis())->handler();
        $id = $category_info['id'];
        $win_pool_key = $this->getWinPoolKey($id);
        return $redisInstance->sAdd($win_pool_key, $user_id);
    }

    protected function addPool($user_id, $category_info)
    {
        $redisInstance = (new Redis())->handler();
        $id = $category_info['id'];
        $pool_key = $this->getPoolKey($id);
        return $redisInstance->sAdd($pool_key, $user_id);
    }

    public function addlog($type, $user_id, $amount)
    {
        $info = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->find();
        if (!$info) { //未中奖
            $this->insert([
                'user_id' => $user_id,
                'date' => date('Y-m-d', time()),
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
        switch ($type) {
            case 3: //邀请奖励
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('invite_commission', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 4: //佣金收入
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('total_commission', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 7: //团购奖励
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('group_buying_commission', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 8: //团长奖励
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('head_of_the_reward', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 26: //月薪
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('salary', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 23: //众筹收益
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('crowdfunding_income', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 24: //推广激励
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('promotion_award', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            case 25: //现金奖励
                $upd = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('cash_award', $amount);
                if (!$upd) {
                    return false;
                }
                return true;
            default:
                # code...
                break;
        }
    }

    public function check($user_id)
    {
        $info = $this->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->find();
        if (!$info) {
            $this->insert([
                'user_id' => $user_id,
                'date' => date('Y-m-d', time()),
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
    }
}
