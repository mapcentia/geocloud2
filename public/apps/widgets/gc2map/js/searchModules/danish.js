gc2map.createSearch = function (me, komKode) {
    var type1, type2, gids = [], searchString, dslM, shouldA = [], shouldM = [], dsl1, dsl2, placeStore;
    var AHOST = "https://dk.gc2.io";
    var ADB = "dk";
    var MHOST = "https://dk.gc2.io";
    var MDB = "dk";
    var onlyAddress = false;
    placeStore = new geocloud.geoJsonStore({
        host: "//dk.gc2.io",
        db: "dk",
        sql: null,
        pointToLayer: null,
        onLoad: function () {
            var resultLayer = new L.FeatureGroup();
            me.map.addLayer(resultLayer);
            resultLayer.addLayer(this.layer);
            me.zoomToExtentOfgeoJsonStore(this);
            if (me.map.getZoom() > 18) {
                me.map.setZoom(18);
            }
        }
    });
    $("#custom-search-form").show();
    $('#custom-search').typeahead({
        highlight: false
    }, {
        name: 'adresse',
        displayKey: 'value',
        templates: {
            header: '<h2 class="typeahead-heading">Adresser</h2>'
        },
        source: function (query, cb) {
            if (query.match(/\d+/g) === null && query.match(/\s+/g) === null) {
                type1 = "vejnavn,bynavn";
            }
            if (query.match(/\d+/g) === null && query.match(/\s+/g) !== null) {
                type1 = "vejnavn_bynavn";
            }
            if (query.match(/\d+/g) !== null) {
                type1 = "adresse";
            }
            var names = [];
            (function ca() {
                switch (type1) {
                    case "vejnavn,bynavn":
                        dsl1 = {
                            "from": 0,
                            "size": 7,
                            "query": {
                                "bool": {
                                    "must": {
                                        "query_string": {
                                            "default_field": "properties.string2",
                                            "query": query.toLowerCase().replace(",", ""),
                                            "default_operator": "AND"
                                        }
                                    },
                                    "filter": {
                                        "bool": {
                                            "should": shouldA
                                        }
                                    }
                                }
                            },
                            "aggregations": {
                                "properties.postnrnavn": {
                                    "terms": {
                                        "field": "properties.postnrnavn",
                                        "size": 7,
                                        "order": {
                                            "_term": "asc"
                                        }
                                    },
                                    "aggregations": {
                                        "properties.postnr": {
                                            "terms": {
                                                "field": "properties.postnr",
                                                "size": 7
                                            },
                                            "aggregations": {
                                                "properties.kommunekode": {
                                                    "terms": {
                                                        "field": "properties.kommunekode",
                                                        "size": 7
                                                    },
                                                    "aggregations": {
                                                        "properties.regionskode": {
                                                            "terms": {
                                                                "field": "properties.regionskode",
                                                                "size": 7
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        };
                        dsl2 = {
                            "from": 0,
                            "size": 7,
                            "query": {
                                "bool": {
                                    "must": {
                                        "query_string": {
                                            "default_field": "properties.string3",
                                            "query": query.toLowerCase().replace(",", ""),
                                            "default_operator": "AND"
                                        }
                                    },
                                    "filter": {
                                        "bool": {
                                            "should": shouldA
                                        }
                                    }
                                }
                            },
                            "aggregations": {
                                "properties.vejnavn": {
                                    "terms": {
                                        "field": "properties.vejnavn",
                                        "size": 7,
                                        "order": {
                                            "_term": "asc"
                                        }
                                    },
                                    "aggregations": {
                                        "properties.kommunekode": {
                                            "terms": {
                                                "field": "properties.kommunekode",
                                                "size": 7
                                            },
                                            "aggregations": {
                                                "properties.regionskode": {
                                                    "terms": {
                                                        "field": "properties.regionskode",
                                                        "size": 7
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        };
                        break;
                    case "vejnavn_bynavn":
                        dsl1 = {
                            "from": 0,
                            "size": 7,
                            "query": {
                                "bool": {
                                    "must": {
                                        "query_string": {
                                            "default_field": "properties.string1",
                                            "query": query.toLowerCase().replace(",", ""),
                                            "default_operator": "AND"
                                        }
                                    },
                                    "filter": {
                                        "bool": {
                                            "should": shouldA
                                        }
                                    }
                                }
                            },
                            "aggregations": {
                                "properties.vejnavn": {
                                    "terms": {
                                        "field": "properties.vejnavn",
                                        "size": 7,
                                        "order": {
                                            "_term": "asc"
                                        }
                                    },
                                    "aggregations": {
                                        "properties.postnrnavn": {
                                            "terms": {
                                                "field": "properties.postnrnavn",
                                                "size": 7
                                            },
                                            "aggregations": {
                                                "properties.kommunekode": {
                                                    "terms": {
                                                        "field": "properties.kommunekode",
                                                        "size": 7
                                                    },
                                                    "aggregations": {
                                                        "properties.regionskode": {
                                                            "terms": {
                                                                "field": "properties.regionskode",
                                                                "size": 7
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        };
                        break;
                    case "adresse":
                        dsl1 = {
                            "from": 0,
                            "size": 7,
                            "query": {
                                "bool": {
                                    "must": {
                                        "query_string": {
                                            "default_field": "properties.string5",
                                            "query": query.toLowerCase().replace(",", ""),
                                            "default_operator": "AND"
                                        }
                                    },
                                    "filter": {
                                        "bool": {
                                            "should": shouldA
                                        }
                                    }
                                }
                            },

                            "sort": [
                                {
                                    "properties.vejnavn": {
                                        "order": "asc"
                                    }
                                },
                                {
                                    "properties.husnr": {
                                        "order": "asc"
                                    }
                                },
                                {
                                    "properties.litra": {
                                        "order": "asc"
                                    }
                                }
                            ]
                        };
                        break;
                }

                $.ajax({
                    url: AHOST + '/api/v2/elasticsearch/search/' + ADB + '/dar/adgangsadresser_view',
                    data: JSON.stringify(dsl1),
                    contentType: "application/json; charset=utf-8",
                    scriptCharset: "utf-8",
                    dataType: 'json',
                    type: "POST",
                    success: function (response) {
                        if (response.hits === undefined) return;
                        if (type1 === "vejnavn,bynavn") {
                            $.each(response.aggregations["properties.postnrnavn"].buckets, function (i, hit) {
                                var str = hit.key;
                                names.push({value: str});
                            });
                            $.ajax({
                                url: AHOST + '/api/v2/elasticsearch/search/' + ADB + '/dar/adgangsadresser_view',
                                data: JSON.stringify(dsl2),
                                contentType: "application/json; charset=utf-8",
                                scriptCharset: "utf-8",
                                dataType: 'json',
                                type: "POST",
                                success: function (response) {
                                    if (response.hits === undefined) return;

                                    if (type1 === "vejnavn,bynavn") {
                                        $.each(response.aggregations["properties.vejnavn"].buckets, function (i, hit) {
                                            var str = hit.key;
                                            names.push({value: str});
                                        });
                                    }
                                    if (names.length === 1 && (type1 === "vejnavn,bynavn" || type1 === "vejnavn_bynavn")) {
                                        type1 = "adresse";
                                        names = [];
                                        gids = [];
                                        ca();
                                    } else {
                                        cb(names);
                                    }

                                }
                            })
                        } else if (type1 === "vejnavn_bynavn") {
                            $.each(response.aggregations["properties.vejnavn"].buckets, function (i, hit) {
                                var str = hit.key;
                                $.each(hit["properties.postnrnavn"].buckets, function (m, n) {
                                    var tmp = str;
                                    tmp = tmp + ", " + n.key;
                                    names.push({value: tmp});
                                });

                            });
                            if (names.length === 1 && (type1 === "vejnavn,bynavn" || type1 === "vejnavn_bynavn")) {
                                type1 = "adresse";
                                names = [];
                                gids = [];
                                ca();
                            } else {
                                cb(names);
                            }

                        } else if (type1 === "adresse") {
                            $.each(response.hits.hits, function (i, hit) {
                                var str = hit._source.properties.string4;
                                gids[str] = hit._source.properties.gid;
                                names.push({value: str});
                            });
                            if (names.length === 1 && (type1 === "vejnavn,bynavn" || type1 === "vejnavn_bynavn")) {
                                type1 = "adresse";
                                names = [];
                                gids = [];
                                ca();
                            } else {
                                cb(names);
                            }
                        }

                    }
                })
            })();
        }
    }, {
        name: 'matrikel',
        displayKey: 'value',
        templates: {
            header: '<h2 class="typeahead-heading">Matrikel</h2>'
        },
        source: function (query, cb) {
            var names = [];
            type2 = (query.match(/\d+/g) != null) ? "jordstykke" : "ejerlav";
            if (!onlyAddress) {
                (function ca() {

                    switch (type2) {
                        case "jordstykke":
                            dslM = {
                                "from": 0,
                                "size": 7,
                                "query": {
                                    "bool": {
                                        "must": {
                                            "query_string": {
                                                "default_field": "properties.string1",
                                                "query": query.toLowerCase(),
                                                "default_operator": "AND"
                                            }
                                        },
                                        "filter": {
                                            "bool": {
                                                "should": shouldM
                                            }
                                        }
                                    }
                                },
                                "sort": [
                                    {
                                        "properties.nummer": {
                                            "order": "asc"
                                        }
                                    },
                                    {
                                        "properties.litra": {
                                            "order": "asc"
                                        }
                                    },
                                    {
                                        "properties.ejerlavsnavn": {
                                            "order": "asc"
                                        }
                                    }
                                ]
                            };
                            break;
                        case "ejerlav":
                            dslM = {
                                "from": 0,
                                "size": 7,
                                "query": {
                                    "bool": {
                                        "must": {
                                            "query_string": {
                                                "default_field": "properties.string1",
                                                "query": query.toLowerCase(),
                                                "default_operator": "AND"
                                            }
                                        },
                                        "filter": {
                                            "bool": {
                                                "should": shouldM
                                            }
                                        }
                                    }
                                },
                                "aggregations": {
                                    "properties.ejerlavsnavn": {
                                        "terms": {
                                            "field": "properties.ejerlavsnavn",
                                            "order": {
                                                "_term": "asc"
                                            },
                                            "size": 7
                                        },
                                        "aggregations": {
                                            "properties.kommunekode": {
                                                "terms": {
                                                    "field": "properties.kommunekode",
                                                    "size": 7
                                                }
                                            }
                                        }
                                    }
                                }
                            };
                            break;
                    }

                    $.ajax({
                        url: MHOST + '/api/v2/elasticsearch/search/' + MDB + '/matrikel',
                        data: JSON.stringify(dslM),
                        contentType: "application/json; charset=utf-8",
                        scriptCharset: "utf-8",
                        dataType: 'json',
                        type: "POST",
                        success: function (response) {
                            if (response.hits === undefined) return;
                            if (type2 === "ejerlav") {
                                $.each(response.aggregations["properties.ejerlavsnavn"].buckets, function (i, hit) {
                                    var str = hit.key;
                                    names.push({value: str});
                                });
                            } else {
                                $.each(response.hits.hits, function (i, hit) {
                                    var str = hit._source.properties.string1;
                                    gids[str] = hit._source.properties.gid;
                                    names.push({value: str});
                                });
                            }
                            if (names.length === 1 && (type2 === "ejerlav")) {
                                type2 = "jordstykke";
                                names = [];
                                gids = [];
                                ca();
                            } else {
                                cb(names);
                            }

                        }
                    })
                })();
            }
        }
    });
    $('#custom-search').bind('typeahead:selected', function (obj, datum, name) {
        if ((type1 === "adresse" && name === "adresse") || (type2 === "jordstykke" && name === "matrikel")) {
            placeStore.reset();
            switch (name) {
                case "matrikel" :
                    placeStore.db = MDB;
                    placeStore.host = MHOST;
                    searchString = datum.value;
                    placeStore.sql = "SELECT gid,the_geom,ST_asgeojson(ST_transform(the_geom,4326)) as geojson FROM matrikel.jordstykke WHERE gid='" + gids[datum.value] + "'";
                    placeStore.load();
                    break;
                case "adresse" :
                    placeStore.db = ADB;
                    placeStore.host = AHOST;
                    placeStore.sql = "SELECT id,kommunekode,the_geom,ST_asgeojson(ST_transform(the_geom,4326)) as geojson FROM dar.adgangsadresser WHERE id='" + gids[datum.value] + "'";
                    searchString = datum.value;
                    placeStore.load();
                    break;

            }
        } else {
            setTimeout(function () {
                $(".typeahead").val(datum.value + " ").trigger("paste").trigger("input");
            }, 100);
        }
    });
};
