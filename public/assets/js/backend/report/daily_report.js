define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'report/daily_report/index' + location.search,
                    add_url: 'report/daily_report/add',
                    edit_url: 'report/daily_report/edit',
                    del_url: 'report/daily_report/del',
                    multi_url: 'report/daily_report/multi',
                    import_url: 'report/daily_report/import',
                    table: 'daily_report',
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
                        // {field: 'id', title: __('Id')},
                        {field: 'date', title: __('Date')},
                        {field: 'first_recharge_num', title: __('First_recharge_num')},
                        {field: 'first_recharge_amount', title: __('First_recharge_amount'), operate:'BETWEEN'},
                        {field: 'recharge_num_less_than', title: __('Recharge_num_less_than')},
                        {field: 'recharge_num_amount_less_than', title: __('Recharge_num_amount_less_than'), operate:'BETWEEN'},
                        {field: 'first_withdraw_num', title: __('First_withdraw_num')},
                        {field: 'first_withdraw_amount', title: __('First_withdraw_amount'), operate:'BETWEEN'},
                        {field: 'withdraw_num_less_than', title: __('Withdraw_num_less_than')},
                        {field: 'withdraw_num_amount_less_than', title: __('Withdraw_num_amount_less_than'), operate:'BETWEEN'},
                        {field: 'level_up_num', title: __('Level_up_num')},
                        {field: 'level_up_recharge_amount', title: __('Level_up_recharge_amount'), operate:'BETWEEN'},
                        {field: 'level_down_num', title: __('Level_down_num')},
                        {field: 'level_down_withdraw_amount', title: __('Level_down_withdraw_amount'), operate:'BETWEEN'},
                        {field: 'order_award_amount', title: __('Order_award_amount'), operate:'BETWEEN'},
                        {field: 'level_1_commission', title: __('Level_1_commission'), operate:'BETWEEN'},
                        {field: 'level_2_commission', title: __('Level_2_commission'), operate:'BETWEEN'},
                        {field: 'level_3_commission', title: __('Level_3_commission'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
