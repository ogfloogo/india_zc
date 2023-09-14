define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'agent/user/index',
                    add_url: 'agent/user/add',
                    edit_url: 'agent/user/edit',
                    del_url: 'agent/user/del',
                    multi_url: 'agent/user/multi',
                    table: 'agent',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                columns: [
                    [
                        // { checkbox: true },
                        // { field: 'id', title: __('Id'), sortable: true },
                        // {field: 'username', title: __('Username'), operate: 'LIKE'},
                        // { field: 'sid', title: __('上级用户id') },
                        { field: 'nickname', title: __('Nickname'), operate: 'LIKE' },
                        // {field: 'email', title: __('Email'), operate: 'LIKE'},
                        { field: 'mobile', title: __('Mobile'), operate: 'LIKE' },
                        { field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false },
                        { field: 'level', title: __('Level'), operate: 'BETWEEN', sortable: true },
                        { field: 'sid_name', title: __('上级'), operate: false },
                        { field: 'money', title: __('余额'), sortable: true },
                        { field: 'group_number', title: __('团队人数'), operate: false },

                        { field: 'usertotal.total_recharge', title: __('充值金额'), operate: false, sortable: true },
                        { field: 'usertotal.total_withdrawals', title: __('提现金额'), operate: false, sortable: true },
                        { field: 'usertotal.invite_number', title: __('邀请好友数'), operate: false, sortable: true },
                        { field: 'usertotal.invite_commission', title: __('累计获得邀请奖励'), operate: false, sortable: true },
                        { field: 'usertotal.total_commission', title: __('累计获得佣金'), operate: false, sortable: true },
                        { field: 'first_recharge_time', title: __('首次充值时间'), operate: false },

                        { field: 'invite_code', title: __('邀请码') },

                        { field: 'logintime', title: __('Logintime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true },
                        // { field: 'loginip', title: __('Loginip') },
                        { field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true },
                        // { field: 'joinip', title: __('Joinip') },

                        { field: 'status', title: __('Status'), searchList: { "0": __('Status 0'), "1": __('Status 1') }, formatter: Table.api.formatter.status },
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate, buttons: [
                                // {
                                //     name: 'user_award',
                                //     text: __('好友详情'),
                                //     title: function (row) {
                                //         return row.mobile + ' ' + __('好友详情');
                                //     },
                                //     classname: 'btn btn-xs btn-success btn-dialog',
                                //     extend: 'data-area=\'["95%","90%"]\'',

                                //     icon: 'fa fa-user',
                                //     url: 'user/user_award/index?user_id={row.id}',
                                //     callback: function (data) {
                                //         Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                //     },
                                //     visible: function (row) {
                                //         //返回true时按钮显示,返回false隐藏
                                //         return true;
                                //     }
                                // },
                                // {
                                //     name: 'user_group',
                                //     text: __('团队详情'),
                                //     title: function (row) {
                                //         return row.mobile + ' ' + __('团队详情');
                                //     },
                                //     classname: 'btn btn-xs btn-success btn-dialog',
                                //     extend: 'data-area=\'["95%","90%"]\'',

                                //     icon: 'fa fa-group',
                                //     url: 'user/commission_log/index?user_id={row.id}',
                                //     callback: function (data) {
                                //         Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                //     },
                                //     visible: function (row) {
                                //         //返回true时按钮显示,返回false隐藏
                                //         return true;
                                //     }
                                // }
                                // ,
                                // {
                                //     name: 'user_money_log',
                                //     text: __('余额变动'),
                                //     title: function (row) {
                                //         return row.mobile + ' ' + __('余额变动记录');
                                //     },
                                //     classname: 'btn btn-xs btn-success btn-dialog',
                                //     extend: 'data-area=\'["95%","90%"]\'',

                                //     icon: 'fa',
                                //     url: 'user/user_money_log/index?user_id={row.id}',
                                //     callback: function (data) {
                                //         Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                //     },
                                //     visible: function (row) {
                                //         //返回true时按钮显示,返回false隐藏
                                //         return true;
                                //     }
                                // }
                            ]

                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            table.off('dbl-click-row.bs.table');
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        updatemoney: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {
                user: function (value, row, index) {
                    if (!row.sid) {
                        return '-';
                    }
                    console.log(row)
                    //这里手动构造URL
                    // url = "user/user_award/index/ids/" + row.sid+'&{ids}';
                    url = 'user/user_award/index?user_id={row.sid}',

                        //方式一,直接返回class带有addtabsit的链接,这可以方便自定义显示内容
                        //return '<a href="' + url + '" class="label label-success addtabsit" title="' + __("Search %s", value) + '">' + __('Search %s', value) + '</a>';

                        //方式二,直接调用Table.api.formatter.addtabs
                        this.url = url;
                    // return Fast.api.open(url,__('title'),{area:['880px','550px']}) //area:控制弹窗大小

                    return Table.api.formatter.dialog.call(this, value, row, index);
                },
                level: function (value, row, index) {
                    if (row.is_robot) {
                        return '-';
                    }
                    console.log(row)
                    //这里手动构造URL
                    // url = "user/user_award/index/ids/" + row.sid+'&{ids}';
                    url = 'user/user_level_log/index?user_id={row.id}',

                        //方式一,直接返回class带有addtabsit的链接,这可以方便自定义显示内容
                        //return '<a href="' + url + '" class="label label-success addtabsit" title="' + __("Search %s", value) + '">' + __('Search %s', value) + '</a>';

                        //方式二,直接调用Table.api.formatter.addtabs
                        this.url = url;
                    // return Fast.api.open(url,__('title'),{area:['880px','550px']}) //area:控制弹窗大小

                    return Table.api.formatter.dialog.call(this, value, row, index);
                },
            }
        }
    };
    return Controller;
});