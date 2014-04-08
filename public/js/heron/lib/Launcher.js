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

/**
 * Autolaunches Heron as app.
 * To prevent this and control explicitly include NoLaunch.js before App.js
 */
Ext.onReady(function() {
    if (typeof console === 'undefined') {
   		console = { log:function (s) {}}
   	}
   	console.log('Starting Heron v' + Heron.globals.version  + ' - Proxy URL="' + OpenLayers.ProxyHost + '" - Service URL="' + Heron.globals.serviceUrl + '"');

	if (!Heron.noAutoLaunch) {
		Heron.App.create();
		Heron.App.show();
	}
}, Heron.App);
