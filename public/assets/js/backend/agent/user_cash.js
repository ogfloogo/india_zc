define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'agent/user_cash/index' + location.search,
                    add_url: 'agent/user_cash/add',
                    edit_url: 'agent/user_cash/edit',
                    del_url: 'agent/user_cash/del',
                    multi_url: 'agent/user_cash/multi',
                    import_url: 'agent/user_cash/import',
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
                        { field: 'user.mobile', title: __('手机号') },                        
                        { field: 'user_recharge', title: __('充值总额'), operate: false },
                        { field: 'total_withdrawals', title: __('提现总额'), operate: false },
                        { field: 'price', title: __('Price'), operate: false },
                        { field: 'trueprice', title: __('Trueprice'), operate: false },
                        { field: 'after_money', title: __('After_money'), operate: false },
                        { field: 'channel', title: __('Channel'), operate: 'LIKE' },
                        { field: 'status', title: __('Status'), searchList: { "0": __('Status 0'), "1": __('Status 1'), "2": __('Status 2'), "3": __('Status 3'), "4": __('Status 4'), "5": __('Status 5') }, formatter: Table.api.formatter.status },

                        { field: 'order_id', title: __('Order_id'), operate: 'LIKE' },
                        { field: 'username', title: __('Username'), operate: 'LIKE' },
                        { field: 'bankname', title: __('Bankname'), operate: 'LIKE' },
                        { field: 'bankcard', title: __('Bankcard'), operate: 'LIKE' },
                        { field: 'bankcode', title: __('Bankcode'), operate: 'LIKE' },
                        { field: 'ifsc', title: __('Ifsc'), operate: 'LIKE' },                        
                        { field: 'type', title: __('Type'), searchList: { "1": __('Type 1'), "2": __('Type 2') }, formatter: Table.api.formatter.normal },
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        
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
                url: 'agent/user_cash/recyclebin' + location.search,
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
                                    url: 'agent/user_cash/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'agent/user_cash/destroy',
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
