<?php

namespace app\admin\model\activity;

use app\admin\model\CacheModel;
use think\Model;
use traits\model\SoftDelete;

class Popups extends CacheModel
{

    use SoftDelete;

    

    // 表名
    protected $name = 'popups';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'is_login_text',
        'status_text'
    ];
    
    public $cache_prefix = 'zclc:popup:';

    
    public function getIsLoginList()
    {
        return ['0' => __('Is_login 0'), '1' => __('Is_login 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getIsLoginTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_login']) ? $data['is_login'] : '');
        $list = $this->getIsLoginList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
