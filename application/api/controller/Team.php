<?php

namespace app\api\controller;

use app\api\model\Commission;
use app\api\model\Level;
use app\api\model\Popularizeuser;
use app\api\model\Teamlevel;
use app\api\model\Userteam;
use app\api\model\Usertotal;
use app\common\model\MoneyLog;
use think\helper\Time;

/**
 * 团队
 */
class Team extends Controller
{
    /**
     *团队统计
     *
     */
    public function myteamtotal()
    {
        $this->verifyUser();
        $list = (new Userteam())->myteamtotal($this->uid,$this->userInfo);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 团队列表
     */
    public function myteamlist()
    {
        $this->verifyUser();
        $post = $this->request->post();
        $page = $this->request->post('page'); //ID
        if (!$page) {
            $this->error(__('parameter error'));
        }
        $list = (new Userteam())->myteamlist($post,$this->uid);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 团队列表-teamsize
     */
    public function myteamsizelist()
    {
        $this->verifyUser();
        $post = $this->request->post();
        $page = $this->request->post('page'); //ID
        if (!$page) {
            $this->error(__('parameter error'));
        }
        $list = (new Userteam())->myteamsizelist($post,$this->uid);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 今日佣金，总佣金记录列表
     */
    public function commissionlist()
    {
        $this->verifyUser();
        $post = $this->request->post();
        $page = $this->request->post('page'); //ID
        if (!$page) {
            $this->error(__('parameter error'));
        }
        $list = (new Userteam())->commissionlist($post,$this->uid);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 我的下级各等级人数统计
     */
    public function childlevel(){
        $this->verifyUser();
        $level = $this->request->post('level'); //ID
        if (!$level) {
            $this->error(__('parameter error'));
        }
        $list = (new Userteam())->childlevel($level,$this->uid);
        $this->success(__('The request is successful'), $list);
    }
    
    /**
     * 我的下级各等级超过我的人数统计2
     */
    public function childlevelsurpass(){
        $this->verifyUser();
        $level = $this->request->post('level'); //ID
        if (!$level) {
            $this->error(__('parameter error'));
        }
        $list = (new Userteam())->childlevelsurpass($level,$this->userInfo);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 我的下级各等级超过我的人数统计3
     */
    public function childlevelsurpasss(){
        $this->verifyUser();
        $level = $this->request->post('level'); //ID
        if (!$level) {
            $this->error(__('parameter error'));
        }
        $list = (new Userteam())->childlevelsurpasss($level,$this->userInfo);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 我的下级各等级人数统计-总
     */
    public function childleveltotal(){
        $this->verifyUser();
        $list = (new Userteam())->childleveltotal($this->uid);
        $this->success(__('The request is successful'), $list);
    }
    
    /**
     * 我的下级各等级超过我的人数统计2-总
     */
    public function childlevelsurpasstotal(){
        $this->verifyUser();
        $list = (new Userteam())->childlevelsurpasstotal($this->userInfo);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 我的下级各等级超过我的人数统计3-总
     */
    public function childlevelsurpassstotal(){
        $this->verifyUser();
        $list = (new Userteam())->childlevelsurpassstotal($this->userInfo);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 团队成员列表
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myTeamMember(){
        $this->verifyUser();
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $where = ['user_id'=>$this->uid];
        if($this->request->param('level')){
            $where['level'] = $this->request->param('level');
        }
        $total = (new Userteam())->where($where)->count();
        $list = (new Userteam())
            ->where($where)
            ->order('createtime desc')
            ->page($page, $pageSize)
            ->select();
        $commission = new Commission();
        foreach ($list as &$value){
            $user = (new \app\api\model\User())->field('nickname,avatar,jointime')->where(['id'=>$value['user_id']])->find();
            $value['jointime'] = date('Y-m-d H:i:s',$user['jointime']);
            $value['nickname'] = $user['nickname'];
            $value['avatar'] = format_image($user['avatar']);
            $commission->setTableName($this->uid);
            $value['today_commission'] = $commission->where(['to_id'=>$this->uid,'from_id'=>$value['team']])->whereTime('createtime','today')->sum('commission');
            $value['history_commission'] = $commission->where(['to_id'=>$this->uid,'from_id'=>$value['team']])->sum('commission');
        }
        $result = [
            "total" => $total,
            "rows"  => $list
        ];
        $this->success('',$result);
    }

    /**
     * 团队等级详情
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function teamLevelInfo(){
        $teamlevel = (new Level());
//        $info = Teamlevel::where('1=1')->order('level asc')->select();
        $info =$teamlevel->tablelist();
        $condition = [];
        $benefit = [];
        foreach ($info as $value){
            $condition[] = [
                'name' => $value['name'],
                'level' => $value['level'],
                'need_num' => $value['need_num'],
                'need_user_recharge' => $value['need_user_recharge'],
            ];
            $benefit[] = [
                'name' => $value['name'],
                'level' => $value['level'],
                'cash' => $value['cash'],
                'salary' => $value['salary'],
                'rate1' => $value['rate1'],
            ];
        }
        $this->success('',['condition'=>$condition,'benefit'=>$benefit]);
    }

    /**
     * 今日、历史佣金详情
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function commissionDetails(){
        $this->verifyUser();
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $where = ['to_id'=>$this->uid];
        if($this->request->param('level')){
            $where['level'] = $this->request->param('level');
        }
        if($this->request->param('today')){
            $time = Time::today();
            $where['createtime'] = ['between', [$time[0], $time[1]]];
        }
        $commission = new Commission();
        $commission->setTableName($this->uid);
        $total = $commission->where($where)->count();
        $list = $commission
            ->field('to_id,from_id,createtime,commission')
            ->where($where)
            ->order('createtime desc')
            ->page($page, $pageSize)
            ->select();
        foreach ($list as &$value){
            $user = (new \app\api\model\User())->field('nickname,avatar')->where(['id'=>$value['to_id']])->find();
            $value['nickname'] = $user['nickname'];
            $value['avatar'] = format_image($user['avatar']);
        }
        $result = [
            "total" => $total,
            "rows"  => $list
        ];
        $this->success('',$result);
    }

    /**
     * 推广激励收入列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function promotionAwardList(){
        $this->verifyUser();
        $log = new \app\admin\model\user\UserMoneyLog();
        $log->setTableName($this->uid);
        $rs = $log->field('id,money,createtime')->where(['user_id' => $this->uid, 'type' => 24])->order('id desc')->select();
        foreach ($rs as &$value){
            $value['createtime'] = date('Y-m-d H:i:s');
        }
        $this->success('',$rs);
    }

    public function cashAwardList(){
        $this->verifyUser();
        $log = new \app\admin\model\user\UserMoneyLog();
        $log->setTableName($this->uid);
        $rs = $log->field('id,money,createtime,remark')->where(['user_id' => $this->uid, 'type' => 25])->order('id desc')->select();
        foreach ($rs as &$value){
            $value['createtime'] = date('Y-m-d H:i:s');
        }
        $this->success('',$rs);
    }

    public function team(){
        $this->verifyUser();
        $userInfo = $this->userInfo;
        $return = [];
//        if($userInfo['level'] == 0){
//            //未开通团队 code=2
//            $data['is_team'] = 0;
//            $this->success(__('The request is successful'),$data);
//        }else{
            $return['is_team'] = 1;
            $pid_info = (new \app\api\model\User())->where(['id'=>$userInfo['sid']])->field('id,nickname,avatar,mobile,buy_level')->find();
            if($pid_info){
                $level = (new Teamlevel())->detail($pid_info['buy_level']);
                $pid_info['buy_level_name'] = $level['name']??'';
                $pid_info['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
                $pid_info['avatar'] = format_image($pid_info['avatar']);
            }
            $return['my_manager'] = $pid_info;
            $level1_user = (new Userteam())->where(['user_id'=>$userInfo['id'],'level'=>1])->column('team');
            $level2_user = (new Userteam())->where(['user_id'=>$userInfo['id'],'level'=>2])->column('team');
            $return['my_team'] = [
                'level1' => [
                    'rate' => config('site.first_team'),
                    'recharge' => bcadd((new Usertotal())->where(['user_id'=>['in',$level1_user]])->sum('total_recharge'),0,2),
                    'commission' => (new Usertotal())->where(['user_id'=>$userInfo['id']])->value('first_commission'),
                    'team_num' => count($level1_user),
                    'partner_num' => (new \app\api\model\User())->where(['id'=>['in',$level1_user],'level'=>['<>',0]])->count()
                ],

                'level2' => [
                    'rate' => config('site.second_team'),
                    'recharge' => bcadd((new Usertotal())->where(['user_id'=>['in',$level2_user]])->sum('total_recharge'),0,2),
                    'commission' => (new Usertotal())->where(['user_id'=>$userInfo['id']])->value('second_commission'),
                    'team_num' => count($level2_user),
                    'partner_num' => (new \app\api\model\User())->where(['id'=>['in',$level2_user],'level'=>['<>',0]])->count()
                ],
            ];
            $user_total = (new Usertotal())->where(['user_id'=>$userInfo['id']])->find();
            $return['statistics'] = [
                'total_income' => bcadd($user_total['first_commission'],$user_total['second_commission'],2),
                'total_team_num' => $return['my_team']['level1']['team_num'] + $return['my_team']['level2']['team_num'],
                'total_recharge' => bcadd($return['my_team']['level1']['recharge'],$return['my_team']['level2']['recharge'],2),
            ];
            $this->success(__('The request is successful'), $return);
//        }
    }

    public function teamLevel(){
        $this->verifyUser();
        $userInfo = $this->userInfo;
        $level = $this->request->post('level');
        if (!$level) {
            $this->error(__('parameter error'));
        }
        $level2 = $level;
        $where = [];
        if($this->request->post('type')){
            if($this->request->post('type') == 1){
                $where['buy_level'] = 0;
            }
            if($this->request->post('type') == 2){
                $where['buy_level'] = ['<>',0];
            }
        }

        $user_ids = (new Userteam())->where(['user_id'=>$userInfo['id'],'level'=>$level])->column('team');
        $list = (new \app\api\model\User())->where(['id'=>['in',$user_ids]])->where($where)->select();
        $return = [];
        $return['list'] = [];
        $user_commission = new Commission();
        $user_commission->setTableName($this->uid);
        $amount = (new \app\api\model\Financeproject())->where(['popularize'=>1,'status'=>1,'buy_level'=>$userInfo['buy_level']])->value('fixed_amount');
        foreach ($list as $key => $value){
            $order_money = (new \app\api\model\Financeorder())->where(['user_id'=>$value['id'],'status'=>1,'is_robot'=>0,'popularize'=>['<>',2]])->sum('amount');
            $level = (new Teamlevel())->detail($value['buy_level']);
            $return['list'][$key] = [
                'nickname' => $value['nickname'],
                'mobile' => $value['mobile'],
                'avatar' => format_image($value['avatar']),
                'buy_level' => $value['buy_level'],
                'buy_level_name' => $level['name']??'',
                'buy_level_image' => !empty($level['image'])?format_image($level['image']):'',
                'property' => bcadd($value['money'],$order_money,2),
                'profit' => (new Usertotal())->where(['user_id'=>$value['id']])->value('crowdfunding_income'),
                'commission' => bcadd($user_commission->where(['to_id'=>$userInfo['id'],'from_id'=>$value['id']])->sum('commission'),0,2),
//                'award' => (new Popularizeuser())->where(['pid'=>$userInfo['id'],'user_id'=>$value['id'],'is_condition'=>1])->sum('award')??0,
                'recharge' => bcadd((new Usertotal())->where(['user_id'=>$value['id']])->value('total_recharge'),0,2),
            ];
            if($value['level'] == 0&&$value['buy_level'] == 0){
                //普通用户
                $level = (new Teamlevel())->detail(0);
                $return['list'][$key]['buy_level'] = $userInfo['buy_level'];
                $return['list'][$key]['buy_level_name'] = $level['name']??'';
                $return['list'][$key]['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
                $return['list'][$key]['status'] = 0;
                $return['list'][$key]['receive_award'] = $amount ?? 0;
                $return['list'][$key]['not_award'] = 0;
                $return['list'][$key]['award'] = 0;
            }else{
                $return['list'][$key]['status'] = 1;
                $return['list'][$key]['award'] = (new Popularizeuser())->where(['user_id'=>$value['id'],'is_condition'=>1])->sum('award');
                $return['list'][$key]['not_award'] = (new Popularizeuser())->where(['user_id'=>$value['id'],'is_condition'=>0])->sum('award');
                $return['list'][$key]['receive_award'] = 0;
            }
        }
        $level_text = $level2==1?'first':'second';
        $return['statistics'] = [
            'rate' => config("site.{$level_text}_team"),
            'total_recharge' => bcadd((new Usertotal())->where(['user_id'=>['in',$user_ids]])->sum('total_recharge'),0,2),
            'total_commission' => bcadd((new Usertotal())->where(['user_id'=>$userInfo['id']])->value("{$level_text}_commission"),0,2),
            'team_num' => count($user_ids),
            'partner_num' => (new \app\api\model\User())->where(['id'=>['in',$user_ids],'level'=>['<>',0]])->count()
        ];
        $this->success(__('The request is successful'), $return);
    }
}
