<?php

namespace app\api\controller;

use app\api\model\User;
use app\common\exception\UploadException;
use app\common\library\Upload;

/**
 * 网络文化页面
 */
class Uploads extends Controller
{
    /**
     * 文件上传
     */
    public function uploadfile()
    {
        $attachment = null;
        //默认普通上传文件
        $file = $this->request->file('file');
        try {
            $upload = new Upload($file);
            $attachment = $upload->upload();
        } catch (UploadException $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
    }

    /**
     * 修改用户头像
     */
    public function updateavatar()
    {
        $this->verifyUser();
        $attachment = null;
        //默认普通上传文件
        $file = $this->request->file('file');
        try {
            $upload = new Upload($file);
            $attachment = $upload->upload();
        } catch (UploadException $e) {
            $this->error($e->getMessage());
        }
        $upd = (new User())->where('id', $this->uid)->update(['avatar' => $attachment->url]);
        if (!$upd) {
            $this->error(__('operation failure'));
        }
        //更新用户缓存信息
        (new User())->refresh($this->uid);
        $this->success(__('operate successfully'));
    }

    /**
     * 分享海报
     */
    public function uploadshare()
    {
        $this->verifyUser();
        //默认普通上传文件
        $file = $this->request->file('file');
        try {
            $upload = new Upload($file);
            $attachment = $upload->uploadshare($this->uid);
        } catch (UploadException $e) {
            $this->error($e->getMessage());
        }
        $this->success(__('operate successfully'),$attachment);
    }
}
