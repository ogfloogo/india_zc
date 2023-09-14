<?php

namespace app\admin\validate;

use think\Validate;

class User extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        // 'username' => 'require|regex:\w{3,30}|unique:user',
        // 'nickname' => 'require|unique:user',
        'password' => 'regex:\S{6,30}',
        'withdraw_password' => 'regex:\S{6,30}',
        // 'email'    => 'require|email|unique:user',
        'mobile'   => 'unique:user'
    ];

    /**
     * 字段描述
     */
    protected $field = [];
    /**
     * 提示消息
     */
    protected $message = [];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => [],
        'edit' => ['username', 'nickname', 'password', 'withdraw_password', 'email', 'mobile'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'username' => __('Username'),
            'nickname' => __('Nickname'),
            'password' => __('Password'),
            'withdraw_password' => __('提现密码'),
            'email'    => __('Email'),
            'mobile'   => __('Mobile')
        ];
        parent::__construct($rules, $message, $field);
    }
}
