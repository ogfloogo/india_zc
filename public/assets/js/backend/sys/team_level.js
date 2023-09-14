define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'sys/team_level/index' + location.search,
                    add_url: 'sys/team_level/add',
                    edit_url: 'sys/team_level/edit',
                    del_url: 'sys/team_level/del',
                    multi_url: 'sys/team_level/multi',
                    import_url: 'sys/team_level/import',
                    table: 'team_level',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'name', title: __('Name')},
                        {field: 'level', title: __('Level')},
                        {field: 'withdrawal_rate', title: __('Withdrawal_rate'), operate:'BETWEEN'},
                        {field: 'need_num', title: __('Need_num')},
                        {field: 'need_user_recharge', title: __('Need_user_recharge')},
                        {field: 'rate1', title: __('Rate1'), operate:'BETWEEN'},
                        {field: 'rate2', title: __('Rate2'), operate:'BETWEEN'},
                        {field: 'rate3', title: __('Rate3'), operate:'BETWEEN'},
                        {field: 'cash', title: __('Cash'), operate:'BETWEEN'},
                        {field: 'salary', title: __('Salary'), operate:'BETWEEN'},
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
