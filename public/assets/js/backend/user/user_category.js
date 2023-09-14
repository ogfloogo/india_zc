define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user_category/index' + location.search,
                    add_url: 'user/user_category/add',
                    edit_url: 'user/user_category/edit',
                    del_url: 'user/user_category/del',
                    multi_url: 'user/user_category/multi',
                    import_url: 'user/user_category/import',
                    table: 'user_category',
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
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'date', title: __('Date'), operate: 'LIKE'},
                        // {field: 'user_id', title: __('User_id')},
                        {field: 'num', title: __('Num')},
                        {field: 'win_num', title: __('Win_num')},
                        {field: 'group_buying_commission', title: __('Group_buying_commission'), operate:'BETWEEN'},
                        {field: 'head_of_the_reward', title: __('Head_of_the_reward'), operate:'BETWEEN'},
                        {field: 'exchangemoney', title: __('Exchangemoney'), operate:'BETWEEN'},
                        {field: 'total_commission', title: __('Total_commission'), operate:'BETWEEN'},
                        {field: 'invite_commission', title: __('Invite_commission'), operate:'BETWEEN'},
                        // {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
