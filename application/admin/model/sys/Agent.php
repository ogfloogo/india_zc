<?php

namespace app\admin\model\sys;

use app\admin\model\Admin;
use fast\Random;
use think\Model;
use traits\model\SoftDelete;

class Agent extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'agent';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text'
    ];



    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function checkAndAdd($admin_id)
    {
        $wc['admin_id'] = $admin_id;
        $wc['deletetime'] = null;
        $exist = $this->where($wc)->count();
        if ($exist) {
            return true;
        }
        $code = Random::alnum(8);
        while ($this->where(['code' => $code, 'admin_id' => ['neq', $admin_id]])->count()) {
            $code = Random::alnum(8);
        }
        $data['admin_id'] = $admin_id;
        $data['code'] = $code;
        $data['status'] = 1;
        $data['createtime'] = time();
        $data['updatetime'] = time();
        $id = $this->insertGetId($data);
        if ($id) {
            return (new Admin())->where(['id' => $admin_id])->update(['agent_id' => $id]);
        }
    }
}
