if (typeof gc2map === "undefined") {
    var gc2map;
    gc2map = (function () {
        "use strict";
        window.__ = function (string) {
            if (typeof gc2i18n !== 'undefined') {
                if (gc2i18n.dict[string]) {
                    return gc2i18n.dict[string];
                }
            }
            return string;
        };
        var init, js, maps = [],
            scriptsLoaded = false,
            scriptSource = (function () {
                var scripts = document.getElementsByTagName('script'),
                    script = scripts[scripts.length - 1];
                if (script.getAttribute.length !== undefined) {
                    return script.src;
                }
                return script.getAttribute('src', -1);
            }()),
            host;
        if (typeof window.geocloud_host === "undefined") {
            window.geocloud_host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
        }
        host = window.geocloud_host;
        if (typeof $ === "undefined") {
            js = document.createElement("script");
            js.type = "text/javascript";
            js.src = "https://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
            document.getElementsByTagName("head")[0].appendChild(js);
        }
        (function pollForjQuery() {
            if (typeof $ !== "undefined") {
                $.getScript(host + "/api/v1/baselayerjs");
                // @if DEBUG
                $.getScript(host + "/js/leaflet/leaflet-all.js#grunt-cache-bust");
                $.getScript(host + "/js/openlayers/proj4js-combined.js");
                $.getScript(host + "/js/bootstrap3/js/bootstrap.min.js");
                $.getScript(host + "/js/hogan/hogan-2.0.0.js");
                $.getScript(host + "/apps/widgets/gc2map/js/bootstrap-alert.js");
                // @endif
                // @if !DEBUG
                $.getScript(host + "/apps/widgets/gc2map/js/build/all.min.js#grunt-cache-bust");
                // @endif
                (function pollForDependencies() {
                    if (typeof L !== "undefined" &&
                        typeof Proj4js !== "undefined" &&
                        typeof $().emulateTransitionEnd !== 'undefined' &&
                        typeof Hogan !== "undefined" &&
                        typeof window.setBaseLayers !== "undefined"
                    ) {
                        // @if DEBUG
                        $.getScript(host + "/api/v3/js/geocloud.js?a439ffa6e8bc2dd0#grunt-cache-bust");
                        $.getScript(host + "/apps/widgets/gc2map/js/main.js?a431ad6b702c34a3#grunt-cache-bust");
                        $.getScript(host + "/apps/widgets/gc2map/js/templates.js?d65e510c44da571d#grunt-cache-bust");
                        // @endif
                        L.Icon.Default.imagePath = host + "/js/leaflet/images";
                        (function pollForDependants() {
                            if (typeof geocloud !== "undefined" && typeof MapCentia !== "undefined" && (typeof this !== "undefined" && typeof this.Templates !== "undefined")) {
                                scriptsLoaded = true;
                            } else {
                                setTimeout(pollForDependants, 10);
                            }
                        }());
                    } else {
                        setTimeout(pollForDependencies, 10);
                    }
                }());
                // @if DEBUG
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: host + '/apps/widgets/gc2map/css/bootstrap.css?cea63a515abbff15#grunt-cache-bust'
                }).appendTo('head');
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: host + '/apps/widgets/gc2map/css/bootstrap-alert.css?a849390f2a859c66#grunt-cache-bust'
                }).appendTo('head');
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: host + '/apps/widgets/gc2map/css/non-responsive.css?3a2e6af4eca91b40#grunt-cache-bust'
                }).appendTo('head');
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: host + '/apps/widgets/gc2map/css/styles.css?fe15deedc44b317d#grunt-cache-bust'
                }).appendTo('head');
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: host + '/js/leaflet/plugins/markercluster/MarkerCluster.css?fe15deedc44b317d#grunt-cache-bust'
                }).appendTo('head');
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: host + '/js/leaflet/plugins/markercluster/MarkerCluster.Default.css?fe15deedc44b317d#grunt-cache-bust'
                }).appendTo('head');
                // @endif
                // @if !DEBUG
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: host + '/apps/widgets/gc2map/css/build/all.min.css?fe15deedc44b317d#grunt-cache-bust'
                }).appendTo('head');
                // @endif
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: 'https://netdna.bootstrapcdn.com/font-awesome/4.0.1/css/font-awesome.min.css'
                }).appendTo('head');
                $('<link/>').attr({
                    rel: 'stylesheet',
                    type: 'text/css',
                    href: 'https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300'
                }).appendTo('head');
            } else {
                setTimeout(pollForjQuery, 10);
            }
        }());

        init = function (conf) {
            var defaults = {
                    host: host,
                    width: "100%",
                    height: "100%",
                    staticMap: false,
                    infoText: null,
                    infoTextWidth: "200px",
                    locale: null,
                    key: null,
                    clickDistance: 5,
                    baseLayers: null,
                    template: "body.tmpl",
                    callBack: function () {
                    }
                },
                prop,
                divs,
                div,
                gc2RandId;
            if (conf) {
                for (prop in conf) {
                    defaults[prop] = conf[prop];
                }
            }
            gc2RandId = (defaults.key !== null) ? defaults.key : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
            if (defaults.key === null) {
                divs = document.getElementsByTagName('div');
                div = divs[divs.length - 1];
                div.setAttribute("id", gc2RandId);
            } else {
                div = document.getElementById(defaults.key);
            }
            div.classList.add("gc2map");
            div.style.width = defaults.width;
            div.style.height = defaults.height;
            if (div.style.position === "") {
                div.style.position = "relative";
            }
            (function pollForScripts() {
                var i = 0;
                if (scriptsLoaded) {
                    var context;
                    $.getScript(host + "/js/i18n/" + (defaults.locale || window.gc2Al) + ".js");
                    (function pollForDict() {
                        if (typeof gc2i18n !== "undefined") {
                            context = gc2i18n.dict;
                            context.id = gc2RandId;
                            context.infoText = defaults.infoText;
                            context.infoTextWidth = defaults.infoTextWidth;
                            context.height = defaults.height;
                            if (defaults.staticMap) {
                                if (typeof defaults.extent === "object") {
                                    var p1 = geocloud.transformPoint(defaults.extent[0], defaults.extent[1], "EPSG:4326", "EPSG:900913");
                                    var p2 = geocloud.transformPoint(defaults.extent[2], defaults.extent[3], "EPSG:4326", "EPSG:900913");
                                    defaults.extent = [p1.x, p1.y, p2.x, p2.y];
                                    $("#" + gc2RandId).html("<img src='" + defaults.host + "/api/v1/staticmap/png/" + defaults.db + "?baselayer=" + defaults.setBaseLayer.toUpperCase() + "&layers=" + defaults.layers.join(",") + "&size=" + $("#" + gc2RandId).width() + "x" + $("#" + gc2RandId).height() + "&bbox=" + defaults.extent.join(",") + "&lifetime=10'>");
                                } else if (typeof defaults.zoom === "object") {
                                    $("#" + gc2RandId).html("<img src='" + defaults.host + "/api/v1/staticmap/png/" + defaults.db + "?baselayer=" + defaults.setBaseLayer.toUpperCase() + "&layers=" + defaults.layers.join(",") + "&size=" + $("#" + gc2RandId).width() + "x" + $("#" + gc2RandId).height() + "&zoom=" + defaults.zoom[2] + "&center=" + defaults.zoom[1] + "," + defaults.zoom[0] + "&lifetime=10'>");
                                }
                            } else {
                                $("#" + gc2RandId).html(this.Templates[defaults.template].render(context));
                                if (defaults.infoText) {
                                    $("#info-text-" + gc2RandId).show();
                                }
                                maps[gc2RandId] = new MapCentia(gc2RandId);
                                maps[gc2RandId].init(defaults);
                            }
                        } else {
                            i = i + 1;
                            if (i > 1000) {
                                alert("Seems that i18n file " + (defaults.locale || window.gc2Al) + ".js is not loading. Please check your locale setting.");
                            } else {
                                setTimeout(pollForDict, 10);
                            }
                        }
                    }());
                } else {
                    setTimeout(pollForScripts, 10);
                }
            }());
            return {
                maps: maps
            };
        };
        return {
            maps: maps,
            init: init
        };
    }());
}
/*! @source http://purl.eligrey.com/github/classList.js/blob/master/classList.js*/
if ("document" in self && !("classList" in document.createElement("_"))) {
    (function (j) {
        "use strict";
        if (!("Element" in j)) {
            return
        }
        var a = "classList", f = "prototype", m = j.Element[f], b = Object, k = String[f].trim || function () {
                return this.replace(/^\s+|\s+$/g, "")
            }, c = Array[f].indexOf || function (q) {
                var p = 0, o = this.length;
                for (; p < o; p++) {
                    if (p in this && this[p] === q) {
                        return p
                    }
                }
                return -1
            }, n = function (o, p) {
            this.name = o;
            this.code = DOMException[o];
            this.message = p
        }, g = function (p, o) {
            if (o === "") {
                throw new n("SYNTAX_ERR", "An invalid or illegal string was specified")
            }
            if (/\s/.test(o)) {
                throw new n("INVALID_CHARACTER_ERR", "String contains an invalid character")
            }
            return c.call(p, o)
        }, d = function (s) {
            var r = k.call(s.getAttribute("class") || ""), q = r ? r.split(/\s+/) : [], p = 0, o = q.length;
            for (; p < o; p++) {
                this.push(q[p])
            }
            this._updateClassName = function () {
                s.setAttribute("class", this.toString())
            }
        }, e = d[f] = [], i = function () {
            return new d(this)
        };
        n[f] = Error[f];
        e.item = function (o) {
            return this[o] || null
        };
        e.contains = function (o) {
            o += "";
            return g(this, o) !== -1
        };
        e.add = function () {
            var s = arguments, r = 0, p = s.length, q, o = false;
            do {
                q = s[r] + "";
                if (g(this, q) === -1) {
                    this.push(q);
                    o = true
                }
            } while (++r < p);
            if (o) {
                this._updateClassName()
            }
        };
        e.remove = function () {
            var t = arguments, s = 0, p = t.length, r, o = false;
            do {
                r = t[s] + "";
                var q = g(this, r);
                if (q !== -1) {
                    this.splice(q, 1);
                    o = true
                }
            } while (++s < p);
            if (o) {
                this._updateClassName()
            }
        };
        e.toggle = function (p, q) {
            p += "";
            var o = this.contains(p), r = o ? q !== true && "remove" : q !== false && "add";
            if (r) {
                this[r](p)
            }
            return !o
        };
        e.toString = function () {
            return this.join(" ")
        };
        if (b.defineProperty) {
            var l = {get: i, enumerable: true, configurable: true};
            try {
                b.defineProperty(m, a, l)
            } catch (h) {
                if (h.number === -2146823252) {
                    l.enumerable = false;
                    b.defineProperty(m, a, l)
                }
            }
        } else {
            if (b[f].__defineGetter__) {
                m.__defineGetter__(a, i)
            }
        }
    }(self));
}