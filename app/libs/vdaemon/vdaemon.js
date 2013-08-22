///////////////////////////////////////////////////////////////////////////////
// VDaemon PHP Library version 3.1.0
// Copyright (C) 2002-2009 Alexander Orlov
//
// VDaemon client-side validation file
//
///////////////////////////////////////////////////////////////////////////////

function VDSymError()
{
  return true;
}
window.onerror = VDSymError;

var vdAllForms = new Object();
var vdForm = null;

function VDValidateForm(formName, submit)
{
	if (typeof(vdAllForms[formName]) == "undefined")
		return true;

	var browser = VDDetectBrowser();
	if (browser != "IE" && browser != "Opera" && browser != "Gecko")
		return true;

	vdForm = vdAllForms[formName];
	vdForm.focus = false;
	VDPrepareValues();

	var isPageValid = true;
	var eventType = submit ? "submit" : "blur";
	for (var idx = 0; idx < vdForm.validators.length; idx++) {
		if (typeof(vdForm.validators[idx]) != "undefined") {
			VDValidateValidator(vdForm.validators[idx], eventType);
			isPageValid = isPageValid && vdForm.validators[idx].isvalid;
		}
	}
	vdForm.isvalid = isPageValid;

	VDUpdateLabels(eventType);
	VDUpdateSummaries(eventType);

	vdForm = null;
	return isPageValid;
}

function VDResetForm(formName)
{
	if (typeof(vdAllForms[formName]) == "undefined")
		return true;

	var browser = VDDetectBrowser();
	if (browser != "IE" && browser != "Opera" && browser != "Gecko")
		return true;

	vdForm = vdAllForms[formName];
	if (typeof(vdForm.controls) == "undefined")
		VDPrepareControls();

	VDUpdateLabels("reset");
	VDUpdateSummaries("reset");

	vdForm = null;
	return true;
}

function VDBindHandlers()
{
	var browser = VDDetectBrowser();
	for (var key in vdAllForms) {
		if (browser == "IE" || browser == "Opera") {
			document.forms[key].attachEvent('onsubmit', VDIeSubmitHandler);
			document.forms[key].attachEvent('onreset', VDIeResetHandler);
		} else if (browser == "Gecko") {
			document.forms[key].addEventListener('submit', VDGeckoSubmitHandler, false);
			document.forms[key].addEventListener('reset', VDGeckoResetHandler, false);
		}

		for (var idx = 0; idx < document.forms[key].elements.length; idx++) {
			var element = document.forms[key].elements[idx];
			if (element.type == "submit" && element.tagName != "BUTTON") {
				if (browser == "IE" || browser == "Opera") {
					element.attachEvent('onclick', VDIeClickHandler);
				} else if (browser == "Gecko") {
					element.addEventListener('click', VDGeckoClickHandler, false);
				}
			}
			else if (element.type != "button" && element.type != "image" &&
			element.type != "submit" && element.type != "reset") {
				if (vdAllForms[key].validationmode == "onchange") {
					if (browser == "IE" || browser == "Opera") {
						element.attachEvent('onblur', VDIeSubmitHandler);
					} else if (browser == "Gecko") {
						element.addEventListener('blur', VDGeckoSubmitHandler, false);
					}
				}
			}
		}
	}
}

function VDIeSubmitHandler()
{
	if (vdForm == null) {
		var formName = VDGetFormName(event.srcElement);
		var submit = event.type == "submit";
		var valid = VDValidateForm(formName, submit);
		if (submit) {
			if (valid) {
				VDDisableButtons(formName);
			} else {
				event.returnValue = false;
			}
		}
	}
}

function VDIeResetHandler()
{
	if (vdForm == null) {
		var formName = VDGetFormName(event.srcElement);
		VDResetForm(formName);
	}
}

function VDIeClickHandler()
{
	if (vdForm == null) {
		var formName = VDGetFormName(event.srcElement);
		vdAllForms[formName].submit = event.srcElement;
	}
}

function VDGeckoSubmitHandler(event)
{
	if (vdForm == null) {
		var formName = VDGetFormName(event.target);
		var submit = event.type == "submit";
		var valid = VDValidateForm(formName, submit);
		if (submit) {
			if (valid) {
				VDDisableButtons(formName);
			} else {
				event.preventDefault();
			}
		}
	}
}

function VDGeckoResetHandler(event)
{
	if (vdForm == null) {
		var formName = VDGetFormName(event.target);
		VDResetForm(formName);
	}
}

function VDGeckoClickHandler(event)
{
	if (vdForm == null) {
		var formName = VDGetFormName(event.target);
		vdAllForms[formName].submit = event.target;
	}
}

function VDGetFormName(element)
{
	var result = '';
	if (element.tagName == "INPUT" || element.tagName == "SELECT" || element.tagName == "TEXTAREA") {
		element = element.form;
	}
	if (element != null) {
		if (typeof(element.id) == "string") {
			result = element.id;
		} else if (element.getAttributeNode("ID") != null) {
			result = element.getAttributeNode("ID").value;
		}
		if (result == '') {
			if (typeof(element.name) == "string") {
				result = element.name;
			} else if (element.getAttributeNode("NAME") != null) {
				result = element.getAttributeNode("NAME").value;
			}
		}
	}
	return result;
}

function VDDisableButtons(formName)
{
	if (vdAllForms[formName].disablebuttons == "none")
		return;

	for (var idx = 0; idx < document.forms[formName].elements.length; idx++) {
		var element = document.forms[formName].elements[idx];
		if (element.type == "submit" || element.type == "image" ||
		(vdAllForms[formName].disablebuttons == "all" &&
		(element.type == "button" || element.type == "reset"))) {
			element.disabled = true;
		}
	}
}

function VDDetectBrowser()
{
	var detect = navigator.userAgent.toLowerCase();
	var browser;

	if (detect.indexOf('gecko') > -1) browser = "Gecko";
	else if (detect.indexOf('opera') > -1) browser = "Opera";
	else if (document.all) browser = "IE";
	else browser = "Unknown";

	return browser;
}

function VDGetPhpControlName(ctrlName)
{
	var result = new Array();
	var posL, posR, index;

	posL = ctrlName.indexOf('[');
	if (posL == 0) {
		return null;
	}
	posR = ctrlName.indexOf(']', posL);
	result[0] = posL > 0 && posR > 0 ? ctrlName.substring(0, posL) : ctrlName;
	result[0] = result[0].replace('[', '_');
	result[0] = result[0].replace('.', '_');

	while (posL > 0 && posR > 0) {
		index = ctrlName.substring(posL + 1, posR);
		index = VDEscape(index);
		if (index.match(/^0$|^[1-9][0-9]*$/) != null) { // decimal int
			index = parseInt(index);
		}
		result[result.length] = index;

		posL = ctrlName.indexOf('[', posR);
		if (posL != posR + 1) {
			posL = -1;
		} else {
			posR = ctrlName.indexOf(']', posL);
		}
	}

	return result;
}

function VDPrepareControls()
{
	var control;
	var phpName;
	var element;
	vdForm.controls = new Array();

	for (var idx = 0; idx < document.forms[vdForm.name].elements.length; idx++) {
		element = document.forms[vdForm.name].elements[idx];
		if (element.name && element.name != "VDaemonValidators" && element.tagName != "BUTTON" &&
		element.type != "button" && element.type != "image" && element.type != "reset") {
			phpName = VDGetPhpControlName(element.name);
			if (phpName != null) {
				control = new Object();
				control.phpName = phpName;
				control.obj = element;
				vdForm.controls[vdForm.controls.length] = control;
			}
		}
	}
}

function VDPrepareValues()
{
	var values, index, ref;

	if (typeof(vdForm.controls) == "undefined")
		VDPrepareControls();

	vdForm.values = new Object();
	for (var i = 0; i < vdForm.controls.length; i++) {
		values = VDGetElementValues(vdForm.controls[i].obj);
		for (var v = 0; v < values.length; v++) {
			ref = vdForm.values;
			index = null;
			for (var j = 0; j < vdForm.controls[i].phpName.length; j++) {
				if (index != null)
					ref = ref[index];
				index = vdForm.controls[i].phpName[j];
				if (index === "")
					index = ref.length;
				if (typeof(ref[index]) != "object") {
					ref[index] = new Object();
				}
			}
			ref[index] = values[v];
		}
	}
}

function VDGetElementValues(element)
{
	var result = new Array();
	if (element.type == "select-multiple") {
		var options = element.getElementsByTagName("OPTION");
		if (typeof(options.length) == "number") {
			for (var idx = 0; idx < options.length; idx++) {
				var value = VDGetOptionValue(options[idx]);
				if (value != null) {
					result[result.length] = value;
				}
			}
		}
	} else if (typeof(element.value) == "string") {
		if (element.type == "checkbox" || element.type == "radio") {
			if (element.checked)
				result[result.length] = VDTrim(element.value);
		} else if (element.type == "submit") {
			if (vdForm.disablebuttons == "none" &&
			typeof(vdForm.submit) == "object" && vdForm.submit == element) {
				vdForm.submit = null;
				result[result.length] = VDTrim(element.value);
			}
		} else
			result[result.length] = VDTrim(element.value);
	}
	return result;
}

function VDGetOptionValue(option)
{
	var result = null;
	if (option.selected) {
		if (typeof(option.value) == "string") {
			result = VDTrim(option.value);
		} else {
			result = VDTrim(option.text);
		}
	}
	return result;
}

function VDValidateValidator(validator, eventType)
{
	validator.isvalid = true;
	switch (validator.type) {
		case "required":
			validator.isvalid = VDEvaluateRequired(validator);
			break;
		case "checktype":
			validator.isvalid = VDEvaluateChecktype(validator);
			break;
		case "range":
			validator.isvalid = VDEvaluateRange(validator);
			break;
		case "compare":
			validator.isvalid = VDEvaluateCompare(validator);
			break;
		case "regexp":
			validator.isvalid = VDEvaluateRegExp(validator);
			break;
		case "format":
			validator.isvalid = VDEvaluateFormat(validator);
			break;
		case "custom":
			validator.isvalid = VDEvaluateCustom(validator);
			break;
		case "group":
			validator.isvalid = -1;
			for (var i = 0; i < validator.validators.length; i++) {
				VDValidateValidator(validator.validators[i], "");
				if (validator.isvalid == -1) {
					validator.isvalid = validator.validators[i].isvalid;
				} else {
					switch (validator.operator) {
						case "and":
							validator.isvalid = validator.isvalid && validator.validators[i].isvalid;
							break;
						case "or":
							validator.isvalid = validator.isvalid || validator.validators[i].isvalid;
							break;
						case "xor":
							validator.isvalid = validator.isvalid != validator.validators[i].isvalid;
							break;
					}
				}
			}
			break;
	}

	if (eventType == "submit" && !validator.isvalid && !vdForm.focus) {
		var fcontrol = VDFindFocus(validator);
		if (fcontrol) {
			var ctrlObj = document.forms[vdForm.name].elements[fcontrol];
			if (typeof(ctrlObj) != "undefined") {
				if (typeof(ctrlObj.tagName) == "undefined" && typeof(ctrlObj.length) == "number") {
					ctrlObj = ctrlObj[0];
				}
				try {
					ctrlObj.focus();
				} catch (e) {}
				vdForm.focus = true;
			}
		}
	}
}

function VDFindFocus(validator)
{
	var fcontrol = null;
	if (validator.type == "group") {
		for (var i = 0; i < validator.validators.length; i++) {
			if (!validator.validators[i].isvalid) {
				fcontrol = VDFindFocus(validator.validators[i]);
				if (fcontrol)
					break;
			}
		}
	} else if (typeof(validator.fcontrol) == "string") {
		fcontrol = validator.fcontrol;
	}

	return fcontrol;
}

function VDUpdateLabels(eventType)
{
	if (typeof(vdForm.labels) == "undefined")
		return;
	var i, j;
	for (i = 0; i < vdForm.labels.length; i++) {
		var oLabel = vdForm.labels[i];
		var label = document.getElementById(oLabel.id);
		if (label != null) {
			var isValid = true;
			if (eventType != "reset") {
				for (j = 0; j < oLabel.validators.length; j++) {
					var valName = oLabel.validators[j];
					var valState = VDGetValidatorState(valName);
					if (valState != -1) {
						isValid = isValid && valState;
					}
				}
			}

			label.innerHTML = "";
			if (isValid) {
				label.innerHTML = oLabel.oktext;
				label.className = oLabel.okclass;
			} else {
				label.innerHTML = oLabel.errtext;
				label.className = oLabel.errclass;
			}

			if (typeof(oLabel.cokclass) == "object") {
				for (j in oLabel.cokclass) {
					if (typeof(vdForm.controls[j].obj) == "object") {
						vdForm.controls[j].obj.className = isValid ? oLabel.cokclass[j] : oLabel.cerrclass;
					}
				}
			}
		}
	}
}

function VDUpdateSummaries(eventType)
{
	if (typeof(vdForm.summaries) == "undefined")
		return;

	for (var i = 0; i < vdForm.summaries.length; i++) {
		var headerSep, first, pre, post, last, s;
		var oSummary = vdForm.summaries[i];
		var summary = document.getElementById(oSummary.id);
		if (summary != null) {
			if (eventType == "reset" || vdForm.isvalid) {
				//summary.innerHTML = oSummary.showsummary ? "&nbsp;" : "";
				summary.innerHTML = "";
				summary.style.display = "none";
			} else {
				if (oSummary.showsummary) {
					switch (oSummary.displaymode) {
						case "list":
						default:
							headerSep = "<br>";
							first = "";
							pre = "";
							post = "<br>";
							last = "";
							break;
						case "bulletlist":
							headerSep = "";
							first = "<ul>";
							pre = "<li>";
							post = "</li>";
							last = "</ul>";
							break;
						case "paragraph":
							headerSep = " ";
							first = "";
							pre = "";
							post = " ";
							last = "";
							break;
					}

					s = "";
					for (var j = 0; j < vdForm.validators.length; j++) {
						var val = vdForm.validators[j];
						s += VDGetValidatorErrMsg(val, pre, post);
					}
					if (s != "") {
						s = first + s + last;
						if (oSummary.headertext != "") {
							s = oSummary.headertext + headerSep + s;
						}
					} else if (oSummary.headertext != "") {
						s = oSummary.headertext;
					}

					summary.innerHTML = s;
					summary.style.display = (s == "") ? "none" : "";
					//window.scrollTo(0,0);
				}

				if (eventType == "submit" && oSummary.messagebox) {
					switch (oSummary.displaymode) {
						case "list":
						default:
							pre = "";
							post = "\n";
							break;
						case "bulletlist":
							pre = "  - ";
							post = "\n";
							break;
						case "paragraph":
							pre = "";
							post = " ";
							break;
					}

					headerSep = "\n";
					first = "";
					last = "";

					s = "";
					for (var j = 0; j < vdForm.validators.length; j++) {
						var val = vdForm.validators[j];
						s += VDGetValidatorErrMsg(val, pre, post);
					}
					if (s != "") {
						s = first + s + last;
						if (oSummary.headertext != "") {
							s = oSummary.headertext + headerSep + s;
						}
					} else if (oSummary.headertext != "") {
						s = oSummary.headertext;
					}

					alert(s);
				}
			}
		}
	}
}

function VDGetValidatorErrMsg(val, pre, post)
{
	var result = "";
	if (!val.isvalid) {
		if (val.errmsg) {
			result += pre + val.errmsg + post;
		}
		if (val.type == "group" && val.operator != "xor") {
			for (var i = 0; i < val.validators.length; i++) {
				result += VDGetValidatorErrMsg(val.validators[i], pre, post);
			}
		}
	}

	return result;
}

function VDGetValidatorState(valName)
{
	var result = -1;
	if (valName) {
		for (var i = 0; i < vdForm.validators.length; i++) {
			result = VDGetValStateR(valName, vdForm.validators[i], false);
			if (result != -1) {
				break;
			}
		}
	}

	return result;
}

function VDGetValStateR(valName, val, parentState)
{
	var result = -1;
	if (val.name == valName) {
		result = parentState || val.isvalid;
	} else if (val.type == "group" && val.operator != "xor") {
		for (var i = 0; i < val.validators.length; i++) {
			result = VDGetValStateR(valName, val.validators[i], val.isvalid);
			if (result != -1) {
				result = parentState || result;
				break;
			}
		}
	}

	return result;
}

function VDGetControlValue(ctrlName)
{
	var result = vdForm.values;

	if (typeof(ctrlName) != "object")
		return null;

	for (var idx = 0; idx < ctrlName.length; idx++) {
		if (typeof(result[ctrlName[idx]]) == "undefined") {
			return null;
		}
		result = result[ctrlName[idx]];
	}

	return result;
}

function VDTrim(str)
{
	var match = str.match(/^\s*(\S+(\s+\S+)*)\s*$/);
	return (match == null) ? "" : match[1];
}

function VDEscape(value)
{
	value = value.replace(/\\/g, "\\\\");   //")
	value = value.replace(/'/g, "\\'");	 //')
	value = value.replace(/"/g, '\\"');	 //")

	return value;
}

function VDConvert(op, val)
{
	var dataType = val.validtype;
	var num, cleanInput, m, exp;
	if (dataType == "integer") {
		subPattern = val.groupchar != '' ? val.groupchar + '?' : '';
		pattern = '^\\s*[-+]?\\d{1,3}(?:' + subPattern + '\\d{3})*\\s*$';
		exp = new RegExp(pattern);
		if (op.match(exp) == null)
			return null;
		cleanInput = val.groupchar != '' ? op.replace(new RegExp(val.groupchar, 'g'), '') : op;
		num = parseInt(cleanInput, 10);
		return (isNaN(num) ? null : num);
	} else if(dataType == "float") {
		subPattern = val.groupchar != '' ? val.groupchar + '?' : '';
		pattern = '^\\s*[-+]?(\\d{1,3}(?:' + subPattern + '\\d{3})*)?(' + val.decimalchar + '\\d+)?\\s*$';
		exp = new RegExp(pattern);
		if (op.match(exp) == null)
			return null;
		cleanInput = val.groupchar != '' ? op.replace(new RegExp(val.groupchar, 'g'), '') : op;
		cleanInput = val.decimalchar != '\\.' ? cleanInput.replace(new RegExp(val.decimalchar), '.') : cleanInput;
		num = parseFloat(cleanInput);
		return (isNaN(num) ? null : num);
	} else if (dataType == "currency") {
		subPattern = val.groupchar != '' ? val.groupchar + '?' : '';
		pattern = '^\\s*[-+]?(\\d{1,3}(?:' + subPattern + '\\d{3})*)?(' + val.decimalchar + '\\d{1,2})?\\s*$';
		exp = new RegExp(pattern);
		if (op.match(exp) == null)
			return null;
		cleanInput = val.groupchar != '' ? op.replace(new RegExp(val.groupchar, 'g'), '') : op;
		cleanInput = val.decimalchar != '\\.' ? cleanInput.replace(new RegExp(val.decimalchar), '.') : cleanInput;
		num = parseFloat(cleanInput);
		return (isNaN(num) ? null : num);
	} else if (dataType == "date") {
		return VDConvertDate(op, val);
	} else if (dataType == "time") {
		return VDConvertTime(op, val);
	} else if (dataType == "datetime") {
		exp = /^\s*([-\d\.\/]+)\s+([\d:]+\s?(?:PM|AM)?)\s*$/i;
		m = op.match(exp);
		if (m == null)
			return null;
		var date = VDConvertDate(m[1], val);
		var time = VDConvertTime(m[2], val);
		if (date == null || time == null)
			return null;

		return date + time;
		return VDConvertDate(op, val);
	} else {
		return op.toString();
	}
}

function VDConvertDate(op, val)
{
	function VDGetFullYear(year) {
		return (year + 2000) - ((year < 30) ? 0 : 100);
	}

	var day, month, year, m, exp;
	if (val.dateorder == "ymd") {
		exp = new RegExp("^\\s*(\\d{2}(\\d{2})?)([-./])(\\d{1,2})\\3(\\d{1,2})\\s*$");
		m = op.match(exp);
		if (m == null)
			return null;
		day = m[5];
		month = m[4];
		year = (m[1].length == 4) ? m[1] : VDGetFullYear(parseInt(m[1], 10));
	} else {
		exp = new RegExp("^\\s*(\\d{1,2})([-./])(\\d{1,2})\\2(\\d{2}(\\d{2})?)\\s*$");
		m = op.match(exp);
		if (m == null)
			return null;
		if (val.dateorder == "dmy") {
			day = m[1];
			month = m[3];
		} else {
			day = m[3];
			month = m[1];
		}
		year = (m[4].length == 4) ? m[4] : VDGetFullYear(parseInt(m[4], 10));
	}
	month -= 1;
	var date = new Date(year, month, day);
	return (typeof(date) == "object" && year == date.getFullYear() && month == date.getMonth() && day == date.getDate()) ? date.valueOf() : null;
}

function VDConvertTime(op, val)
{
	var hour, min, sec, suf, m, exp;
	if (val.timeformat == "12") {
		exp = /^\s*(\d{1,2}):(\d{2})(?::(\d{2}))?\s?(PM|AM)\s*$/i;
		m = op.match(exp);
		if (m == null)
			return null;
		hour = parseInt(m[1], 10);
		min = m[2];
		sec = m[3] ? m[3] : 0;
		suf = m[4].toLowerCase();

		if (hour < 1 || hour > 12)
			return null;
		if (hour == 12) {
			hour = (suf == 'am') ? 0 : 12;
		} else if (suf == 'pm') {
			hour += 12;
		}
	} else {
		exp = /^\s*(\d{1,2}):(\d{2})(?::(\d{2}))?\s*$/;
		m = op.match(exp);
		if (m == null)
			return null;
		hour = m[1];
		min = m[2];
		sec = m[3] ? m[3] : 0;
	}

	var date = new Date(1970, 0, 1, hour, min, sec);
	return (typeof(date) == "object" && hour == date.getHours() && min == date.getMinutes() && sec == date.getSeconds()) ? date.valueOf() : null;
}

function VDCompare(operand1, operand2, operator, val)
{
	var op1, op2;
	if ((op1 = VDConvert(operand1, val)) == null)
		return false;
	if ((op2 = VDConvert(operand2, val)) == null)
		return true;

	if (val.validtype == "string" && !val.casesensitive) {
		op1 = op1.toLowerCase();
		op2 = op2.toLowerCase();
	}
	switch (operator) {
		case "ne":
			return (op1 != op2);
		case "g":
			return (op1 > op2);
		case "ge":
			return (op1 >= op2);
		case "l":
			return (op1 < op2);
		case "le":
			return (op1 <= op2);
		case "e":
		default:
			return (op1 == op2);
	}
}

function VDEvaluateRequired(validator)
{
	var value = VDGetControlValue(validator.control);
	if (value == null)
		return validator.negation;

	var len;
	if (typeof(value) == "object") {
		len = 0;
		for (var i in value) {
			if (value[i] !== '')
				len++;
		}
	} else
		len = value.length;

	var result = true;
	if (len < validator.minlength) {
		result = false;
	} else if (validator.maxlength != -1) {
		result = (len <= validator.maxlength);
	}
	if (validator.negation) {
		result = !result;
	}

	return result;
}

function VDEvaluateChecktype(validator)
{
	var value = VDGetControlValue(validator.control);
	if (value != null && typeof(value) == "object")
		return true;
	if (value == null || value.length == 0)
		return !validator.required;

	var result = (VDConvert(value, validator) != null);
	if (validator.negation) {
		result = !result;
	}

	return result;
}

function VDEvaluateRange(validator)
{
	var value = VDGetControlValue(validator.control);
	if (value != null && typeof(value) == "object")
		return true;
	if (value == null || value.length == 0)
		return !validator.required;

	var result = (VDCompare(value, validator.minvalue, "ge", validator) &&
				  VDCompare(value, validator.maxvalue, "le", validator));
	if (validator.negation) {
		result = !result;
	}

	return result;
}

function VDEvaluateCompare(validator)
{
	var value = VDGetControlValue(validator.control);
	if (value != null && typeof(value) == "object")
		return true;
	if (value == null || value.length == 0)
		return !validator.required;

	var compareTo = "";
	if (typeof(validator.comparevalue) != "undefined") {
		compareTo = validator.comparevalue;
	} else if (typeof(validator.comparecontrol) != "undefined") {
		compareTo = VDGetControlValue(validator.comparecontrol);
	} else
		return false;

	if (compareTo == null)
		return false;
	else if (typeof(compareTo) == "object")
		return true;

	var result = VDCompare(value, compareTo, validator.operator, validator);
	if (validator.negation) {
		result = !result;
	}

	return result;
}

function VDEvaluateRegExp(validator)
{
	var value = VDGetControlValue(validator.control);
	if (value != null && typeof(value) == "object")
		return true;
	if (value == null || value.length == 0)
		return !validator.required;

	var result = true;
	var rx;
	try {
		eval("rx = " + validator.clientregexp + ";");
		var matches = rx.exec(value);
		result = (matches != null);
		if (validator.negation) {
			result = !result;
		}
	} catch(e) {
		result = true;
	}

	return result;
}

function VDEvaluateFormat(validator)
{
	var value = VDGetControlValue(validator.control);
	if (value != null && typeof(value) == "object")
		return true;
	if (value == null || value.length == 0)
		return !validator.required;

	var rx;
	switch (validator.format) {
		case 'email':
			rx = /^[\w'+-]+(\.[\w'+-]+)*@[\w-]+(\.[\w-]+)*\.\w{1,8}$/;
			break;
		case 'zip_us5':
			rx = /^\d{5}$/;
			break;
		case 'zip_us9':
			rx = /^\d{5}[\s-]\d{4}$/;
			break;
		case 'zip_us':
			rx = /^\d{5}([\s-]\d{4})?$/;
			break;
		case 'zip_canada':
			rx = /^[a-z]\d[a-z]\s?\d[a-z]\d$/i;
			break;
		case 'zip_uk':
			rx = /^[a-z](\d|\d[a-z]|\d{2}|[a-z]\d|[a-z]\d[a-z]|[a-z]\d{2})\s?\d[a-z]{2}$/i;
			break;
		case 'phone_us':
			rx = /^(\+?\d{1,3})?[-\s\.]?(\(\d{3}\)|\d{3})[-\s\.]?\d{3}[-\s\.]?\d{4}(([-\s\.]|(\s?(x|ext\.?)))\d{1,5})?$/i;
			break;
		case 'ip4':
			rx = /^(([3-9]\d?|[01]\d{0,2}|2\d?|2[0-4]\d|25[0-5])\.){3}([3-9]\d?|[01]\d{0,2}|2\d?|2[0-4]\d|25[0-5])$/;
			break;
		default:
			rx = /^$/;
			break;
	}
	var matches = rx.exec(value);
	var result = (matches != null);
	if (validator.negation) {
		result = !result;
	}

	return result;
}

function VDEvaluateCustom(validator)
{
	var value = null;
	if (typeof(validator.control) == "object") {
		value = VDGetControlValue(validator.control);
	}

	var args = new Object();
	args.isvalid = true;
	args.errmsg = validator.errmsg;
	args.value = value;
	if (typeof(validator.clientfunction) == "string") {
		var rx = /^[a-zA-Z_]\w*$/;
		var m = rx.exec(validator.clientfunction);
		var isfunc;
		if (m != null) {
			eval("isfunc = typeof(" + validator.clientfunction + ") == 'function';");
			if (isfunc) {
				eval(validator.clientfunction + "(args);");
				args.isvalid = (args.isvalid === true);
				if (typeof(args.errmsg) == "string") {
					validator.errmsg = args.errmsg;
				}
			}
		}
	}
	return args.isvalid;
}