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

/** api: (define)
 *  module = Heron.widgets
 *  class = GridCellRenderer
 */


/** api: example
 *
 *  .. code-block:: javascript
 *
 *         {
 *			 xtype: 'hr_featureinfopanel',
 *			 border: true,
 *			   .
 *			   .
 *		   hropts: {
 *			   infoFormat: 'application/vnd.ogc.gml',
 *			   displayPanels: ['Grid', 'XML'],
 *			   exportFormats: ['CSV', 'XLS'],
 *			   maxFeatures: 10,
 *			   gridCellRenderers: [
 *			  {
 *				featureType: 'contracts',
 *				attrName: 'contractId'
 *				renderer: {
 *				  fn : Heron.widgets.GridCellRenderer.directLink,
 *				  options : {
 *					url: 'http://resources.com/contracts/show?id={companyId}.{contractId}'
 *					target: '_new'
 *				  }
 *			  },
 *			  {
 *				featureType: 'tst-plan',
 *				attrName : 'planId',
 *				renderer :  {
 *				  fn : Heron.widgets.GridCellRenderer.browserPopupLink,
 *				  options : {
 *					url: 'http://resources.com/plans/show?id={planId}',
 *					winName: 'demoWin',				// optional - default: 'herongridcellpopup'
 *					bReopen: false,					// optional - default: false
 *					hasMenubar: true,				// optional - default: false
 *					hasToolbar: true,				// optional - default: false
 *					hasAddressbar: true,			// optional - default: false
 *					hasStatusbar: true,				// optional - default: false
 *					hasScrollbars: true,			// optional - default: false
 *					isResizable: true,				// optional - default: false
 *					hasPos: true,					// optional - default: false
 *					xPos: 10,						// optional - default: 0
 *					yPos: 20,						// optional - default: 0
 *					hasSize: true,					// optional - default: false
 *					wSize: 400,						// optional - default: 200
 *					hSize: 800,						// optional - default: 100
 *					attrPreTxt: 'Plan: '			// optional - default: ''
 *				}
 *			   }
 *			 ]
 *			 }
 *		 }
 *
 */

Ext.namespace("Heron.widgets");

/** api: constructor
 *  .. class:: GridCellRenderer()
 *
 *  Functions for custom rendering of features within Grids like GetFeatureInfo.
 *
 * Global Singleton class.
 * See http://my.opera.com/Aux/blog/2010/07/22/proper-singleton-in-javascript
 *
 */
Heron.widgets.GridCellRenderer =

        (function () { // Creates and runs anonymous function, its result is assigned to Singleton

            // Any variable inside function becomes "private"

            /** Private functions. */


            /** This is a definition of our Singleton, it is also private, but we will share it below */
            var instance = {
                /** Substitute actual values from record in template {attrName}'s in given template. */
                substituteAttrValues: function (template, options, record) {
                    // One-time parse out attr names (should use RegExp() but this is quick for now)
                    if (!options.attrNames) {
                        options.attrNames = new Array();
                        var inAttrName = false;
                        var attrName = '';
                        for (var i = 0; i < template.length; i++) {
                            var s = template.charAt(i);
                            if (s == '{') {
                                inAttrName = true;
                                attrName = '';
                            } else if (s == '}') {
                                options.attrNames.push(attrName)
                                inAttrName = false;
                            } else if (inAttrName) {
                                attrName += s;
                            }
                        }
                    }
                    var result = template;
                    for (var j = 0; j < options.attrNames.length; j++) {
                        var name = options.attrNames[j];
                        var value = record.data[name];
                        if (!value) {
                            // Default: remove at least when empty value
                            value = '';
                        }
                        var valueTemplate = '{' + name + '}';

                        result = result.replace(valueTemplate, value);
                    }
                    return result;

                },

                directLink: function (value, metaData, record, rowIndex, colIndex, store) {
                    if (!this.options) {
                        return value;
                    }

                    var options = this.options;

                    var url = options.url;
                    if (!url) {
                        return value;
                    }

                    url = Heron.widgets.GridCellRenderer.substituteAttrValues(url, options, record);

                    var result = '<a href="' + url + '" target="{target}">' + value + '</a>';
                    var target = options.target ? options.target : '_new';
                    var targetTemplate = '{target}';

                    return result.replace(targetTemplate, target);
                },

                /**
                 * Render with a link to a browser popup window "Explorer Window".
                 * @param value - the attribute (cell) value
                 */
                browserPopupLink: function (value, metaData, record, rowIndex, colIndex, store) {
                    if (!this.options) {
                        return value;
                    }

                    var options = this.options;

                    var templateURL = options.url;
                    if (!templateURL) {
                        return value;
                    }

                    var BrowserParam = '\'' + (options.winName ? options.winName : 'herongridcellpopup') + '\''
                                    + ', ' + (options.bReopen ? options.bReopen : false)
                                    + ', \'' + (Heron.widgets.GridCellRenderer.substituteAttrValues(templateURL, options, record)) + '\''
                                    + ', ' + (options.hasMenubar ? options.hasMenubar : false)
                                    + ', ' + (options.hasToolbar ? options.hasToolbar : false)
                                    + ', ' + (options.hasAddressbar ? options.hasAddressbar : false)
                                    + ', ' + (options.hasStatusbar ? options.hasStatusbar : false)
                                    + ', ' + (options.hasScrollbars ? options.hasScrollbars : false)
                                    + ', ' + (options.isResizable ? options.isResizable : false)
                                    + ', ' + (options.hasPos ? options.hasPos : false)
                                    + ', ' + (options.xPos ? options.xPos : 0)
                                    + ', ' + (options.yPos ? options.yPos : 0)
                                    + ', ' + (options.hasSize ? options.hasSize : false)
                                    + ', ' + (options.wSize ? options.wSize : 200)
                                    + ', ' + (options.hSize ? options.hSize : 100)
                            ;

                    // <a href="#" onclick="Heron.Utils.openBrowserWindow('demoWin', false, 'http://en.wikipedia.org/wiki/Germany', true, true, true, true, true, true, true, 10, 20, true, 600, 800); return false">Germany</a>
                    return (options.attrPreTxt ? options.attrPreTxt : "") + '<a href="#" onclick="' + 'Heron.Utils.openBrowserWindow(' + BrowserParam + '); return false">' + value + '</a>';
                },

                /**
                 * Custom rendering for any template.
                 * @param value - the attribute (cell) value
                 */
                valueSubstitutor: function (value, metaData, record, rowIndex, colIndex, store) {
                    if (!this.options) {
                        return value;
                    }

                    var options = this.options;

                    var template = options.template;
                    if (!template) {
                        return value;
                    }

                    return Heron.widgets.GridCellRenderer.substituteAttrValues(template, options, record);
                }

            };

            // Simple magic - global variable Singleton transforms into our singleton!
            return(instance);

        })();

