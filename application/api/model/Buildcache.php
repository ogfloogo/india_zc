<?php

namespace app\api\model;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Cache;
use think\Model;
use think\Db;
use app\admin\model\CacheModel;
use app\admin\model\sys\TeamLevel;
use app\common\model\Protocol;
use think\cache\driver\Redis;

/**
 * 更新redis
 */
class Buildcache extends Model
{
    /**
     * 理财活动
     */
    public function buildFinanceCategoryCache()
    {
        $list = Db::table('fa_finance')->order('weigh asc')->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            (new CacheModel())->setLevelCache("finance", $item['id'], $item);
            (new CacheModel())->setSortedSetCache("finance", $item['id'], $item, 0, $item['weigh']);
        }
        return $list;
    }

    /**
     * 理财方案
     */
    public function buildFinanceCache()
    {
        // $list = (new Goods())->where(['id' => ['GT', 0]])->select();
        $list =  Db::table('fa_finance_project')->order('weigh asc')->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            (new CacheModel())->setLevelCacheIncludeDel("financeproject", $item['id'], $item);
            (new CacheModel())->setSortedSetCache("financeproject", $item['id'], $item, $item['f_id'], $item['weigh']);
            (new CacheModel())->setRecommendSortedSetCache("financeproject", $item['id'], $item,  $item['f_id'], $item['weigh']);
            (new CacheModel())->setSortedSetCache("financeproject", $item['id'], $item, 0, $item['weigh']);
        }
        return $list;
    }

    /**
     * 团队等级
     */
    public function buildLevelCache()
    {
        $list = (new TeamLevel())->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            $item = $item->toArray();
            (new CacheModel())->setUserLevelCache("team_level", intval($item['id']), intval($item['level']), $item);
        }
    }

    /**
     * 项目投资金额，购买人数
     */
    public function buildFinance(){
        $redis = new Redis();
        $redis->handler()->select(6);
        $finance_list = Db::table("fa_finance")->where('deletetime',null)->select();
        foreach($finance_list as $key=>$value){
            //购买次数
            $buy_number = Db::table("fa_finance_order")->where('f_id',$value['id'])->group('user_id')->count('id');
            //购买金额
            $buy_amount = Db::table("fa_finance_order")->where('f_id',$value['id'])->sum('amount');
            $redis->handler()->zAdd("zclc:financeordernum", $buy_number, $value['id']);
            $redis->handler()->zAdd("zclc:financeordermoney", $buy_amount, $value['id']);
        }
    }

    /**
     * 规则协议配置
     */
    /**
     * 项目投资金额，购买人数
     */
    protected function buildProtocol(){
        $redis = new Redis();
        $redis->handler()->select(6);
        $finance_list = Db::table("fa_protocol")->where('deletetime',null)->select();
        foreach($finance_list as $key=>$value){
            (new Protocol())->setcache($value['id']);
        }
    }
}
