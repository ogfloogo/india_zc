define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'robot/user_target/index' + location.search,
                    add_url: 'robot/user_target/add',
                    edit_url: 'robot/user_target/edit',
                    del_url: 'robot/user_target/del',
                    multi_url: 'robot/user_target/multi',
                    import_url: 'robot/user_target/import',
                    table: 'user_target',
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
                        // { field: 'id', title: __('Id') },
                        { field: 'user_id', title: __('用户id') },
                        { field: 'user.mobile', title: __('手机号') },
                        { field: 'user.level', title: __('等级') },
                        { field: 'user.money', title: __('余额') },
                        { field: 'group_number', title: __('团队人数') },

                        { field: 'status', title: __('Status'), searchList: { "0": __('Status 0'), "1": __('Status 1'), "2": __('Status 2') }, formatter: Table.api.formatter.status },
                        // {field: 'robot_num', title: __('Robot_num')},
                        { field: 'addrobottime', title: __('最近robot添加'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'addordertime', title: __('最近订单添加'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate }
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
