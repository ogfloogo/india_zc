<?php

namespace app\admin\command;

use app\admin\model\activity\Popups;
use app\admin\model\AuthRule;
use app\admin\model\groupbuy\Goods;
use app\admin\model\groupbuy\GoodsCategory;
use app\admin\model\userlevel\UserLevel;
use app\api\model\Usermerchandise;
use app\api\model\Usertotal;
use ReflectionClass;
use ReflectionMethod;
use think\Cache;
use think\cache\driver\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Loader;

class BuildPool extends Command
{
    protected $model = null;
    protected $redisInstance = null;

    protected function configure()
    {
        $this->setName('BuildPool')
            ->setDescription('构建候选池');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        $this->redisInstance = (new Redis())->handler();
        set_time_limit(0);
        $this->buildGoodsCategoryPoolCache();
    }

    protected function buildGoodsCategoryPoolCache()
    {
        $list = (new GoodsCategory())->where(['status' => 1, 'deletetime' => null])->select();
        foreach ($list as $item) {
            $item = $item->toArray();
            if ($item) {
                $this->buildPoolSet($item);
            }
        }
    }

    protected function buildPoolSet($item)
    {
        $id = $item['id'];
        $begin_time = $item['pool_in_num'];
        $frozen_time = $item['win_must_num'];
        $pool_key = (new GoodsCategory())->cache_prefix . 'pool:' . $id;
        $userList = (new Usermerchandise())->where(['category_id' => $id])->select();
        foreach ($userList as $userItem) {
            $now_time = $userItem['num'];
            $is_wined = $userItem['win_num'];
            $last_win_num = $userItem['last_win_num'];
            if ($is_wined) {
                if ($now_time >= ($last_win_num + $frozen_time)) {
                    //add
                    $this->redisInstance->sAdd($pool_key, $userItem['user_id']);
                } else {
                    $is_in_pool = $this->redisInstance->sIsMember($pool_key, $userItem['user_id']);
                    if ($is_in_pool) {
                        $this->redisInstance->sRem($pool_key, $userItem['user_id']);
                    }
                }
            } else {
                if ($now_time >= $begin_time) {
                    //add
                    $this->redisInstance->sAdd($pool_key, $userItem['user_id']);
                }else{
                    $is_in_pool = $this->redisInstance->sIsMember($pool_key, $userItem['user_id']);
                    if ($is_in_pool) {
                        $this->redisInstance->sRem($pool_key, $userItem['user_id']);
                    }
                }
            }
        }
    }
}
