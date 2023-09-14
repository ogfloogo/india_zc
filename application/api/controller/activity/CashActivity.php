<?php

namespace app\api\controller\activity;

use app\api\controller\Controller;
use app\api\model\activity\UserActivityPrize;
use app\api\model\activity\UserActivityTask;
use app\api\model\activity\CashActivity as ModelCashActivity;
use app\api\model\activity\UserActivityMoneyLog;
use think\Config;

class CashActivity extends Controller
{
    public function awardlist()
    {
        $this->verifyUser();
        $level = $this->userInfo['level'];
        $list = (new ModelCashActivity())->list($level, $this->userInfo);
        $res['list'] = $list;
        $res['reward_cash'] = Config::get('site.reward_cash');
        $res['my_info'] = (new UserActivityMoneyLog())->getCashInfo($this->userInfo['id'], (new ModelCashActivity())->getActivityId(), Config::get('site.reward_cash'));
        $this->success(__('The request is successful'), $res);
    }

    public function receive()
    {
        $this->verifyUser();
        $user_prize_id = $this->request->param('user_prize_id', 0);
        if (!$user_prize_id) {
            $this->error(__('request_failed'));
        }
        $prize_model = (new UserActivityPrize());
        $prize_model->setTableName($this->userInfo['id']);
        $task_model = (new UserActivityTask());
        $task_model->setTableName($this->userInfo['id']);
        $user_prize_info = $prize_model->getInfoById($user_prize_id);
        if (!$user_prize_info) {
            $this->error(__('data_no_exist'));
        }
        if ($user_prize_info['activity_id'] != (new ModelCashActivity())->getActivityId()) {
            $this->error(__('data_no_exist'));
        }
        if ($user_prize_info['user_id'] != $this->userInfo['id']) {
            $this->error(__('data_no_exist'));
        }
        if ($user_prize_info['status'] == 1) {
            $this->error(__('the_prize_has_received'));
        }
        if ($user_prize_info['status'] == 2 || ($user_prize_info['prize_expiretime'] && $user_prize_info['prize_expiretime'] < time())) {
            $this->error(__('the_prize_is_expired'));
        }

        $user_task_info = $task_model->getInfoById($user_prize_info['user_task_id']);
        if ($user_task_info['status'] != 2) {
            $this->error(__('the_task_not_finished'));
        }
        $res = $prize_model->doReceive($user_prize_id, $user_prize_info, true);
        if ($res) {
            $this->success(__('The request is successful'), $res);
        }
        $this->error(__('request_failed'));
    }

    public function receivecash()
    {
        $this->verifyUser();
        $my_cash_info = (new UserActivityMoneyLog())->getCashInfo($this->userInfo['id'], (new ModelCashActivity())->getActivityId(), Config::get('site.reward_cash'));
        if (!$my_cash_info['receivable']) {
            $this->error(__('can_not_receive_cash'));
        }
        if ($my_cash_info['receivable'] == 2) {
            $this->error(__('already_received'));
        }

        $money_amount = $my_cash_info['my_cash'];
        $is_received = (new UserActivityMoneyLog())->doReceiveCash($this->userInfo['id'], $this->userInfo, (new ModelCashActivity())->getActivityId(), $money_amount);
        if ($is_received) {
            $res['receive_money'] = $money_amount;
            $res['reward_cash'] = Config::get('site.reward_cash');
            $res['my_info'] = (new UserActivityMoneyLog())->getCashInfo($this->userInfo['id'], (new ModelCashActivity())->getActivityId(), Config::get('site.reward_cash'));
            $this->success(__('The request is successful'), $res);
        } else {
            $this->error(__('request_failed'));
        }
    }

    public function test()
    {
        $this->verifyUser();
        for ($i = 0; $i < 11; $i++) {
            (new UserActivityTask())->updateTaskResult($i, $this->uid);
        }
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
            $str1 = Config::get('site.reward_cash');
            //随机电话号段
            $nickname = buildMobile();
            $replace = [];
            $replace[] = $nickname;
            $replace[] = Config::get('site.currency').$str1;

            $str_map = [
                "english" => 'User NAME has received gift MONEY',
                "india" => 'उपयोगकर्ता NAME उपहार XXX प्राप्त हुआ है',
                "spain" => 'Usuario NAME ha recibido regalo MONEY',
                "portugal" => 'Usuário NAME recebeu presente MONEY',

            ];
            $str = isset($str_map[$this->language]) ? $str_map[$this->language] : $str_map['english'];
            $data[] = str_replace(['NAME', 'MONEY'], $replace, $str);            
        }
        shuffle($data);
        $this->success(__('The request is successful'), $data);
    }
}
