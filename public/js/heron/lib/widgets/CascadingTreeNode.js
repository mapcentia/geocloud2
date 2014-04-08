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

/** api: constructor
 *  .. class:: CascadingTreeNode(config)
 *
 *      A subclass of ``Ext.tree.AsyncTreeNode``. Tree parent node with checkbox
 *      that recursively toggles its childnodes when checked/unchecked.
 *      Can be used to switch on/off on multiple Layers in child ``GeoExt LayerNodes``.
 */
Heron.widgets.CascadingTreeNode = Ext.extend(Ext.tree.AsyncTreeNode, {

    /** api: config[checked]
     * Shows a checkbox when ``true`` (checked) or ``false`` (unchecked) or none if ``undefined``.
     * Default value is ``false``.
     */
    checked: false,

    /** private: method[constructor]
     *  Private constructor override.
     */
    constructor: function () {
        // We always want to show a checkbox, that is the whole purpose of this class!
        if (arguments[0].checked === undefined) {
            arguments[0].checked = this.checked;
        }
        Heron.widgets.CascadingTreeNode.superclass.constructor.apply(this, arguments);
        this.on({
            "append": this.onAppend,
            "remove": this.onRemove,
            "checkchange": this.onCheckChange,
            scope: this
        });

    },

    /** private: method[onAppend]
     *  :param child: ``Ext.tree.TreeNode``
     *  :param checked: ``Boolean``
     *
     *  Handler for child append events: listen to check changes for possibly (un)checking ourselves.
     */
    onAppend: function (panel, self, child) {
        child.on({
            "checkchange": this.onChildCheckChange,
            scope: this
        });

    },

    /** private: method[onRemove]
     *  :param child: ``Ext.tree.TreeNode``
     *  :param checked: ``Boolean``
     *
     *  Handler for child remove events.
     */
    onRemove: function (panel, self, child) {
        child.un({
            "checkchange": this.onChildCheckChange,
            scope: this
        });
    },

    /** private: method[onChildCheckChange]
     *  :param child: ``Ext.tree.TreeNode``
     *  :param checked: ``Boolean``
     *
     *  Handler for child check changes: see if we need to change our own check-state.
     */
    onChildCheckChange: function (child, checked) {
        // Safety check: don't get here multiple times while checking
        if (this.childCheckChange) {
            return;
        }

        // We just count the children and the amount checked.
        // If those numbers are equal we need to be checked, all other cases unchecked.
        var childrenTotal = 0, childrenChecked = 0;
        var self = this;
        this.cascade(function (child) {
            // Don't check ourselves nor any other directory nodes that are not cascaders
            if (child == self || (child.hasChildNodes() && !child.attributes.xtype == 'hr_cascader')) {
                return;
            }

            childrenTotal++;
            if (child.ui.isChecked()) {
                childrenChecked++;
            }
        }, null, null);

        // See if we need to check/uncheck
        this.childCheckChange = true;
        this.ui.toggleCheck(childrenTotal == childrenChecked);
        this.childCheckChange = false;
    },

    /** private: method[onCheckChange]
     *  :param node: ``Ext.tree.TreeNode``
     *  :param checked: ``Boolean``
     *
     *  Handler for checkchange events
     */
    onCheckChange: function (node, checked) {
        node.cascade(function (child) {
            // Don't check ourselves or when a child changes checkstate
            if (child == node || node.childCheckChange) {
                return;
            }

            // Standard check in/off for child tree node
            if (child.ui.rendered) {
                child.ui.toggleCheck(checked);
            } else {
                child.attributes.checked = checked;
            }
        }, null, null);
    }
});

/**
 * NodeType: hr_cascader
 */
Ext.tree.TreePanel.nodeTypes.hr_cascader = Heron.widgets.CascadingTreeNode;