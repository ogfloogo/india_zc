define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'financebuy/finance_issue/index' + location.search,
                    add_url: 'financebuy/finance_issue/add',
                    edit_url: 'financebuy/finance_issue/edit',
                    del_url: 'financebuy/finance_issue/del',
                    multi_url: 'financebuy/finance_issue/multi',
                    import_url: 'financebuy/finance_issue/import',
                    table: 'finance_issue',
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
                        { field: 'finance.name', title: __('Finance_id') },
                        { field: 'name', title: __('Name'), operate: 'LIKE' },
                        { field: 'day', title: __('Day') },
                        { field: 'presell_start_time', title: __('Presell_start_time'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'presell_end_time', title: __('Presell_end_time'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'start_time', title: __('Start_time'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'end_time', title: __('End_time'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'status', title: __('Status'), searchList: { "0": __('Status 0'), "1": __('Status 1'), "2": __('Status 2') }, formatter: Table.api.formatter.status },
                        { field: 'weigh', title: __('Weigh'), operate: false},
                        { field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        { field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime },
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate, buttons: [
                                {
                                    name: 'finance_order',
                                    text: __('理财订单列表'),
                                    title: function (row) {
                                        return row.name + ' ' + __('理财订单列表');
                                    },
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    extend: 'data-area=\'["95%","90%"]\'',

                                    icon: 'fa ',
                                    url: 'financebuy/finance_order/index?finance_id={row.finance_id}',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                }]
                        }]
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
                url: 'financebuy/finance_issue/recyclebin' + location.search,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'name', title: __('Name'), align: 'left' },
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
                                    url: 'financebuy/finance_issue/restore',
                                    refresh: true
                                },
                                {
                                    name: 'Destroy',
                                    text: __('Destroy'),
                                    classname: 'btn btn-xs btn-danger btn-ajax btn-destroyit',
                                    icon: 'fa fa-times',
                                    url: 'financebuy/finance_issue/destroy',
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
            $("#c-finance_id").change(function (data) {
                console.log($("#c-finance_id").val())
                $.ajax('financebuy/finance/info?ids='
                    + $('#c-finance_id').val()).done(function (data) {
                        console.log(data.code);
                        if (data.code) {
                            var presell_day = data.data.presell_day;
                            var day = data.data.day;
                            $('#c-day').val(data.data.day);
                            console.log(day)
                            console.log(presell_day)
                            let pStartDate = new Date($('#c-presell_start_time').val());
                            pEndDate = pStartDate.setDate(pStartDate.getDate() + presell_day);
                            endDate = pStartDate.setDate(pStartDate.getDate() + day);
                            // dateTime = new Date(dateTime);
                            $('#c-presell_end_time').val(Moment(parseInt(pEndDate)).format("YYYY-MM-DD HH:mm:ss"));
                            // $('#c-start_time').val(Moment(parseInt(pEndDate + 1000)).format("YYYY-MM-DD HH:mm:ss"));
                            console.log(Moment(parseInt(pEndDate + 86400000)).format("YYYY-MM-DD") + " 00:00:00");
                            $('#c-start_time').val(Moment(parseInt(pEndDate + 86400000)).format("YYYY-MM-DD 00:00:00"));
                            $('#c-end_time').val(Moment(parseInt(endDate)).format("YYYY-MM-DD") + " 23:59:59");
                        } else {
                            alert(data.msg);
                            return;
                        }
                    });
            });
            Controller.api.bindevent();
        },
        edit: function () {
            $("#c-finance_id").change(function (data) {
                console.log($("#c-finance_id").val())
                $.ajax('financebuy/finance/info?ids='
                    + $('#c-finance_id').val()).done(function (data) {
                        console.log(data.code);
                        if (data.code) {
                            var presell_day = data.data.presell_day;
                            var day = data.data.day;
                            $('#c-day').val(data.data.day);
                            console.log(day)
                            console.log(presell_day)
                            let pStartDate = new Date($('#c-presell_start_time').val());
                            pEndDate = pStartDate.setDate(pStartDate.getDate() + presell_day);
                            endDate = pStartDate.setDate(pStartDate.getDate() + day);
                            // dateTime = new Date(dateTime);
                            $('#c-presell_end_time').val(Moment(parseInt(pEndDate)).format("YYYY-MM-DD HH:mm:ss"));
                            // $('#c-start_time').val(Moment(parseInt(pEndDate + 1000)).format("YYYY-MM-DD HH:mm:ss"));
                            // $('#c-start_time').val(Moment(parseInt(pEndDate)).format("YYYY-MM-DD HH:mm:ss"));
                            // $('#c-end_time').val(Moment(parseInt(endDate)).format("YYYY-MM-DD HH:mm:ss"));
                            $('#c-start_time').val(Moment(parseInt(pEndDate + 86400000)).format("YYYY-MM-DD 00:00:00"));
                            $('#c-end_time').val(Moment(parseInt(endDate)).format("YYYY-MM-DD") + " 23:59:59");
                        } else {
                            alert(data.msg);
                            return;
                        }
                    });
            });
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
