define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'sys/app_version/index' + location.search,
                    add_url: 'sys/app_version/add',
                    edit_url: 'sys/app_version/edit',
                    del_url: 'sys/app_version/del',
                    multi_url: 'sys/app_version/multi',
                    import_url: 'sys/app_version/import',
                    table: 'app_version',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'system', title: __('System'), searchList: {"1":__('System 1'),"2":__('System 2')}, formatter: Table.api.formatter.normal},
                        // {field: 'channel', title: __('Channel'), searchList: {"0":__('Channel 0'),"1":__('Channel 1')}, formatter: Table.api.formatter.normal},
                        {field: 'number', title: __('Number'), operate: 'LIKE'},
                        {field: 'update_type', title: __('Update_type'), searchList: {"1":__('Update_type 1'),"2":__('Update_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'download_type_and', title: __('Download_type_and'), searchList: {"1":__('Download_type_and 1'),"2":__('Download_type_and 2')}, formatter: Table.api.formatter.normal},
                        {field: 'update_url_and_file', title: __('Update_url_and_file'), operate: false, formatter: Table.api.formatter.file},
                        {field: 'download_type_ios', title: __('Download_type_ios'), searchList: {"1":__('Download_type_ios 1'),"2":__('Download_type_ios 2')}, formatter: Table.api.formatter.normal},
                        {field: 'update_url_ios_file', title: __('Update_url_ios_file'), operate: false, formatter: Table.api.formatter.file},
                        {field: 'download_type_wgt', title: __('Download_type_wgt'), searchList: {"1":__('Download_type_wgt 1'),"2":__('Download_type_wgt 2')}, formatter: Table.api.formatter.normal},
                        {field: 'update_url_wgt_file', title: __('Update_url_wgt_file'), operate: false, formatter: Table.api.formatter.file},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
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
                url: 'sys/app_version/recyclebin' + location.search,
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
                                    url: 'sys/app_version/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'sys/app_version/destroy',
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
