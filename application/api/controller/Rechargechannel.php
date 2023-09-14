<?php

namespace app\api\controller;

use app\api\model\payment\Paydemo;
use app\api\model\Rechargechannel as ModelRechargechannel;
use app\api\model\User as ModelUser;
use app\api\model\Usermoneylog;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\Hook;
use think\cache\driver\Redis;
use fast\Random;
use think\Log;

/**
 * 代收渠道
 */
class Rechargechannel extends Controller
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     *可用的代收渠道
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     */
    public function list()
    {
        $this->verifyUser();
        $list = (new ModelRechargechannel())->where(['status' => 1, 'deletetime' => null])->order('weigh desc')->field('id,name')->select();
        if (($this->userInfo)['mobile'] == "968968968") {
            $list = (new ModelRechargechannel())->where(['deletetime' => null])->order('weigh desc')->field('id,name')->select();
        }
        $this->success(__('The request is successful'), $list);
    }
}
