<?php

namespace app\api\command;

use app\api\model\Finance;
use app\api\model\Usermoneylog;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;

class Refundmoney extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Refundmoney')
            ->setDescription('退款');
    }

    protected function execute(Input $input, Output $output)
    {
        $list = db('user_cash')->where(['status' => 5, 'updatetime' => 1694317740])->select();
        foreach ($list as $value) {
            Db::startTrans();
            try {
                $money_result = (new Usermoneylog())->moneyrecords($value['user_id'], $value['price'], 'inc', 6);
                if(!$money_result){
                    Log::mylog($value['user_id'].'-退款失败', $value['price'], 'refundmoney');
                    Db::commit();
                    continue;
                }   
                Db::commit();  
                Log::mylog($value['user_id'].'-退款成功', $value['price'], 'refundmoney');  
                echo "------------";
                echo "执行成功" . "\n";         
            } catch (Exception $e) {
                Db::rollback();
                Log::mylog('退款失败', $e, 'refundmoney');
            }
        }
    }
}