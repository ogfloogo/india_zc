define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'groupbuy/goods_category/index' + location.search,
                    add_url: 'groupbuy/goods_category/add',
                    edit_url: 'groupbuy/goods_category/edit',
                    del_url: 'groupbuy/goods_category/del',
                    multi_url: 'groupbuy/goods_category/multi',
                    import_url: 'groupbuy/goods_category/import',
                    table: 'goods_category',
                    dragsort_url: '',

                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'level_id', title: __('Level_id'), visible: false },
                        { field: 'level_name', title: __('Level_id'), operate: false },
                        { field: 'name', title: __('Name'), operate: 'LIKE' },
                        { field: 'price', title: __('Price'), operate: 'BETWEEN' },
                        { field: 'extra_reward_desc', title: __('Extra_reward_desc'), operate: 'LIKE' },
                        { field: 'icon_image', title: __('Icon_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image },
                        { field: 'buyback', title: __('Buyback'), operate: 'BETWEEN' },
                        { field: 'reward', title: __('Reward'), operate: 'BETWEEN' },
                        { field: 'win_must_num', title: __('Win_must_num') },
                        { field: 'pool_in_num', title: __('Pool_in_num') },
                        { field: 'daily_win_limit', title: __('Daily_win_limit') },
                        { field: 'now_pool_num', title: __('目前入池数') },
                        { field: 'daily_win_man', title: __('今日已获得') },
                        { field: 'weigh', title: __('Weigh'), operate: false },
                        { field: 'status', title: __('Status'), searchList: { "0": __('Status 0'), "1": __('Status 1') }, formatter: Table.api.formatter.status },
                        // {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        { field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'groupbuy/goods_category/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'name', title: __('Name'), align: 'left' },
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            width: '130px',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'groupbuy/goods_category/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'groupbuy/goods_category/destroy',
                                    refresh: true
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },

        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
