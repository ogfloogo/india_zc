<?php

namespace app\api\model;

use function EasyWeChat\Kernel\Support\get_client_ip;

use app\api\controller\Shell;
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;

/**
 * 理财下单
 */
class Financeorder extends Model
{
    protected $name = 'finance_order';

    public function addorder($post, $userinfo, $price, $project_info, $copies)
    {
        // Log::mylog('方案', $project_info, 'project_info');
        $order_id = $this->createorder();
        while ($this->where(['order_id' => $order_id])->find()) {
            $order_id = $this->createorder();
        }
        $time = time();
        //开始收益时间
        $earning_start_time = $time;
        //结束收益时间-发放时间
        $earning_end_time = $earning_start_time + 60 * 60 * 24 * $project_info['day'];
        try {
            Db::startTrans();
            $insert = [
                'f_id' => $project_info['f_id'], //众筹项目ID
                'project_id' => $project_info['id'], //众筹方案ID
                'user_id' => $userinfo['id'], //用户ID
                'amount' => $price, //下单金额
                'buy_time' => $time, //购买时间
                'buy_rate' => $project_info['rate'], //收益率
                'order_id' => $order_id, //订单号
                'level' => $userinfo['level'], //用户等级
                'earning_start_time' => $earning_start_time, //开始收益时间
                'earning_end_time' => $earning_end_time, //结束收益时间
                'status' => 1,
                'type' => $project_info['type'], //类型:1=等额本息,2=先息后本
                'createtime' => $time,
                'updatetime' => $time,
                'copies' => $copies,
                'capital' => $project_info['capital'] * $copies,
                'interest' => $project_info['interest'] * $copies,
                'buy_level' => $project_info['buy_level'],
                'popularize' => $project_info['popularize'],
                'is_new_hand' => $project_info['is_new_hand'],
            ];

            $insert['num'] = $project_info['day'];
            $insert['surplus_num'] = $project_info['day'];
            $insert['collection_time'] = $earning_start_time + 60 * 60 * 24;

            $popularize = $project_info['popularize'];
            $total_profit = bcmul($project_info['interest']*$copies,$project_info['day'],2);//总利润
            $amount = $popularize==2?0:$price;
            $total_revenue = bcadd($total_profit,$amount,2);
            $insert['estimated_profit'] = $total_profit;
            $insert['estimated_income'] = $total_revenue;
            //创建理财订单
            $order_id = $this->insertGetid($insert);
            //非体验项目
            if($popularize != 2){
                //支付(体验项目不用支付)
                $paynow = (new Usermoneylog())->moneyrecords($userinfo['id'], $price, 'dec', 18, "众筹下单");
                if (!$paynow) {
                    Db::rollback();
                    Log::mylog('众筹支付失败', $order_id, 'financeorder');
                }
                if ($popularize == 1) {
                    //更新购买等级
                    $this->updatelevel($userinfo,$project_info['id']);
                    //购买初级合伙人及以上的项目  才算日奖励（已经改成每日充值奖励）
//                    if($project_info['buy_level'] >= 2){
//                        (new Usertask())->taskRewardType($userinfo['id'],3);
//                    }
                    //判断是否开放团队，不是的话自动开放团队(前提项目是推广项目)
                    if ($userinfo['level'] == 0) {
                        (new User())->where(['id' => $userinfo['id']])->update(['level' => 1]);
                        (new User())->refresh($userinfo['id']);
                        (new Usertask())->taskRewardType($userinfo['id'],3,1);
                    }
                    (new Popularizeaward())->createData($project_info,$userinfo['id']);
                    //判断上级是否获得推广激励
                    if($userinfo['sid']){
                        (new Popularizeaward())->updateData($project_info,$userinfo['sid'],$userinfo['id'],$order_id);
                    }
                }else{
                    //普通项目才有上级佣金,已改成每日发放
//                    $rs = $this->pidCommission($order_id,$userinfo['id'],$total_profit);
//                    if(!$rs){
//                        Db::rollback();
//                        Log::mylog('上级佣金插入失败', $order_id.'---'.$userinfo['id'].'---'.$project_info['id'], 'financeorder');
//                    }
                    $normal_order = (new Financeorder())->where(['id'=>['<>',$order_id],'user_id'=>$userinfo['id'],'popularize'=>0,'is_robot'=>0])->count();
                    if(!$normal_order){
                        (new Usertask())->taskRewardType($userinfo['id'],4);
                    }
                }
            }else{
                //标记用户购买过体验项目
                (new User())->where(['id' => $userinfo['id']])->update(['is_experience' => 1]);
                (new User())->refresh($userinfo['id']);
            }
            Db::commit();
            //push 理财发放
            $redis = new Redis();
            $redis->handler()->select(6);
            if($popularize == 0){//类型:0=普通项目,1=推广项目,2=体验项目
                $redis->handler()->zAdd("zclc:financelist", $insert['collection_time'], $order_id);
            }elseif($popularize == 1){
                $redis->handler()->zAdd("zclc:financelisttg", $insert['collection_time'], $order_id);
            }elseif($popularize == 2){
                $redis->handler()->zAdd("zclc:financelistty", $insert['collection_time'], $order_id);
            }
            //统计
            $this->statistics($project_info['f_id'], $price, $userinfo['id']);
            //插入上次下单时间
            $redis = new Redis();
            $redis->handler()->select(2);
            $redis->handler()->set("zclc:financeordertime:" . $userinfo['id'], $userinfo['id'], 5);
            $image = (new Finance())->where(['id'=>$project_info['f_id']])->value('image');
            $return = [
                'name' => $project_info['name'],
                'image' => format_image($image),
                'amount' => $price,
                'type' => $project_info['type'],
                'starttime' => date('Y-m-d H:i:s', $earning_start_time),
                'endtime' => date('Y-m-d H:i:s', $earning_end_time),
                'paytime' => date('Y-m-d H:i:s', $insert['buy_time']),
                'order_id' => $insert['order_id'],
                'day' => $project_info['day'],
                'rate' => $project_info['rate'],
                'f_id' => $project_info['f_id'],
                'buy_level' => $project_info['buy_level'],
                'per_invite' => 2,
                'invite_money' => $project_info['fixed_amount'],
                'popularize' => $popularize
            ];
            $return['total_profit'] = $total_profit;
            $return['total_revenue'] = $total_revenue;
            if($project_info['type'] == 2){
                $return['daily_income'] = bcmul($project_info['interest'],$copies,2);
            }else{
                $return['daily_income'] = bcadd($project_info['capital']*$copies,$project_info['interest']*$copies,2);
            }
            $level = (new Teamlevel())->detail($project_info['buy_level']);
            $return['buy_level_name'] = $level['name']??'';
            $return['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
            return $return;
        } catch (Exception $e) {
            Log::mylog('理财下单失败', $e, 'financeorder');
            //刷新用户信息
            (new User())->refresh($userinfo['id']);
            return false;
        }
    }

    public function pidCommission($order_id,$from_id,$commissions){
        $commission = new Commission();
        $usermoneylog = new Usermoneylog();
        $usertotal = new Usertotal();
        $first = (new Userteam())->where(['team'=>$from_id,'level'=>1])->value('user_id');
        if($first){
            $rate1 = config('site.first_team');
            $commission->setTableName($first);
            $insert = [
                'to_id' => $first,
                'from_id' => $from_id,
                'level' => 1,
                'order_id' => $order_id,
                'commission' => bcmul($commissions,$rate1/100,2),
                'commission_fee' => $rate1,
                'createtime' => time(),
                'updatetime' => time(),
            ];
            //余额变动
            if($insert['commission'] > 0){
                $commission->insert($insert);
                $isok = $usermoneylog->moneyrecords($first, $insert['commission'], 'inc', 4, "来源用户ID:" . $from_id.'订单ID'.$order_id);
                if(!$isok){
                    return false;
                }
            }
            $usertotal->where('user_id', $first)->setInc('first_commission', $insert['commission']);
            $second = (new Userteam())->where(['team'=>$from_id,'level'=>2])->value('user_id');
            if($second){
                $rate2 = config('site.second_team');
                $commission->setTableName($second);
                $insert = [
                    'to_id' => $second,
                    'from_id' => $from_id,
                    'level' => 2,
                    'order_id' => $order_id,
                    'commission' => bcmul($commissions,$rate2/100,2),
                    'commission_fee' => $rate2,
                    'createtime' => time(),
                    'updatetime' => time(),
                ];
                //余额变动
                if($insert['commission'] > 0) {
                    $commission->insert($insert);
                    $isok = $usermoneylog->moneyrecords($second, $insert['commission'], 'inc', 4, "来源ID:" . $from_id.'项目ID'.$order_id);
                    if(!$isok){
                        return false;
                    }
                }
                $usertotal->where('user_id', $second)->setInc('second_commission', $insert['commission']);
            }
        }
        return true;
    }

    public function updatelevel($userinfo,$finance_id){
        $finance_info = (new Financeproject())->detail($finance_id);
        if($finance_info['buy_level'] > $userinfo['buy_level']){
            (new User())->where('id',$userinfo['id'])->update(['buy_level'=>$finance_info['buy_level']]);
            (new User())->refresh($userinfo['id']);
        }
    }

    /**
     * 获取当前收益率
     */
    public function getrate($finance_id, $issue_id)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $order_number = $redis->handler()->zScore("zclc:financeordernum", $issue_id);
        if (!$order_number) {
            $order_number = 0;
        }
        $redis->handler()->select(0);
        $rate = $redis->handler()->ZRANGEBYSCORE('new:finance_rate:set:' . $finance_id, $order_number, '+inf', ['limit' => [0, 1]]);
        $rate_info = $redis->handler()->Hgetall("new:finance_rate:" . $rate[0]);
        return $rate_info;
    }

    /**
     * 获取当前购买人数
     */
    public function getbuyers($issue_id)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $buyers = $redis->handler()->zScore("zclc:financeordernum", $issue_id);
        if (!$buyers) {
            $buyers = 0;
        }
        return $buyers;
    }

    /**
     * 统计下单人数，下单金额
     */
    public function statistics($finance_id, $amount, $user_id)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $is_exist = $redis->handler()->zScore("zclc:financeordernum", $finance_id);
        //存在就累加，不存在就新增
        if ($is_exist) {
            $is_buy = (new Financeorder())->where(['user_id'=>$user_id,'is_robot'=>0,'f_id'=>$finance_id])->find();
            if (!empty($is_buy)) {
                //更新分数
                $redis->handler()->zIncrBy("zclc:financeordernum", 1, $finance_id);
            }
        } else {
            $redis->handler()->zAdd("zclc:financeordernum", 1, $finance_id);
        }
        //下单金额
        $is_exist_amount = $redis->handler()->zScore("zclc:financeordermoney", $finance_id);
        if ($is_exist_amount) {
            //更新分数
            $redis->handler()->zIncrBy("zclc:financeordermoney", $amount, $finance_id);
        } else {
            $redis->handler()->zAdd("zclc:financeordermoney", $amount, $finance_id);
        }
    }

    /**
     * 生成唯一订单号
     */
    public function createorder()
    {
        $msec = substr(microtime(), 2, 2);        //	毫秒
        $subtle = substr(uniqid('', true), -8);    //	微妙
        return date('YmdHis') . $msec . $subtle;  // 当前日期 + 当前时间 + 当前时间毫秒 + 当前时间微妙
    }

    /**
     * 订单详情
     */
    public function orderdetail($order_id)
    {
        return $this->where('id', $order_id)->find();
    }

    /**
     * 我的理财列表
     * @ApiMethod (POST)
     * @param string $type 1=今日团购,2=历史团购
     * @param string $page 当前页
     */
    public function orderlist($post, $user_id)
    {
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount;
        $list = $this
            ->where('user_id', $user_id)
            ->where('state', $post['type'])
            ->where('deletetime', null)
            ->where('is_robot', 0)
            ->order('state desc')
            ->order('createtime desc')
            ->field('order_id,finance_id,issue_id,issue_id as id,state,amount,earning_end_time,buy_rate_end')
            // ->group('issue_id')
            ->limit($startNum, $pageCount)
            ->select();
        foreach ($list as $key => $value) {
            $issue_info = (new Financeissue())->where('id', $value['issue_id'])->find();
            $list[$key]['name'] = $issue_info['name'];
            $list[$key]['presell_start_time'] = $issue_info['presell_start_time'];
            $list[$key]['presell_end_time'] = $issue_info['presell_end_time'];
            $list[$key]['start_time'] = $issue_info['start_time'];
            $list[$key]['end_time'] = $issue_info['end_time'];
            $list[$key]['end_days'] = $issue_info['end_time'] + 60 * 60 * 24;
            $list[$key]['status'] = $issue_info['status'];
            $list[$key]['day'] = $issue_info['day'];
            $finance_info = (new Finance())->detail($value['finance_id']);
            $list[$key]['finance_name'] = $finance_info['name'];
            $list[$key]['price'] = $finance_info['price'];
            //购买人数-收益率
            $rate = (new Financerate())->detail($value['finance_id']);
            $list[$key]['ratelist'] = $rate;
            //当前收益率
            $now_rate = ($this->getrate($value['finance_id'], $value['issue_id']))['rate'];
            $list[$key]['rate'] = $now_rate;
            //预计收益
            $list[$key]['anticipated_income'] = bcmul($now_rate / 100, $value['amount'], 2);
            //购买人数
            $list[$key]['buyers'] = $this->getbuyers($value['issue_id']);
            if ($value['state'] == 0 && $value['presell_end_time'] < time()) {
                $this->where('order_id', $value['order_id'])->update(['state' => 1]);
            }
        }
        return $list;
    }

    /**
     * 获取当前期号最后收益率
     */
    public function getissuerate($order_info)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $now_rate = $redis->handler()->zScore("zclc:issuerate", $order_info['issue_id']);
        if (!$now_rate) {
            $now_rate = $this->getrate($order_info['finance_id'], $order_info['issue_id']);
            $redis->handler()->zAdd("zclc:issuerate", $now_rate['rate'], $order_info['issue_id']);
        }
        return $now_rate;
    }

    /**
     * 理财机器人下单
     */
    public function financeRobotBuy($data)
    {
        //生成唯一订单号
        $order_id = $this->createorder();
        while ($this->where(['order_id' => $order_id])->find()) {
            $order_id = $this->createorder();
        }
        //理财方案详情
        $finance_info = (new Financeproject())->detail($data['project_id']);
        if($finance_info['popularize'] == 2){
            $count = (new \app\api\model\Financeorder())->where(['user_id'=>$data['robot_user_id'],'project_id'=>$data['project_id'],'is_robot'=>1])->count();
            if($count){
                return false;
            }
        }
        //是否下架
        if ($finance_info['status'] == 0) {
            //删除robot
            $redis = new Redis();
            $redis->handler()->select(6);
            $redis->handler()->hDel("zclc:financeproject:robot", $data['project_id']);
            return false;
        }
        $price = $data['amount'];
        $insert = [
            'project_id' => $data['project_id'], //众筹项目ID
            'user_id' => $data['robot_user_id'], //用户ID
            'amount' => $price, //下单金额
            'order_id' => $order_id, //订单号
            'level' => 0,
            'f_id' => $finance_info['f_id'],
            'is_robot' => 1,
            'popularize' => $finance_info['popularize'],
            'buy_time' => time(), //购买时间
            'createtime' => time(),
            'updatetime' => time(),
        ];
        //创建理财订单
        $order_id = $this->insertGetid($insert);
        Db::commit();
        //更新机器人最后下单时间
        db('user_robot')->where('id', $data['robot_user_id'])->update(['buytime' => time()]);
        //统计
        $this->robotstatistics($finance_info['f_id'], $price, $data['robot_user_id']);
        $rand_time = time() + rand($finance_info['robot_addorder_time_start'], $finance_info['robot_addorder_time_end']);
        if ($finance_info['fixed_amount'] == 0) {
            $rand_price = rand($finance_info['user_min_buy'], $finance_info['user_max_buy']);
        } else {
            $rand_price = $finance_info['fixed_amount'];
        }
        $redis = new Redis();
        $redis->handler()->select(6);
        $redis->handler()->hset("zclc:financeproject:robot", $data['project_id'], $rand_time . "-" . $rand_price);
    }

    /**
     * 开启机器人
     * finance_id 理财活动ID
     * robot_addorder_time_start 下单时间间隔1
     * robot_addorder_time_end 下单时间间隔2
     * robot_addorder_num_start 每次下单数量1
     * robot_addorder_num_end 每次下单数量2
     * fixed_amount 固定金额
     */
    public function openrobot($finance_id, $robot_addorder_time_start, $robot_addorder_time_end, $user_min_buy, $user_max_buy, $fixed_amount)
    {
        $rand_time = time() + rand($robot_addorder_time_start, $robot_addorder_time_end);
        if ($fixed_amount == 0) {
            $rand_price = rand($user_min_buy, $user_max_buy);
        } else {
            $rand_price = $fixed_amount;
        }
        $redis = new Redis();
        $redis->handler()->select(6);
        $open = $redis->handler()->hset("zclc:financeproject:robot", $finance_id, $rand_time . "-" . $rand_price);
        if (!$open) {
            return false;
        }
        return true;
    }

    /**
     * 关闭机器人
     * finance_id 理财活动ID
     */
    public function closerobot($finance_id)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $close = $redis->handler()->hDel("zclc:financeproject:robot", $finance_id);
        if (!$close) {
            return false;
        }
        return true;
    }

    /**
     * 统计下单人数，下单金额
     */
    public function robotstatistics($f_id, $amount, $user_id)
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        //该活动未下过单
        $is_exist = $redis->handler()->zScore("zclc:financeordernum", $f_id);
        if ($is_exist) {
            $is_buy = (new Financeorder())->where(['user_id'=>$user_id,'is_robot'=>1,'f_id'=>$f_id])->find();
            if (!empty($is_buy)) {
                //更新分数
                $redis->handler()->zIncrBy("zclc:financeordernum", 1, $f_id);
            }
        } else {
            $redis->handler()->zAdd("zclc:financeordernum", 1, $f_id);
        }
        //下单金额
        $is_exist_amount = $redis->handler()->zScore("zclc:financeordermoney", $f_id);
        if ($is_exist_amount) {
            //更新分数
            $redis->handler()->zIncrBy("zclc:financeordermoney", $amount, $f_id);
        } else {
            $redis->handler()->zAdd("zclc:financeordermoney", $amount, $f_id);
        }
    }
}
