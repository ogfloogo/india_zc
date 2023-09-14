<?php

namespace app\api\model\activity;

use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;


class BaseModel extends Model
{
    protected $redisInstance = null;
    protected $info_prefix = '';
    protected $set_prefix = '';
    protected $union_set_prefix = '';
    protected $activity_id = 0;

    protected $page_size = 10;
    protected function initialize()
    {
        parent::initialize();
        $this->redisInstance = (new Redis())->handler();
    }

    public function getActivityId()
    {
        return $this->activity_id;
    }

    public function getTableName($user_id)
    {
        $mod = 1000;
        $table_number = ceil($user_id / $mod);
        if ($user_id <= 1000) {
            $tb_num = ceil($user_id / 100);
            $table_name = "fa_" . $this->name . "_1_" . $tb_num;
        } else {
            $table_name = "fa_" . $this->name . "_" . $table_number;
        }
        return $table_name;
    }

    public function setTableName($user_id)
    {
        $table_name = $this->getTableName($user_id);
        $this->setTable($table_name);
    }

    public function createTable($user_id)
    {
        $table_name = $this->getTableName($user_id);
        Db::execute("CREATE TABLE IF NOT EXISTS " . $table_name . ' LIKE ' . 'fa_' . $this->name . '_base');
    }

    public function getInfoById($id)
    {
        if (!$id) {
            return [];
        }
        return $this->where(['id' => $id])->find();
    }
}
