<?php

namespace app\api\controller;

use app\api\model\Goods as ModelGoods;
use app\api\model\Goodscategory;
use app\api\model\Level;
use app\api\model\Order as ModelOrder;
use app\api\model\Usercategory;
use app\api\model\Usermoneylog;
use app\api\model\Userrobot;
use app\common\model\User;
use think\cache\driver\Redis;
use think\Config;
use think\helper\Time;
use think\Log;

/**
 * 商品
 */
class Goods extends Controller
{
    /**
     *商品分类
     *
     * @ApiMethod (POST)
     */
    public function goodscategory()
    {
        $categoryList = (new Goodscategory())->getcategoryList();
        $this->success(__('The request is successful'), $categoryList);
    }

    /**
     * 分类-商品列表
     * 
     */
    public function goodslist()
    {
        $id = $this->request->post('id'); //ID
        $goodslist = (new ModelGoods())->getgoodslist($id);
        $this->success(__('The request is successful'), $goodslist);
    }

    /**
     * 商品详情
     * 
     */
    public function goodsdetail()
    {
        $this->verifyUser();
        $id = $this->request->post('id'); //商品ID
        $goodslist = (new ModelGoods())->goodsdetail($id);
        $this->success(__('The request is successful'), $goodslist);
    }

    /**
     * 商品详情-更多参数
     */
    public function goodsdetailparam()
    {
        $this->verifyUser();
        $id = $this->request->post('category_id'); //商品区ID
        $userinfo = $this->userInfo;
        (new Usercategory())->check($this->uid);
        $user_today_info = (new Usercategory())->where('user_id', $userinfo['id'])->where('date', date('Y-m-d', time()))->field('num,group_buying_commission,head_of_the_reward')->find();
        $goodslist = (new ModelGoods())->goodsdetailparam($id, $userinfo, $user_today_info);
        $time = Time::today();
        $goodslist['leader_bonus'] = bcmul($goodslist['reward'], Config::get("site.group_head_reward") / 100, 2);
        //团长开团
        $userinfo = $this->userInfo;
        $redis = new Redis();
        $level = $redis->handler()->hMget('zclc:level:' . $userinfo['level'], ['open_group_num']);
        $order_num = (new ModelOrder())->where('user_id', $userinfo['id'])->where('type', 1)->where('createtime', 'between', [$time[0], $time[1]])->count();
        $goodslist['group_buying_num'] = $level['open_group_num'];
        $goodslist['my_group_buying_num'] = $order_num;
        //倒计时
        $redis->handler()->select(2);
        //最后一次下单时间
        //$goodslist['countdown'] = (new ModelOrder())->getcountdown($this->uid);
        $goodslist['countdown'] = 0;
        //无法开团弹窗
        $goodslist['unable_open'] = [];
        if($userinfo['level'] < 5){
            $mylevel = $goodslist['mylevelrate'];
            $next_level = (new Level())->mylevel_commission_rates(5);
            $goodslist['unable_open'] = [
                'mylevel' => $mylevel['name'],
                'next_level' => $next_level['name'],
                'must_money' => bcsub($next_level['become_balance'],$userinfo['money'],2)
            ];
        }
        $this->success(__('The request is successful'), $goodslist);
    }

    /**
     * 首页商品区
     * 
     */
    public function homepagegoods()
    {
        $homepagegoods = (new ModelGoods())->homepagegoods();
        $this->success(__('The request is successful'), $homepagegoods);
    }

    /**
     * 首页推荐商品区
     */
    public function recommend()
    {
        $user = $this->getCacheUser();
        if ($user) {
            $recommend = (new ModelGoods())->recommend($user);
        } else {
            $recommend = (new ModelGoods())->recommends();
        }
        $this->success(__('The request is successful'), $recommend);
    }

    /**
     * 团购推荐
     */
    public function buyerrecommend()
    {
        //拼团人数
        $min = 300;
        $max = 350;
        $number = mt_rand($min, $max);
        //列表
        $data = [];
        for ($i = 0; $i < 2; $i++) {
            //随机头像
            $start = 3;
            $end = 222;
            $pic = mt_rand($start, $end);
            //随机电话号段
            //$my_array = array("6","7","8","9");
            $my_array = (new Userrobot())->getname();
            $length = count($my_array) - 1;
            $hd = rand(0, $length);
            $begin = $my_array[$hd];
            $a = rand(10, 99);
            $b = rand(100, 999);
            $avatar = '/client/static/img/avatar.5ff7027a.png';
            // $data[$i]['nickname'] = $begin.$a.'****'.$b;
            $data[$i]['nickname'] = $begin;
            $data[$i]['avatar'] = format_image("/uploads/robotpic/" . $pic . ".jpg");
            $start_time = time() + 60 * 60;
            $end_time = time() + 60 * 60 * 24;
            $data[$i]['end_time'] = mt_rand($start_time, $end_time);
        }
        $res['number'] = $number;
        $res['list'] = $data;
        $this->success(__('The request is successful'), $res);
    }

    /**
     * 团购列表
     */
    public function buyerlist()
    {
        //列表
        $data = [];
        for ($i = 3; $i < 23; $i++) {
            //随机电话号段
            $my_array = (new Userrobot())->getname();
            $length = count($my_array) - 1;
            $hd = rand(0, $length);
            $begin = $my_array[$hd];
            $a = rand(10, 99);
            $b = rand(100, 999);
            $avatar = '/client/static/img/avatar.5ff7027a.png';
            $data[$i]['nickname'] = $begin;
            $data[$i]['avatar'] = format_image("/uploads/robotpic/" . $i . ".jpg");
            $start_time = time() + 60 * 60;
            $end_time = time() + 60 * 60 * 24;
            $data[$i]['end_time'] = mt_rand($start_time, $end_time);
        }
        $data2 = [];
        for ($i = 3; $i < 23; $i++) {
            //随机电话号段
            $my_array = array("6","7","8","9");
            $length = count($my_array) - 1;
            $hd = rand(0, $length);
            $begin = $my_array[$hd];
            $a = rand(10, 99);
            $b = rand(100, 999);
            $avatar = '/client/static/img/avatar.5ff7027a.png';
            $data2[$i]['nickname'] = $begin . $a . '****' . $b;
            $data2[$i]['avatar'] = format_image($avatar);
            $start_time = time() + 60 * 60;
            $end_time = time() + 60 * 60 * 24;
            $data2[$i]['end_time'] = mt_rand($start_time, $end_time);
        }
        $all = array_merge($data,$data2);
        shuffle($all);
        // $return = shuffle_assoc($all);
        $this->success(__('The request is successful'), $all);
    }
}
