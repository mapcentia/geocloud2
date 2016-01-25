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
// Set schema for the mapfiles write request
$_SESSION['postgisschema'] = "public";


// If main user fetch all sub users
//if (!$_SESSION['subuser']) {
$_SESSION['subusers'] = array();
$_SESSION['subuserEmails'] = array();
$sQuery = "SELECT * FROM {$sTable} WHERE parentdb = :sUserID";
$res = $postgisObject->prepare($sQuery);
$res->execute(array(":sUserID" => $_SESSION['screen_name']));
while ($rowSubUSers = $postgisObject->fetchRow($res)) {
    $_SESSION['subusers'][] = $rowSubUSers["screenname"];
    $_SESSION['subuserEmails'][$rowSubUSers["screenname"]] = $rowSubUSers["email"];
};
//}
?>
<div class="container" xmlns="http://www.w3.org/1999/html">
    <div id="main">
        <div class="row">
            <div class="col-md-8 right-border">
                <div id="db_exists" style="display: none">
                    <input id="schema-filter" type="text" class="form-control" placeholder="Filter by name..."
                           style="width: 200px; margin-bottom: 15px">

                    <!--                    <div style="position: absolute">No schemas found.</div>-->
                    <div id="schema-list"></div>
                </div>
            </div>

            <div class="col-md-4">
                <div id="user-container" style="display: none">
                    <input id="user-filter" type="text" class="form-control" placeholder="Filter by user..."
                           style="width: 200px; margin-bottom: 15px">

                    <!--                    <div style="position: absolute">No users found.</div>-->
                    <div id="subusers-el"></div>
                </div>
            </div>

        </div>
    </div>

    <div id="db_exists_not" style="display: none">
        <?php
        echo "<a href='" . $host . "/user/createstore' id='btn-create' class='btn btn-lg btn-danger' title='' data-placement='right' data-content='Click here to create your PostGIS database.'>Create New Database</a>";
        ?>
    </div>
</div>
<div id="confirm-user-delete" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirm deletion of sub-user</h4>
            </div>
            <div class="modal-body">
                <p>If you later create a sub-user with the same name, it will get the privileges of the deleted one.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="delete-user">Delete</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div><!-- /.modal -->
<div id="logstash-modal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"></h4>
            </div>
            <div class="modal-body">
                <div role="tabpanel">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" role="tablist" id="main-tabs">
                        <li role="presentation" class="active"><a href="#tile-content" aria-controls=""
                                                                  role="tab"
                                                                  data-toggle="tab">Tile downloads</a></li>
                        <li role="presentation"><a href="#es-content" aria-controls="" role="tab"
                                                   data-toggle="tab">Elasticsearch queries</a></li>
                        <li role="presentation"><a href="#wms-content" aria-controls="" role="tab"
                                                   data-toggle="tab">OWS requests</a></li>
                        <!--<li role="presentation"><a href="#wfs-content" aria-controls="" role="tab"
                                                   data-toggle="tab">WMS</a></li>-->
                    </ul>
                </div>
                <!-- Tab panes -->
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="tile-content">
                        <div id="widget1"></div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="es-content">
                        <div id="widget2"></div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="wms-content">
                        <div id="widget3"></div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="wfs-content">
                        <div id="widget4"></div>
                    </div>
                </div>


            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div><!-- /.modal -->

<script type="text/html" id="template-schema-list">
    <div id="<%= this . schema %>">
        <div class="panel panel-default">
            <div class="panel-heading"><span class="glyphicon glyphicon-globe"></span> <%= this . schema %></div>
            <div class="panel-body">
                <!--<div style="margin-bottom: 15px">
                    Description coming...
                </div>-->
                <div style="margin-bottom: 15px">
                    Number of layers <span class="badge"><%= this . count || "0" %></span>
                </div>

                <div style="float: right">
                    <a data-toggle="tooltip" data-placement="top"
                       title="Open '<%= this . schema %>' in the response Map Viewer"
                       class="btn btn-xs btn-default" target="_blank"
                       href="<?php echo $cdnHost . "/apps/viewer/" ?><%= db %>/<%= this . schema %>"><span>Viewer</span>
                    </a>
                    <a data-toggle="tooltip" data-placement="top"
                       title="Open '<%= this . schema %>' in the advanced Map Client"
                       class="btn btn-xs btn-default" target="_blank"
                       href="<?php echo $cdnHost . "/apps/mapclient/" ?><%= db %>/<%= this . schema %>"><span>Map client</span>
                    </a>
                    <?php if (App::$param['logstashHost']) { ?>
                    <a data-toggle="tooltip" data-placement="top" data-schema="<%= this . schema %>"
                       title="See statistics for '<%= this . schema %>'"
                       class="btn btn-xs btn-default fixed-width logstash"><span
                            class="glyphicon glyphicon-stats"></span>
                    </a>
                    <?php } ?>
                    <a data-toggle="tooltip" data-placement="top"
                       title="Open GC2 administration for '<%= this . schema %>'"
                       class="btn btn-xs btn-primary fixed-width" target="_blank"
                       href="<?php echo $cdnHost . "/store/" ?><%= db %>/<%= this . schema %>"><span
                            class="glyphicon glyphicon-cog"></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</script>
<script type="text/html" id="template-subuser-list">
    <div id="<%= this %>">
        <div class="panel panel-default">
            <div class="panel-heading"><span class="glyphicon glyphicon-user"></span> <%= this %></div>
            <div class="panel-body">
                <div style="margin-bottom: 15px">
                    <%= subUserEmails[this] %>
                </div>

                <div class="user-btns">
                    <form method="get" action="/user/edit/u"><input name="user" type="hidden" value="<%= this %>"/>
                        <button data-user="<%= this %>" data-toggle="tooltip" data-placement="top"
                                title="Change the pw and group of user <%= this %>"
                                class="btn btn-xs btn-primary fixed-width change" type="submit"><span
                                class="glyphicon glyphicon-cog"></span></button>
                    </form>
                    <form method="post" action="/user/delete/p"><input name="user" type="hidden" value="<%= this %>"/>
                        <button data-toggle="tooltip" data-placement="top" title="Delete the user <%= this %>"
                                class="btn btn-xs btn-danger fixed-width delete" type="submit"><span
                                class="glyphicon glyphicon-trash"></span></button>
                    </form>
                </div>

            </div>
        </div>
    </div>
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
        $("#schema-filter").keyup(function () {
            $('#schema-list > div[id*="' + $("#schema-filter").val() + '"]').css("display", "inline");
            $('#schema-list > div:not([id*="' + $("#schema-filter").val() + '"])').css("display", "none");
            if ($("#schema-filter").val().length == 0) {
                $('#schema-list > div').css("display", "inline");
            }
        });
        $("#user-filter").keyup(function () {
            $('#subusers-el > div[id*="' + $("#user-filter").val() + '"]').css("display", "inline");
            $('#subusers-el > div:not([id*="' + $("#user-filter").val() + '"])').css("display", "none");
            if ($("#user-filter").val().length == 0) {
                $('#subusers-el > div').css("display", "inline");
            }
        });
        $.ajax({
            url: hostName + '/controllers/database/exist/' + db,
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
                            $('#schema-list').append($('#template-schema-list').jqote(response.data));
                            $('#sb').fadeIn(400);
                            $('.logstash').on('click', function (e) {
                                e.preventDefault();
                                var schema = $(this).data("schema"),
                                    template = $('#widgetTemplate'),
                                    host = '/controllers/logstash';

                                $('#widget1').logstashWidget(host, template, 'Tile downloads', db + ' ' + schema + '.*' + ' mapcache');
                                $('#widget2').logstashWidget(host, template, 'Elasticsearch queries', db + ' ' + schema + ' api elasticsearch');
                                $('#widget3').logstashWidget(host, template, 'OWS request', db + ' ' + schema + ' ows');
                                $('#logstash-modal').modal();
                                $('#logstash-modal h4').html("Stats for " + schema);
                            });
                            if (subUsers) {
                                $('#subusers-el').append($('#template-subuser-list').jqote(subUsers));
                                if (subUsers.length > 0) {
                                    $("#user-container").delay(200).fadeIn(400);
                                    $('.delete').on('click', function (e) {
                                        var $form = $(this).closest('form');
                                        e.preventDefault();
                                        $('#confirm-user-delete').modal({backdrop: 'static', keyboard: false})
                                            .one('click', '#delete-user', function () {
                                                $form.trigger('submit');
                                            });
                                    });
                                    $('.change').on('click', function (e) {
                                        var $form = $(this).closest('form');
                                        e.preventDefault();
                                        $form.trigger('submit');

                                    });
                                }
                            }
                            $(function () {
                                $('[data-toggle="tooltip"]').tooltip()
                            })
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
                    $.ajax({
                        dataType: 'jsonp',
                        jsonp: 'jsonp_callback',
                        url: hostName + '/controllers/mapcachefile',
                        success: function (response) {
                            $.ajax({
                                dataType: 'jsonp',
                                jsonp: 'jsonp_callback',
                                url: hostName + '/controllers/mapcache/add/' + db ,
                                success: function (response) {

                                }
                            });
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
