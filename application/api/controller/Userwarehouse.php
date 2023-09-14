<?php

namespace app\api\controller;

use app\api\model\Faq as ModelFaq;
use app\api\model\Logisticsorder;
use app\api\model\Useraddress;
use app\api\model\Userwarehouse as ModelUserwarehouse;
use think\Config;

/**
 * 用户仓库
 */
class Userwarehouse extends Controller
{

   /**
     * 仓库列表
     *
     * @ApiMethod (POST)
     * @param string $type 类型 1可兑现商品 2可配送商品
     * @param string $page 分页 当前页 默认第一页开始
     * @param string $mobile   手机号
     */
    public function list(){
        $this->verifyUser();
        $param = $this->request->post();
        $page = $this->request->post('page'); //分页
        if (!$page) {
            $this->error(__('parameter error'));
        }
        $list = (new ModelUserwarehouse())->list($param,$this->uid);
        $this->success(__('The request is successful'),$list);
    }

    /**
     * 可兑现金额
     */
    public function gettotal(){
        $this->verifyUser();
        $total = (new ModelUserwarehouse())->gettotal($this->uid);
        $this->success(__('The request is successful'),$total);
    }

    /**
     * 地址列表
     */
    public function getaddress(){
        $this->verifyUser();
        $list = (new Useraddress())->getaddresslist($this->uid);
        $this->success(__('The request is successful'),$list);
    }

    /**
     * 地址确认
     *
     * @ApiMethod (POST)
     * @param string $order_id 订单ID
     * @param string $wid 仓库ID
     * @param string $address_id 地址ID
     */
    public function verifyaddress(){
        $this->verifyUser();
        $param = $this->request->post();
        $order_id = $this->request->post('order_id'); //订单ID
        $wid = $this->request->post('wid'); //仓库ID
        $address_id = $this->request->post('address_id'); //地址ID
        if (!$order_id || !$wid || !$address_id) {
            $this->error(__('parameter error'));
        }
        $return = (new Logisticsorder())->verifyaddress($param,$this->uid);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 兑换现金
     *
     * @ApiMethod (POST)
     * @param string $id 仓库ID
     */
    public function exchangemoney(){
        $this->verifyUser();
        $wid = $this->request->post('wid'); //仓库ID
        if (!$wid) {
            $this->error(__('parameter error'));
        }
        $userinfo = $this->userInfo;
        $status = (new ModelUserwarehouse())->where('id',$wid)->find();
        if($status['status'] == 1){
            $this->error(__('operation failure'));
        }
        $return = (new ModelUserwarehouse())->exchangemoney($wid,$this->uid,$userinfo['level'],$status);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 兑换全部现金 All
     * @ApiMethod (POST)
     */
    public function exchangemoneyall(){
        $this->verifyUser();
        $status = (new ModelUserwarehouse())->where('user_id',$this->uid)->where('status',0)->select();
        if(!$status){
            $this->error(__('operation failure'));
        }
        $userinfo = $this->userInfo;
        $return = (new ModelUserwarehouse())->exchangemoneyall($userinfo);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 配送详情 状态:0=确认地址(待收货),1=配送商品(已发货),2=商品已送达(待签收),3=已确认收货
     *
     * @ApiMethod (POST)
     */
    public function shippingdetails(){
        $this->verifyUser();
        $wid = $this->request->post('wid'); //仓库ID
        if (!$wid) {
            $this->error(__('parameter error'));
        }
        $return = (new ModelUserwarehouse())->shippingdetails($wid);
        $this->success(__('The request is successful'),$return);
    }

     /**
     * 确认收货
     *
     * @ApiMethod (POST)
     */
    public function confirm_receipt(){
        $this->verifyUser();
        $id = $this->request->post('id'); //仓库ID
        if (!$id) {
            $this->error(__('parameter error'));
        }
        (new Logisticsorder())->where('id',$id)->update(['status'=>3]);
        $this->success(__('operate successfully'));
    }
}
