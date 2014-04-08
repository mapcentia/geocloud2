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
Ext.namespace("Heron");

/** Define global variables, may be overridden. */
Ext.namespace("Heron.globals");

/** REST Services specific to Heron. */
Heron.globals = {
	serviceUrl: '/cgi-bin/heron.cgi',
    version: '1.0.1',
	imagePath: undefined
};

try {
	// Define here for now as this file is always included but we need a better way
	Proj4js.defs["EPSG:28992"] = "+proj=sterea +lat_0=52.15616055555555 +lon_0=5.38763888888889 +k=0.999908 +x_0=155000 +y_0=463000 +ellps=bessel +units=m +towgs84=565.2369,50.0087,465.658,-0.406857330322398,0.350732676542563,-1.8703473836068,4.0812 +no_defs";
} catch(err) {
	// ignore
}

/** api: (define)
 *  module = Heron
 *  class = App
 */

/** api: constructor
 *  .. class:: App()
 *
 *  The main entry of Heron, all begins here. The entire application is created from the configuration ("Heron.layout") file.
 *  Normally there is no need to override this class. See the Launcher.js how this class is used to autolaunch
 *  a Heron app. This is the defeault behaviour.
 *
 * .. code-block:: javascript
 *
 *      // Creating and launching a Heron app is a 2-step process
 *
 *      // Create the components from the Heron.layout config
 *   	Heron.App.create();
 *
 *      // Make components visible
 *   	Heron.App.show();
 *
 */
Ext.namespace("Heron.App");
Heron.App = function() {

	return {
		create : function() {

			Ext.QuickTips.init();

			if (Heron.layout.renderTo || Heron.layout.xtype == 'window') {
				// Render topComponent into a page div element or floating window
				Heron.App.topComponent = Ext.create(Heron.layout);
			} else {
				// Default: render top component into an ExtJS ViewPort (full screen)
				Heron.App.topComponent = new Ext.Viewport({
					id	:"hr-topComponent",
					layout: "fit",
					hideBorders: true,

					// This creates the entire layout from the config !
					items: [Heron.layout]
				});
			}
		},

		show : function() {
			Heron.App.topComponent.show();
		},

		getMap : function() {
			return Heron.App.map;
		},

		setMap : function(aMap) {
			Heron.App.map = aMap;
		},

		getMapPanel : function() {
			return Heron.App.mapPanel;
		},

		setMapPanel : function(aMapPanel) {
			Heron.App.mapPanel = aMapPanel;
		}
	};
}();
