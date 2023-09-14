<?php

namespace app\api\command;

use app\api\model\Financeorder;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\api\model\User;
use app\api\model\Usermoneylog;
use think\Db;
use think\Log;
use think\Exception;


class Givemoney extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Givemoney')
            ->setDescription('理财到期-普通项目');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $output->writeln('Givemoney');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9512);
        //守护进程模式
        $ws->set([
            'daemonize' => true,
            'worker_num' => 1,
            // 'task_worker_num' => 4,
        ]);
        //监听WebSocket连接打开事件
        $ws->on('Open', function ($ws, $request) {
            $ws->push($request->fd, "hello, welcome\n");
        });

        //监听WebSocket消息事件
        $ws->on('Message', function ($ws, $frame) {
            echo "Message: {$frame->data}\n";
            $ws->push($frame->fd, "server: {$frame->data}");
        });
        $ws->on('WorkerStart', function ($ws, $worker_id) {
            echo "workerId:{$worker_id}\n";
            $redis = new Redis();
            $user = new User();
            $usermoneylog = new Usermoneylog();
            \Swoole\Timer::tick(1500, function () use ($redis, $user, $usermoneylog, $worker_id) {
                $redis->handler()->select(6);
                $list = $redis->handler()->ZRANGEBYSCORE('zclc:financelist', '-inf', time(), ['withscores' => true]);
                foreach ($list as $key => $value) {
                    $lock = $redis->handler()->setnx("zclc:financelock" . $key, $key);
                    if (!$lock) {
                        continue;
                    }
                    $redis->handler()->expireAt("zclc:financelock" . $key, time() + 10);
                    //理财订单是否存在
                    $order_id = $key;
                    $order_info = (new Financeorder())->where(['id'=>$order_id])->find();
                    if(!$order_info){
                        Log::mylog('理财订单不存在', $list, 'Givemoney');
                        $redis->handler()->del("zclc:financelock" . $key);
                        $redis->handler()->zRem('zclc:financelist', $key);
                        continue;
                    }
                    $user_id = $order_info['user_id'];
                    $user_info = db("user")->where(['id'=>$user_id])->field('id')->find();
                    if(!$user_info){
                        $redis->handler()->zRem('zclc:financelist', $key);
                        $redis->handler()->del("zclc:financelock" . $key);
                        continue;
                    }
                    //理财订单是否已完成
                    if($order_info['status'] == 2){
                        Log::mylog('订单已发放', $list, 'Givemoney');
                        $redis->handler()->del("zclc:financelock" . $key);
                        $redis->handler()->zRem('zclc:financelist', $key);
                        continue;
                    }
                    try {
                        //余额变动
                        Db::startTrans();
                        //收益
                        $interest = $order_info['interest'];//利息
                        $earnings = $order_info['interest'];
                        if($order_info['type']==1&&$order_info['capital']!=0){
                            //等额本息、返还本金不为0，已收益等于每日的利息+本金
                            $earnings = $order_info['interest'] + $order_info['capital'];
                        }
                        $surplus_num = $order_info->surplus_num;
                        $order_info->surplus_num = $order_info->surplus_num-1;
                        if($surplus_num-1 == 0){
                            //最后一期
                            $order_info->earnings = $order_info->earnings + $order_info['interest'] + $order_info['capital'];
                            $order_info->status = 2;
                        }else{
                            //非最后一期
                            //下一期收益时间
                            $order_info->earnings = $order_info->earnings + $earnings;
                            $collection_time = $order_info->collection_time+60*60*24;
                            $order_info->collection_time = $collection_time;
                            $redis->handler()->zRem('zclc:financelist', $key);
                            $redis->handler()->del("zclc:financelock" . $key);
                            //加入下一期的结算时间
                            $redis->handler()->zAdd("zclc:financelist", $collection_time, $order_id);
                            $order_info->save();
                            $return_money = $order_info['capital'];
                            //每期返还本金(等额本息)
                            if($order_info['type'] == 1){
                                if($return_money != 0){
                                    $isok = $usermoneylog->moneyrecords($user_id, $return_money , 'inc', 19, "本金返还{$order_id}");
                                    if ($isok == false) {
                                        Db::rollback();
                                        $redis->handler()->del("zclc:financelock" . $key);
                                        continue;
                                    }
                                }
                            }
                            Db::commit();
                            //等额本息每期返还利息
                            $commission = $user_id.'-'.$order_id.'-'.$interest.'-'.$order_info['level'];
                            $redis->handler()->rpush("commissionlist", $commission);
                            continue;
                        }

                        //最后一期返还本金
                        $return_money = $order_info['capital'];
                        $isok = true;
                        if($return_money != 0){
                            $isok = $usermoneylog->moneyrecords($user_id, $return_money , 'inc', 19, "本金返还{$order_id}");
                        }
                        if ($isok == false) {
                            Db::rollback();
                            $redis->handler()->del("zclc:financelock" . $key);
                            continue;
                        } else {
                            $order_info->save();
                            if($order_info['popularize'] == 1){
                                //只有推广项目的订单结束才需要更新购买等级
                                $this->updatelevel_expire($user_id,$order_id);
                            }
                            Db::commit();
                            //等额本息、先息后本最后一期返还利息
                            $commission = $user_id.'-'.$order_id.'-'.$interest.'-'.$order_info['level'];
                            $redis->handler()->rpush("commissionlist", $commission);
                            $redis->handler()->zRem('zclc:financelist', $key);
                            $redis->handler()->del("zclc:financelock" . $key);
                        }
                        //已读未读
                        db("user")->where(['id' => $user_id])->update(['earnings_read' => 0]);
                    } catch (Exception $e) {
                        Db::rollback();
                        Log::mylog('理财到期', $e, 'Givemoney');
                    }
                }
            }, $ws);
        });
        //监听WebSocket连接关闭事件
        $ws->on('Close', function ($ws, $fd) {
            echo "client-{$fd} is closed\n";
        });
        $ws->start();
    }

    /**
     * 理财发放更新称号等级
     */
    public function updatelevel_expire($user_id,$order_id){
        $order_info = (new Financeorder())->where(['id'=>$order_id])->find();
        $userinfo = (new User())->where('id',$user_id)->field('id,buy_level')->find();
        if($order_info['buy_level'] == $userinfo['buy_level']){
            //查找更低等级的在持理财
            $allorder = (new Financeorder())->where(['user_id'=>$user_id,'status'=>1,'popularize'=>1])->order('buy_level desc')->field('buy_level')->limit(1)->find();
            if($allorder){
                (new User())->where('id',$userinfo['id'])->update(['buy_level'=>$allorder['buy_level']]);
            }else{
                (new User())->where('id',$userinfo['id'])->update(['buy_level'=>0]);
            }
            (new User())->refresh($userinfo['id']);
        }
    }
}
