<?php

namespace app\api\controller;

use app\api\model\Useraward as ModelUseraward;
use app\common\model\Redpacket as ModelRedpacket;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Db;
use app\api\model\Usermoneylog;
use think\Exception;
use think\Log;

/**
 * 翻牌
 */
class Flop extends Controller
{
    /**
     *初始化
     *
     */
    public function index()
    {
        $this->verifyUser();
        $user_id = $this->uid;
        $this->initial($user_id);
        $times_info = db('flop_times')->where(['user_id' => $user_id, 'nowday' => date('Y-m-d', time())])->field('times,not_use_times')->find();
        //翻牌记录
        $card_info = db('flop')->field('id,name')->select();
        foreach ($card_info as &$value) {
            $is_exit = db('flop_log')->where(['fid' => $value['id'], 'user_id' => $user_id, 'days' => date('Y-m-d', time())])->find();
            if (!empty($is_exit)) {
                $value['status'] = 1;
                $value['price'] = $is_exit['price'];
            } else {
                $value['status'] = 0;
                $value['price'] = null;
            }
        }
        $return = [
            'times' => $times_info['times']-$times_info['not_use_times'],
            'total' => $times_info['times'],
            'card_info' => $card_info
        ];
        $this->success(__('Receive success'), $return);
    }

    /**
     * 翻牌
     */
    public function drawlottery()
    {
        $fid = $this->request->post('fid');
        if (!$fid) {
            $this->error(__('parameter error'));
        }
        //牌是否存在
        $card_exit = db('flop')->where(['id' => $fid])->field('id')->find();
        if (empty($card_exit)) {
            $this->error(__('parameter error'));
        }
        $this->verifyUser();
        $user_id = $this->uid;
        //翻牌次数
        $times = db('flop_times')->where(['user_id' => $user_id, 'nowday' => date('Y-m-d', time())])->value('not_use_times');
        if ($times == 0) {
            $this->error(__('No more times'));
        }
        //是否已经翻过牌了
        $is_exit = db('flop_log')->where(['fid' => $fid, 'user_id' => $user_id, 'days' => date('Y-m-d', time())])->field('id')->find();
        if (!empty($is_exit)) {
            $this->error(__('This card has been turned over'));
        }
        Db::startTrans();
        try {
            $rs = db('flop_times')->where(['user_id' => $user_id, 'nowday' => date('Y-m-d', time())])->setDec('not_use_times', 1);
            if (!$rs) {
                Db::rollback();
                $this->error(__('operation failure'));
            }
            //获取翻牌金额
            $price = $this->getrandprice($fid);
            db('flop_log')->insert([
                "user_id" => $user_id,
                "fid" => $fid,
                "price" => $price,
                "days" => date('Y-m-d', time()),
                "createtime" => time(),
                "updatetime" => time()
            ]);
            //金额变动
            $usermoneylog = (new Usermoneylog())->moneyrecords($user_id, $price, 'inc', 32, "翻牌奖励" . $price);
            if (!$usermoneylog) {
                Db::rollback();
                $this->error(__('operation failure'));
            }
            //提交
            Db::commit();
            $this->success(__('operate successfully'), ['money' => $price]);
        } catch (Exception $e) {
            Log::mylog('翻牌失败', $e, 'flop');
            Db::rollback();
            $this->error(__('operation failure'));
        }
    }

    /**
     * 中奖纪录
     */
    public function recordlog()
    {
        $list = db('flop_log')->field('id,user_id,price,createtime')->order('id desc')->limit(100)->select();
        foreach ($list as &$value) {
            $userinfo = db('user')->where(['id' => $value['user_id']])->field("nickname,avatar")->find();
            $value['nickname'] = $userinfo['nickname'];
            $value['avatar'] = format_image($userinfo['avatar']);
        }
        $this->success(__('Request successful'), $list);
    }

    /**
     * 我的中奖纪录
     */
    public function myrecordlog()
    {
        $user_id = 1;
        $list = db('flop_log')->where(['user_id'=>$user_id])->field('id,user_id,price,createtime')->order('id desc')->select();
        foreach ($list as &$value) {
            $userinfo = db('user')->where(['id' => $value['user_id']])->field("nickname,avatar")->find();
            $value['nickname'] = $userinfo['nickname'];
            $value['avatar'] = format_image($userinfo['avatar']);
        }
        $this->success(__('Request successful'), $list);
    }

    /**
     * 初始化
     */
    public function initial($user_id)
    {
        $res = db('flop_times')->where(['user_id' => $user_id, 'nowday' => date("Y-m-d", time())])->find();
        if (empty($res)) {
            $log_number = db('flop_log')->where(['user_id' => $user_id])->group('days')->count();
            $days = $log_number + 1;
            $times = db('flop_days')->where(['days' => $days])->value('times');
            if (!$times) {
                $times = 0;
            }
            $ist = [
                'user_id' => $user_id,
                'times' => $times,
                'not_use_times' => $times,
                'nowday' => date('Y-m-d', time()),
                'days' => $days,
                'createtime' => time(),
                'updatetime' => time()
            ];
            db('flop_times')->insert($ist);
        }
    }

    /**
     * 获取翻牌金额
     * @param int $fid 翻牌ID
     * @return int
     */
    function getrandprice($fid)
    {
        $card_info = db('flop')->where(['id' => $fid])->value('rand_price');
        $list = explode('-', $card_info);
        $price = mt_rand($list[0], $list[1]);
        return $price;
    }
}
