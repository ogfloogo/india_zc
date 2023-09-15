<?php

namespace app\admin\command;
use app\api\model\Financeorder;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Loader;
use app\admin\model\CacheModel;
use app\admin\model\sys\TeamLevel;
use app\common\model\Protocol;
use think\cache\driver\Redis;
use app\common\model\Contactus as ModelContactus;


class BuildCache extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('BuildCache')
            ->setDescription('重建缓存（等级、商品、商品区）');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        // (new Usertotal())->setLogin(70);
        // echo (new Usertotal())->getLoginCount();

        // return ;
        set_time_limit(0);
        $this->buildFinanceCategoryCache();
        $this->buildFinanceCache();
        $this->buildLevelCache();
        $this->buildFinance();
        $this->buildProtocol();
        $this->buildContactus();
        $this->buildappverion();
        $this->buildOrder();
        $this->buildLabel();
        $this->buildJgq();
        $this->buildforumlist();
        $this->buildforumcommentlist();
    }


    protected function buildJgq()
    {
        $list = Db::table('fa_recommend')->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            (new CacheModel())->setLevelCache("financejgq", $item['id'], $item);
            (new CacheModel())->setSortedSetCache("financejgq", $item['id'], $item, 0, $item['weigh']);
        }
    }
    /**
     * 方案标签
     */
    protected function buildLabel()
    {
        $list = Db::table('fa_label')->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            (new CacheModel())->setLevelCache("financelabel", $item['id'], $item);
            (new CacheModel())->setSortedSetCache("financelabel", $item['id'], $item, 0, $item['weigh']);
        }
    }

    /**
     * 理财活动
     */
    protected function buildFinanceCategoryCache()
    {
        $list = Db::table('fa_finance')->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            (new CacheModel())->setLevelCache("finance", $item['id'], $item);
            (new CacheModel())->setSortedSetCache("finance", $item['id'], $item, 0, $item['weigh']);
        }
    }

    /**
     * 理财方案
     */
    protected function buildFinanceCache()
    {
        // $list = (new Goods())->where(['id' => ['GT', 0]])->select();
        $list =  Db::table('fa_finance_project')->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            (new CacheModel())->setLevelCacheIncludeDel("financeproject", $item['id'], $item);
            (new CacheModel())->setSortedSetCache("financeproject", $item['id'], $item, $item['f_id'], $item['weigh']);
            (new CacheModel())->setRecommendSortedSetCache("financeproject", $item['id'], $item,  $item['f_id'], $item['weigh']);
            (new CacheModel())->setSortedSetCache("financeproject", $item['id'], $item, 0, $item['weigh']);
        }
    }

    /**
     * 团队等级
     */
    protected function buildLevelCache()
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
    protected function buildFinance(){
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
    protected function buildProtocol(){
        $redis = new Redis();
        $redis->handler()->select(6);
        $finance_list = Db::table("fa_protocol")->where('deletetime',null)->select();
        foreach($finance_list as $key=>$value){
            (new Protocol())->setcache($value['id']);
        }
    }

    /**
     * 联系我们
     */
    protected function buildContactus(){
        $redis = new Redis();
        $redis->handler()->select(6);
        $finance_list = Db::table("fa_contact_us")->where('deletetime',null)->select();
        foreach($finance_list as $key=>$value){
            (new ModelContactus())->setcache($value['id']);
        }
    }

    /**
     * APP版本
     */
    protected function buildappverion(){
        (new \app\admin\model\sys\AppVersion())->setCurrentVersion();
    }

    protected function buildOrder(){
        set_time_limit(0);
        $list = (new Financeorder())->where(['status'=>1,'is_robot'=>0])->select();
        foreach ($list as $value){
            $redis = new Redis();
            $redis->handler()->select(6);
            if($value['popularize'] == 0){//类型:0=普通项目,1=推广项目,2=体验项目
                $redis->handler()->zAdd("zclc:financelist", $value['collection_time'], $value['id']);
            }elseif($value['popularize'] == 1){
                $redis->handler()->zAdd("zclc:financelisttg", $value['collection_time'], $value['id']);
            }elseif($value['popularize'] == 2){
                $redis->handler()->zAdd("zclc:financelistty", $value['collection_time'], $value['id']);
            }
        }
    }

    /**
     * 帖子列表-初始化
     */
    protected function buildforumlist(){
        $forumlist = Db::table("fa_forum_list")->where('deletetime',null)->select();
        foreach($forumlist as $key=>$value){
            (new CacheModel())->setLevelCacheIncludeDel("forumlist", $value['id'], $value);
            (new CacheModel())->setSortedSetCache("forumlist", $value['id'], $value, $value['pid'], $value['is_top']);
            (new CacheModel())->setRecommendSortedSetCache("forumlist", $value['id'], $value,  $value['pid'], $value['is_top']);
            (new CacheModel())->setSortedSetCache("forumlist", $value['id'], $value, 0, $value['is_top']);
        }
    }

    /**
     * 评论列表-初始化
     */
    protected function buildforumcommentlist(){
        $forumlist = Db::table("fa_forum_comment")->where('deletetime',null)->select();
        foreach($forumlist as $key=>$value){
            (new CacheModel())->setLevelCacheIncludeDel("commentlist", $value['id'], $value);
            (new CacheModel())->setSortedSetCache("commentlist", $value['id'], $value, $value['fid'], $value['createtime']);
            (new CacheModel())->setRecommendSortedSetCache("commentlist", $value['id'], $value,  $value['fid'], $value['createtime']);
            (new CacheModel())->setSortedSetCache("commentlist", $value['id'], $value, 0, $value['createtime']);
        }
    }

}
