<?php

namespace app\api\model;

use app\admin\model\finance\WithdrawChannel;
use think\Model;
use think\Config;
use app\api\controller\Controller as base;
use think\Db;
use think\Exception;
use think\Log;

/**
 * 用户银行卡
 */
class Userbank extends Model
{
    protected $name = 'user_bank';

    /**
     * 获取用户地址列表
     */
    public function getbanklist()
    {
        $userid = (new base())->userid();
        $list = $this->where('status', 1)->where('user_id', $userid)->order('updatetime desc')->select();
        return $list;
    }

    /**
     *添加银行卡号
     */
    public function addbankcard($post, $userid)
    {
        //开启事务
        Db::startTrans();
        try {
            // $isset = $this->where('bankcard', $post['bankcard'])->find();
            // if ($isset) {
            //     return ["code" => 3];
            // }
            //卡号是否出过款 
            // $iscash = (new Usercash())
            //     ->where('bankcard', $post['bankcard'])
            //     ->where('user_id', 'neq', $userid)
            //     ->where('status', 3)
            //     ->find();
            // if ($iscash) {
            //     return ["code" => 3];
            // }
            $insert = [
                'user_id' => $userid,
                'username' => $post['username'],
                'bankcard' => $post['bankcard'],
                'bankname' => $post['bankname'],
                'bankphone' => $post['bankphone'],
                'ifsc' => $post['ifsc'],
                'createtime' => time(),
                'updatetime' => time()
            ];
            $this->insert($insert);
            Db::commit();
            return ["code" => 1];
        } catch (Exception $e) {
            Log::mylog('bankcard', $e, 'bankcard');
            //事务回滚
            Db::rollback();
            return false;
        }
    }

    /**
     * 编辑银行卡
     */
    public function editbankcard($post, $userid)
    {
        $isset = $this->where('bankcard', $post['bankcard'])->where("id", "neq", $post['id'])->find();
        if ($isset) {
            return ["code" => 3];
        }
         $isset_ifsc = $this->where('ifsc', $post['ifsc'])->where("id", "neq", $post['id'])->find();
         if ($isset_ifsc) {
             return ["code" => 3];
         }
        //卡号是否出过款 
        $iscash = (new Usercash())
            ->where('bankcard', $post['bankcard'])
            ->where('user_id', 'neq', $userid)
            ->where('status', 3)
            ->find();
        if ($iscash) {
            return ["code" => 3];
        }
        $upd = [
            'username' => $post['username'],
            'bankcard' => $post['bankcard'],
            'bankname' => $post['bankname'],
            'bankphone' => $post['bankphone'],
            'ifsc' => $post['ifsc'],
            'updatetime' => time()
        ];
        $this->where('id', $post['id'])->update($upd);
        return ["code" => 1];
    }

    /**
     * 删除地址
     */
    public function delbankcard($post)
    {
        return $this->where('id', $post['id'])->delete();
    }

    /**
     * 设置默认银行卡
     */
    public function setdefault($post, $uid)
    {
        //是否默认地址
        if ($post['is_default'] == 1) {
            $is_default = $this->where('user_id', $uid)->where('default', 1)->find();
            if ($is_default) {
                $this->where('user_id', $uid)->update(['default' => 0]);
            }
        }
        return $this->where('id', $post['id'])->update(['default' => $post['is_default']]);
    }
}
