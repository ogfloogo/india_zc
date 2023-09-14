<?php

namespace app\api\controller;

use app\api\model\Level;
use app\api\model\Teamlevel;
use fast\Http;
use fast\Random;
use think\cache\driver\Redis;
use think\Log;

/**
 * 示例接口
 */
class Revision extends Controller
{
    /**
     * 一级筛选列表
     */
    public function Firstlevelfilter(){
        // $list = (new Level())->levelscreenlist();
        $return = [
            ['id' => 0, 'level' => 'recommend', 'name' => "Recommend"],['id' => 1, 'level' => 'all', 'name' => "All"]
        ];
        // foreach($list as $value){
        //     if($value['level'] > 1){
        //         $return[] = $value;
        //     }
        // }
        // array_unshift($return, ['id' => 0, 'level' => 'recommend', 'name' => "Recommend"],['id' => 1, 'level' => 'all', 'name' => "All"]);
        $this->success("The request is successful",$return);
    }
    
}
