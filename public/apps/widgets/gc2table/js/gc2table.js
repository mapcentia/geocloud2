/*global $:false */
/*global jQuery:false */
/*global Backbone:false */
/*global jRespond:false */
/*global window:false */
/*global console:false */
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
        js.src = "//ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
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
            if (typeof _ === "undefined") {
                $.getScript(host + "/js/underscore/underscore-min.js");
            }
            if (typeof jRespond === "undefined") {
                $.getScript(host + "/js/div/jRespond.js");
            }
            (function pollForDependencies() {
                if (typeof jQuery().typeahead !== "undefined" &&
                    typeof jQuery().bootstrapTable !== "undefined" &&
                    typeof jQuery().bootstrapTable.locales !== "undefined" &&
                    typeof _ !== 'undefined' &&
                    typeof jRespond !== "undefined") {
                    if (typeof jQuery().bootstrapTable.locales['da-DK'] === "undefined") {
                        $.getScript(host + "/js/bootstrap-table/bootstrap-table-locale-all.js");
                    }
                    if (typeof jQuery().bootstrapTable.defaults.filterControl === "undefined") {
                        $.getScript(host + "/js/bootstrap-table/extensions/filter-control/bootstrap-table-filter-control.js");
                    }
                    if (typeof jQuery().bootstrapTable.defaults.exportDataType === "undefined") {
                        $.getScript(host + "/js/bootstrap-table/extensions/export/bootstrap-table-export.min.js");
                    }
                    if (typeof jQuery().tableExport === "undefined") {
                        $.getScript(host + "/js/tableExport.jquery.plugin/tableExport.min.js");
                    }
                    if (typeof Backbone === "undefined") {
                        $.getScript("//cdnjs.cloudflare.com/ajax/libs/backbone.js/1.3.3/backbone-min.js");
                    }
                    (function pollForDependants() {
                        if (typeof jQuery().bootstrapTable.defaults.filterControl !== "undefined" &&
                            typeof jQuery().bootstrapTable.defaults.exportDataType !== "undefined" &&
                            typeof jQuery().tableExport !== "undefined" &&
                            typeof jQuery().bootstrapTable.locales['da-DK'] !== "undefined" &&
                            typeof Backbone !== "undefined") {
                            scriptsLoaded = true;
                        } else {
                            setTimeout(pollForDependants, 10);
                        }
                    }());
                } else {
                    setTimeout(pollForDependencies, 10);
                }
            }());

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
                locale: 'en-US',
                callCustomOnload: true,
                popupHtml: null,
                ns: "",
                template: null,
                usingCarto: false,
                onSelect: function () {
                },
                onMouseOver: function () {
                },
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
            tableBodyHeight = defaults.tableBodyHeight,
            styleSelected = defaults.styleSelected,
            el = defaults.el, click, loadDataInTable, moveEndOff, moveEndOn,
            setSelectedStyle = defaults.setSelectedStyle,
            setViewOnSelect = defaults.setViewOnSelect,
            onSelect = defaults.onSelect,
            onMouseOver = defaults.onMouseOver,
            openPopUp = defaults.openPopUp,
            autoPan = defaults.autoPan,
            responsive = defaults.responsive,
            callCustomOnload = defaults.callCustomOnload,
            locale = defaults.locale,
            popupHtml = defaults.popupHtml,
            ns = defaults.ns,
            template = defaults.template,
            usingCartodb = defaults.usingCartodb;

        $(el).parent("div").addClass("gc2map");

        (function poll() {
            if (scriptsLoaded) {
                var originalLayers, filters, filterControls;
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
                        var str = "<table>", renderedText;
                        $.each(cm, function (i, v) {
                            if (typeof v.showInPopup === "undefined" || (typeof v.showInPopup === "boolean" && v.showInPopup === true)) {
                                str = str + "<tr><td>" + v.header + "</td><td>" + m.map._layers[id].feature.properties[v.dataIndex] + "</td></tr>";
                            }
                        });
                        str = str + "</table>";

                        if (template) {
                            renderedText = Mustache.render(template, m.map._layers[id].feature.properties);
                            if (usingCartodb) {
                                renderedText = $.parseHTML(renderedText)[0].children[1].innerHTML
                            }
                        }

                        m.map._layers[id].bindPopup(renderedText || str, {
                            className: "custom-popup",
                            autoPan: autoPan,
                            closeButton: true
                        }).openPopup();

                        object.trigger("openpopup" + "_" + uid, m.map._layers[id]);
                    }

                });
                click = function (e) {
                    var row = $('*[data-uniqueid="' + e.target._leaflet_id + '"]');
                    $(ns + " .fixed-table-body").animate({
                        scrollTop: $(ns + " .fixed-table-body").scrollTop() + (row.offset().top - $(ns + " .fixed-table-body").offset().top)
                    }, 300);
                    object.trigger("selected" + "_" + uid, e.target._leaflet_id);
                };
                $(el).append("<thead><tr></tr></thead>");
                $.each(cm, function (i, v) {
                    $(el + ' thead tr').append("<th data-filter-control=" + (v.filterControl || "false") + " data-field='" + v.dataIndex + "' data-sortable='" + (v.sortable || "false") + "' data-editable='false' data-formatter='" + (v.formatter || "") + "'>" + v.header + "</th>");
                });

                var filterMap =
                    _.debounce(function () {
                        var visibleRows = [];
                        $.each(store.layer._layers, function (i, v) {
                            m.map.removeLayer(v);
                        });
                        $.each(originalLayers, function (i, v) {
                            m.map.addLayer(v);
                        });
                        filters = {};
                        filterControls = {};
                        $.each(cm, function (i, v) {
                            if (v.filterControl) {
                                filters[v.dataIndex] = $(".bootstrap-table-filter-control-" + v.dataIndex).val();
                                filterControls[v.dataIndex] = v.filterControl;
                            }
                        });
                        $.each($(el + " tbody").children(), function (x, y) {
                            visibleRows.push($(y).attr("data-uniqueid"));
                        });
                        $.each(store.layer._layers, function (i, v) {
                            if (visibleRows.indexOf(v._leaflet_id + "") === -1) {
                                m.map.removeLayer(v);
                            }
                        });
                        bindEvent();
                    }, 500);

                var bindEvent = function (e) {
                    setTimeout(function () {

                        $(el + ' > tbody > tr').on("click", function (e) {
                            var id = $(this).data('uniqueid');
                            object.trigger("selected" + "_" + uid, id);
                            var layer = m.map._layers[id];
                            setTimeout(function () {
                                if (setViewOnSelect) {
                                    m.map.fitBounds(layer.getBounds());
                                }
                            }, 100);
                            onSelect(id, layer);
                        });

                    }, 100);
                };
                $(el).bootstrapTable({
                    uniqueId: "_id",
                    height: height,
                    locale: locale,
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
                loadDataInTable = function (doNotCallCustomOnload) {
                    data = [];
                    $.each(store.layer._layers, function (i, v) {
                        v.feature.properties._id = i;
                        $.each(v.feature.properties, function (n, m) {
                            $.each(cm, function (j, k) {
                                if (k.dataIndex === n && ((typeof k.link === "boolean" && k.link === true) || (typeof k.link === "string"))) {
                                    v.feature.properties[n] = "<a target='_blank' rel='noopener' href='" + v.feature.properties[n] + "'>" + (typeof k.link === "string" ? k.link : "Link") + "</a>";
                                }

                            });
                        });
                        data.push(v.feature.properties);
                        v.on({
                            click: click
                        });

                    });

                    originalLayers = jQuery.extend(true, {}, store.layer._layers);

                    $(el).bootstrapTable("load", data);

                    bindEvent();

                    if (callCustomOnload && !doNotCallCustomOnload) {
                        customOnLoad(store);
                    }

                    $(".fixed-table-body").css("overflow", "auto");
                    $(".fixed-table-body").css("max-height", tableBodyHeight + "px");
                    $(".fixed-table-body").css("height", tableBodyHeight + "px");

                };

                var moveEndEvent = function () {
                    store.reset();
                    store.load();
                };

                moveEndOff = function () {
                    m.map.off("moveend", moveEndEvent);
                };

                moveEndOn = function () {
                    m.on("moveend", moveEndEvent);
                };

                if (autoUpdate) {
                    m.on("moveend", moveEndEvent);
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
            uid: uid,
            store: store,
            moveEndOff: moveEndOff,
            moveEndOn: moveEndOn
        };
    };
    return {
        init: init,
        isLoaded: isLoaded
    };
}());
