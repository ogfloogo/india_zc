define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/all_user_money_log/index' + location.search,
                    add_url: 'user/all_user_money_log/add',
                    edit_url: 'user/all_user_money_log/edit',
                    del_url: 'user/all_user_money_log/del',
                    multi_url: 'user/all_user_money_log/multi',
                    import_url: 'user/all_user_money_log/import',
                    table: 'user_money_log',
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
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'user_id', title: __('User_id') },
                        { field: 'user.mobile', title: __('Mobile') },
                        { field: 'user.money', title: __('当前余额'), operate: false },
                        {
                            field: 'money', title: __('Money'), operate: false, formatter: function (value, row) {
                                return (row.mold == 'dec' ? '-' : '+') + value;
                            }
                        },
                        { field: 'before', title: __('Before'), operate: false },
                        { field: 'after', title: __('After'), operate: false },
                        {
                            field: 'type', title: __('Type'), searchList: {
                                "1": __('Type 1'), "2": __('Type 2'), "3": __('Type 3'), "4": __('Type 4'), "5": __('Type 5'), "6": __('Type 6'), "7": __('Type 7'), "8": __('Type 8'), "9": __('Type 9'), "10": __('Type 10'), "11": __('Type 11'), "12": __('Type 12'), '13': __('Type 13'), '14': __('Type 14'), '18': __('Type 18'), '19': __('Type 19')
                                , '20': __('Type 20'), '21': __('Type 21'), '22': __('Type 22')
                            }, formatter: Table.api.formatter.normal
                        },
                        { field: 'mold', title: __('Mold'), operate: false },
                        { field: 'remark', title: __('Remark'), operate: 'LIKE' },
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
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
