define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'finance/user_cash/index' + location.search,
                    add_url: 'finance/user_cash/add',
                    edit_url: 'finance/user_cash/edit',
                    del_url: 'finance/user_cash/del',
                    multi_url: 'finance/user_cash/multi',
                    import_url: 'finance/user_cash/import',
                    table: 'user_cash',
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
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'user_id', title: __('User_id'), formatter: Controller.api.formatter.user },
                        { field: 'user.mobile', title: __('手机号') },
                        {
                            field: 'user.jointime', title: __('注册时间'), formatter(value, row, index) {
                                return new Date(parseInt(value) * 1000).toLocaleString();
                                return new Date(parseInt(value) * 1000).toLocaleString().replace(/:\d{1,2}$/, ' ');
                            },
                        },
                        { field: 'sid_name', title: __('上级'), operate: false },
                        { field: 'user_recharge', title: __('充值总额'), operate: false },
                        { field: 'total_withdrawals', title: __('提现总额'), operate: false },
                        { field: 'price', title: __('Price'), operate: false },
                        { field: 'trueprice', title: __('Trueprice'), operate: false },
                        { field: 'after_money', title: __('After_money'), operate: false },
                        { field: 'ip', title: __('Ip'), operate: 'LIKE' },
                        { field: 'channel', title: __('Channel'), operate: 'LIKE' },
                        { field: 'status', title: __('Status'), searchList: { "0": __('Status 0'), "1": __('Status 1'), "2": __('Status 2'), "3": __('Status 3'), "4": __('Status 4'), "5": __('Status 5'), "6": __('Status 6') }, formatter: Table.api.formatter.status },

                        { field: 'order_id', title: __('Order_id'), operate: 'LIKE' },
                        { field: 'username', title: __('Username'), operate: 'LIKE' },
                        { field: 'bankname', title: __('Bankname'), operate: 'LIKE' },
                        { field: 'bankcard', title: __('Bankcard'), operate: 'LIKE' },
                        { field: 'bankcode', title: __('Bankcode'), operate: 'LIKE' },
                        { field: 'order_no', title: __('Order_no'), operate: 'LIKE' },
                        { field: 'ifsc', title: __('Ifsc'), operate: 'LIKE' },
                        { field: 'phone', title: __('Phone'), operate: 'LIKE' },
                        { field: 'email', title: __('Email'), operate: 'LIKE' },
                        { field: 'address', title: __('Address'), operate: 'LIKE' },
                        { field: 'upi', title: __('Upi'), operate: 'LIKE' },
                        { field: 'cpf', title: __('Cpf'), operate: 'LIKE' },
                        { field: 'content', title: __('Content'), operate: 'LIKE' },
                        { field: 'type', title: __('Type'), searchList: { "1": __('Type 1'), "2": __('Type 2') }, formatter: Table.api.formatter.normal },
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate, buttons: [

                                {
                                    name: 'user_money_log',
                                    text: __('变动记录'),
                                    title: function (row) {
                                        return row.username + ' ' + __('变动记录');
                                    },
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    extend: 'data-area=\'["95%","90%"]\'',

                                    icon: 'fa',
                                    url: 'user/user_money_log/index?user_id={row.user_id}&ids={row.user_id}',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                },

                                {
                                    name: 'doPay',
                                    text: __('提交'),
                                    title: __('提交'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa ',
                                    url: 'finance/user_cash/doPay?id={row.id}',
                                    // confirm: '确认提交？',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        console.log(data, ret);
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        if (row.status == 1) {
                                            return true;
                                        }
                                        return false;
                                    }
                                },
                                {
                                    name: 'passandpay',
                                    text: __('通过并提交'),
                                    title: __('通过并提交'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa ',
                                    url: 'finance/user_cash/passAndPay?id={row.id}',
                                    // confirm: '确认通过并提交？',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        console.log(data, ret);
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        if (row.status == 0) {
                                            return true;
                                        }
                                        return false;
                                    }
                                },
                                // {
                                //     name: 'reject',
                                //     text: __('拒绝'),
                                //     title: __('拒绝'),
                                //     classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                //     icon: 'fa ',
                                //     url: 'finance/user_cash/reject?id={row.id}',
                                //     confirm: '确认拒绝？',
                                //     success: function (data, ret) {
                                //         $(".btn-refresh").trigger("click");
                                //         // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));                                        //如果需要阻止成功提示，则必须使用return false;
                                //         //return false;
                                //     },
                                //     error: function (data, ret) {
                                //         console.log(data, ret);
                                //         Layer.alert(ret.msg);
                                //         return false;
                                //     },
                                //     visible: function (row) {
                                //         //返回true时按钮显示,返回false隐藏
                                //         if (row.status == 0) {
                                //             return true;
                                //         }
                                //         return false;
                                //     }
                                // },
                                {
                                    name: 'reject',
                                    text: __('  拒  绝  '),
                                    title: __('  拒  绝  '),
                                    classname: 'btn btn-xs btn-danger btn-dialog',
                                    extend: 'data-area=\'["40%","50%"]\'',

                                    icon: 'fa',
                                    url: 'finance/user_cash/reject?id={row.id}',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        if (row.status == 0 || row.status == 1 || row.status == 4) {
                                            return true;
                                        }
                                        return false;
                                    }
                                }
                                ,
                                {
                                    name: 'pass',
                                    text: __('不提'),
                                    title: __('不提'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                    icon: 'fa ',
                                    url: 'finance/user_cash/pass?id={row.id}',
                                    // confirm: '确认通过（不提交）？',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        console.log(data, ret);
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        if (row.status == 0) {
                                            return true;
                                        }
                                        return false;
                                    }
                                },
                                {
                                    name: 'mockpass',
                                    text: __('模拟通过'),
                                    title: __('模拟通过'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa ',
                                    url: 'finance/user_cash/mockpass?id={row.id}',
                                    // confirm: '确认通过（不提交）？',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        console.log(data, ret);
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        if (row.status == 0) {
                                            return true;
                                        }
                                        return false;
                                    }
                                },
                                {
                                    name: 'refreshOrderNo',
                                    text: __('刷新单号'),
                                    title: __('刷新单号'),
                                    classname: 'btn btn-xs btn-link btn-ajax',
                                    extend: 'data-area=\'["40%","50%"]\'',
                                    confirm: '确定刷新订单号？',
                                    icon: 'fa',
                                    url: 'finance/user_cash/refreshOrderNo?id={row.id}',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                        // Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        if (row.status == 4) {
                                            return true;
                                        }
                                        return false;
                                    }
                                }
                            ]
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        recyclebin: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    'dragsort_url': ''
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: 'finance/user_cash/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        {
                            field: 'deletetime',
                            title: __('Deletetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            width: '130px',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'Restore',
                                    text: __('Restore'),
                                    classname: 'btn btn-xs btn-info btn-ajax btn-restoreit',
                                    icon: 'fa fa-rotate-left',
                                    url: 'finance/user_cash/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'finance/user_cash/destroy',
                                    refresh: true
                                }
                            ],
                            formatter: Table.api.formatter.operate
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
        reject: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {
                user: function (value, row, index) {
                    console.log(row)
                    //这里手动构造URL
                    url = "user/user/edit/ids/" + row.user_id + '&{ids}';

                    //方式一,直接返回class带有addtabsit的链接,这可以方便自定义显示内容
                    //return '<a href="' + url + '" class="label label-success addtabsit" title="' + __("Search %s", value) + '">' + __('Search %s', value) + '</a>';

                    //方式二,直接调用Table.api.formatter.addtabs
                    this.url = url;
                    // return Fast.api.open(url,__('title'),{area:['880px','550px']}) //area:控制弹窗大小

                    return Table.api.formatter.dialog.call(this, value, row, index);
                }
            }
        }
    };
    return Controller;
});
