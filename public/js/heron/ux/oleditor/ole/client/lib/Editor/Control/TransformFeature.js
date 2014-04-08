/**
 * Class: OpenLayers.Editor.Control.DragFeature
 * 
 * Displays bounding box around clicked features along with handles to scale and rotate geometries.
 * Also allows to move features by dragging them.
 * 
 * Inherits from:
 *  - <OpenLayers.Control.TransformFeature>
 */
OpenLayers.Editor.Control.TransformFeature = OpenLayers.Class(OpenLayers.Control.TransformFeature, {
    CLASS_NAME: 'OpenLayers.Editor.Control.TransformFeature',
    
    /**
     * @type {OpenLayers.Layer.Vector}
     */
    editLayer: null,
    
    /**
     * List of strategies that have been temporarily suspended to prevent side effects
     * @type {Array.<OpenLayers.Strategy>}
     */
    strategiesOnHold: null,
    
    /**
     * @type {OpenLayers.Feature.Vector} Feature that was selected for transform previously
     */
    drawOriginalsFeature: null,
    /**
     * @type {String} Render intent of previously transformed feature
     */
    drawOriginalsRenderIntent: null,
    
    /**
     * @param {OpenLayers.Layer.Vector} editLayer
     */
    initialize: function(editLayer){
        this.strategiesOnHold = [];
        
        OpenLayers.Control.TransformFeature.prototype.initialize.call(this, editLayer, {
            renderIntent: "transform",
            rotationHandleSymbolizer: "rotate"
        });
        
        this.editLayer = editLayer;
        
        this.addStyles();
        
        this.events.on({
            'transformcomplete': function(e){
                e.feature.state = OpenLayers.State.UPDATE;
                this.editLayer.events.triggerEvent("afterfeaturemodified", {
                    feature: e.feature
                });
            },
            scope: this
        });
        
        this.title = OpenLayers.i18n('oleTransformFeature');
    },
    
    /**
     * Adds style of box around object and handles shown during transformation
     */
    addStyles: function(){
        var control = this;
        this.editLayer.styleMap.styles.transform =new OpenLayers.Style({
            display: "${getDisplay}",
            cursor: "${role}",
            pointRadius: 5,
            fillColor: "#07f",
            strokeOpacity: "${getStrokeOpacity}",
            fillOpacity: 1,
            strokeColor: "${getStrokeColor}",
            strokeWidth: "${getStrokeWidth}",
            strokeDashstyle: '${getStrokeDashstyle}'
        }, {
            context: {
                getDisplay: function(feature) {
                    if(control.feature===null || control.feature.geometry instanceof OpenLayers.Geometry.Point){
                        return "none";
                    }
                    // hide the resize handle at the south-east corner
                    return feature.attributes.role === "se-resize" ? "none" : "";
                },
                getStrokeColor: function(feature){
                    return feature.geometry instanceof OpenLayers.Geometry.Point ? '#037' : "#ff00ff";
                },
                getStrokeOpacity: function(feature){
                    return feature.geometry instanceof OpenLayers.Geometry.Point ? 0.8 : 0.5;
                },
                getStrokeWidth: function(feature){
                    return feature.geometry instanceof OpenLayers.Geometry.Point ? 2 : 1;
                },
                getStrokeDashstyle: function(feature){
                    return feature.geometry instanceof OpenLayers.Geometry.Point ? 'solid' : 'longdash';
                }
            }
        });
        this.editLayer.styleMap.styles.rotate = new OpenLayers.Style({
            display: "${getDisplay}",
            pointRadius: 10,
            fillColor: "#ddd",
            fillOpacity: 1,
            strokeColor: "black",
            // Display arrow image (rotationHandle.png) unless Browser is IE which does not reliable support data URI
            externalGraphic: OpenLayers.Util.getBrowserName()==='msie' ? undefined : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABcAAAAWCAYAAAArdgcFAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAOnAAADnUBiCgbeAAAAAd0SU1FB9wIAgsPAyGVVyoAAAQFSURBVDjLjZVfTJNnFMZ//aQ0UVMgIi11Kngx1KUGBo0XU+bmmFnmErzRkBK2RBMUyfaV4cQ4t5GNm8UMTJTMbDSj4YI5GFmcMxvyZ4yIJJUbY5GNJv7ZQtGJNBoTL+yzC+NnugJykpO8Oe85z/u85z3vOUhiPj13tsfcX71PGzesV0ZGhgA5nU5tWF+gfXvf0089Z8yF4uc0XvjtnOkrKRbwXC1+uUjnzvaYiwL/4vNPtWTJEgHKyclRTU2Nurq6FA6HFY1GdfnyZXV3d+vgwYPKyckRIMMw9MnHR7Qg+Id1HwiQzWZTdXW1rly5omg0Oq9evXpVtbW1MgxDgGpr9mtO8OC3X5s2m01paWk6derUgqD/19OnT8tutwtQ68kTVopskgDIzl6hu3dnaGxspLKykvnk4cOHRCKRJJvD4eDatWs0NDSQkeFkdjZuA54wP1QfEKDNmzc/l6Xf70951DVr1igajaq0tFSA3q+tkSQMgO/PdAFgmmYSo4mJiRTm9+/ft9Z1dXWEQiGOHz8OQCAQAOCHrh8BMEaGB8ybN2/hcrnw+XxWYDgcZufOnfT29qYckJmZSV5eHu3t7Xi9XoqLiwHYtGkTq1evZioWY7D/V9P486/JZoDCwkJsNpsF0NLSgsfjobS0NAV86dKltLa2MjMzQygUStorKSkBYHx8otmYmooBkJubazk8evSI0dFRdu3ahcPhmPNhCwoKKCoqYmhoKMm+cuVKAGKxGGlP2SYSCcshHo+TSCRwu90poKZp8uDBAwBcLhfj4+NJ+0+rzzAM0jyeJ4ynpqYsh6ysLNLT07l+/XoK+Nq1a631jRs3Ugjcvn0bALfbhVFQ8GIAYGxsjMePHwNgt9vZtm0b3d3d3Lt3b860XLx4kUgkQllZWRLrS5cuAfDSxg0BJJGfnydA7e3tVj2fP39eDodDXq9Xg4ODSbXe0dGhrKws5efnKxKJWPbOzk4BWrXKI+v7HzvaIECFhYWanJy0nNva2rRs2TLZ7XZt3bpV5eXl8nq9ArRu3ToNDAwkHerz+QToUH1ASd8/1+1WbHqa+vp6Dhw4YF11enqaYDDIyMgI8Xgct9vNjh078Pv9SZUUDAZpamoiO3sFd+78awOegZ/p7DAr/O82AzQ1NbF7924WKz09PRw+fJhEIkHou2+orNr7rLc81cbPjln9Ys+ePVYPn0/D4bCqqqqsmKNHPtKCw6L15Ak5HOkCtHz5clVUVKitrU19fX0aGxtTf3+/gsGg/H6/nE6nANntdrV89aUWNeZGR4bMN8u2L2rMbX/9NQ3/fmHOMWflfC4ZGR4wz/78S/PQH8Pc+vsfZmfjZGZm8MIqD1u2vMI7b78V2PLqGy3zxf8Hbd5G4wGXKsEAAAAASUVORK5CYII=',
            graphicWidth:23,
            graphicHeight:22
        }, {
            context: {
                getDisplay: function(feature) {
                    if(control.feature===null || control.feature.geometry instanceof OpenLayers.Geometry.Point){
                        return "none";
                    }
                    // only display the rotate handle at the south-east corner
                    return feature.attributes.role === "se-rotate" ? "" : "none";
                }
            }
        });
    },
    
    activate: function(){
        // Disable BBOX strategy since it destroys all features whilst updating data
        for(var strategyIter=0; OpenLayers.Util.isArray(this.layer.strategies) && strategyIter<this.layer.strategies.length; strategyIter++){
            if(this.layer.strategies[strategyIter] instanceof OpenLayers.Strategy.BBOX){
                this.strategiesOnHold.push(this.layer.strategies[strategyIter]);
                this.layer.strategies[strategyIter].deactivate();
            }
        }
        
        var activated = OpenLayers.Control.TransformFeature.prototype.activate.call(this);
        if(this.feature===null || this.feature.geometry instanceof OpenLayers.Geometry.Point){
            // Re-render handles to hide them when control is activated initially without a feature selected so far
            this.editLayer.drawFeature(this.box, this.renderIntent);
            var f, handleIter;
            for(handleIter=0; handleIter<this.rotationHandles.length; handleIter++){
                f = this.rotationHandles[handleIter];
                this.editLayer.drawFeature(f, this.renderIntent);
            }
            for(handleIter=0; handleIter<this.handles.length; handleIter++){
                f = this.handles[handleIter];
                this.editLayer.drawFeature(f, this.renderIntent);
            }
        }
        
        this.events.on({
            setfeature: this.highlightTransformedFeature,
            scope: this
        });
        return activated;
    },
    
    deactivate: function () {
        this._moving = true;
        this.box.geometry.rotate(-this.rotation, this.center);
        delete this._moving;
        
        // Re-enable strategies that have been disabled by this control
        for(var strategyIter=0; strategyIter<this.strategiesOnHold.length; strategyIter++){
            this.strategiesOnHold[strategyIter].activate();
        }
        
        var deactivated = OpenLayers.Control.TransformFeature.prototype.deactivate.apply(this, arguments);
        // Clear old selection to so that after re-activation the box handle geometries are calculated again
        this.unsetFeature();
        
        this.events.un({
            setfeature: this.highlightTransformedFeature,
            scope: this
        });
        // Restore rendering style of highlighted feature
        if(this.drawOriginalsFeature){
            this.layer.drawFeature(this.drawOriginalsFeature, this.drawOriginalsRenderIntent);
            this.drawOriginalsFeature = null;
            this.drawOriginalsRenderIntent = null;
        }
        
        return deactivated;
    },
    
    /**
     * Highlights the feature currently being transformed
     * @param {Object} e setfeature events
     */
    highlightTransformedFeature: function(e){
        if(this.drawOriginalsFeature){
            this.layer.drawFeature(this.drawOriginalsFeature, this.drawOriginalsRenderIntent);
        }
        this.drawOriginalsFeature = e.feature;
        this.drawOriginalsRenderIntent = e.feature.renderIntent;
        
        this.layer.drawFeature(e.feature, 'select');
    },
    
    /**
     * Copy of superclass's implementation except that it does not collapse bounding box when a point feature is selected.
     */
    createBox: function() {
        var control = this;
        
        this.center = new OpenLayers.Geometry.Point(0, 0);
        this.box = new OpenLayers.Feature.Vector(
            new OpenLayers.Geometry.LineString([
                new OpenLayers.Geometry.Point(-1, -1),
                new OpenLayers.Geometry.Point(0, -1),
                new OpenLayers.Geometry.Point(1, -1),
                new OpenLayers.Geometry.Point(1, 0),
                new OpenLayers.Geometry.Point(1, 1),
                new OpenLayers.Geometry.Point(0, 1),
                new OpenLayers.Geometry.Point(-1, 1),
                new OpenLayers.Geometry.Point(-1, 0),
                new OpenLayers.Geometry.Point(-1, -1)
            ]), null,
            typeof this.renderIntent == "string" ? null : this.renderIntent
        );
        
        // Override for box move - make sure that the center gets updated
        this.box.geometry.move = function(x, y) {
            control._moving = true;
            OpenLayers.Geometry.LineString.prototype.move.apply(this, arguments);
            control.center.move(x, y);
            delete control._moving;
        };

        // Overrides for vertex move, resize and rotate - make sure that
        // handle and rotationHandle geometries are also moved, resized and
        // rotated.
        var vertexMoveFn = function(x, y) {
            OpenLayers.Geometry.Point.prototype.move.apply(this, arguments);
            this._rotationHandle && this._rotationHandle.geometry.move(x, y);
            this._handle.geometry.move(x, y);
        };
        var vertexResizeFn = function(scale, center, ratio) {
            OpenLayers.Geometry.Point.prototype.resize.apply(this, arguments);
            this._rotationHandle && this._rotationHandle.geometry.resize(
                scale, center, ratio);
            this._handle.geometry.resize(scale, center, ratio);
        };
        var vertexRotateFn = function(angle, center) {
            OpenLayers.Geometry.Point.prototype.rotate.apply(this, arguments);
            this._rotationHandle && this._rotationHandle.geometry.rotate(
                angle, center);
            this._handle.geometry.rotate(angle, center);
        };
        
        // Override for handle move - make sure that the box and other handles
        // are updated, and finally transform the feature.
        var handleMoveFn = function(x, y) {
            var oldX = this.x, oldY = this.y;
            OpenLayers.Geometry.Point.prototype.move.call(this, x, y);
            if(control._moving) {
                return;
            }
            var evt = control.dragControl.handlers.drag.evt;
            var preserveAspectRatio = !control._setfeature &&
                control.preserveAspectRatio;
            var reshape = !preserveAspectRatio && !(evt && evt.shiftKey);
            var oldGeom = new OpenLayers.Geometry.Point(oldX, oldY);
            var centerGeometry = control.center;
            this.rotate(-control.rotation, centerGeometry);
            oldGeom.rotate(-control.rotation, centerGeometry);
            var dx1 = this.x - centerGeometry.x;
            var dy1 = this.y - centerGeometry.y;
            var dx0 = dx1 - (this.x - oldGeom.x);
            var dy0 = dy1 - (this.y - oldGeom.y);
            if (control.irregular && !control._setfeature) {
               dx1 -= (this.x - oldGeom.x) / 2;
               dy1 -= (this.y - oldGeom.y) / 2;
            }
            this.x = oldX;
            this.y = oldY;
            var scale, ratio = 1;
            if(control.feature.geometry instanceof OpenLayers.Geometry.Point){
                scale = 1;
            } else {
                if (reshape) {
                    scale = Math.abs(dy0) < 0.00001 ? 1 : dy1 / dy0;
                    ratio = (Math.abs(dx0) < 0.00001 ? 1 : (dx1 / dx0)) / scale;
                } else {
                    var l0 = Math.sqrt((dx0 * dx0) + (dy0 * dy0));
                    var l1 = Math.sqrt((dx1 * dx1) + (dy1 * dy1));
                    scale = l1 / l0;
                }
            }

            // rotate the box to 0 before resizing - saves us some
            // calculations and is inexpensive because we don't drawFeature.
            control._moving = true;
            control.box.geometry.rotate(-control.rotation, centerGeometry);
            delete control._moving;

            control.box.geometry.resize(scale, centerGeometry, ratio);
            control.box.geometry.rotate(control.rotation, centerGeometry);
            control.transformFeature({scale: scale, ratio: ratio});
            if (control.irregular && !control._setfeature) {
               var newCenter = centerGeometry.clone();
               newCenter.x += Math.abs(oldX - centerGeometry.x) < 0.00001 ? 0 : (this.x - oldX);
               newCenter.y += Math.abs(oldY - centerGeometry.y) < 0.00001 ? 0 : (this.y - oldY);
               control.box.geometry.move(this.x - oldX, this.y - oldY);
               control.transformFeature({center: newCenter});
            }
        };
        
        // Override for rotation handle move - make sure that the box and
        // other handles are updated, and finally transform the feature.
        var rotationHandleMoveFn = function(x, y){
            var oldX = this.x, oldY = this.y;
            OpenLayers.Geometry.Point.prototype.move.call(this, x, y);
            if(control._moving) {
                return;
            }
            var evt = control.dragControl.handlers.drag.evt;
            var constrain = (evt && evt.shiftKey) ? 45 : 1;
            var centerGeometry = control.center;
            var dx1 = this.x - centerGeometry.x;
            var dy1 = this.y - centerGeometry.y;
            var dx0 = dx1 - x;
            var dy0 = dy1 - y;
            this.x = oldX;
            this.y = oldY;
            var a0 = Math.atan2(dy0, dx0);
            var a1 = Math.atan2(dy1, dx1);
            var angle = a1 - a0;
            angle *= 180 / Math.PI;
            control._angle = (control._angle + angle) % 360;
            var diff = control.rotation % constrain;
            if(Math.abs(control._angle) >= constrain || diff !== 0) {
                angle = Math.round(control._angle / constrain) * constrain -
                    diff;
                control._angle = 0;
                control.box.geometry.rotate(angle, centerGeometry);
                control.transformFeature({rotation: angle});
            } 
        };

        var handles = new Array(8);
        var rotationHandles = new Array(4);
        var geom, handle, rotationHandle;
        var positions = ["sw", "s", "se", "e", "ne", "n", "nw", "w"];
        for(var i=0; i<8; ++i) {
            geom = this.box.geometry.components[i];
            handle = new OpenLayers.Feature.Vector(geom.clone(), {
                role: positions[i] + "-resize"
            }, typeof this.renderIntent == "string" ? null :
                this.renderIntent);
            if(i % 2 == 0) {
                rotationHandle = new OpenLayers.Feature.Vector(geom.clone(), {
                    role: positions[i] + "-rotate"
                }, typeof this.rotationHandleSymbolizer == "string" ?
                    null : this.rotationHandleSymbolizer);
                rotationHandle.geometry.move = rotationHandleMoveFn;
                geom._rotationHandle = rotationHandle;
                rotationHandles[i/2] = rotationHandle;
            }
            geom.move = vertexMoveFn;
            geom.resize = vertexResizeFn;
            geom.rotate = vertexRotateFn;
            handle.geometry.move = handleMoveFn;
            geom._handle = handle;
            handles[i] = handle;
        }
        
        this.rotationHandles = rotationHandles;
        this.handles = handles;
    }
});