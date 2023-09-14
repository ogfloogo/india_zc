<?php

namespace app\api\command;

use app\admin\model\groupbuy\GoodsCategory;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;
use app\api\model\Commission;
use app\api\model\Goods;
use app\api\model\Goodscategory as ModelGoodscategory;
use app\api\model\Order;
use app\api\model\Rtmoney as ModelRtmoney;
use app\api\model\Usercategory;
use app\api\model\Usermerchandise;
use app\api\model\Usermoneylog;
use app\api\model\Usertotal;
use think\Db;
use think\Log;

class Rtmoney extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Rtmoney')
            ->setDescription('下单返还');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $output->writeln('Rtmoney');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9508);
        //守护进程模式
        $ws->set([
            'daemonize' => true,
            'worker_num' => 1,
            //'task_worker_num' => 4,
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
            $usermoneylog = new ModelRtmoney();
            $order = new Order();
            $category_info = new ModelGoodscategory();
            $goodinfo = new Goods();
            $usermerchandise = new Usermerchandise();
            $usercategory = new Usercategory();
            \Swoole\Timer::tick(3000, function () use ($order, $category_info, $goodinfo, $usermoneylog, $usermerchandise, $usercategory) {
                $list = $order->where('id', 'gt', 1169998)->where('id','lt',3018014)->where('earnings', 0)->select();
                Log::mylog('列表', $list, 'Rtmoney');
                foreach ($list as $key => $value) {
                    // Db::startTrans();
                    try {
                        $goods = $goodinfo->detail($value['good_id']);
                        $categoryinfo = $category_info->detail($goods['category_id']);
                        if ($value['type'] == 1) { //我要开团
                            $tz = $usermoneylog->opengrouprewards($value['user_id'], $categoryinfo, $value['id']);
                            if (!$tz) {
                                continue;
                                // Db::rollback();
                            }
                        } else { //一键开团
                            $tg = $usermoneylog->akeytoopen($value['user_id'], $categoryinfo, $value['id']);
                            if (!$tg) {
                                continue;
                                // Db::rollback();
                            }
                        }
                        //未中奖，新增用户该商品下单次数
                        $usermerchandise->where('user_id', $value['user_id'])->where('category_id', $goods['category_id'])->setInc('num', 1);
                        //未中奖，新增用户当天下单次数
                        $usercategory->where('user_id', $value['user_id'])->where('date', date('Y-m-d', time()))->setInc('num', 1);
                        //更新订单
                        (new Order())->where('id', $value['id'])->update(["earnings" => $categoryinfo['reward']]);

                        // Db::commit();
                    } catch (Exception $e) {
                        // Db::rollback();
                        Log::mylog('下单返还', $e, 'Rtmoney');
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
}
