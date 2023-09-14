define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'financebuy/finance_rate/index' + location.search,
                    add_url: 'financebuy/finance_rate/add?finance_id=' + finance_id,
                    edit_url: 'financebuy/finance_rate/edit',
                    del_url: 'financebuy/finance_rate/del',
                    multi_url: 'financebuy/finance_rate/multi',
                    import_url: 'financebuy/finance_rate/import',
                    table: 'finance_rate',
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
                        {field: 'finance_id', title: __('Finance_id')},
                        {field: 'start', title: __('Start')},
                        {field: 'end', title: __('End')},
                        {field: 'rate', title: __('Rate')},
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
