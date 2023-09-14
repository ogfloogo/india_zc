<?php

namespace app\api\controller\activity;

use app\api\controller\Controller;
use app\api\model\activity\ActivityLogisticsOrder;
use app\api\model\activity\ActivityWarehouse;
use app\api\model\activity\LuckyDraw as ModelLuckyDraw;
use app\api\model\activity\LuckyDrawPrize;
use app\api\model\activity\LuckyDrawTask;
use app\api\model\activity\UserDrawLog;
use app\api\model\activity\UserLuckyDrawMoney;
use app\api\model\Useraddress;
use think\Config;

class LuckyDraw extends Controller
{

    public function cashprice()
    {
        $this->verifyUser();
        $user_draw_log_model = (new UserDrawLog());
        $user_draw_log_model->setTableName($this->userInfo['id']);
        $res['my_draw_info'] = $user_draw_log_model->getMyDrawInfo($this->userInfo['id'], $this->userInfo);

        $res['draw_config'] = (new ModelLuckyDraw())->formatInfo(0, $this->userInfo);

        $this->success(__('The request is successful'), $res);
    }

    public function receivecash()
    {
        $this->verifyUser();
        $user_draw_log_model = (new UserDrawLog());
        $user_draw_log_model->setTableName($this->userInfo['id']);
        $my_draw_info = $user_draw_log_model->getMyDrawInfo($this->userInfo['id'], $this->userInfo);
        $draw_config = (new ModelLuckyDraw())->formatInfo(0, $this->userInfo);
        $today_cash = $my_draw_info['today_prize_cash'];
        $prize_moneys = $draw_config['prize_moneys'];
        if (bccomp($today_cash, $prize_moneys[0]) == -1) {
            $this->error(__('no reach the limit'));
        }
        rsort($prize_moneys);
        $can_withdraw = 0;
        foreach ($prize_moneys as $money_limit) {
            if (bccomp($today_cash, $money_limit) > -1) {
                $can_withdraw = 1;
                break;
            }
        }
        $is_received = false;
        if ($can_withdraw) {
            $money_model = (new UserLuckyDrawMoney());
            $money_model->setTableName($this->userInfo['id']);
            // $is_received = true;
            $is_received = $money_model->doReceive($this->userInfo['id'], $this->userInfo, $money_limit);
        }
        if ($is_received) {
            $res['receive_money'] = $money_limit;
            $user_draw_log_model = (new UserDrawLog());
            $user_draw_log_model->setTableName($this->userInfo['id']);
            $res['my_draw_info'] = $user_draw_log_model->getMyDrawInfo($this->userInfo['id'], $this->userInfo);
            $res['draw_config'] = (new ModelLuckyDraw())->formatInfo(0, $this->userInfo);
            $this->success(__('The request is successful'), $res);
        } else {
            $this->error(__('request_failed'));
        }
    }

    public function drawlist()
    {
        // echo $this->language;
        // echo Config::get('site.fail2')[$this->language];
        $this->verifyUser();
        $level = $this->userInfo['level'];
        $drawlist = (new LuckyDrawPrize())->list($level);
        $res['list'] = $drawlist;

        $user_draw_log_model = (new UserDrawLog());
        $user_draw_log_model->setTableName($this->userInfo['id']);
        $res['my_draw_info'] = $user_draw_log_model->getMyDrawInfo($this->userInfo['id'], $this->userInfo);

        $res['draw_config'] = (new ModelLuckyDraw())->formatInfo(0, $this->userInfo);

        $this->success(__('The request is successful'), $res);
    }

    public function tasklist()
    {
        $this->verifyUser();
        $level = $this->userInfo['level'];
        $tasklist = (new LuckyDrawTask())->list($level, $this->userInfo);
        $res['list'] = $tasklist;
        $this->success(__('The request is successful'), $res);
    }

    public function mydrawlist()
    {
        $this->verifyUser();
        $page = $this->request->param('page', 0);
        $user_draw_log_model = (new UserDrawLog());
        $user_draw_log_model->setTableName($this->userInfo['id']);
        $res['list'] = $user_draw_log_model->list($this->userInfo['id'], $this->userInfo, $page);
        $this->success(__('The request is successful'), $res);
    }

    public function myprizelist()
    {
        $this->verifyUser();
        $page = $this->request->param('page', 0);
        $user_draw_log_model = (new UserDrawLog());
        $user_draw_log_model->setTableName($this->userInfo['id']);
        $res['list'] = $user_draw_log_model->prizelist($this->userInfo['id'], $this->userInfo, $page);
        $this->success(__('The request is successful'), $res);
    }

    public function draw()
    {
        $this->verifyUser();
        $user_draw_log_model = (new UserDrawLog());
        $user_draw_log_model->setTableName($this->userInfo['id']);
        $num = $user_draw_log_model->getDailyDrawNum($this->userInfo['id'], $this->userInfo);
        if ($num <= 0) {
            $this->error(__('no_chance'));
        }

        $res = $user_draw_log_model->doDraw($this->userInfo['id'], $this->userInfo);
        if ($res) {
            $this->success(__('The request is successful'), $res);
        }
        $this->error(__('request_failed'));
    }


    /**
     * 地址确认
     *
     * @ApiMethod (POST)
     * @param string $id 奖品列表id
     * @param string $wid 仓库ID
     * @param string $address_id 地址ID
     */
    public function verifyaddress()
    {
        $this->verifyUser();
        $id = $this->request->post('user_draw_id', 0); //奖品列表id
        $warehouse_id = $this->request->post('warehouse_id', 0); //仓库ID
        $address_id = $this->request->post('address_id', 0); //地址ID
        if (!$id || !$warehouse_id || !$address_id) {
            $this->error(__('parameter error'));
        }
        $warehouse_info = (new ActivityWarehouse())->getInfoById($warehouse_id);
        if (empty($warehouse_info)) {
            $this->error(__('parameter error'));
        }
        if ($warehouse_info['user_draw_id'] != $id || $warehouse_info['user_id'] != $this->uid) {
            $this->error(__('parameter error'));
        }
        $address_info = (new Useraddress())->where(['id' => $address_id])->find();

        if (empty($address_info)) {
            $this->error(__('parameter error'));
        }

        if ($address_info['user_id'] != $this->uid) {
            $this->error(__('parameter error'));
        }
        $res = (new ActivityLogisticsOrder())->verifyaddress($id, $warehouse_id, $address_id, $this->uid);
        if (!$res) {
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 兑换现金
     *
     * @ApiMethod (POST)
     * @param string $id 仓库ID
     */
    public function exchangemoney()
    {
        $this->verifyUser();
        $warehouse_id = $this->request->post('warehouse_id'); //仓库ID
        if (!$warehouse_id) {
            $this->error(__('parameter error'));
        }
        $userinfo = $this->userInfo;
        $warehouse_info = (new ActivityWarehouse())->where('id', $warehouse_id)->find();
        if ($warehouse_info['status']) {
            $this->error(__('operation failure'));
        }
        $return = (new ActivityWarehouse())->exchangemoney($warehouse_id, $this->uid, $userinfo['level'], $warehouse_info);
        if (!$return) {
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 兑换全部现金 All
     * @ApiMethod (POST)
     */
    public function exchangemoneyall()
    {
        $this->verifyUser();
        $warehouse_list = (new ActivityWarehouse())->where('user_id', $this->uid)->where('status', 0)->select();
        if (!$warehouse_list) {
            $this->error(__('operation failure'));
        }
        $userinfo = $this->userInfo;
        $res = (new ActivityWarehouse())->exchangemoneyall($userinfo);
        if (!$res) {
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 配送详情 状态:0=确认地址(待收货),1=配送商品(已发货),2=商品已送达(待签收),3=已确认收货
     *
     * @ApiMethod (POST)
     */
    public function shippingdetails()
    {
        $this->verifyUser();
        $warehouse_id = $this->request->post('warehouse_id'); //仓库ID
        if (!$warehouse_id) {
            $this->error(__('parameter error'));
        }
        $warehouse_info = (new ActivityWarehouse())->getInfoById($warehouse_id);
        if (empty($warehouse_info)) {
            $this->error(__('parameter error'));
        }
        $order_info = (new ActivityLogisticsOrder())->where('warehouse_id', $warehouse_id)->find();
        if (empty($order_info)) {
            $this->error(__('parameter error'));
        }
        $return = (new ActivityWarehouse())->shippingdetails($warehouse_info, $order_info);
        $this->success(__('The request is successful'), $return);
    }

    /**
     * 确认收货
     *
     * @ApiMethod (POST)
     */
    public function confirm_receipt()
    {
        $this->verifyUser();
        $order_id = $this->request->post('order_id', 0); //订单id
        if (!$order_id) {
            $this->error(__('parameter error'));
        }
        $order_info = (new ActivityLogisticsOrder())->where('id', $order_id)->find();
        if (empty($order_info)) {
            $this->error(__('parameter error'));
        }
        $res = (new ActivityLogisticsOrder())->where('id', $order_id)->update(['status' => 3]);
        if (!$res) {
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }
}
