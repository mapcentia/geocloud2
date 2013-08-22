<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Ron McClain <ron@humaniq.com>                                |
// +----------------------------------------------------------------------+
//
// $Id: ObjectFlexy.php,v 1.1 2009/03/26 18:56:32 mhoegh Exp $

require_once("HTML/QuickForm/Renderer/Object.php");

/**
 * @abstract Long Description
 * A static renderer for HTML_Quickform.  Makes a QuickFormFlexyObject
 * from the form content suitable for use with a Flexy template
 *
 * Usage:
 * $form =& new HTML_QuickForm('form', 'POST');
 * $template =& new HTML_Template_Flexy();
 * $renderer =& new HTML_QuickForm_Renderer_ObjectFlexy(&$template);
 * $renderer->setHtmlTemplate("html.html");
 * $renderer->setLabelTemplate("label.html");
 * $form->accept($renderer);
 * $view = new StdClass;
 * $view->form = $renderer->toObject();
 * $template->compile("mytemplate.html");
 *
 * @see QuickFormFlexyObject
 *
 * Based on the code for HTML_QuickForm_Renderer_ArraySmarty
 *
 * @public
 */
class HTML_QuickForm_Renderer_ObjectFlexy extends HTML_QuickForm_Renderer_Object {
    /**
     * HTML_Template_Flexy instance
     * @var object $_flexy
     */
    var $_flexy;

    /**
     * Current element index
     * @var integer $_elementIdx
     */
    var $_elementIdx;

    /**
     * The current element index inside a group
     * @var integer $_groupElementIdx
     */
     var $_groupElementIdx = 0;

    /**
     * Name of template file for form html
     * @var string $_html
     * @see     setRequiredTemplate()
     */
    var $_html = '';

    /**
     * Name of template file for form labels
     * @var string $label
     * @see        setErrorTemplate()
     */
    var $label = '';

    /**
     * Class of the element objects, so you can add your own
     * element methods
     * @var string $_elementType
     */
    var $_elementType = 'QuickformFlexyElement';

    /**
     * Constructor
     *
     * @param $flexy object   HTML_Template_Flexy instance
     * @public
     */
    function HTML_QuickForm_Renderer_ObjectFlexy(&$flexy)
    {
        $this->HTML_QuickForm_Renderer_Object(true);
        $this->_obj = new QuickformFlexyForm();
        $this->_flexy =& $flexy;
    } // end constructor

    function renderHeader(&$header)
    {
        if($name = $header->getName()) {
            $this->_obj->header->$name = $header->toHtml();
        } else {
            $this->_obj->header[$this->_sectionCount] = $header->toHtml();
        }
        $this->_currentSection = $this->_sectionCount++;
    } // end func renderHeader

    function startGroup(&$group, $required, $error)
    {
        parent::startGroup($group, $required, $error);
        $this->_groupElementIdx = 1;
    } //end func startGroup

    /**
     * Creates an object representing an element containing
     * the key for storing this
     *
     * @private
     * @param element object     An HTML_QuickForm_element object
     * @param required bool        Whether an element is required
     * @param error string    Error associated with the element
     * @return object
     */
     function _elementToObject(&$element, $required, $error)
     {
        $ret = parent::_elementToObject($element, $required, $error);
        if($ret->type == 'group') {
            $ret->html = $element->toHtml();
            unset($ret->elements);
        }
        if(!empty($this->_label)) {
            $this->_renderLabel($ret);
        }

        if(!empty($this->_html)) {
            $this->_renderHtml($ret);
            $ret->error = $error;
        }

        // Create an element key from the name
        if (false !== ($pos = strpos($ret->name, '[')) || is_object($this->_currentGroup)) {
            if (!$pos) {
                $keys = '->{\'' . $ret->name . '\'}';
            } else {
                $keys = '->{\'' . str_replace(array('[', ']'), array('\'}->{\'', ''), $ret->name) . '\'}';
            }
            // special handling for elements in native groups
            if (is_object($this->_currentGroup)) {
                // skip unnamed group items unless radios: no name -> no static access
                // identification: have the same key string as the parent group
                if ($this->_currentGroup->keys == $keys && 'radio' != $ret->type) {
                    return false;
                }
                // reduce string of keys by remove leading group keys
                if (0 === strpos($keys, $this->_currentGroup->keys)) {
                    $keys = substr_replace($keys, '', 0, strlen($this->_currentGroup->keys));
                }
            }
        } elseif (0 == strlen($ret->name)) {
            $keys = '->{\'element_' . $this->_elementIdx . '\'}';
        } else {
            $keys = '->{\'' . $ret->name . '\'}';
        }
        // for radios: add extra key from value
        if ('radio' == $ret->type && '[]' != substr($keys, -2)) {
            $keys .= '->{\'' . $ret->value . '\'}';
        }
        $ret->keys = $keys;
        $this->_elementIdx++;
        return $ret;
    }

    /**
     * Stores an object representation of an element in the 
     * QuickformFormObject instance
     *
     * @private
     * @param elObj object  Object representation of an element
     * @return void
     */
    function _storeObject($elObj) 
    {
        if ($elObj) {
            $keys = $elObj->keys;
            unset($elObj->keys);
            if(is_object($this->_currentGroup) && ('group' != $elObj->type)) {
                $code = '$this->_currentGroup' . $keys . ' = $elObj;';
            } else {
                $code = '$this->_obj' . $keys . ' = $elObj;';
            }
            eval($code);
        }
    }

    /**
     * Set the filename of the template to render html elements.
     * In your template, {html} is replaced by the unmodified html.
     * If the element is required, {required} will be true.
     * Eg.
     * {if:error}
     *   <font color="red" size="1">{error:h}</font><br />
     * {end:}
     * {html:h}
     *
     * @public
     * @param template string   Filename of template
     * @return void
     */
    function setHtmlTemplate($template)
    {
        $this->_html = $template;
    } 

    /**
     * Set the filename of the template to render form labels
     * In your template, {label} is replaced by the unmodified label.
     * {error} will be set to the error, if any.  {required} will
     * be true if this is a required field
     * Eg.
     * {if:required}
     * <font color="orange" size="1">*</font>
     * {end:}
     * {label:h}
     *
     * @public
     * @param template string   Filename of template
     * @return void
     */
    function setLabelTemplate($template) 
    {
        $this->_label = $template;
    }

    function _renderLabel(&$ret)
    {
        $this->_flexy->compile($this->_label);
        $ret->label = $this->_flexy->bufferedOutputObject($ret);
    }

    function _renderHtml(&$ret)
    {
        $this->_flexy->compile($this->_html);
        $ret->html = $this->_flexy->bufferedOutputObject($ret);
    }

} // end class HTML_QuickForm_Renderer_ObjectFlexy

/**
 * @abstract Long Description
 * This class represents the object passed to outputObject()
 * 
 * Eg.  
 * {form.outputJavaScript():h}
 * {form.outputHeader():h}
 *   <table>
 *     <tr>
 *       <td>{form.name.label:h}</td><td>{form.name.html:h}</td>
 *     </tr>
 *   </table>
 * </form>
 * 
 * @public
 */
class QuickformFlexyForm {
    /**
     * Whether the form has been frozen
     * @var boolean $frozen
     */
    var $frozen;        
    
    /**
     * Javascript for client-side validation
     * @var string $javascript
     */
     var $javascript;

     /**
      * Attributes for form tag
      * @var string $attributes
      */
     var $attributes;

     /**
      * Note about required elements
      * @var string $requirednote
      */
     var $requirednote;

     /**
      * Collected html of all hidden variables
      * @var string $hidden
      */
     var $hidden;

     /**
      * Set if there were validation errors.  
      * StdClass object with element names for keys and their
      * error messages as values
      * @var object $errors
      */
     var $errors;

     /**
      * Array of QuickformElementObject elements.  If there are headers in the form
      * this will be empty and the elements will be in the 
      * separate sections
      * @var array $elements
      */
     var $elements;

     /**
      * Array of sections contained in the document
      * @var array $sections
      */
     var $sections;

     /**
      * Output &lt;form&gt; header
      * {form.outputHeader():h} 
      * @return string    &lt;form attributes&gt;
      */
     function outputHeader()
     {
        $hdr = "<form " . $this->attributes . ">\n";
        return $hdr;
     }

     /**
      * Output form javascript
      * {form.outputJavaScript():h}
      * @return string    Javascript
      */
     function outputJavaScript()
     {
        return $this->javascript;
     }
} // end class QuickformFlexyForm

/**
 * Convenience class describing a form element.
 * The properties defined here will be available from 
 * your flexy templates by referencing
 * {form.zip.label:h}, {form.zip.html:h}, etc.
 */
class QuickformFlexyElement {
    
    /**
     * Element name
     * @var string $name
     */
    var $name;

    /**
     * Element value
     * @var mixed $value
     */
    var $value;

    /**
     * Type of element
     * @var string $type
     */
    var $type;

    /**
     * Whether the element is frozen
     * @var boolean $frozen
     */
    var $frozen;

    /**
     * Label for the element
     * @var string $label
     */
    var $label;

    /**
     * Whether element is required
     * @var boolean $required
     */
    var $required;

    /**
     * Error associated with the element
     * @var string $error
     */
    var $error;

    /**
     * Some information about element style
     * @var string $style
     */
    var $style;

    /**
     * HTML for the element
     * @var string $html
     */
    var $html;

    /**
     * If element is a group, the group separator
     * @var mixed $separator
     */
    var $separator;

    /**
     * If element is a group, an array of subelements
     * @var array $elements
     */
    var $elements;
} // end class QuickformFlexyElement
?>