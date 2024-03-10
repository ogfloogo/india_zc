<?php

namespace app\api\command;

use app\api\model\Finance;
use app\api\model\Usermoneylog;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;

class Refundproject extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Refundproject')
            ->setDescription('订单退回');
    }

    protected function execute(Input $input, Output $output)
    {
        $list = db('finance_order')->where(['status' => 1, 'is_robot' => 0])->select();
        foreach ($list as $value) {
            Db::startTrans();
            try {
                //体验直接改成已完成
                if($value['popularize'] == 2){
                    $ty = db('finance_order')->where(['id' => $value['id']])->update(['status'=>2,'surplus_num'=>0]);
                    if(!$ty){
                        Log::mylog('体验订单状态修改失败，订单id：'.$value['id'], '', 'Refundproject');
                    }
                    Db::commit();
                    continue;
                }
                if($value['type'] == 1){
                    //等额本息
                    //返还本金 = 每日返还的本金 * 剩余期数
                    $return_money = $value['capital'] * $value['surplus_num'];
                    //返还利息 = 每日返还的利息 * 剩余期数
                    $return_commission = $value['interest'] * $value['surplus_num'];
                }elseif($value['type'] == 2){
                    //先息后本
                    //返还本金 = 本金
                    $return_money = $value['capital'];
                    //返还利息 = 每日返还的利息 * 剩余期数
                    $return_commission = $value['interest'] * $value['surplus_num'];
                }else{
                    Db::commit();
                    continue;
                }
                $money_result = (new Usermoneylog())->moneyrecords($value['user_id'], $return_money, 'inc', 19,"本金返还{$value['id']}");
                if(!$money_result){
                    Log::mylog('订单本金返还失败，订单id：'.$value['id'], $return_money, 'Refundproject');
                    Db::commit();
                    continue;
                }
                $money_result2 = (new Usermoneylog())->moneyrecords($value['user_id'], $return_commission, 'inc', 23,"理财收益{$value['id']}");
                if(!$money_result2){
                    Log::mylog('订单利息返还失败，订单id：'.$value['id'], $return_commission, 'Refundproject');
                    Db::commit();
                    continue;
                }
                //订单改为已完成 剩余期数改为0
                $earnings = $value['earnings'] + $return_money + $return_commission;
                $rs = db('finance_order')->where(['id' => $value['id']])->update(['status'=>2,'surplus_num'=>0,'earnings'=> $earnings]);
                if(!$rs){
                    Log::mylog('订单状态修改失败，订单id：'.$value['id'], '', 'Refundproject');
                    Db::commit();
                    continue;
                }
                Db::commit();
                echo "------------";
                echo "执行成功" . "\n";         
            } catch (Exception $e) {
                Db::rollback();
                Log::mylog('订单退款失败', $e, 'Refundproject');
            }
        }
    }
}