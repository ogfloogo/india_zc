const { data } = require("jquery");

define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var chartCash = Echarts.init(document.getElementById('echart-cash'), 'walden');
            var chartReg = Echarts.init(document.getElementById('echart-reg'), 'walden');
            var chartUser = Echarts.init(document.getElementById('echart-user'), 'walden');
            console.log(Config.chart_data.date);

            var option1 = {
                title: {
                    text: '充值记录'
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },
                legend: {
                    top: '10%'
                },
                grid: {
                    top: '20%',
                    left: '10%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: [
                    {
                        type: 'category',
                        data: Config.chart_data.date,
                        splitLine: { show: false },

                        axisTick: {
                            alignWithLabel: true
                        }
                    }
                ],
                yAxis: [
                    {
                        type: 'value',
                        boundaryGap: [0, 0.01]
                    }
                ],
                series: [
                    // {
                    //     name: '充值金额',
                    //     type: 'bar',

                    //     label: {
                    //         show: true,
                    //         position: 'top'
                    //     },
                    //     data: Config.chart_data.recharge,
                    //     color: 'rgb(75,134,232)',
                    // },
                    // {
                    //     name: '提现金额',
                    //     type: 'bar',
                    //     color: 'rgb(94,187,195)',

                    //     label: {
                    //         show: true,
                    //         position: 'top'
                    //     },
                    //     data: Config.chart_data.withdraw
                    // }
                    {
                        name: '充值人数',
                        type: 'bar',

                        label: {
                            show: true,
                            position: 'top'
                        },
                        data: Config.chart_data.re_user,
                        color: 'rgb(75,134,232)',
                    },
                    {
                        name: '首充人数',
                        type: 'bar',
                        color: 'rgb(94,187,195)',

                        label: {
                            show: true,
                            position: 'top'
                        },
                        data: Config.chart_data.first
                    }
                ]
            };

            // 使用刚指定的配置项和数据显示图表。
            chartCash.setOption(option1);
            var option2 = {
                title: {
                    text: '注册登录记录'
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },
                legend: {
                    top: '10%'
                },
                grid: {
                    top: '20%',
                    left: '10%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: [
                    {
                        type: 'category',
                        data: Config.chart_data.date,
                        splitLine: { show: false },

                        axisTick: {
                            alignWithLabel: true
                        }
                    }
                ],
                yAxis: [
                    {
                        type: 'value',
                        boundaryGap: [0, 0.01]
                    }
                ],
                series: [
                    {
                        name: '注册人数',
                        type: 'bar',
                        data: Config.chart_data.reg,
                        color: 'rgb(222,117,94)',
                        label: {
                            show: true,
                            position: 'top'
                        },
                    },
                    {
                        name: '登录人数',
                        type: 'bar',
                        data: Config.chart_data.user,
                        color: 'rgb(230,197,125)',

                        label: {
                            show: true,
                            position: 'top'
                        },
                    }
                ]
            };
            chartReg.setOption(option2);
            var option3 = {
                title: {
                    text: '会员等级人数统计'
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },

                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: [
                    {
                        type: 'category',
                        splitLine: { show: false },

                        data: Config.level_data.level,
                        axisTick: {
                            alignWithLabel: true
                        }
                    }
                ],
                yAxis: [
                    {
                        type: 'value',

                    }
                ],
                series: [
                    {
                        name: '会员人数',
                        type: 'bar',
                        data: Config.level_data.user,
                        color: 'rgb(75,134,232)',
                        barWidth: '30%',
                        label: {
                            show: true,
                            position: 'top'
                        },
                    }
                ]
            };
            chartUser.setOption(option3);

            $(window).resize(function () {
                chartCash.resize();
                chartReg.resize();
                chartUser.resize();
            });

            $(document).on("click", ".btn-refresh", function () {
                setTimeout(function () {
                    chartCash.resize();
                    chartReg.resize();
                    chartUser.resize();
                }, 0);
            });

        }
    };

    return Controller;
});
