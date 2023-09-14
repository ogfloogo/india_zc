define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'sys/check_report/index' + location.search,
                    add_url: 'sys/check_report/add',
                    edit_url: 'sys/check_report/edit',
                    del_url: 'sys/check_report/del',
                    multi_url: 'sys/check_report/multi',
                    import_url: 'sys/check_report/import',
                    table: 'check_report',
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
                        { field: 'date', title: __('Date') },
                        { field: 'user_id', title: __('User_id'), operate: 'LIKE'  },
                        { field: 'mobile', title: __('Mobile'), operate: 'LIKE' },
                        { field: 'money', title: __('Money'), operate: false },
                        { field: 'cal_new', title: __('Cal'), operate: false },
                        { field: 'diff_new', title: __('Diff'), operate: false, sortable: true },
                        { field: 'diff_log_ids', title: __('差异记录id'), operate: false },
                        { field: 'order_amount', title: __('下单总额'), operate: false },
                        { field: 'order_back', title: __('下单返回'), operate: false },
                        { field: 'group', title: __('Group'), operate: false },
                        { field: 'head', title: __('Head'), operate: false },
                        { field: 'commission', title: __('Commission'), operate: false },
                        { field: 'invite', title: __('Invite'), operate: false },
                        { field: 'new_login', title: __('New_login'), operate: false },
                        { field: 'withdraw_waiting', title: __('Withdraw_waiting'), operate: false },
                        { field: 'withdraw', title: __('Withdraw'), operate: false },
                        { field: 'recharge', title: __('Recharge'), operate: false },
                        { field: 'admin_inc', title: __('Admin_inc'), operate: false },
                        { field: 'admin_dec', title: __('Admin_dec'), operate: false },
                        { field: 'cal', title: __('Cal'), operate: false },
                        { field: 'diff', title: __('Diff'), operate: false, sortable: true },
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate, buttons: [
                                {
                                    name: 'recal',
                                    text: __('重算'),
                                    title: function (row) {
                                        return row.user_id + ' ' + __('重算');
                                    },
                                    classname: 'btn btn-xs btn-warning btn-success btn-ajax',
                                    extend: 'data-area=\'["95%","90%"]\'',

                                    icon: 'fa',
                                    url: 'sys/check_report/recal?user_id={row.user_id}',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                },{
                                    name: 'checklog',
                                    text: __('检验'),
                                    title: function (row) {
                                        return row.user_id + ' ' + __('检验日志');
                                    },
                                    classname: 'btn btn-xs btn-info btn-success btn-ajax',
                                    extend: 'data-area=\'["95%","90%"]\'',

                                    icon: 'fa',
                                    url: 'sys/check_report/checklog?user_id={row.user_id}',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                },
                                {
                                    name: 'user_money_log',
                                    text: __('余额变动'),
                                    title: function (row) {
                                        return row.mobile + ' ' + __('余额变动记录');
                                    },
                                    classname: 'btn btn-xs  btn-success btn-dialog',
                                    extend: 'data-area=\'["95%","90%"]\'',

                                    icon: 'fa',
                                    url: 'user/user_money_log/index?user_id={row.user_id}',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                }]
                        }
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
