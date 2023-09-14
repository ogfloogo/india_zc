define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'forumsys/forum/index' + location.search,
                    add_url: 'forumsys/forum/add',
                    edit_url: 'forumsys/forum/edit',
                    del_url: 'forumsys/forum/del',
                    multi_url: 'forumsys/forum/multi',
                    import_url: 'forumsys/forum/import',
                    table: 'forum_list',
                    dragsort_url:""
                }
            });

            var table = $("#table");

            $(document).on('click', '.btn-pass', function () {
                var ids = Table.api.selectedids(table);
                var url = 'forumsys/forum/pass/ids/'+ids;
                layer.confirm("确认批量通过？",null,
                    function () {
                        Fast.api.ajax(url,
                            function(){
                                layer.closeAll();
                                table.bootstrapTable('refresh');
                            },function(){
                                layer.closeAll();
                                table.bootstrapTable('refresh');
                            })
                    }
                )
            });
            $(document).on('click', '.btn-refuse', function () {
                var ids = Table.api.selectedids(table);
                var url = 'forumsys/forum/refuse/ids/'+ids;
                layer.confirm("确认批量拒绝？",null,
                    function () {
                        Fast.api.ajax(url,
                            function(){
                                layer.closeAll();
                                table.bootstrapTable('refresh');
                            },function(){
                                layer.closeAll();
                                table.bootstrapTable('refresh');
                            })
                    }
                )
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'pid', title: __('Name'), visible:false},
                        {field: 'channel.name', title: __('Channel.name'), operate: 'LIKE'},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'content', title: __('Content')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'is_top', title: __('Is_top'), searchList: {"0":__('Is_top 0'),"1":__('Is_top 1')}, formatter: Table.api.formatter.normal},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate, buttons: [

                                {
                                    name: 'user_money_log',
                                    text: __('评论列表'),
                                    title: function (row) {
                                        return '评论列表';
                                    },
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    extend: 'data-area=\'["95%","90%"]\'',
                                    icon: 'fa',
                                    url: 'forumsys/forumcomment/index?fid={row.id}',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                },
                            ]
                        }
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
                url: 'forumsys/forum/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
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
                                    url: 'forumsys/forum/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'forumsys/forum/destroy',
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
