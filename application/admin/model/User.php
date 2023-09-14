<?php

namespace app\admin\model;

use app\admin\model\finance\UserRecharge;
use app\admin\model\user\UserMoneyLog as UserUserMoneyLog;
use app\admin\model\user\UserTeam;
use app\api\model\Level;
use app\api\model\Usercategory;
use app\api\model\Usermoneylog;
use app\api\model\Usertotal;
use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use think\cache\driver\Redis;
use think\Model;

class User extends Model
{

    // 表名
    protected $name = 'user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'prevtime_text',
        'logintime_text',
        'jointime_text',
        'sid_name',
        'first_recharge_time',
        'group_number'
    ];

    public function getOriginData()
    {
        return $this->origin;
    }

    protected static function init()
    {
        self::beforeUpdate(function ($row) {
            $changed = $row->getChangedData();
            //如果有修改密码
            if (isset($changed['password']) || isset($changed['withdraw_password'])) {
                $salt = \fast\Random::alnum();
                if (isset($changed['password']) && $changed['password']) {
                    $row->password = \app\common\library\Auth::instance()->getEncryptPassword($changed['password'], $salt);
                    $row->salt = $salt;
                } else {
                    unset($row->password);
                }
                if (isset($changed['withdraw_password']) && $changed['withdraw_password']) {
                    $row->withdraw_password = \app\common\library\Auth::instance()->getEncryptPassword($changed['withdraw_password'], $salt);
                    $row->salt = $salt;
                } else {
                    unset($row->withdraw_password);
                }
            }
        });


        self::beforeUpdate(function ($row) {

            $changedata = $row->getChangedData();
            $origin = $row->getOriginData();
            if (isset($changedata['money']) && (function_exists('bccomp') ? bccomp($changedata['money'], $origin['money'], 2) !== 0 : (float)$changedata['money'] !== (float)$origin['money'])) {
                if (bccomp($changedata['money'], $origin['money'], 2) == 1) {
                    $mold = 'inc';
                } else {
                    $mold = 'dec';
                }
                if (isset($_REQUEST['remark'])) {
                    $remark =  $_REQUEST['remark'] ?? "管理员变更金额";
                } else {
                    $remark =  "管理员变更金额";
                }
                $type = 10; //变动类型:1=充值,2=提现,3=邀请奖励,4=佣金收入,5=团购下单,6=拒绝提现,7=团购奖励,8=团长奖励,9=新用户注册奖励,10=管理员操作
                $obj = (new UserUserMoneyLog());
                $obj->setTableName($row['id']);
                $obj->create(['user_id' => $row['id'], 'money' => abs(bcsub($changedata['money'], $origin['money'], 2)), 'before' => $origin['money'], 'after' => $changedata['money'], 'remark' => $remark, 'mold' => $mold, 'type' => $type]);
            }
            if (isset($changedata['score']) && (int)$changedata['score'] !== (int)$origin['score']) {
                ScoreLog::create(['user_id' => $row['id'], 'score' => $changedata['score'] - $origin['score'], 'before' => $origin['score'], 'after' => $changedata['score'], 'remark' => '管理员变更积分']);
            }
        });

        self::afterUpdate(function ($row) {
            $changedata = $row->getChangedData();
            $origin = $row->getOriginData();
            if (isset($changedata['money']) && (function_exists('bccomp') ? bccomp($changedata['money'], $origin['money'], 2) !== 0 : (float)$changedata['money'] !== (float)$origin['money'])) {
                $type = 10; //变动类型:1=充值,2=提现,3=邀请奖励,4=佣金收入,5=团购下单,6=拒绝提现,7=团购奖励,8=团长奖励,9=新用户注册奖励,10=管理员操作
                //统计当日报表
                (new Usercategory())->addlog($type, $row['id'], abs(bcsub($changedata['money'], $origin['money'], 2)));
                //统计用户总报表
                (new Usertotal())->addlog($type, $row['id'], abs(bcsub($changedata['money'], $origin['money'], 2)));
                //刷新用户信息
                $userinfo_new = (new User())->where('id', $row['id'])->find();
                $redis = new Redis();
                $redis->handler()->select(1);
                $cache = $redis->handler()->get("token:" . $userinfo_new['token']);
                if ($cache) {
                    $redis->handler()->set("token:" . $userinfo_new['token'], json_encode($userinfo_new->toArray()), 60 * 60 * 24);
                }
            }
        });
    }

    public function getGenderList()
    {
        return ['1' => __('Male'), '0' => __('Female')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }


    public function getPrevtimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['prevtime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getLogintimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['logintime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getJointimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['jointime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPrevtimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setLogintimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setJointimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setBirthdayAttr($value)
    {
        return $value ? $value : null;
    }

    public function usertotal()
    {
        return $this->belongsTo('\app\admin\model\userlevel\UserTotal', 'id', 'user_id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getFirstRechargetimeAttr($value, $data)
    {
        $info = (new UserRecharge())->where(['status' => 1, 'user_id' => $data['id']])->order('id ASC')->find();
        $value = $info ? $info['paytime'] :  "";
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getGroupNumberAttr($value, $data)
    {
        $count = (new UserTeam())->where(['user_id' => $data['id'], 'level' => ['gt', 0]])->count();
        return $count;
    }

    public function getSidNameAttr($value, $data)
    {
        $name = $this->where(['id' => $data['sid']])->value('mobile');
        return $name;
    }
}
