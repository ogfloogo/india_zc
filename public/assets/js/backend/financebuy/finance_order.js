define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'financebuy/finance_order/index' + location.search,
                    add_url: 'financebuy/finance_order/add',
                    // edit_url: 'financebuy/finance_order/edit',
                    // del_url: 'financebuy/finance_order/del',
                    multi_url: 'financebuy/finance_order/multi',
                    import_url: 'financebuy/finance_order/import',
                    table: 'finance_order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                // fixedColumns: true,
                // fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'order_id', title: __('Order_id'), operate: 'LIKE'},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'f_id', title: __('F_id')},
                        {field: 'project_id', title: __('Project_id')},
                        {field: 'is_robot', title: __('Is_robot'), searchList: {"1":__('Is_robot 1'),"0":__('Is_robot 0')}, formatter: Table.api.formatter.normal},
                        // {field: 'buy_number', title: __('Buy_number')},
                        {field: 'buy_time', title: __('Buy_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'popularize', title: __('Popularize'), searchList: {"0":__('Popularize 0'),"1":__('Popularize 1'),"2":__('Popularize 2')}, formatter: Table.api.formatter.normal},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'capital', title: __('Capital'), operate:'BETWEEN'},
                        {field: 'interest', title: __('Interest'), operate:'BETWEEN'},
                        {field: 'buy_rate', title: __('Buy_rate')},
                        {field: 'earnings', title: __('Earnings'), operate:'BETWEEN'},
                        {field: 'num', title: __('Num')},
                        {field: 'surplus_num', title: __('Surplus_num')},
                        {field: 'collection_time', title: __('Collection_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'earning_start_time', title: __('Earning_start_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'earning_end_time', title: __('Earning_end_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
