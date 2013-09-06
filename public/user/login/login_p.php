<?php
include '../header.php';
include '../html_header.php';
// Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
    die("<script>window.location='{$userHostName}/user/login'</script>");
}
($_SESSION['zone']) ? $prefix = $_SESSION['zone'] . "." : $prefix = "";
$checkDb = json_decode(file_get_contents("http://{$prefix}{$domain}/controller/databases/postgres/doesdbexist/{$_SESSION['screen_name']}"));
?>
<div class="container">
    <?php if ($checkDb->success) : ?>
        <div class="row dashboard">
            <div class="span3">
                <?php
                echo "<a href='http://{$prefix}{$domain}/store/{$_SESSION['screen_name']}' id='btn-admin' class='btn btn-large btn-info' data-placement='top'
                                     title='Start the administration of your GeoCloud'>Start admin</a>";
                file_get_contents("http://{$prefix}{$domain}/controller/mapfile/{$_SESSION['screen_name']}/public/create");
                ?>
            </div>
            <div class="span3">
                <div id="schema-list">
                    <h2>Maps <span><i><i id="schema-tool-tip" data-placement="right" class="icon-info-sign"
                                         title="Logical groups of layers"></i></i></span></h2>
                    <table class="table" id="schema-table"></table>
                </div>
            </div>
        </div>
        <script>
            $('#btn-create').popover('show');
            $("#schema-tool-tip").tooltip();
            $("#btn-admin").tooltip();
        </script>
    <?php endif; ?>
    <?php if (!$checkDb->success) : ?>
        <div class="row dashboard-create">
            <div class="row">
                <div class="span3">
                    <?php
                    echo "<a href='http://{$prefix}{$domain}/user/createstore' id='btn-create' class='btn btn-large btn-info' title='' data-placement='right' data-content='Click here to create your geocloud. It may take some secs, so stay on this page.'>Create new database</a>";
                    ?>
                </div>
            </div>
        </div>
        <script>
            $('#btn-create').popover('show');
        </script>
    <?php endif; ?>
</div>
<?php if ($checkDb->success) : ?>
    <script type="text/html" id="template-schema-list">
        <tr class="map-entry">
            <td><%= this . schema %></td>
            <td><a target="_blank"
                   href="http://<?php echo "{$prefix}{$domain}/apps/viewer/" ?><%= db %>/<%= this . schema %>">View
                </a></td>
        </tr>
    </script>
    <script>
        var metaDataKeys = [];
        var metaDataKeysTitle = [];
        var db = "<?php echo $_SESSION['screen_name'];?>";
        var hostName = "http://<?php echo "{$prefix}{$domain}";?>";
        $(window).ready(function () {
            /*$.ajax({
                url: hostName + '/controller/geometry_columns/' + db + '/getall/',
                async: true,
                dataType: 'jsonp',
                jsonp: 'jsonp_callback',
                success: function (response) {
                    var metaData = response;
                    for (var i = 0; i < metaData.data.length; i++) {
                        metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                        (metaData.data[i].f_table_title) ? metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i] : null;
                    }
                    //console.log(metaData);
                }
            });
            */
            $.ajax({
                url: hostName + '/controller/geometry_columns/' + db + '/getschemas',
                async: true,
                dataType: 'jsonp',
                jsonp: 'jsonp_callback',
                success: function (response) {
                    //console.log(response);
                    $('#schema-table').append($('#template-schema-list').jqote(response.data));
                }
            });
        });
    </script>
<?php endif; ?>
<script id="IntercomSettingsScriptTag">
    window.intercomSettings = {
        // TODO: The current logged in user's email address.
        email: "<?php echo $_SESSION['email']; ?>",
        // TODO: The current logged in user's sign-up date as a Unix timestamp.
        created_at: <?php echo $_SESSION['created']; ?>,
        app_id: "154aef785c933674611dca1f823960ad5f523b92"
    };
</script>
<script>
    (function () {
        var w = window;
        var ic = w.Intercom;
        if (typeof ic === "function") {
            ic('reattach_activator');
            ic('update', intercomSettings);
        } else {
            var d = document;
            var i = function () {
                i.c(arguments)
            };
            i.q = [];
            i.c = function (args) {
                i.q.push(args)
            };
            w.Intercom = i;
            function l() {
                var s = d.createElement('script');
                s.type = 'text/javascript';
                s.async = true;
                s.src = 'https://api.intercom.io/api/js/library.js';
                var x = d.getElementsByTagName('script')[0];
                x.parentNode.insertBefore(s, x);
            }

            if (w.attachEvent) {
                w.attachEvent('onload', l);
            } else {
                w.addEventListener('load', l, false);
            }
        }
        ;
    })()
</script>
</body>
</html>
