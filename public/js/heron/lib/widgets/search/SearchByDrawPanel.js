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
 *  class = SearchByDrawPanel
 *  base_link = `Heron.widgets.search.SpatialSearchPanel <SpatialSearchPanel.html>`_
 */

/** api: example
 *  This Panel is mostly used in combination with the  `Heron.widgets.search.FeaturePanel <FeaturePanel.html>`_
 *  in which results from a search are displayed in a grid and on the Map. Both Panels are usually bundled
 *  in a `Heron.widgets.search.SearchCenterPanel <SearchCenterPanel.html>`_ that manages the search and result Panels.
 *  See config example below.
 *
 *  .. code-block:: javascript

    {
    xtype: 'hr_searchcenterpanel',
    hropts: {
        searchPanel: {
        xtype: 'hr_searchbydrawpanel',
            id: 'hr-searchbydrawpanel',
            header: false,
             border: false,
            style: {
                fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
                fontSize: '12px'
            }
        },
        resultPanel: {
            xtype: 'hr_featuregridpanel',
            id: 'hr-featuregridpanel',
            header: false,
             border: false,
            autoConfig: true,
             exportFormats: ['XLS', 'WellKnownText'],
            hropts: {
                zoomOnRowDoubleClick: true,
                zoomOnFeatureSelect: false,
                zoomLevelPointSelect: 8,
                zoomToDataExtent: false
            }
        }
    }
 *
 *  A ``SearchCenterPanel`` can be embedded in a Toolbar item popup as toolbar definition item ``searchcenter``
 *  as below. ``Heron.examples.searchPanelConfig`` is the ``SearchCenterPanel`` config as above.
 *
 *  .. code-block:: javascript

    {
    type: "searchcenter",
    // Options for SearchPanel window
    options: {
         show: true,

        searchWindow: {
            title: __('Search by Drawing'),
            x: 100,
            y: undefined,
            width: 360,
            height: 400,
            items: [
                Heron.examples.searchPanelConfig
            ]
        }
    }
    }
 */

/** api: constructor
 *  .. class:: SearchByDrawPanel(config)
 *
 *  A Panel to hold a spatial (WFS) query by drawing geometries.
 */
Heron.widgets.search.SearchByDrawPanel = Ext.extend(Heron.widgets.search.SpatialSearchPanel, {

    /** api: config[name]
     *  ``String``
     *  Name, e.g. for multiple searches combo.
     */
    name: __('Search by Drawing'),

// See also: http://ian01.geog.psu.edu/geoserver_docs/apps/gaz/search.html
    initComponent: function () {

        this.items = [
            this.createTargetLayerCombo(),
            this.createDrawToolPanel(),
            /*            {
             xtype: "spacer"
             },
             {
             xtype: 'checkbox',
             fieldLabel: __('Sketch only'),
             checked: this.sketchOnly,
             listeners: {
             afterrender: function (obj) {
             new Ext.ToolTip({
             target: obj.id,
             html: __('Tooltip for checkbox')
             });
             },
             check: function (c, checked) {
             this.sketchOnly = checked;
             },
             scope: this
             }
             }, */
            this.createStatusPanel(),
            this.createActionButtons()
        ];
        Heron.widgets.search.SearchByDrawPanel.superclass.initComponent.call(this);

        this.addListener("drawcontroladded", this.activateDrawControl, this);

    },

    createActionButtons: function () {
        // Just a Cancel Button for now
        return this.cancelButton = new Ext.Button({
            text: __('Cancel'),
            tooltip: __('Cancel ongoing search'),
            disabled: true,
            handler: function () {
                this.fireEvent('searchcanceled', this);
            },
            scope: this
        });
    },

    /** api: method[onDrawingComplete]
     *  Called when feature drawn selected.
     */
    onDrawingComplete: function (searchPanel, selectionLayer) {
        this.searchFromSketch();
    },

    onFeatureDrawn: function () {
        this.fireEvent('drawingcomplete', this, this.selectionLayer);
    },

    /** api: method[onPanelRendered]
     *  Called when Panel has been rendered.
     */
    onPanelRendered: function () {
        this.updateStatusPanel(__('Select a drawing tool and draw to search immediately'));

        // Select the first layer
        this.targetLayer = this.targetLayerCombo.selectedLayer;
        if (this.targetLayer) {
            this.fireEvent('targetlayerselected', this.targetLayer);
        }
    },

    /** api: method[onParentShow]
     *  Called when parent Panel is shown in Container.
     */
    onParentShow: function () {
        this.activateDrawControl();
    },

    /** api: method[onParentHide]
     *  Called when parent Panel is hidden in Container.
     */
    onParentHide: function () {
        this.deactivateDrawControl();
    },

    onSearchCanceled: function (searchPanel) {
        Heron.widgets.search.SearchByFeaturePanel.superclass.onSearchCanceled.call(this);
        this.cancelButton.disable();
        if (this.selectionLayer) {
            this.selectionLayer.removeAllFeatures();
        }
    },

    onSearchComplete: function (searchPanel, result) {
        Heron.widgets.search.SearchByFeaturePanel.superclass.onSearchComplete.call(this, searchPanel, result);
        this.cancelButton.disable();
    },

    /** api: method[searchFromFeatures]
     *
     *  Issue spatial search via WFS.
     */
    searchFromSketch: function () {
        // Protect against too many features returned in query (see wfs config options)
        var selectionLayer = this.selectionLayer;
        var geometry = selectionLayer.features[0].geometry;
        if (!this.search([geometry], {projection: selectionLayer.projection, units: selectionLayer.units, targetLayer: this.targetLayer})) {
        }
        this.sketch = true;
        this.cancelButton.enable();
    }

});

/** api: xtype = hr_searchbydrawpanel */
Ext.reg('hr_searchbydrawpanel', Heron.widgets.search.SearchByDrawPanel);
