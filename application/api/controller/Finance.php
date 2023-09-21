<?php

namespace app\api\controller;

use app\api\model\Buildcache;
use app\api\model\Finance as ModelFinance;
use app\api\model\Financeissue;
use app\api\model\Financeorder as ModelFinanceorder;
use app\api\model\Order as ModelOrder;
use app\api\model\Orderoften;
use app\api\model\Teamlevel;
use app\api\model\Userrobot;
use think\cache\driver\Redis;
use think\Config;
use think\helper\Time;
use think\Log;

/**
 * 理财活动
 */
class Finance extends Controller
{
    /**
     * 理财列表-首页推荐
     *
     * @ApiMethod (POST)
     */
    public function homelist()
    {
        $addorder = (new ModelFinance())->homelist();
        $this->success(__('order successfully'), $addorder ?? []);
    }

    /**
     * 众筹项目列表
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function list()
    {
        $checklogin = $this->getCacheUser();
        $field = ['id', 'name', 'content', 'list_content', 'image', 'zc_day', 'popularize', 'money', 'endtime', 'status', 'username', 'userimage', 'tab'];
        $field2 = ['id', 'name', 'rate', 'type', 'day', 'fixed_amount', 'status', 'buy_level', 'capital', 'interest', 'popularize', 'is_new_hand'];
        $list = (new \app\api\model\Finance())->getfinanceyList($field);
        if (!$list) {
            $list = (new Buildcache())->buildFinanceCategoryCache();
            $list = (new \app\api\model\Finance())->getfinanceyList($field);
        }
        $redis = new Redis();
        $redis->handler()->select(6);
        $return = [];
        //用户是否登录
        if ($checklogin) {
            foreach ($list as $val) {
                //是否购买过体验项目
                if ($checklogin['is_experience'] == 1) { //购买过，剔除体验项目
                    if ($val['popularize'] < 2) {
                        $return[] = $val;
                    }
                } else {
                    $return[] = $val;
                }
            }
        } else {
            foreach ($list as $val) {
                if ($val['popularize'] < 2) {
                    $return[] = $val;
                }
            }
        }
        foreach ($return as &$value) {
            if ($value['tab']) {
                $tablist = explode('---', $value['tab']);
                $value['tab'] = !empty((new \app\api\model\Finance())->gettab($tablist)) ? (new \app\api\model\Finance())->gettab($tablist) : [];
            } else {
                $value['tab'] = [];
            }
            $value['image'] = format_image($value['image']);
            $value['userimage'] = format_image($value['userimage']);
            $already_buy = $redis->handler()->zScore("zclc:financeordermoney", $value['id']); //已认购
            if (!$already_buy) {
                $already_buy = (new Buildcache())->buildFinance();
                $already_buy = $redis->handler()->zScore("zclc:financeordermoney", $value['id']); //已认购
            }
            $value['already_buy'] = !$already_buy ? 0 : $already_buy;
            $value['already_rate'] = round($value['already_buy'] / $value['money'] * 100, 0);
            $value['surplus_day'] = ceil(($value['endtime'] - time()) / (60 * 60 * 24)); //剩余天数
            $buy_num = $redis->handler()->zScore("zclc:financeordernum", $value['id']);
            if (!$buy_num) {
                $buy_num = (new Buildcache())->buildFinance();
                $buy_num = $redis->handler()->zScore("zclc:financeordernum", $value['id']);
            }
            $value['buy_num'] = !$buy_num ? 0 : $buy_num; //支持人数
            if ($checklogin) {
                $project_info = (new \app\api\model\Financeproject())->lists($value['id'], $checklogin['id'], $field2);
                if (!$project_info) {
                    $project_info = (new Buildcache())->buildFinanceCache();
                    $project_info = (new \app\api\model\Financeproject())->lists($value['id'], $checklogin['id'], $field2);
                }
            } else {
                $project_info = (new \app\api\model\Financeproject())->list($value['id'], $field2);
                if (!$project_info) {
                    $project_info = (new Buildcache())->buildFinanceCache();
                    $project_info = (new \app\api\model\Financeproject())->list($value['id'], $field2);
                }
            }
            if ($project_info) {
                foreach ($project_info as &$v) {
                    $level = (new Teamlevel())->detail($v['buy_level']);
                    $v['buy_level_name'] = $level['name'] ?? '';
                    $v['buy_level_image'] = !empty($level['image']) ? format_image($level['image']) : '';
                    $v['total_profit'] = bcmul($v['interest'], $v['day'], 2);
                    $fixed_amount = $v['popularize'] == 2 ? 0 : $v['fixed_amount'];
                    $v['total_revenue'] = bcadd($v['total_profit'], $fixed_amount, 2);
                    if ($v['type'] == 2) {
                        $v['daily_income'] = $v['interest'];
                    } else {
                        $v['daily_income'] = bcadd($v['capital'], $v['interest'], 2);
                    }
                    $v['buy_num'] = (new \app\api\model\Financeorder())->where(['project_id' => $v['id']])->group('user_id')->count();
                    $v['roi'] = bcmul($v['rate'], $v['day'], 2);
                }
            }
            $value['project_info'] = $project_info;
        }
        $return2 = [];
        //        foreach ($return as $vs){
        //            if($vs['popularize'] != 1){
        //                $return2[] = $vs;
        //            }
        //        }
        $result = [
            "rows"  => $return
        ];
        $this->success(__('The request is successful'), $result);
    }

    /**
     * 项目详情
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function details()
    {
        $this->verifyUser();
        $userInfo = $this->userInfo;
        if ($this->request->param('go')) {
            $id = (new \app\api\model\Finance())->where(['popularize' => 1, 'status' => 1])->order('weigh asc')->value('id');
        } else {
            $id = $this->request->param('id');
            if (!$id) {
                $this->error(__('parameter error'));
            }
        }
        $redis = new Redis();
        $redis->handler()->select(6);
        $field = ['id', 'name', 'content', 'list_content', 'rotation_images', 'username', 'userimage', 'zc_day', 'money', 'endtime', 'popularize', 'details_images', 'team_images', 'finance_images', 'status', 'tab'];
        $info = (new \app\api\model\Finance())->detail($id, $field);
        if (!$info) {
            $this->error(__('operation failure'));
        }
        //轮播图
        $rotation_images = explode(",", format_image($info['rotation_images']));
        $array_rotation_images = [];
        $find = Config::get('image_url');
        foreach ($rotation_images as $key => $val) {
            if (strpos($val, $find) !== false) {
                $array_rotation_images[$key]['type'] = 0; //图片
                $array_rotation_images[$key]['value'] = $val;
            } else {
                $array_rotation_images[$key]['type'] = 1; //视频
                $array_rotation_images[$key]['value'] = $val;
            }
        }

        $info['rotation_images'] = $array_rotation_images;
        $info['userimage'] = format_image($info['userimage']);
        $already_buy = $redis->handler()->zScore("zclc:financeordermoney", $id);
        if (!$already_buy) {
            $already_buy = (new \app\api\model\Financeorder())->where(['f_id' => $id])->sum('amount');
        }
        $info['already_buy'] = $already_buy;
        $buy_num = $redis->handler()->zScore("zclc:financeordernum", $id);
        if (!$buy_num) {
            $buy_num = (new \app\api\model\Financeorder())->where(['f_id' => $id])->count();
        }
        $info['buy_num'] = $buy_num;
        $info['already_rate'] = round($info['already_buy'] / $info['money'] * 100, 0);
        $info['surplus_day'] = ceil(($info['endtime'] - time()) / (60 * 60 * 24));
        $field2 = ['id', 'name', 'rate', 'type', 'day', 'fixed_amount', 'status', 'buy_level', 'capital', 'interest', 'image', 'popularize', 'content', 'is_new_hand'];
        $project_info = (new \app\api\model\Financeproject())->lists($id, $this->uid, $field2);
        $online_project_info = [];
        foreach ($project_info as &$value) {
            $value['image'] = format_image($value['image']);
            $level = (new Teamlevel())->detail($value['buy_level']);
            $value['buy_level_name'] = $level['name'] ?? '';
            $value['buy_level_image'] = !empty($level['image']) ? format_image($level['image']) : '';
            $value['roi'] = bcmul($value['rate'], $value['day'], 2);
            if ($value['status'] == 1) {
                $value['can_buy'] = 1;
                //推广项目、体验项目都能购买  普通项目判断称号等级
                if ($value['popularize'] == 0) {
                    if ($userInfo['buy_level'] < $value['buy_level']) {
                        $value['can_buy'] = 0;
                    }
                }
                $value['total_profit'] = bcmul($value['interest'], $value['day'], 2);
                $fixed_amount = $value['popularize'] == 2 ? 0 : $value['fixed_amount'];
                $value['total_revenue'] = bcadd($value['total_profit'], $fixed_amount, 2);
                if ($value['type'] == 2) {
                    $value['daily_income'] = $value['interest'];
                    $value['interest'] = $value['total_profit'];
                } else {
                    $value['daily_income'] = bcadd($value['capital'], $value['interest'], 2);
                }
                $online_project_info[] = $value;
            }
        }
        $info['project_info'] = $online_project_info;
        $max_info = (new \app\api\model\Financeorder())->field('user_id,sum(amount) amount,is_robot')
            ->where(['f_id' => $id])
            ->order('amount desc,is_robot asc')
            ->group('user_id,is_robot')
            ->find();
        if ($max_info) {
            if ($max_info['is_robot'] == 1) {
                $user_info = (new Userrobot())->field('name,avatar')->where(['id' => $max_info['user_id']])->find();
                $max_info['nickname'] = $user_info['name'] ?? "";
                $max_info['avatar'] = format_image($user_info['avatar']) ?? "";
            } else {
                $user_info = (new \app\api\model\User())->field('nickname,avatar')->where(['id' => $max_info['user_id']])->find();
                if ($user_info) {
                    $max_info['nickname'] = $user_info['nickname'] ?? "";
                    $max_info['avatar'] = !empty(format_image($user_info['avatar'])) ? format_image($user_info['avatar']) : "";
                }
            }
        }
        unset($max_info['is_robot']);
        $info['max_user'] = $max_info;
        $this->success(__('The request is successful'), $info);
    }

    public function buyList()
    {
        $id = $this->request->param('id');
        if (!$id) {
            $this->error(__('parameter error'));
        }
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $total = (new \app\api\model\Financeorder())->where(['f_id' => $id])->count();
        $list = (new \app\api\model\Financeorder())
            ->field('user_id,sum(amount) amount,is_robot')
            ->where(['f_id' => $id])
            ->page($page, $pageSize)
            ->order('amount desc,is_robot asc')
            ->group('user_id,is_robot')
            ->select();
        foreach ($list as &$value) {
            if ($value['is_robot'] == 1) {
                $user_info = (new Userrobot())->field('name,avatar')->where(['id' => $value['user_id']])->find();
                $value['nickname'] = $user_info['name'];
                $value['avatar'] = format_image($user_info['avatar']);
            } else {
                $user_info = (new \app\api\model\User())->field('nickname,avatar')->where(['id' => $value['user_id']])->find();
                if ($user_info) {
                    $value['nickname'] = $user_info['nickname'] ?? "";
                    $value['avatar'] = format_image($user_info['avatar']);
                }
            }
            unset($value['is_robot']);
        }
        $result = [
            "total" => $total,
            "rows"  => $list
        ];
        $this->success(__('The request is successful'), $result);
    }

    /**
     * 累计收益金额，累计购买人数
     */
    public function total()
    {
        $this->verifyUser();
        //累计收益
        $earnings = (new ModelFinanceorder())->where('status', 1)->where('state', 2)->where('is_robot', 0)
            ->where('user_id', $this->uid)->sum('earnings');
        //持有的理财
        $my_finance = (new ModelFinanceorder())->where('status', 1)->where('is_robot', 0)->where('state', 'lt', 2)->where('user_id', $this->uid)->sum('amount');
        $return = [
            'earnings' => $earnings,
            'my_finance' => $my_finance,
        ];
        $this->success(__('order successfully'), $return);
    }

    public function totaltest()
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $financelist = $redis->handler()->ZRANGEBYSCORE('zclc:financeordernum', '-inf', '+inf', ['withscores' => true]);
        $buyers = 0;
        foreach ($financelist as $key => $value) {
            $buyers += $value;
        }
    }
    /**
     * 理财详情
     *
     * @ApiMethod (POST)
     */
    public function detail()
    {
        $this->verifyUser();
        $id = $this->request->post("id"); //期号ID
        if (!$id) {
            $this->error(__('parameter error'));
        }
        $detail = (new Financeissue())->detail($id, $this->uid);
        $this->success(__('order successfully'), $detail ?? []);
    }

    /**
     * 用户协议
     */
    public function protocol()
    {
        $data = Config::get("site.protocol");
        $this->success(__('order successfully'), $data ?? []);
    }

    /**
     * 用户特权
     */
    public function privilege()
    {
        $data = Config::get("site.privilege");
        $this->success(__('order successfully'), $data ?? []);
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

    public function faq()
    {
        $list = db('finance_faq')->where('status', 1)->field('title,content')->where('deletetime', null)->select();
        $this->success(__('order successfully'), $list);
    }

    /**
     * 购买记录播报
     * 
     */
    public function broadcast()
    {
        //列表
        $data = [];
        for ($i = 0; $i < 20; $i++) {
            //随机电话号段
            $my_array = array("6", "7", "8", "9");
            $length = count($my_array) - 1;
            $hd = rand(0, $length);
            $begin = $my_array[$hd];
            $a = rand(10, 99);
            $b = rand(100, 999);
            //随机提现金额
            $pay_amount = "1000,3000,5000,7000,9000,6000,10000,13000,23000,28000,30000,35000,46000,50000";
            $top_up_array = explode(",", $pay_amount);
            $lengths = count($top_up_array) - 1;
            $hds = rand(0, $lengths);
            $begins = $top_up_array[$hds];

            //随机提现金额
            $pay_amounts = "150,260,430,460,320,450,600,760,890,1500,2800,1920,1560,1790,1780,2690";
            $top_up_arrays = explode(",", $pay_amounts);
            $lengthss = count($top_up_arrays) - 1;
            $hdss = rand(0, $lengthss);
            $beginss = $top_up_arrays[$hdss];

            $nickname = $begin . $a . '****' . $b;
            if ($this->language == "english") {
                $data[] = $nickname . " successfully purchased ₹" . $begins;
                $data[] = $nickname . " successfully credited to ₹" . $beginss;
            } else {
                $data[] = $nickname . " सफलतापूर्वक खरीदा गया ₹" . $begins;
                $data[] = $nickname . " सफलतापूर्वक श्रेय दिया गया ₹" . $beginss;
            }
        }
        shuffle($data);
        $this->success(__('The request is successful'), $data);
    }


    public function plan()
    {
        $this->verifyUser();
        $userInfo = $this->userInfo;
        $level = $this->request->post("level");
        $label_ids = $this->request->post("label_ids", 0);
        if (!$level) {
            $this->error(__('parameter error'));
        }
        if ($level == 'recommend') {
            $where['level'] = 'recommend';
        } elseif ($level == 'all') {
            $where['level'] = 'all';
        } else {
            $where['level'] = $level;
        }
        if ($label_ids) {
            $label_array = explode(',', $label_ids);
            $where['label_ids'] = $label_array;
        }
        $redis = new Redis();
        $field = ['id', 'name', 'rate', 'type', 'day', 'fixed_amount', 'status', 'buy_level', 'capital', 'interest', 'popularize', 'is_new_hand', 'label_ids', 'day_roi', 'f_id', 'recommend','sort'];
        $list = (new \app\api\model\Financeproject())->getPlanList($field, $where, $userInfo['id'],$userInfo['is_experience']);
        $newhand = [];
        foreach ($list as &$value) {
            $finance = (new \app\api\model\Finance())->detail($value['f_id'], ['name']);
            $value['finance_name'] = $finance['name'];
            //            $value['image'] = format_image($value['image']);
            $levels = (new Teamlevel())->detail($value['buy_level']);
            $value['buy_level_name'] = $levels['name'] ?? '';
            $value['buy_level_image'] = !empty($levels['image']) ? format_image($levels['image']) : '';
            $value['roi'] = bcmul($value['rate'], $value['day'], 2);
            $redis->handler()->select(6);
            $buy_num = (new \app\api\model\Financeorder())->where(['project_id' => $value['id']])->group('user_id')->count();
            $value['buy_num'] = !$buy_num ? 0 : $buy_num; //支持人数
            $value['label'] = (new \app\api\model\Financeproject())->getLabel($value['label_ids']);
            $value['can_buy'] = 1;
            //推广项目、体验项目都能购买  普通项目判断称号等级
            if ($value['popularize'] == 0) {
                if ($userInfo['buy_level'] < $value['buy_level']) {
                    $value['can_buy'] = 0;
                }
            }
            $value['total_profit'] = bcmul($value['interest'], $value['day'], 0);
            $fixed_amount = $value['popularize'] == 2 ? 0 : $value['fixed_amount'];
            $value['total_revenue'] = bcadd($value['total_profit'], $fixed_amount, 0);
            if ($value['type'] == 2) {
                $value['daily_income'] = bcadd($value['interest'],0,1);
            } else {
                $value['daily_income'] = bcadd($value['capital'], $value['interest'], 1);
            }
            if($value['is_new_hand'] == 1){
                $newhand[] = $value;
            }
            $value['daily_income'] = floatval($value['daily_income']);
            $value['rate'] = bcadd($value['rate'],0,1);
            $value['fixed_amount'] = bcadd($value['fixed_amount'],0,0);
        }
        //        $buy_list = (new \app\api\model\Financeorder())->field('user_id,amount')->where(['is_robot'=>0])->order('id desc')->limit(20)->select();
        //        $user_ids = array_column($buy_list,'user_id');
        //        $nickname = (new \app\api\model\User())->field('id,nickname')->where(['id'=>['in',$user_ids]])->column('nickname','id');
        //        $buy_list2 = [];
        //        foreach ($buy_list as $v){
        //            if(!isset($nickname[$v['user_id']])){
        //                continue;
        //            }
        //            $v['nickname'] = $nickname[$v['user_id']];
        //            $buy_list2[] = $v;
        //        }
        if ($level == 'recommend') {
            // $list = sort_array($list, 'rate', 'SORT_DESC');
            $edit = array_column($list, 'sort');
            array_multisort($edit, SORT_ASC, $list);
            //如果存在新手方案则合并到第一个
            // if(!empty($newhand)){
            //     $lists = [];
            //     foreach($list as &$vs){
            //         if($vs['id'] != $newhand[0]['id']){
            //             $lists[] = $vs;
            //         }
            //     }
            //     array_unshift($lists, $newhand[0]);
            //     $list = $lists;
            // }
        } else {
            // $list = sort_array($list, 'rate', 'SORT_ASC');
            $edit = array_column($list, 'rate');
            array_multisort($edit, SORT_ASC, $list);
        }
        $return['buy_list'] = $this->promotionreport();
        $return['plan_list'] = $list;
        $this->success('', $return);
    }

    public function label()
    {
        $return = (new \app\api\model\Financeproject())->getLabelList();
        $this->success('', $return);
    }

    public function promotionreport()
    {
        //列表
        $data = [];
        for ($i = 0; $i < 20; $i++) {
            //随机电话号段
            $my_array = array("9");
            $hd = array_rand($my_array, 1);
            $begin = $my_array[$hd];
            $a = rand(10, 99);
            $b = rand(100, 999);
            //随机奖励金额
            $pay_amount = "1000,5000,10000,3200,4500,6000,7000,10000,23000,28000,100,400,2000,8000";
            $top_up_array = explode(",", $pay_amount);
            $hds = array_rand($top_up_array, 1);
            $begins = $top_up_array[$hds];
            $nickname = phonenumber();
            $data[] = [
                'amount' => $begins,
                'nickname' => $nickname
            ];
        }
        shuffle($data);
        return $data;
    }
}
