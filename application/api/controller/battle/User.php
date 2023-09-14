<?php

namespace app\api\controller\battle;

use app\admin\model\User as AdminModelUser;
use app\api\controller\Controller;
use app\api\model\battle\BattleRound;
use app\api\model\battle\BattleTeam;
use app\api\model\battle\BattleTeamApply;
use app\api\model\battle\BattleTeamMember;
use app\api\model\battle\BattleTeamRound;
use app\api\model\User as ModelUser;
use app\api\model\Usercategory;
use app\api\model\Usertotal;
use app\common\library\Sms;
use app\common\model\User as CommonModelUser;
use think\cache\driver\Redis;
use think\Log;


class User extends Controller
{
    protected $noNeedLogin = ['login'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
        $this->verifyUser();
        // $this->teamInfo = (new BattleTeam())->where(['user_id' => $this->uid])->find();
        // if (empty($this->teamInfo)) {
        //     $this->error(__('YOU_HAVE_NO_TEAM'));
        // }
    }

    public function home()
    {
        //TODO 存redis
        $list = (new BattleTeam())->where(['id' => ['GT', 0]])->order('team_power DESC')->limit(10)->select();
        $order = 1;
        $newList = [];
        foreach ($list as $item) {
            $newItem['rank'] = $order++;
            $newItem['team_id'] = $item['id'];
            $newItem['logo_image'] = format_image($item['logo_image']);
            $newItem['team_power'] = $item['team_power'];
            $newList[] = $newItem;
        }
        $res['list'] = $newList;
        $my_team_id = (new BattleTeamMember())->where(['user_id' => $this->uid])->value('team_id');
        $res['my_team_id'] = $my_team_id;
        $this->success(__('SUCC'), $res);
    }

    public function teamlist()
    {
        //TODO 存redis 分页
        $list = (new BattleTeam())->where(['id' => ['GT', 0]])->order('weigh DESC')->select();
        $newList = [];
        foreach ($list as $item) {
            $newItem['team_id'] = $item['id'];
            $newItem['logo_image'] = format_image($item['logo_image']);
            $newItem['team_people_num'] = $item['team_people_num'];
            //TODO user redIS
            $newItem['head'] = (new BattleTeam())->getHeadInfo($item['user_id']);
            $newItem['is_applyed'] = (new BattleTeamApply())->where(['user_id' => $this->uid, 'team_id' => $item['id']])->count();
            $newList[] = $newItem;
        }
        $res['list'] = $newList;
        $this->success(__('SUCC'), $res);
    }

    public function apply()
    {
        $team_id = $this->request->param('team_id',0);
        if(empty($team_id)){
            $this->error(__('YOU_HAVE_NOT_SELECT_TEAM'));
        }

        $exist = (new BattleTeamApply())->where(['user_id'=>$this->uid,'team_id'=>$team_id])->count();
        if($exist){
            $this->error(__('ALREADY_APPLIED'));
        }

        $is_added = (new BattleTeamApply())->addApply($this->uid,$team_id);
        //TODO 
        $res = [];
        if($is_added){
            $this->success(__('SUCC'), $res);
        }else{
            $this->error(__('FAILED'));
        }
        
    }

    public function applylist()
    {
        //TODO  分页
        $map = [0=>'waiting',1=>'applied',2=>'rejected'];
        $list = (new BattleTeamApply())->where(['user_id' => $this->uid])->order('id DESC')->select();
        $newList = [];
        foreach ($list as $item) {
            //TODO redis Team
            $newItem['team_info'] = (new BattleTeam())->getInfo($item['id']);
            $newItem['apply_time'] = $item['createtime'];
            $newItem['status_desc'] = $map[$item['status']];
            $newList[] = $newItem;
        }
        $res['list'] = $newList;
        $this->success(__('SUCC'), $res);
    }

    public function myteaminfo(){
        $my_team_id = (new BattleTeamMember())->where(['user_id' => $this->uid])->value('team_id');

        if(empty($my_team_id)){
            $this->error(__('YOU_HAVE_NOT_SELECT_TEAM'));
        }

        //TODO 没有team_id
        $teamInfo = (new BattleTeam())->getInfo($my_team_id);
        $teamInfo['rank'] = 0;
        $roundInfo = (new BattleRound())->getCurrentInfo();
        if(empty($roundInfo)){
            $this->error(__('SYSTEM_ERROR'));
        }
        $teamInfo['round_id'] = $roundInfo['id'];

        //TODO 如果没有报错
        $roundInfo = (new BattleTeamRound())->getCurrentInfo($my_team_id,$roundInfo['id']);
        $teamInfo['prize_pool'] = $roundInfo['prize_pool'];
        $teamInfo['finish_people'] = $roundInfo['finish_people'];
        $res['info'] = $teamInfo;
        $this->success(__('SUCC'), $res);

    }
}
