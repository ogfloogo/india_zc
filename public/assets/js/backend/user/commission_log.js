define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/commission_log/index' + location.search,
                    add_url: 'user/commission_log/add',
                    edit_url: 'user/commission_log/edit',
                    del_url: 'user/commission_log/del',
                    multi_url: 'user/commission_log/multi',
                    import_url: 'user/commission_log/import',
                    table: 'commission_log',
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
                        // {field: 'id', title: __('Id')},
                        { field: 'from_user.id', title: __('用户ID'),formatter: Controller.api.formatter.user},  //
                        { field: 'from_user.mobile', title: __('手机号') },
                        { field: 'from_user.joinip', title: __('注册IP') },
                        { field: 'from_user.loginip', title: __('登录IP') },
                        { field: 'from_user.level', title: __('成员会员等级') },
                        { field: 'to_user.level', title: __('团长会员等级') },
                        { field: 'commission_log.level', title: __('团队等级'), visible: false },
                        { field: 'order.amount', title: __('消费金额'), operate: false },
                        { field: 'commission_fee', title: __('佣金比例'), operate: false },
                        { field: 'commission', title: __('Commission'), operate: false },
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        // {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
                url: 'user/commission_log/recyclebin' + location.search,
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
                                    url: 'user/commission_log/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'user/commission_log/destroy',
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {
                user: function (value, row, index) {
                    console.log(row)
                    //这里手动构造URL
                    url = "user/user/edit/ids/" + row.from_id+'&{ids}';

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
