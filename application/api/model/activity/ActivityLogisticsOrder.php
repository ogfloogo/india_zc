<?php

namespace app\api\model\activity;

use app\api\model\activity\BaseModel;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;


class ActivityLogisticsOrder extends Model
{
    public function verifyaddress($user_draw_id, $warehouse_id, $address_id, $user_id)
    {
        $draw_model = (new UserDrawLog());
        $draw_model->setTableName($user_id);
        $user_draw_info = $draw_model->getInfoById($user_draw_id);
        if (empty($user_draw_info)) {
            return false;
        }
        if ($user_draw_info['status']) {
            return false;
        }

        //开启事务
        Db::startTrans();
        try {
            $this->insert([
                "order_no" => $user_id . '_' . $user_draw_id,
                "user_draw_id" => $user_draw_id,
                "user_id" => $user_id,
                "warehouse_id" => $warehouse_id,
                "address_id" => $address_id,
                "createtime" => time(),
                "updatetime" => time(),
            ]);
            //更新仓库状态
            (new ActivityWarehouse())->where(['id' => $warehouse_id])->update(['status' => 2]);
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            return false;
        }
    }
}
