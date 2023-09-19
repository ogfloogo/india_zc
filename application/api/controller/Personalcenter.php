<?php

namespace app\api\controller;

use app\api\model\Banner;
use app\api\model\Order;
use app\api\model\Useraddress;
use app\api\model\Userbank;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Log;

/**
 * 个人中心
 */
class Personalcenter extends Controller
{

    /**
     *地址管理-地址列表
     *
     */
    public function getaddress(){
        $this->verifyUser();
        $list = (new Useraddress())->getaddresslist($this->uid);
        $this->success(__('The request is successful'),$list);
    }

    /**
     * 地址添加
     * 
     */
    public function address(){
        $this->verifyUser();
        $post = $this->request->post();
        $name = $this->request->post('name');//收货人姓名
        $mobile = $this->request->post('mobile');//收货人手机号码
        $postcode = $this->request->post('postcode');//收货邮编
        $province = $this->request->post('province');//省份
        $city = $this->request->post('city');//城市
        $county = $this->request->post('county');//县城
        $village = $this->request->post('village');//农村
        $address = $this->request->post('address');//详细地址
        if(!$name || !$mobile || !$postcode || !$province || !$city || !$county || !$village || !$address){
            $this->error(__('parameter error'));
        }
        $return = (new Useraddress())->address($post,$this->uid);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 地址编辑
     * 
     */
    public function editaddress(){
        $this->verifyUser();
        $post = $this->request->post();
        $id = $this->request->post('id');//ID
        if(!$id){
            $this->error(__('parameter error'));
        }
        $return = (new Useraddress())->editaddress($post,$this->uid);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 地址删除
     * 
     */
    public function deladdress(){
        $this->verifyUser();
        $post = $this->request->post();
        $id = $this->request->post('id');//ID
        if(!$id){
            $this->error(__('parameter error'));
        }
        $return = (new Useraddress())->deladdress($post);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 设置默认地址
     * 
     */
    public function setdefault(){
        $this->verifyUser();
        $post = $this->request->post();
        $id = $this->request->post('id');//ID
        $is_default = $this->request->post('is_default');//ID
        if(!$id || !$is_default){
            $this->error(__('parameter error'));
        }
        $return = (new Useraddress())->setdefault($post);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 用户银行卡列表
     * 
     */
    public function getbanklist(){
        $this->verifyUser();
        $list = (new Userbank())->getbanklist();
        $this->success(__('The request is successful'),$list);
    }

    /**
     * 银行卡添加
     * 
     */
    public function addbankcard(){
        $this->verifyUser();
        $post = $this->request->post();
        $username = $this->request->post('username');//用户真实姓名
        $bankcard = $this->request->post('bankcard');//银行卡卡号
        $bankname = $this->request->post('bankname');//银行卡名称
        $bankphone = $this->request->post('bankphone');//银行卡手机号
        $ifsc = $this->request->post('ifsc');//IFSC
        if(!$username || !$bankcard || !$bankname || !$bankphone || !$ifsc){
            $this->error(__('parameter error'));
        }
        $return = (new Userbank())->addbankcard($post,$this->uid);
        if($return){
            if ($return['code'] && $return['code'] == 3) {
                $this->error(__("The bank card number already exists"));
            }
            $this->success(__('operate successfully'));
        }
        $this->success(__('operation failure'));
    }

     /**
     * 银行卡编辑
     * 
     */
    public function editbankcard(){
        $this->verifyUser();
        $post = $this->request->post();
        $id = $this->request->post('id');//ID
        if(!$id){
            $this->error(__('parameter error'));
        }
        $return = (new Userbank())->editbankcard($post,$this->uid);
        if($return){
            if ($return['code'] && $return['code'] == 3) {
                $this->error(__("The bank card number already exists"));
            }
            $this->success(__('operate successfully'));
        }
        $this->success(__('operation failure'));
    }

    /**
     * 银行卡删除
     * 
     */
    public function delbankcard(){
        $this->verifyUser();
        $post = $this->request->post();
        $id = $this->request->post('id');//ID
        if(!$id){
            $this->error(__('parameter error'));
        }
        $return = (new Userbank())->delbankcard($post);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }

    /**
     * 设置默认银行卡
     * 
     */
    public function setdefaultbank(){
        $this->verifyUser();
        $post = $this->request->post();
        $id = $this->request->post('id');//ID
        $is_default = $this->request->post('is_default');//ID
        if(!$id || !$is_default){
            $this->error(__('parameter error'));
        }
        $return = (new Userbank())->setdefault($post,$this->uid);
        if(!$return){
            $this->error(__('operation failure'));
        }
        $this->success(__('operate successfully'));
    }
}
