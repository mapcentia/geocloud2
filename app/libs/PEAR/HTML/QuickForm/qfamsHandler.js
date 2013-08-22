/**
 * JavaScript functions to handle standard behaviors of a QuickForm advmultiselect element
 *
 * @category   HTML
 * @package    HTML_QuickForm_advmultiselect
 * @author     Laurent Laville <pear@laurent-laville.org>
 * @copyright  2007 Laurent Laville
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    CVS: $Id: qfamsHandler.js,v 1.1 2009/03/26 18:56:27 mhoegh Exp $
 * @since      File available since Release 1.3.0
 */

/**
 * - qfamsInit -
 *
 * initialize onclick event handler for all checkbox element
 * of a QuickForm advmultiselect element with single select box.
 *
 * @return     void
 * @public
 * @since      1.3.0
 */
function qfamsInit()
{
    if (window.qfamsName) {
        for (var e = 0; e < window.qfamsName.length; e++) {
            var div    = document.getElementById('qfams_' + window.qfamsName[e]);
            var inputs = div.getElementsByTagName('input');
            for (var i = 0; i < inputs.length; i++) {
                inputs[i].onclick = qfamsUpdateLiveCounter;
            }
        }
    }
}

/**
 * - qfamsUpdateCounter -
 *
 * text tools to replace all childs of 'c' element by a new text node of 'v' value
 *
 * @param      dom element   c    html element; <span> is best use in most case
 * @param      string        v    new counter value
 *
 * @return     void
 * @public
 * @since      1.3.0
 */
function qfamsUpdateCounter(c, v)
{
    if (c != null) {
        // remove all previous child nodes of 'c' element
        if (c.childNodes) {
            for (var i = 0; i < c.childNodes.length; i++) {
                c.removeChild(c.childNodes[i]);
            }
        }
        // add new text value 'v'
        var nodeText = document.createTextNode(v);
        c.appendChild(nodeText);
    }
}

/**
 * - qfamsUpdateLiveCounter -
 *
 * standard onclick event handler to dynamic change value of counter
 * that display current selection
 *
 * @return     void
 * @private
 * @since      1.3.0
 */
function qfamsUpdateLiveCounter()
{
    var lbl = this.parentNode;
    var selectedCount = 0;

    // Find all the checkboxes...
    var div   = lbl.parentNode;
    var inputs = div.getElementsByTagName('input');
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].checked == 1) {
            selectedCount++;
        }
    }
    var e = div.id;
    var qfamsName = e.substring(e.indexOf('_', 0) + 1, e.length);
    // updates item count
    var span = document.getElementById(qfamsName + '_selected');
    qfamsUpdateCounter(span, selectedCount + '/' + inputs.length);
}

/**
 * - qfamsEditSelection -
 *
 * in single select box mode, edit current selection and update live counter
 *
 * @param      string        qfamsName      QuickForm advmultiselect element name
 * @param      integer       selectMode     Selection mode (0 = uncheck, 1 = check, 2 = toggle)
 *
 * @return     void
 * @public
 * @since      1.3.0
 */
function qfamsEditSelection(qfamsName, selectMode)
{
    if (selectMode !== 0 && selectMode !== 1 && selectMode !== 2) {
        return;
    }
    var selectedCount = 0;

    // Find all the checkboxes...
    var fruit  = document.getElementById('qfams_' + qfamsName);
    var inputs = fruit.getElementsByTagName('input');

    // Loop through all checkboxes (input element)
    for (var i = 0; i < inputs.length; i++) {
        if (selectMode == 2) {
            if (inputs[i].checked == 0) {
                inputs[i].checked = 1;
            } else if (inputs[i].checked == 1) {
                inputs[i].checked = 0;
            }
        } else {
            inputs[i].checked = selectMode;
        }
        if (inputs[i].checked == 1) {
            selectedCount++;
        }
    }

    // updates selected item count
    var span = document.getElementById(qfamsName + '_selected');
    qfamsUpdateCounter(span, selectedCount + '/' + inputs.length);
}

/**
 * - qfamsMoveSelection -
 *
 * in double select box mode, move current selection and update live counter
 *
 * @param      string        qfamsName      QuickForm advmultiselect element name
 * @param      dom element   selectLeft     Data source list
 * @param      dom element   selectRight    Target data list
 * @param      dom element   selectHidden   Full data source (selected, unselected)
 *                                          private usage
 * @param      string        action         Action name (add, remove, all, none, toggle)
 * @param      string        arrange        Sort option (none, asc, desc)
 *
 * @return     void
 * @public
 * @since      1.3.0
 */
function qfamsMoveSelection(qfamsName, selectLeft, selectRight, selectHidden, action, arrange)
{
    if (action == 'add' || action == 'all' || action == 'toggle') {
        var source = selectLeft;
        var target = selectRight;
    } else {
        var source = selectRight;
        var target = selectLeft;
    }
    // Don't do anything if nothing selected. Otherwise we throw javascript errors.
    if (source.selectedIndex == -1 && (action == 'add' || action == 'remove')) {
        return;
    }

    var maxTo = target.length;

    // Add items to the 'TO' list.
    for (var i = 0; i < source.length; i++) {
        if (action == 'all' || action == 'none' || action == 'toggle' || source.options[i].selected == true ) {
            target.options[target.length]= new Option(source.options[i].text, source.options[i].value);
        }
    }

    // Remove items from the 'FROM' list.
    for (var i = (source.length - 1); i >= 0; i--){
        if (action == 'all' || action == 'none' || action == 'toggle' || source.options[i].selected == true) {
            source.options[i] = null;
        }
    }

    // Add items to the 'FROM' list for toggle function
    if (action == 'toggle') {
        for (var i = 0; i < maxTo; i++) {
            source.options[source.length]= new Option(target.options[i].text, target.options[i].value);
        }
        for (var i = (maxTo - 1); i >= 0; i--) {
            target.options[i] = null;
        }
    }

    // updates unselected item count
    var c = document.getElementById(qfamsName + '_unselected');
    var s = document.getElementById('__' + qfamsName);
    qfamsUpdateCounter(c, s.length);

    // updates selected item count
    var c = document.getElementById(qfamsName + '_selected');
    var s = document.getElementById('_' + qfamsName);
    qfamsUpdateCounter(c, s.length);

    // Sort list if required
    if (arrange !== 'none') {
        qfamsSortList(target, qfamsCompareText, arrange);
    }

    // Set the appropriate items as 'selected in the hidden select.
    // These are the values that will actually be posted with the form.
    qfamsUpdateHidden(selectHidden, selectRight);
}

/**
 * - qfamsSortList -
 *
 * sort selection list if option is given in HTML_QuickForm_advmultiselect class constructor
 *
 * @param      dom element   list           Selection data list
 * @param      prototype     compareFunction to sort each element of a list
 * @param      string        arrange        Sort option (none, asc, desc)
 *
 * @return     void
 * @private
 * @since      1.3.0
 */
function qfamsSortList(list, compareFunction, arrange)
{
    var options = new Array (list.options.length);
    for (var i = 0; i < options.length; i++) {
        options[i] = new Option (
            list.options[i].text,
            list.options[i].value,
            list.options[i].defaultSelected,
            list.options[i].selected
        );
    }
    options.sort(compareFunction);
    if (arrange == 'desc') {
        options.reverse();
    }
    list.options.length = 0;
    for (var i = 0; i < options.length; i++) {
        list.options[i] = options[i];
    }
}

/**
 * - qfamsCompareText -
 *
 * callback function to sort each element of two lists A and B
 *
 * @param      string        option1        single element of list A
 * @param      string        option2        single element of list B
 *
 * @return     integer       -1 if option1 is less than option2,
 *                            0 if option1 is equal to option2
 *                            1 if option1 is greater than option2
 * @private
 * @since      1.3.0
 */
function qfamsCompareText(option1, option2)
{
    if (option1.text == option2.text) {
        return 0;
    }
    return option1.text < option2.text ? -1 : 1;
}

/**
 * - qfamsUpdateHidden -
 *
 * update private list that handle selection of all elements (selected and unselected)
 *
 * @param      dom element   h              hidden list (contains all elements)
 * @param      dom element   r              selection list (contains only elements selected)
 *
 * @return     void
 * @private
 * @since      1.3.0
 */
function qfamsUpdateHidden(h, r)
{
    for (var i = 0; i < h.length; i++) {
        h.options[i].selected = false;
    }

    for (var i = 0; i < r.length; i++) {
        h.options[h.length] = new Option(r.options[i].text, r.options[i].value);
        h.options[h.length - 1].selected = true;
    }
}

/**
 * - qfamsMoveUp -
 *
 * User-End may arrange and element up to the selection list
 *
 * @param      dom element   l              selection list (contains only elements selected)
 * @param      dom element   h              hidden list (contains all elements)
 *
 * @return     void
 * @public
 * @since      1.3.0
 */
function qfamsMoveUp(l, h)
{
    var indice = l.selectedIndex;
    if (indice < 0) {
        return;
    }
    if (indice > 0) {
        qfamsMoveSwap(l, indice, indice - 1);
        qfamsUpdateHidden(h, l);
    }
}

/**
 * - qfamsMoveDown -
 *
 * User-End may arrange and element down to the selection list
 *
 * @param      dom element   l              selection list (contains only elements selected)
 * @param      dom element   h              hidden list (contains all elements)
 *
 * @return     void
 * @public
 * @since      1.3.0
 */
function qfamsMoveDown(l, h)
{
    var indice = l.selectedIndex;
    if (indice < 0) {
        return;
    }
    if (indice < l.options.length - 1) {
        qfamsMoveSwap(l, indice, indice + 1);
        qfamsUpdateHidden(h, l);
    }
}

/**
 * - qfamsMoveSwap -
 *
 * User-End may invert two elements position in the selection list
 *
 * @param      dom element   l              selection list (contains only elements selected)
 * @param      integer       i              element source indice
 * @param      integer       j              element target indice
 *
 * @return     void
 * @public
 * @since      1.3.0
 */
function qfamsMoveSwap(l, i, j)
{
    var valeur = l.options[i].value;
    var texte  = l.options[i].text;
    l.options[i].value = l.options[j].value;
    l.options[i].text  = l.options[j].text;
    l.options[j].value = valeur;
    l.options[j].text  = texte;
    l.selectedIndex = j;
}

