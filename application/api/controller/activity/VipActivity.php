<?php

namespace app\api\controller\activity;

use app\api\controller\Controller;
use app\api\model\activity\UserActivityPrize;
use app\api\model\activity\UserActivityTask;
use app\api\model\activity\VipActivity as ModelVipActivity;

class VipActivity extends Controller
{
    public function awardlist()
    {
        $this->verifyUser();
        $level = $this->userInfo['level'];
        $awardlist = (new ModelVipActivity())->list($level, $this->userInfo);
        $res['list'] = $awardlist;
        $this->success(__('The request is successful'), $res);
    }

    public function receive()
    {
        $this->verifyUser();
        $user_prize_id = $this->request->param('id', 0);
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
        if ($user_prize_info['activity_id'] != (new ModelVipActivity())->getActivityId()) {
            $this->error(__('data_no_exist'));
        }
        if ($user_prize_info['user_id'] != $this->userInfo['id']) {
            $this->error(__('data_no_exist'));
        }
        if ($user_prize_info['status'] == 1) {
            $this->error(__('the_prize_has_received'));
        }
        if ($user_prize_info['status'] == 2 || $user_prize_info['prize_expiretime'] < time()) {
            $this->error(__('the_prize_is_expired'));
        }

        $user_task_info = $task_model->getInfoById($user_prize_info['user_task_id']);
        if ($user_task_info['status'] != 2) {
            $this->error(__('the_task_not_finished'));
        }
        $res = $prize_model->doReceive($user_prize_id, $user_prize_info);
        if ($res) {
            $this->success(__('The request is successful'), $res);
        }
        $this->error(__('request_failed'));
    }
}
