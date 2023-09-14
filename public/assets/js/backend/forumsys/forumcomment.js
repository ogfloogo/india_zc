define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'forumsys/forumcomment/index' + location.search,
                    add_url: 'forumsys/forumcomment/add',
                    edit_url: 'forumsys/forumcomment/edit',
                    del_url: 'forumsys/forumcomment/del',
                    multi_url: 'forumsys/forumcomment/multi',
                    import_url: 'forumsys/forumcomment/import',
                    table: 'forum_comment',
                    dragsort_url:""
                }
            });

            var table = $("#table");

            $(document).on('click', '.btn-pass', function () {
                var ids = Table.api.selectedids(table);
                var url = 'forumsys/forumcomment/pass/ids/'+ids;
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
                var url = 'forumsys/forumcomment/refuse/ids/'+ids;
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
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'fid', title: __('Fid')},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
                url: 'forumsys/forumcomment/recyclebin' + location.search,
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
                                    url: 'forumsys/forumcomment/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'forumsys/forumcomment/destroy',
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
