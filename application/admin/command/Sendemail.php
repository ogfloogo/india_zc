<?php

namespace app\admin\command;
use app\api\model\Financeorder;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Log;
use think\Validate;
use app\common\library\Email;




class Sendemail extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('Sendemail')
            ->setDescription('邮件发送');
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        $this->sendemail();
    }

    /**
     * 邮件发送
     */
    protected function sendemail()
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
        $list = db('user')->where('email','not null')->field('id,email')->order('id asc')->limit(22096,22695)->select();
        foreach($list as $key=>$value){
            $receiver = $value['email'];
            if ($receiver) {
                if (!Validate::is($receiver, "email")) {
                    //$this->error(__('Please input correct email'));
                    continue;
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
                    db('send_eamil_log')->insert([
                        'user_id' => $value['id'],
                        'email' => $value['email'],
                        'date' => date("Y-m-d",time()),
                        'createtime' => time(),
                        'status' => 1
                    ]);
                    echo "邮箱：".$value['email'].",发送成功";
                    echo "\n";
                } else {
                    db('send_eamil_log')->insert([
                        'user_id' => $value['id'],
                        'email' => $value['email'],
                        'date' => date("Y-m-d",time()),
                        'createtime' => time(),
                        'status' => 0
                    ]);
                    Log::mylog('发送失败邮箱',$value,'sendemail_error');
                    Log::mylog('发送失败原因',$email->getError(),'sendemail_error');
                    echo "邮箱：".$value['email'].",发送失败";
                    echo "\n";
                    continue;
                }
            } else {
                //$this->error(__('Invalid parameters'));
                continue;
            }
        }
        Log::mylog('结束',$list,'sendemail');
    }

}
