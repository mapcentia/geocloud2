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
 *  class = XMLTreePanel
 *  base_link = `Ext.tree.TreePanel <http://docs.sencha.com/ext-js/3-4/#!/api/Ext.tree.TreePanel>`_
 */


/** api: constructor
 *  .. class:: XMLTreePanel(config)
 *
 *  Specialized TreePanel to display an XML as a tree such with GML.
 */
Heron.widgets.XMLTreePanel = Ext.extend(Ext.tree.TreePanel, {
			/**
			 * Constructor: create and layout Menu from config.
			 **/
			initComponent : function() {
				Ext.apply(this, {
							autoScroll		: true,
							rootVisible : false,
							root		: this.root ? this.root : {
								nodeType: 'async',
								text: 'Ext JS',
								draggable: false,
								id: 'source'
							}
						});

				Heron.widgets.XMLTreePanel.superclass.initComponent.apply(this, arguments);
				// this.addListener("afterrender", this.createXmlTree);
			},

			/**
			 Create an Ext.tree.TreePanel in the passed Element using
			 an XML document from the passed URL, calling the passed
			 callback on completion.
			 @param el {String/Element/HtmlElement} The tree's container.
			 @param url {String} The URL from which to read the XML
			 @param callback {function:tree.render} The function to call on completion,
			 defaults to rendering the tree.
			 */
			xmlTreeFromUrl: function(url) {
				// var url = 'http://local.kademo.nl/gs2/wfs?request=GetFeature&typeName=kad:lki_vlakken&maxFeatures=10&version=1.0.0';

				var self = this;
				Ext.Ajax.request({
							url : url,
							method: 'GET',
							params :null,
							success: function (result, request) {
								self.xmlTreeFromDoc(self, result.responseXML);
							},
							failure: function (result, request) {
								alert('error in ajax request');
							}
						});
			},

			xmlTreeFromText: function(self, text) {
				var doc = new OpenLayers.Format.XML().read(text);
				self.xmlTreeFromDoc(self, doc);
				return doc;
			},

			xmlTreeFromDoc: function(self, doc) {
				self.setRootNode(self.treeNodeFromXml(self, doc.documentElement || doc));
			},

			/**
			 Create a TreeNode from an XML node
			 */
			treeNodeFromXml: function (self, XmlEl) {
				//	Text is nodeValue to text node, otherwise it's the tag name
				var t = ((XmlEl.nodeType == 3) ? XmlEl.nodeValue : XmlEl.tagName);

				//	No text, no node.
				if (t.replace(/\s/g, '').length == 0) {
					return null;
				}
				var result = new Ext.tree.TreeNode({
							text : t
						});

				//	For Elements, process attributes and children
				var xmlns = 'xmlns', xsi = 'xsi';

				if (XmlEl.nodeType == 1) {
					Ext.each(XmlEl.attributes, function(a) {
						var nodeName = a.nodeName;
						if (!(XmlEl.parentNode.nodeType == 9 && (nodeName.substring(0, xmlns.length) === xmlns || nodeName.substring(0, xsi.length) === xsi))) {


							var c = new Ext.tree.TreeNode({
										text: a.nodeName
									});
							c.appendChild(new Ext.tree.TreeNode({
										text: a.nodeValue
									}));
							result.appendChild(c);
						}
					});
					Ext.each(XmlEl.childNodes, function(el) {
						//		Only process Elements and TextNodes
						if ((el.nodeType == 1) || (el.nodeType == 3)) {
							var c = self.treeNodeFromXml(self, el);
							if (c) {
								result.appendChild(c);
							}
						}
					});
				}
				return result;
			}
		});

/** api: xtype = hr_xmltreepanel */
Ext.reg('hr_xmltreepanel', Heron.widgets.XMLTreePanel);

