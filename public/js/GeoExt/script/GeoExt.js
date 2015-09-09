Ext.namespace("GeoExt");
GeoExt.LayerLegend = Ext.extend(Ext.Container, {
    layerRecord: null,
    showTitle: true,
    legendTitle: null,
    labelCls: null,
    layerStore: null,
    initComponent: function () {
        GeoExt.LayerLegend.superclass.initComponent.call(this);
        this.autoEl = {};
        this.add({
            xtype: "label",
            html: this.getLayerTitle(this.layerRecord),
            cls: "x-form-item x-form-item-label" + (this.labelCls ? " " + this.labelCls : "")
        });
        if (this.layerRecord && this.layerRecord.store) {
            this.layerStore = this.layerRecord.store;
            this.layerStore.on("update", this.onStoreUpdate, this);
            this.layerStore.on("add", this.onStoreAdd, this);
            this.layerStore.on("remove", this.onStoreRemove, this)
        }
    },
    getLabel: function () {
        var a = this.items.get(0);
        return a.rendered ? a.el.dom.innerHTML : a.html
    },
    onStoreRemove: function (b, a, c) {
    },
    onStoreAdd: function (b, a, c) {
    },
    onStoreUpdate: function (c, a, b) {
        if (a === this.layerRecord && this.items.getCount() > 0) {
            var d = a.getLayer();
            this.setVisible(d.getVisibility() && d.calculateInRange() && d.displayInLayerSwitcher && !a.get("hideInLegend"));
            this.update()
        }
    },
    update: function () {
        var b = this.getLayerTitle(this.layerRecord);
        var a = this.items.get(0);
        if (a instanceof Ext.form.Label && this.getLabel() !== b) {
            a.setText(b, false)
        }
    },
    getLayerTitle: function (a) {
        var b = this.legendTitle || "";
        if (this.showTitle && !b) {
            if (a && !a.get("hideTitle")) {
                b = a.getLayer().title || a.get("title") || a.get("name") || a.getLayer().name || ""
            }
        }
        return b
    },
    beforeDestroy: function () {
        if (this.layerStore) {
            this.layerStore.un("update", this.onStoreUpdate, this);
            this.layerStore.un("remove", this.onStoreRemove, this);
            this.layerStore.un("add", this.onStoreAdd, this)
        }
        GeoExt.LayerLegend.superclass.beforeDestroy.apply(this, arguments)
    },
    onDestroy: function () {
        this.layerRecord = null;
        this.layerStore = null;
        GeoExt.LayerLegend.superclass.onDestroy.apply(this, arguments)
    }
});
GeoExt.LayerLegend.getTypes = function (c, a) {
    var e = (a || []).concat(), j = [], b, g;
    for (g in GeoExt.LayerLegend.types) {
        b = GeoExt.LayerLegend.types[g].supports(c);
        if (b > 0) {
            if (e.indexOf(g) == -1) {
                j.push({type: g, score: b})
            }
        } else {
            e.remove(g)
        }
    }
    j.sort(function (k, i) {
        return k.score < i.score ? 1 : (k.score == i.score ? 0 : -1)
    });
    var f = j.length, h = new Array(f);
    for (var d = 0; d < f; ++d) {
        h[d] = j[d].type
    }
    return e.concat(h)
};
GeoExt.LayerLegend.supports = function (a) {
};
GeoExt.LayerLegend.types = {};
Ext.namespace("GeoExt");
GeoExt.VectorLegend = Ext.extend(GeoExt.LayerLegend, {
    layerRecord: null,
    layer: null,
    rules: null,
    symbolType: null,
    untitledPrefix: "Untitled ",
    clickableSymbol: false,
    clickableTitle: false,
    selectOnClick: false,
    enableDD: false,
    bodyBorder: false,
    feature: null,
    selectedRule: null,
    currentScaleDenominator: null,
    initComponent: function () {
        GeoExt.VectorLegend.superclass.initComponent.call(this);
        if (this.layerRecord) {
            this.layer = this.layerRecord.getLayer();
            if (this.layer.map) {
                this.map = this.layer.map;
                this.currentScaleDenominator = this.layer.map.getScale();
                this.layer.map.events.on({zoomend: this.onMapZoom, scope: this})
            }
        }
        if (!this.symbolType) {
            if (this.feature) {
                this.symbolType = this.symbolTypeFromFeature(this.feature)
            } else {
                if (this.layer) {
                    if (this.layer.features.length > 0) {
                        var a = this.layer.features[0].clone();
                        a.attributes = {};
                        this.feature = a;
                        this.symbolType = this.symbolTypeFromFeature(this.feature)
                    } else {
                        this.layer.events.on({featuresadded: this.onFeaturesAdded, scope: this})
                    }
                }
            }
        }
        if (this.layer && this.feature && !this.rules) {
            this.setRules()
        }
        this.rulesContainer = new Ext.Container({autoEl: {}});
        this.add(this.rulesContainer);
        this.addEvents("titleclick", "symbolclick", "ruleclick", "ruleselected", "ruleunselected", "rulemoved");
        this.update()
    },
    onMapZoom: function () {
        this.setCurrentScaleDenominator(this.layer.map.getScale())
    },
    symbolTypeFromFeature: function (b) {
        var a = b.geometry.CLASS_NAME.match(/Point|Line|Polygon/);
        return (a && a[0]) || "Point"
    },
    onFeaturesAdded: function () {
        this.layer.events.un({featuresadded: this.onFeaturesAdded, scope: this});
        var a = this.layer.features[0].clone();
        a.attributes = {};
        this.feature = a;
        this.symbolType = this.symbolTypeFromFeature(this.feature);
        if (!this.rules) {
            this.setRules()
        }
        this.update()
    },
    setRules: function () {
        var a = this.layer.styleMap && this.layer.styleMap.styles["default"];
        if (!a) {
            a = new OpenLayers.Style()
        }
        if (a.rules.length === 0) {
            this.rules = [new OpenLayers.Rule({title: a.title, symbolizer: a.createSymbolizer(this.feature)})]
        } else {
            this.rules = a.rules
        }
    },
    setCurrentScaleDenominator: function (a) {
        if (a !== this.currentScaleDenominator) {
            this.currentScaleDenominator = a;
            this.update()
        }
    },
    getRuleEntry: function (a) {
        return this.rulesContainer.items.get(this.rules.indexOf(a))
    },
    addRuleEntry: function (a, b) {
        this.rulesContainer.add(this.createRuleEntry(a));
        if (!b) {
            this.doLayout()
        }
    },
    removeRuleEntry: function (a, c) {
        var b = this.getRuleEntry(a);
        if (b) {
            this.rulesContainer.remove(b);
            if (!c) {
                this.doLayout()
            }
        }
    },
    selectRuleEntry: function (b) {
        var a = b != this.selectedRule;
        if (this.selectedRule) {
            this.unselect()
        }
        if (a) {
            var c = this.getRuleEntry(b);
            c.body.addClass("x-grid3-row-selected");
            this.selectedRule = b;
            this.fireEvent("ruleselected", this, b)
        }
    },
    unselect: function () {
        this.rulesContainer.items.each(function (b, a) {
            if (this.rules[a] == this.selectedRule) {
                b.body.removeClass("x-grid3-row-selected");
                this.selectedRule = null;
                this.fireEvent("ruleunselected", this, this.rules[a])
            }
        }, this)
    },
    createRuleEntry: function (b) {
        var a = true;
        if (this.currentScaleDenominator != null) {
            if (b.minScaleDenominator) {
                a = a && (this.currentScaleDenominator >= b.minScaleDenominator)
            }
            if (b.maxScaleDenominator) {
                a = a && (this.currentScaleDenominator < b.maxScaleDenominator)
            }
        }
        return {
            xtype: "panel",
            layout: "column",
            border: false,
            hidden: !a,
            bodyStyle: this.selectOnClick ? {cursor: "pointer"} : undefined,
            defaults: {border: false},
            items: [this.createRuleRenderer(b), this.createRuleTitle(b)],
            listeners: {
                render: function (c) {
                    this.selectOnClick && c.getEl().on({
                        click: function (d) {
                            this.selectRuleEntry(b)
                        }, scope: this
                    });
                    if (this.enableDD == true) {
                        this.addDD(c)
                    }
                }, scope: this
            }
        }
    },
    createRuleRenderer: function (k) {
        var f = [this.symbolType, "Point", "Line", "Polygon"];
        var h, e;
        var l = k.symbolizers;
        if (!l) {
            var n = k.symbolizer;
            for (var c = 0, g = f.length; c < g; ++c) {
                h = f[c];
                if (n[h]) {
                    n = n[h];
                    e = true;
                    break
                }
            }
            l = [n]
        } else {
            var a;
            outer:for (var c = 0, m = f.length; c < m; ++c) {
                h = f[c];
                a = OpenLayers.Symbolizer[h];
                if (a) {
                    for (var b = 0, d = l.length; b < d; ++b) {
                        if (l[b] instanceof a) {
                            e = true;
                            break outer
                        }
                    }
                }
            }
        }
        return {
            xtype: "gx_renderer",
            symbolType: e ? h : this.symbolType,
            symbolizers: l,
            style: this.clickableSymbol ? {cursor: "pointer"} : undefined,
            listeners: {
                click: function () {
                    if (this.clickableSymbol) {
                        this.fireEvent("symbolclick", this, k);
                        this.fireEvent("ruleclick", this, k)
                    }
                }, scope: this
            }
        }
    },
    createRuleTitle: function (a) {
        return {
            cls: "x-form-item",
            style: "padding: 0.2em 0.5em 0;",
            bodyStyle: Ext.applyIf({background: "transparent"}, this.clickableTitle ? {cursor: "pointer"} : undefined),
            html: this.getRuleTitle(a),
            listeners: {
                render: function (b) {
                    this.clickableTitle && b.getEl().on({
                        click: function () {
                            this.fireEvent("titleclick", this, a);
                            this.fireEvent("ruleclick", this, a)
                        }, scope: this
                    })
                }, scope: this
            }
        }
    },
    addDD: function (b) {
        var c = b.ownerCt;
        var a = this;
        new Ext.dd.DragSource(b.getEl(), {
            ddGroup: c.id, onDragOut: function (g, d) {
                var f = Ext.getCmp(d);
                f.removeClass("gx-ruledrag-insert-above");
                f.removeClass("gx-ruledrag-insert-below");
                return Ext.dd.DragZone.prototype.onDragOut.apply(this, arguments)
            }, onDragEnter: function (j, g) {
                var i = Ext.getCmp(g);
                var f;
                var d = c.items.indexOf(b);
                var h = c.items.indexOf(i);
                if (d > h) {
                    f = "gx-ruledrag-insert-above"
                } else {
                    if (d < h) {
                        f = "gx-ruledrag-insert-below"
                    }
                }
                f && i.addClass(f);
                return Ext.dd.DragZone.prototype.onDragEnter.apply(this, arguments)
            }, onDragDrop: function (f, d) {
                a.moveRule(c.items.indexOf(b), c.items.indexOf(Ext.getCmp(d)));
                return Ext.dd.DragZone.prototype.onDragDrop.apply(this, arguments)
            }, getDragData: function (g) {
                var f = g.getTarget(".x-column-inner");
                if (f) {
                    var h = f.cloneNode(true);
                    h.id = Ext.id();
                    return {sourceEl: f, repairXY: Ext.fly(f).getXY(), ddel: h}
                }
            }
        });
        new Ext.dd.DropTarget(b.getEl(), {
            ddGroup: c.id, notifyDrop: function () {
                return true
            }
        })
    },
    update: function () {
        GeoExt.VectorLegend.superclass.update.apply(this, arguments);
        if (this.symbolType && this.rules) {
            if (this.rulesContainer.items) {
                var a;
                for (var b = this.rulesContainer.items.length - 1; b >= 0; --b) {
                    a = this.rulesContainer.getComponent(b);
                    this.rulesContainer.remove(a, true)
                }
            }
            for (var b = 0, c = this.rules.length; b < c; ++b) {
                this.addRuleEntry(this.rules[b], true)
            }
            this.doLayout();
            if (this.selectedRule) {
                this.getRuleEntry(this.selectedRule).body.addClass("x-grid3-row-selected")
            }
        }
    },
    updateRuleEntry: function (a) {
        var b = this.getRuleEntry(a);
        if (b) {
            b.removeAll();
            b.add(this.createRuleRenderer(a));
            b.add(this.createRuleTitle(a));
            b.doLayout()
        }
    },
    moveRule: function (a, b) {
        var c = this.rules[a];
        this.rules.splice(a, 1);
        this.rules.splice(b, 0, c);
        this.update();
        this.fireEvent("rulemoved", this, c)
    },
    getRuleTitle: function (a) {
        var b = a.title || a.name || "";
        if (!b && this.untitledPrefix) {
            b = this.untitledPrefix + (this.rules.indexOf(a) + 1)
        }
        return b
    },
    beforeDestroy: function () {
        if (this.layer) {
            if (this.layer.events) {
                this.layer.events.un({featuresadded: this.onFeaturesAdded, scope: this})
            }
            if (this.layer.map && this.layer.map.events) {
                this.layer.map.events.un({zoomend: this.onMapZoom, scope: this})
            }
        }
        delete this.layer;
        delete this.map;
        delete this.rules;
        GeoExt.VectorLegend.superclass.beforeDestroy.apply(this, arguments)
    },
    onStoreRemove: function (b, a, c) {
        if (a.getLayer() === this.layer) {
            if (this.map && this.map.events) {
                this.map.events.un({zoomend: this.onMapZoom, scope: this})
            }
        }
    },
    onStoreAdd: function (d, c, e) {
        for (var f = 0, a = c.length; f < a; f++) {
            var b = c[f];
            if (b.getLayer() === this.layer) {
                if (this.layer.map && this.layer.map.events) {
                    this.layer.map.events.on({zoomend: this.onMapZoom, scope: this})
                }
            }
        }
    }
});
GeoExt.VectorLegend.supports = function (a) {
    return a.getLayer() instanceof OpenLayers.Layer.Vector ? 1 : 0
};
GeoExt.LayerLegend.types.gx_vectorlegend = GeoExt.VectorLegend;
Ext.reg("gx_vectorlegend", GeoExt.VectorLegend);
Ext.namespace("GeoExt.tree");
GeoExt.tree.LayerContainer = Ext.extend(Ext.tree.AsyncTreeNode, {
    text: "Layers", constructor: function (a) {
        a = Ext.applyIf(a || {}, {text: this.text});
        this.loader = a.loader instanceof GeoExt.tree.LayerLoader ? a.loader : new GeoExt.tree.LayerLoader(Ext.applyIf(a.loader || {}, {store: a.layerStore}));
        GeoExt.tree.LayerContainer.superclass.constructor.call(this, a)
    }, recordIndexToNodeIndex: function (c) {
        var b = this.loader.store;
        var e = b.getCount();
        var a = this.childNodes.length;
        var f = -1;
        for (var d = e - 1; d >= 0; --d) {
            if (this.loader.filter(b.getAt(d)) === true) {
                ++f;
                if (c === d || f > a - 1) {
                    break
                }
            }
        }
        return f
    }, destroy: function () {
        delete this.layerStore;
        GeoExt.tree.LayerContainer.superclass.destroy.apply(this, arguments)
    }
});
Ext.tree.TreePanel.nodeTypes.gx_layercontainer = GeoExt.tree.LayerContainer;
Ext.namespace("GeoExt.tree");
GeoExt.tree.BaseLayerContainer = Ext.extend(GeoExt.tree.LayerContainer, {
    text: "Base Layer", constructor: function (a) {
        a = Ext.applyIf(a || {}, {text: this.text, loader: {}});
        a.loader = Ext.applyIf(a.loader, {
            baseAttrs: Ext.applyIf(a.loader.baseAttrs || {}, {
                iconCls: "gx-tree-baselayer-icon",
                checkedGroup: "baselayer"
            }), filter: function (b) {
                var c = b.getLayer();
                return c.displayInLayerSwitcher === true && c.isBaseLayer === true
            }
        });
        GeoExt.tree.BaseLayerContainer.superclass.constructor.call(this, a)
    }
});
Ext.tree.TreePanel.nodeTypes.gx_baselayercontainer = GeoExt.tree.BaseLayerContainer;
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.PrintExtent = Ext.extend(Ext.util.Observable, {
    initialConfig: null,
    printProvider: null,
    map: null,
    layer: null,
    control: null,
    pages: null,
    page: null,
    constructor: function (a) {
        a = a || {};
        Ext.apply(this, a);
        this.initialConfig = a;
        if (!this.printProvider) {
            this.printProvider = this.pages[0].printProvider
        }
        if (!this.pages) {
            this.pages = []
        }
        this.addEvents("selectpage");
        GeoExt.plugins.PrintExtent.superclass.constructor.apply(this, arguments)
    },
    print: function (a) {
        this.printProvider.print(this.map, this.pages, a)
    },
    init: function (c) {
        this.map = c.map;
        c.on("destroy", this.onMapPanelDestroy, this);
        if (!this.layer) {
            this.layer = new OpenLayers.Layer.Vector(null, {displayInLayerSwitcher: false})
        }
        this.createControl();
        for (var b = 0, a = this.pages.length; b < a; ++b) {
            this.addPage(this.pages[b])
        }
        this.show()
    },
    addPage: function (a) {
        a = a || new GeoExt.data.PrintPage({printProvider: this.printProvider});
        if (this.pages.indexOf(a) === -1) {
            this.pages.push(a)
        }
        this.layer.addFeatures([a.feature]);
        a.on("change", this.onPageChange, this);
        this.page = a;
        var b = this.map;
        if (b.getCenter()) {
            this.fitPage()
        } else {
            b.events.register("moveend", this, function () {
                b.events.unregister("moveend", this, arguments.callee);
                this.fitPage()
            })
        }
        return a
    },
    removePage: function (a) {
        this.pages.remove(a);
        if (a.feature.layer) {
            this.layer.removeFeatures([a.feature])
        }
        a.un("change", this.onPageChange, this)
    },
    selectPage: function (a) {
        this.control.active && this.control.setFeature(a.feature)
    },
    show: function () {
        this.map.addLayer(this.layer);
        this.map.addControl(this.control);
        this.control.activate();
        if (this.page && this.map.getCenter()) {
            this.updateBox()
        }
    },
    hide: function () {
        var c = this.map, a = this.layer, b = this.control;
        if (b && b.events) {
            b.deactivate();
            if (c && c.events && b.map) {
                c.removeControl(b)
            }
        }
        if (c && c.events && a && a.map) {
            c.removeLayer(a)
        }
    },
    onMapPanelDestroy: function () {
        var e = this.map;
        for (var a = this.pages.length - 1, c = a; c >= 0; c--) {
            this.removePage(this.pages[c])
        }
        this.hide();
        var d = this.control;
        if (e && e.events && d && d.events) {
            d.destroy()
        }
        var b = this.layer;
        if (!this.initialConfig.layer && e && e.events && b && b.events) {
            b.destroy()
        }
        delete this.layer;
        delete this.control;
        delete this.page;
        this.map = null
    },
    createControl: function () {
        this.control = new OpenLayers.Control.TransformFeature(this.layer, {
            preserveAspectRatio: true,
            eventListeners: {
                beforesetfeature: function (c) {
                    for (var b = 0, a = this.pages.length; b < a; ++b) {
                        if (this.pages[b].feature === c.feature) {
                            this.page = this.pages[b];
                            c.object.rotation = -this.pages[b].rotation;
                            break
                        }
                    }
                }, setfeature: function (c) {
                    for (var b = 0, a = this.pages.length; b < a; ++b) {
                        if (this.pages[b].feature === c.feature) {
                            this.fireEvent("selectpage", this.pages[b]);
                            break
                        }
                    }
                }, beforetransform: function (g) {
                    this._updating = true;
                    var f = this.page;
                    if (g.rotation) {
                        if (this.printProvider.layout.get("rotation")) {
                            f.setRotation(-g.object.rotation)
                        } else {
                            g.object.setFeature(f.feature)
                        }
                    } else {
                        if (g.center) {
                            f.setCenter(OpenLayers.LonLat.fromString(g.center.toShortString()))
                        } else {
                            f.fit(g.object.box, {mode: "closest"});
                            var h = this.printProvider.scales.getAt(0);
                            var i = this.printProvider.scales.getAt(this.printProvider.scales.getCount() - 1);
                            var b = g.object.box.geometry.getBounds();
                            var a = f.feature.geometry.getBounds();
                            var d = f.scale === h && b.containsBounds(a);
                            var c = f.scale === i && a.containsBounds(b);
                            if (d === true || c === true) {
                                this.updateBox()
                            }
                        }
                    }
                    delete this._updating;
                    return false
                }, transformcomplete: this.updateBox, scope: this
            }
        })
    },
    fitPage: function () {
        if (this.page) {
            this.page.fit(this.map, {mode: "screen"})
        }
    },
    updateBox: function () {
        var a = this.page;
        this.control.active && this.control.setFeature(a.feature, {rotation: -a.rotation})
    },
    onPageChange: function (b, a) {
        if (!this._updating) {
            this.control.active && this.control.setFeature(b.feature, {rotation: -b.rotation})
        }
    }
});
Ext.preg("gx_printextent", GeoExt.plugins.PrintExtent);
Ext.namespace("GeoExt");
GeoExt.SliderTip = Ext.extend(Ext.slider.Tip, {
    hover: true,
    minWidth: 10,
    offsets: [0, -10],
    dragging: false,
    init: function (a) {
        GeoExt.SliderTip.superclass.init.apply(this, arguments);
        if (this.hover) {
            a.on("render", this.registerThumbListeners, this)
        }
        this.slider = a
    },
    registerThumbListeners: function () {
        var a, d;
        for (var b = 0, c = this.slider.thumbs.length; b < c; ++b) {
            a = this.slider.thumbs[b];
            d = a.tracker.el;
            (function (e, f) {
                f.on({
                    mouseover: function (g) {
                        this.onSlide(this.slider, g, e);
                        this.dragging = false
                    }, mouseout: function () {
                        if (!this.dragging) {
                            this.hide.apply(this, arguments)
                        }
                    }, scope: this
                })
            }).apply(this, [a, d])
        }
    },
    onSlide: function (b, c, a) {
        this.dragging = true;
        return GeoExt.SliderTip.superclass.onSlide.apply(this, arguments)
    }
});
Ext.namespace("GeoExt");
GeoExt.Lang = new (Ext.extend(Ext.util.Observable, {
    locale: navigator.language || navigator.userLanguage,
    dict: null,
    constructor: function () {
        this.addEvents("localize");
        this.dict = {};
        Ext.util.Observable.constructor.apply(this, arguments)
    },
    add: function (a, d) {
        var c = this.dict[a];
        if (!c) {
            this.dict[a] = Ext.apply({}, d)
        } else {
            for (var b in d) {
                c[b] = Ext.apply(c[b] || {}, d[b])
            }
        }
        if (!a || a === this.locale) {
            this.set(a)
        } else {
            if (this.locale.indexOf(a + "-") === 0) {
                this.set(this.locale)
            }
        }
    },
    set: function (j) {
        var m = j ? j.split("-") : [];
        var b = "";
        var c = {}, k;
        for (var g = 0, l = m.length; g < l; ++g) {
            b += (b && "-" || "") + m[g];
            if (b in this.dict) {
                k = this.dict[b];
                for (var h in k) {
                    if (h in c) {
                        Ext.apply(c[h], k[h])
                    } else {
                        c[h] = Ext.apply({}, k[h])
                    }
                }
            }
        }
        for (var h in c) {
            var f = window;
            var d = h.split(".");
            var e = false;
            for (var g = 0, l = d.length; g < l; ++g) {
                var a = d[g];
                if (a in f) {
                    f = f[a]
                } else {
                    e = true;
                    break
                }
            }
            if (!e) {
                Ext.apply(f, c[h])
            }
        }
        this.locale = j;
        this.fireEvent("localize", j)
    }
}))();
Ext.namespace("GeoExt");
GeoExt.ZoomSlider = Ext.extend(Ext.slider.SingleSlider, {
    map: null,
    baseCls: "gx-zoomslider",
    aggressive: false,
    updating: false,
    initComponent: function () {
        GeoExt.ZoomSlider.superclass.initComponent.call(this);
        if (this.map) {
            if (this.map instanceof GeoExt.MapPanel) {
                this.map = this.map.map
            }
            this.bind(this.map)
        }
        if (this.aggressive === true) {
            this.on("change", this.changeHandler, this)
        } else {
            this.on("changecomplete", this.changeHandler, this)
        }
        this.on("beforedestroy", this.unbind, this)
    },
    onRender: function () {
        GeoExt.ZoomSlider.superclass.onRender.apply(this, arguments);
        this.el.addClass(this.baseCls)
    },
    afterRender: function () {
        Ext.slider.SingleSlider.superclass.afterRender.apply(this, arguments);
        this.update()
    },
    addToMapPanel: function (a) {
        this.on({
            render: function () {
                var b = this.getEl();
                b.setStyle({position: "absolute", zIndex: a.map.Z_INDEX_BASE.Control});
                b.on({mousedown: this.stopMouseEvents, click: this.stopMouseEvents})
            }, afterrender: function () {
                this.bind(a.map)
            }, scope: this
        })
    },
    stopMouseEvents: function (a) {
        a.stopEvent()
    },
    removeFromMapPanel: function (a) {
        var b = this.getEl();
        b.un("mousedown", this.stopMouseEvents, this);
        b.un("click", this.stopMouseEvents, this);
        this.unbind()
    },
    bind: function (a) {
        this.map = a;
        this.map.events.on({zoomend: this.update, changebaselayer: this.initZoomValues, scope: this});
        if (this.map.baseLayer) {
            this.initZoomValues();
            this.update()
        }
    },
    unbind: function () {
        if (this.map && this.map.events) {
            this.map.events.un({zoomend: this.update, changebaselayer: this.initZoomValues, scope: this})
        }
    },
    initZoomValues: function () {
        var a = this.map.baseLayer;
        if (this.initialConfig.minValue === undefined) {
            this.minValue = a.minZoomLevel || 0
        }
        if (this.initialConfig.maxValue === undefined) {
            this.maxValue = a.minZoomLevel == null ? a.numZoomLevels - 1 : a.maxZoomLevel
        }
    },
    getZoom: function () {
        return this.getValue()
    },
    getScale: function () {
        return OpenLayers.Util.getScaleFromResolution(this.map.getResolutionForZoom(this.getValue()), this.map.getUnits())
    },
    getResolution: function () {
        return this.map.getResolutionForZoom(this.getValue())
    },
    changeHandler: function () {
        if (this.map && !this.updating) {
            this.map.zoomTo(this.getValue())
        }
    },
    update: function () {
        if (this.rendered && this.map) {
            this.updating = true;
            this.setValue(this.map.getZoom());
            this.updating = false
        }
    }
});
Ext.reg("gx_zoomslider", GeoExt.ZoomSlider);
Ext.namespace("GeoExt.tree");
GeoExt.tree.WMSCapabilitiesLoader = function (a) {
    Ext.apply(this, a);
    GeoExt.tree.WMSCapabilitiesLoader.superclass.constructor.call(this)
};
Ext.extend(GeoExt.tree.WMSCapabilitiesLoader, Ext.tree.TreeLoader, {
    url: null,
    layerOptions: null,
    layerParams: null,
    requestMethod: "GET",
    getParams: function (a) {
        return {service: "WMS", request: "GetCapabilities"}
    },
    processResponse: function (b, d, e, c) {
        var a = new OpenLayers.Format.WMSCapabilities().read(b.responseXML || b.responseText);
        a.capability && this.processLayer(a.capability, a.capability.request.getmap.href, d);
        if (typeof e == "function") {
            e.apply(c || d, [d])
        }
    },
    createWMSLayer: function (b, a) {
        if (b.name) {
            return new OpenLayers.Layer.WMS(b.title, a, OpenLayers.Util.extend({
                formats: b.formats[0],
                layers: b.name
            }, this.layerParams), OpenLayers.Util.extend({
                minScale: b.minScale,
                queryable: b.queryable,
                maxScale: b.maxScale,
                metadata: b
            }, this.layerOptions))
        } else {
            return null
        }
    },
    processLayer: function (b, a, c) {
        Ext.each(b.nestedLayers, function (d) {
            var e = this.createNode({
                text: d.title || d.name,
                nodeType: "node",
                layer: this.createWMSLayer(d, a),
                leaf: (d.nestedLayers.length === 0)
            });
            if (e) {
                c.appendChild(e)
            }
            if (d.nestedLayers) {
                this.processLayer(d, a, e)
            }
        }, this)
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.WMSCapabilitiesReader = function (a, b) {
    a = a || {};
    if (!a.format) {
        a.format = new OpenLayers.Format.WMSCapabilities()
    }
    if (typeof b !== "function") {
        b = GeoExt.data.LayerRecord.create(b || a.fields || [{name: "name", type: "string"}, {
            name: "title",
            type: "string"
        }, {name: "abstract", type: "string"}, {name: "queryable", type: "boolean"}, {
            name: "opaque",
            type: "boolean"
        }, {name: "noSubsets", type: "boolean"}, {name: "cascaded", type: "int"}, {
            name: "fixedWidth",
            type: "int"
        }, {name: "fixedHeight", type: "int"}, {name: "minScale", type: "float"}, {
            name: "maxScale",
            type: "float"
        }, {
            name: "prefix",
            type: "string"
        }, {name: "formats"}, {name: "styles"}, {name: "srs"}, {name: "dimensions"}, {name: "bbox"}, {name: "llbbox"}, {name: "attribution"}, {name: "keywords"}, {name: "identifiers"}, {name: "authorityURLs"}, {name: "metadataURLs"}, {name: "infoFormats"}])
    }
    GeoExt.data.WMSCapabilitiesReader.superclass.constructor.call(this, a, b)
};
Ext.extend(GeoExt.data.WMSCapabilitiesReader, Ext.data.DataReader, {
    attributionCls: "gx-attribution",
    read: function (a) {
        var b = a.responseXML;
        if (!b || !b.documentElement) {
            b = a.responseText
        }
        return this.readRecords(b)
    },
    serviceExceptionFormat: function (a) {
        if (OpenLayers.Util.indexOf(a, "application/vnd.ogc.se_inimage") > -1) {
            return "application/vnd.ogc.se_inimage"
        }
        if (OpenLayers.Util.indexOf(a, "application/vnd.ogc.se_xml") > -1) {
            return "application/vnd.ogc.se_xml"
        }
        return a[0]
    },
    imageFormat: function (b) {
        var a = b.formats;
        if (b.opaque && OpenLayers.Util.indexOf(a, "image/jpeg") > -1) {
            return "image/jpeg"
        }
        if (OpenLayers.Util.indexOf(a, "image/png") > -1) {
            return "image/png"
        }
        if (OpenLayers.Util.indexOf(a, "image/png; mode=24bit") > -1) {
            return "image/png; mode=24bit"
        }
        if (OpenLayers.Util.indexOf(a, "image/gif") > -1) {
            return "image/gif"
        }
        return a[0]
    },
    imageTransparent: function (a) {
        return a.opaque == undefined || !a.opaque
    },
    readRecords: function (u) {
        if (typeof u === "string" || u.nodeType) {
            u = this.meta.format.read(u)
        }
        if (!!u.error) {
            throw new Ext.data.DataReader.Error("invalid-response", u.error)
        }
        var e = u.version;
        var c = u.capability || {};
        var f = c.request && c.request.getmap && c.request.getmap.href;
        var h = c.layers;
        var g = c.exception ? c.exception.formats : [];
        var p = this.serviceExceptionFormat(g);
        var o = [];
        if (f && h) {
            var l = this.recordType.prototype.fields;
            var t, b, d, s, a, k;
            for (var n = 0, r = h.length; n < r; n++) {
                t = h[n];
                if (t.name) {
                    b = {};
                    for (var m = 0, q = l.length; m < q; m++) {
                        a = l.items[m];
                        k = t[a.mapping || a.name] || a.defaultValue;
                        k = a.convert(k);
                        b[a.name] = k
                    }
                    d = {
                        attribution: t.attribution ? this.attributionMarkup(t.attribution) : undefined,
                        minScale: t.minScale,
                        maxScale: t.maxScale
                    };
                    if (this.meta.layerOptions) {
                        Ext.apply(d, this.meta.layerOptions)
                    }
                    s = {
                        layers: t.name,
                        exceptions: p,
                        format: this.imageFormat(t),
                        transparent: this.imageTransparent(t),
                        version: e
                    };
                    if (this.meta.layerParams) {
                        Ext.apply(s, this.meta.layerParams)
                    }
                    b.layer = new OpenLayers.Layer.WMS(t.title || t.name, f, s, d);
                    o.push(new this.recordType(b, b.layer.id))
                }
            }
        }
        return {totalRecords: o.length, success: true, records: o}
    },
    attributionMarkup: function (a) {
        var b = [];
        if (a.logo) {
            b.push("<img class='" + this.attributionCls + "-image' src='" + a.logo.href + "' />")
        }
        if (a.title) {
            b.push("<span class='" + this.attributionCls + "-title'>" + a.title + "</span>")
        }
        if (a.href) {
            for (var c = 0; c < b.length; c++) {
                b[c] = "<a class='" + this.attributionCls + "-link' href=" + a.href + ">" + b[c] + "</a>"
            }
        }
        return b.join(" ")
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.LayerStoreMixin = function () {
    return {
        map: null, reader: null, constructor: function (b) {
            b = b || {};
            b.reader = b.reader || new GeoExt.data.LayerReader({}, b.fields);
            delete b.fields;
            var c = b.map instanceof GeoExt.MapPanel ? b.map.map : b.map;
            delete b.map;
            if (b.layers) {
                b.data = b.layers
            }
            delete b.layers;
            var a = {initDir: b.initDir};
            delete b.initDir;
            arguments.callee.superclass.constructor.call(this, b);
            this.addEvents("bind");
            if (c) {
                this.bind(c, a)
            }
        }, bind: function (d, a) {
            if (this.map) {
                return
            }
            this.map = d;
            a = a || {};
            var b = a.initDir;
            if (a.initDir == undefined) {
                b = GeoExt.data.LayerStore.MAP_TO_STORE | GeoExt.data.LayerStore.STORE_TO_MAP
            }
            var c = d.layers.slice(0);
            if (b & GeoExt.data.LayerStore.STORE_TO_MAP) {
                this.each(function (e) {
                    this.map.addLayer(e.getLayer())
                }, this)
            }
            if (b & GeoExt.data.LayerStore.MAP_TO_STORE) {
                this.loadData(c, true)
            }
            d.events.on({
                changelayer: this.onChangeLayer,
                addlayer: this.onAddLayer,
                removelayer: this.onRemoveLayer,
                scope: this
            });
            this.on({
                load: this.onLoad,
                clear: this.onClear,
                add: this.onAdd,
                remove: this.onRemove,
                update: this.onUpdate,
                scope: this
            });
            this.data.on({replace: this.onReplace, scope: this});
            this.fireEvent("bind", this, d)
        }, unbind: function () {
            if (this.map) {
                this.map.events.un({
                    changelayer: this.onChangeLayer,
                    addlayer: this.onAddLayer,
                    removelayer: this.onRemoveLayer,
                    scope: this
                });
                this.un("load", this.onLoad, this);
                this.un("clear", this.onClear, this);
                this.un("add", this.onAdd, this);
                this.un("remove", this.onRemove, this);
                this.data.un("replace", this.onReplace, this);
                this.map = null
            }
        }, onChangeLayer: function (b) {
            var e = b.layer;
            var c = this.findBy(function (f, g) {
                return f.getLayer() === e
            });
            if (c > -1) {
                var a = this.getAt(c);
                if (b.property === "order") {
                    if (!this._adding && !this._removing) {
                        var d = this.map.getLayerIndex(e);
                        if (d !== c) {
                            this._removing = true;
                            this.remove(a);
                            delete this._removing;
                            this._adding = true;
                            this.insert(d, [a]);
                            delete this._adding
                        }
                    }
                } else {
                    if (b.property === "name") {
                        a.set("title", e.name)
                    } else {
                        this.fireEvent("update", this, a, Ext.data.Record.EDIT)
                    }
                }
            }
        }, onAddLayer: function (a) {
            if (!this._adding) {
                var b = a.layer;
                this._adding = true;
                this.loadData([b], true);
                delete this._adding
            }
        }, onRemoveLayer: function (a) {
            if (this.map.unloadDestroy) {
                if (!this._removing) {
                    var b = a.layer;
                    this._removing = true;
                    this.remove(this.getById(b.id));
                    delete this._removing
                }
            } else {
                this.unbind()
            }
        }, onLoad: function (c, b, e) {
            if (!Ext.isArray(b)) {
                b = [b]
            }
            if (e && !e.add) {
                this._removing = true;
                for (var f = this.map.layers.length - 1; f >= 0; f--) {
                    this.map.removeLayer(this.map.layers[f])
                }
                delete this._removing;
                var a = b.length;
                if (a > 0) {
                    var g = new Array(a);
                    for (var d = 0; d < a; d++) {
                        g[d] = b[d].getLayer()
                    }
                    this._adding = true;
                    this.map.addLayers(g);
                    delete this._adding
                }
            }
        }, onClear: function (a) {
            this._removing = true;
            for (var b = this.map.layers.length - 1; b >= 0; b--) {
                this.map.removeLayer(this.map.layers[b])
            }
            delete this._removing
        }, onAdd: function (b, a, c) {
            if (!this._adding) {
                this._adding = true;
                var e;
                for (var d = a.length - 1; d >= 0; --d) {
                    e = a[d].getLayer();
                    this.map.addLayer(e);
                    if (c !== this.map.layers.length - 1) {
                        this.map.setLayerIndex(e, c)
                    }
                }
                delete this._adding
            }
        }, onRemove: function (b, a, c) {
            if (!this._removing) {
                var d = a.getLayer();
                if (this.map.getLayer(d.id) != null) {
                    this._removing = true;
                    this.removeMapLayer(a);
                    delete this._removing
                }
            }
        }, onUpdate: function (c, a, b) {
            if (b === Ext.data.Record.EDIT) {
                if (a.modified && a.modified.title) {
                    var d = a.getLayer();
                    var e = a.get("title");
                    if (e !== d.name) {
                        d.setName(e)
                    }
                }
            }
        }, removeMapLayer: function (a) {
            this.map.removeLayer(a.getLayer())
        }, onReplace: function (c, a, b) {
            this.removeMapLayer(a)
        }, getByLayer: function (b) {
            var a = this.findBy(function (c) {
                return c.getLayer() === b
            });
            if (a > -1) {
                return this.getAt(a)
            }
        }, destroy: function () {
            this.unbind();
            GeoExt.data.LayerStore.superclass.destroy.call(this)
        }
    }
};
GeoExt.data.LayerStore = Ext.extend(Ext.data.Store, new GeoExt.data.LayerStoreMixin);
GeoExt.data.LayerStore.MAP_TO_STORE = 1;
GeoExt.data.LayerStore.STORE_TO_MAP = 2;
Ext.namespace("GeoExt.tree");
GeoExt.tree.LayerLoader = function (a) {
    Ext.apply(this, a);
    this.addEvents("beforeload", "load");
    GeoExt.tree.LayerLoader.superclass.constructor.call(this)
};
Ext.extend(GeoExt.tree.LayerLoader, Ext.util.Observable, {
    store: null, filter: function (a) {
        return a.getLayer().displayInLayerSwitcher == true
    }, baseAttrs: null, uiProviders: null, load: function (a, b) {
        if (this.fireEvent("beforeload", this, a)) {
            this.removeStoreHandlers();
            while (a.firstChild) {
                a.removeChild(a.firstChild)
            }
            if (!this.uiProviders) {
                this.uiProviders = a.getOwnerTree().getLoader().uiProviders
            }
            if (!this.store) {
                this.store = GeoExt.MapPanel.guess().layers
            }
            this.store.each(function (c) {
                this.addLayerNode(a, c)
            }, this);
            this.addStoreHandlers(a);
            if (typeof b == "function") {
                b()
            }
            this.fireEvent("load", this, a)
        }
    }, onStoreAdd: function (b, a, c, e) {
        if (!this._reordering) {
            var f = e.recordIndexToNodeIndex(c + a.length - 1);
            for (var d = 0; d < a.length; ++d) {
                this.addLayerNode(e, a[d], f)
            }
        }
    }, onStoreRemove: function (b, a, c, d) {
        if (!this._reordering) {
            this.removeLayerNode(d, a)
        }
    }, addLayerNode: function (d, a, b) {
        b = b || 0;
        if (this.filter(a) === true) {
            var e = this.createNode({nodeType: "gx_layer", layer: a.getLayer(), layerStore: this.store});
            var c = d.item(b);
            if (c) {
                d.insertBefore(e, c)
            } else {
                d.appendChild(e)
            }
            e.on("move", this.onChildMove, this)
        }
    }, removeLayerNode: function (b, a) {
        if (this.filter(a) === true) {
            var c = b.findChildBy(function (d) {
                return d.layer == a.getLayer()
            });
            if (c) {
                c.un("move", this.onChildMove, this);
                c.remove();
                b.reload()
            }
        }
    }, onChildMove: function (j, b, h, i, f) {
        this._reordering = true;
        var e = this.store.getByLayer(b.layer);
        if (i instanceof GeoExt.tree.LayerContainer && this.store === i.loader.store) {
            i.loader._reordering = true;
            this.store.remove(e);
            var a;
            if (i.childNodes.length > 1) {
                var g = (f === 0) ? f + 1 : f - 1;
                a = this.store.findBy(function (k) {
                    return i.childNodes[g].layer === k.getLayer()
                });
                f === 0 && a++
            } else {
                if (h.parentNode === i.parentNode) {
                    var c = i;
                    do {
                        c = c.previousSibling
                    } while (c && !(c instanceof GeoExt.tree.LayerContainer && c.lastChild));
                    if (c) {
                        a = this.store.findBy(function (k) {
                            return c.lastChild.layer === k.getLayer()
                        })
                    } else {
                        var d = i;
                        do {
                            d = d.nextSibling
                        } while (d && !(d instanceof GeoExt.tree.LayerContainer && d.firstChild));
                        if (d) {
                            a = this.store.findBy(function (k) {
                                return d.firstChild.layer === k.getLayer()
                            })
                        }
                        a++
                    }
                }
            }
            if (a !== undefined) {
                this.store.insert(a, [e]);
                window.setTimeout(function () {
                    i.reload();
                    h.reload()
                })
            } else {
                this.store.insert(oldRecordIndex, [e])
            }
            delete i.loader._reordering
        }
        delete this._reordering
    }, addStoreHandlers: function (b) {
        if (!this._storeHandlers) {
            this._storeHandlers = {
                add: this.onStoreAdd.createDelegate(this, [b], true),
                remove: this.onStoreRemove.createDelegate(this, [b], true)
            };
            for (var a in this._storeHandlers) {
                this.store.on(a, this._storeHandlers[a], this)
            }
        }
    }, removeStoreHandlers: function () {
        if (this._storeHandlers) {
            for (var a in this._storeHandlers) {
                this.store.un(a, this._storeHandlers[a], this)
            }
            delete this._storeHandlers
        }
    }, createNode: function (attr) {
        if (this.baseAttrs) {
            Ext.apply(attr, this.baseAttrs)
        }
        if (typeof attr.uiProvider == "string") {
            attr.uiProvider = this.uiProviders[attr.uiProvider] || eval(attr.uiProvider)
        }
        attr.nodeType = attr.nodeType || "gx_layer";
        return new Ext.tree.TreePanel.nodeTypes[attr.nodeType](attr)
    }, destroy: function () {
        this.removeStoreHandlers()
    }
});
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.PrintProviderField = Ext.extend(Ext.util.Observable, {
    target: null, constructor: function (a) {
        this.initialConfig = a;
        Ext.apply(this, a);
        GeoExt.plugins.PrintProviderField.superclass.constructor.apply(this, arguments)
    }, init: function (b) {
        this.target = b;
        var a = {scope: this, render: this.onRender, beforedestroy: this.onBeforeDestroy};
        a[b instanceof Ext.form.ComboBox ? "select" : "valid"] = this.onFieldChange;
        b.on(a)
    }, onRender: function (a) {
        var b = this.printProvider || a.ownerCt.printProvider;
        if (a.store === b.layouts) {
            a.setValue(b.layout.get(a.displayField));
            b.on({layoutchange: this.onProviderChange, scope: this})
        } else {
            if (a.store === b.dpis) {
                a.setValue(b.dpi.get(a.displayField));
                b.on({dpichange: this.onProviderChange, scope: this})
            } else {
                if (a.initialConfig.value === undefined) {
                    a.setValue(b.customParams[a.name])
                }
            }
        }
    }, onFieldChange: function (c, a) {
        var d = this.printProvider || c.ownerCt.printProvider;
        var b = c.getValue();
        this._updating = true;
        if (a) {
            switch (c.store) {
                case d.layouts:
                    d.setLayout(a);
                    break;
                case d.dpis:
                    d.setDpi(a)
            }
        } else {
            d.customParams[c.name] = b
        }
        delete this._updating
    }, onProviderChange: function (b, a) {
        if (!this._updating) {
            this.target.setValue(a.get(this.target.displayField))
        }
    }, onBeforeDestroy: function () {
        var a = this.target;
        a.un("beforedestroy", this.onBeforeDestroy, this);
        a.un("render", this.onRender, this);
        a.un("select", this.onFieldChange, this);
        a.un("valid", this.onFieldChange, this);
        var b = this.printProvider || a.ownerCt.printProvider;
        b.un("layoutchange", this.onProviderChange, this);
        b.un("dpichange", this.onProviderChange, this)
    }
});
Ext.preg("gx_printproviderfield", GeoExt.plugins.PrintProviderField);
Ext.namespace("GeoExt", "GeoExt.data");
GeoExt.data.LayerReader = function (a, b) {
    a = a || {};
    if (!(b instanceof Function)) {
        b = GeoExt.data.LayerRecord.create(b || a.fields || {})
    }
    GeoExt.data.LayerReader.superclass.constructor.call(this, a, b)
};
Ext.extend(GeoExt.data.LayerReader, Ext.data.DataReader, {
    totalRecords: null, readRecords: function (f) {
        var a = [];
        if (f) {
            var c = this.recordType, k = c.prototype.fields;
            var g, d, e, b, h, n, l, m;
            for (g = 0, d = f.length; g < d; g++) {
                h = f[g];
                n = {};
                for (e = 0, b = k.length; e < b; e++) {
                    l = k.items[e];
                    m = h[l.mapping || l.name] || l.defaultValue;
                    m = l.convert(m);
                    n[l.name] = m
                }
                n.layer = h;
                a[a.length] = new c(n, h.id)
            }
        }
        return {records: a, totalRecords: this.totalRecords != null ? this.totalRecords : a.length}
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.WMSDescribeLayerStore = function (a) {
    a = a || {};
    GeoExt.data.WMSDescribeLayerStore.superclass.constructor.call(this, Ext.apply(a, {
        proxy: a.proxy || (!a.data ? new Ext.data.HttpProxy({
            url: a.url,
            disableCaching: false,
            method: "GET"
        }) : undefined), reader: new GeoExt.data.WMSDescribeLayerReader(a, a.fields)
    }))
};
Ext.extend(GeoExt.data.WMSDescribeLayerStore, Ext.data.Store);
Ext.namespace("GeoExt.form");
GeoExt.form.BasicForm = Ext.extend(Ext.form.BasicForm, {
    protocol: null,
    prevResponse: null,
    autoAbort: true,
    doAction: function (b, a) {
        if (b == "search") {
            a = Ext.applyIf(a || {}, {protocol: this.protocol, abortPrevious: this.autoAbort});
            b = new GeoExt.form.SearchAction(this, a)
        }
        return GeoExt.form.BasicForm.superclass.doAction.call(this, b, a)
    },
    search: function (a) {
        return this.doAction("search", a)
    }
});
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.TreeNodeRadioButton = Ext.extend(Ext.util.Observable, {
    constructor: function (a) {
        Ext.apply(this.initialConfig, Ext.apply({}, a));
        Ext.apply(this, a);
        this.addEvents("radiochange");
        GeoExt.plugins.TreeNodeRadioButton.superclass.constructor.apply(this, arguments)
    }, init: function (a) {
        a.on({
            rendernode: this.onRenderNode,
            rawclicknode: this.onRawClickNode,
            beforedestroy: this.onBeforeDestroy,
            scope: this
        })
    }, onRenderNode: function (c) {
        var b = c.attributes;
        if (b.radioGroup && !b.radio) {
            b.radio = Ext.DomHelper.insertBefore(c.ui.anchor, ['<input type="radio" class="gx-tree-radio" name="', b.radioGroup, '_radio"></input>'].join(""))
        }
    }, onRawClickNode: function (b, c) {
        var a = c.getTarget(".gx-tree-radio", 1);
        if (a) {
            a.defaultChecked = a.checked;
            this.fireEvent("radiochange", b);
            return false
        }
    }, onBeforeDestroy: function (a) {
        a.un("rendernode", this.onRenderNode, this);
        a.un("rawclicknode", this.onRawClickNode, this);
        a.un("beforedestroy", this.onBeforeDestroy, this)
    }
});
Ext.preg("gx_treenoderadiobutton", GeoExt.plugins.TreeNodeRadioButton);
Ext.namespace("GeoExt.data");
GeoExt.data.StyleReader = Ext.extend(Ext.data.JsonReader, {
    onMetaChange: function () {
        GeoExt.data.StyleReader.superclass.onMetaChange.apply(this, arguments);
        this.recordType.prototype.commit = Ext.createInterceptor(this.recordType.prototype.commit, function () {
            var a = this.store.reader;
            a.raw[a.meta.root] = a.meta.storeToData(this.store)
        })
    }, readRecords: function (d) {
        var a, c;
        if (d instanceof OpenLayers.Symbolizer.Raster) {
            a = "colorMap"
        } else {
            a = "rules"
        }
        this.raw = d;
        Ext.applyIf(this.meta, GeoExt.data.StyleReader.metaData[a]);
        var b = {metaData: this.meta};
        b[a] = d[a];
        return GeoExt.data.StyleReader.superclass.readRecords.call(this, b)
    }
});
GeoExt.data.StyleReader.metaData = {
    colorMap: {
        root: "colorMap",
        idProperty: "filter",
        fields: [{
            name: "symbolizers", mapping: function (a) {
                return {fillColor: a.color, fillOpacity: a.opacity, stroke: false}
            }
        }, {name: "filter", mapping: "quantity", type: "float"}, {
            name: "label", mapping: function (a) {
                return a.label || a.quantity
            }
        }],
        storeToData: function (a) {
            a.sort("filter", "ASC");
            var b = [];
            a.each(function (g) {
                var e = g.get("symbolizers"), d = g.get("label"), c = g.isModified("label");
                var f = Number(g.get("filter"));
                g.data.filter = f;
                if ((!g.json.label && !c && g.isModified("filter")) || (c && !d)) {
                    g.data.label = f
                }
                b.push(Ext.apply(g.json, {
                    color: e.fillColor,
                    label: typeof d == "string" ? d : undefined,
                    opacity: e.opacity,
                    quantity: f
                }))
            });
            return b
        }
    },
    rules: {
        root: "rules",
        fields: ["symbolizers", "filter", {
            name: "label",
            mapping: "title"
        }, "name", "description", "elseFilter", "minScaleDenominator", "maxScaleDenominator"],
        storeToData: function (a) {
            var b = [];
            a.each(function (d) {
                var c = d.get("filter");
                if (typeof c === "string") {
                    c = c ? OpenLayers.Format.CQL.prototype.read(c) : null
                }
                b.push(Ext.apply(d.json, {
                    symbolizers: d.get("symbolizers"),
                    filter: c,
                    title: d.get("label"),
                    name: d.get("name"),
                    description: d.get("description"),
                    elseFilter: d.get("elseFilter"),
                    minScaleDenominator: d.get("minScaleDenominator"),
                    maxScaleDenominator: d.get("maxScaleDenominator")
                }))
            });
            return b
        }
    }
};
Ext.namespace("GeoExt", "GeoExt.data");
GeoExt.data.FeatureReader = function (a, b) {
    a = a || {};
    if (!(b instanceof Function)) {
        b = GeoExt.data.FeatureRecord.create(b || a.fields || {})
    }
    GeoExt.data.FeatureReader.superclass.constructor.call(this, a, b)
};
Ext.extend(GeoExt.data.FeatureReader, Ext.data.DataReader, {
    totalRecords: null, read: function (a) {
        return this.readRecords(a.features)
    }, readRecords: function (b) {
        var c = [];
        if (b) {
            var f = this.recordType, l = f.prototype.fields;
            var k, g, h, d, q, p, n, o;
            for (k = 0, g = b.length; k < g; k++) {
                q = b[k];
                p = {};
                if (q.attributes) {
                    for (h = 0, d = l.length; h < d; h++) {
                        n = l.items[h];
                        if (/[\[\.]/.test(n.mapping)) {
                            try {
                                o = new Function("obj", "return obj." + n.mapping)(q.attributes)
                            } catch (m) {
                                o = n.defaultValue
                            }
                        } else {
                            o = q.attributes[n.mapping || n.name] || n.defaultValue
                        }
                        if (n.convert) {
                            o = n.convert(o)
                        }
                        p[n.name] = o
                    }
                }
                p.feature = q;
                p.state = q.state;
                p.fid = q.fid;
                var a = (q.state === OpenLayers.State.INSERT) ? undefined : q.id;
                c[c.length] = new f(p, a)
            }
        }
        return {records: c, totalRecords: this.totalRecords != null ? this.totalRecords : c.length}
    }
});
Ext.namespace("GeoExt");
GeoExt.WMSLegend = Ext.extend(GeoExt.LayerLegend, {
    defaultStyleIsFirst: true,
    useScaleParameter: true,
    baseParams: null,
    initComponent: function () {
        GeoExt.WMSLegend.superclass.initComponent.call(this);
        var a = this.layerRecord.getLayer();
        this._noMap = !a.map;
        a.events.register("moveend", this, this.onLayerMoveend);
        this.update()
    },
    onLayerMoveend: function (a) {
        if ((a.zoomChanged === true && this.useScaleParameter === true) || this._noMap) {
            delete this._noMap;
            this.update()
        }
    },
    getLegendUrl: function (g, h) {
        var e = this.layerRecord;
        var a;
        var k = e && e.get("styles");
        var f = e.getLayer();
        h = h || [f.params.LAYERS].join(",").split(",");
        var j = f.params.STYLES && [f.params.STYLES].join(",").split(",");
        var i = h.indexOf(g);
        var b = j && j[i];
        if (k && k.length > 0) {
            if (b) {
                Ext.each(k, function (l) {
                    a = (l.name == b && l.legend) && l.legend.href;
                    return !a
                })
            } else {
                if (this.defaultStyleIsFirst === true && !j && !f.params.SLD && !f.params.SLD_BODY) {
                    a = k[0].legend && k[0].legend.href
                }
            }
        }
        if (!a) {
            a = f.getFullRequestString({
                REQUEST: "GetLegendGraphic",
                WIDTH: null,
                HEIGHT: null,
                EXCEPTIONS: "application/vnd.ogc.se_xml",
                LAYER: g,
                LAYERS: null,
                STYLE: (b !== "") ? b : null,
                STYLES: null,
                SRS: null,
                FORMAT: null,
                TIME: null
            })
        }
        if (a.toLowerCase().indexOf("request=getlegendgraphic") != -1) {
            if (a.toLowerCase().indexOf("format=") == -1) {
                a = Ext.urlAppend(a, "FORMAT=image/gif")
            }
            if (this.useScaleParameter === true) {
                var c = f.map.getScale();
                a = Ext.urlAppend(a, "SCALE=" + c)
            }
        }
        var d = Ext.apply({}, this.baseParams);
        if (f.params._OLSALT) {
            d._OLSALT = f.params._OLSALT
        }
        a = Ext.urlAppend(a, Ext.urlEncode(d));
        return a
    },
    update: function () {
        var d = this.layerRecord.getLayer();
        if (!(d && d.map)) {
            return
        }
        GeoExt.WMSLegend.superclass.update.apply(this, arguments);
        var h, b, c, a;
        h = [d.params.LAYERS].join(",").split(",");
        var e = [];
        var g = this.items.get(0);
        this.items.each(function (i) {
            c = h.indexOf(i.itemId);
            if (c < 0 && i != g) {
                e.push(i)
            } else {
                if (i !== g) {
                    b = h[c];
                    var j = this.getLegendUrl(b, h);
                    if (!OpenLayers.Util.isEquivalentUrl(j, i.url)) {
                        i.setUrl(j)
                    }
                }
            }
        }, this);
        for (c = 0, a = e.length; c < a; c++) {
            var f = e[c];
            this.remove(f);
            f.destroy()
        }
        for (c = 0, a = h.length; c < a; c++) {
            b = h[c];
            if (!this.items || !this.getComponent(b)) {
                this.add({xtype: "gx_legendimage", url: this.getLegendUrl(b, h), itemId: b})
            }
        }
        this.doLayout()
    },
    beforeDestroy: function () {
        if (this.useScaleParameter === true) {
            var a = this.layerRecord.getLayer();
            a && a.events && a.events.unregister("moveend", this, this.onLayerMoveend)
        }
        GeoExt.WMSLegend.superclass.beforeDestroy.apply(this, arguments)
    }
});
GeoExt.WMSLegend.supports = function (a) {
    return a.getLayer() instanceof OpenLayers.Layer.WMS ? 1 : 0
};
GeoExt.LayerLegend.types.gx_wmslegend = GeoExt.WMSLegend;
Ext.reg("gx_wmslegend", GeoExt.WMSLegend);
Ext.namespace("GeoExt.data");
GeoExt.data.WMSDescribeLayerReader = function (a, b) {
    a = a || {};
    if (!a.format) {
        a.format = new OpenLayers.Format.WMSDescribeLayer()
    }
    if (!(typeof b === "function")) {
        b = Ext.data.Record.create(b || a.fields || [{name: "owsType", type: "string"}, {
            name: "owsURL",
            type: "string"
        }, {name: "typeName", type: "string"}])
    }
    GeoExt.data.WMSDescribeLayerReader.superclass.constructor.call(this, a, b)
};
Ext.extend(GeoExt.data.WMSDescribeLayerReader, Ext.data.DataReader, {
    read: function (a) {
        var b = a.responseXML;
        if (!b || !b.documentElement) {
            b = a.responseText
        }
        return this.readRecords(b)
    }, readRecords: function (e) {
        if (typeof e === "string" || e.nodeType) {
            e = this.meta.format.read(e)
        }
        var b = [], d;
        for (var c = 0, a = e.length; c < a; c++) {
            d = e[c];
            if (d) {
                b.push(new this.recordType(d))
            }
        }
        return {totalRecords: b.length, success: true, records: b}
    }
});
Ext.namespace("GeoExt.form");
GeoExt.form.SearchAction = Ext.extend(Ext.form.Action, {
    type: "search", response: null, constructor: function (b, a) {
        GeoExt.form.SearchAction.superclass.constructor.call(this, b, a)
    }, run: function () {
        var b = this.options;
        var a = GeoExt.form.toFilter(this.form, b.logicalOp, b.wildcard);
        if (b.clientValidation === false || this.form.isValid()) {
            if (b.abortPrevious && this.form.prevResponse) {
                b.protocol.abort(this.form.prevResponse)
            }
            this.form.prevResponse = b.protocol.read(Ext.applyIf({
                filter: a,
                callback: this.handleResponse,
                scope: this
            }, b))
        } else {
            if (b.clientValidation !== false) {
                this.failureType = Ext.form.Action.CLIENT_INVALID;
                this.form.afterAction(this, false)
            }
        }
    }, handleResponse: function (a) {
        this.form.prevResponse = null;
        this.response = a;
        if (a.success()) {
            this.form.afterAction(this, true)
        } else {
            this.form.afterAction(this, false)
        }
        var b = this.options;
        if (b.callback) {
            b.callback.call(b.scope, a)
        }
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.FeatureRecord = Ext.data.Record.create([{name: "feature"}, {name: "state"}, {name: "fid"}]);
GeoExt.data.FeatureRecord.prototype.getFeature = function () {
    return this.get("feature")
};
GeoExt.data.FeatureRecord.prototype.setFeature = function (a) {
    if (a !== this.data.feature) {
        this.dirty = true;
        if (!this.modified) {
            this.modified = {}
        }
        if (this.modified.feature === undefined) {
            this.modified.feature = this.data.feature
        }
        this.data.feature = a;
        if (!this.editing) {
            this.afterEdit()
        }
    }
};
GeoExt.data.FeatureRecord.create = function (e) {
    var c = Ext.extend(GeoExt.data.FeatureRecord, {});
    var d = c.prototype;
    d.fields = new Ext.util.MixedCollection(false, function (f) {
        return f.name
    });
    GeoExt.data.FeatureRecord.prototype.fields.each(function (g) {
        d.fields.add(g)
    });
    if (e) {
        for (var b = 0, a = e.length; b < a; b++) {
            d.fields.add(new Ext.data.Field(e[b]))
        }
    }
    c.getField = function (f) {
        return d.fields.get(f)
    };
    return c
};
Ext.namespace("GeoExt.tree");
GeoExt.tree.LayerParamLoader = function (a) {
    Ext.apply(this, a);
    this.addEvents("beforeload", "load");
    GeoExt.tree.LayerParamLoader.superclass.constructor.call(this)
};
Ext.extend(GeoExt.tree.LayerParamLoader, Ext.util.Observable, {
    param: null, delimiter: ",", load: function (b, d) {
        if (this.fireEvent("beforeload", this, b)) {
            while (b.firstChild) {
                b.removeChild(b.firstChild)
            }
            var c = (b.layer instanceof OpenLayers.Layer.HTTPRequest) && b.layer.params[this.param];
            if (c) {
                var a = (c instanceof Array) ? c.slice() : c.split(this.delimiter);
                Ext.each(a, function (g, e, f) {
                    this.addParamNode(g, f, b)
                }, this)
            }
            if (typeof d == "function") {
                d()
            }
            this.fireEvent("load", this, b)
        }
    }, addParamNode: function (a, b, d) {
        var e = this.createNode({layer: d.layer, param: this.param, item: a, allItems: b, delimiter: this.delimiter});
        var c = d.item(0);
        if (c) {
            d.insertBefore(e, c)
        } else {
            d.appendChild(e)
        }
    }, createNode: function (attr) {
        if (this.baseAttrs) {
            Ext.apply(attr, this.baseAttrs)
        }
        if (typeof attr.uiProvider == "string") {
            attr.uiProvider = this.uiProviders[attr.uiProvider] || eval(attr.uiProvider)
        }
        attr.nodeType = attr.nodeType || "gx_layerparam";
        return new Ext.tree.TreePanel.nodeTypes[attr.nodeType](attr)
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.PrintPage = Ext.extend(Ext.util.Observable, {
    printProvider: null,
    feature: null,
    center: null,
    scale: null,
    rotation: 0,
    customParams: null,
    constructor: function (a) {
        this.initialConfig = a;
        Ext.apply(this, a);
        if (!this.customParams) {
            this.customParams = {}
        }
        this.addEvents("change");
        GeoExt.data.PrintPage.superclass.constructor.apply(this, arguments);
        this.feature = new OpenLayers.Feature.Vector(OpenLayers.Geometry.fromWKT("POLYGON((-1 -1,1 -1,1 1,-1 1,-1 -1))"));
        if (this.printProvider.capabilities) {
            this.setScale(this.printProvider.scales.getAt(0))
        } else {
            this.printProvider.on({
                loadcapabilities: function () {
                    this.setScale(this.printProvider.scales.getAt(0))
                }, scope: this, single: true
            })
        }
        this.printProvider.on({layoutchange: this.onLayoutChange, scope: this})
    },
    getPrintExtent: function (a) {
        a = a instanceof GeoExt.MapPanel ? a.map : a;
        return this.calculatePageBounds(this.scale, a.getUnits())
    },
    setScale: function (e, a) {
        var d = this.calculatePageBounds(e, a);
        var c = d.toGeometry();
        var b = this.rotation;
        if (b != 0) {
            c.rotate(-b, c.getCentroid())
        }
        this.updateFeature(c, {scale: e})
    },
    setCenter: function (a) {
        var d = this.feature.geometry;
        var e = d.getBounds().getCenterLonLat();
        var c = a.lon - e.lon;
        var b = a.lat - e.lat;
        d.move(c, b);
        this.updateFeature(d, {center: a})
    },
    setRotation: function (b, c) {
        if (c || this.printProvider.layout.get("rotation") === true) {
            var a = this.feature.geometry;
            a.rotate(this.rotation - b, a.getCentroid());
            this.updateFeature(a, {rotation: b})
        }
    },
    fit: function (h, j) {
        j = j || {};
        var b = h, i;
        if (h instanceof GeoExt.MapPanel) {
            b = h.map
        } else {
            if (h instanceof OpenLayers.Feature.Vector) {
                b = h.layer.map;
                i = h.geometry.getBounds()
            }
        }
        if (!i) {
            i = b.getExtent();
            if (!i) {
                return
            }
        }
        this._updating = true;
        var a = i.getCenterLonLat();
        this.setCenter(a);
        var g = b.getUnits();
        var d = this.printProvider.scales.getAt(0);
        var c = Number.POSITIVE_INFINITY;
        var e = i.getWidth();
        var f = i.getHeight();
        this.printProvider.scales.each(function (n) {
            var l = this.calculatePageBounds(n, g);
            if (j.mode == "closest") {
                var m = Math.abs(l.getWidth() - e) + Math.abs(l.getHeight() - f);
                if (m < c) {
                    c = m;
                    d = n
                }
            } else {
                var k = j.mode == "screen" ? !i.containsBounds(l) : l.containsBounds(i);
                if (k || (j.mode == "screen" && !k)) {
                    d = n
                }
                return k
            }
        }, this);
        this.setScale(d, g);
        delete this._updating;
        this.updateFeature(this.feature.geometry, {center: a, scale: d})
    },
    updateFeature: function (e, b) {
        var d = this.feature;
        var a = d.geometry !== e;
        e.id = d.geometry.id;
        d.geometry = e;
        if (!this._updating) {
            for (var c in b) {
                if (b[c] === this[c]) {
                    delete b[c]
                } else {
                    this[c] = b[c];
                    a = true
                }
            }
            Ext.apply(this, b);
            d.layer && d.layer.drawFeature(d);
            a && this.fireEvent("change", this, b)
        }
    },
    calculatePageBounds: function (b, g) {
        var k = b.get("value");
        var e = this.feature;
        var i = this.feature.geometry;
        var a = i.getBounds().getCenterLonLat();
        var l = this.printProvider.layout.get("size");
        var g = g || (e.layer && e.layer.map && e.layer.map.getUnits()) || "dd";
        var d = OpenLayers.INCHES_PER_UNIT[g];
        var j = l.width / 72 / d * k / 2;
        var c = l.height / 72 / d * k / 2;
        return new OpenLayers.Bounds(a.lon - j, a.lat - c, a.lon + j, a.lat + c)
    },
    onLayoutChange: function () {
        if (this.printProvider.layout.get("rotation") === false) {
            this.setRotation(0, true)
        }
        this.scale && this.setScale(this.scale)
    },
    destroy: function () {
        this.printProvider.un("layoutchange", this.onLayoutChange, this)
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.LayerRecord = Ext.data.Record.create([{name: "layer"}, {name: "title", type: "string", mapping: "name"}]);
GeoExt.data.LayerRecord.prototype.getLayer = function () {
    return this.get("layer")
};
GeoExt.data.LayerRecord.prototype.setLayer = function (a) {
    if (a !== this.data.layer) {
        this.dirty = true;
        if (!this.modified) {
            this.modified = {}
        }
        if (this.modified.layer === undefined) {
            this.modified.layer = this.data.layer
        }
        this.data.layer = a;
        if (!this.editing) {
            this.afterEdit()
        }
    }
};
GeoExt.data.LayerRecord.prototype.clone = function (b) {
    var a = this.getLayer() && this.getLayer().clone();
    return new this.constructor(Ext.applyIf({layer: a}, this.data), b || a.id)
};
GeoExt.data.LayerRecord.create = function (e) {
    var c = Ext.extend(GeoExt.data.LayerRecord, {});
    var d = c.prototype;
    d.fields = new Ext.util.MixedCollection(false, function (f) {
        return f.name
    });
    GeoExt.data.LayerRecord.prototype.fields.each(function (g) {
        d.fields.add(g)
    });
    if (e) {
        for (var b = 0, a = e.length; b < a; b++) {
            d.fields.add(new Ext.data.Field(e[b]))
        }
    }
    c.getField = function (f) {
        return d.fields.get(f)
    };
    return c
};
Ext.namespace("GeoExt.form");
GeoExt.form.toFilter = function (b, d, e) {
    if (b instanceof Ext.form.FormPanel) {
        b = b.getForm()
    }
    var c = [], h = b.getValues(false);
    for (var a in h) {
        var i = a.split("__");
        var g = h[a], f;
        if (i.length > 1 && (f = GeoExt.form.toFilter.FILTER_MAP[i[1]]) !== undefined) {
            a = i[0]
        } else {
            f = OpenLayers.Filter.Comparison.EQUAL_TO
        }
        if (f === OpenLayers.Filter.Comparison.LIKE) {
            switch (e) {
                case GeoExt.form.ENDS_WITH:
                    g = ".*" + g;
                    break;
                case GeoExt.form.STARTS_WITH:
                    g += ".*";
                    break;
                case GeoExt.form.CONTAINS:
                    g = ".*" + g + ".*";
                    break;
                default:
                    break
            }
        }
        c.push(new OpenLayers.Filter.Comparison({type: f, value: g, property: a}))
    }
    return c.length == 1 && d != OpenLayers.Filter.Logical.NOT ? c[0] : new OpenLayers.Filter.Logical({
        type: d || OpenLayers.Filter.Logical.AND,
        filters: c
    })
};
GeoExt.form.toFilter.FILTER_MAP = {
    eq: OpenLayers.Filter.Comparison.EQUAL_TO,
    ne: OpenLayers.Filter.Comparison.NOT_EQUAL_TO,
    lt: OpenLayers.Filter.Comparison.LESS_THAN,
    le: OpenLayers.Filter.Comparison.LESS_THAN_OR_EQUAL_TO,
    gt: OpenLayers.Filter.Comparison.GREATER_THAN,
    ge: OpenLayers.Filter.Comparison.GREATER_THAN_OR_EQUAL_TO,
    like: OpenLayers.Filter.Comparison.LIKE
};
GeoExt.form.ENDS_WITH = 1;
GeoExt.form.STARTS_WITH = 2;
GeoExt.form.CONTAINS = 3;
GeoExt.form.recordToField = function (i, q) {
    q = q || {};
    var l = i.get("type");
    if (typeof l === "object" && l.xtype) {
        return l
    }
    l = l.split(":").pop();
    var n;
    var d = i.get("name");
    var g = i.get("restriction") || {};
    var c = i.get("nillable") || false;
    var o = i.get("label");
    var b = q.labelTpl;
    // HACK. Render combo box if WFS enumeration
    var arrStore = null;
    if (typeof i.data.restriction !== "undefined") {
        if (typeof i.data.restriction.enumeration !== "undefined") {
            arrStore = i.data.restriction.enumeration;
            if (typeof arrStore === "string") {
                arrStore = [arrStore];
            }
        }
    }
    if (b) {
        var k = (b instanceof Ext.Template) ? b : new Ext.XTemplate(b);
        o = k.apply(i.data)
    } else {
        if (o == null) {
            o = d
        }
    }
    var h = {
        name: d,
        labelStyle: c ? "" : q.mandatoryFieldLabelStyle != null ? q.mandatoryFieldLabelStyle : "font-weight:bold;"
    };
    var a = GeoExt.form.recordToField.REGEXES;
    if (l.match(a.text)) {
        var e = g.maxLength !== undefined ? parseFloat(g.maxLength) : undefined;
        var f = g.minLength !== undefined ? parseFloat(g.minLength) : undefined;
        if (!arrStore) {
            if (e) {
                n = Ext.apply({xtype: "textfield", fieldLabel: o, maxLength: e, minLength: f}, h);
            } else {
                n = Ext.apply({xtype: "textarea", fieldLabel: o, maxLength: e, minLength: f}, h);
            }
        } else {
            n = Ext.apply(new Ext.form.ComboBox({
                store: arrStore,
                editable: false,
                triggerAction: 'all',
                fieldLabel: o, maxLength: e, minLength: f
            }), h)
        }
    } else if (l.match(a.number)) {
        var j = g.maxInclusive !== undefined ? parseFloat(g.maxInclusive) : undefined;
        var m = g.minInclusive !== undefined ? parseFloat(g.minInclusive) : undefined;
        if (!arrStore) {
            n = Ext.apply({xtype: "numberfield", fieldLabel: o, maxValue: j, minValue: m}, h)
        } else {
            n = Ext.apply(new Ext.form.ComboBox({
                store: arrStore,
                editable: false,
                triggerAction: 'all',
                fieldLabel: o, maxValue: j, minValue: m
            }), h)
        }
    } else if (l.match(a.boolean)) {
        n = Ext.apply({xtype: "checkbox"}, h);
        var p = q.checkboxLabelProperty || "boxLabel";
        n[p] = o
    } else if (l.match(a.date)) {
        n = Ext.apply(new Ext.form.DateField({
            fieldLabel: o,
            convert: function (value, records) {
                var rcptDate = new Date(value);
                return Ext.Date.format(rcptDate, 'm-d-Y g:i A');
            }
        }), h)
    } else if (l.match(a.imageType)) {
        n = Ext.apply({
            xtype: 'fileuploadfield',
            emptyText: 'Image byte string',
            fieldLabel: o,
            readOnly: false,
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            },
            listeners: {
                'afterrender': function(cmp) {
                    cmp.getEl().next().set({
                        "accept": "image/*"
                    });
                },
                'fileselected': function (fb, v) {
                    var reader = new FileReader(), img = document.createElement("img"),
                        file = document.querySelector('#' + fb.fileInput.id).files[0];
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        var canvas = document.createElement("canvas"),
                            ctx = canvas.getContext("2d"),
                            MAX_WIDTH = 800,
                            MAX_HEIGHT = 800,
                            width = img.width,
                            height = img.height;
                        ctx.drawImage(img, 0, 0);
                        if (width > height) {
                            if (width > MAX_WIDTH) {
                                height *= MAX_WIDTH / width;
                                width = MAX_WIDTH;
                            }
                        } else {
                            if (height > MAX_HEIGHT) {
                                width *= MAX_HEIGHT / height;
                                height = MAX_HEIGHT;
                            }
                        }
                        canvas.width = width;
                        canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);
                        $("#" + fb.id).val(btoa(canvas.toDataURL("image/png")));
                    };
                    reader.readAsDataURL(file);
                }
            }
        }, h);
    } else if (l.match(a.base64Binary)) {
        n = Ext.apply({
            xtype: 'fileuploadfield',
            emptyText: 'File byte string',
            fieldLabel: o,
            readOnly: false,
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            },
            listeners: {
                'fileselected': function (fb, v) {
                    var reader = new FileReader()
                    file = document.querySelector('#' + fb.fileInput.id).files[0];
                    reader.onload = function (e) {
                        $("#" + fb.id).val(btoa(reader.result));
                    };
                    reader.readAsDataURL(file);
                }
            }
        }, h);
    }
    return n
}
;
GeoExt.form.recordToField.REGEXES = {
    text: new RegExp("^(text|string)$", "i"),
    number: new RegExp("^(number|float|decimal|double|int|long|integer|short)$", "i"),
    "boolean": new RegExp("^(boolean)$", "i"),
    date: new RegExp("^(date|dateTime)$", "i"),
    base64Binary: new RegExp("^(base64Binary)$", "i"),
    imageType: new RegExp("^(imageType)$", "i")
};
Ext.namespace("GeoExt");
GeoExt.FeatureRenderer = Ext.extend(Ext.BoxComponent, {
    feature: undefined,
    symbolizers: [OpenLayers.Feature.Vector.style["default"]],
    symbolType: "Polygon",
    resolution: 1,
    minWidth: 20,
    minHeight: 20,
    renderers: ["SVG", "VML", "Canvas"],
    rendererOptions: null,
    pointFeature: undefined,
    lineFeature: undefined,
    polygonFeature: undefined,
    renderer: null,
    initComponent: function () {
        GeoExt.FeatureRenderer.superclass.initComponent.apply(this, arguments);
        Ext.applyIf(this, {
            pointFeature: new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(0, 0)),
            lineFeature: new OpenLayers.Feature.Vector(new OpenLayers.Geometry.LineString([new OpenLayers.Geometry.Point(-8, -3), new OpenLayers.Geometry.Point(-3, 3), new OpenLayers.Geometry.Point(3, -3), new OpenLayers.Geometry.Point(8, 3)])),
            polygonFeature: new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Polygon([new OpenLayers.Geometry.LinearRing([new OpenLayers.Geometry.Point(-8, -4), new OpenLayers.Geometry.Point(-6, -6), new OpenLayers.Geometry.Point(6, -6), new OpenLayers.Geometry.Point(8, -4), new OpenLayers.Geometry.Point(8, 4), new OpenLayers.Geometry.Point(6, 6), new OpenLayers.Geometry.Point(-6, 6), new OpenLayers.Geometry.Point(-8, 4)])]))
        });
        if (!this.feature) {
            this.setFeature(null, {draw: false})
        }
        this.addEvents("click")
    },
    initCustomEvents: function () {
        this.clearCustomEvents();
        this.el.on("click", this.onClick, this)
    },
    clearCustomEvents: function () {
        if (this.el && this.el.removeAllListeners) {
            this.el.removeAllListeners()
        }
    },
    onClick: function () {
        this.fireEvent("click", this)
    },
    onRender: function (b, a) {
        if (!this.el) {
            this.el = document.createElement("div");
            this.el.id = this.getId()
        }
        if (!this.renderer || !this.renderer.supported()) {
            this.assignRenderer()
        }
        this.renderer.map = {
            getResolution: (function () {
                return this.resolution
            }).createDelegate(this)
        };
        GeoExt.FeatureRenderer.superclass.onRender.apply(this, arguments);
        this.drawFeature()
    },
    afterRender: function () {
        GeoExt.FeatureRenderer.superclass.afterRender.apply(this, arguments);
        this.initCustomEvents()
    },
    onResize: function (a, b) {
        this.setRendererDimensions();
        GeoExt.FeatureRenderer.superclass.onResize.apply(this, arguments)
    },
    setRendererDimensions: function () {
        var h = this.feature.geometry.getBounds();
        var j = h.getWidth();
        var g = h.getHeight();
        var e = this.initialConfig.resolution;
        if (!e) {
            e = Math.max(j / this.width || 0, g / this.height || 0) || 1
        }
        this.resolution = e;
        var c = Math.max(this.width || this.minWidth, j / e);
        var i = Math.max(this.height || this.minHeight, g / e);
        var b = h.getCenterPixel();
        var f = c * e / 2;
        var d = i * e / 2;
        var a = new OpenLayers.Bounds(b.x - f, b.y - d, b.x + f, b.y + d);
        this.renderer.setSize(new OpenLayers.Size(Math.round(c), Math.round(i)));
        this.renderer.setExtent(a, true)
    },
    assignRenderer: function () {
        for (var b = 0, a = this.renderers.length; b < a; ++b) {
            var c = OpenLayers.Renderer[this.renderers[b]];
            if (c && c.prototype.supported()) {
                this.renderer = new c(this.el, this.rendererOptions);
                break
            }
        }
    },
    setSymbolizers: function (b, a) {
        this.symbolizers = b;
        if (!a || a.draw) {
            this.drawFeature()
        }
    },
    setSymbolType: function (b, a) {
        this.symbolType = b;
        this.setFeature(null, a)
    },
    setFeature: function (b, a) {
        this.feature = b || this[this.symbolType.toLowerCase() + "Feature"];
        if (!a || a.draw) {
            this.drawFeature()
        }
    },
    drawFeature: function () {
        this.renderer.clear();
        this.setRendererDimensions();
        var c = OpenLayers.Symbolizer;
        var g = c && c.Text;
        var f, e, b;
        for (var d = 0, a = this.symbolizers.length; d < a; ++d) {
            f = this.symbolizers[d];
            e = this.feature;
            if (!g || !(f instanceof g)) {
                if (c && (f instanceof c)) {
                    f = f.clone();
                    if (!this.initialConfig.feature) {
                        b = f.CLASS_NAME.split(".").pop().toLowerCase();
                        e = this[b + "Feature"]
                    }
                } else {
                    f = Ext.apply({}, f)
                }
                this.renderer.drawFeature(e.clone(), f)
            }
        }
    },
    update: function (a) {
        a = a || {};
        if (a.feature) {
            this.setFeature(a.feature, {draw: false})
        } else {
            if (a.symbolType) {
                this.setSymbolType(a.symbolType, {draw: false})
            }
        }
        if (a.symbolizers) {
            this.setSymbolizers(a.symbolizers, {draw: false})
        }
        this.drawFeature()
    },
    beforeDestroy: function () {
        this.clearCustomEvents();
        if (this.renderer) {
            this.renderer.destroy()
        }
    }
});
Ext.reg("gx_renderer", GeoExt.FeatureRenderer);
Ext.namespace("GeoExt");
GeoExt.Popup = Ext.extend(Ext.Window, {
    anchored: true,
    map: null,
    panIn: true,
    unpinnable: true,
    location: null,
    insideViewport: null,
    animCollapse: false,
    draggable: false,
    shadow: false,
    popupCls: "gx-popup",
    ancCls: null,
    anchorPosition: "auto",
    initComponent: function () {
        if (this.map instanceof GeoExt.MapPanel) {
            this.map = this.map.map
        }
        if (!this.map && this.location instanceof OpenLayers.Feature.Vector && this.location.layer) {
            this.map = this.location.layer.map
        }
        if (this.location instanceof OpenLayers.Feature.Vector) {
            this.location = this.location.geometry
        }
        if (this.location instanceof OpenLayers.Geometry) {
            if (typeof this.location.getCentroid == "function") {
                this.location = this.location.getCentroid()
            }
            this.location = this.location.getBounds().getCenterLonLat()
        } else {
            if (this.location instanceof OpenLayers.Pixel) {
                this.location = this.map.getLonLatFromViewPortPx(this.location)
            }
        }
        var a = this.map.getExtent();
        if (a && this.location) {
            this.insideViewport = a.containsLonLat(this.location)
        }
        if (this.anchored) {
            this.addAnchorEvents()
        }
        this.baseCls = this.popupCls + " " + this.baseCls;
        this.elements += ",anc";
        GeoExt.Popup.superclass.initComponent.call(this)
    },
    onRender: function (b, a) {
        GeoExt.Popup.superclass.onRender.call(this, b, a);
        this.ancCls = this.popupCls + "-anc";
        this.createElement("anc", this.el.dom)
    },
    initTools: function () {
        if (this.unpinnable) {
            this.addTool({id: "unpin", handler: this.unanchorPopup.createDelegate(this, [])})
        }
        GeoExt.Popup.superclass.initTools.call(this)
    },
    show: function () {
        GeoExt.Popup.superclass.show.apply(this, arguments);
        if (this.anchored) {
            this.position();
            if (this.panIn && !this._mapMove) {
                this.panIntoView()
            }
        }
    },
    maximize: function () {
        if (!this.maximized && this.anc) {
            this.unanchorPopup()
        }
        GeoExt.Popup.superclass.maximize.apply(this, arguments)
    },
    setSize: function (a, c) {
        if (this.anc) {
            var b = this.anc.getSize();
            if (typeof a == "object") {
                c = a.height - b.height;
                a = a.width
            } else {
                if (!isNaN(c)) {
                    c = c - b.height
                }
            }
        }
        GeoExt.Popup.superclass.setSize.call(this, a, c)
    },
    position: function () {
        if (this._mapMove === true) {
            this.insideViewport = this.map.getExtent().containsLonLat(this.location);
            if (this.insideViewport !== this.isVisible()) {
                this.setVisible(this.insideViewport)
            }
        }
        if (this.isVisible()) {
            var g = this.map.getPixelFromLonLat(this.location), e = Ext.fly(this.map.div).getBox(true), h = g.y + e.y, c = g.x + e.x, i = this.el.getSize(), a = this.anc.getSize(), b = this.anchorPosition;
            if (b.indexOf("right") > -1 || g.x > e.width / 2) {
                this.anc.addClass("right");
                var d = this.el.getX(true) + i.width - this.anc.getX(true) - a.width;
                c -= i.width - d - a.width / 2
            } else {
                this.anc.removeClass("right");
                var f = this.anc.getLeft(true);
                c -= f + a.width / 2
            }
            if (b.indexOf("bottom") > -1 || g.y > e.height / 2) {
                this.anc.removeClass("top");
                h -= i.height + a.height
            } else {
                this.anc.addClass("top");
                h += a.height
            }
            this.setPosition(c, h)
        }
    },
    unanchorPopup: function () {
        this.removeAnchorEvents();
        this.draggable = true;
        this.header.addClass("x-window-draggable");
        this.dd = new Ext.Window.DD(this);
        this.anc.remove();
        this.anc = null;
        this.tools.unpin.hide()
    },
    panIntoView: function () {
        var h = Ext.fly(this.map.div).getBox(true);
        var e = this.getPosition(true);
        e[0] -= h.x;
        e[1] -= h.y;
        var a = [h.width, h.height];
        var g = this.getSize();
        var d = [e[0], e[1]];
        var f = this.map.paddingForPopups;
        if (e[0] < f.left) {
            d[0] = f.left
        } else {
            if (e[0] + g.width > a[0] - f.right) {
                d[0] = a[0] - f.right - g.width
            }
        }
        if (e[1] < f.top) {
            d[1] = f.top
        } else {
            if (e[1] + g.height > a[1] - f.bottom) {
                d[1] = a[1] - f.bottom - g.height
            }
        }
        var c = e[0] - d[0];
        var b = e[1] - d[1];
        this.map.pan(c, b)
    },
    onMapMove: function () {
        if (!(this.hidden && this.insideViewport)) {
            this._mapMove = true;
            this.position();
            delete this._mapMove
        }
    },
    addAnchorEvents: function () {
        this.map.events.on({move: this.onMapMove, scope: this});
        this.on({resize: this.position, collapse: this.position, expand: this.position, scope: this})
    },
    removeAnchorEvents: function () {
        this.map.events.un({move: this.onMapMove, scope: this});
        this.un("resize", this.position, this);
        this.un("collapse", this.position, this);
        this.un("expand", this.position, this)
    },
    beforeDestroy: function () {
        if (this.anchored) {
            this.removeAnchorEvents()
        }
        GeoExt.Popup.superclass.beforeDestroy.call(this)
    }
});
Ext.reg("gx_popup", GeoExt.Popup);
Ext.namespace("GeoExt.data");
GeoExt.data.AttributeStoreMixin = function () {
    return {
        constructor: function (a) {
            a = a || {};
            arguments.callee.superclass.constructor.call(this, Ext.apply(a, {
                proxy: a.proxy || (!a.data ? new Ext.data.HttpProxy({
                    url: a.url,
                    disableCaching: false,
                    method: "GET"
                }) : undefined),
                reader: new GeoExt.data.AttributeReader(a, a.fields || ["name", "type", "restriction", {
                    name: "nillable",
                    type: "boolean"
                }])
            }));
            if (this.feature) {
                this.bind()
            }
        }, bind: function () {
            this.on({update: this.onUpdate, load: this.onLoad, add: this.onAdd, scope: this});
            var a = [];
            this.each(function (b) {
                a.push(b)
            });
            this.updateFeature(a)
        }, onUpdate: function (c, a, b) {
            this.updateFeature([a])
        }, onLoad: function (b, a, c) {
            if (!c || c.add !== true) {
                this.updateFeature(a)
            }
        }, onAdd: function (b, a, c) {
            this.updateFeature(a)
        }, updateFeature: function (d) {
            var k = this.feature, g = k.layer;
            var e, h, f, c, j, a, b;
            for (e = 0, h = d.length; e < h; e++) {
                f = d[e];
                c = f.get("name");
                j = f.get("value");
                a = k.attributes[c];
                if (a !== j) {
                    b = true
                }
            }
            if (b && g && g.events && g.events.triggerEvent("beforefeaturemodified", {feature: k}) !== false) {
                for (e = 0, h = d.length; e < h; e++) {
                    f = d[e];
                    c = f.get("name");
                    j = f.get("value");
                    k.attributes[c] = j
                }
                g.events.triggerEvent("featuremodified", {feature: k});
                g.drawFeature(k)
            }
        }
    }
};
GeoExt.data.AttributeStore = Ext.extend(Ext.data.Store, GeoExt.data.AttributeStoreMixin());
Ext.namespace("GeoExt.form");
GeoExt.form.FormPanel = Ext.extend(Ext.form.FormPanel, {
    protocol: null, createForm: function () {
        delete this.initialConfig.listeners;
        return new GeoExt.form.BasicForm(null, this.initialConfig)
    }, search: function (a) {
        this.getForm().search(a)
    }
});
Ext.reg("gx_formpanel", GeoExt.form.FormPanel);
Ext.namespace("GeoExt.data");
GeoExt.data.WMCReader = function (a, b) {
    a = a || {};
    if (!a.format) {
        a.format = new OpenLayers.Format.WMC()
    }
    if (!(typeof b === "function")) {
        b = GeoExt.data.LayerRecord.create(b || a.fields || [{name: "abstract", type: "string"}, {
            name: "metadataURL",
            type: "string"
        }, {name: "queryable", type: "boolean"}, {name: "formats"}, {name: "styles"}])
    }
    GeoExt.data.WMCReader.superclass.constructor.call(this, a, b)
};
Ext.extend(GeoExt.data.WMCReader, Ext.data.DataReader, {
    read: function (a) {
        var b = a.responseXML;
        if (!b || !b.documentElement) {
            b = a.responseText
        }
        return this.readRecords(b)
    }, readRecords: function (f) {
        var m = this.meta.format;
        if (typeof f === "string" || f.nodeType) {
            f = m.read(f)
        }
        var p = f ? f.layersContext : undefined;
        var a = [];
        if (p) {
            var c = this.recordType, h = c.prototype.fields;
            var g, d, e, b, l, o, k, n;
            for (g = 0, d = p.length; g < d; g++) {
                l = p[g];
                o = {};
                for (e = 0, b = h.length; e < b; e++) {
                    k = h.items[e];
                    n = l[k.mapping || k.name] || k.defaultValue;
                    n = k.convert(n);
                    o[k.name] = n
                }
                o.layer = m.getLayerFromContext(l);
                a.push(new this.recordType(o, o.layer.id))
            }
        }
        return {totalRecords: a.length, success: true, records: a}
    }
});
GeoExt.Lang.add("fr", {
    "GeoExt.tree.LayerContainer.prototype": {text: "Couches"},
    "GeoExt.tree.BaseLayerContainer.prototype": {text: "Couches de base"},
    "GeoExt.tree.OverlayLayerContainer.prototype": {text: "Couches additionnelles"}
});
Ext.namespace("GeoExt.tree");
GeoExt.tree.OverlayLayerContainer = Ext.extend(GeoExt.tree.LayerContainer, {
    text: "Overlays",
    constructor: function (a) {
        a = Ext.applyIf(a || {}, {text: this.text});
        a.loader = Ext.applyIf(a.loader || {}, {
            filter: function (b) {
                var c = b.getLayer();
                return c.displayInLayerSwitcher === true && c.isBaseLayer === false
            }
        });
        GeoExt.tree.OverlayLayerContainer.superclass.constructor.call(this, a)
    }
});
Ext.tree.TreePanel.nodeTypes.gx_overlaylayercontainer = GeoExt.tree.OverlayLayerContainer;
Ext.namespace("GeoExt.data");
GeoExt.data.WFSCapabilitiesReader = function (a, b) {
    a = a || {};
    if (!a.format) {
        a.format = new OpenLayers.Format.WFSCapabilities()
    }
    if (!(typeof b === "function")) {
        b = GeoExt.data.LayerRecord.create(b || a.fields || [{name: "name", type: "string"}, {
            name: "title",
            type: "string"
        }, {name: "namespace", type: "string", mapping: "featureNS"}, {name: "abstract", type: "string"}])
    }
    GeoExt.data.WFSCapabilitiesReader.superclass.constructor.call(this, a, b)
};
Ext.extend(GeoExt.data.WFSCapabilitiesReader, Ext.data.DataReader, {
    read: function (a) {
        var b = a.responseXML;
        if (!b || !b.documentElement) {
            b = a.responseText
        }
        return this.readRecords(b)
    }, readRecords: function (t) {
        if (typeof t === "string" || t.nodeType) {
            t = this.meta.format.read(t)
        }
        var g = t.featureTypeList.featureTypes;
        var f = this.recordType.prototype.fields;
        var d, c, b, e, h, s;
        var a, k;
        var l = {url: t.capability.request.getfeature.href.post};
        var o = [];
        for (var n = 0, q = g.length; n < q; n++) {
            d = g[n];
            if (d.name) {
                c = {};
                for (var m = 0, p = f.length; m < p; m++) {
                    b = f.items[m];
                    e = d[b.mapping || b.name] || b.defaultValue;
                    e = b.convert(e);
                    c[b.name] = e
                }
                k = {featureType: d.name, featureNS: d.featureNS};
                if (this.meta.protocolOptions) {
                    Ext.apply(k, this.meta.protocolOptions, l)
                } else {
                    Ext.apply(k, {}, l)
                }
                a = {protocol: new OpenLayers.Protocol.WFS(k), strategies: [new OpenLayers.Strategy.Fixed()]};
                var r = this.meta.layerOptions;
                if (r) {
                    Ext.apply(a, Ext.isFunction(r) ? r() : r)
                }
                c.layer = new OpenLayers.Layer.Vector(d.title || d.name, a);
                o.push(new this.recordType(c, c.layer.id))
            }
        }
        return {totalRecords: o.length, success: true, records: o}
    }
});
Ext.namespace("GeoExt");
GeoExt.LegendPanel = Ext.extend(Ext.Panel, {
    dynamic: true,
    layerStore: null,
    preferredTypes: null,
    filter: function (a) {
        return true
    },
    initComponent: function () {
        GeoExt.LegendPanel.superclass.initComponent.call(this)
    },
    onRender: function () {
        GeoExt.LegendPanel.superclass.onRender.apply(this, arguments);
        if (!this.layerStore) {
            this.layerStore = GeoExt.MapPanel.guess().layers
        }
        this.layerStore.each(function (a) {
            this.addLegend(a)
        }, this);
        if (this.dynamic) {
            this.layerStore.on({
                add: this.onStoreAdd,
                remove: this.onStoreRemove,
                clear: this.onStoreClear,
                scope: this
            })
        }
    },
    recordIndexToPanelIndex: function (h) {
        var j = this.layerStore;
        var g = j.getCount();
        var c = -1;
        var a = this.items ? this.items.length : 0;
        var d, e;
        for (var b = g - 1; b >= 0; --b) {
            d = j.getAt(b);
            e = d.getLayer();
            var f = GeoExt.LayerLegend.getTypes(d);
            if (e.displayInLayerSwitcher && f.length > 0 && (j.getAt(b).get("hideInLegend") !== true)) {
                ++c;
                if (h === b || c > a - 1) {
                    break
                }
            }
        }
        return c
    },
    getIdForLayer: function (a) {
        return this.id + "-" + a.id
    },
    onStoreAdd: function (c, b, d) {
        var f = this.recordIndexToPanelIndex(d + b.length - 1);
        for (var e = 0, a = b.length; e < a; e++) {
            this.addLegend(b[e], f)
        }
        this.doLayout()
    },
    onStoreRemove: function (b, a, c) {
        this.removeLegend(a)
    },
    removeLegend: function (a) {
        if (this.items) {
            var b = this.getComponent(this.getIdForLayer(a.getLayer()));
            if (b) {
                this.remove(b, true);
                this.doLayout()
            }
        }
    },
    onStoreClear: function (a) {
        this.removeAllLegends()
    },
    removeAllLegends: function () {
        this.removeAll(true);
        this.doLayout()
    },
    addLegend: function (a, b) {
        if (this.filter(a) === true) {
            var d = a.getLayer();
            b = b || 0;
            var e;
            var c = GeoExt.LayerLegend.getTypes(a, this.preferredTypes);
            if (d.displayInLayerSwitcher && !a.get("hideInLegend") && c.length > 0) {
                this.insert(b, {
                    xtype: c[0],
                    id: this.getIdForLayer(d),
                    layerRecord: a,
                    hidden: !((!d.map && d.visibility) || (d.getVisibility() && d.calculateInRange()))
                })
            }
        }
    },
    onDestroy: function () {
        if (this.layerStore) {
            this.layerStore.un("add", this.onStoreAdd, this);
            this.layerStore.un("remove", this.onStoreRemove, this);
            this.layerStore.un("clear", this.onStoreClear, this)
        }
        GeoExt.LegendPanel.superclass.onDestroy.apply(this, arguments)
    }
});
Ext.reg("gx_legendpanel", GeoExt.LegendPanel);
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.TreeNodeActions = Ext.extend(Ext.util.Observable, {
    actionsCls: "gx-tree-layer-actions",
    actionCls: "gx-tree-layer-action",
    constructor: function (a) {
        Ext.apply(this.initialConfig, Ext.apply({}, a));
        Ext.apply(this, a);
        this.addEvents("action");
        GeoExt.plugins.TreeNodeActions.superclass.constructor.apply(this, arguments)
    },
    init: function (a) {
        a.on({
            rendernode: this.onRenderNode,
            rawclicknode: this.onRawClickNode,
            beforedestroy: this.onBeforeDestroy,
            scope: this
        })
    },
    onRenderNode: function (g) {
        var j = g.rendered;
        if (!j) {
            var c = g.attributes;
            var h = c.actions || this.actions;
            if (h && h.length > 0) {
                var f = ['<div class="', this.actionsCls, '">'];
                for (var e = 0, b = h.length; e < b; e++) {
                    var d = h[e];
                    f = f.concat(['<img id="' + g.id + "_" + d.action, '" ext:qtip="' + d.qtip, '" src="' + Ext.BLANK_IMAGE_URL, '" class="' + this.actionCls + " " + d.action + '" />'])
                }
                f.concat(["</div>"]);
                Ext.DomHelper.insertFirst(g.ui.elNode, f.join(""))
            }
            if (g.layer && g.layer.map) {
                this.updateActions(g)
            } else {
                if (g.layerStore) {
                    g.layerStore.on({
                        bind: function () {
                            this.updateActions(g)
                        }, scope: this
                    })
                }
            }
        }
    },
    updateActions: function (a) {
        var b = a.attributes.actions || this.actions || [];
        Ext.each(b, function (c, d) {
            var e = Ext.get(a.id + "_" + c.action);
            if (e && typeof c.update == "function") {
                c.update.call(a, e)
            }
        })
    },
    onRawClickNode: function (b, d) {
        if (d.getTarget("." + this.actionCls, 1)) {
            var a = d.getTarget("." + this.actionCls, 1);
            var c = a.className.replace(this.actionCls + " ", "");
            this.fireEvent("action", b, c, d);
            return false
        }
    },
    onBeforeDestroy: function (a) {
        a.un("rendernode", this.onRenderNode, this);
        a.un("rawclicknode", this.onRawClickNode, this);
        a.un("beforedestroy", this.onBeforeDestroy, this)
    }
});
Ext.preg("gx_treenodeactions", GeoExt.plugins.TreeNodeActions);
GeoExt.Lang.add("nl", {
    "GeoExt.tree.LayerContainer.prototype": {text: "Kaartlagen"},
    "GeoExt.tree.BaseLayerContainer.prototype": {text: "Basis kaarten"},
    "GeoExt.tree.OverlayLayerContainer.prototype": {text: "Kaart overlays"}
});
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.PrintPageField = Ext.extend(Ext.util.Observable, {
    printPage: null,
    target: null,
    constructor: function (a) {
        this.initialConfig = a;
        Ext.apply(this, a);
        GeoExt.plugins.PrintPageField.superclass.constructor.apply(this, arguments)
    },
    init: function (c) {
        this.target = c;
        var b = {beforedestroy: this.onBeforeDestroy, scope: this};
        var a = c instanceof Ext.form.ComboBox ? "select" : c instanceof Ext.form.Checkbox ? "check" : "valid";
        b[a] = this.onFieldChange;
        c.on(b);
        this.printPage.on({change: this.onPageChange, scope: this});
        this.printPage.printProvider.on({layoutchange: this.onLayoutChange, scope: this});
        this.setValue(this.printPage)
    },
    onFieldChange: function (c, a) {
        var d = this.printPage.printProvider;
        var b = c.getValue();
        this._updating = true;
        if (c.store === d.scales || c.name === "scale") {
            this.printPage.setScale(a)
        } else {
            if (c.name == "rotation") {
                !isNaN(b) && this.printPage.setRotation(b)
            } else {
                this.printPage.customParams[c.name] = b
            }
        }
        delete this._updating
    },
    onPageChange: function (a) {
        if (!this._updating) {
            this.setValue(a)
        }
    },
    onLayoutChange: function (c, b) {
        var a = this.target;
        a.name == "rotation" && a.setDisabled(!b.get("rotation"))
    },
    setValue: function (a) {
        var b = this.target;
        b.suspendEvents();
        if (b.store === a.printProvider.scales || b.name === "scale") {
            if (a.scale) {
                b.setValue(a.scale.get(b.displayField))
            }
        } else {
            if (b.name == "rotation") {
                b.setValue(a.rotation)
            }
        }
        b.resumeEvents()
    },
    onBeforeDestroy: function () {
        this.target.un("beforedestroy", this.onBeforeDestroy, this);
        this.target.un("select", this.onFieldChange, this);
        this.target.un("valid", this.onFieldChange, this);
        this.printPage.un("change", this.onPageChange, this);
        this.printPage.printProvider.un("layoutchange", this.onLayoutChange, this)
    }
});
Ext.preg("gx_printpagefield", GeoExt.plugins.PrintPageField);
Ext.namespace("GeoExt.tree");
GeoExt.tree.LayerNodeUI = Ext.extend(Ext.tree.TreeNodeUI, {
    constructor: function (a) {
        GeoExt.tree.LayerNodeUI.superclass.constructor.apply(this, arguments)
    }, render: function (d) {
        var c = this.node.attributes;
        if (c.checked === undefined) {
            c.checked = this.node.layer.getVisibility()
        }
        GeoExt.tree.LayerNodeUI.superclass.render.apply(this, arguments);
        var b = this.checkbox;
        if (c.checkedGroup) {
            var e = Ext.DomHelper.insertAfter(b, ['<input type="radio" name="', c.checkedGroup, '_checkbox" class="', b.className, b.checked ? '" checked="checked"' : "", '"></input>'].join(""));
            e.defaultChecked = b.defaultChecked;
            Ext.get(b).remove();
            this.checkbox = e
        }
        this.enforceOneVisible()
    }, onClick: function (a) {
        if (a.getTarget(".x-tree-node-cb", 1)) {
            this.toggleCheck(this.isChecked())
        } else {
            GeoExt.tree.LayerNodeUI.superclass.onClick.apply(this, arguments)
        }
    }, toggleCheck: function (a) {
        a = (a === undefined ? !this.isChecked() : a);
        GeoExt.tree.LayerNodeUI.superclass.toggleCheck.call(this, a);
        this.enforceOneVisible()
    }, enforceOneVisible: function () {
        var b = this.node.attributes;
        var e = b.checkedGroup;
        if (e && e !== "gx_baselayer") {
            var d = this.node.layer;
            var a = this.node.getOwnerTree().getChecked();
            var c = 0;
            Ext.each(a, function (g) {
                var f = g.layer;
                if (!g.hidden && g.attributes.checkedGroup === e) {
                    c++;
                    if (f != d && b.checked) {
                        f.setVisibility(false)
                    }
                }
            });
            if (c === 0 && b.checked == false) {
                d.setVisibility(true)
            }
        }
    }, appendDDGhost: function (c) {
        var b = this.elNode.cloneNode(true);
        var a = Ext.DomQuery.select("input[type='radio']", b);
        Ext.each(a, function (d) {
            d.name = d.name + "_clone"
        });
        c.appendChild(b)
    }
});
GeoExt.tree.LayerNode = Ext.extend(Ext.tree.AsyncTreeNode, {
    layer: null, layerStore: null, constructor: function (a) {
        a.leaf = a.leaf || !(a.children || a.loader);
        if (!a.iconCls && !a.children) {
            a.iconCls = "gx-tree-layer-icon"
        }
        if (a.loader && !(a.loader instanceof Ext.tree.TreeLoader)) {
            a.loader = new GeoExt.tree.LayerParamLoader(a.loader)
        }
        this.defaultUI = this.defaultUI || GeoExt.tree.LayerNodeUI;
        Ext.apply(this, {layer: a.layer, layerStore: a.layerStore});
        if (a.text) {
            this.fixedText = true
        }
        GeoExt.tree.LayerNode.superclass.constructor.apply(this, arguments)
    }, render: function (a) {
        var c = this.layer instanceof OpenLayers.Layer && this.layer;
        if (!c) {
            if (!this.layerStore || this.layerStore == "auto") {
                this.layerStore = GeoExt.MapPanel.guess().layers
            }
            var b = this.layerStore.findBy(function (e) {
                return e.get("title") == this.layer
            }, this);
            if (b != -1) {
                c = this.layerStore.getAt(b).getLayer()
            }
        }
        if (!this.rendered || !c) {
            var d = this.getUI();
            if (c) {
                this.layer = c;
                if (c.isBaseLayer) {
                    this.draggable = false;
                    Ext.applyIf(this.attributes, {checkedGroup: "gx_baselayer"})
                }
                if (!this.text) {
                    this.text = c.name
                }
                d.show();
                this.addVisibilityEventHandlers()
            } else {
                d.hide()
            }
            if (this.layerStore instanceof GeoExt.data.LayerStore) {
                this.addStoreEventHandlers(c)
            }
        }
        GeoExt.tree.LayerNode.superclass.render.apply(this, arguments)
    }, addVisibilityEventHandlers: function () {
        this.layer.events.on({visibilitychanged: this.onLayerVisibilityChanged, scope: this});
        this.on({checkchange: this.onCheckChange, scope: this})
    }, onLayerVisibilityChanged: function () {
        if (!this._visibilityChanging) {
            this.getUI().toggleCheck(this.layer.getVisibility())
        }
    }, onCheckChange: function (c, b) {
        if (b != this.layer.getVisibility()) {
            this._visibilityChanging = true;
            var a = this.layer;
            if (b && a.isBaseLayer && a.map) {
                a.map.setBaseLayer(a)
            } else {
                a.setVisibility(b)
            }
            delete this._visibilityChanging
        }
    }, addStoreEventHandlers: function () {
        this.layerStore.on({add: this.onStoreAdd, remove: this.onStoreRemove, update: this.onStoreUpdate, scope: this})
    }, onStoreAdd: function (c, b, d) {
        var a;
        for (var e = 0; e < b.length; ++e) {
            a = b[e].getLayer();
            if (this.layer == a) {
                this.getUI().show();
                break
            } else {
                if (this.layer == a.name) {
                    this.render();
                    break
                }
            }
        }
    }, onStoreRemove: function (b, a, c) {
        if (this.layer == a.getLayer()) {
            this.getUI().hide()
        }
    }, onStoreUpdate: function (c, a, b) {
        var d = a.getLayer();
        if (!this.fixedText && (this.layer == d && this.text !== d.name)) {
            this.setText(d.name)
        }
    }, destroy: function () {
        var b = this.layer;
        if (b instanceof OpenLayers.Layer) {
            b.events.un({visibilitychanged: this.onLayerVisibilityChanged, scope: this})
        }
        delete this.layer;
        var a = this.layerStore;
        if (a) {
            a.un("add", this.onStoreAdd, this);
            a.un("remove", this.onStoreRemove, this);
            a.un("update", this.onStoreUpdate, this)
        }
        delete this.layerStore;
        this.un("checkchange", this.onCheckChange, this);
        GeoExt.tree.LayerNode.superclass.destroy.apply(this, arguments)
    }
});
Ext.tree.TreePanel.nodeTypes.gx_layer = GeoExt.tree.LayerNode;
Ext.namespace("GeoExt.tree");
GeoExt.tree.LayerParamNode = Ext.extend(Ext.tree.TreeNode, {
    layer: null,
    param: null,
    item: null,
    delimiter: null,
    allItems: null,
    constructor: function (a) {
        var b = a || {};
        b.iconCls = b.iconCls || "gx-tree-layerparam-icon";
        b.text = b.text || b.item;
        this.param = b.param;
        this.item = b.item;
        this.delimiter = b.delimiter || ",";
        this.allItems = b.allItems;
        GeoExt.tree.LayerParamNode.superclass.constructor.apply(this, arguments);
        this.getLayer();
        if (this.layer) {
            if (!this.allItems) {
                this.allItems = this.getItemsFromLayer()
            }
            if (this.attributes.checked == null) {
                this.attributes.checked = this.layer.getVisibility() && this.getItemsFromLayer().indexOf(this.item) >= 0
            } else {
                this.onCheckChange(this, this.attributes.checked)
            }
            this.layer.events.on({visibilitychanged: this.onLayerVisibilityChanged, scope: this});
            this.on({checkchange: this.onCheckChange, scope: this})
        }
    },
    getLayer: function () {
        if (!this.layer) {
            var c = this.attributes.layer;
            if (typeof c == "string") {
                var a = this.attributes.layerStore || GeoExt.MapPanel.guess().layers;
                var b = a.findBy(function (d) {
                    return d.get("title") == c
                });
                c = b != -1 ? a.getAt(b).getLayer() : null
            }
            this.layer = c
        }
        return this.layer
    },
    getItemsFromLayer: function () {
        var a = this.layer.params[this.param];
        return a instanceof Array ? a : (a ? a.split(this.delimiter) : [])
    },
    createParams: function (a) {
        var b = {};
        b[this.param] = this.layer.params[this.param] instanceof Array ? a : a.join(this.delimiter);
        return b
    },
    onLayerVisibilityChanged: function () {
        if (this.getItemsFromLayer().length === 0) {
            this.layer.mergeNewParams(this.createParams(this.allItems))
        }
        var a = this.layer.getVisibility();
        if (a && this.getItemsFromLayer().indexOf(this.item) !== -1) {
            this.getUI().toggleCheck(true)
        }
        if (!a) {
            this.layer.mergeNewParams(this.createParams([]));
            this.getUI().toggleCheck(false)
        }
    },
    onCheckChange: function (e, d) {
        var c = this.layer;
        var b = [];
        var a = this.getItemsFromLayer();
        if (d === true && c.getVisibility() === false && a.length === this.allItems.length) {
            a = []
        }
        Ext.each(this.allItems, function (g) {
            if ((g !== this.item && a.indexOf(g) !== -1) || (d === true && g === this.item)) {
                b.push(g)
            }
        }, this);
        var f = (b.length > 0);
        f && c.mergeNewParams(this.createParams(b));
        if (f !== c.getVisibility()) {
            c.setVisibility(f)
        }
        (!f) && c.mergeNewParams(this.createParams([]))
    },
    destroy: function () {
        var a = this.layer;
        if (a instanceof OpenLayers.Layer) {
            a.events.un({visibilitychanged: this.onLayerVisibilityChanged, scope: this})
        }
        delete this.layer;
        this.un("checkchange", this.onCheckChange, this);
        GeoExt.tree.LayerParamNode.superclass.destroy.apply(this, arguments)
    }
});
Ext.tree.TreePanel.nodeTypes.gx_layerparam = GeoExt.tree.LayerParamNode;
Ext.namespace("GeoExt");
GeoExt.UrlLegend = Ext.extend(GeoExt.LayerLegend, {
    initComponent: function () {
        GeoExt.UrlLegend.superclass.initComponent.call(this);
        this.add(new GeoExt.LegendImage({url: this.layerRecord.get("legendURL")}))
    }, update: function () {
        GeoExt.UrlLegend.superclass.update.apply(this, arguments);
        this.items.get(1).setUrl(this.layerRecord.get("legendURL"))
    }
});
GeoExt.UrlLegend.supports = function (a) {
    return a.get("legendURL") == null ? 0 : 10
};
GeoExt.LayerLegend.types.gx_urllegend = GeoExt.UrlLegend;
Ext.reg("gx_urllegend", GeoExt.UrlLegend);
Ext.namespace("GeoExt.state");
GeoExt.state.PermalinkProvider = function (b) {
    GeoExt.state.PermalinkProvider.superclass.constructor.apply(this, arguments);
    b = b || {};
    var a = b.url;
    delete b.url;
    Ext.apply(this, b);
    this.state = this.readURL(a)
};
Ext.extend(GeoExt.state.PermalinkProvider, Ext.state.Provider, {
    encodeType: true, readURL: function (b) {
        var d = {};
        var e = OpenLayers.Util.getParameters(b);
        var a, c, f;
        for (a in e) {
            if (e.hasOwnProperty(a)) {
                c = a.split("_");
                if (c.length > 1) {
                    f = c[0];
                    d[f] = d[f] || {};
                    d[f][c.slice(1).join("_")] = this.encodeType ? this.decodeValue(e[a]) : e[a]
                }
            }
        }
        return d
    }, getLink: function (d) {
        d = d || document.location.href;
        var f = {};
        var g, a, c = this.state;
        for (g in c) {
            if (c.hasOwnProperty(g)) {
                for (a in c[g]) {
                    f[g + "_" + a] = this.encodeType ? unescape(this.encodeValue(c[g][a])) : c[g][a]
                }
            }
        }
        OpenLayers.Util.applyDefaults(f, OpenLayers.Util.getParameters(d));
        var e = OpenLayers.Util.getParameterString(f);
        var b = d.indexOf("?");
        if (b > 0) {
            d = d.substring(0, b)
        }
        return Ext.urlAppend(d, e)
    }
});
Ext.namespace("GeoExt");
GeoExt.LegendImage = Ext.extend(Ext.BoxComponent, {
    url: null,
    defaultImgSrc: null,
    imgCls: null,
    initComponent: function () {
        GeoExt.LegendImage.superclass.initComponent.call(this);
        if (this.defaultImgSrc === null) {
            this.defaultImgSrc = Ext.BLANK_IMAGE_URL
        }
        this.autoEl = {tag: "img", "class": (this.imgCls ? this.imgCls : ""), src: this.defaultImgSrc}
    },
    setUrl: function (a) {
        this.url = a;
        var b = this.getEl();
        if (b) {
            b.un("error", this.onImageLoadError, this);
            b.on("error", this.onImageLoadError, this, {single: true});
            b.dom.src = a
        }
    },
    onRender: function (b, a) {
        GeoExt.LegendImage.superclass.onRender.call(this, b, a);
        if (this.url) {
            this.setUrl(this.url)
        }
    },
    onDestroy: function () {
        var a = this.getEl();
        if (a) {
            a.un("error", this.onImageLoadError, this)
        }
        GeoExt.LegendImage.superclass.onDestroy.apply(this, arguments)
    },
    onImageLoadError: function () {
        this.getEl().dom.src = this.defaultImgSrc
    }
});
Ext.reg("gx_legendimage", GeoExt.LegendImage);
Ext.namespace("GeoExt");
GeoExt.LayerOpacitySlider = Ext.extend(Ext.slider.SingleSlider, {
    layer: null,
    complementaryLayer: null,
    delay: 5,
    changeVisibilityDelay: 5,
    aggressive: false,
    changeVisibility: false,
    value: null,
    inverse: false,
    constructor: function (a) {
        if (a.layer) {
            this.layer = this.getLayer(a.layer);
            this.bind();
            this.complementaryLayer = this.getLayer(a.complementaryLayer);
            if (a.inverse !== undefined) {
                this.inverse = a.inverse
            }
            a.value = (a.value !== undefined) ? a.value : this.getOpacityValue(this.layer);
            delete a.layer;
            delete a.complementaryLayer
        }
        GeoExt.LayerOpacitySlider.superclass.constructor.call(this, a)
    },
    bind: function () {
        if (this.layer && this.layer.map) {
            this.layer.map.events.on({changelayer: this.update, scope: this})
        }
    },
    unbind: function () {
        if (this.layer && this.layer.map && this.layer.map.events) {
            this.layer.map.events.un({changelayer: this.update, scope: this})
        }
    },
    update: function (a) {
        if (a.property === "opacity" && a.layer == this.layer && !this._settingOpacity) {
            this.setValue(this.getOpacityValue(this.layer))
        }
    },
    setLayer: function (a) {
        this.unbind();
        this.layer = this.getLayer(a);
        this.setValue(this.getOpacityValue(a));
        this.bind()
    },
    getOpacityValue: function (a) {
        var b;
        if (a && a.opacity !== null) {
            b = parseInt(a.opacity * (this.maxValue - this.minValue))
        } else {
            b = this.maxValue
        }
        if (this.inverse === true) {
            b = (this.maxValue - this.minValue) - b
        }
        return b
    },
    getLayer: function (a) {
        if (a instanceof OpenLayers.Layer) {
            return a
        } else {
            if (a instanceof GeoExt.data.LayerRecord) {
                return a.getLayer()
            }
        }
    },
    initComponent: function () {
        GeoExt.LayerOpacitySlider.superclass.initComponent.call(this);
        if (this.changeVisibility && this.layer && (this.layer.opacity == 0 || (this.inverse === false && this.value == this.minValue) || (this.inverse === true && this.value == this.maxValue))) {
            this.layer.setVisibility(false)
        }
        if (this.complementaryLayer && ((this.layer && this.layer.opacity == 1) || (this.inverse === false && this.value == this.maxValue) || (this.inverse === true && this.value == this.minValue))) {
            this.complementaryLayer.setVisibility(false)
        }
        if (this.aggressive === true) {
            this.on("change", this.changeLayerOpacity, this, {buffer: this.delay})
        } else {
            this.on("changecomplete", this.changeLayerOpacity, this)
        }
        if (this.changeVisibility === true) {
            this.on("change", this.changeLayerVisibility, this, {buffer: this.changeVisibilityDelay})
        }
        if (this.complementaryLayer) {
            this.on("change", this.changeComplementaryLayerVisibility, this, {buffer: this.changeVisibilityDelay})
        }
        this.on("beforedestroy", this.unbind, this)
    },
    changeLayerOpacity: function (a, b) {
        if (this.layer) {
            b = b / (this.maxValue - this.minValue);
            if (this.inverse === true) {
                b = 1 - b
            }
            this._settingOpacity = true;
            this.layer.setOpacity(b);
            delete this._settingOpacity
        }
    },
    changeLayerVisibility: function (b, c) {
        var a = this.layer.getVisibility();
        if ((this.inverse === false && c == this.minValue) || (this.inverse === true && c == this.maxValue) && a === true) {
            this.layer.setVisibility(false)
        } else {
            if ((this.inverse === false && c > this.minValue) || (this.inverse === true && c < this.maxValue) && a == false) {
                this.layer.setVisibility(true)
            }
        }
    },
    changeComplementaryLayerVisibility: function (b, c) {
        var a = this.complementaryLayer.getVisibility();
        if ((this.inverse === false && c == this.maxValue) || (this.inverse === true && c == this.minValue) && a === true) {
            this.complementaryLayer.setVisibility(false)
        } else {
            if ((this.inverse === false && c < this.maxValue) || (this.inverse === true && c > this.minValue) && a == false) {
                this.complementaryLayer.setVisibility(true)
            }
        }
    },
    addToMapPanel: function (a) {
        this.on({
            render: function () {
                var b = this.getEl();
                b.setStyle({position: "absolute", zIndex: a.map.Z_INDEX_BASE.Control});
                b.on({mousedown: this.stopMouseEvents, click: this.stopMouseEvents})
            }, scope: this
        })
    },
    removeFromMapPanel: function (a) {
        var b = this.getEl();
        b.un({mousedown: this.stopMouseEvents, click: this.stopMouseEvents, scope: this});
        this.unbind()
    },
    stopMouseEvents: function (a) {
        a.stopEvent()
    }
});
Ext.reg("gx_opacityslider", GeoExt.LayerOpacitySlider);
Ext.namespace("GeoExt.data");
GeoExt.data.AttributeReader = function (a, b) {
    a = a || {};
    if (!a.format) {
        a.format = new OpenLayers.Format.WFSDescribeFeatureType()
    }
    GeoExt.data.AttributeReader.superclass.constructor.call(this, a, b || a.fields);
    if (a.feature) {
        this.recordType.prototype.fields.add(new Ext.data.Field("value"))
    }
};
Ext.extend(GeoExt.data.AttributeReader, Ext.data.DataReader, {
    read: function (a) {
        var b = a.responseXML;
        if (!b || !b.documentElement) {
            b = a.responseText
        }
        return this.readRecords(b)
    }, readRecords: function (h) {
        var e;
        if (h instanceof Array) {
            e = h
        } else {
            e = this.meta.format.read(h).featureTypes[0].properties
        }
        var s = this.meta.feature;
        var c = this.recordType;
        var l = c.prototype.fields;
        var f = l.length;
        var n, r, a, k, o, q, p, b = [];
        for (var g = 0, m = e.length; g < m; ++g) {
            o = false;
            n = e[g];
            r = {};
            for (var d = 0; d < f; ++d) {
                p = l.items[d];
                a = p.name;
                q = p.convert(n[a]);
                if (this.ignoreAttribute(a, q)) {
                    o = true;
                    break
                }
                r[a] = q
            }
            if (s) {
                q = s.attributes[r.name];
                if (q !== undefined) {
                    if (this.ignoreAttribute("value", q)) {
                        o = true
                    } else {
                        r.value = q
                    }
                }
            }
            if (!o) {
                b[b.length] = new c(r)
            }
        }
        return {success: true, records: b, totalRecords: b.length}
    }, ignoreAttribute: function (a, c) {
        var d = false;
        if (this.meta.ignore && this.meta.ignore[a]) {
            var b = this.meta.ignore[a];
            if (typeof b == "string") {
                d = (b === c)
            } else {
                if (b instanceof Array) {
                    d = (b.indexOf(c) > -1)
                } else {
                    if (b instanceof RegExp) {
                        d = (b.test(c))
                    }
                }
            }
        }
        return d
    }
});
Ext.namespace("GeoExt.grid");
GeoExt.grid.SymbolizerColumn = Ext.extend(Ext.grid.Column, {
    renderer: function (a, b) {
        if (a != null) {
            var c = Ext.id();
            window.setTimeout(function () {
                var d = Ext.get(c);
                if (d) {
                    new GeoExt.FeatureRenderer({symbolizers: a instanceof Array ? a : [a], renderTo: d})
                }
            }, 0);
            b.css = "gx-grid-symbolizercol";
            return '<div id="' + c + '"></div>'
        }
    }
});
Ext.grid.Column.types.gx_symbolizercolumn = GeoExt.grid.SymbolizerColumn;
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.TreeNodeComponent = Ext.extend(Ext.util.Observable, {
    constructor: function (a) {
        Ext.apply(this.initialConfig, Ext.apply({}, a));
        Ext.apply(this, a);
        GeoExt.plugins.TreeNodeComponent.superclass.constructor.apply(this, arguments)
    }, init: function (a) {
        a.on({rendernode: this.onRenderNode, beforedestroy: this.onBeforeDestroy, scope: this})
    }, onRenderNode: function (d) {
        var e = d.rendered;
        var a = d.attributes;
        var c = a.component || this.component;
        if (!e && c) {
            var b = Ext.DomHelper.append(d.ui.elNode, [{tag: "div"}]);
            if (typeof c == "function") {
                c = c(d, b)
            } else {
                if (typeof c == "object" && typeof c.fn == "function") {
                    c = c.fn.apply(c.scope, [d, b])
                }
            }
            if (typeof c == "object" && typeof c.xtype == "string") {
                c = Ext.ComponentMgr.create(c)
            }
            if (c instanceof Ext.Component) {
                c.render(b);
                d.component = c
            }
        }
    }, onBeforeDestroy: function (a) {
        a.un("rendernode", this.onRenderNode, this);
        a.un("beforedestroy", this.onBeforeDestroy, this)
    }
});
Ext.preg("gx_treenodecomponent", GeoExt.plugins.TreeNodeComponent);
Ext.namespace("GeoExt");
GeoExt.ZoomSliderTip = Ext.extend(GeoExt.SliderTip, {
    template: "<div>Zoom Level: {zoom}</div><div>Resolution: {resolution}</div><div>Scale: 1 : {scale}</div>",
    compiledTemplate: null,
    init: function (a) {
        this.compiledTemplate = new Ext.Template(this.template);
        GeoExt.ZoomSliderTip.superclass.init.call(this, a)
    },
    getText: function (a) {
        var b = {zoom: a.value, resolution: this.slider.getResolution(), scale: Math.round(this.slider.getScale())};
        return this.compiledTemplate.apply(b)
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.PrintProvider = Ext.extend(Ext.util.Observable, {
    url: null,
    capabilities: null,
    method: "POST",
    encoding: document.charset || document.characterSet || "UTF-8",
    timeout: 30000,
    customParams: null,
    scales: null,
    dpis: null,
    layouts: null,
    dpi: null,
    layout: null,
    constructor: function (a) {
        this.initialConfig = a;
        Ext.apply(this, a);
        if (!this.customParams) {
            this.customParams = {}
        }
        this.addEvents("loadcapabilities", "layoutchange", "dpichange", "beforeprint", "print", "printexception", "beforeencodelayer", "encodelayer", "beforedownload");
        GeoExt.data.PrintProvider.superclass.constructor.apply(this, arguments);
        this.scales = new Ext.data.JsonStore({
            root: "scales",
            sortInfo: {field: "value", direction: "DESC"},
            fields: ["name", {name: "value", type: "float"}]
        });
        this.dpis = new Ext.data.JsonStore({root: "dpis", fields: ["name", {name: "value", type: "float"}]});
        this.layouts = new Ext.data.JsonStore({
            root: "layouts",
            fields: ["name", {name: "size", mapping: "map"}, {name: "rotation", type: "boolean"}]
        });
        if (a.capabilities) {
            this.loadStores()
        } else {
            if (this.url.split("/").pop()) {
                this.url += "/"
            }
            this.initialConfig.autoLoad && this.loadCapabilities()
        }
    },
    setLayout: function (a) {
        this.layout = a;
        this.fireEvent("layoutchange", this, a)
    },
    setDpi: function (a) {
        this.dpi = a;
        this.fireEvent("dpichange", this, a)
    },
    print: function (c, d, m) {
        if (c instanceof GeoExt.MapPanel) {
            c = c.map
        }
        d = d instanceof Array ? d : [d];
        m = m || {};
        if (this.fireEvent("beforeprint", this, c, d, m) === false) {
            return
        }
        var h = Ext.apply({
            units: c.getUnits(),
            srs: c.baseLayer.projection.getCode(),
            layout: this.layout.get("name"),
            dpi: this.dpi.get("value")
        }, this.customParams);
        var l = d[0].feature.layer;
        var k = [];
        var f = c.layers.concat();
        f.remove(c.baseLayer);
        f.unshift(c.baseLayer);
        Ext.each(f, function (o) {
            if (o !== l && o.getVisibility() === true) {
                var n = this.encodeLayer(o);
                n && k.push(n)
            }
        }, this);
        h.layers = k;
        var g = [];
        Ext.each(d, function (n) {
            g.push(Ext.apply({
                center: [n.center.lon, n.center.lat],
                scale: n.scale.get("value"),
                rotation: n.rotation
            }, n.customParams))
        }, this);
        h.pages = g;
        if (m.overview) {
            var j = [];
            Ext.each(m.overview.layers, function (o) {
                var n = this.encodeLayer(o);
                n && j.push(n)
            }, this);
            h.overviewLayers = j
        }
        if (m.legend) {
            var i = m.legend;
            var e = i.rendered;
            if (!e) {
                i = i.cloneConfig({renderTo: document.body, hidden: true})
            }
            var b = [];
            i.items && i.items.each(function (n) {
                if (!n.hidden) {
                    var o = this.encoders.legends[n.getXType()];
                    b = b.concat(o.call(this, n, h.pages[0].scale))
                }
            }, this);
            if (!e) {
                i.destroy()
            }
            h.legends = b
        }
        if (this.method === "GET") {
            var a = Ext.urlAppend(this.capabilities.printURL, "spec=" + encodeURIComponent(Ext.encode(h)));
            this.download(a)
        } else {
            Ext.Ajax.request({
                url: this.capabilities.createURL,
                timeout: this.timeout,
                jsonData: h,
                headers: {"Content-Type": "application/json; charset=" + this.encoding},
                success: function (n) {
                    var o = Ext.decode(n.responseText).getURL;
                    this.download(o)
                },
                failure: function (n) {
                    this.fireEvent("printexception", this, n)
                },
                params: this.initialConfig.baseParams,
                scope: this
            })
        }
    },
    download: function (a) {
        if (this.fireEvent("beforedownload", this, a) !== false) {
            if (Ext.isOpera) {
                window.open(a)
            } else {
                window.location.href = a
            }
        }
        this.fireEvent("print", this, a)
    },
    loadCapabilities: function () {
        if (!this.url) {
            return
        }
        var a = this.url + "info.json";
        Ext.Ajax.request({
            url: a, method: "GET", disableCaching: false, success: function (b) {
                this.capabilities = Ext.decode(b.responseText);
                this.loadStores()
            }, params: this.initialConfig.baseParams, scope: this
        })
    },
    loadStores: function () {
        this.scales.loadData(this.capabilities);
        this.dpis.loadData(this.capabilities);
        this.layouts.loadData(this.capabilities);
        this.setLayout(this.layouts.getAt(0));
        this.setDpi(this.dpis.getAt(0));
        this.fireEvent("loadcapabilities", this, this.capabilities)
    },
    encodeLayer: function (a) {
        var b;
        for (var d in this.encoders.layers) {
            if (OpenLayers.Layer[d] && a instanceof OpenLayers.Layer[d]) {
                if (this.fireEvent("beforeencodelayer", this, a) === false) {
                    return
                }
                b = this.encoders.layers[d].call(this, a);
                this.fireEvent("encodelayer", this, a, b);
                break
            }
        }
        return (b && b.type) ? b : null
    },
    getAbsoluteUrl: function (c) {
        var b;
        if (Ext.isIE6 || Ext.isIE7 || Ext.isIE8) {
            b = document.createElement("<a href='" + c + "'/>");
            b.style.display = "none";
            document.body.appendChild(b);
            b.href = b.href;
            document.body.removeChild(b)
        } else {
            b = document.createElement("a");
            b.href = c
        }
        return b.href
    },
    encoders: {
        layers: {
            Layer: function (b) {
                var a = {};
                if (b.options && b.options.maxScale) {
                    a.minScaleDenominator = b.options.maxScale
                }
                if (b.options && b.options.minScale) {
                    a.maxScaleDenominator = b.options.minScale
                }
                return a
            }, WMS: function (b) {
                var a = this.encoders.layers.HTTPRequest.call(this, b);
                Ext.apply(a, {
                    type: "WMS",
                    layers: [b.params.LAYERS].join(",").split(","),
                    format: b.params.FORMAT,
                    styles: [b.params.STYLES].join(",").split(",")
                });
                var d;
                for (var c in b.params) {
                    d = c.toLowerCase();
                    if (!b.DEFAULT_PARAMS[d] && "layers,styles,width,height,srs".indexOf(d) == -1) {
                        if (!a.customParams) {
                            a.customParams = {}
                        }
                        a.customParams[c] = b.params[c]
                    }
                }
                return a
            }, OSM: function (b) {
                var a = this.encoders.layers.TileCache.call(this, b);
                return Ext.apply(a, {
                    type: "OSM",
                    baseURL: a.baseURL.substr(0, a.baseURL.indexOf("$")),
                    extension: "png"
                })
            }, TMS: function (b) {
                var a = this.encoders.layers.TileCache.call(this, b);
                return Ext.apply(a, {type: "TMS", format: b.type})
            }, TileCache: function (b) {
                var a = this.encoders.layers.HTTPRequest.call(this, b);
                return Ext.apply(a, {
                    type: "TileCache",
                    layer: b.layername,
                    maxExtent: b.maxExtent.toArray(),
                    tileSize: [b.tileSize.w, b.tileSize.h],
                    extension: b.extension,
                    resolutions: b.serverResolutions || b.resolutions
                })
            }, WMTS: function (b) {
                var a = this.encoders.layers.HTTPRequest.call(this, b);
                return Ext.apply(a, {
                    type: "WMTS",
                    layer: b.layer,
                    version: b.version,
                    requestEncoding: b.requestEncoding,
                    tileOrigin: [b.tileOrigin.lon, b.tileOrigin.lat],
                    tileSize: [b.tileSize.w, b.tileSize.h],
                    style: b.style,
                    formatSuffix: b.formatSuffix,
                    dimensions: b.dimensions,
                    params: b.params,
                    maxExtent: (b.tileFullExtent != null) ? b.tileFullExtent.toArray() : b.maxExtent.toArray(),
                    matrixSet: b.matrixSet,
                    zoomOffset: b.zoomOffset,
                    resolutions: b.serverResolutions || b.resolutions
                })
            }, KaMapCache: function (b) {
                var a = this.encoders.layers.KaMap.call(this, b);
                return Ext.apply(a, {
                    type: "KaMapCache",
                    group: b.params.g,
                    metaTileWidth: b.params.metaTileSize["w"],
                    metaTileHeight: b.params.metaTileSize["h"]
                })
            }, KaMap: function (b) {
                var a = this.encoders.layers.HTTPRequest.call(this, b);
                return Ext.apply(a, {
                    type: "KaMap",
                    map: b.params.map,
                    extension: b.params.i,
                    group: b.params.g || "",
                    maxExtent: b.maxExtent.toArray(),
                    tileSize: [b.tileSize.w, b.tileSize.h],
                    resolutions: b.serverResolutions || b.resolutions
                })
            }, HTTPRequest: function (b) {
                var a = this.encoders.layers.Layer.call(this, b);
                return Ext.apply(a, {
                    baseURL: this.getAbsoluteUrl(b.url instanceof Array ? b.url[0] : b.url),
                    opacity: (b.opacity != null) ? b.opacity : 1,
                    singleTile: b.singleTile
                })
            }, Image: function (b) {
                var a = this.encoders.layers.Layer.call(this, b);
                return Ext.apply(a, {
                    type: "Image",
                    baseURL: this.getAbsoluteUrl(b.getURL(b.extent)),
                    opacity: (b.opacity != null) ? b.opacity : 1,
                    extent: b.extent.toArray(),
                    pixelSize: [b.size.w, b.size.h],
                    name: b.name
                })
            }, Vector: function (n) {
                if (!n.features.length) {
                    return
                }
                var m = [];
                var p = {};
                var c = n.features;
                var q = new OpenLayers.Format.GeoJSON();
                var d = new OpenLayers.Format.JSON();
                var b = 1;
                var k = {};
                var r, a, j, l, e;
                for (var h = 0, o = c.length; h < o; ++h) {
                    r = c[h];
                    a = r.style || n.style || n.styleMap.createSymbolizer(r, r.renderIntent);
                    j = d.write(a);
                    l = k[j];
                    if (l) {
                        e = l
                    } else {
                        k[j] = e = b++;
                        if (a.externalGraphic) {
                            p[e] = Ext.applyIf({externalGraphic: this.getAbsoluteUrl(a.externalGraphic)}, a)
                        } else {
                            p[e] = a
                        }
                    }
                    var f = q.extract.feature.call(q, r);
                    f.properties = OpenLayers.Util.extend({_gx_style: e}, f.properties);
                    m.push(f)
                }
                var g = this.encoders.layers.Layer.call(this, n);
                return Ext.apply(g, {
                    type: "Vector",
                    styles: p,
                    styleProperty: "_gx_style",
                    geoJson: {type: "FeatureCollection", features: m},
                    name: n.name,
                    opacity: (n.opacity != null) ? n.opacity : 1
                })
            }, Markers: function (g) {
                var b = [];
                for (var e = 0, h = g.markers.length; e < h; e++) {
                    var f = g.markers[e];
                    var j = new OpenLayers.Geometry.Point(f.lonlat.lon, f.lonlat.lat);
                    var a = {
                        externalGraphic: f.icon.url,
                        graphicWidth: f.icon.size.w,
                        graphicHeight: f.icon.size.h,
                        graphicXOffset: f.icon.offset.x,
                        graphicYOffset: f.icon.offset.y
                    };
                    var k = new OpenLayers.Feature.Vector(j, {}, a);
                    b.push(k)
                }
                var d = new OpenLayers.Layer.Vector(g.name);
                d.addFeatures(b);
                var c = this.encoders.layers.Vector.call(this, d);
                d.destroy();
                return c
            }
        }, legends: {
            gx_wmslegend: function (h, b) {
                var d = this.encoders.legends.base.call(this, h);
                var j = [];
                for (var e = 1, f = h.items.getCount(); e < f; ++e) {
                    var a = h.items.get(e).url;
                    if (h.useScaleParameter === true && a.toLowerCase().indexOf("request=getlegendgraphic") != -1) {
                        var g = a.split("?");
                        var c = Ext.urlDecode(g[1]);
                        c.SCALE = b;
                        a = g[0] + "?" + Ext.urlEncode(c)
                    }
                    j.push(this.getAbsoluteUrl(a))
                }
                d[0].classes[0] = {name: "", icons: j};
                return d
            }, gx_urllegend: function (b) {
                var a = this.encoders.legends.base.call(this, b);
                a[0].classes.push({name: "", icon: this.getAbsoluteUrl(b.items.get(1).url)});
                return a
            }, base: function (a) {
                return [{name: a.getLabel(), classes: []}]
            }
        }
    }
});
Ext.namespace("GeoExt");
GeoExt.MapPanel = Ext.extend(Ext.Panel, {
    map: null,
    layers: null,
    center: null,
    zoom: null,
    extent: null,
    prettyStateKeys: false,
    stateEvents: ["aftermapmove", "afterlayervisibilitychange", "afterlayeropacitychange", "afterlayerorderchange", "afterlayernamechange", "afterlayeradd", "afterlayerremove"],
    initComponent: function () {
        if (!(this.map instanceof OpenLayers.Map)) {
            this.map = new OpenLayers.Map(Ext.applyIf(this.map || {}, {allOverlays: true}))
        }
        var a = this.layers;
        if (!a || a instanceof Array) {
            this.layers = new GeoExt.data.LayerStore({layers: a, map: this.map.layers.length > 0 ? this.map : null})
        }
        if (typeof this.center == "string") {
            this.center = OpenLayers.LonLat.fromString(this.center)
        } else {
            if (this.center instanceof Array) {
                this.center = new OpenLayers.LonLat(this.center[0], this.center[1])
            }
        }
        if (typeof this.extent == "string") {
            this.extent = OpenLayers.Bounds.fromString(this.extent)
        } else {
            if (this.extent instanceof Array) {
                this.extent = OpenLayers.Bounds.fromArray(this.extent)
            }
        }
        GeoExt.MapPanel.superclass.initComponent.call(this);
        this.addEvents("aftermapmove", "afterlayervisibilitychange", "afterlayeropacitychange", "afterlayerorderchange", "afterlayernamechange", "afterlayeradd", "afterlayerremove");
        this.map.events.on({
            moveend: this.onMoveend,
            changelayer: this.onChangelayer,
            addlayer: this.onAddlayer,
            removelayer: this.onRemovelayer,
            scope: this
        })
    },
    onMoveend: function () {
        this.fireEvent("aftermapmove")
    },
    onChangelayer: function (a) {
        if (a.property) {
            if (a.property === "visibility") {
                this.fireEvent("afterlayervisibilitychange")
            } else {
                if (a.property === "order") {
                    this.fireEvent("afterlayerorderchange")
                } else {
                    if (a.property === "name") {
                        this.fireEvent("afterlayernamechange")
                    } else {
                        if (a.property === "opacity") {
                            this.fireEvent("afterlayeropacitychange")
                        }
                    }
                }
            }
        }
    },
    onAddlayer: function () {
        this.fireEvent("afterlayeradd")
    },
    onRemovelayer: function () {
        this.fireEvent("afterlayerremove")
    },
    applyState: function (g) {
        this.center = new OpenLayers.LonLat(g.x, g.y);
        this.zoom = g.zoom;
        var f, c, e, b, a, d;
        var h = this.map.layers;
        for (f = 0, c = h.length; f < c; f++) {
            e = h[f];
            b = this.prettyStateKeys ? e.name : e.id;
            a = g["visibility_" + b];
            if (a !== undefined) {
                a = (/^true$/i).test(a);
                if (e.isBaseLayer) {
                    if (a) {
                        this.map.setBaseLayer(e)
                    }
                } else {
                    e.setVisibility(a)
                }
            }
            d = g["opacity_" + b];
            if (d !== undefined) {
                e.setOpacity(d)
            }
        }
    },
    getState: function () {
        var f;
        if (!this.map) {
            return
        }
        var a = this.map.getCenter();
        f = a ? {x: a.lon, y: a.lat, zoom: this.map.getZoom()} : {};
        var e, c, d, b, g = this.map.layers;
        for (e = 0, c = g.length; e < c; e++) {
            d = g[e];
            b = this.prettyStateKeys ? d.name : d.id;
            f["visibility_" + b] = d.getVisibility();
            f["opacity_" + b] = d.opacity == null ? 1 : d.opacity
        }
        return f
    },
    updateMapSize: function () {
        if (this.map) {
            this.map.updateSize()
        }
    },
    renderMap: function () {
        var a = this.map;
        a.render(this.body.dom);
        this.layers.bind(a);
        if (a.layers.length > 0) {
            this.setInitialExtent()
        } else {
            this.layers.on("add", this.setInitialExtent, this, {single: true})
        }
    },
    setInitialExtent: function () {
        var a = this.map;
        if (this.center || this.zoom != null) {
            a.setCenter(this.center, this.zoom)
        } else {
            if (this.extent) {
                a.zoomToExtent(this.extent)
            } else {
                a.zoomToMaxExtent()
            }
        }
    },
    afterRender: function () {
        GeoExt.MapPanel.superclass.afterRender.apply(this, arguments);
        if (!this.ownerCt) {
            this.renderMap()
        } else {
            this.ownerCt.on("move", this.updateMapSize, this);
            this.ownerCt.on({afterlayout: this.afterLayout, scope: this})
        }
    },
    afterLayout: function () {
        var b = this.getInnerWidth() - this.body.getBorderWidth("lr");
        var a = this.getInnerHeight() - this.body.getBorderWidth("tb");
        if (b > 0 && a > 0) {
            this.ownerCt.un("afterlayout", this.afterLayout, this);
            this.renderMap()
        }
    },
    onResize: function () {
        GeoExt.MapPanel.superclass.onResize.apply(this, arguments);
        this.updateMapSize()
    },
    onBeforeAdd: function (a) {
        if (typeof a.addToMapPanel === "function") {
            a.addToMapPanel(this)
        }
        GeoExt.MapPanel.superclass.onBeforeAdd.apply(this, arguments)
    },
    remove: function (b, a) {
        if (typeof b.removeFromMapPanel === "function") {
            b.removeFromMapPanel(this)
        }
        GeoExt.MapPanel.superclass.remove.apply(this, arguments)
    },
    beforeDestroy: function () {
        if (this.ownerCt) {
            this.ownerCt.un("move", this.updateMapSize, this)
        }
        if (this.map && this.map.events) {
            this.map.events.un({
                moveend: this.onMoveend,
                changelayer: this.onChangelayer,
                addlayer: this.onAddlayer,
                removelayer: this.onRemovelayer,
                scope: this
            })
        }
        if (!this.initialConfig.map || !(this.initialConfig.map instanceof OpenLayers.Map)) {
            if (this.map && this.map.destroy) {
                this.map.destroy()
            }
        }
        delete this.map;
        GeoExt.MapPanel.superclass.beforeDestroy.apply(this, arguments)
    }
});
GeoExt.MapPanel.guess = function () {
    return Ext.ComponentMgr.all.find(function (a) {
        return a instanceof GeoExt.MapPanel
    })
};
Ext.reg("gx_mappanel", GeoExt.MapPanel);
Ext.namespace("GeoExt");
GeoExt.PrintMapPanel = Ext.extend(GeoExt.MapPanel, {
    sourceMap: null,
    printProvider: null,
    printPage: null,
    previewScales: null,
    center: null,
    zoom: null,
    extent: null,
    currentZoom: null,
    initComponent: function () {
        if (this.sourceMap instanceof GeoExt.MapPanel) {
            this.sourceMap = this.sourceMap.map
        }
        if (!this.map) {
            this.map = {}
        }
        Ext.applyIf(this.map, {
            projection: this.sourceMap.getProjection(),
            maxExtent: this.sourceMap.getMaxExtent(),
            maxResolution: this.sourceMap.getMaxResolution(),
            units: this.sourceMap.getUnits()
        });
        if (!(this.printProvider instanceof GeoExt.data.PrintProvider)) {
            this.printProvider = new GeoExt.data.PrintProvider(this.printProvider)
        }
        this.printPage = new GeoExt.data.PrintPage({printProvider: this.printProvider});
        this.previewScales = new Ext.data.Store();
        this.previewScales.add(this.printProvider.scales.getRange());
        this.layers = [];
        var a;
        Ext.each(this.sourceMap.layers, function (b) {
            b.getVisibility() === true && this.layers.push(b.clone())
        }, this);
        this.extent = this.sourceMap.getExtent();
        GeoExt.PrintMapPanel.superclass.initComponent.call(this)
    },
    bind: function () {
        this.printPage.on("change", this.fitZoom, this);
        this.printProvider.on("layoutchange", this.syncSize, this);
        this.map.events.register("moveend", this, this.updatePage);
        this.printPage.fit(this.sourceMap);
        if (this.initialConfig.limitScales === true) {
            this.on("resize", this.calculatePreviewScales, this);
            this.calculatePreviewScales()
        }
    },
    afterRender: function () {
        GeoExt.PrintMapPanel.superclass.afterRender.apply(this, arguments);
        this.syncSize();
        if (!this.ownerCt) {
            this.bind()
        } else {
            this.ownerCt.on({afterlayout: {fn: this.bind, scope: this, single: true}})
        }
    },
    adjustSize: function (f, b) {
        var g = this.printProvider.layout.get("size");
        var e = g.width / g.height;
        var d = this.ownerCt;
        var c = (d && d.autoWidth) ? 0 : (f || this.initialConfig.width);
        var a = (d && d.autoHeight) ? 0 : (b || this.initialConfig.height);
        if (c) {
            b = c / e;
            if (a && b > a) {
                b = a;
                f = b * e
            } else {
                f = c
            }
        } else {
            if (a) {
                f = a * e;
                b = a
            }
        }
        return {width: f, height: b}
    },
    fitZoom: function () {
        if (!this._updating && this.printPage.scale) {
            this._updating = true;
            var a = this.printPage.getPrintExtent(this.map);
            this.currentZoom = this.map.getZoomForExtent(a);
            this.map.zoomToExtent(a);
            delete this._updating
        }
    },
    updatePage: function () {
        if (!this._updating) {
            var a = this.map.getZoom();
            this._updating = true;
            if (a === this.currentZoom) {
                this.printPage.setCenter(this.map.getCenter())
            } else {
                this.printPage.fit(this.map)
            }
            delete this._updating;
            this.currentZoom = a
        }
    },
    calculatePreviewScales: function () {
        this.previewScales.removeAll();
        this.printPage.suspendEvents();
        var e = this.printPage.scale;
        var h = this.map.getSize();
        var g = {};
        var a = [];
        this.printProvider.scales.each(function (n) {
            this.printPage.setScale(n);
            var k = this.printPage.getPrintExtent(this.map);
            var l = this.map.getZoomForExtent(k);
            var i = Math.max(k.getWidth() / h.w, k.getHeight() / h.h);
            var j = this.map.getResolutionForZoom(l);
            var m = Math.abs(i - j);
            if (!(l in g) || g[l].diff > m) {
                g[l] = {rec: n, diff: m};
                a.indexOf(l) == -1 && a.push(l)
            }
        }, this);
        for (var b = 0, c = a.length; b < c; ++b) {
            this.previewScales.add(g[a[b]].rec)
        }
        e && this.printPage.setScale(e);
        this.printPage.resumeEvents();
        if (e && this.previewScales.getCount() > 0) {
            var f = this.previewScales.getAt(0);
            var d = this.previewScales.getAt(this.previewScales.getCount() - 1);
            if (e.get("value") < d.get("value")) {
                this.printPage.setScale(d)
            } else {
                if (e.get("value") > f.get("value")) {
                    this.printPage.setScale(f)
                }
            }
        }
        this.fitZoom()
    },
    print: function (a) {
        this.printProvider.print(this.map, [this.printPage], a)
    },
    beforeDestroy: function () {
        this.map.events.unregister("moveend", this, this.updatePage);
        this.printPage.un("change", this.fitZoom, this);
        this.printProvider.un("layoutchange", this.syncSize, this);
        GeoExt.PrintMapPanel.superclass.beforeDestroy.apply(this, arguments)
    }
});
Ext.reg("gx_printmappanel", GeoExt.PrintMapPanel);
Ext.namespace("GeoExt.data");
GeoExt.data.ScaleStore = Ext.extend(Ext.data.Store, {
    map: null, constructor: function (a) {
        var b = (a.map instanceof GeoExt.MapPanel ? a.map.map : a.map);
        delete a.map;
        a = Ext.applyIf(a, {reader: new Ext.data.JsonReader({}, ["level", "resolution", "scale"])});
        GeoExt.data.ScaleStore.superclass.constructor.call(this, a);
        if (b) {
            this.bind(b)
        }
    }, bind: function (b, a) {
        this.map = (b instanceof GeoExt.MapPanel ? b.map : b);
        this.map.events.register("changebaselayer", this, this.populateFromMap);
        if (this.map.baseLayer) {
            this.populateFromMap()
        } else {
            this.map.events.register("addlayer", this, this.populateOnAdd)
        }
    }, unbind: function () {
        if (this.map) {
            this.map.events.unregister("addlayer", this, this.populateOnAdd);
            this.map.events.unregister("changebaselayer", this, this.populateFromMap);
            delete this.map
        }
    }, populateOnAdd: function (a) {
        if (a.layer.isBaseLayer) {
            this.populateFromMap();
            this.map.events.unregister("addlayer", this, this.populateOnAdd)
        }
    }, populateFromMap: function () {
        var c = [];
        var a = this.map.baseLayer.resolutions;
        var b = this.map.baseLayer.units;
        for (var e = a.length - 1; e >= 0; e--) {
            var d = a[e];
            c.push({level: e, resolution: d, scale: OpenLayers.Util.getScaleFromResolution(d, b)})
        }
        this.loadData(c)
    }, destroy: function () {
        this.unbind();
        GeoExt.data.ScaleStore.superclass.destroy.apply(this, arguments)
    }
});
Ext.namespace("GeoExt.data");
GeoExt.data.FeatureStoreMixin = function () {
    return {
        layer: null, reader: null, featureFilter: null, constructor: function (b) {
            b = b || {};
            b.reader = b.reader || new GeoExt.data.FeatureReader({}, b.fields);
            var c = b.layer;
            delete b.layer;
            if (b.features) {
                b.data = b.features
            }
            delete b.features;
            var a = {initDir: b.initDir};
            delete b.initDir;
            arguments.callee.superclass.constructor.call(this, b);
            if (c) {
                this.bind(c, a)
            }
        }, bind: function (d, b) {
            if (this.layer) {
                return
            }
            this.layer = d;
            b = b || {};
            var f = b.initDir;
            if (b.initDir == undefined) {
                f = GeoExt.data.FeatureStore.LAYER_TO_STORE | GeoExt.data.FeatureStore.STORE_TO_LAYER
            }
            var e = d.features.slice(0);
            if (f & GeoExt.data.FeatureStore.STORE_TO_LAYER) {
                var a = this.getRange();
                for (var c = a.length - 1; c >= 0; c--) {
                    this.layer.addFeatures([a[c].getFeature()])
                }
            }
            if (f & GeoExt.data.FeatureStore.LAYER_TO_STORE) {
                this.loadData(e, true)
            }
            d.events.on({
                featuresadded: this.onFeaturesAdded,
                featuresremoved: this.onFeaturesRemoved,
                featuremodified: this.onFeatureModified,
                scope: this
            });
            this.on({
                load: this.onLoad,
                clear: this.onClear,
                add: this.onAdd,
                remove: this.onRemove,
                update: this.onUpdate,
                scope: this
            })
        }, unbind: function () {
            if (this.layer) {
                this.layer.events.un({
                    featuresadded: this.onFeaturesAdded,
                    featuresremoved: this.onFeaturesRemoved,
                    featuremodified: this.onFeatureModified,
                    scope: this
                });
                this.un("load", this.onLoad, this);
                this.un("clear", this.onClear, this);
                this.un("add", this.onAdd, this);
                this.un("remove", this.onRemove, this);
                this.un("update", this.onUpdate, this);
                this.layer = null
            }
        }, getRecordFromFeature: function (a) {
            return this.getByFeature(a) || null
        }, getByFeature: function (c) {
            var a;
            if (c.state !== OpenLayers.State.INSERT) {
                a = this.getById(c.id)
            } else {
                var b = this.findBy(function (d) {
                    return d.getFeature() === c
                });
                if (b > -1) {
                    a = this.getAt(b)
                }
            }
            return a
        }, onFeaturesAdded: function (b) {
            if (!this._adding) {
                var f = b.features, e = f;
                if (this.featureFilter) {
                    e = [];
                    var d, a, c;
                    for (var d = 0, a = f.length; d < a; d++) {
                        c = f[d];
                        if (this.featureFilter.evaluate(c) !== false) {
                            e.push(c)
                        }
                    }
                }
                this._adding = true;
                this.loadData(e, true);
                delete this._adding
            }
        }, onFeaturesRemoved: function (b) {
            if (!this._removing) {
                var e = b.features, d, a, c;
                for (c = e.length - 1; c >= 0; c--) {
                    d = e[c];
                    a = this.getByFeature(d);
                    if (a !== undefined) {
                        this._removing = true;
                        this.remove(a);
                        delete this._removing
                    }
                }
            }
        }, onFeatureModified: function (h) {
            if (!this._updating) {
                var j = h.feature;
                var c = this.getByFeature(j);
                if (c !== undefined) {
                    c.beginEdit();
                    var a = j.attributes;
                    if (a) {
                        var d = this.recordType.prototype.fields;
                        for (var b = 0, e = d.length; b < e; b++) {
                            var f = d.items[b];
                            var g = f.mapping || f.name;
                            if (g in a) {
                                c.set(f.name, f.convert(a[g]))
                            }
                        }
                    }
                    c.set("state", j.state);
                    c.set("fid", j.fid);
                    c.setFeature(j);
                    this._updating = true;
                    c.endEdit();
                    delete this._updating
                }
            }
        }, addFeaturesToLayer: function (b) {
            var c, a, d;
            d = new Array((a = b.length));
            for (c = 0; c < a; c++) {
                d[c] = b[c].getFeature()
            }
            if (d.length > 0) {
                this._adding = true;
                this.layer.addFeatures(d);
                delete this._adding
            }
        }, onLoad: function (b, a, c) {
            if (!c || c.add !== true) {
                this._removing = true;
                this.layer.removeFeatures(this.layer.features);
                delete this._removing;
                this.addFeaturesToLayer(a)
            }
        }, onClear: function (a) {
            this._removing = true;
            this.layer.removeFeatures(this.layer.features);
            delete this._removing
        }, onAdd: function (b, a, c) {
            if (!this._adding) {
                this.addFeaturesToLayer(a)
            }
        }, onRemove: function (b, a, c) {
            if (!this._removing) {
                var d = a.getFeature();
                if (this.layer.getFeatureById(d.id) != null) {
                    this._removing = true;
                    this.layer.removeFeatures([a.getFeature()]);
                    delete this._removing
                }
            }
        }, onUpdate: function (e, b, d) {
            if (!this._updating) {
                var g = new GeoExt.data.FeatureRecord().fields;
                var f = b.getFeature();
                if (f.state !== OpenLayers.State.INSERT) {
                    f.state = OpenLayers.State.UPDATE
                }
                if (b.fields) {
                    var a = this.layer.events.triggerEvent("beforefeaturemodified", {feature: f});
                    if (a !== false) {
                        var c = f.attributes;
                        b.fields.each(function (i) {
                            var h = i.mapping || i.name;
                            if (!g.containsKey(h)) {
                                c[h] = b.get(i.name)
                            }
                        });
                        this._updating = true;
                        this.layer.events.triggerEvent("featuremodified", {feature: f});
                        delete this._updating;
                        if (this.layer.getFeatureById(f.id) != null) {
                            this.layer.drawFeature(f)
                        }
                    }
                }
            }
        }, destroy: function () {
            this.unbind();
            GeoExt.data.FeatureStore.superclass.destroy.call(this)
        }
    }
};
GeoExt.data.FeatureStore = Ext.extend(Ext.data.Store, new GeoExt.data.FeatureStoreMixin);
GeoExt.data.FeatureStore.LAYER_TO_STORE = 1;
GeoExt.data.FeatureStore.STORE_TO_LAYER = 2;
Ext.namespace("GeoExt.data");
GeoExt.data.WFSCapabilitiesStore = function (a) {
    a = a || {};
    GeoExt.data.WFSCapabilitiesStore.superclass.constructor.call(this, Ext.apply(a, {
        proxy: a.proxy || (!a.data ? new Ext.data.HttpProxy({
            url: a.url,
            disableCaching: false,
            method: "GET"
        }) : undefined), reader: new GeoExt.data.WFSCapabilitiesReader(a, a.fields)
    }))
};
Ext.extend(GeoExt.data.WFSCapabilitiesStore, Ext.data.Store);
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.AttributeForm = function (a) {
    Ext.apply(this, a)
};
GeoExt.plugins.AttributeForm.prototype = {
    attributeStore: null, formPanel: null, init: function (a) {
        this.formPanel = a;
        if (this.attributeStore instanceof Ext.data.Store) {
            this.fillForm();
            this.bind(this.attributeStore)
        }
        a.on("destroy", this.onFormDestroy, this)
    }, bind: function (a) {
        this.unbind();
        a.on({load: this.onLoad, scope: this});
        this.attributeStore = a
    }, unbind: function () {
        if (this.attributeStore) {
            this.attributeStore.un("load", this.onLoad, this)
        }
    }, onLoad: function () {
        if (this.formPanel.items) {
            this.formPanel.removeAll()
        }
        this.fillForm()
    }, fillForm: function () {
        this.attributeStore.each(function (a) {
            // HACK. Do not render gc2 system fields
            if (a.data.name.substring(0, 4) !== "gc2_") {
                var b = GeoExt.form.recordToField(a, Ext.apply({checkboxLabelProperty: "fieldLabel"}, this.recordToFieldOptions || {}));
                if (b) {
                    this.formPanel.add(b)
                }
            }
        }, this);
        this.formPanel.doLayout()
    }, onFormDestroy: function () {
        this.unbind()
    }
};
Ext.preg("gx_attributeform", GeoExt.plugins.AttributeForm);
Ext.namespace("GeoExt", "GeoExt.data");
GeoExt.data.ProtocolProxy = function (a) {
    Ext.apply(this, a);
    GeoExt.data.ProtocolProxy.superclass.constructor.apply(this, arguments)
};
Ext.extend(GeoExt.data.ProtocolProxy, Ext.data.DataProxy, {
    protocol: null,
    abortPrevious: true,
    setParamsAsOptions: false,
    response: null,
    load: function (g, c, h, e, b) {
        if (this.fireEvent("beforeload", this, g) !== false) {
            var f = {params: g || {}, request: {callback: h, scope: e, arg: b}, reader: c};
            var a = OpenLayers.Function.bind(this.loadResponse, this, f);
            if (this.abortPrevious) {
                this.abortRequest()
            }
            var d = {params: g, callback: a, scope: this};
            Ext.applyIf(d, b);
            if (this.setParamsAsOptions === true) {
                Ext.applyIf(d, d.params);
                delete d.params
            }
            this.response = this.protocol.read(d)
        } else {
            h.call(e || this, null, b, false)
        }
    },
    abortRequest: function () {
        if (this.response) {
            this.protocol.abort(this.response);
            this.response = null
        }
    },
    loadResponse: function (c, b) {
        if (b.success()) {
            var a = c.reader.read(b);
            this.fireEvent("load", this, c, c.request.arg);
            c.request.callback.call(c.request.scope, a, c.request.arg, true)
        } else {
            this.fireEvent("loadexception", this, c, b);
            c.request.callback.call(c.request.scope, null, c.request.arg, false)
        }
    }
});
Ext.namespace("GeoExt");
GeoExt.Action = Ext.extend(Ext.Action, {
    control: null,
    activateOnEnable: false,
    deactivateOnDisable: false,
    map: null,
    uScope: null,
    uHandler: null,
    uToggleHandler: null,
    uCheckHandler: null,
    constructor: function (a) {
        this.uScope = a.scope;
        this.uHandler = a.handler;
        this.uToggleHandler = a.toggleHandler;
        this.uCheckHandler = a.checkHandler;
        a.scope = this;
        a.handler = this.pHandler;
        a.toggleHandler = this.pToggleHandler;
        a.checkHandler = this.pCheckHandler;
        var b = this.control = a.control;
        delete a.control;
        this.activateOnEnable = !!a.activateOnEnable;
        delete a.activateOnEnable;
        this.deactivateOnDisable = !!a.deactivateOnDisable;
        delete a.deactivateOnDisable;
        if (b) {
            if (a.map) {
                a.map.addControl(b);
                delete a.map
            }
            if ((a.pressed || a.checked) && b.map) {
                b.activate()
            }
            if (b.active) {
                a.pressed = true;
                a.checked = true
            }
            b.events.on({activate: this.onCtrlActivate, deactivate: this.onCtrlDeactivate, scope: this})
        }
        arguments.callee.superclass.constructor.call(this, a)
    },
    pHandler: function (a) {
        var b = this.control;
        if (b && b.type == OpenLayers.Control.TYPE_BUTTON) {
            b.trigger()
        }
        if (this.uHandler) {
            this.uHandler.apply(this.uScope, arguments)
        }
    },
    pToggleHandler: function (a, b) {
        this.changeControlState(b);
        if (this.uToggleHandler) {
            this.uToggleHandler.apply(this.uScope, arguments)
        }
    },
    pCheckHandler: function (a, b) {
        this.changeControlState(b);
        if (this.uCheckHandler) {
            this.uCheckHandler.apply(this.uScope, arguments)
        }
    },
    changeControlState: function (a) {
        if (a) {
            if (!this._activating) {
                this._activating = true;
                this.control.activate();
                this.initialConfig.pressed = true;
                this.initialConfig.checked = true;
                this._activating = false
            }
        } else {
            if (!this._deactivating) {
                this._deactivating = true;
                this.control.deactivate();
                this.initialConfig.pressed = false;
                this.initialConfig.checked = false;
                this._deactivating = false
            }
        }
    },
    onCtrlActivate: function () {
        var a = this.control;
        if (a.type == OpenLayers.Control.TYPE_BUTTON) {
            this.enable()
        } else {
            this.safeCallEach("toggle", [true]);
            this.safeCallEach("setChecked", [true])
        }
    },
    onCtrlDeactivate: function () {
        var a = this.control;
        if (a.type == OpenLayers.Control.TYPE_BUTTON) {
            this.disable()
        } else {
            this.safeCallEach("toggle", [false]);
            this.safeCallEach("setChecked", [false])
        }
    },
    safeCallEach: function (e, b) {
        var d = this.items;
        for (var c = 0, a = d.length; c < a; c++) {
            if (d[c][e]) {
                d[c].rendered ? d[c][e].apply(d[c], b) : d[c].on({
                    render: d[c][e].createDelegate(d[c], b),
                    single: true
                })
            }
        }
    },
    setDisabled: function (a) {
        if (!a && this.activateOnEnable && this.control && !this.control.active) {
            this.control.activate()
        }
        if (a && this.deactivateOnDisable && this.control && this.control.active) {
            this.control.deactivate()
        }
        return GeoExt.Action.superclass.setDisabled.apply(this, arguments)
    }
});
Ext.namespace("GeoExt.tree");
GeoExt.tree.TreeNodeUIEventMixin = function () {
    return {
        constructor: function (a) {
            a.addEvents("rendernode", "rawclicknode");
            this.superclass = arguments.callee.superclass;
            this.superclass.constructor.apply(this, arguments)
        }, render: function (a) {
            if (!this.rendered) {
                this.superclass.render.apply(this, arguments);
                this.fireEvent("rendernode", this.node)
            }
        }, onClick: function (a) {
            if (this.fireEvent("rawclicknode", this.node, a) !== false) {
                this.superclass.onClick.apply(this, arguments)
            }
        }
    }
};
Ext.namespace("GeoExt.grid");
GeoExt.grid.FeatureSelectionModelMixin = function () {
    return {
        autoActivateControl: true,
        layerFromStore: true,
        selectControl: null,
        bound: false,
        superclass: null,
        selectedFeatures: [],
        autoPanMapOnSelection: false,
        constructor: function (a) {
            a = a || {};
            if (a.selectControl instanceof OpenLayers.Control.SelectFeature) {
                if (!a.singleSelect) {
                    var b = a.selectControl;
                    a.singleSelect = !(b.multiple || !!b.multipleKey)
                }
            } else {
                if (a.layer instanceof OpenLayers.Layer.Vector) {
                    this.selectControl = this.createSelectControl(a.layer, a.selectControl);
                    delete a.layer;
                    delete a.selectControl
                }
            }
            if (a.autoPanMapOnSelection) {
                this.autoPanMapOnSelection = true;
                delete a.autoPanMapOnSelection
            }
            this.superclass = arguments.callee.superclass;
            this.superclass.constructor.call(this, a)
        },
        initEvents: function () {
            this.superclass.initEvents.call(this);
            if (this.layerFromStore) {
                var a = this.grid.getStore() && this.grid.getStore().layer;
                if (a && !(this.selectControl instanceof OpenLayers.Control.SelectFeature)) {
                    this.selectControl = this.createSelectControl(a, this.selectControl)
                }
            }
            if (this.selectControl) {
                this.bind(this.selectControl)
            }
        },
        createSelectControl: function (b, a) {
            a = a || {};
            var d = a.singleSelect !== undefined ? a.singleSelect : this.singleSelect;
            a = OpenLayers.Util.extend({toggle: true, multipleKey: d ? null : (Ext.isMac ? "metaKey" : "ctrlKey")}, a);
            var c = new OpenLayers.Control.SelectFeature(b, a);
            b.map.addControl(c);
            return c
        },
        bind: function (e, b) {
            if (!this.bound) {
                b = b || {};
                this.selectControl = e;
                if (e instanceof OpenLayers.Layer.Vector) {
                    this.selectControl = this.createSelectControl(e, b.controlConfig)
                }
                if (this.autoActivateControl) {
                    this.selectControl.activate()
                }
                var d = this.getLayers();
                for (var c = 0, a = d.length; c < a; c++) {
                    d[c].events.on({
                        featureselected: this.featureSelected,
                        featureunselected: this.featureUnselected,
                        scope: this
                    })
                }
                this.on("rowselect", this.rowSelected, this);
                this.on("rowdeselect", this.rowDeselected, this);
                this.bound = true
            }
            return this.selectControl
        },
        unbind: function () {
            var c = this.selectControl;
            if (this.bound) {
                var d = this.getLayers();
                for (var b = 0, a = d.length; b < a; b++) {
                    d[b].events.un({
                        featureselected: this.featureSelected,
                        featureunselected: this.featureUnselected,
                        scope: this
                    })
                }
                this.un("rowselect", this.rowSelected, this);
                this.un("rowdeselect", this.rowDeselected, this);
                if (this.autoActivateControl) {
                    c.deactivate()
                }
                this.selectControl = null;
                this.bound = false
            }
            return c
        },
        featureSelected: function (a) {
            if (!this._selecting) {
                var b = this.grid.store;
                var c = b.findBy(function (d, e) {
                    return d.getFeature() == a.feature
                });
                if (c != -1 && !this.isSelected(c)) {
                    this._selecting = true;
                    this.selectRow(c, !this.singleSelect);
                    this._selecting = false;
                    this.grid.getView().focusRow(c)
                }
            }
        },
        featureUnselected: function (a) {
            if (!this._selecting) {
                var b = this.grid.store;
                var c = b.findBy(function (d, e) {
                    return d.getFeature() == a.feature
                });
                if (c != -1 && this.isSelected(c)) {
                    this._selecting = true;
                    this.deselectRow(c);
                    this._selecting = false;
                    this.grid.getView().focusRow(c)
                }
            }
        },
        rowSelected: function (c, g, b) {
            var e = b.getFeature();
            if (!this._selecting && e) {
                var f = this.getLayers();
                for (var d = 0, a = f.length; d < a; d++) {
                    if (f[d].selectedFeatures.indexOf(e) == -1) {
                        this._selecting = true;
                        this.selectControl.select(e);
                        this._selecting = false;
                        this.selectedFeatures.push(e);
                        break
                    }
                }
                if (this.autoPanMapOnSelection) {
                    this.recenterToSelectionExtent()
                }
            }
        },
        rowDeselected: function (c, g, b) {
            var e = b.getFeature();
            if (!this._selecting && e) {
                var f = this.getLayers();
                for (var d = 0, a = f.length; d < a; d++) {
                    if (f[d].selectedFeatures.indexOf(e) != -1) {
                        this._selecting = true;
                        this.selectControl.unselect(e);
                        this._selecting = false;
                        OpenLayers.Util.removeItem(this.selectedFeatures, e);
                        break
                    }
                }
                if (this.autoPanMapOnSelection && this.selectedFeatures.length > 0) {
                    this.recenterToSelectionExtent()
                }
            }
        },
        getLayers: function () {
            return this.selectControl.layers || [this.selectControl.layer]
        },
        recenterToSelectionExtent: function () {
            var c = this.selectControl.map;
            var b = this.getSelectionExtent();
            var a = c.getZoomForExtent(b, false);
            if (a > c.getZoom()) {
                c.setCenter(b.getCenterLonLat())
            } else {
                c.zoomToExtent(b)
            }
        },
        getSelectionExtent: function () {
            var b = null;
            var d = this.selectedFeatures;
            if (d && (d.length > 0)) {
                var e = null;
                for (var c = 0, a = d.length; c < a; c++) {
                    e = d[c].geometry;
                    if (e) {
                        if (b === null) {
                            b = new OpenLayers.Bounds()
                        }
                        b.extend(e.getBounds())
                    }
                }
            }
            return b
        }
    }
};
GeoExt.grid.FeatureSelectionModel = Ext.extend(Ext.grid.RowSelectionModel, new GeoExt.grid.FeatureSelectionModelMixin);
Ext.namespace("GeoExt.data");
GeoExt.data.WMSCapabilitiesStore = function (a) {
    a = a || {};
    GeoExt.data.WMSCapabilitiesStore.superclass.constructor.call(this, Ext.apply(a, {
        proxy: a.proxy || (!a.data ? new Ext.data.HttpProxy({
            url: a.url,
            disableCaching: false,
            method: "GET"
        }) : undefined), reader: new GeoExt.data.WMSCapabilitiesReader(a, a.fields)
    }))
};
Ext.extend(GeoExt.data.WMSCapabilitiesStore, Ext.data.Store);
Ext.namespace("GeoExt");
GeoExt.LayerOpacitySliderTip = Ext.extend(GeoExt.SliderTip, {
    template: "<div>{opacity}%</div>",
    compiledTemplate: null,
    init: function (a) {
        this.compiledTemplate = new Ext.Template(this.template);
        GeoExt.LayerOpacitySliderTip.superclass.init.call(this, a)
    },
    getText: function (a) {
        var b = {opacity: a.value};
        return this.compiledTemplate.apply(b)
    }
});
GeoExt.version = "1.1";
