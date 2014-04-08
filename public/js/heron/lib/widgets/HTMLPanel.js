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
Ext.namespace("Heron.widgets");

/** api: (define)
 *  module = Heron.widgets
 *  class = HTMLPanel
 *  base_link = `Ext.Panel <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.Panel>`_
 */

/** api: constructor
 *  .. class:: Heron.widgets.HTMLPanel(config)
 *
 *  A panel designed to hold HTML content.
 */
Heron.widgets.HTMLPanel = Ext.extend(Ext.Panel, {

	initComponent : function() {

		Heron.widgets.HTMLPanel.superclass.initComponent.call(this);

		this.addListener('render', function() {
			this.loadMask = new Ext.LoadMask(this.body, {
				msg: __('Loading...')
			})
		});
	}
});

/** api: xtype = hr_htmlpanel */
Ext.reg('hr_htmlpanel', Heron.widgets.HTMLPanel);

