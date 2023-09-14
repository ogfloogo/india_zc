<?php

namespace app\api\model;

use app\api\controller\controller;
use Exception;

use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Cache;
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Log;

//中奖
class Userwarehouse extends Model
{
    protected $name = 'user_warehouse';

    /**
     * 用户仓库列表
     *
     * @ApiMethod (POST)
     */
    public function list($post, $user_id)
    {
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount;
        $where['a.status'] = $post['type'];
        $where['a.deletetime'] = null;
        $list = $this
            ->alias('a')
            ->join('logistics_order b', 'a.id=b.wid', 'left')
            ->join('user_address c', 'b.address_id=c.id', 'left')
            ->where('a.user_id', $user_id)
            ->where($where)
            ->field('a.id,a.good_id,a.order_id,a.createtime,c.is_default,c.name,c.mobile,c.postcode,c.address,c.province,city,county,village,b.status')
            ->limit($startNum, $pageCount)
            ->select();
        if ($list) {
            foreach ($list as $key => $value) {
                $goodsinfo = (new Goods())->detail($value['good_id']);
                if ($goodsinfo) {
                    $list[$key]['good_name'] = $goodsinfo['name'];
                    $list[$key]['cover_image'] = format_image($goodsinfo['cover_image']);
                    $list[$key]['createtime'] = format_time($value['createtime']);
                    $list[$key]['price'] = ((new Order())->where('id', $value['order_id'])->field('amount')->find())['amount'];
                    //回购金额
                    $order_detail = (new Order())->orderdetail($value['order_id']);
                    $list[$key]['buyback'] = $order_detail['buyback'];
                }
            }
        }
        return $list;
    }

    /**
     * 可兑现金额
     */
    public function gettotal($user_id)
    {
        return $this
            ->where('status', 0)
            ->where('user_id', $user_id)
            ->sum('buyback');
    }

    /**
     * 中奖，入库
     */
    public function drawwinning($post, $userinfo, $id, $category_info)
    {
        $insert = [
            'user_id' => $userinfo['id'],
            'good_id' => $post['good_id'],
            'order_id' => $id,
            'buyback' => $category_info['buyback'],
            'price' => $category_info['price'],
            'createtime' => time(),
            'updatetime' => time()
        ];
        $this->insert($insert);
        Log::mylog('中奖入库', $insert, 'userwarehouse');
    }

    /**
     * 兑换现金
     */
    public function exchangemoney($wid, $user_id, $mylevel, $status)
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
            $usermoneylog = (new Usermoneylog())->moneyrecords($user_id, $status['buyback'], 'inc', 11, $wid);
            if (!$usermoneylog) {
                Db::rollback();
                return false;
            }
            //分佣
            $bouns = bcsub($status['buyback'], $status['price'], 2);
            //日报表
            $usercategory = new Usercategory();
            $usercategory->check($user_id);
            $usercategory->where('user_id', $user_id)->where('date', date('Y-m-d', time()))->setInc('exchangemoney', $bouns);
            //用户信息统计
            (new Usertotal())->where('user_id', $user_id)->setInc('exchangemoney', $bouns);
            $userinfo = (new User())->where('id', $user_id)->field('level,agent_id')->find();
            (new Commission())->commissionissued($user_id, $bouns, $status['order_id'], $mylevel, intval($userinfo['agent_id']));
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            Log::mylog('兑换现金', $e, 'exchangemoney');
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
    public function shippingdetails($wid)
    {
        $this_data = (new Logisticsorder())->where('wid', $wid)->find();
        $order_no = (new Order())->where('id', $this_data['order_id'])->field('good_id,order_id,createtime')->find();
        $goodsinfo = (new Goods())->detail($order_no['good_id']);
        $goodscategory = (new Goodscategory())->detail($goodsinfo['category_id']);
        $address = (new Useraddress())->where('id', $this_data['address_id'])->find();
        $list = [
            'id' => $this_data['id'],
            'goodsname' => $goodsinfo['name'],
            'cover_image' => format_image($goodsinfo['cover_image']),
            'price' => $goodscategory['price'],
            'status_list' => [0, 1, 2, 3],
            'address' => $address,
            'status' => $this_data['status'],
            'createtime' => format_time($this_data['createtime']),
            'order_id' => $order_no['order_id'],
            'ordertime' => format_time($order_no['createtime']),
        ];
        return $list;
    }
}
