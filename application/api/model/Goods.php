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
 * 商品
 */
class Goods extends Model
{
    protected $name = 'goods';

    /**
     * 商品列表
     */
    public function getgoodslist($id)
    {
        $category_id_key = "zclc:good:set:" . $id;
        $redis = new Redis();
        $goodslist = $redis->handler()->ZRANGEBYSCORE($category_id_key, '-inf', '+inf', ['withscores' => true]);
        $res = [];
        foreach ($goodslist as $key => $value) {
            $res[] = $redis->handler()->hMget('zclc:good:' . intval($key), ['id', 'name', 'category_id', 'cover_image', 'group_buy_num']);
        }
        foreach ($res as $k => $v) {
            $category_info = $redis->handler()->hMget('zclc:category:' . $v['category_id'], ['price']);
            $res[$k]['price'] = $category_info['price'];
            $res[$k]['cover_image'] = format_image($v['cover_image']);
        }
        return $res;
    }

    /**
     * 商品详情
     */
    public function goodsdetail($id)
    {
        $category_id_key = "zclc:good:" . $id;
        $redis = new Redis();
        $detail = $this->detail($id);
        $detail['cover_image'] = format_image($detail['cover_image']);
        $banner_images = explode(',', $detail['banner_images']);
        $array = [];
        foreach ($banner_images as $key => $value) {
            $array[] = format_image($value);
        }
        $detail['banner_images'] = implode(',', $array);
        //商品价格
        $category = (new Goodscategory())->detail($detail['category_id']);
        $detail['price'] = $category['price'];
        $detail['buyback'] = bcsub($category['buyback'], $category['price'], 2);
        $detail['reward'] = $category['reward'];
        $daily_buy_num = Config::get("site.daily_buy_num");
        $detail['rewards'] = bcmul($daily_buy_num, $detail['reward'], 2);
        $detail['win_must_num'] = $category['win_must_num'];
        $detail['detail_images'] = format_image($detail['detail_images']);
        return $detail;
    }

    public function goodsdetailparam($id, $userinfo, $user_today_info)
    {
        //每日开团次数
        $daily_buy_num = Config::get("site.daily_buy_num");
        //今日收入
        $today_income = bcadd($user_today_info['group_buying_commission'], $user_today_info['head_of_the_reward'], 2);
        //今日已团购次数
        $today_buy_num = $user_today_info['num'];
        //该商品区再拼几次必中商品
        $redis = new Redis();
        $category_info = $redis->handler()->hMget("zclc:category:" . $id, ['price', 'buyback', 'reward', 'win_must_num']);
        $user_category = (new Usermerchandise())->where('user_id', $userinfo['id'])->where('category_id', $id)->find();
        if (!$user_category) {
            $will_be_in = $category_info['win_must_num'];
        } else {
            $will_be_in = $category_info['win_must_num'] - $user_category['num'];
            if ($will_be_in < 0) {
                $will_be_in = $category_info['win_must_num'];
            }
        }
        //团队佣金比例
        $mylevelrate = (new Level())->mylevel_commission_rates($userinfo['level']);
        $mylevelrate['reward_invite'] = json_decode($mylevelrate['reward_invite'],true);
        //今日团队佣金
        $time = Time::today();
        $commission = new Commission();
        $commission->setTableName($userinfo['id']);
        $myteam_today_commission = $commission->where('to_id', $userinfo['id'])->where('level', 'in', '1,2,3')->where('createtime', 'between', [$time[0], $time[1]])->sum('commission');
        //下一级的开团次数
        $mylevel = $userinfo['level'];
        if ($mylevel < 8) {
            $next_level = $mylevel + 1;
            $level = (new Level())->mylevel_commission_rates($next_level);
            while ($level['open_group_num'] == 0) {
                $next_level = $next_level + 1;
                $level = (new Level())->mylevel_commission_rates($next_level);
            }
            $next_open_group_num = $level['open_group_num'];
        } else {
            $next_open_group_num = 0;
        }

        return [
            'daily_buy_num' => $daily_buy_num, //每日开团次数
            'today_income' => $today_income, //今日收入
            'today_buy_num' => $today_buy_num, //今日已团购次数
            'will_be_in' => $will_be_in, //该商品区再拼几次必中商品
            'mylevelrate' => $mylevelrate, //团队佣金比例
            'myteam_today_commission' => $myteam_today_commission, //今日团队佣金
            'reward' => $category_info['reward'],
            'next_open_group_num' => $next_open_group_num
        ];
    }

    /**
     * 首页商品区
     */
    public function homepagegoods()
    {
        $goods_id_key =  "zclc:good:set:rec";
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:category:set:0', '-inf', '+inf', ['withscores' => true]);
        $left = [];
        foreach ($categorylist as $k => $v) {
            $left[$k]['id'] = $k;
            $reward = $redis->handler()->hMget("zclc:category:" . intval($k), ['reward', 'name', 'price','extra_reward_desc']);
            $left[$k]['name'] = $reward['name'];
            $left[$k]['price'] = $reward['price'];
            $left[$k]['extra_reward_desc'] = $reward['extra_reward_desc'];
            //今日可赚金额
            $daily_buy_num = Config::get('site.daily_buy_num');
            $left[$k]['money'] = bcmul($daily_buy_num, $reward['reward'], 2);
        }
        foreach ($left as $ks => $vs) {
            $goods_id_key =  "zclc:good:set:rec:" . $vs['id'];
            $goodids = $redis->handler()->ZRANGEBYSCORE($goods_id_key, '-inf', '+inf', ['withscores' => true]);
            $child = [];
            foreach ($goodids as $ksv => $vsv) {
                $child[] = $this->goodsdetail(intval($ksv));
            }
            $left[$ks]['list'] = $child;
        }
        $edit = array_column($left, 'price');
        array_multisort($edit, SORT_ASC, $left);
        return $left;
    }

    public function recommendlist()
    {
        $goods_id_key =  "zclc:good:set:rec";
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:category:set:0', '-inf', '+inf', ['withscores' => true]);
        $left = [];
        foreach ($categorylist as $k => $v) {
            $left[$k]['id'] = $k;
            $reward = $redis->handler()->hMget("zclc:category:" . intval($k), ['reward', 'name', 'price','extra_reward_desc']);
            $left[$k]['name'] = $reward['name'];
            $left[$k]['price'] = $reward['price'];
            $left[$k]['extra_reward_desc'] = $reward['extra_reward_desc'];
            //今日可赚金额
            $daily_buy_num = Config::get('site.daily_buy_num');
            $left[$k]['money'] = bcmul($daily_buy_num, $reward['reward'], 2);
        }
        foreach ($left as $ks => $vs) {
            $goods_id_key =  "zclc:good:set:rec:" . $vs['id'];
            $goodids = $redis->handler()->ZRANGEBYSCORE($goods_id_key, '-inf', '+inf', ['withscores' => true]);
            $child = [];
            foreach ($goodids as $ksv => $vsv) {
                $child[] = $this->goodsdetail(intval($ksv));
            }
            $left[$ks]['list'] = $child;
        }
        return $left;
    }
    /**
     * 首页商品推荐区
     */
    public function recommend($userinfo)
    {
        $list = $this->homepagegoods();
        if($userinfo['level'] == 1){
            return $list[0];
        }
        $newlist = [];
        foreach ($list as $key => $value) {
            if ($userinfo['money'] >= $value['price']) {
                $newlist[] = $value;
            }
        }
        $edit = array_column($newlist, 'price');
        array_multisort($edit, SORT_DESC, $newlist);
        return $newlist[0];
    }

    public function recommends()
    {
        $list = $this->homepagegoods();
        return $list[0];
    }

    public function detail($good_id)
    {
        $redis = new Redis();
        $detail = $redis->handler()->Hgetall("zclc:good:" . $good_id);
        return $detail;
    }
}
