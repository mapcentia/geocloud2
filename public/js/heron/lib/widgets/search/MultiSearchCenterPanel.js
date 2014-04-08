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
Ext.namespace("Heron.widgets.search");

/** api: (define)
 *  module = Heron.widgets.search
 *  class = MultiSearchCenterPanel
 *  base_link = `GeoExt.form.FormPanel <http://www.geoext.org/lib/GeoExt/widgets/form/FormPanel.html>`_
 */

/** api: example
 *  Sample code showing how to configure a Heron MultiSearchCenterPanel.
 *  Note that the  config contains an array of objects that each have a SearchPanel and a ResultPanel.
 *  SearchPanels may use any SearchPanel (Form-, GXP_Query- and/or SpatialSearchPanel).
 *
 *  .. code-block:: javascript

     {
         xtype: 'hr_multisearchcenterpanel',
         height: 600,
         hropts: [
             {
                 searchPanel: {
                     xtype: 'hr_formsearchpanel',
                     name: 'Attribute (Form) Search: USA States',
                     header: false,
                     protocol: new OpenLayers.Protocol.WFS({
                         version: "1.1.0",
                         url: "http://suite.opengeo.org/geoserver/ows?",
                         srsName: "EPSG:4326",
                         featureType: "states",
                         featureNS: "http://usa.opengeo.org"
                     }),
                     items: [
                         {
                             xtype: "textfield",
                             name: "STATE_NAME__like",
                             value: 'ah',
                             fieldLabel: "  name"
                         },
                         {
                             xtype: "label",
                             id: "helplabel",
                             html: 'Type name of a USA state, wildcards are appended and match is case-insensitive.<br/>Almost any single letter will yield results.<br/>',
                             style: {
                                 fontSize: '10px',
                                 color: '#AAAAAA'
                             }
                         }
                     ],
                     hropts: {
                         onSearchCompleteZoom: 10,
                         autoWildCardAttach: true,
                         caseInsensitiveMatch: true,
                         logicalOperator: OpenLayers.Filter.Logical.AND
                     }
                 },
                 resultPanel: {
                      xtype: 'hr_featuregridpanel',
                      id: 'hr-featuregridpanel',
                      header: false,
                      autoConfig: true,
                      hropts: {
                          zoomOnRowDoubleClick: true,
                          zoomOnFeatureSelect: false,
                          zoomLevelPointSelect: 8,
                          zoomToDataExtent: false
                      }
                  }
             },
             {
                 searchPanel: {
                     xtype: 'hr_spatialsearchpanel',
                     name: __('Spatial Search'),
                     header: false,
                     bodyStyle: 'padding: 6px',
                     style: {
                         fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
                         fontSize: '12px'
                     },
                     selectFirst: true

                 },
                 resultPanel: {
                     xtype: 'hr_featuregridpanel',
                     id: 'hr-featuregridpanel',
                     header: false,
                     autoConfig: true,
                     hropts: {
                         zoomOnRowDoubleClick: true,
                         zoomOnFeatureSelect: false,
                         zoomLevelPointSelect: 8,
                         zoomToDataExtent: false
                     }
                 }
             },
             {
                 searchPanel: {
                     xtype: 'hr_spatialsearchpanel',
                     name: __('Spatial Search: with last result geometries'),
                     description: 'This search uses the feature-geometries of the last result to construct and perform a spatial search.',
                     header: false,
                     border: false,
                     bodyStyle: 'padding: 6px',
                     style: {
                         fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
                         fontSize: '12px'
                     },
                     hropts: {
                         fromLastResult: true,
                         maxFilterGeometries: 50,
                         selectFirst: false
                     }
                 },
                 resultPanel: {
                     xtype: 'hr_featuregridpanel',
                     id: 'hr-featuregridpanel',
                     header: false,
                     border: false,
                     autoConfig: true,
                     hropts: {
                         zoomOnRowDoubleClick: true,
                         zoomOnFeatureSelect: false,
                         zoomLevelPointSelect: 8,
                         zoomToDataExtent: false
                     }
                 }
             },
             {
                 searchPanel: {
                     xtype: 'hr_gxpquerypanel',
                     name: __('Spatial and Attributes: build your own queries'),
                     description: 'This search uses both search within Map extent and/or your own attribute criteria',
                     header: false,
                     border: false
                 },
                 resultPanel: {
                     xtype: 'hr_featuregridpanel',
                     id: 'hr-featuregridpanel',
                     header: false,
                     border: false,
                     autoConfig: true,
                     hropts: {
                         zoomOnRowDoubleClick: true,
                         zoomOnFeatureSelect: false,
                         zoomLevelPointSelect: 8,
                         zoomToDataExtent: true
                     }
                 }
             }
         ]
     }

 * And then enable the MultiSearchCenterPanel as a MapPanel toolbar item (type: 'searchcenter', icon: binoculars).
 *
 *  .. code-block:: javascript
 *

     Heron.options.map.toolbar = [
         {type: "featureinfo", options: {max_features: 20}},
         {type: "-"} ,
         {type: "pan"},
         {type: "zoomin"},
         {type: "zoomout"},
         {type: "zoomvisible"},
         {type: "-"} ,
         {type: "zoomprevious"},
         {type: "zoomnext"},
         {type: "-"},
         {
             type: "searchcenter",
             // Options for SearchPanel window
             options: {
                 show: true,

                 searchWindow: {
                     title: __('Multiple Searches'),
                     x: 100,
                     y: undefined,
                     width: 360,
                     height: 440,
                     items: [
                         Heron.examples.searchPanelConfig
                     ]
                 }
             }
         }
     ];

*/

/** api: constructor
 *  .. class:: MultiSearchCenterPanel(config)
 *
 *  A panel designed to hold a multiple Search/ResultPanel combinations and a
 *  combobox selector to select a specific Search.
 */
Heron.widgets.search.MultiSearchCenterPanel = Ext.extend(Heron.widgets.search.SearchCenterPanel, {

    config: [],

    initComponent: function () {
        this.config = this.hropts;

        var searchNames = [];
        Ext.each(this.config, function (item) {
            searchNames.push(item.searchPanel.name ? item.searchPanel.name : __('Undefined (check your config)'));
        });

        this.combo = new Ext.form.ComboBox({
            store: searchNames, //direct array data
            value: searchNames[0],
            editable: false,
            typeAhead: false,
            triggerAction: 'all',
            emptyText: __('Select a search...'),
            selectOnFocus: true,
            width: 250,
            listeners: {
                scope: this,
                'select': this.onSearchSelect
            }

        });

        this.tbar = [
            {'text': __('Search') + ': '},
            this.combo
        ];

        this.setPanels(this.config[0].searchPanel, this.config[0].resultPanel);
        Heron.widgets.search.MultiSearchCenterPanel.superclass.initComponent.call(this);
    },


    /** api: method[onSearchSelect]
     *  Called when search selected in combo box.
     */
    onSearchSelect: function (comboBox) {
        var self = this;
        Ext.each(this.config, function (item) {
            if (item.searchPanel.name == comboBox.value) {
                self.switchPanels(item.searchPanel, item.resultPanel);
            }
        });
        this.showSearchPanel(this);
    },

    /***
     * Callback from SearchPanel on successful search.
     */
    onSearchSuccess: function (searchPanel, result) {
        Heron.widgets.search.MultiSearchCenterPanel.superclass.onSearchSuccess.call(this, searchPanel, result);
        this.lastResultFeatures = result.features;
    },

    /**
     * Set the Search and Result Panels to be displayed.
     */
    setPanels: function (searchPanel, resultPanel) {
        if (this.hropts.searchPanel && this.hropts.searchPanel.name === searchPanel.name) {
            return false;
        }
        this.hropts.searchPanel = searchPanel;
        this.hropts.resultPanel = resultPanel;
        return true;
    },

    /**
     * Set the Search and Result Panels to be displayed.
     */
    switchPanels: function (searchPanel, resultPanel) {
        if (!this.setPanels(searchPanel, resultPanel)) {
            return;
        }

        if (this.searchPanel) {
            this.lastSearchName = this.searchPanel.name;
            this.remove(this.searchPanel, true);
        }

        if (this.resultPanel) {
            this.resultPanel.cleanup();
            this.remove(this.resultPanel, true);
            this.resultPanel = null;
        }

        if (this.hropts.searchPanel.hropts && this.hropts.searchPanel.hropts.fromLastResult) {
            this.hropts.searchPanel.hropts.filterFeatures = this.lastResultFeatures;
            this.hropts.searchPanel.hropts.lastSearchName = this.lastSearchName;
        }

        this.searchPanel = Ext.create(this.hropts.searchPanel);
        this.add(this.searchPanel);
        this.searchPanel.show();

        this.getLayout().setActiveItem(this.searchPanel);
        this.onRendered();
    }
});

/** api: xtype = hr_multisearchcenterpanel */
Ext.reg('hr_multisearchcenterpanel', Heron.widgets.search.MultiSearchCenterPanel);


