<?php

namespace app\api\model;

use think\Model;
use app\api\controller\Controller as base;
use app\api\controller\Shell;
use Exception;
use think\Config;
use think\Db;
use think\helper\Time;
use think\Log;

use function EasyWeChat\Kernel\Support\get_client_ip;

/**
 * 用户提现
 */
class Usercash extends Model
{
    protected $name = 'user_cash';

    /**
     * 提现记录
     */
    const TYPECASH = [
        "english" => [
            0 => "Processing", //待审核
            1 => "Estimated arrival time ", //审核通过未提交
            2 => "Approved, waiting for bank payment", //审核通过已提交
            3 => "Success", //代付成功
            4 => "Fail", //代付失败
            5 => "Fail", //已驳回
            6 => "Success", //模拟成功
        ],
        "india" => [
            0 => "प्रसंस्करण", //待审核
            1 => "अनुमानित आगमन समय ", //审核通过未提交
            2 => "स्वीकृत, बैंक भुगतान की प्रतीक्षा कर रहा है", //审核通过已提交
            3 => "सफलता", //代付成功
            4 => "विफल", //代付失败
            5 => "विफल", //已驳回
            6 => "सफलता", //代付成功
        ]
    ];

    /**
     * 用户提现
     */
    public function userwithdraw($post, $userinfo)
    {
        //提现费率
        // $rate = (new Teamlevel())->detail($userinfo['level']);
        // if($rate){
        //     $withdraw_fee = $rate['withdrawal_rate'] / 100;
        // }else{
        //     $withdraw_fee = Config::get('site.withdraw_fee') / 100;
        // }
        $withdraw_fee = Config::get('site.withdraw_fee') / 100;
        //代付订单号
        $order_id = $this->createorder();
        while ($this->where(['order_id' => $order_id])->find()) {
            $order_id = $this->createorder();
        }
        //代付银行卡信息
        $bankinfo = (new Userbank())->where('id', $post['bank_id'])->find();
        //手续费
        $servicecharge = bcmul($post['price'], $withdraw_fee, 2);
        //到账金额
        $trueprice = bcsub($post['price'], $servicecharge);
        //事务开启
        //Db::startTrans();
        try {
            $insert = [
                "user_id" => $userinfo["id"], //用户ID
                "order_id" => $order_id, //代付订单号
                "username" => $bankinfo["username"], //持卡人姓名
                "bankcard" => $bankinfo["bankcard"], //卡号
                "bankname" => $bankinfo["bankname"], //银行编码
                "ifsc" => $bankinfo["ifsc"] ?? "", //ifsc
                "phone" => $bankinfo["bankphone"], //持卡人手机号
                "email" => $bankinfo["email"] ?? "", //邮箱
                "address" => $bankinfo["address"] ?? "", //银行卡地址
                "price" => $post['price'], //提现金额
                "trueprice" => $trueprice, //到账金额
                "after_money" => bcsub($userinfo["money"], $post['price'], 2), //提现后余额
                "ip" => get_real_ip(), //提现IP地址
                "createtime" => time(),
                "updatetime" => time(),
                "agent_id" => intval($userinfo['agent_id']),
            ];
            // $this->insert($insert);
            //扣除余额
            $return = (new Usermoneylog())->withdraw($insert, $userinfo['id'], $post['price'], 'dec', 2, $order_id);
            if(!$return){
                return false;
            }
            (new User())->refresh($userinfo['id']);
            //统计平台日报表
            // (new Shell())->addreport();
            // (new Report())->where('date', date('Y-m-d', time()))->setInc('cash', $post['price']);
            //提交
            // Db::commit();
            return true;
        } catch (Exception $e) {
            Log::mylog('提现失败', $e, 'usercash');
            //Db::rollback();
            return false;
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

    //状态:0=待审核,1=审核通过未提交,2=通过已提交,3=代付成功,4=代付失败,5=已驳回
    /**
     * 提现记录
     */
    public function withdrawlog($post, $user_id, $language)
    {
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount; 
        switch ($post['status']) { //状态:0=待审核,1=审核通过未提交,2=通过已提交,3=代付成功,4=代付失败,5=已驳回
            case 0: //提现待审核
                $where['status'] = ['in', [0,1 ,2,3,4,5,6]];
                break;
            case 1: //提现成功
                $where['status'] = ['in', [3, 6]];
                break;
            case 2: //提现失败
                $where['status'] = ['in', [4, 5]];
                break;
            default:
                # code...
                break;
        }
        $list = $this->where('user_id', $user_id)
            ->where($where)
            ->where('deletetime', null)
            ->field('id,order_id,price,trueprice,status,content,createtime,bankcard,paytime,username,bankname,content')
            ->order('createtime desc')
            ->limit($startNum, $pageCount)
            ->select();
        foreach ($list as $key => $value) {
            if($value['status'] == 1){
                // $week = $this->week($value['createtime']);
                // if($week){
                //     $list[$key]["title"] = self::TYPECASH[$language][$value['status']]. date("Y-m-d",$value['createtime']+60*60*48);
                // }else{
                //     $list[$key]["title"] = self::TYPECASH[$language][$value['status']]. date("Y-m-d",$value['createtime']+60*60*24);
                // }
                $list[$key]["title"] = "Paying，Estimated to arrive within 30 minutes";
            }else{
                $list[$key]["title"] = self::TYPECASH[$language][$value['status']];
            }
            $list[$key]["createtime"] = format_time($value['createtime']);
            $list[$key]["paytime"] = $value['paytime'] == 0 ? "" : format_time($value['paytime']);
        }
        return $list;
    }

    function week($str){
        if((date('w',$str)==6)){
            return true;
        }else{
            return false;
        }
     }
}
