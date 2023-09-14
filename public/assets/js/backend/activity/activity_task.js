define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'activity/activity_task/index' + location.search,
                    add_url: 'activity/activity_task/add',
                    edit_url: 'activity/activity_task/edit',
                    del_url: 'activity/activity_task/del',
                    multi_url: 'activity/activity_task/multi',
                    import_url: 'activity/activity_task/import',
                    table: 'activity_task',
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
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'name', title: __('Name'), operate: 'LIKE' },
                        { field: 'type', title: __('Type'), searchList: { "0": __('Type 0'), "1": __('Type 1'), "2": __('Type 2'), "3": __('Type 3'), "4": __('Type 4'), "5": __('Type 5'), "6": __('Type 6'), "7": __('Type 7'), "8": __('Type 8'), "9": __('Type 9'), "10": __('Type 10') }, formatter: Table.api.formatter.normal },
                        { field: 'userlevel.name', title: __('Level_id') },
                        { field: 'date_type', title: __('Date_type'), searchList: { "0": __('Date_type 0'), "1": __('Date_type 1') }, formatter: Table.api.formatter.normal },
                        { field: 'num', title: __('Num') },
                        { field: 'is_auto_get', title: __('Is_auto_get'), searchList: { "0": __('Is_auto_get 0'), "1": __('Is_auto_get 1') }, formatter: Table.api.formatter.normal },
                        { field: 'is_auto_prize', title: __('Is_auto_prize'), searchList: { "0": __('Is_auto_prize 0'), "1": __('Is_auto_prize 1') }, formatter: Table.api.formatter.normal },
                        { field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image },
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
                url: 'activity/activity_task/recyclebin' + location.search,
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
                                    url: 'activity/activity_task/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'activity/activity_task/destroy',
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
