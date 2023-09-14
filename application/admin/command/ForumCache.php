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


class ForumCache extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('ForumCache')
            ->setDescription('论坛');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        $this->buildforumlist();
        $this->buildforumcommentlist();
    }

    /**
     * 帖子列表-初始化
     */
    protected function buildforumlist(){
        $forumlist = Db::table("fa_forum")->where('deletetime',null)->select();
        foreach($forumlist as $key=>$value){
            (new CacheModel())->setLevelCacheIncludeDel("forumlist", $value['id'], $value);
            // (new CacheModel())->setSortedSetCache("forumlist", $value['id'], $value, $value['pid'], $value['is_top']);
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
            // (new CacheModel())->setSortedSetCache("commentlist", $value['id'], $value, $value['fid'], $value['createtime']);
            (new CacheModel())->setRecommendSortedSetCache("commentlist", $value['id'], $value,  $value['fid'], $value['createtime']);
            (new CacheModel())->setSortedSetCache("commentlist", $value['id'], $value, 0, $value['createtime']);
        }
    }

    /**
     * 频道列表-初始化
     */
    protected function buildforumchannel(){
        $forumlist = Db::table("fa_forum_channel")->where('deletetime',null)->select();
        foreach($forumlist as $key=>$value){
            (new CacheModel())->setLevelCacheIncludeDel("forumchannel", $value['id'], $value);
            (new CacheModel())->setSortedSetCache("forumchannel", $value['id'], $value, 0, $value['weigh']);
        }
    }
}
