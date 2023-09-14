<?php

namespace app\api\controller;

use app\admin\model\financebuy\FinanceOrder as FinancebuyFinanceOrder;
use app\api\model\Finance as ModelFinance;
use app\api\model\Financeissue;
use app\api\model\Financeorder as ModelFinanceorder;
use app\api\model\Financeproject;
use app\api\model\Order as ModelOrder;
use app\api\model\Orderoften;
use app\api\model\Teamlevel;
use app\api\model\User;
use think\cache\driver\Redis;
use think\Config;
use think\helper\Time;
use think\Log;

/**
 * 理财活动下单
 */
class Financeorder extends Controller
{

    /**
     * 理财下单
     *
     * @ApiMethod (POST)
     * @param string $project_id 项目ID
     * @param string $num 购买数量
     */

    public function addorder()
    {
        $this->verifyUser();
        $userinfo = $this->userInfo;
        //下单时间限制
        $redis = new Redis();
        $redis->handler()->select(2);
        $last = $redis->handler()->get("zclc:financeordertime:" . $this->uid);
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
        $post = $this->request->post();
        $project_id = $this->request->post("project_id"); //项目ID
        $amount = $this->request->post("amount"); //购买金额
        if (!$amount || !$project_id) {
            $this->error(__('parameter error'));
        }
        //方案是否存在
        $project_info = (new Financeproject())->detail($project_id);
        if (!$project_info) {
            $this->error(__("Activities that don't exist"));
        }
        if ($amount % $project_info['fixed_amount'] != 0) {
            $this->error(__("Amount error"));
        }
        $copies = $amount / $project_info['fixed_amount']; //份数
        //是否购买过新手方案
        if ($project_info['is_new_hand'] == 1) {
            if ($copies != 1) {
                $this->error(__("Each user is limited to purchase ".$project_info['fixed_amount']." pesos"));
            }
            $is_buy = db('finance_order')->where(['user_id' => $this->uid, 'is_robot' => 0, 'is_new_hand' => 1])->field('id')->find();
            if (!empty($is_buy)) {
                $this->error(__("Each user can only invest in this type of plan once"));
            }
        }
        if ($project_info['popularize'] == 1) {
            //推广项目，相同方案同时只能购买一份
            //            if($copies != 1){
            //                //推广项目单次只能购买一份
            //                $this->error(__("Only one copy can be purchased at a time"));
            //            }
        } elseif ($project_info['popularize'] == 2) {
            //体验项目（只能购买一份、购买一次）
            //            $order = (new ModelFinanceorder())->where(['user_id'=>$userinfo['id'],'project_id'=>$project_id])->count();
            //            if($order){
            //                $this->error(__("You have purchased an experience item"));
            //            }
            if ($userinfo['is_experience'] == 1) {
                $this->error(__("You have purchased an experience item"));
            }
            if ($copies != 1) {
                //体验项目只能购买一份
                $this->error(__("Only one experience item can be purchased"));
            }
        } else {
            //普通项目，判断用户称号等级是否小于项目称号等级
            if ($userinfo['buy_level'] < $project_info['buy_level']) {
                $level = (new Teamlevel())->detail($project_info['buy_level']);
                $this->error(__("You need to upgrade to ") . $level['name']);
            }
        }

        //方案是否在进行中
        if ($project_info['status'] == 0) {
            $this->error(__("Activity not started"));
        }
        //众筹是否在进行中
        $finance_info = (new \app\api\model\Finance())->detail($project_info['f_id']);
        if ($finance_info['status'] == 0) {
            $this->error(__("Activity not started"));
        }

        //判断金额是否正确
        if ($amount != bcmul($project_info['fixed_amount'], $copies, 2)) {
            $this->error(__('Amount error'));
        }

        //判断余额(体验项目不判断余额)
        if ($project_info['popularize'] != 2) {
            if ($amount > $userinfo['money']) {
                $this->error(__('Your balance is not enough'), '', 10);
            }
        }
        $addorder = (new ModelFinanceorder())->addorder($post, $userinfo, $amount, $project_info, $copies);
        if (!$addorder) {
            $this->error(__('order failed'));
        }
        $this->success(__('order successfully'), $addorder);
    }

    /**
     * 我的众筹
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myOrder()
    {
        $this->verifyUser();
        $redis = new Redis();
        $redis->handler()->select(6);
        $field = ['id', 'name', 'image', 'content', 'list_content', 'endtime', 'status', 'money'];
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $where = [
            'user_id' => $this->uid,
            'is_robot' => 0
        ];
        $statuss = $this->request->param('status');
        if (intval($statuss) > 0) {
            //1收益中 2已结束
            $f_ids = (new \app\api\model\Financeorder())->where(['user_id' => $this->uid, 'status' => 1,'is_robot'=>0])->column('f_id');
            if (intval($statuss) == 2) {
                $f_ids = (new \app\api\model\Financeorder())->where(['user_id' => $this->uid, 'f_id' => ['not in', $f_ids],'is_robot'=>0])->column('f_id');
            }
            $where['f_id'] = ['in', $f_ids];
        }
        $total = (new \app\api\model\Financeorder())->where($where)->group('f_id')->count();
        $list = (new \app\api\model\Financeorder())
            ->field('f_id,sum(amount) amount,sum(earnings) earnings,sum(estimated_income) estimated_income')
            ->where($where)
            ->order('f_id desc')
            ->group('f_id')
            ->page($page, $pageSize)
            ->select();
        foreach ($list as &$value) {
            $finance_info = (new \app\api\model\Finance())->detail($value['f_id'], $field);
            $order = (new \app\api\model\Financeorder())->where(['f_id' => $value['f_id'], 'user_id' => $this->uid, 'is_robot' => 0])->field('status')->order('status asc')->find();
            $value['name'] = $finance_info['name'];
            $value['image'] = format_image($finance_info['image']);
            $value['content'] = $finance_info['content'];
            $value['list_content'] = $finance_info['list_content'];
            $value['status'] = $order['status'];
            if ($finance_info['endtime'] - time() <= 0) {
                $value['surplus_day'] = 0;
            } else {
                $value['surplus_day'] = ceil(($finance_info['endtime'] - time()) / (60 * 60 * 24));
            }
            $already_buy = $redis->handler()->zScore("zclc:financeordermoney", $value['f_id']); //已认购
            $already_buy = !$already_buy ? 0 : $already_buy;
            $value['already_rate'] = round($already_buy / $finance_info['money'] * 100, 2);
        }
        //已读未读
        db("user")->where(['id' => $this->uid])->update(['earnings_read' => 1]);
        (new User())->refresh($this->uid);
        $result = [
            "total" => $total,
            "rows"  => $list
        ];
        $this->success(__('The request is successful'), $result);
    }

    public function myOrderDetail()
    {
        $this->verifyUser();

        $f_id = $this->request->post("f_id"); //项目ID
        if (!$f_id) {
            $this->error(__('parameter error'));
        }
        $where = [
            'user_id' => $this->uid,
            'f_id' => $f_id,
            'is_robot' => 0
        ];
        $total = (new \app\api\model\Financeorder())->where($where)->count();
        $list = (new \app\api\model\Financeorder())
            ->field('id,amount,earnings,collection_time,status,order_id,buy_time,type,earning_start_time,earning_end_time,surplus_num,buy_rate,num,capital,interest,popularize,estimated_income,project_id')
            ->where($where)
            ->order('createtime desc')
            ->select();
        $total_amount = 0;
        $total_income = 0;
        $total_estimated_income = 0;
        foreach ($list as &$value) {
            $project_info = (new Financeproject())->detail($value['project_id'], ['name']);
            $value['name'] = $project_info['name'];
            $value['estimated_income'] = bcmul($value['interest'], $value['num'], 2);
            $value['amount'] = $value['popularize'] == 2 ? 0 : $value['amount'];
            $total_amount += $value['amount'];
            $total_income += $value['earnings'];
            $total_estimated_income += $value['estimated_income'];
            $value['buy_time'] = date('Y-m-d H:i:s', $value['buy_time']);

            $value['day'] = ($value['earning_end_time'] - $value['earning_start_time']) / 86400; //收益进度 总天数
            if ($value['status'] == 2) {
                $value['already_day'] = $value['day'];
                $value['today_income'] = 0;
            } else {
                $value['already_day'] = intval((time() - $value['earning_start_time']) / 86400); //收益进度 已经收益天数
                $value['today_income'] = $value['earnings'] == 0 ? 0 : (date('H:i:s', time()) > date('H:i:s', $value['collection_time']) ? bcdiv($value['earnings'], ($value['num'] - $value['surplus_num']), 2) : 0);
            }
            $value['earning_end_time'] = date('Y-m-d H:i:s', $value['earning_end_time']);
            $value['total_profit'] = bcmul($value['interest'], $value['num'], 2);
            $amount = $value['popularize'] == 2 ? 0 : $value['amount'];
            $value['total_revenue'] = bcadd($value['total_profit'], $amount, 2);
            if ($value['type'] == 2) {
                $value['daily_income'] = $value['interest'];
            } else {
                $value['daily_income'] = bcadd($value['capital'], $value['interest'], 2);
            }
        }
        $field = ['id', 'name', 'file1', 'file2', 'file1_name', 'file2_name', 'popularize'];
        $redis = new Redis();
        $redis->handler()->select(6);
        $finance_info = (new \app\api\model\Finance())->detail($f_id, $field);
        $statistics = [
            'finance_id' => $finance_info['id'],
            'finance_name' => $finance_info['name'],
            'order_num' => $total,
            'total_amount' => $total_amount,
            'total_income' => $total_income,
            'total_estimated_income' => $total_estimated_income,
            'file1_name' => $finance_info['file1_name'],
            'file1' => $finance_info['file1'],
            'file2_name' => $finance_info['file2_name'],
            'file2' => $finance_info['file2'],
            'popularize' => $finance_info['popularize']
        ];
        $result = [
            "total" => $total,
            "rows"  => $list,
            'statistics' => $statistics
        ];
        $this->success(__('The request is successful'), $result);
    }

    public function orderDetail()
    {
        $this->verifyUser();
        $order_id = $this->request->post("order_id");
        if (!$order_id) {
            $this->error(__('parameter error'));
        }
        $info = (new \app\api\model\Financeorder())->where(['user_id' => $this->uid, 'order_id' => $order_id, 'is_robot' => 0])->find();
        if (!$info) {
            $this->error(__('Order does not exist'));
        }
        $project_info = (new Financeproject())->detail($info['project_id']);
        $image = (new \app\api\model\Finance())->where(['id' => $project_info['f_id']])->value('image');
        $return = [
            'name' => $project_info['name'],
            'image' => format_image($image),
            'amount' => $info['amount'],
            'type' => $project_info['type'],
            'starttime' => date('Y-m-d H:i:s', $info['earning_start_time']),
            'endtime' => date('Y-m-d H:i:s', $info['earning_end_time']),
            'paytime' => date('Y-m-d H:i:s', $info['buy_time']),
            'order_id' => $info['order_id'],
            'day' => $project_info['day'],
            'rate' => $project_info['rate'],
            'f_id' => $project_info['f_id'],
            'per_invite' => 2,
            'invite_money' => $project_info['fixed_amount'],
            'popularize' => $project_info['popularize']
        ];
        $return['total_profit'] = bcmul($info['interest'], $project_info['day'], 2);
        $amount = $info['popularize'] == 2 ? 0 : $info['amount'];
        $return['total_revenue'] = bcadd($return['total_profit'], $amount, 2);
        if ($project_info['type'] == 2) {
            $return['daily_income'] = bcmul($project_info['interest'], $info['copies'], 2);
        } else {
            $return['daily_income'] = bcadd($project_info['capital'] * $info['copies'], $project_info['interest'] * $info['copies'], 2);
        }
        $level = (new Teamlevel())->detail($project_info['buy_level']);
        $return['buy_level_name'] = $level['name'] ?? '';
        $return['buy_level_image'] = !empty($level['image']) ? format_image($level['image']) : '';
        $this->success(__('The request is successful'), $return);
    }
}
