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
<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
<link href="/css/banner-ie.css" rel="stylesheet">
<![endif]-->

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script src="/js/jquery-placeholder/jquery.placeholder.js"></script>
<script src="/js/bootstrap/js/bootstrap.js"></script>
<script src="/js/jqote2/jquery.jqote2.js"></script>

<link href="/js/bootstrap3/css/bootstrap.css" rel="stylesheet">
<link href="/css/banner.css" rel="stylesheet">
<link rel="StyleSheet" href="/css/proximanova.css" type="text/css"/>
<script>
    $(function () {
        $('input, textarea').placeholder();

    });
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
.delete {
    float: right;
}
.right-border {
    border-right: 1px solid #ddd;
}


#schema-list > table > thead > tr > th:last-child, #schema-list > table > tbody > tr > td:nth-child(n+2) {
    text-align: right;
}
</style>
</head>
<body>
<?php include_once("../../../app/conf/analyticstracking.php") ?>
<div id="corner">
    <a href="<?php echo (\app\conf\App::$param['homepage']) ? : "http://www.mapcentia.com/en/geocloud/geocloud.htm"; ?>"></a>
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
                Password</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a
                href="http://mapcentia.screenstepslive.com/">Help</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a
                href="/user/logout">Log Out</a>&nbsp;&nbsp;&nbsp;
        <?php } ?>
    </div>
</div>