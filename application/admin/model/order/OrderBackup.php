<?php

namespace app\admin\model\order;

use think\Model;
use traits\model\SoftDelete;

class OrderBackup extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'order_backup';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'pay_status_text',
        'is_winner_text'
    ];
    

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getPayStatusList()
    {
        return ['0' => __('Pay_status 0'), '1' => __('Pay_status 1'), '2' => __('Pay_status 2')];
    }

    public function getIsWinnerList()
    {
        return ['0' => __('Is_winner 0'), '1' => __('Is_winner 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPayStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_status']) ? $data['pay_status'] : '');
        $list = $this->getPayStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsWinnerTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_winner']) ? $data['is_winner'] : '');
        $list = $this->getIsWinnerList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
