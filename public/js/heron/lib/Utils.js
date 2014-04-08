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
Ext.namespace("Heron.Utils");
Ext.namespace("Heron.globals");

/** api: (define)
 *  module = Heron
 *  class = Utils
 */

/** api: constructor
 *  .. class:: Utils()
 *
 *  Various utility functions.
 */
Heron.Utils =
        (function () { // Creates and runs anonymous function, its result is assigned to Singleton

            // Any variable inside function becomes "private"

            /** Browser windows in use. */
            var browserWindows = new Array();

            /** Placeholder, should become a "loading.." window */
            var openMsgURL = 'http://extjs.cachefly.net/ext-3.4.0/resources/images/default/s.gif';
            /** Private functions. */


            /**
             * This is a definition of our Singleton, it is also private, but we will share it below
             **/
            var instance = {

                /**
                 * Function: factory method, create an OpenLayers Object from argument array
                 *
                 * Arguments: array, first element is class name, other elements are args specific to class
                 *
                 * Returns:
                 * {Object} The OpenLayers class instance
                 */
                createOLObject: function (argArr) {
                    // Create class from string name, e.g. "OpenLayers.Layer.WMS"
                    var clazz = eval(argArr[0]);

                    // Extract the arguments for the class' constructor
                    var args = [].slice.call(argArr, 1);

                    // Create the Class
                    function F() {
                    }

                    F.prototype = clazz.prototype;

                    // Create instance (function) of class and call constructor
                    var instance = new F();
                    instance.initialize.apply(instance, args);
                    return instance;
                },

                /**
                 * Function: getScriptLocation
                 *
                 * Returns:
                 * {String} The URL string for the directory where of the Heron main script
                 */
                getScriptLocation: function () {
                    if (!Heron.globals.scriptLoc) {
                        Heron.globals.scriptLoc = '';
                        var scriptName = (!Heron.singleFile) ? "lib/DynLoader.js" : "script/Heron.js";
                        var r = new RegExp("(^|(.*?\\/))(" + scriptName + ")(\\?|$)"),
                                scripts = document.getElementsByTagName('script'),
                                src = "";
                        for (var i = 0, len = scripts.length; i < len; i++) {
                            src = scripts[i].getAttribute('src');
                            if (src) {
                                var m = src.match(r);
                                if (m) {
                                    Heron.globals.scriptLoc = m[1];
                                    break;
                                }
                            }
                        }
                    }
                    return Heron.globals.scriptLoc;
                },

                /**
                 * Function: getImagesLocation
                 *
                 * Returns:
                 * {String} The fully formatted image location string
                 */
                getImagesLocation: function () {
                    return Heron.globals.imagePath || (Heron.Utils.getScriptLocation() + "resources/images/");
                },

                /**
                 * Function: getImageLocation
                 *
                 * Returns:
                 * {String} The fully formatted location string for a specified image
                 */
                getImageLocation: function (image) {
                    return Heron.Utils.getImagesLocation() + image;
                },

                /**
                 * Function: rand
                 *
                 * Returns:
                 * {Integer} Random integer between and including min and max
                 */
                rand: function (min, max) {
                    return Math.floor(Math.random() * ((max - min) + 1) + min);
                },

                /**
                 * Function: randArrayElm
                 *
                 * Returns:
                 * {Integer} Random array element from array arr.
                 */
                randArrayElm: function (arr) {
                    return arr[Heron.Utils.rand(0, arr.length - 1)];
                },

                /** Format a text string of XML into indented and optionally HTML-escaped text. */
                formatXml: function (xml, htmlEscape) {
                    var reg = /(>)(<)(\/*)/g;
                    var wsexp = / *(.*) +\n/g;
                    var contexp = /(<.+>)(.+\n)/g;
                    xml = xml.replace(reg, '$1\n$2$3').replace(wsexp, '$1\n').replace(contexp, '$1\n$2');
                    var pad = 0;
                    var formatted = '';
                    var lines = xml.split('\n');
                    var indent = 0;
                    var lastType = 'other';
                    // 4 types of tags - single, closing, opening, other (text, doctype, comment) - 4*4 = 16 transitions
                    var transitions = {
                        'single->single': 0,
                        'single->closing': -1,
                        'single->opening': 0,
                        'single->other': 0,
                        'closing->single': 0,
                        'closing->closing': -1,
                        'closing->opening': 0,
                        'closing->other': 0,
                        'opening->single': 1,
                        'opening->closing': 0,
                        'opening->opening': 1,
                        'opening->other': 1,
                        'other->single': 0,
                        'other->closing': -1,
                        'other->opening': 0,
                        'other->other': 0
                    };

                    for (var i = 0; i < lines.length; i++) {
                        var ln = lines[i];
                        var single = Boolean(ln.match(/<.+\/>/)); // is this line a single tag? ex. <br />
                        var closing = Boolean(ln.match(/<\/.+>/)); // is this a closing tag? ex. </a>
                        var opening = Boolean(ln.match(/<[^!].*>/)); // is this even a tag (that's not <!something>)

                        var type = single ? 'single' : closing ? 'closing' : opening ? 'opening' : 'other';
                        var fromTo = lastType + '->' + type;
                        lastType = type;
                        var padding = '';

                        indent += transitions[fromTo];
                        for (var j = 0; j < indent; j++) {
                            padding += '    ';
                        }

                        if (htmlEscape) {
                            ln = ln.replace('<', '&lt;');
                            ln = ln.replace('>', '&gt;');
                        }
                        formatted += padding + ln + '\n';
                    }

                    return formatted;
                },


// --------------------------------------------------------------------------------------
// Opens url in a (new) explorer window
// Initial implementation by: Wolfram Winter (Deutsche Bahn)
//
// --------------------------------------------------------------------------------------
                // winName       -  Name of the explorer window, which will be opened
                //                  Could be used for later reference
                // bReopen          true/false  if the explorer window 'winName' is open, it will be
                //                              closed and opened again - otherwise the window will be
                //                              unchanged
                // theURL        -  the URL, that will be opened
                // hasMenubar    -  true/false  Flag - showing the menue bar
                // hasToolbar    -  true/false  Flag - showing the standard buttons previous/next
                // hasAddressbar -  true/false  Flag - showing the adress bar
                // hasStatusbar  -  true/false  Flag - showing the status bar
                // hasScrollbars -  true/false  Flag - showing the scrollbars of the explorer window
                // isResizable   -  true/false  Flag - the user could change the explorer window size
                // hasPos        -  = 0 - explorer window will be set by the system
                //                  < 0 - explorer window will be set left top
                //                  > 0 - explorer window will be set at the 'xPos', 'yPos'
                // xPos          -  x-position of the explorer window (horizontal position - left)
                // yPos          -  y-position of the explorer window (vertical position - top)
                // hasSize       -  = 0 - explorer window will be set by the system without any width and height
                //                  <>0 - explorer window will be set with width 'wsize' and height 'hsize'
                // wSize         -  Width of the explorer window
                // hSize         -  Height of the explorer window

// --------------------------------------------------------------------------------------
                openBrowserWindow: function (winName, bReopen, theURL, hasMenubar, hasToolbar, hasAddressbar, hasStatusbar, hasScrollbars, isResizable, hasPos, xPos, yPos, hasSize, wSize, hSize) {

                    var x, y;
                    var options = "";
                    var pwin = null;

                    if (hasMenubar) {
                        options += "menubar=yes";
                    } else {
                        options += "menubar=no";
                    }

                    if (hasToolbar) {
                        options += ",toolbar=yes";
                    } else {
                        options += ",toolbar=no";
                    }

                    if (hasAddressbar) {
                        options += ",location=yes";
                    } else {
                        options += ",location=no";
                    }

                    if (hasStatusbar) {
                        options += ",status=yes";
                    } else {
                        options += ",status=no";
                    }

                    if (hasScrollbars) {
                        options += ",scrollbars=yes";
                    } else {
                        options += ",scrollbars=no";
                    }

                    if (isResizable) {
                        options += ",resizable=yes";
                    } else {
                        options += ",resizable=no";
                    }

                    if (!hasSize) {
                        wSize = 640;
                        hSize = 480;
                    }
                    options += ",width=" + wSize + ",innerWidth=" + wSize;
                    options += ",height=" + hSize + ",innerHeight=" + hSize;


                    if (!hasPos) {
                        xPos = (screen.width - 700) / 2;         // explorer window position - slight left
                        yPos = 75;                           // explorer window position - top
                    }

                    options += ",left=" + xPos + ",top=" + yPos;

//					// --- Test status of explorer window ---
//					if (!browserWindows[winName] || browserWindows[winName].closed) {
//						// pwin = window.open("", winName, options);
//						pwin = window.open(openMsgURL, winName, options);
//						browserWindows[winName] = pwin;
//					} else {
//						pwin = browserWindows[winName];
//					}

                    // --- Open explorer window ---
                    if (bReopen) {
                        // Close explorer window and open it again
                        // pwin.close();
                        browserWindows[winName] = window.open(theURL, winName, options);
                    } else {
                        if (!browserWindows[winName] || browserWindows[winName].closed) {
                            browserWindows[winName] = window.open(theURL, winName, options);
                        } else {
                            // Open url in existing explorer window
                            browserWindows[winName].location.href = theURL;
                        }
                    }
                    browserWindows[winName].focus();
                }
            };


// Simple magic - global variable Singleton transforms into our singleton!
            return(instance);

        })();

Ext.ns('Ext.ux.form'); // set up Ext.ux.form namespace


/**
 * @class Ext.ux.form.Spacer
 * @extends Ext.BoxComponent
 * Utility spacer class.
 * From: http://www.sencha.com/forum/showthread.php?31989-Ext.ux.form.Spacer
 * @constructor
 * @param {Number} height (optional) Spacer height in pixels (defaults to 22).
 */
Ext.ux.form.Spacer = Ext.extend(Ext.BoxComponent, {
    height: 12,
    autoEl: 'div' // thanks @jack =)
});
Ext.reg('spacer', Ext.ux.form.Spacer);
