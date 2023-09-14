define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'financebuy/finance_project/index?f_id=' + Config.f_id + '&popularize=' + Config.popularize,
                    add_url: 'financebuy/finance_project/add?f_id=' + Config.f_id + '&popularize=' + Config.popularize,
                    edit_url: 'financebuy/finance_project/edit',
                    // del_url: 'financebuy/finance_project/del',
                    multi_url: 'financebuy/finance_project/multi',
                    import_url: 'financebuy/finance_project/import',
                    table: 'finance_project',
                }
            });

            var table = $("#table");

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
                        {field: 'level.name', title: __('Buy_level')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        // {field: 'user_min_buy', title: __('User_min_buy'), operate:'BETWEEN'},
                        // {field: 'user_max_buy', title: __('User_max_buy'), operate:'BETWEEN'},
                        {field: 'fixed_amount', title: __('Fixed_amount'), operate:'BETWEEN'},
                        {field: 'rate', title: __('Rate'), operate:'BETWEEN'},
                        {field: 'day', title: __('Day')},
                        {field: 'label_name', title: __('标签'),operate: false},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'is_new_hand', title: __('Is_new_hand'), searchList: {"1":__('Is_new_hand 1'),"0":__('Is_new_hand 0')}, formatter: Table.api.formatter.status},
                        {field: 'capital', title: __('Capital'), operate:'BETWEEN'},
                        {field: 'interest', title: __('Interest'), operate:'BETWEEN'},
                        {field: 'f_id', title: __('F_id')},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.normal},
                        {field: 'popularize', title: __('Popularize'), searchList: {"0":__('Popularize 0'),"1":__('Popularize 1'),"2":__('Popularize 2')}, formatter: Table.api.formatter.normal},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'robot_status', title: __('Robot_status'), searchList: {"1":__('Robot_status 1'),"0":__('Robot_status 0')}, formatter: Table.api.formatter.status},
                        {field: 'robot_addorder_time_start', title: __('Robot_addorder_time_start')},
                        {field: 'robot_addorder_time_end', title: __('Robot_addorder_time_end')},
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
