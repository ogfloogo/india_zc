<?php

namespace app\api\controller;

use app\admin\model\activity\Turntablelog;
use app\admin\model\activity\Turntabletimes;
use think\Db;
use app\api\model\Usermoneylog;
use think\Exception;
use think\Log;

/**
 * FAQ
 */
class Turntable extends Controller
{
    public function index(){
        $code = $this->request->post('code');
        if (!$code) {
            $this->error(__('parameter error'));
        }
        $turntable = (new \app\admin\model\activity\Turntable())->where(['code'=>$code,'starttime'=>['<=',time()],'endtime'=>['>=',time()]])->find();
        if(!$turntable){
            $this->error('Activity does not exist');
        }
        $endtime = $turntable['endtime'] - time();
        $prize = array_keys(json_decode($turntable['prize_json'],true));
        $return = [
            'prize' => $prize,
            'endtime' => $endtime,
            'user_info' => []
        ];
        $checklogin = $this->getCacheUser();
        if($checklogin){
            $info = (new Turntabletimes())->where(['a_id'=>$turntable['id'],'user_id'=>$checklogin['id']])->field('times,status')->find();
            if(!empty($info)){
                if($info['status'] == 0 && $turntable['givetimes'] > 0){
                    (new Turntabletimes())->where(['a_id' => $turntable['id'], 'user_id' => $checklogin['id']])->setInc('times', $turntable['givetimes']);
                    (new Turntabletimes())->where(['a_id' => $turntable['id'], 'user_id' => $checklogin['id']])->update(['status'=>1]);
                }
            }else{//记录不存在
                if($turntable['givetimes'] > 0){
                    $create = [
                        'a_id' => $turntable['id'],
                        'user_id' => $checklogin['id'],
                        'times' => $turntable['givetimes'],
                        'status' => 1,
                    ];
                    (new Turntabletimes())->create($create);
                }
            }
            $times = (new Turntabletimes())->where(['a_id'=>$turntable['id'],'user_id'=>$checklogin['id']])->value('times');
            $return['user_info'] = [
                'balance' => $checklogin['money'],
                'avatar' => format_image($checklogin['avatar']),
                'nickname' => $checklogin['nickname'],
                'times' => $times??0
            ];
        }
        $this->success(__('operate successfully'),$return);
    }

    public function lottery(){
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $code = $this->request->post('code');
        if (!$code) {
            $this->error(__('parameter error'));
        }
        $turntable = (new \app\admin\model\activity\Turntable())->where(['code'=>$code,'starttime'=>['<=',time()],'endtime'=>['>=',time()]])->find();
        if(!$turntable){
            $this->error('Activity does not exist');
        }
        $times = (new Turntabletimes())->where(['a_id'=>$turntable['id'],'user_id'=>$userinfo['id']])->value('times');
        if(!$times||$times <= 0){
            $this->error('Insufficient number of draws available');
        }
        $prize = json_decode($turntable['prize_json'],true);
        $money = $this->random($prize);
        Db::startTrans();
        try {
            $rs = (new Turntabletimes())->where(['a_id'=>$turntable['id'],'user_id'=>$userinfo['id']])->setDec('times',1);
            if(!$rs){
                Db::rollback();
                $this->error(__('operation failure'));
            }
            (new Turntablelog())->insert([
                "a_id" => $turntable['id'],
                "user_id" => $userinfo['id'],
                "money" => $money,
                "createtime" => time(),
            ]);
            //金额变动
            $usermoneylog = (new Usermoneylog())->moneyrecords($userinfo['id'], $money, 'inc', 31, "转盘抽奖".$money);
            if(!$usermoneylog){
                Db::rollback();
                $this->error(__('operation failure'));
            }
            //提交
            Db::commit();
            $this->success(__('operate successfully'),['money'=>$money]);
        }catch(Exception $e){
            Log::mylog('抽奖失败', $e, 'turntable');
            Db::rollback();
            $this->error(__('operation failure'));
        }

    }

    function random($ps){
        static $arr = array();
        $key = md5(serialize($ps));

        if (!isset($arr[$key])) {
            $max = array_sum($ps);
            foreach ($ps as $k=>$v) {
                $v = $v / $max * 10000;
                for ($i=0; $i<$v; $i++) $arr[$key][] = $k;
            }
        }
        return $arr[$key][mt_rand(0,count($arr[$key])-1)];
    }

    public function list(){
        $this->verifyUser();
        $code = $this->request->post('code');
        if (!$code) {
            $this->error(__('parameter error'));
        }
        $turntable = (new \app\admin\model\activity\Turntable())->where(['code'=>$code])->find();
        if(!$turntable){
            $this->error('Activity does not exist');
        }
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $list = (new Turntablelog())
            ->where(['a_id' => $turntable['id'],'user_id'=>$this->uid])
            ->page($page, $pageSize)
            ->select();
        foreach ($list as &$value){
            $value['createtime'] = date('Y-m-d H:i:s',$value['createtime']);
        }
        $this->success(__('registered successfully'),$list);
    }
}
