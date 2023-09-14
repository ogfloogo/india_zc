<?php

namespace app\api\controller;

use app\api\model\Faq as ModelFaq;
use think\Config;

/**
 * FAQ
 */
class Faq extends Controller
{

    /**
     * 问答列表
     */
    public function list()
    {
        $list = (new ModelFaq())->list($this->language);
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 平台介绍
     */
    public function pingtai_picture()
    {
        $this->success(__('The request is successful'), ['content' => Config::get('site.pingtai_picture')]);
    }
}
