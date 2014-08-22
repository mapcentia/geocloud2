<?php
use \app\inc\Model;
use \app\conf\App;

include '../header.php';
$postgisObject = new Model();
include '../html_header.php';
// Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
    die("<script>window.location='/user/login'</script>");
}


$prefix = ($_SESSION['zone']) ? App::$param['domainPrefix'] . $_SESSION['zone'] . "." : "";
if (App::$param['domain']) {
    $host = "http://" . $prefix . App::$param['domain'];
} else {
    $host = App::$param['host'];
}

if (App::$param['cdnSubDomain']) {
    $bits = explode("://", $host);
    $cdnHost = $bits[0] . "://" . App::$param['cdnSubDomain'] . "." . $bits[1];
} else {
    $cdnHost = $host;
}
// If main user fetch all sub users
if (!$_SESSION['subuser']) {
    $_SESSION['subusers'] = array();
    $_SESSION['subuserEmails'] = array();
    $sQuery = "SELECT * FROM {$sTable} WHERE parentdb = :sUserID";
    $res = $postgisObject->prepare($sQuery);
    $res->execute(array(":sUserID" => $_SESSION['screen_name']));
    while ($rowSubUSers = $postgisObject->fetchRow($res)) {
        $_SESSION['subusers'][] = $rowSubUSers["screenname"];
        $_SESSION['subuserEmails'][$rowSubUSers["screenname"]] = $rowSubUSers["email"];
    };
}
?>
<div class="container">
    <div id="main">
        <div id="db_exists" style="display: none">
            <div class="row">
                <div id="sb" class="col-md-12 dashboard" style="display: none">
                    <div id="schema-list">
                        <table class="table table-condensed" id="schema-table">
                            <thead><tr><th>Schema</th><th>Viewer</th><!--<th>Viewer</th>--><th>Admin</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="row" style="margin-top: 30px">
                <div class="col-md-12" id="subusers-el" style="display: none">

                    <table class="table" id="subusers-table">
                        <thead><tr><th>Sub-user</th><th>Email</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="db_exists_not" style="display: none">
            <?php
            echo "<a href='" . $host . "/user/createstore' id='btn-create' class='btn btn-lg btn-danger' title='' data-placement='right' data-content='Click here to create your PostGIS database.'>Create new database</a>";
            ?>
        </div>
    </div>
</div>
<script type="text/html" id="template-schema-list">
    <tr class="map-edntry">
        <td><%= this . schema %></td>
        <td><a class="btn btn-xs btn-default" target="_blank"
               href="<?php echo $cdnHost . "/apps/viewer/" ?><%= db %>/<%= this . schema %>">View
        </a></td>
        <!--<td><a target="_blank"
               href="<?php echo $cdnHost . "/apps/heron/" ?><%= db %>/<%= this . schema %>">View
        </a></td>-->
        <td><a class="btn btn-xs btn-danger" target="_blank"
               href="/store/<%= db %>/<%= this . schema %>">Admin
        </a></td>
    </tr>
</script>
<script type="text/html" id="template-subuser-list">
    <tr class="subuser-entry">
        <td><span class="glyphicon glyphicon-user"></span> <%= this %></td>
        <td><%= subUserEmails[this] %></td>
    </tr>
</script>
<script>
    var metaDataKeys = [];
    var metaDataKeysTitle = [];
    var db = "<?php echo $_SESSION['screen_name'];?>";
    var hostName = "<?php echo $host ?>";
    <?php
       if (!$_SESSION['subuser']){
       echo "var subUsers = ".json_encode($_SESSION['subusers']).";\n";
       echo "var subUserEmails = ".json_encode($_SESSION['subuserEmails']).";\n";
       }
       else {
       echo "var subUsers = null;\n";
       echo "var subUserEmails = null;\n";
       }
   ?>
    $(window).ready(function () {
        $.ajax({
            url: hostName + '/controllers/database/exist/<?php echo $_SESSION['screen_name'] ?>',
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                if (response.success === true) {
                    $("#db_exists").show();
                    $('#btn-create').popover('show');
                    $("#schema-tool-tip").tooltip();
                    $("#btn-admin").tooltip();
                    $.ajax({
                        url: hostName + '/controllers/database/schemas',
                        dataType: 'jsonp',
                        jsonp: 'jsonp_callback',
                        success: function (response) {
                            //console.log(response);
                            $('#schema-table tbody').append($('#template-schema-list').jqote(response.data));
                            $('#sb').fadeIn(400);
                            if (subUsers) {
                                $('#subusers-table tbody').append($('#template-subuser-list').jqote(subUsers));
                                if (subUsers.length > 0) {
                                    $("#subusers-el").delay(200).fadeIn(400);
                                }
                            }
                        }
                    });
                    $.ajax({
                        dataType: 'jsonp',
                        jsonp: 'jsonp_callback',
                        url: hostName + '/controllers/mapfile',
                        success: function (response) {

                        }
                    });
                    $.ajax({
                        dataType: 'jsonp',
                        jsonp: 'jsonp_callback',
                        url: hostName + '/controllers/cfgfile',
                        success: function (response) {

                        }
                    });
                }
                else {
                    $("#db_exists_not").show();
                    $('#btn-create').popover('show');
                }
            }
        });
    });
</script>
<?php
if (\app\conf\App::$param['intercom_io']) {
    include_once("../../../app/conf/intercom.js.inc");
}
?>
</body>
</html>
