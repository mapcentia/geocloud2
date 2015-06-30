<!DOCTYPE html>
<html lang="en-us">
<head>
    <title>Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Store geographical data and make online maps"/>
    <meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC"/>
    <meta name="author" content="Martin Hoegh"/>

    <!--[if lt IE 9]>
    <script src="https://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <link href="/css/banner-ie.css" rel="stylesheet">
    <![endif]-->

    <script src="http://code.jquery.com/jquery-2.1.4.min.js"></script>


    <!-- Elasticsearch -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/elasticsearch/5.0.0/elasticsearch.jquery.min.js"></script>

    <!-- HighCharts -->
    <script src="http://code.highcharts.com/highcharts.js"></script>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
    <!--<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">-->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>

    <script src="/js/jquery-placeholder/jquery.placeholder.js"></script>
    <script src="/js/jqote2/jquery.jqote2.js"></script>

    <link href="/js/bootstrap3/css/bootstrap.css" rel="stylesheet">
    <link href="/css/banner.css" rel="stylesheet">
    <link rel="StyleSheet" href="/css/proximanova.css" type="text/css"/>
    <script>
        $(function () {
            $('input, textarea').placeholder();

        });
    </script>
    <script type="text/template" id="widgetTemplate">


                <div class="btn-toolbar pull-right" role="toolbar" aria-label="" style="margin-top: 15px">
                    <div class="btn-group" data-toggle="buttons">
                        <button type="button" class="refresh btn btn-xs btn-default">
                            <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
                            Refresh
                        </button>
                        <label class="btn btn-xs btn-default">
                            <input type="checkbox" class="auto-refresh" autocomplete="off"> Auto (5s)
                        </label>
                    </div>
                    <div class="btn-group" data-toggle="buttons">
                        <label class="btn btn-xs btn-default active">
                            <input class="range" type="radio" value="hour" checked /> Hour
                        </label>
                        <label class="btn btn-xs btn-default">
                            <input class="range" type="radio" value="day" /> Day
                        </label>
                        <label class="btn btn-xs btn-default">
                            <input class="range" type="radio" value="week" /> Week
                        </label>
                        <label class="btn btn-xs btn-default">
                            <input class="range" type="radio" value="month" /> Month
                        </label>
                    </div>
                </div>
                <div class="graph" style="height:360px;width:758px"></div>


    </script>
    <script>
        /* eslint-env browser */
        /* global jQuery */

        (function( $ ) {

            var AUTODELAY = 5000;
            var ID = 0;

            function makeElasticSearchParams(range, pattern) {
                var subtract;
                var interval;
                switch (range) {
                    case 'hour':
                        subtract = '1h';
                        interval = 'minute';
                        break;
                    case 'day':
                        subtract = '1d';
                        interval = 'hour';
                        break;
                    case 'week':
                        subtract = '1w';
                        interval = 'day';
                        break;
                    case 'month':
                        subtract = '1M';
                        interval = 'day';
                        break;
                    default:
                        throw 'Unkown range type';
                }

                return {
                    index: 'logstash-*',
                    body: {
                        'query': {
                            'filtered': {
                                'query': {
                                    "query_string" : {
                                        "default_field" : "request",
                                        "default_operator": "AND",
                                        "query" : pattern
                                    }
                                },
                                'filter': {
                                    'range': {
                                        '@timestamp': {
                                            'gte': 'now-' + subtract,
                                            'lte': 'now'
                                        }
                                    }
                                }
                            }
                        },
                        aggregations: {
                            'histogram': {
                                'date_histogram': {
                                    field: '@timestamp',
                                    interval: interval,
                                    'min_doc_count': 0,
                                    'extended_bounds': {
                                        min: 'now-' + subtract,
                                        max: 'now'
                                    }
                                }
                            }
                        }
                    }
                };
            }

            function makeHighchartsParams(name, data) {
                return {
                    title: false,
                    plotOptions: {
                        line: {
                            animation: false
                        }
                    },
                    xAxis: {
                        type: 'datetime',
                        title: {
                            text: 'Date'
                        }
                    },
                    yAxis: {
                        title: {
                            text: 'Hits'
                        },
                        plotLines: [{
                            value: 0,
                            width: 1,
                            color: '#808080'
                        }]
                    },
                    legend: {
                        enabled: false,
                        layout: 'vertical',
                        align: 'right',
                        verticalAlign: 'middle',
                        borderWidth: 0
                    },
                    series: [{
                        name: name,
                        data: data
                    }]
                };
            }

            $.fn.logstashWidget = function(url, template, name, pattern) {
                return this.each(function() {
                    var id = ID++;
                    var element = $(this);
                    var html = template.html();
                    var autoTimer = null;
                    element.html(html);
                    element.find('.title-text').text(name);
                    element.find('.range').prop('name', 'range' + id);

                    function fetch(range){
                        var params = makeElasticSearchParams(range, pattern);
                        return $.ajax({
                            type: 'POST',
                            url: url,
                            data: JSON.stringify(params),
                            dataType: 'json',
                            contentType: 'application/json'
                        });
                    }

                    function visualize(data){
                        var params = makeHighchartsParams(name, data);
                        element.find('.graph').highcharts(params);
                    }

                    function update(){
                        var range = element.find('.range:checked').val();
                        fetch(range).then(visualize);
                    }
                    function autoupdate(){
                        var enabled = element.find('.auto-refresh').is(':checked');
                        element.find('.refresh').prop('disabled', enabled);
                        if (enabled) {
                            autoTimer = setTimeout(function(){
                                update();
                                autoupdate();
                            }, AUTODELAY);
                        } else if (autoTimer) {
                            clearTimeout(autoTimer);
                            autoTimer = null;
                        }
                    }

                    element.find('.range').on('change', update);
                    element.find('.refresh').on('click', update);
                    element.find('.auto-refresh').on('change', autoupdate);
                    setTimeout(update, 400);
                    //update();
                });
            };

        }( jQuery ));
    </script>
    <style type="text/css">
        body {
            font-family: proximanova, "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .container {
            position: relative;
        }

        .popover-title {
            display: none !important;
        }

        .popover {
            width: 200px;
        }

        h1, h2, h3, h4, h5, h6 {
            margin: 10px 0;
            font-family: inherit;
            font-weight: bold;
            line-height: 1;
            color: inherit;
            text-rendering: optimizelegibility;
        }

        .navbar .brand-dev:hover {
            text-decoration: none;
        }

        .navbar .brand-dev {
            float: left;
            display: block;
            padding: 8px 20px 12px;
            margin-left: -20px;
            font-size: 20px;
            font-weight: 200;
            line-height: 1;
            color: #ffffff;
        }

        .dialog, .dashboard {
            border: 1px solid black;
            padding: 40px;
            margin-left: auto;
            background-color: #f7f7f7;
            border: 1px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            background-clip: padding-box;
            position: relative;
        }

        .dialog-center {
            border: 1px solid black;
            padding: 40px;
            margin: auto;
            background-color: #f7f7f7;
            border: 1px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            background-clip: padding-box;
            position: relative;
        }

        .signup-hero {
            position: absolute !important;
            width: 450px;
            margin: 0;
            padding: 60px 0;
            font-family: proximanova, "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        #main {
            margin: 0;
            padding: 0;
            width: 840px;
            margin: auto;
            margin-top: 75px;
        }

        .container {
            position: relative;
        }

        .dashboard-create {
            padding: 40px;
            margin: auto;
            margin-top: 50px;
        }

        .dialog, .dialog-wide {

        }

        .dialog-narrow {
            width: 350px;
        }

        .dialog-wide {
            width: 450px;
        }

        #logo {
            width: 95px;

        }

        .dashboard {
            min-height: 200px;
        }

        .first {
            margin-top: 10px
        }

        .last {
            margin-bottom: 30px;
        }

        .box {
            -webkit-border-radius: 4px;
            -moz-border-radius: 4px;
            border-radius: 4px;
            adding: 10px;
            display: block;
            background: white;
            background: -webkit-gradient(linear, left top, left bottom, color-stop(0%, white), color-stop(100%, #DDD));
            background: -webkit-linear-gradient(top, white 0, #DDD 100%);
            background: -moz-linear-gradient(top, white 0, #DDD 100%);
            background: -ms-linear-gradient(top, white 0, #DDD 100%);
            background: -o-linear-gradient(top, white 0, #DDD 100%);
            background: linear-gradient(top, white 0, #DDD 100%);
            filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffffff', endColorstr='#dddddd', GradientType=0);
            border-left: solid 1px #BBB;
            border-right: solid 1px #CCC;
            border-bottom: solid 1px #AAA;
            border-top: solid 1px #DDD;
            -webkit-box-shadow: 0 1px 0 rgba(0, 0, 0, .1);
            -moz-box-shadow: 0 1px 0 rgba(0, 0, 0, .1);
            box-shadow: 0 1px 0 rgba(0, 0, 0, .1);
            height: 230px;
            position: relative;
        }

        .inner {
            padding: 10px;
        }

        .box h2 {
            display: block;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 20px;
            font-weight: bold;
            color: #777;
            border-bottom: 1px solid #E2E2E2;
            -webkit-box-shadow: 0 1px 0 #fff;
            -moz-box-shadow: 0 1px 0 #fff;
            box-shadow: 0 1px 0 #fff;
            -webkit-text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
            -moz-text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
            text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
            line-height: 20px;
        }

        h2 span i {
            font-size: 13px;
            font-weight: bold;
            font-style: normal
        }

        .icon-ok {
            margin-right: 5px;
        }

        .box .inner {
            color: #777;
            font-weight: bold;
            -webkit-text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
            -moz-text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
            text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
            line-height: 20px;
        }

        .box .minus {
            color: #aaa;
        }

        .box .no-icon {
            visibility: hidden;
        }

        .round_border {
            -webkit-border-radius: 4px;
            -moz-border-radius: 4px;
            border-radius: 4px;
        }

        .btn-upgrade {
            position: absolute;
            bottom: 15px;
            right: 15px;
            float: right;
        }

        .all-plans i {
            margin-left: 20px;
        }

        .all-plans {
            margin-top: 15px;
        }

        .form {
            margin-bottom: 0px;
        }

        #btn-admin {
            margin-top: 50px;
        }

        .map-entry {
            font-size: 12pt;
            font-weight: normal;
        }

        .map-entry a {
            float: left;
        }

        .subuser-entry td {
            border: none;
            padding: 3px;
        }

        .mm-or {
            top: 69px;
            color: #666666;
            font-size: bold;
            background-color: #f7f7f7;
            position: absolute;
            text-align: center;
            top: -10px;
            width: 40px;
            left: 115px;
        }

        .lgbx-signup {
            border-top: 1px solid #dfdfdf;
            margin-top: 20px;
            padding-top: 20px;
            position: relative;
        }

        .center {
            text-align: center;
        }

        .full-width {
            width: 100%;
        }

        .label {
            width: 100%;
        }

        #db_exists_not {
            margin-top: 100px;
        }

        #corner a {
            position: absolute;
            background-image: url("<?php echo \app\conf\App::$param['loginLogo']; ?>");
            width: 150px;
            height: 80px;
            background-size: 120px;
            background-repeat: no-repeat;
            top: 5px;
            left: 5px;
            display: block;
            z-index: 2;
        }

        .fixed-width {
            width: 40px;
        }

        .padded {
            padding: 40px;
        }

        .user-btns {
            float: right;
            width: 84px;
        }

        .delete {
            float: right;
        }

        .change {
            float: left;
        }

        .right-border {
            border-right: 1px solid #ddd;
        }

        #schema-list > table > thead > tr > th:last-child, #schema-list > table > tbody > tr > td:nth-child(n+2) {
            text-align: right;
        }
        #logstash-modal  .modal-dialog {
            width: 800px;
            height: 600px;
        }
        #logstash-modal  .modal-body {
            height: 500px;
        }
    </style>
</head>
<body>
<?php include_once("../../../app/conf/analyticstracking.php") ?>
<div id="corner">
    <a href="<?php echo (\app\conf\App::$param['homepage']) ?: "http://www.mapcentia.com/en/geocloud/geocloud.htm"; ?>"></a>
</div>
<div style="position: absolute; right: 5px; top: 3px; z-index: 2">
    <div>
        <?php if (!$_SESSION['auth'] || !$_SESSION['screen_name']) { ?>
            <a href="/user/login">Sign In</a>
        <?php
        } else {
            ?>
            <a href="/user/login/p"><?php echo $_SESSION['screen_name'] ?></a>
            <?php if ($_SESSION['subuser']) echo " ({$_SESSION['subuser']})" ?>
            <?php if (!$_SESSION['subuser']) { ?>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/user/new">New Sub-User</a>
            <?php } ?>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/user/edit">Change
                Password</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a target="_blank"
                href="http://mapcentia.screenstepslive.com/">Help</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a
                href="/user/logout">Log Out</a>&nbsp;&nbsp;&nbsp;
        <?php } ?>
    </div>
</div>