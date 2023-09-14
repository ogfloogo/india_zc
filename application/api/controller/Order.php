<?php

namespace app\api\controller;

use app\api\model\Goods;
use app\api\model\Goodscategory;
use app\api\model\Level;
use app\api\model\Order as ModelOrder;
use app\api\model\Orderoften;
use app\api\model\Usermoneylog;
use app\api\model\Userrecharge;
use app\common\model\User;
use think\cache\driver\Redis;
use fast\Random;
use think\Config;
use think\helper\Time;
use think\Log;

/**
 * 下单
 */
class Order extends Controller
{

    /**
     * 团购下单
     *
     * @ApiMethod (POST)
     * @param string $type 1=开团订单,2=一键成团订单
     * @param string $good_id 商品ID
     */
    public function addorder()
    {
        $this->verifyUser();
        $time = Time::today();
        $userinfo = $this->userInfo;
        //倒计时
        // $countdown = (new ModelOrder())->getcountdown($this->uid);
        // if($countdown > 0){
        //     $this->error(__('Temporarily unable to place an order'));
        // }
        //下单时间限制
        $redis = new Redis();
        $redis->handler()->select(2);
        $last = $redis->handler()->get("zclc:addorder:" . $this->uid);
        if ($last) {
            //获取头部
            $header = $this->request->header();
            (new Orderoften())->insert([
                "ip" => get_real_ip(),
                "user_id" => $this->uid,
                "content" => json_encode($header),
                "createtime" => time()
            ]);
            $this->error(__('Requests are too frequent'));
        }
        //是否在体验时间内
        $redis->handler()->select(6);
        $end_time = $redis->handler()->zScore("zclc:sendlist", $this->uid); //到期时间
        if ($end_time) {
            if ($end_time < time()) {
                $this->error(__('Experience has expired'));
            }
        }
        $post = $this->request->post();
        $type = $this->request->post("type"); //1=开团订单,2=一键成团订单
        $good_id = $this->request->post("good_id"); //商品ID
        $category_id = $this->request->post("category_id"); //分类ID
        if (!$type || !$good_id || !$category_id) {
            $this->error(__('parameter error'));
        }
        //判断余额
        $category_info = (new Goodscategory())->detail($post['category_id']);
        if ($category_info['price'] > $userinfo['money']) {
            $data['code'] = 10;
            $this->success(__('Your balance is not enough'), $data);
        }
        //Vip1下单次数限制  1-3 2-5 1-4
        //是否有充值记录
        //$isrecharge = (new Userrecharge())->where('user_id', $this->uid)->where('status', 1)->find();
        //注册时间是否超过3天
        // $is_over_time = false;
        // $lf = (time() - $userinfo['createtime']) / (60 * 60 * 24);
        // if ($lf >= 3 && $lf < 7) {
        //     $is_over_time = true;
        // }
            
        //大于7天，断货
        // if ($lf >= 7 && $userinfo['level'] == 1) {
        //     $list = (new Goods())->homepagegoods();
        //     $return[0] = $list[2];
        //     $return[1] = $list[3];
        //     $this->error(__('This item is out of stock, please check later'), $return, 0, '', [], 1);
        // }
        //团购次数
        $order_num = (new ModelOrder())->where('user_id', $userinfo['id'])->where('createtime', 'between', [$time[0], $time[1]])->count();

        //团购次数
        switch ($userinfo['level']) {
            case 1:
                $daily_buy_num = 6;
                break;
            case 2:
                $daily_buy_num = 7;
                break;
            case 3:
                $daily_buy_num = 8;
                break;
            case 4:
                $daily_buy_num = 9;
                break;
            default:
                $daily_buy_num = Config::get('site.daily_buy_num');
                break;
        }
        
        if ($order_num >= $daily_buy_num) {
            $this->error(__('You have reached the max of group-buying times today for your current level'));
        }
        if ($type == 1) { //我要开团
            //团购次数
            $redis = new Redis();
            $redis->handler()->select(0);
            $level = $redis->handler()->hMget('zclc:level:' . $userinfo['level'], ['open_group_num']);
            $order_num = (new ModelOrder())->where('user_id', $userinfo['id'])->where('type', 1)->where('createtime', 'between', [$time[0], $time[1]])->count();
            if ($order_num >= $level['open_group_num']) {
                $data['code'] = 9;
                $this->success(__('Reached the max create group-buying times today'), $data);
            }
        }
        $addorder = (new ModelOrder())->addorder($post, $userinfo, $category_info);
        if (!$addorder) {
            $this->error(__('order failed'));
        }
        $this->success(__('order successfully'), $addorder);
    }

    public function checkaddorder()
    {
    }
    /**
     * 我的团购
     *
     * @ApiMethod (POST)
     * @param string $type 1=今日团购,2=历史团购
     * @param string $page 当前页
     */
    public function orderlist()
    {
        $this->verifyUser();
        $post = $this->request->post();
        $type = $this->request->post("type");
        $page = $this->request->post("page");
        if (!$type || !$page) {
            $this->error(__('parameter error'));
        }
        $orderlist = (new ModelOrder())->orderlist($post, $this->uid);
        $this->success(__('order successfully'), $orderlist);
    }

    /**
     * 我的团购-当天次数统计
     *
     * @ApiMethod (POST)
     * @param string $type 1=今日团购,2=历史团购
     * @param string $page 当前页
     */
    public function timestotal()
    {
        $this->verifyUser();
        $configtimes = Config::get("site.daily_buy_num");
        $time = Time::today();
        $where['createtime'] = ['between', [$time[0], $time[1]]];
        $where['user_id'] = $this->uid;
        $count = (new ModelOrder())->where($where)->count();
        $this->success(__('order successfully'), $count . "/" . $configtimes);
    }
}
