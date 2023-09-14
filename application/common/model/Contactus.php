<?php

namespace app\common\model;

use think\Model;
use traits\model\SoftDelete;
use think\cache\driver\Redis;

class Contactus extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'contact_us';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [

    ];
    
 /**
     * 存redis
     */
    public function setcache($id){
        $res = $this->where('id',$id)->find()->toArray();
        $redis = new Redis();
        $ist = $redis->handler()->hMset("zclc:contactus:".$id,$res);
        if(!$ist){
            return false;
        }
        return true;
    }


    public function delcache($ids)
    {
        $redis = new Redis();
        if (empty($ids)) {
            return false;
        }
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $redis->handler()->del("zclc:contactus:".$id);
            }
            return true;
        } else {
            return $redis->handler()->del("zclc:contactus:".$ids);
        }
    }

    public function getTypeList()
    {
        return ['2' => __('Type 2'), '3' => __('Type 3')];
    }






}
