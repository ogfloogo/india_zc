define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'financebuy/popularize_award/index' + location.search,
                    add_url: 'financebuy/popularize_award/add',
                    edit_url: 'financebuy/popularize_award/edit',
                    del_url: 'financebuy/popularize_award/del',
                    multi_url: 'financebuy/popularize_award/multi',
                    import_url: 'financebuy/popularize_award/import',
                    table: 'popularize_award',
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
                        {field: 'f_id', title: __('F_id')},
                        {field: 'project_id', title: __('Project_id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'received', title: __('Received'), operate:'BETWEEN'},
                        {field: 'not_claimed', title: __('Not_claimed'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'num', title: __('Num')},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
