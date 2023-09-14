<?php

namespace app\api\model;

use think\Model;
use think\Config;

/**
 * 短信发送记录
 */
class Sendemaillog extends Model
{
    protected $name = 'send_eamil_log';

    /**
     * 发送测试邮件
     * @internal
     */
    public function sendeamil($receiver)
    {
        $row = [
            'mail_type' => Config::get('site.mail_type'),
            'mail_smtp_host' => Config::get('site.mail_smtp_host'),
            'mail_smtp_port' => Config::get('site.mail_smtp_port'),
            'mail_smtp_user' => Config::get('site.mail_smtp_user'),
            'mail_smtp_pass' => Config::get('site.mail_smtp_pass'),
            'mail_verify_type' => Config::get('site.mail_verify_type'),
            'mail_from' => Config::get('site.mail_from'),
            'email_content' => Config::get('site.email_content'),
            'email_title' => Config::get('site.email_title'),
        ];
        if ($receiver) {
            if (!Validate::is($receiver, "email")) {
                $this->error(__('Please input correct email'));
            }
            \think\Config::set('site', array_merge(\think\Config::get('site'), $row));
            $email = new Email;
            $result = $email
                ->to($receiver)
                ->subject(config('site.email_title'))
                ->message(
                    '<div style="min-height:550px; padding: 100px 55px 200px;">' .  config('site.email_content') . '</div>')
                ->send();
            if ($result) {
                $this->success();
            } else {
                $this->error($email->getError());
            }
        } else {
            $this->error(__('Invalid parameters'));
        }
    }
}
