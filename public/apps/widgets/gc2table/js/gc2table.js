/*global $:false */
/*global jQuery:false */
/*global Backbone:false */
/*global jRespond:false */
/*global _:false */
var gc2table = (function () {
    "use strict";
    var host, js, isLoaded, object, init,
        scriptsLoaded = false,
        scriptSource = (function (scripts) {
            scripts = document.getElementsByTagName('script');
            var script = scripts[scripts.length - 1];
            if (script.getAttribute.length !== undefined) {
                return script.src;
            }
            return script.getAttribute('src', -1);
        }());

    // Try to set host from script if not set already
    if (typeof window.geocloud_host === "undefined") {
        host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
    } else {
        host = window.geocloud_host;
    }

    if (typeof $ === "undefined") {
        js = document.createElement("script");
        js.type = "text/javascript";
        js.src = "https://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
        document.getElementsByTagName("head")[0].appendChild(js);
    }
    (function pollForjQuery() {
        if (typeof jQuery !== "undefined") {
            if (typeof jQuery().typeahead === "undefined") {
                $.getScript(host + "/js/typeahead.js-0.10.5/dist/typeahead.bundle.js");
            }
            if (typeof jQuery().bootstrapTable === "undefined") {
                $.getScript(host + "/js/bootstrap-table/bootstrap-table.js");
            }
            if (typeof _ === 'undefined') {
                $.getScript(host + "/js/underscore/underscore-min.js");
            }
            if (typeof jRespond === "undefined") {
                $.getScript(host + "/js/div/jRespond.js");
            }
            if (typeof Backbone === "undefined") {
                $.getScript("http://backbonejs.org/backbone.js");
            }
            (function pollForDependencies() {
                if (typeof jQuery().typeahead !== "undefined" &&
                    typeof jQuery().bootstrapTable !== "undefined" &&
                    typeof _ !== 'undefined' &&
                    typeof jRespond !== "undefined" &&
                    typeof Backbone !== "undefined") {
                    if (typeof jQuery().bootstrapTable.defaults.filterControl === "undefined") {
                        $.getScript(host + "/js/bootstrap-table/extensions/filter-control/bootstrap-table-filter-control.js");
                    }
                    (function pollForDependants() {
                        if (typeof jQuery().bootstrapTable.defaults.filterControl !== "undefined") {
                            scriptsLoaded = true;
                        } else {
                            setTimeout(pollForDependants, 10);
                        }
                    }());
                } else {
                    setTimeout(pollForDependencies, 10);
                }
            }());
            $('<link/>').attr({
                rel: 'stylesheet',
                type: 'text/css',
                href: host + '/js/bootstrap-table/bootstrap-table.min.css'
            }).appendTo('head');
        } else {
            setTimeout(pollForjQuery, 10);
        }
    }());

    isLoaded = function () {
        return scriptsLoaded;
    };
    object = {};
    init = function (conf) {
        var defaults = {
            el: "#table",
            autoUpdate: false,
            height: 300,
            setSelectedStyle: true,
            openPopUp: false,
            setViewOnSelect: true,
            responsive: true,
            autoPan: false,
            styleSelected: {
                weight: 5,
                color: '#666',
                dashArray: '',
                fillOpacity: 0.7
            }
        }, prop,
 	uid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        	var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
        	return v.toString(16);
    	});
        if (conf) {
            for (prop in conf) {
                defaults[prop] = conf[prop];
            }
        }
        var data,
            m = defaults.geocloud2,
            store = defaults.store,
            cm = defaults.cm,
            autoUpdate = defaults.autoUpdate,
            height = defaults.height,
            styleSelected = defaults.styleSelected,
            el = defaults.el, click, loadDataInTable,
            setSelectedStyle = defaults.setSelectedStyle,
            setViewOnSelect = defaults.setViewOnSelect,
            openPopUp = defaults.openPopUp,
            autoPan = defaults.autoPan,
            responsive = defaults.responsive;

        (function poll() {
            if (scriptsLoaded) {
                var originalLayers, filters;
                _.extend(object, Backbone.Events);
                object.on("selected" + "_" + uid, function (id) {
                    $(el + ' tr').removeClass("selected");
                    $.each(store.layer._layers, function (i, v) {
                        try {
                            store.layer.resetStyle(v);
                        } catch (e) {
                        }
                    });
                    var row = $('*[data-uniqueid="' + id + '"]');
                    row.addClass("selected");
                    if (setSelectedStyle) {
                        m.map._layers[id].setStyle(styleSelected);
                    }
                    if (openPopUp) {
                        var str = "<table>";
                        $.each(cm, function (i, v) {
                            str = str + "<tr><td>" + v.header + "</td><td>" + m.map._layers[id].feature.properties[v.dataIndex] + "</td></tr>";
                        });
                        str = str + "</table>";
                        m.map._layers[id].bindPopup(str, {
                            className: "custom-popup",
                            autoPan: autoPan,
                            closeButton: true
                        }).openPopup();
                    }

                });

                click = function (e) {
                    var row = $('*[data-uniqueid="' + e.target._leaflet_id + '"]');
                    $(el).bootstrapTable('scrollTo', row.index() * row.height());
                    object.trigger("selected" + "_" + uid, e.target._leaflet_id);
                };
                $.each(store.layer._layers, function (i, layer) {
                    layer.on({
                        click: click
                    });
                });

                $(el).append("<thead><tr></tr></thead>");
                $.each(cm, function (i, v) {
                    $(el + ' thead tr').append("<th data-filter-control=" + (v.filterControl || "false") + " data-field='" + v.dataIndex + "' data-sortable='" + (v.sortable || "false") + "' data-editable='false' data-formatter='" + (v.formatter || "") + "'>" + v.header + "</th>");
                });

                var filterMap = function () {
                    $.each(store.layer._layers, function (i, v) {
                        m.map.removeLayer(v);
                    });
                    $.each(originalLayers, function (i, v) {
                        m.map.addLayer(v);
                    });
                    filters = {};
                    $.each(cm, function (i, v) {
                        if (v.filterControl) {
                            filters[v.dataIndex] = $("." + v.dataIndex).val();
                        }
                    });
                    $.each(store.layer._layers, function (i, v) {
                        $.each(v.feature.properties, function (u, n) {
                            if (typeof filters[u] !== "undefined" && filters[u] !== null && filters[u] !== n && filters[u] !== "") {
                                m.map.removeLayer(v);
                            }

                        });
                    });
                    bindEvent();
                };

                var bindEvent = function (e) {
                    setTimeout(function () {
                        $(el + ' tr').on("click", function (e) {
                            object.trigger("selected" + "_" + uid, $(this).data('uniqueid'));
                            var layer = m.map._layers[$(this).data('uniqueid')];
                            setTimeout(function () {
                                if (setViewOnSelect) {
                                    m.map.fitBounds(layer.getBounds());
                                }
                            }, 100);
                        });
                    }, 100);
                };
                $(el).bootstrapTable({
                    uniqueId: "_id",
                    height: height,
                    onToggle: bindEvent,
                    onSort: bindEvent,
                    onColumnSwitch: bindEvent,
                    onColumnSearch: filterMap
                });

                // Define a callback for when the SQL returns
                var customOnLoad = store.onLoad;
                store.onLoad = function () {
                    loadDataInTable();
                };
                loadDataInTable = function () {
                    data = [];
                    //customOnLoad();
                    $.each(store.layer._layers, function (i, v) {
                        v.feature.properties._id = i;
                        $.each(v.feature.properties, function (n, m) {
                            $.each(cm, function (j, k) {
                                if (k.dataIndex === n && k.link === true) {
                                    v.feature.properties[n] = "<a target='_blank' rel='noopener' href='" + v.feature.properties[n] + "'>" + "Link" + "</a>";
                                }

                            });
                        });
                        data.push(v.feature.properties);
                        v.on({
                            click: click
                        });

                    });
                    originalLayers = jQuery.extend(true, {}, store.layer._layers);
                    $(el).bootstrapTable('load', data);
                    filterMap();
                };
                loadDataInTable();
                if (autoUpdate) {
                    m.on("moveend", _.debounce(function () {
                            store.reset();
                            store.load();
                        }, 200)
                    );
                }
                var jRes = jRespond([
                    {
                        label: 'handheld',
                        enter: 0,
                        exit: 400
                    },
                    {
                        label: 'desktop',
                        enter: 401,
                        exit: 100000
                    }
                ]);
                if (responsive) {
                    jRes.addFunc({
                        breakpoint: ['handheld'],
                        enter: function () {
                            $(el).bootstrapTable('toggleView');
                        },
                        exit: function () {
                            $(el).bootstrapTable('toggleView');
                        }
                    });
                    jRes.addFunc({
                        breakpoint: ['desktop'],
                        enter: function () {
                        },
                        exit: function () {
                        }
                    });
                }
            } else {
                setTimeout(poll, 20);
            }
        }());
        return {
            loadDataInTable: loadDataInTable,
            object: object,
	    uid: uid
        };
    };
    return {
        init: init,
        isLoaded: isLoaded
    };
}());
