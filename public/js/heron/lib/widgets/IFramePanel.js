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
 * iFrame panel
 *   Adapted from:
 *  http://www.sencha.com/forum/showthread.php?110311-iframePanel
 * @author	Steffen Kamper (original author)
 */

Ext.namespace("Heron.widgets");

/** api: (define)
 *  module = Heron.widgets
 *  class = IFramePanel
 *  base_link = `Ext.Panel <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.Panel>`_
 */

/** api: constructor
 *  .. class:: Heron.widgets.IFramePanel(config)
 *
 *  A panel designed to hold URL content within an IFrame.
 */
Heron.widgets.IFramePanel = Ext.extend(Ext.Panel, {
	name: 'iframe',
	iframe: null,
	src: Ext.isIE && Ext.isSecure ? Ext.SSL_SECURE_URL : 'about:blank',
	maskMessage: __('Loading...'),
	doMask: true,

	// component build
	initComponent: function() {
		this.bodyCfg = {
			tag: 'iframe',
			frameborder: '0',
			src: this.src,
			name: this.name
		};

		Ext.apply(this, {

		});
		Heron.widgets.IFramePanel.superclass.initComponent.apply(this, arguments);

		// apply the addListener patch for 'message:tagging'
		this.addListener = this.on;
	},

	onRender : function() {
		Heron.widgets.IFramePanel.superclass.onRender.apply(this, arguments);
		this.iframe = Ext.isIE ? this.body.dom.contentWindow : window.frames[this.name];
		this.body.dom[Ext.isIE ? 'onreadystatechange' : 'onload'] = this.loadHandler.createDelegate(this);
	},

	loadHandler: function() {
		this.src = this.body.dom.src;
		this.removeMask();
	},

	getIframe: function() {
		return this.iframe;
	},

	getIframeBody: function() {
        var b = this.iframe.document.getElementsByTagName('body');
		if (!Ext.isEmpty(b)){
			return b[0];
		} else {
			return '';
		}
    },

	getUrl: function() {
		return this.body.dom.src;
	},

	setUrl: function(source) {
		this.setMask();
		this.body.dom.src = source;
	},

	resetUrl: function() {
		this.setMask();
		this.body.dom.src = this.src;
	},

	refresh: function() {
		if (!this.isVisible()) {
			return;
		}
		this.setMask();
		this.body.dom.src = this.body.dom.src;
	},

	/** @private */
	setMask: function() {
		if (this.doMask) {
			this.el.mask(this.maskMessage);
		}
	},
	removeMask: function() {
		if (this.doMask) {
			this.el.unmask();
		}
	}
});
Ext.reg('hr_iframePpanel', Heron.widgets.IFramePanel);