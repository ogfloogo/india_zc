<?php

namespace app\admin\model\sys;

use think\cache\driver\Redis;
use think\Model;
use traits\model\SoftDelete;

class AppVersion extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'app_version';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'system_text',
        'channel_text',
        'update_type_text',
        'download_type_and_text',
        'download_type_ios_text',
        'download_type_wgt_text',
        'status_text'
    ];
    

    
    public function getSystemList()
    {
        return ['1' => __('System 1'), '2' => __('System 2')];
    }

    public function getChannelList()
    {
        return ['0' => __('Channel 0'), '1' => __('Channel 1')];
    }

    public function getUpdateTypeList()
    {
        return ['1' => __('Update_type 1'), '2' => __('Update_type 2')];
    }

    public function getDownloadTypeAndList()
    {
        return ['1' => __('Download_type_and 1'), '2' => __('Download_type_and 2')];
    }

    public function getDownloadTypeIosList()
    {
        return ['1' => __('Download_type_ios 1'), '2' => __('Download_type_ios 2')];
    }

    public function getDownloadTypeWgtList()
    {
        return ['1' => __('Download_type_wgt 1'), '2' => __('Download_type_wgt 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getSystemTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['system']) ? $data['system'] : '');
        $list = $this->getSystemList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getChannelTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['channel']) ? $data['channel'] : '');
        $list = $this->getChannelList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getUpdateTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['update_type']) ? $data['update_type'] : '');
        $list = $this->getUpdateTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDownloadTypeAndTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['download_type_and']) ? $data['download_type_and'] : '');
        $list = $this->getDownloadTypeAndList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDownloadTypeIosTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['download_type_ios']) ? $data['download_type_ios'] : '');
        $list = $this->getDownloadTypeIosList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDownloadTypeWgtTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['download_type_wgt']) ? $data['download_type_wgt'] : '');
        $list = $this->getDownloadTypeWgtList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function setCurrentVersion(){
        $version = $this->where(['status'=>1,'deletetime'=>null])->order('number DESC')->find();
        if(empty($version)){
            return [];
        }
        $max_version = $version['number'];
        $this->redisInstance = new Redis();
        $this->redisInstance->handler()->set('zclc:max_version', $max_version);
        $this->redisInstance->handler()->hMSet('zclc:max_version_info', $version->toArray());

    }



}
