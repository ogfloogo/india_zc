<?php

namespace app\api\controller;

use app\api\model\Goodscategory;
use app\api\model\Order as ModelOrder;
use app\api\model\Usermoneylog;
use app\common\model\User;
use think\cache\driver\Redis;
use think\helper\Time;
use think\Log;

/**
 * 开奖
 */
class Drawwinning extends Controller
{

    /**
     *开奖
     *
     * @ApiMethod (POST)
     * @param string $order_id   订单ID
     */
    public function goodscategory(){
        $categoryList = (new Goodscategory())->getcategoryList();
        $this->success(__('The request is successful'),$categoryList);
    }
}
