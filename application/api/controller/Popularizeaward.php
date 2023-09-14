<?php

namespace app\api\controller;

use app\api\model\Popularizeuser;
use app\api\model\Teamlevel;
use app\api\model\Usermoneylog;
use app\api\model\Userteam;
use app\api\model\Usertotal;
use think\Config;
use think\Db;


class Popularizeaward extends Controller
{
    /**
     * 推广激励页（普通用户）
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function noPopularize(){
        $this->verifyUser();
        $finance_info = (new \app\api\model\Finance())->field('id,name,image')->where(['status'=>1,'popularize'=>1])->order('weigh asc')->find();
        $project_info = (new \app\api\model\Financeproject())->list($finance_info['id']);
        foreach ($project_info as &$v){
            unset($v['robot_addorder_time_end']);
            unset($v['robot_addorder_time_start']);
            unset($v['robot_status']);
            unset($v['robot_status_text']);
            unset($v['robot_status']);
            unset($v['status_text']);
            unset($v['type_text']);
            unset($v['popularize_text']);
            $v['image'] = format_image($v['image']);
            $level = (new Teamlevel())->detail($v['buy_level']);
            $v['buy_level_name'] = $level['name']??'';
            $v['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
            $v['total_profit'] = bcmul($v['interest'],$v['day'],2);
            $v['total_revenue'] = bcadd($v['total_profit'],$v['fixed_amount'],2);
            if($v['type'] == 2){
                $v['daily_income'] = $v['interest'];
            }else{
                $v['daily_income'] = bcadd($v['capital'],$v['interest'],2);
            }
        }
        $finance_info['project_info'] = $project_info;
        $this->success(__('The request is successful'),$finance_info);
    }

    /**
     * 推广激励页（推广员过期）
     * @return void
     * @throws \think\Exception
     */
    public function popularizeExpire(){
        $this->verifyUser();
        $userInfo = $this->userInfo;
        $invite_num = (new Userteam())->where(['user_id'=>$userInfo['id'],'level'=>['<>',0]])->column('team');
        $return = [
            'avatar' => $userInfo['avatar'],
            'buy_level' => $userInfo['buy_level'],
            'invite_num' => count($invite_num),
            'partner_num' => (new \app\api\model\User())->where(['id'=>['in',$invite_num,'level'=>['<>',0]]])->count(),
            'award' => (new Usertotal())->where(['user_id'=>$userInfo['id']])->value('promotion_award')
        ];
        $level = (new Teamlevel())->detail($return['buy_level']);
        $return['buy_level_name'] = $level['name']??'';
        $return['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
        $this->success(__('The request is successful'),$return);
    }

    public function popularize(){
        $this->verifyUser();
        $userInfo = $this->userInfo;
        $invite_num = (new Userteam())->where(['user_id'=>$userInfo['id'],'level'=>1])->column('team');
        $return = [
            'avatar' => $userInfo['avatar'],
            'buy_level' => $userInfo['buy_level'],
            'invite_num' => count($invite_num),
            'partner_num' => (new \app\api\model\User())->where(['id'=>['in',$invite_num],'level'=>['<>',0]])->count(),
            'award' => (new Usertotal())->where(['user_id'=>$userInfo['id']])->value('promotion_award')
        ];
        $level = (new Teamlevel())->detail($return['buy_level']);
        $return['buy_level_name'] = $level['name']??'';
        $return['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
        $list = (new \app\api\model\Popularizeaward())->where(['user_id'=>$userInfo['id']])->select();
        foreach ($list as &$value){
            $not_num = (new Popularizeuser())->where(['pid'=>$userInfo['id'],'f_id'=>$value['f_id'],'project_id'=>$value['project_id'],'is_award'=>0,'is_condition'=>1])->count();
            $value['not_num'] = $not_num%2==0?2:1;
            $level = (new Teamlevel())->detail($value['buy_level']);
            $value['buy_level_name'] = $level['name']??'';
            $value['per_invite'] = 2;
            $money = (new \app\api\model\Financeproject())->where(['id'=>$value['project_id']])->value('fixed_amount');
            $value['money'] = $money;
        }
        $return['list'] = $list;
        $this->success(__('The request is successful'),$return);
    }



    /**
     * 推广统计
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function popularizeStatistics(){
        $this->verifyUser();
        $user_info = $this->userInfo;
        $userteam = (new Userteam())->where(['user_id'=>$user_info['id'],'level'=>1])->column('team');
        $amount = (new \app\api\model\Financeproject())->where(['popularize'=>1,'status'=>1,'buy_level'=>$user_info['buy_level']])->value('fixed_amount');
        $list = [];
        $list1 = [];
        $list2 = [];
        foreach ($userteam as $key => &$value){
            $userInfo = (new \app\api\model\User())->field('id,nickname,avatar,mobile,level,buy_level')->where(['id'=>$value])->find();
            $level = (new Teamlevel())->detail($userInfo['buy_level']);
            $list[$key] = [
                "user_id" => $userInfo['id'],
                "buy_level"=> $user_info['buy_level'],
                'buy_level_name'=> $level['name']??'',
                'buy_level_image'=> !empty($level['image'])?format_image($level['image']):'',
                "nickname"=> $userInfo['nickname'],
                "avatar"=> format_image($userInfo['avatar']),
                "mobile"=> $userInfo['mobile'],
            ];

            if($userInfo['level'] == 0&&$userInfo['buy_level'] == 0){
                //普通用户
                $level = (new Teamlevel())->detail(0);
                $list[$key]['buy_level'] = $user_info['buy_level'];
                $list[$key]['buy_level_name'] = $level['name']??'';
                $list[$key]['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
                $list[$key]['status'] = 0;
                $list[$key]['receive_award'] = $amount ?? 0;
                $list[$key]['not_award'] = 0;
                $list[$key]['award'] = 0;
                $list1[] = $list[$key];
            }else{
                $list[$key]['status'] = 1;
                $list[$key]['award'] = (new Popularizeuser())->where(['user_id'=>$value,'is_condition'=>1])->sum('award');
                $list[$key]['not_award'] = (new Popularizeuser())->where(['user_id'=>$value,'is_condition'=>0])->sum('award');
                $list[$key]['receive_award'] = 0;
                $list2[] = $list[$key];
            }
        }
        $return = $list;
        if($this->request->param('type')){
            if($this->request->param('type') == 1){
                $return = $list1;
            }else{
                $return = $list2;
            }
        }
        $this->success(__('The request is successful'), $return);
    }
    /**
     * 领取推广激励金
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function receiveRewards(){
        $this->verifyUser();
        $id = $this->request->param('id');
        if(!$id){
            $this->error(__('parameter error'));
        }
        $award = (new \app\api\model\Popularizeaward())->where(['user_id'=>$this->uid,'id'=>$id])->find();
        if(!$award){
            $this->error(__('operation failure'));
        }
        $finance_id = $award['id'];
        $money = $award['not_claimed'];
        if($award['not_claimed'] <= 0) {
            $this->error(__("Your balance is not enough"));
        }
        Db::startTrans();
        $rs = (new Usermoneylog())->moneyrecords($this->uid, $money, 'inc', 24, "推广激励-项目id:{$finance_id}");
        if(!$rs){
            Db::rollback();
            $this->error(__('operation failure'));
        }
        $award->received = $award->received + $money;
        $award->not_claimed = 0;
        $award->updatetime = time();
        $rs2 = $award->save();
        if(!$rs2){
            Db::rollback();
            $this->error(__('operation failure'));
        }
        $rs3 = (new Popularizeuser())->where(['pid'=>$this->uid,'f_id'=>$award['f_id'],'project_id'=>$award['project_id'],'is_condition'=>1])->update(['is_receive'=>1]);
        if(!$rs3){
            Db::rollback();
            $this->error(__('operation failure'));
        }
        Db::commit();
        $this->success(__('The request is successful'));
    }

    /**
     * 判断推广激励页（code=1 推广员 code=2 普通用户 code=3 推广员过期）
     * @return void
     */
    public function checkPopularizeaward(){
        $this->verifyUser();
        $userInfo = $this->userInfo;
        if($userInfo['buy_level'] == 0){
            //非推广员，判断是新用户还是过期
            //level不等于0代表已经购买过推广项目
            if($userInfo['level'] != 0){
                $data['is_popularize'] = 3;
                $this->success(__('The request is successful'),$data);
            }else{
                $amount = (new \app\api\model\Financeproject())->where(['popularize' => 1, 'status' => 1])->order('fixed_amount desc')->value('fixed_amount');
                $data['amount'] = $amount;
                $data['is_popularize'] = 2;
                $this->success(__('The request is successful'),$data);
            }
        }else{
            $data['is_popularize'] = 1;
            $this->success(__('The request is successful'),$data);
        }
    }


}
