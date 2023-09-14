<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\sys\CheckReport;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog;
use app\api\model\User as ModelUser;
use think\cache\driver\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;

class KickDeadUser extends Command
{
    protected $model = null;
    protected function configure()
    {
        $this->setName('KickDeadUser')
            ->setDescription('KickDeadUser');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {

        set_time_limit(0);
        try {
            $this->runKick();
        } catch (\Throwable $e) {
            Log::myLog('error:', $e, 'check');
        }
    }

    protected function runUser()
    {
        $userIds = (new UserRecharge())->where(['createtime' => ['GT', strtotime('-5 days')], 'status' => 1])->distinct(true)->column('user_id');
        echo (new UserRecharge())->getLastSql();
        (new User())->where(['id' => ['IN', $userIds]])->update(['isrecharge' => 1]);
        //    print_r($userIds);
    }

    protected function runKick()
    {
        $step = 1000;
        $max_user_id = (new User())->where(['id' => ['GT', 0]])->max('id');
        $num = ceil($max_user_id / $step);
        for ($i = 80; $i < $num; $i++) {
            $from = $step*$i+1;
            $end = $step*($i+1);
            $tokenList = (new User())->where(['id' => ['GT', 0],'isrecharge'=>0])->limit($from,$end)->column('token');
            foreach($tokenList as $token){
                // echo $token."\n";
                if ($token) {
                    (new ModelUser())->logout($token);
                }
            }
            echo (new User())->getLastSql();
            echo "\n";
        }
        echo $num;
    }
}
