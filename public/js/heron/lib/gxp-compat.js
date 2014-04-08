/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Some Heron components derive from GXP classes.
 * This would  require the entire GXP lib to be included.
 * This file is needed when NOT using GXP or Heron-GXP-derived classes
 * such that including GXP is not required.
 */

Ext.namespace("gxp");
if (!gxp.QueryPanel) {
    // Just make a null def for the query Panel
    gxp.QueryPanel = function () {
    };
} else {
    /** GXP is used. Below fixes that did not make it into the Boundless GXP Master branch or were too kludgy. */

    /** api: (define)
     *  module = gxp.data
     *  class = WFSProtocolProxy
     *  base_link = `Ext.data.DataProxy <http://extjs.com/deploy/dev/docs/?class=Ext.data.DataProxy>`_
     */
    Ext.namespace("gxp.data");

    gxp.data.WFSProtocolProxy = Ext.extend(GeoExt.data.ProtocolProxy, {

        /** api: method[setFilter]
         *  :arg filter: ``OpenLayers.Filter`` Filter to be set on the WFS protocol.
         *
         *  Does not trigger anything on the protocol (for now).
         */
        setFilter: function(filter) {
            this.protocol.filter = filter;
            // TODO: The protocol could use a setFilter method.
            this.protocol.options.filter = filter;
        },

        /** api: constructor
         *  .. class:: WFSProtocolProxy
         *
         *      A data proxy for use with ``OpenLayers.Protocol.WFS`` objects.
         *
         *      This is mainly to extend Ext 3.0 functionality to the
         *      GeoExt.data.ProtocolProxy.  This could be simplified by having
         *      the ProtocolProxy support writers (implement doRequest).
         */
        constructor: function(config) {

            Ext.applyIf(config, {

                /** api: config[version]
                 *  ``String``
                 *  WFS version.  Default is "1.1.0".
                 */
                version: "1.1.0"

                /** api: config[maxFeatures]
                 *  ``Number``
                 *  Optional limit for number of features requested in a read.  No
                 *  limit set by default.
                 */

                /** api: config[multi]
                 *  ``Boolean`` If set to true, geometries will be casted to Multi
                 *  geometries before writing. No casting will be done for reading.
                 */

            });

            // create the protocol if none provided
            if(!(this.protocol && this.protocol instanceof OpenLayers.Protocol)) {
                config.protocol = new OpenLayers.Protocol.WFS(Ext.apply({
                    version: config.version,
                    srsName: config.srsName,
                    url: config.url,
                    featureType: config.featureType,
                    featureNS :  config.featureNS,
                    geometryName: config.geometryName,
                    schema: config.schema,
                    filter: config.filter,
                    maxFeatures: config.maxFeatures,
                    // VERY VERY NASTY FIX: but the Dutch National SDI (PDOK) still uses an old GeoServer version
                    // which returns "null" namespaces for the default GML3 format. So force GML2 for that domain.
                    // Remove entire override when PDOK have their act together.
                    // JvdB oct 30, 2013.
                    outputFormat: config.url.indexOf('nationaalgeoregister') > 0 ? 'GML2' : undefined,
                    multi: config.multi
                }, config.protocol));
            }

            gxp.data.WFSProtocolProxy.superclass.constructor.apply(this, arguments);
        },


        /** private: method[doRequest]
         *  :arg action: ``String`` The crud action type (create, read, update,
         *      destroy)
         *  :arg records: ``Array(Ext.data.Record)`` If action is load, records will
         *      be null
         *  :arg params: ``Object`` An object containing properties which are to be
         *      used as request parameters.
         *  :arg reader: ``Ext.data.DataReader`` The Reader object which converts
         *      the data object into a block of ``Ext.data.Record`` objects.
         *  :arg callback: ``Function``  A function to be called after the request.
         *      The callback is passed the following arguments: records, options,
         *      success.
         *  :arg scope: ``Object`` The scope in which to call the callback.
         *  :arg arg: ``Object`` An optional argument which is passed to the
         *      callback as its second parameter.
         */
        doRequest: function(action, records, params, reader, callback, scope, arg) {

            // remove the xaction param tagged on because we're using a single url
            // for all actions
            delete params.xaction;

            if (action === Ext.data.Api.actions.read) {
                this.load(params, reader, callback, scope, arg);
            } else {
                if(!(records instanceof Array)) {
                    records = [records];
                }
                // get features from records
                var features = new Array(records.length), feature;
                Ext.each(records, function(r, i) {
                    features[i] = r.getFeature();
                    feature = features[i];
                    feature.modified = Ext.apply(feature.modified || {}, {
                        attributes: Ext.apply(
                            (feature.modified && feature.modified.attributes) || {},
                            r.modified
                        )
                    });
                }, this);


                var o = {
                    action: action,
                    records: records,
                    callback: callback,
                    scope: scope
                };

                var options = {
                    callback: function(response) {
                        this.onProtocolCommit(response, o);
                    },
                    scope: this
                };

                Ext.applyIf(options, params);

                this.protocol.commit(features, options);
            }

        },


        /** private: method[onProtocolCommit]
         *  Callback for the protocol.commit method.  Called with an additional
         *  object containing references to arguments to the doRequest method.
         */
        onProtocolCommit: function(response, o) {
            if(response.success()) {
                var features = response.reqFeatures;
                // deal with inserts, updates, and deletes
                var state, feature;
                var destroys = [];
                var insertIds = response.insertIds || [];
                var i, len, j = 0;
                for(i=0, len=features.length; i<len; ++i) {
                    feature = features[i];
                    state = feature.state;
                    if(state) {
                        if(state == OpenLayers.State.DELETE) {
                            destroys.push(feature);
                        } else if(state == OpenLayers.State.INSERT) {
                            feature.fid = insertIds[j];
                            ++j;
                        } else if (feature.modified) {
                            feature.modified = {};
                        }
                        feature.state = null;
                    }
                }

                for(i=0, len=destroys.length; i<len; ++i) {
                    feature = destroys[i];
                    feature.layer && feature.layer.destroyFeatures([feature]);
                }

                /**
                 * TODO: Update the FeatureStore and FeatureReader to work with
                 * callbacks from 3.0.
                 *
                 * The callback here is the result of store.createCallback.  The
                 * signature should be what is expected by the anonymous function
                 * created in store.createCallback: (data, response, success).  The
                 * callback is a wrapped version of store.onCreateRecords etc.
                 *
                 * The onCreateRecords method calls reader.realize, which expects a
                 * primary key in the data.  Though it *feels* like the job of the
                 * reader, we need to create valid record data here (eventually to
                 * be passed to reader.realize).  The reader.realize method calls
                 * reader.extractValues - which seems like a nice place to grab the
                 * fids from the features.  However, we need the fid in the data
                 * object *before* extractValues is called.  So, we create a basic
                 * data object with just the id (mapping determined by
                 * reader.meta.idProperty or, for Ext > 3.0, reader.getId) and the
                 * state property, which is always reset to null after a commit.
                 *
                 * After the reader.realize method determines that the data is valid
                 * (determined by reader.isValid(data)), then extractValues gets
                 * called - where it will create values objects (to be set as
                 * record.data) from data.features.
                 *
                 * An important thing to note here is that though we may have "batch"
                 * set to true, the store.save sequence issues one request per action.
                 * So, we should *never* be here with a mix of features (deleted,
                 * updated, created).
                 *
                 * Bottom line (based on my current understanding): we need to
                 * implement extractValues for the FeatureReader.
                 */
                len = features.length;
                var data = new Array(len);
                var f;
                for (i=0; i<len; ++i) {
                    f = features[i];
                    // TODO - check if setting the state to null here is appropriate,
                    // or if feature state handling should rather be done in
                    // GeoExt.data.FeatureStore
                    data[i] = {id: f.id, feature: f, state: null};
                    var fields = o.records[i].fields;
                    for (var a in f.attributes) {
                        if (fields.containsKey(a)) {
                            data[i][a] = f.attributes[a];
                        }
                    }
                }

                o.callback.call(o.scope, data, response.priv, true);
            } else {
                // TODO: determine from response if exception was "response" or "remote"
                var request = response.priv;
                if (request.status >= 200 && request.status < 300) {
                    // service exception with 200
                    this.fireEvent("exception", this, "remote", o.action, o, response.error, o.records);
                } else {
                    // non 200 status
                    this.fireEvent("exception", this, "response", o.action, o, request);
                }
                o.callback.call(o.scope, [], request, false);
            }

        }

    });


}


// Fixes a very nasty problem with IE, where the Picker Window doe not stretch to the Picker Box Component's width.
// See https://code.google.com/p/geoext-viewer/issues/detail?id=329#c9
// After long debug session decided to set width/height for now explicitly with values from CSS (see default.css
// .x-color-palette, corrected with frame width/height.
// See lines with comments ++ below, these are the only changes to the original in GXP.
if (gxp.ColorManager) {
    Ext.override(gxp.ColorManager, {
        /**
         * Method: fieldFocus
         * Listener for "focus" event on a field.
         *
         * Parameters:
         * field - {<Styler.form.ColorField>} The focussed field.
         */
        fieldFocus: function(field) {
            if(!gxp.ColorManager.pickerWin) {
                gxp.ColorManager.picker = new Ext.ColorPalette();
                gxp.ColorManager.pickerWin = new Ext.Window({
                    title: "Color Picker",
                    closeAction: "hide",
                    // ++ Originally was true, true, but to fix IE issue
                    autoWidth: Ext.isIE ? false : true,
                    autoHeight: Ext.isIE ? false : true
                });
            } else {
                gxp.ColorManager.picker.purgeListeners();
            }
            var listenerCfg = {
                select: this.setFieldValue,
                scope: this
            };
            var value = this.getPickerValue();
            if (value) {
                var colors = [].concat(gxp.ColorManager.picker.colors);
                if (!~colors.indexOf(value)) {
                    if (gxp.ColorManager.picker.ownerCt) {
                        gxp.ColorManager.pickerWin.remove(gxp.ColorManager.picker);
                        gxp.ColorManager.picker = new Ext.ColorPalette();
                    }
                    colors.push(value);
                    gxp.ColorManager.picker.colors = colors;
                }
                gxp.ColorManager.pickerWin.add(gxp.ColorManager.picker);

                if (Ext.isIE) {
                    // ++ Added to fix IE issue
                    gxp.ColorManager.pickerWin.setSize(456+10, 248+36);
                }

                gxp.ColorManager.pickerWin.doLayout();

                if (gxp.ColorManager.picker.rendered) {
                    gxp.ColorManager.picker.select(value);
                } else {
                    listenerCfg.afterrender = function() {
                        gxp.ColorManager.picker.select(value);
                    };
                }
            }
            gxp.ColorManager.picker.on(listenerCfg);
            gxp.ColorManager.pickerWin.show();
        }
    });
}
