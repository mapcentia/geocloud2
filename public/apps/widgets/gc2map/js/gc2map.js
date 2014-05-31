var gc2Widget = {};
gc2Widget.maps = [];
gc2Widget.scriptsLoaded = false;
(function () {
    "use strict";
    var scriptSource = (function () {
            var scripts = document.getElementsByTagName('script'),
                script = scripts[scripts.length - 1];
            if (script.getAttribute.length !== undefined) {
                return script.src;
            }
            return script.getAttribute('src', -1);
        }()),
        scriptHost,
        host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
    scriptHost = host;
    //host = "http://cowi.mapcentia.com";
    if (typeof jQuery === "undefined") {
        document.write("<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js'><\/script>");
    }
    document.write("<script src='http://maps.google.com/maps/api/js?v=3&sensor=false&libraries=places'><\/script>");
    document.write("<script src='http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js'><\/script>");
    document.write("<script src='" + host + "/api/v3/js/geocloud.js'><\/script>");
    function loadDependencies() {
        //jQuery.getScript("http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js");
        jQuery.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/proj4js-combined.js");
        jQuery.getScript("http://cdn.eu1.mapcentia.com/js/bootstrap3/js/bootstrap.min.js");
        jQuery.getScript("http://cdn.eu1.mapcentia.com/js/hogan/hogan-2.0.0.js");
        jQuery.getScript(host + "/api/v1/baselayerjs");

        pollForDependencies();
        $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css' }).appendTo('head');
        $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/bootstrap.css' }).appendTo('head');

        $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://netdna.bootstrapcdn.com/font-awesome/4.0.1/css/font-awesome.min.css' }).appendTo('head');
        $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/non-responsive.css' }).appendTo('head');
        $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/styles.css' }).appendTo('head');
        $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://fonts.googleapis.com/css?family=Open+Sans+Condensed:300' }).appendTo('head');
    }

    function loadDependants() {
        //jQuery.getScript(host + "/api/v3/js/geocloud.js
        //jQuery.getScript("http://maps.stamen.com/js/tile.stamen.js?v1.2.0");
        jQuery.getScript(scriptHost + "/apps/widgets/gc2map/js/main.js");
        pollForDependants();
    }

    var pollForDependencies = function () {
        if (typeof L !== "undefined" &&
            typeof Proj4js !== "undefined" &&
            typeof $().emulateTransitionEnd !== 'undefined' &&
            typeof Hogan !== "undefined" &&
            //typeof L.StamenTileLayer !== "undefined" &&
            typeof google !== 'undefined' && typeof google.maps !== 'undefined'
            ) {
            loadDependants();
        } else {
            setTimeout(pollForDependencies, 10);
        }
    };
    var pollForDependants = function () {
        if (typeof geocloud !== "undefined" && typeof MapCentia !== "undefined") {
            gc2Widget.scriptsLoaded = true;
        } else {
            setTimeout(pollForDependants, 10);
        }
    };
    var pollForjQuery = function () {
        if (typeof jQuery !== "undefined") {
            loadDependencies();
        } else {
            setTimeout(pollForjQuery, 10);
        }
    };
    pollForjQuery();

    gc2Widget.map = function (conf) {
        var defaults = {
                host: host
            }, prop, pollForScripts, init, divs = document.getElementsByTagName('div'),
            div = divs[divs.length - 1],
            gc2RandId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        if (conf) {
            for (prop in conf) {
                defaults[prop] = conf[prop];
            }
        }
        div.setAttribute("id", gc2RandId);
        if (div.style.position === "") {
            div.style.position = "relative";
        }
        init = function () {
            var templates = {};
            templates.body = new Hogan.Template(function (c, p, i) {
                var _ = this;
                _.b(i = i || "");
                _.b("<div class=\"pane\">");
                _.b("\n" + i);
                _.b("    <!-- map -->");
                _.b("\n" + i);
                _.b("    <div id=\"map-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" class=\"map\"></div>");
                _.b("\n" + i);
                _.b("    <nav class=\"navbar navbar-default\" role=\"navigation\">");
                _.b("\n" + i);
                _.b("        <div class=\"navbar-header\">");
                _.b("\n" + i);
                _.b("        </div>");
                _.b("\n" + i);
                _.b("        <div class=\"collapse navbar-collapse\" id=\"bs-example-navbar-collapse-1\">");
                _.b("\n" + i);
                _.b("            <ul class=\"nav navbar-nav\">");
                _.b("\n" + i);
                _.b("                <li>");
                _.b("\n" + i);
                _.b("                    <button id=\"locate-btn-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" type=\"button\" class=\"btn btn-default navbar-btn locate-btn\">");
                _.b("\n" + i);
                _.b("                        <i class=\"fa fa-location-arrow\"></i>");
                _.b("\n" + i);
                _.b("                    </button>");
                _.b("\n" + i);
                _.b("                </li>");
                _.b("\n" + i);
                _.b("\n" + i);
                _.b("                <li id=\"legend-popover-li-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" class=\"gc-btn\">");
                _.b("\n" + i);
                _.b("                    <a href=\"javascript:void(0)\" id=\"legend-popover-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" rel=\"popover\"");
                _.b("\n" + i);
                _.b("                       data-placement=\"bottom\">");
                _.b("\n" + i);
                _.b("                        Signatur </a>");
                _.b("\n" + i);
                _.b("                </li>");
                _.b("\n" + i);
                _.b("                <li id=\"share-modal-li-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" class=\"gc-btn\">");
                _.b("\n" + i);
                _.b("                    <a href=\"javascript:void(0)\" title=\"Share\"");
                _.b("\n" + i);
                _.b("                       onclick=\"gc2Widget.maps['");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("'].share();\">");
                _.b("\n" + i);
                _.b("                        Del </a>");
                _.b("\n" + i);
                _.b("                </li>");
                _.b("\n" + i);
                _.b("                <li id=\"open-win-li-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" class=\"gc-btn\">");
                _.b("\n" + i);
                _.b("                    <a href=\"javascript:void(0)\" title=\"\"");
                _.b("\n" + i);
                _.b("                       onclick=\"gc2Widget.maps['");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("'].openMapWin('index.html' + decodeURIComponent(geocloud.urlHash),screen.width-20, screen.height-100);\">");
                _.b("\n" + i);
                _.b("                        Stort kort </a>");
                _.b("\n" + i);
                _.b("                </li>");
                _.b("\n" + i);
                _.b("                <li class=\"dropdown\">");
                _.b("\n" + i);
                _.b("                    <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\">Baggrund <b");
                _.b("\n" + i);
                _.b("                            class=\"caret\"></b></a>");
                _.b("\n" + i);
                _.b("                    <ul class=\"dropdown-menu\" id=\"base-layer-list-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\">");
                _.b("\n" + i);
                _.b("                    </ul>");
                _.b("\n" + i);
                _.b("                </li>");
                _.b("\n" + i);
                _.b("            </ul>");
                _.b("\n" + i);
                _.b("        </div>");
                _.b("\n" + i);
                _.b("    </nav>");
                _.b("\n" + i);
                _.b("    <!-- layers -->");
                _.b("\n" + i);
                _.b("    <div id=\"layers-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" class=\"panel-group\"></div>");
                _.b("\n" + i);
                _.b("    <!-- legend -->");
                _.b("\n" + i);
                _.b("    <div id=\"legend-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\"></div>");
                _.b("\n" + i);
                _.b("    <!-- info modal -->");
                _.b("\n" + i);
                _.b("    <div id=\"modal-info-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" class=\"modal fade\">");
                _.b("\n" + i);
                _.b("        <div class=\"modal-dialog modal-infobox\">");
                _.b("\n" + i);
                _.b("            <div class=\"modal-content\">");
                _.b("\n" + i);
                _.b("                <div class=\"modal-header\">");
                _.b("\n" + i);
                _.b("                    <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>");
                _.b("\n" + i);
                _.b("                    <h4 class=\"modal-title\">Info</h4>");
                _.b("\n" + i);
                _.b("                </div>");
                _.b("\n" + i);
                _.b("                <div class=\"modal-body\" id=\"modal-info-body-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\">");
                _.b("\n" + i);
                _.b("                    <ul class=\"nav nav-tabs\" id=\"info-tab-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\"></ul>");
                _.b("\n" + i);
                _.b("                    <div class=\"tab-content\" id=\"info-pane-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\"></div>");
                _.b("\n" + i);
                _.b("                </div>");
                _.b("\n" + i);
                _.b("                <div class=\"modal-footer\">");
                _.b("\n" + i);
                _.b("                    <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Luk</button>");
                _.b("\n" + i);
                _.b("                </div>");
                _.b("\n" + i);
                _.b("            </div>");
                _.b("\n" + i);
                _.b("            <!-- /.modal-content -->");
                _.b("\n" + i);
                _.b("        </div>");
                _.b("\n" + i);
                _.b("        <!-- /.modal-dialog -->");
                _.b("\n" + i);
                _.b("    </div>");
                _.b("\n" + i);
                _.b("    <!-- /.modal -->");
                _.b("\n" + i);
                _.b("    <!-- Share modal -->");
                _.b("\n" + i);
                _.b("    <div id=\"modal-share-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" class=\"modal fade modal-share\">");
                _.b("\n" + i);
                _.b("        <div class=\"modal-dialog\">");
                _.b("\n" + i);
                _.b("            <div class=\"modal-content\">");
                _.b("\n" + i);
                _.b("                <div class=\"modal-header\">");
                _.b("\n" + i);
                _.b("                    <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>");
                _.b("\n" + i);
                _.b("                    <h4 class=\"modal-title\">Del kortet</h4>");
                _.b("\n" + i);
                _.b("                </div>");
                _.b("\n" + i);
                _.b("                <div class=\"modal-body\" id=\"modal-share-body-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\">");
                _.b("\n" + i);
                _.b("                    <form class=\"form-horizontal\" role=\"form\">");
                _.b("\n" + i);
                _.b("                        <div class=\"form-group\">");
                _.b("\n" + i);
                _.b("                            <label class=\"col-sm-1 control-label\"><i class=\"fa fa-share\"></i></label>");
                _.b("\n" + i);
                _.b("\n" + i);
                _.b("                            <div class=\"col-sm-10\">");
                _.b("\n" + i);
                _.b("                                <button type=\"button\" class=\"btn btn-default btn-share\" data-toggle=\"tooltip\"");
                _.b("\n" + i);
                _.b("                                        data-placement=\"top\" title=\"Twitter\"");
                _.b("\n" + i);
                _.b("                                        onclick=\"gc2Widget.maps['");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("'].shareTwitter();\"><i");
                _.b("\n" + i);
                _.b("                                        class=\"fa fa-twitter\"></i>");
                _.b("\n" + i);
                _.b("                                </button>");
                _.b("\n" + i);
                _.b("                                <button type=\"button\" class=\"btn btn-default btn-share\" data-toggle=\"tooltip\"");
                _.b("\n" + i);
                _.b("                                        data-placement=\"top\" title=\"LinkedIn\"");
                _.b("\n" + i);
                _.b("                                        onclick=\"gc2Widget.maps['");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("'].shareLinkedIn();\"><i");
                _.b("\n" + i);
                _.b("                                        class=\"fa fa-linkedin\"></i>");
                _.b("\n" + i);
                _.b("                                </button>");
                _.b("\n" + i);
                _.b("                                <button type=\"button\" class=\"btn btn-default btn-share\" data-toggle=\"tooltip\"");
                _.b("\n" + i);
                _.b("                                        data-placement=\"top\" title=\"Google+\"");
                _.b("\n" + i);
                _.b("                                        onclick=\"gc2Widget.maps['");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("'].shareGooglePlus();\"><i");
                _.b("\n" + i);
                _.b("                                        class=\"fa fa-google-plus\"></i>");
                _.b("\n" + i);
                _.b("                                </button>");
                _.b("\n" + i);
                _.b("                                <button type=\"button\" class=\"btn btn-default btn-share\" data-toggle=\"tooltip\"");
                _.b("\n" + i);
                _.b("                                        data-placement=\"top\" title=\"Facebook\"");
                _.b("\n" + i);
                _.b("                                        onclick=\"gc2Widget.maps['");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("'].shareFacebook();\"><i");
                _.b("\n" + i);
                _.b("                                        class=\"fa fa-facebook\"></i>");
                _.b("\n" + i);
                _.b("                                </button>");
                _.b("\n" + i);
                _.b("                                <button type=\"button\" class=\"btn btn-default btn-share\" data-toggle=\"tooltip\"");
                _.b("\n" + i);
                _.b("                                        data-placement=\"top\" title=\"Tumblr\"");
                _.b("\n" + i);
                _.b("                                        onclick=\"gc2Widget.maps['");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("'].shareTumblr();\">");
                _.b("\n" + i);
                _.b("                                    <i");
                _.b("\n" + i);
                _.b("                                            class=\"fa fa-tumblr\"></i>");
                _.b("\n" + i);
                _.b("                                </button>");
                _.b("\n" + i);
                _.b("                            </div>");
                _.b("\n" + i);
                _.b("                        </div>");
                _.b("\n" + i);
                _.b("                        <div class=\"form-group\">");
                _.b("\n" + i);
                _.b("                            <label for=\"share-url\" class=\"col-sm-1 control-label\"><i");
                _.b("\n" + i);
                _.b("                                    class=\"fa fa-link\"></i></label>");
                _.b("\n" + i);
                _.b("\n" + i);
                _.b("                            <div class=\"col-sm-10\">");
                _.b("\n" + i);
                _.b("                                <input data-toggle=\"tooltip\" data-placement=\"top\" title=\"URL to this map\"");
                _.b("\n" + i);
                _.b("                                       type=\"text\"");
                _.b("\n" + i);
                _.b("                                       class=\"form-control share-text\" id=\"share-url-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" value=\"\">");
                _.b("\n" + i);
                _.b("                            </div>");
                _.b("\n" + i);
                _.b("                        </div>");
                _.b("\n" + i);
                _.b("                        <div class=\"form-group\" id=\"group-iframe-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\">");
                _.b("\n" + i);
                _.b("                            <label for=\"share-iframe\" class=\"col-sm-1 control-label\"><i class=\"fa fa-code\"></i></label>");
                _.b("\n" + i);
                _.b("\n" + i);
                _.b("                            <div class=\"col-sm-10\">");
                _.b("\n" + i);
                _.b("                                <input data-toggle=\"tooltip\" data-placement=\"top\"");
                _.b("\n" + i);
                _.b("                                       title=\"Iframe with this map to embed on web page\" type=\"text\"");
                _.b("\n" + i);
                _.b("                                       class=\"form-control share-text\" id=\"share-iframe-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" value=\"\">");
                _.b("\n" + i);
                _.b("                            </div>");
                _.b("\n" + i);
                _.b("                        </div>");
                _.b("\n" + i);
                _.b("                        <div class=\"form-group\" id=\"group-static-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\">");
                _.b("\n" + i);
                _.b("                            <label for=\"share-static\" class=\"col-sm-1 control-label\"><i");
                _.b("\n" + i);
                _.b("                                    class=\"fa fa-picture-o\"></i></label>");
                _.b("\n" + i);
                _.b("\n" + i);
                _.b("                            <div class=\"col-sm-10\">");
                _.b("\n" + i);
                _.b("                                <input data-toggle=\"tooltip\" data-placement=\"top\"");
                _.b("\n" + i);
                _.b("                                       title=\"URL to a static PNG image of this map\" type=\"text\"");
                _.b("\n" + i);
                _.b("                                       class=\"form-control share-text\" id=\"share-static-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" value=\"\">");
                _.b("\n" + i);
                _.b("                            </div>");
                _.b("\n" + i);
                _.b("                        </div>");
                _.b("\n" + i);
                _.b("                        <div class=\"form-group\" id=\"group-javascript-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\">");
                _.b("\n" + i);
                _.b("                            <label for=\"share-javascript\" class=\"col-sm-1 control-label\">js</label>");
                _.b("\n" + i);
                _.b("\n" + i);
                _.b("                            <div class=\"col-sm-10\">");
                _.b("\n" + i);
                _.b("                                <textarea data-toggle=\"tooltip\" data-placement=\"top\"");
                _.b("\n" + i);
                _.b("                                          title=\"JavaScript for an application\"");
                _.b("\n" + i);
                _.b("                                          class=\"form-control share-text\" id=\"share-javascript-");
                _.b(_.v(_.f("id", c, p, 0)));
                _.b("\" rows=\"6\"");
                _.b("\n" + i);
                _.b("                                          value=\"\"></textarea>");
                _.b("\n" + i);
                _.b("                            </div>");
                _.b("\n" + i);
                _.b("                        </div>");
                _.b("\n" + i);
                _.b("\n" + i);
                _.b("                    </form>");
                _.b("\n" + i);
                _.b("                </div>");
                _.b("\n" + i);
                _.b("                <div class=\"modal-footer\">");
                _.b("\n" + i);
                _.b("                    <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Luk</button>");
                _.b("\n" + i);
                _.b("                </div>");
                _.b("\n" + i);
                _.b("            </div>");
                _.b("\n" + i);
                _.b("            <!-- /.modal-content -->");
                _.b("\n" + i);
                _.b("        </div>");
                _.b("\n" + i);
                _.b("        <!-- /.modal-dialog -->");
                _.b("\n" + i);
                _.b("    </div>");
                _.b("\n" + i);
                _.b("</div>");
                return _.fl();
                ;
            });
            $("#" + gc2RandId).html(templates.body.render({id: gc2RandId}));
            gc2Widget.maps[gc2RandId] = new MapCentia(gc2RandId);
            gc2Widget.maps[gc2RandId].init(defaults);
        };
        pollForScripts = function () {
            if (gc2Widget.scriptsLoaded) {
                init();
            } else {
                setTimeout(pollForScripts, 10);
            }
        };
        pollForScripts();
    };
}());
