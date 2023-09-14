<?php

namespace app\api\controller\battle;

use app\api\controller\Controller;
use app\api\model\battle\BattleTeam;
use app\api\model\battle\BattleTeamApply;
use app\api\model\battle\BattleTeamMember;
use app\api\model\battle\TeamUserBonusLog;
use app\api\model\battle\TeamUserContributeLog;
use app\api\model\battle\TeamUserMoneyLog;
use app\api\model\User as ModelUser;
use app\api\model\Usercategory;
use app\api\model\Usertotal;
use app\common\library\Sms;
use think\cache\driver\Redis;
use think\Log;


class Leader extends Controller
{
    protected $noNeedLogin = ['login'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
        $this->verifyUser();
        $this->teamInfo = (new BattleTeam())->where(['user_id' => $this->uid])->find();
        if (empty($this->teamInfo)) {
            $this->error(__('YOU_HAVE_NO_TEAM'));
        }
    }

    public function home()
    {
        //TODO team_user_member
        $res['balance'] = $this->userInfo['money'];
        $res['team_num'] = $this->teamInfo['team_people_num'];
        $res['today_num'] = 0;
        $res['total_income'] = 0;
        $res['today_income'] = 0;
        $res['unreceive_income'] = 0;
        $this->success(__('SUCC'), $res);
    }

    public function withdraw()
    {
        //TODO 
        $res = [];
        $this->success(__('SUCC'), $res);
    }

    public function updateinfo()
    {
        $name = $this->request->param('team_name', '');
        $logo_image = $this->request->param('logo', '');
        $announcement = $this->request->param('announcement', '');
        if (!$name && !$logo_image && !$announcement) {
            $this->error(__('REQUEST_ERROR'));
        }
        if ($name) {
            if ($name == $this->teamInfo['name']) {
                $this->error(__('Name_IS_SAME'));
            } else {
                $data['name'] = $name;
            }
        }
        if ($logo_image) {
            if ($logo_image == $this->teamInfo['logo_image']) {
                $this->error(__('Logo_IS_SAME'));
            } else {
                $data['logo_image'] = $logo_image;
            }
        }
        if ($announcement) {
            if ($announcement == $this->teamInfo['announcement']) {
                $this->error(__('announcement_IS_SAME'));
            } else {
                $data['announcement'] = $announcement;
            }
        }
        $update_res = (new BattleTeam())->updateInfo($this->teamInfo['id'], $data);
        //TODO 
        $res = [];
        if ($update_res) {
            $this->success(__('SUCC'), $res);
        } else {
            $this->error(__('FAILED'));
        }
    }

    public function memberlist()
    {
        $list = (new BattleTeamMember())->where(['team_id' => $this->teamInfo['id'], 'status' => 1])->order('id DESC')->select();
        $newList = [];
        foreach ($list as $item) {
            //TODO redis Team
            $newItem['user_info'] = (new BattleTeam())->getUserInfo($item['user_id']);
            $newItem['total_contribution'] = $item['total_contribution'];
            //TODO 需要统计
            $newItem['today_contribution'] = 0;
            $newList[] = $newItem;
        }
        $res['list'] = $newList;
        $this->success(__('SUCC'), $res);
    }

    public function memberinfo()
    {
        $user_id = $this->request->param('user_id', 0);
        if (empty($user_id)) {
            $this->error(__('ERROR_REQUEST'));
        }
        //TODO 是否已不再team，禁用等
        $info = (new BattleTeamMember())->where(['team_id' => $this->teamInfo['id'], 'user_id' => $user_id, 'status' => 1])->find();
        $newInfo['user_info'] = (new BattleTeam())->getUserInfo($info['user_id']);
        //TODO 需要统计
        $newInfo['total_contribution'] =  $info['total_contribution'];
        $newInfo['today_contribution'] = 0;
        //TODO 需要统计
        $newInfo['total_bonus'] =  $info['total_bonus'];
        $newInfo['pre_bonus'] = 0;
        $newInfo['jointime'] = $info['createtime'];
        $res['info'] = $newInfo;
        $this->success(__('SUCC'), $res);
    }

    public function moneylist()
    {
        $list = (new TeamUserMoneyLog())->getList($this->teamInfo['id'], $this->uid);
        $res['list'] = $list;
        //TODO 
        $info = (new BattleTeamMember())->where(['team_id' => $this->teamInfo['id'], 'user_id' => $this->uid])->find();
        $res['total_income'] = $info['total_income'];
        $res['total_withdraw'] = $info['total_withdraw'];
        $this->success(__('SUCC'), $res);
    }

    public function contributelist()
    {
        $user_id = $this->request->param('user_id', 0);
        if (empty($user_id)) {
            $this->error(__('ERROR_REQUEST'));
        }
        $list = (new TeamUserContributeLog())->getList($this->teamInfo['id'], $user_id);

        $res['list'] = $list;
        $this->success(__('SUCC'), $res);
    }

    public function bonuslist()
    {
        $user_id = $this->request->param('user_id', 0);
        if (empty($user_id)) {
            $this->error(__('ERROR_REQUEST'));
        }
        $list = (new TeamUserBonusLog())->getList($this->teamInfo['id'], $user_id);

        $res['list'] = $list;
        $this->success(__('SUCC'), $res);
    }

    public function applylist()
    {
        //TODO  分页
        $map = [0 => 'waiting', 1 => 'applied', 2 => 'rejected'];
        $list = (new BattleTeamApply())->where(['team_id' => $this->teamInfo['id'], 'status' => 0])->order('id DESC')->select();
        $newList = [];
        foreach ($list as $item) {
            //TODO redis Team
            $newItem['apply_id'] = $item['id'];
            $newItem['user_info'] = (new BattleTeam())->getUserInfo($item['user_id']);
            $newItem['apply_time'] = $item['createtime'];
            $newItem['status_desc'] = $map[$item['status']];
            $newList[] = $newItem;
        }
        $res['list'] = $newList;
        $this->success(__('SUCC'), $res);
    }

    public function auditapply()
    {
        $apply_id = $this->request->param('apply_id', 0);
        if (empty($apply_id)) {
            $this->error(__('YOU_HAVE_NOT_APPLY_THIS_TEAM'));
        }

        //status 1 通过 2 拒绝
        $status = $this->request->param('status', 0);
        if (empty($status)) {
            $this->error(__('ERROR_REQUEST'));
        }
        $exist = (new BattleTeamApply())->where(['id' => $apply_id])->find();
        if (!$exist) {
            $this->error(__('YOU_HAVE_NOT_APPLY_THIS_TEAM'));
        }

        if ($exist['status'] > 0) {
            $this->error(__('YOU_HAVE_DEALED_THIS_APPLY'));
        }


        $deal_res = (new BattleTeamApply())->dealApply($apply_id, $status, $exist);
        //TODO 
        $res = [];
        if ($deal_res) {
            $this->success(__('SUCC'), $res);
        } else {
            $this->error(__('FAILED'));
        }
    }
}
