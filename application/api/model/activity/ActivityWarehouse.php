<?php

namespace app\api\model\activity;

use app\api\model\activity\BaseModel;
use app\api\model\Useraddress;
use app\api\model\Usermoneylog;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;


class ActivityWarehouse extends BaseModel
{
    public function add($user_draw_id, $user_id, $prize_info)
    {
        $data['user_draw_id'] = $user_draw_id;
        $data['user_id'] = $user_id;

        $data['prize_id'] = $prize_info['id'];
        $data['prize_name'] = $prize_info['name'];
        $data['prize_image'] = $prize_info['image'];
        $data['prize_content'] = $prize_info['content'];
        $data['prize_price'] = $prize_info['price'];

        $data['buyback'] = $prize_info['price'];
        $data['status'] = 0;
        $data['createtime'] = time();
        $data['updatetime'] = time();

        return $this->insertGetId($data);
    }

    /**
     * 兑换现金
     */
    public function exchangemoney($wid, $user_id, $mylevel, $warehouse_info)
    {
        Db::startTrans();
        try {
            //更新仓库状态
            $res = $this->where('id', $wid)->where('status', 0)->update(['status' => 1, 'updatetime' => time()]);
            if (!$res) {
                Db::rollback();
                return false;
            }
            //余额操作
            $usermoneylog = (new Usermoneylog())->moneyrecords($user_id, $warehouse_info['buyback'], 'inc', 17, $wid);
            if (!$usermoneylog) {
                Db::rollback();
                return false;
            }
            // //分佣
            // $bouns = bcsub($status['buyback'], $status['price'], 2);
            // //日报表
            // $usercategory = new Usercategory();
            // $usercategory->check($user_id);
            // $usercategory->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('exchangemoney', $bouns);
            // //用户信息统计
            // (new Usertotal())->where('user_id', $user_id)->setInc('exchangemoney', $bouns);
            // $userinfo = (new User())->where('id', $user_id)->field('level,agent_id')->find();
            // (new Commission())->commissionissued($user_id, $bouns, $status['order_id'], $mylevel, intval($userinfo['agent_id']));
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            // Log::mylog('兑换现金', $e, 'exchangemoney');
            return false;
        }
    }

    /**
     * 兑换全部现金 All
     */
    public function exchangemoneyall($userinfo)
    {
        $list = $this->where('user_id', $userinfo['id'])->where('status', 0)->select();
        foreach ($list as $key => $value) {
            $this->exchangemoney($value['id'], $userinfo['id'], $userinfo['level'], $value);
        }
        return true;
    }

    /**
     * 配送详情 状态:0=确认地址(待收货),1=配送商品(已发货),2=商品已送达(待签收),3=已确认收货
     *
     * @ApiMethod (POST)
     */
    public function shippingdetails($warehouse_info, $order_info)
    {
        $address_info = (new Useraddress())->where('id', $order_info['address_id'])->find();

        $model = (new UserDrawLog());
        $model->setTableName($order_info['user_id']);
        $draw_log_info = $model->where(['id' => $order_info['user_draw_id']])->find();

        $list = [
            'id' => $order_info['id'],
            'name' => $draw_log_info['prize_name'],
            'image' => format_image($draw_log_info['prize_image']),
            'price' => $draw_log_info['prize_price'],
            'status_list' => [0, 1, 2, 3],
            'address' => $address_info,
            'status' => $order_info['status'],
            'createtime' => format_time($order_info['createtime']),
            'ordertime' => format_time($order_info['createtime']),
        ];
        return $list;
    }

    public function list($id)
    {
        $common_set_key = $this->set_prefix . '0';
        $level_set_key = $this->set_prefix . $id;
        $set_num = $this->redisInstance->zunionstore($this->union_set_prefix . $id, [$level_set_key, $common_set_key]);
        $list = [];
        if ($set_num) {
            $level_list = $this->redisInstance->zRevRange($this->union_set_prefix . $id, 0, -1, true);
            foreach ($level_list as $a_id => $seq) {
                $item = $this->formatInfo($a_id);
                $list[] = $item;
            }
        }
        return $list;
    }


    public function info($id)
    {
        $info_key = $this->info_prefix . $id;
        $detail_info = $this->redisInstance->hGetAll($info_key);
        return $detail_info;
    }

    public function formatInfo($id)
    {
        $detail_info = $this->info($id);
        $info = [];
        if ($detail_info) {
            $info['id'] = $detail_info['id'];
            $info['name'] = $detail_info['name'];
            $info['level'] = $detail_info['level'];
            $info['image'] = format_image($detail_info['image'], true);
            $info['target_num'] = $detail_info['num'];
            // $banner_images = explode(',', $detail['banner_images']);
            // $array = [];
            // foreach ($banner_images as $key => $value) {
            //     $array[] = format_image($value);
            // }
            // $detail['banner_images'] = implode(',', $array);
        }
        return $info;
    }
}
