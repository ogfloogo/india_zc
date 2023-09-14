define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'sys/report/index' + location.search,
                    add_url: 'sys/report/add',
                    edit_url: 'sys/report/edit',
                    del_url: 'sys/report/del',
                    multi_url: 'sys/report/multi',
                    import_url: 'sys/report/import',
                    table: 'report',
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
                        {field: 'user', title: __('User')},
                        {field: 'rechargeuser', title: __('Rechargeuser')},
                        {field: 'order', title: __('Order')},
                        {field: 'ordermoney', title: __('Ordermoney'), operate:'BETWEEN'},
                        {field: 'rewardmoney', title: __('Rewardmoney'), operate:'BETWEEN'},
                        {field: 'rechargeorder', title: __('Rechargeorder')},
                        {field: 'recharge', title: __('Recharge'), operate:'BETWEEN'},
                        {field: 'cash', title: __('Cash'), operate:'BETWEEN'},
                        {field: 'send', title: __('Send'), operate:'BETWEEN'},
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
