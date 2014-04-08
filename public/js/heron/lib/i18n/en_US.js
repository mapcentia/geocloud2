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
Ext.namespace("Heron.i18n");

/** api: (define)
 *  module = Heron.i18n
 *  class = Heron.i18n.dict (en_US)
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.form.ComboBox>`_
 */

/**
 * Define dictionary for the US locale.
 * Maintained by: Heron devs
 */
Heron.i18n.dict = {
    // 0.67
    'Active Layers': 'Active Layers',
    'Base Layer': 'Base Layer',
    'Base Layers': 'Base Layers',
    'BaseMaps': 'Base Maps',
    'Choose a Base Layer': 'Choose a Base Layer',
    'Legend': 'Legend',
    'Feature Info': 'Feature Info',
    'Feature Data': 'Feature Data',
    'Feature(s)': 'feature(s)',
    'No layer selected': 'No layer selected',
    'Save Features': 'Save Features',
    'Get Features': 'Get Features',
    'Feature information': 'Feature information',
    'No Information found': 'No Information found',
    'Layer not added': 'Layer not added',
    'Attribute': 'Attribute',
    'Value': 'Value',
    'Recieving data': 'Recieving data',
    'Layers': 'Layers',
    'No match': 'No match',
    'Loading...': 'Loading...',
    'Bookmarks': 'Bookmarks',
    'Places': 'Places',
    'Unknown': 'Unknown',
    'Feature Info unavailable': 'Feature Info unavailable',
    'Pan': 'Pan',
    'Leg': 'Leg',
    'Measure length': 'Measure length',
    'Measure area': 'Measure Area',
    'Length': 'Length',
    'Area': 'Area',
    'Result >': 'Result >',
    '< Search': '< Search',
    'Search': 'Search',
    'Search Nominatim': 'Search (OSM) data by name and address',
    'Search OpenLS': 'Search with OpenLS service',
    'Search PDOK': 'Type (parts of) Dutch national address',
    'Searching...': 'Searching...',
    'Search Completed: ': 'Search completed: ',
    'services': 'services',
    'service': 'service',
    'Type Nominatim': 'Type a placename or address...',
    'Overlays': 'Overlays',
    'Waiting for': 'Waiting for',
    'Warning': 'Warning',
    'Zoom in': 'Zoom in',
    'Zoom out': 'Zoom out',
    'Zoom to full extent': 'Zoom to full extent',
    'Zoom previous': 'Zoom previous',
    'Zoom next': 'Zoom next',

    // 0.68
    'Scale': 'Scale',
    'Resolution': 'Resolution',
    'Zoom': 'Zoom level',

    // 0.70
    'Export': 'Export',
    'Choose a Display Option': 'Choose a Display Option',
    'Display': 'Display',
    'Grid': 'Grid',
    'Tree': 'Tree',
    'XML': 'XML',
    'Invalid export format configured: ': 'Invalid export format configured: ',
    'No features available or non-grid display chosen': 'No features available or non-grid display chosen',
    'Choose an Export Format': 'Choose an Export Format',
    'Print Visible Map Area Directly': 'Print Visible Map Area Directly',
    'Direct Print Demo': 'Direct Print Demo',
    'This is a simple map directly printed.': 'This is a simple map directly printed.',
    'Print Dialog Popup with Preview Map': 'Print Dialog Popup with Preview Map',
    'Print Preview': 'Print Preview',
    'Print Preview Demo': 'Print Preview Demo',
    'Error getting Print options from server: ': 'Error getting Print options from server: ',
    'Error from Print server: ': 'Error from Print server: ',
    'No print provider url property passed in hropts.': 'No print provider url property passed in hropts.',
    'Create PDF...': 'Create PDF...',
    'Loading print data...': 'Loading print data...',

    // 0.71
    'Go to coordinates': 'Go to coordinates',
    'Go!': 'Go!',
    'Pan and zoom to location': 'Pan and zoom to location',
    'Enter coordinates to go to location on map': 'Enter coordinates to go to location on map',
    'Active Themes': 'Active Themes',
    'Move up': 'Move up',
    'Move down': 'Move down',
    'Opacity': 'Opacity',
    'Remove layer from list': 'Remove layer from list',
    'Tools': 'Tools',
    'Removing': 'Removing',
    'Are you sure you want to remove the layer from your list of layers?': 'Are you sure you want to remove the layer from your list of layers?',
    'You are not allowed to remove the baselayer from your list of layers!': 'You are not allowed to remove the baselayer from your list of layers!',

    // 0.72
    'Draw Features': 'Draw Features',

    // 0.73
    'Spatial Search': 'Spatial Search',
    'Search by Drawing': 'Search by Drawing',
    'Select the Layer to query': 'Select the Layer to query',
    'Choose a geometry tool and draw with it to search for objects that touch it.': 'Choose a geometry tool and draw with it to search for objects that touch it.',
    'Seconds': 'Seconds',
    'Working on it...': 'Working on it...',
    'Still searching, please be patient...': 'Still searching, please be patient...',
    'Still searching, have you selected an area with too many objects?': 'Still searching, have you selected an area with too many objects?',
    'as': 'as',
    'Undefined (check your config)': 'Undefined (check your config)',
    'Objects': 'Objects',
    'objects': 'objects',
    'Features': 'Features',
    'features': 'features',
    'Result': 'Result',
    'Results': 'Results',
    'of': 'of',
    'Using geometries from the result: ': 'Using geometries from the result: ',
    'with': 'with',
    'Too many geometries for spatial filter: ': 'Too many geometries for spatial filter: ',
    'Bookmark current map context (layers, zoom, extent)': 'Bookmark current map context (layers, zoom, extent)',
    'Add a bookmark': 'Add a bookmark',
    'Bookmark name cannot be empty': 'Bookmark name cannot be empty',
    'Your browser does not support local storage for user-defined bookmarks': 'Your browser does not support local storage for user-defined bookmarks',
    'Return to map navigation': 'Return to map navigation',
    'Draw point': 'Draw point',
    'Draw line': 'Draw line',
    'Draw polygon': 'Draw polygon',
    'Draw circle (click and drag)': 'Draw circle (click and drag)',
    'Draw Rectangle (click and drag)': 'Draw Rectangle (click and drag)',
    'Sketch is saved for use in Search by Selected Features': 'Sketch is saved for use in Search by Selected Features',
    'Select a search...': 'Select a search...',
    'Clear': 'Clear',

    // 0.74
    'Project bookmarks': 'Project bookmarks',
    'Your bookmarks': 'Your bookmarks',
    'Name': 'Name',
    'Description': 'Description',
    'Add': 'Add',
    'Cancel': 'Cancel',
    'Remove bookmark:': 'Remove bookmark:',
    'Restore map context:': 'Restore map context:',
    'Error: No \'BookmarksPanel\' found.': 'Error: No \'BookmarksPanel\' found.',
    'Input system': 'Input system',
    'Choose input system...': 'Choose input system...',
    'Map system': 'Map system',
    'X': 'X',
    'Y': 'Y',
    'Enter X-coordinate...': 'Enter X-coordinate...',
    'Enter Y-coordinate...': 'Enter Y-coordinate...',
    'Choose scale...': 'Choose scale...',
    'no zoom': 'no zoom',
    'Mode': 'Mode',
    'Remember locations': 'Remember locations',
    'Hide markers on close': 'Hide markers on close',
    'Remove markers on close': 'Remove markers on close',
    'Remove markers': 'Remove markers',
    'Location': 'Location',
    'Marker position: ': 'Marker position: ',
    'No features found': 'No features found',
    'Feature Info unavailable (you may need to make some layers visible)': 'Feature Info unavailable (you may need to make some layers visible)',
    'Search by Feature Selection': 'Search by Feature Selection',
    'Download': 'Download',
    'Choose a Download Format': 'Choose a Download Format',
    'Remove all results': 'Remove all results',
    'Download URL string too long (max 2048 chars): ': 'Download URL string too long (max 2048 chars): ',

    // 0.75
    'Query Panel': 'Query Panel',
    'Cancel current search': 'Cancel current search',
    'Search in target layer using the selected filters': 'Search in target layer using the selected filters',
    'Ready': 'Ready',
    'Search Failed': 'Search Failed',
    'Search aborted': 'Search aborted',

    // 0.76
    'No query layers found': 'No query layers found',
    'Edit Layer Style': 'Edit Layer Style',
    'Zoom to Layer Extent': 'Zoom to Layer Extent',
    'Get Layer information': 'Get Layer information',
    'Change Layer opacity': 'Change Layer opacity',
    'Select a drawing tool and draw to search immediately': 'Select a drawing tool and draw to search immediately',
    'Search in': 'Search in',
    'Search Canceled': 'Search Canceled',
    'Help and info for this example': 'Help and info for this example',

    // 1.0.1
    'Details': 'Details',
    'Table': 'Table',
    'Show record(s) in a table grid': 'Show record(s) in a table grid',
    'Show single record': 'Show single record',
    'Show next record': 'Show next record',
    'Show previous record': 'Show previous record',
	'Feature tooltips' : 'Feature tooltips',
	'FeatureTooltip' : 'FeatureTooltip',
	'Upload features from local file' : 'Upload features from local file',
	'My Upload' : 'My Upload',
	'Anything is allowed here' : 'Anything is allowed here',
	'Edit vector Layer styles' : 'Edit vector Layer styles',
	'Style Editor' : 'Style Editor',
	'Open a map context (layers, styling, extent) from file' : 'Open a map context (layers, styling, extent) from file',
	'Save current map context (layers, styling, extent) to file' : 'Save current map context (layers, styling, extent) to file',
	'Upload' : 'Upload',
	'Uploading file...' : 'Uploading file...',
    'Change feature styles': 'Change feature styles'
    'Error reading map file, map has not been loaded.':'Error reading map file, map has not been loaded.'
    'Error on removing layers.':'Error on removing layers.'
    'Error loading map file.':'Error loading map file.'
    'Error reading layer tree.':'Error reading layer tree.'

};
