<?php

namespace app\api\model;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

/**
 * FAQ
 */
class Faq extends Model
{
    protected $name = 'faq';

    public function list($language){
        $list = $this->where(['status'=>1,'language'=>$language,'deletetime'=>null])->field('title,content')->select();
        foreach($list as $key=>$value){
            $list[$key]['titile'] = $value['title'];
        }
        return $list;
    }
}
