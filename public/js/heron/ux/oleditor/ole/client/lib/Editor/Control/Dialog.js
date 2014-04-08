/**
 * @copyright  2011 geOps
 * @license    https://github.com/geops/ole/blob/master/license.txt
 * @link       https://github.com/geops/ole
 */

/**
 * Class: OpenLayers.Editor.Control.Dialog
 * ...
 *
 * Inherits from:
 *  - <OpenLayers.Control>
 */
OpenLayers.Editor.Control.Dialog = OpenLayers.Class(OpenLayers.Control, {

    dialogDiv: null,

    buttonClass: null,

    inputTextClass: null,

    modal: true,

    initialize: function (options) {

        OpenLayers.Control.prototype.initialize.apply(this, [options]);

    },

    show: function (options) {

        var element, cancelButton, saveButton, okButton;

        if (OpenLayers.Util.indexOf(this.map.viewPortDiv.childNodes, this.dialogDiv) > -1) {
            this.map.viewPortDiv.removeChild(this.dialogDiv);
        }

        if (!options) {
            options = {};
        }

        this.dialogDiv = document.createElement('div');
        OpenLayers.Element.addClass(this.dialogDiv, 'oleDialog');

        if (options.toolbox) {
            OpenLayers.Element.addClass(this.dialogDiv, 'oleDialogToolbar');
        } else {
            OpenLayers.Element.addClass(this.div, 'oleFadeMap');
        }

        if (options.title) {
            element = document.createElement('h3');
            element.innerHTML = options.title;
            this.dialogDiv.appendChild(element);
        }

        if (typeof options.content === 'string') {
            element = document.createElement('div');
            element.innerHTML = options.content;
            this.dialogDiv.appendChild(element);
        } else if (OpenLayers.Util.isElement(options.content)) {
            this.dialogDiv.appendChild(options.content);
        }

        if (options.save) {
            cancelButton = this.getButton(OpenLayers.i18n('oleDialogCancelButton'));
            this.dialogDiv.appendChild(cancelButton);
            saveButton = this.getButton(OpenLayers.i18n(options.saveButtonText ? options.saveButtonText : 'oleDialogSaveButton'));
            this.dialogDiv.appendChild(saveButton);
            OpenLayers.Event.observe(cancelButton, 'click', OpenLayers.Function.bind(this.hide, this));

            // In some cases the Dialog client will hide the dialog div
            if (!options.noHideOnSave) {
                OpenLayers.Event.observe(saveButton, 'click', OpenLayers.Function.bind(this.hide, this));
            }
            OpenLayers.Event.observe(saveButton, 'click', options.save);
            if (options.cancel) {
                OpenLayers.Event.observe(cancelButton, 'click', options.cancel);
            }
        } else if (!options.toolbox) {
            okButton = this.getButton(OpenLayers.i18n('oleDialogOkButton'));
            this.dialogDiv.appendChild(okButton);

            OpenLayers.Event.observe(okButton, 'click', OpenLayers.Function.bind(this.hide, this));
            if (options.close) {
                OpenLayers.Event.observe(okButton, 'click', options.close);
            }
        }

        // Add class to text input elements.
        var inputElements = this.dialogDiv.getElementsByTagName('input');
        for (var i = 0; i < inputElements.length; i++) {
            if (inputElements[i].getAttribute('type') == 'text') {
                OpenLayers.Element.addClass(inputElements[i], this.inputTextClass);
            }
        }

        this.map.viewPortDiv.appendChild(this.dialogDiv);

        OpenLayers.Event.observe(this.div, 'click', this.ignoreEvent);
        OpenLayers.Event.observe(this.div, 'mousedown', this.ignoreEvent);
        OpenLayers.Event.observe(this.div, 'dblclick', this.ignoreEvent);
        OpenLayers.Event.observe(this.dialogDiv, 'mousedown', this.ignoreEvent);
        OpenLayers.Event.observe(this.dialogDiv, 'dblclick', this.ignoreEvent);
    },

    hide: function () {
        // Clients may call hide from different internal states
        // Do some defensive checks here...
        if (!this.dialogDiv) {
            return;
        }

        this.map.viewPortDiv.removeChild(this.dialogDiv);
        OpenLayers.Element.removeClass(this.div, 'oleFadeMap');
        this.dialogDiv = null;
    },

    ignoreEvent: function (event) {
        OpenLayers.Event.stop(event, true);
    },

    /**
     * Instantiates a button
     * @param {string} value Value and text on button
     * @return {!HTMLButtonElement}
     */
    getButton: function (value) {
        var button = document.createElement('input');
        button.value = value;
        button.type = 'button';
        OpenLayers.Element.addClass(button, this.buttonClass);
        /** @type {!HTMLButtonElement} */
        return button;
    },

    CLASS_NAME: 'OpenLayers.Editor.Control.Dialog'
});
