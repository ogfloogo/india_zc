<?php

namespace app\api\controller;

use app\api\model\Goodscategory;
use app\api\model\Level;
use app\api\model\Order;
use app\api\model\Usermoneylog;
use think\cache\driver\Redis;
use think\Config;
use think\helper\Time;

/**
 * Earnmoney-Highmoney
 */
class Earnmoney extends Controller
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     * 首页看板
     */
    public function board()
    {
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $time = Time::today();
        //今日收入
        (new Usermoneylog())->settables($this->uid);
        //今日团购收入
        $list['tg_income'] = (new Usermoneylog())->where('user_id', $this->uid)->where('createtime', 'between', [$time[0], $time[1]])->where('type', 'in', [7, 8])->sum('money');
        //当日总团购次数
        $list['daily_group_buying_num'] = Config::get('site.daily_buy_num');
        //已团购次数
        $list['group_buying_num'] = (new Order())->where('user_id', $this->uid)->where('createtime', 'between', [$time[0], $time[1]])->count();
        //开团次数
        $redis = new Redis();
        $redis->handler()->select(0);
        $level = $redis->handler()->hMget('zclc:level:' . $userinfo['level'], ['open_group_num']);
        //当日总开团次数
        $list['daily_open_group_num'] = $level['open_group_num'];
        //已开团次数
        $list['open_group_num'] = (new Order())->where('user_id', $this->uid)->where('type', 1)->where('createtime', 'between', [$time[0], $time[1]])->count();
        //预计总收入
        $goods_category = (new Goodscategory())->where('level', intval($userinfo['level']))->where('deletetime', null)->field("buyback,reward")->find();
        $reward = bcmul($goods_category["reward"], 12, 2);
        //开团收入
        //团长奖励
        $group_head_reward = Config::get('site.group_head_reward');
        $open_group_buying = bcmul($goods_category["reward"] * $level['open_group_num'], (intval($group_head_reward) / 100), 2); //团长奖励
        $list['total'] = bcadd($reward, $open_group_buying, 2);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * earnmoney 配置
     * 
     */
    public function earnmoneysystem()
    {
        $list['register_an_account'] = Config::get("site.register_an_account");
        $list['group_buying'] = Config::get("site.group_buying");
        $list['higher_earning'] = Config::get("site.higher_earning");
        $list['inviteing_your_friends'] = Config::get("site.inviteing_your_friends");
        $list['eg_balance'] = Config::get("site.eg_balance");
        $list['eg_level'] = Config::get("site.eg_level");
        $list['eg_you_friends_can_get'] = Config::get("site.eg_you_friends_can_get");
        $list['eg_rate'] = Config::get("site.eg_rate");
        $list['formula'] = Config::get("site.eg_formula");
        $this->success(__('The request is successful'), $list);
    }

    /**
     * highearnings表格
     * 
     * @ApiMethod (POST)
     */
    public function highearnings()
    {
        $list = $this->tablelist();
        $this->success(__('The request is successful'), $list);
    }

    /**
     * Earnmoney第一个表格
     */
    public function firsttable()
    {
        $list = (new Level())->tablelist();
        $this->success(__('The request is successful'), $list);
    }

    /**
     * Earnmoney第二个表格
     */
    public function secondtable()
    {
        $list = (new Level())->tablelist();
        foreach ($list as $key => $value) {
            $goods_category = (new Goodscategory())->where('level', intval($value['level']))->where('deletetime', null)->field("buyback,reward")->find();
            $list[$key]["reward"] = bcmul($goods_category["reward"], 12, 2);
            $list[$key]["buyback"] = $goods_category["buyback"];
            //开团收入
            //团长奖励
            $group_head_reward = Config::get('site.group_head_reward');
            $list[$key]['open_group_buying'] = bcmul($goods_category["reward"] * $value['open_group_num'], (intval($group_head_reward) / 100), 2); //团长奖励
            $list[$key]['total'] = bcadd($list[$key]["reward"], $list[$key]['open_group_buying'], 2);
        }
        $this->success(__('The request is successful'), $list);
    }

    /**
     * Earnmoney第三个表格
     */
    public function thirdtable()
    {
        $list = (new Level())->tablelist();
        $this->success(__('The request is successful'), $list);
    }

    /**
     * member 
     */
    public function classbenefits()
    {
        $list = (new Level())->tablelist();
        foreach ($list as $key => $value) {
            $goodscategory = (new Goodscategory())->where('level', $value['level'])->where('deletetime', null)->find();
            //团购奖励
            $list[$key]['daily_income'] = bcmul($goodscategory['reward'], 12, 2);
            $this->success(__('The request is successful'), $list);
        }
    }

    public function tablelist()
    {
        $redis = new Redis();
        $highlist = $redis->handler()->ZRANGEBYSCORE('high:set:0', '-inf', '+inf', ['withscores' => true]);
        $list = [];
        foreach ($highlist as $k => $v) {
            $list[] = $reward = $redis->handler()->hMget("high:" . intval($k), ['name', 'team_income', 'reward_invite', 'level', 'become_balance']);
        }
        foreach ($list as $k => $v) {
            $invite = json_decode($v['reward_invite'], true);
            foreach ($invite as $ks => $vs) {
                switch ($vs['level']) {
                    case 1:
                        $invite[$ks]['level'] = "A";
                        break;
                    case 2:
                        $invite[$ks]['level'] = "B";
                        break;
                    case 3:
                        $invite[$ks]['level'] = "C";
                        break;
                    default:
                        # code...
                        break;
                }
            }
            $list[$k]['reward_invite'] = $invite;
        }
        return $list;
    }
}
