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
Ext.namespace("Heron.data");

/** api: (define)
 *  module = Heron.data
 *  class = MapContext
 *  base_link = `Ext.DomHelper <http://docs.sencha.com/ext-js/3-4/#!/api/Ext.DomHelper>`_
 */
/**
 * Define functions to help with Map Context open and save.
 */
Heron.data.MapContext = {
    prefix: "heron:",
    xmlns: 'xmlns:heron="http://heron-mc.org/context"',
    oldNodes: null,
    initComponent: function () {
        Heron.data.MapContext.superclass.initComponent.call(this);
    },
    /** method[saveContext]
     *  Save a Web Map Context file
     *  WMC (only WMS layers) is extended with save of
     *  TMS and Image layers and custom layertree
     *  :param mapPanel: Panel with the Heron map
     *         options: config options
     */
    saveContext: function (mapPanel, options) {
        var self = this;
        var data = self.writeContext (mapPanel);
        // data = Heron.Utils.formatXml;
        // this formatter is preferred: less returns, smaller padding
        data = this.formatXml(data);
        data = Base64.encode(data);
        try {
            // Cleanup previous form if required
            Ext.destroy(Ext.get('hr_downloadForm'));
        }
        catch (e) {
        }
        var formFields = [
            {tag: 'input', type: 'hidden', name: 'data', value: data},
            {tag: 'input', type: 'hidden', name: 'filename', value: options.fileName + options.fileExt},
            {tag: 'input', type: 'hidden', name: 'mime', value: 'text/xml'},
            {tag: 'input', type: 'hidden', name: 'encoding', value: 'base64'},
            {tag: 'input', type: 'hidden', name: 'action', value: 'download'},
        ];

        var form = Ext.DomHelper.append(
                document.body,
                {
                    tag: 'form',
                    id: 'hr_downloadForm',
                    method: 'post',
                    /** Heron CGI URL, see /services/heron.cgi. */
                    action: Heron.globals.serviceUrl,
                    children: formFields
                }
        );

        // Add Form to document and submit
        document.body.appendChild(form);
        form.submit();
    },
     /** method[openContext]
     *  Open a Web Map Context file
     *  WMC (only WMS layers) is extended with load of
     *  TMS and Image layers and custom layertree
     *  :param mapPanel: Panel with the Heron map
     *         options: config options
     */
    openContext: function (mapPanel, options){
        var self = this;
        var data = null;
        try {
            // Cleanup previous form if required
            Ext.destroy(Ext.get('hr_uploadForm'));
        }
        catch (e) {
        }

        var uploadForm = new Ext.form.FormPanel({
            id: 'hr_uploadForm',
            fileUpload: true,
            width: 300,
            autoHeight: true,
            bodyStyle: 'padding: 10px 10px 10px 10px;',
            labelWidth: 5,
            defaults: {
                anchor: '95%',
                allowBlank: false,
                msgTarget: 'side'
            },
            items:[
            {
                xtype: 'field',
                id: 'mapfile',
        		name: 'file',
                inputType: 'file'
            }],

            buttons: [{
                text: __('Upload'),
                handler: function(){
                    if(uploadForm.getForm().isValid()){
                        var fileField = uploadForm.getForm().findField('mapfile');
                        var selectedFile = fileField.getValue();
                        if (!selectedFile) {
                            Ext.Msg.alert(__('Warning'), __('No file specified.'));
                            return;
                        }
                        uploadForm.getForm().submit({
                            url: Heron.globals.serviceUrl,
                            mime: 'text/html',
                            params: {
                                action: 'upload',
                                mime: 'text/html',
                                encoding: 'base64'
                            },
                            waitMsg: __('Uploading file...'),
                            success: function(form, action){
                                data = Base64.decode(action.response.responseText);
                                self.loadContext (mapPanel, data);
                                uploadWindow.close();
                            },
                            failure: function (form, action){
                                //somehow we allways get no succes althought the response is as expected
                                data = Base64.decode(action.response.responseText);
                                self.loadContext (mapPanel, data);
                                uploadWindow.close();
                            }
                        });
                    }
                }
            },
            {
                text: __('Cancel'),
                handler: function(){
                    uploadWindow.close();
            }
            }]
        });

        var uploadWindow = new Ext.Window({
            id: 'hr_uploadWindow',
            title: 'Upload',
            closable:true,
            width: 400,
            height: 120,
            plain:true,
            layout: 'fit',
            items: uploadForm,
            listeners: {
                show: function() {
                    var form = this.items.get(0);
                    form.getForm().load();
                }
            }
        });
        uploadWindow.show();

    },
     /** private: method[writeContext]
     *  Write a Web Map Context in the map
     *  :param mapPanel: Panel with the Heron map
     *  :return data: the Web Map Context
     */
    writeContext: function (mapPanel) {
        var map = mapPanel.getMap();

        // Save the standard context including OpenLayers context
        var format = new OpenLayers.Format.WMC();
        var data = format.write(map);

        // Save omitted info by WMC about map to context
        var objMap = {units: map.units,
                        xy_precision: map.xy_precision,
                        projection: map.projection,
                        zoom: map.zoom,
                        resolutions: map.resolutions,
                        resolution: map.resolution,
                        maxExtent: {
                            bottom: map.maxExtent.bottom,
                            left: map.maxExtent.left,
                            right: map.maxExtent.right,
                            top: map.maxExtent.top
                        }
                      };

        var jsonMap = (Ext.encode(objMap));
        jsonMap = this.formatJson(jsonMap);
        var mapOptions = "<Extension><"+this.prefix + "mapOptions " + this.xmlns + ">\n" +
                            jsonMap +
                          "</"+this.prefix + "mapOptions></Extension>";
        data = data.replace("</LayerList>","</LayerList>" + mapOptions);

        // Save the treeConfig stored in LayerTreePanel to context
        var treePanel = Heron.App.topComponent.findByType('hr_layertreepanel')[0];

        if (treePanel && treePanel.jsonTreeConfig != null) {
            var jsonTree = treePanel.jsonTreeConfig;
            var tree = "<Extension><"+this.prefix + "treeConfig " + this.xmlns + ">" +
                            jsonTree +
                          "</"+this.prefix + "treeConfig></Extension>";
            data = data.replace("</LayerList>","</LayerList>" + tree);
        }

        // Save possible TMS layers to context
        var arrTmsLayers = new Array();
        arrTmsLayers = map.getLayersBy("id", /OpenLayers.Layer.TMS/);
        var jsonTmsLayers = '';

        for (var i = 0; i < arrTmsLayers.length; i++){
            var tmsLayer = arrTmsLayers[i];
            var objTmsOptions = {layername: tmsLayer.layername,
                    type: tmsLayer.type,
                    isBaseLayer: tmsLayer.isBaseLayer,
                    transparent: tmsLayer.transparent,
                    bgcolor: tmsLayer.bgcolor,
                    visibility: tmsLayer.visibility,
                    singleTile: tmsLayer.singleTile,
                    alpha: tmsLayer.alpha,
                    opacity: tmsLayer.opacity,
                    minResolution: tmsLayer.minResolution,
                    maxResolution: tmsLayer.maxResolution,
                    projection: tmsLayer.projection.projCode,
                    units: tmsLayer.units,
                    transitionEffect: tmsLayer.transitionEffect
            }
            var objTms = {name: tmsLayer.name,
                      url: tmsLayer.url,
                      options: objTmsOptions};

            var jsonTms = (Ext.encode(objTms));

            if (jsonTmsLayers == '')
                jsonTmsLayers += jsonTms
            else
                jsonTmsLayers = jsonTmsLayers + ',' + jsonTms;
        }

        if (jsonTmsLayers != ''){
            jsonTmsLayers = this.formatJson(jsonTmsLayers);
            var tms = "<Extension><"+this.prefix + "tmsLayers " + this.xmlns + ">\n[" +
                            jsonTmsLayers +
                          "]\n</"+this.prefix + "tmsLayers></Extension>";
            data = data.replace("</LayerList>","</LayerList>" + tms);
        }

        // Save possible Image layers to context (especially layer Blanc/None)
        var arrImgLayers = new Array();
        arrImgLayers = map.getLayersBy("id", /OpenLayers.Layer.Image/);
        var jsonImgLayers = '';

        for (i = 0; i < arrImgLayers.length; i++){
            var imgLayer = arrImgLayers[i];
            var objImgOptions = {layername: imgLayer.layername,
                    type: imgLayer.type,
                    isBaseLayer: imgLayer.isBaseLayer,
                    transparent: imgLayer.transparent,
                    bgcolor: imgLayer.bgcolor,
                    visibility: imgLayer.visibility,
                    alpha: imgLayer.alpha,
                    opacity: imgLayer.opacity,
                    minResolution: imgLayer.minResolution,
                    maxResolution: imgLayer.maxResolution,
                    projection: imgLayer.projection.projCode,
                    units: imgLayer.units,
                    transitionEffect: imgLayer.transitionEffect,
                    size: imgLayer.size,
                    extent: imgLayer.extent
            }
            var objImg = {name: imgLayer.name,
                      url: imgLayer.url,
                      options: objImgOptions};

            var jsonImg = (Ext.encode(objImg));

            if (jsonImgLayers == '')
                jsonImgLayers += jsonImg
            else
                jsonImgLayers = jsonImgLayers + ',' + jsonImg;
        }

        if (jsonImgLayers != ''){
            jsonImgLayers = this.formatJson(jsonImgLayers);
            var img = "<Extension><"+this.prefix + "imageLayers " + this.xmlns + ">\n[" +
                            jsonImgLayers +
                          "]\n</"+this.prefix + "imageLayers></Extension>";
            data = data.replace("</LayerList>","</LayerList>" + img);
        }

        return data;
    },
    /** private: method[loadContext]
     *  Load a Web Map Context in the map
     *  :param mapPanel: Panel with the Heron map
     *         data: the Web Map Context
     */
    loadContext: function (mapPanel, data) {
        var map = mapPanel.getMap();
        var format = new OpenLayers.Format.WMC();
        var num;
        var objLayer;
        var newLayer;
        var isBaseLayerInFile = false;
        var oldNodes = new Array();
        var treePanel = Heron.App.topComponent.findByType('hr_layertreepanel')[0];

        if (treePanel){
            var treeRoot = treePanel.getRootNode();            
        }

        // Get tree from data
        var strTagStart = "<" + this.prefix + 'treeConfig ' + this.xmlns + ">"
        var strTagEnd = "</" + this.prefix + 'treeConfig' + ">"
        var posStart = data.indexOf(strTagStart);
        var posEnd = data.indexOf(strTagEnd);

        var newTreeConfig = null;
        if (posStart > 0){
            posStart = data.indexOf(strTagStart) + strTagStart.length;
            newTreeConfig = data.substring(posStart, posEnd);
        } 

        // Get map options from data
        strTagStart = "<" + this.prefix + 'mapOptions ' + this.xmlns + ">"
        strTagEnd = "</" + this.prefix + 'mapOptions' + ">"
        posStart = data.indexOf(strTagStart);
        posEnd = data.indexOf(strTagEnd);

        var newMapOptions = null;
        if (posStart > 0){
            posStart = data.indexOf(strTagStart) + strTagStart.length;
            newMapOptions = data.substring(posStart, posEnd);
        }

        // Get TMS layers from data
        strTagStart = "<" + this.prefix + 'tmsLayers ' + this.xmlns + ">"
        strTagEnd = "</" + this.prefix + 'tmsLayers' + ">"
        posStart = data.indexOf(strTagStart);
        posEnd = data.indexOf(strTagEnd);

        var tmsLayers = null;
        if (posStart > 0){
            posStart = data.indexOf(strTagStart) + strTagStart.length;
            tmsLayers = data.substring(posStart, posEnd);
        }

        // Get Image layers from data
        strTagStart = "<" + this.prefix + 'imageLayers ' + this.xmlns + ">"
        strTagEnd = "</" + this.prefix + 'imageLayers' + ">"
        posStart = data.indexOf(strTagStart);
        posEnd = data.indexOf(strTagEnd);

        var imgLayers = null;
        if (posStart > 0){
            posStart = data.indexOf(strTagStart) + strTagStart.length;
            imgLayers = data.substring(posStart, posEnd);
        }

        // create testMap to check file and BaseLayer existense
        try {
            var testMap = new OpenLayers.Map();
            testMap = format.read(data,{map: testMap});
            num = testMap.getNumLayers();
            var i = 0;
            do {
                isBaseLayerInFile = testMap.layers[i].isBaseLayer;
                i++;
            } while (!isBaseLayerInFile && i < num)
            testMap.destroy();
        } catch  (err) {
            Ext.Msg.alert(__('Error reading map file, map has not been loaded.'));
            console.log ("Error while testing WMC file: " + err.message);
            testMap.destroy();
            return;
        }

        if (treePanel){
            // Need to preload tree otherwise cascade does not find all nodes
            treePanel.getLoader().doPreload(treeRoot);

            // Store all old node ids for later removal
            for (i = 0; i < treeRoot.childNodes.length; i++){
                oldNodes.push(treeRoot.childNodes[i]);
                treeRoot.childNodes[i].cascade (function (node){
                    oldNodes.push(node);
                }, null, null);
            }
        }

        // Remove old layers
        num = map.getNumLayers();
        for (i = num - 1; i >= 0; i--) {
            var strLayer = null;
            try {
                strLayer = map.layers[i].name;
                map.removeLayer(map.layers[i],false);
            } catch (err) {
                Ext.Msg.alert(__('Error on removing layers.'));
                console.log ("Problem with removing layers before loading map: " + err.message );
                console.log ("Layer[" + i + "]: " + strLayer);
            }
        }

        if (treePanel){
            // Clean up remaing tree nodes
            while (oldNodes.length > 0) {
                var oldNode = oldNodes.pop()
                if (oldNode){
                    this.removeTreeNode (oldNode);
                }
            }
        }

        // Set map options
        var mapOptions = Ext.decode(newMapOptions);

        // Set maxExtent as Bounds object
        var maxExtent = mapOptions.maxExtent;
        var bounds = new OpenLayers.Bounds(maxExtent.left, maxExtent.bottom, maxExtent.right, maxExtent.top);

        // Delete maxExtent from mapOptions, is no bounds object
        delete mapOptions.maxExtent;
        map.setOptions(mapOptions);
        map.setOptions({maxExtent: bounds});

        // Set allOverlays true if no Baselayers and the other way around.
        map.allOverlays = !isBaseLayerInFile

        // Load TMS layers
        if (tmsLayers != null) {
            tmsLayers = Ext.decode(tmsLayers);
            for (i=0; i<tmsLayers.length; i++ ){
                objLayer = tmsLayers[i];
                newLayer = new OpenLayers.Layer.TMS(objLayer.name,objLayer.url, objLayer.options );
                if (newLayer.isBaseLayer && !isBaseLayerInFile){
                    isBaseLayerInFile = true;
                    map.allOverlays = false;
                }
                map.addLayer(newLayer);
                if (objLayer.options.isBaseLayer && objLayer.options.visibility){
                    map.setBaseLayer (newLayer);
                }
            }
        }

        // Load Image layers (we need this because layer None or Blanc is an image layer)
        if (imgLayers != null) {
            imgLayers = Ext.decode(imgLayers);
            for (i=0; i<imgLayers.length; i++ ){
                objLayer = imgLayers[i];
                var imgExtent = objLayer.options.extent;
                // remove extent from object as it is no Bounds object
                delete objLayer.options.extent;
                var objExtent = new OpenLayers.Bounds(imgExtent.left, imgExtent.bottom, imgExtent.right, imgExtent.top);

                newLayer = new OpenLayers.Layer.Image(objLayer.name,objLayer.url, objExtent, objLayer.options.size, objLayer.options );
                if (newLayer.isBaseLayer && !isBaseLayerInFile){
                    isBaseLayerInFile = true;
                    map.allOverlays = false;
                }
                map.addLayer(newLayer);
                if (objLayer.options.isBaseLayer && objLayer.options.visibility){
                    map.setBaseLayer (newLayer);
                }
            }
        }

        // Load map from data read from file
        try {
            map = format.read(data, {map: map});
        } catch (err) {
            Ext.Msg.alert(__('Error loading map file.'));
            console.log ("Error loading map file: " + err.message );
        }


        // Load new tree from file
        if (treePanel && newTreeConfig) {
            treeRoot.attributes.children = Ext.decode(newTreeConfig);
            try {
                treePanel.getLoader().load(treeRoot);
                // Save this treeConfig at LayerTreePanel object for next time save action
                treePanel.jsonTreeConfig = newTreeConfig;
            } catch(err) {
                Ext.Msg.alert(__('Error reading layer tree.'));
                console.log ("Error on loading tree: " + err.message);
            }
        }

        // EPSG box
        var epsgTxt = map.getProjection();
        if (epsgTxt) {
            // Get EPSG text element.
            var epsg = Ext.getCmp("map-panel-epsg");
            if (epsg) {
                // Found, show EPSG text.
                epsg.setText(epsgTxt);
            }
        }

        //set active baselayer
        num = format.context.layersContext.length;
        for ( i = num - 1; i >= 0; i--) {
            if ((format.context.layersContext[i].isBaseLayer == true) &&
                (format.context.layersContext[i].visibility == true)){
                var strActiveBaseLayer = format.context.layersContext[i].title;
                var newBaseLayer = map.getLayersByName(strActiveBaseLayer)[0];
                if (newBaseLayer){
                    try {
                        map.setBaseLayer(newBaseLayer);
                    } catch(err) {
                        console.log ("Error on setting Baselayer: " + err.message);
                    }
                }
            }
        }

        map.zoomToExtent(format.context.bounds);
    },
    /** private method[removeTreeNode]
     *  Remove a node from the tree recursively
     *  :param node: node to delete
     */
    removeTreeNode: function (node){
        if (node.childNodes && node.childNodes.length > 0) {
            for (var i = 0; i < node.childNodes.length; i++){
                this.removeTreeNode (node.childNodes[i]);
            }
        } else {
            node.remove (true);
        }
    },
     /** method[formatXml]
     *  Format as readable XML
     *  :param xml: xml text to format with indents
     *  This formatXml differs from Heron.Utils.formatXml:
     *      less returns (tag/end-tag on one line if only one value in between)
     *  If accepted, replace Heron.Utils.formatXml with this one
     */
    formatXml: function (xml) {
        // Thanks to: https://gist.github.com/sente/1083506
        var formatted = '';
        var reg = /(>)(<)(\/*)/g;
        xml = xml.replace(reg, '$1\n$2$3');
        var arrSplit = xml.split('\n');
        var pad = 0;
        for (var intNode = 0; intNode < arrSplit.length; intNode++) {
            var node = arrSplit[intNode];
            var indent = 0;
            if (node.match( /.+<\/\w[^>]*>$/ )) {
                indent = 0;
            } else if (node.match( /^<\/\w/ )) {
                if (pad != 0) {
                    pad -= 1;
                }
            } else if (node.match( /^<\w[^>]*[^\/]>.*$/ )) {
                indent = 1;
            } else {
               indent = 0;
            }

            var padding = '';
            for (var i = 0; i < pad; i++) {
                padding += '    ';
            }

            formatted += padding + node + '\n';
            pad += indent;
        }

        return formatted;
    },
     /** method[formatJson]
     *  Format as readable Json
     *  :param json: json text to format with indents
     *  If accepted, probably better placed in Heron.Utils
     */
    formatJson: function (json) {
        var formatted = '';
        json = json.replace(/({)/g, '$1\n');
        json = json.replace(/(})({)/g, '$1\n$2');
        json = json.replace(/(:)({)/g, '$1\n$2');
        json = json.replace(/(,)/g, '$1\n');
        json = json.replace(/(})/g, '\n$1');
        var arrSplit = json.split('\n');
        var pad = 0;
        // this has to be changed for json
        for (var intNode = 0; intNode < arrSplit.length; intNode++) {
            var node = arrSplit[intNode];
            var indent = 0;
            if (node.match( /}/ )) {
                if (pad != 0) {
                    pad -= 1;
                }
            } else if (node.match( /{/ )) {
                indent = 1;
            } else {
               indent = 0;
            }

            var padding = '';
            for (var i = 0; i < pad; i++) {
                padding += '    ';
            }

            formatted += padding + node + '\n';
            pad += indent;
        }

        return formatted;
    }
};