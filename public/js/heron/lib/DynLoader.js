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
(function() {

  var singleFile = (typeof Heron == "object" && Heron.singleFile);

  /**
   * Relative path of this script.
   */
  var scriptName = (!singleFile) ? "lib/DynLoader.js" : "Heron.js";
  var jsFiles = window.heron;
  window.heron = {
    /**
     * Method: _getScriptLocation
     * Return the path to this script. This is also implemented in
     * OpenLayers/SingleFile.js
     *
     * Returns:
     * {String} Path to this script
     */
    _getScriptLocation: (function() {
      var r = new RegExp("(^|(.*?\\/))(" + scriptName + ")(\\?|$)"),
          s = document.getElementsByTagName('script'),
          src, m, l = "";
      for (var i = 0, len = s.length; i < len; i++) {
        src = s[i].getAttribute('src');
        if (src) {
          var m = src.match(r);
          if (m) {
            l = m[1];
            break;
          }
        }
      }
      return (function() {
        return l;
      });
    })()
  };

  /**
   * heron.singleFile is a flag indicating this file is being included
   * in a Single File Library build of the heron Library.
   *
   * When we are *not* part of a SFL build we dynamically include the
   * OpenLayers library code.
   *
   * When we *are* part of a SFL build we do not dynamically include the
   * heron library code as it will be appended at the end of this file.
   */
  if (!singleFile) {
    if (!jsFiles) {
      jsFiles = [
        "i18n.js",
        "override-openlayers.js",
        "override-ext.js",
        "override-geoext.js",
        "gxp-compat.js",
        "App.js",
        "Launcher.js",
        "Utils.js",
        "data/OpenLS_XLSReader.js",
        "data/DataExporter.js",
        "data/MapContext.js",
        "ext.ux/Exporter-all.js",
        "ext.ux/WebToolKit-base64.js",
        "widgets/GridCellRenderer.js",
        "widgets/LayerNodeMenuItem.js",
        "widgets/LayerNodeContextMenu.js",
        "widgets/ActiveLayersPanel.js",
        "widgets/ActiveThemesPanel.js",
        "widgets/LayerCombo.js",
        "widgets/BaseLayerCombo.js",
        "widgets/CascadingTreeNode.js",
        "widgets/CapabilitiesTreePanel.js",
        "widgets/search/CoordSearchPanel.js",
        "widgets/search/FeatureInfoPanel.js",
        "widgets/search/FeatureInfoPopup.js",
        "widgets/XMLTreePanel.js",
        "widgets/HTMLPanel.js",
        "widgets/BookmarksPanel.js",
        "widgets/LayerTreePanel.js",
        "widgets/LayerLegendPanel.js",
        "widgets/LoadingPanel.js",
        "widgets/StyleFeature.js",
        "widgets/MapPanel.js",
        "widgets/MenuPanel.js",
        "widgets/MultiLayerNode.js",
        "widgets/search/GeocoderCombo.js",
        "widgets/search/OpenLSSearchCombo.js",
        "widgets/search/NominatimSearchCombo.js",
        "widgets/PrintPreviewWindow.js",
        "widgets/search/FeaturePanel.js",
        "widgets/search/SearchCenterPanel.js",
        "widgets/search/MultiSearchCenterPanel.js",
        "widgets/search/SpatialSearchPanel.js",
        "widgets/search/SearchByDrawPanel.js",
        "widgets/search/SearchByFeaturePanel.js",
        "widgets/search/FormSearchPanel.js",
        "widgets/search/GXP_QueryPanel.js",
        "widgets/ToolbarBuilder.js",
        "widgets/ScaleSelectorCombo.js"
      ];
    }

    // use "parser-inserted scripts" for guaranteed execution order
    // http://hsivonen.iki.fi/script-execution/
    var scriptTags = new Array(jsFiles.length);
    var host = heron._getScriptLocation() + "lib/";
    for (var i = 0, len = jsFiles.length; i < len; i++) {
      scriptTags[i] = "<script src='" + host + jsFiles[i] +
          "'></script>";
    }
    if (scriptTags.length > 0) {
      document.write(scriptTags.join(""));
    }
  }
})();

