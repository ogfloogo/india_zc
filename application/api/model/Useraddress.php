<?php

namespace app\api\model;

use think\Model;
use think\Config;
use app\api\controller\Controller as base;
use think\Db;
use think\Exception;


/**
 * 用户地址
 */
class Useraddress extends Model
{
    protected $name = 'user_address';
    
    /**
     * 获取用户地址列表
     */
    public function getaddresslist($userid){
       $list = $this->where('user_id',$userid)->where(['status' => 1, 'deletetime' => null])->order('updatetime desc')->select();
       return $list;
    }

    /**
     *添加地址
     */
    public function address($post,$user_id){
        //开启事务
        Db::startTrans();
        try{
            if($post['is_default'] == 1){
                $this->where('user_id',$user_id)->update(['is_default' => 0]);
            }
            $insert = [
                'user_id' => $user_id,
                'name' => $post['name'],
                'mobile' => $post['mobile'],
                'postcode' => $post['postcode'],
                'province' => $post['province'],
                'city' => $post['city'],
                'county' => $post['county'],
                'village' => $post['village'],
                'address' => $post['address'],
                'is_default' => $post['is_default'],
                'createtime' => time(),
                'updatetime' => time()
            ];
            $this->insert($insert);
            Db::commit();
            return true;
        }catch(Exception $e){
            //事务回滚
            Db::rollback();
            return false;
        }
    }

    /**
     * 编辑地址
     */
    public function editaddress($post,$user_id){
        if($post['is_default'] == 1){
            $this->where('user_id',$user_id)->update(['is_default' => 0]);
        }
        $upd = [
            'name' => $post['name'],
            'mobile' => $post['mobile'],
            'postcode' => $post['postcode'],
            'province' => $post['province'],
            'city' => $post['city'],
            'county' => $post['county'],
            'village' => $post['village'],
            'address' => $post['address'],
            'is_default' => $post['is_default'],
            'updatetime' => time()
        ];
        return $this->where('id',$post['id'])->update($upd);
    }

    /**
     * 删除地址
     */
    public function deladdress($post){
        return $this->where('id',$post['id'])->update(['deletetime'=>time()]);
    }

    /**
     * 设置默认地址
     */
    public function setdefault($post){
        $userid = (new base())->userid();
        //是否默认地址
        if($post['is_default'] ==1 ){
            $is_default = $this->where('user_id',$userid)->where('is_default',1)->find();
            if($is_default){
                $this->where('user_id',$userid)->update(['is_default' => 0]);
            }
        }
        return $this->where('id',$post['id'])->update(['is_default' => $post['is_default']]);
    }

}
