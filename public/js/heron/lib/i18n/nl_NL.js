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
 *  class = Heron.i18n.dict (nl_NL)
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.form.ComboBox>`_
 */

/**
 * Define dictionary for the Dutch locale.
 * Maintained by: Heron devs
 */
Heron.i18n.dict = {
    // 0.67
	'Active Layers' : 'Actieve Lagen',
	'Base Layer': 'Basislagen',
	'Base Layers': 'Basislagen',
	'BaseMaps': 'Basis Kaarten',
	'Choose a Base Layer': 'Kies een basis kaart',
	'Legend': 'Legenda',
	'Feature Info': 'Objectinformatie',
	'Feature Data': 'Objectgegevens',
	'Feature(s)': 'object(en)',
	'No layer selected': 'Geen laag geselecteerd',
	'Save Features': 'Bewaar objecten',
	'Get Features': 'Objecten ophalen',
	'Feature information': 'Objectinformatie',
	'No Information found': 'Geen informatie gevonden',
	'Layer not added': 'Laag niet toegevoegd',
	'Attribute': 'Attribuut',
	'Value': 'Waarde',
	'Recieving data':'Bezig met het ophalen van gegevens',
	'Layers': 'Lagen',
	'No match': 'Niet gevonden',
	'Loading...': 'Bezig met laden...',
	'Bookmarks': 'Snelkoppelingen',
	'Places': 'Plaatsen',
	'Unknown': 'Onbekend',
	'Feature Info unavailable':'Geen objectinformatie beschikbaar',
	'Pan': 'Verschuiven',
	'Measure length': 'Lengte meten',
	'Measure area': 'Oppervlakte meten',
	'Leg' : 'Traject',
	'Length': 'Lengte',
	'Area': 'Oppervlakte',
	'Result >': 'Resultaat >',
	'< Search': '< Zoeken',
	'Search': 'Zoeken',
	'Search Nominatim': 'Zoeken in (OSM) data met naam en/of adres',
	'Search OpenLS' : 'Zoeken met OpenLS zoekdienst',
	'Search PDOK': 'Geef een NL adres...',
	'Searching...': 'Bezig met zoeken...',
	'Search Completed: ': 'Zoeken voltooid: ',
	'services':'diensten',
	'service':'dienst',
	'Type Nominatim': 'Type een plaatsnaam en/of adres...',
	'Overlays': 'Themalagen',
	'Waiting for': 'Wachten op',
	'Warning': 'Waarschuwing',
	'Zoom in': 'Inzoomen',
	'Zoom out': 'Uitzoomen',
	'Zoom to full extent':'Toon hele kaart',
	'Zoom previous': 'Naar vorig kaartbeeld',
	'Zoom next': 'Naar volgend kaartbeeld',

	// 0.68
	'Scale': 'Schaal',
	'Resolution': 'Resolutie',
	'Zoom': 'Zoomniveau',

	// 0.70
	'Export': 'Exporteer',
	'Choose a Display Option' : 'Kies een weergave optie',
	'Display': 'Weergave',
	'Grid': 'Tabel',
	'Tree' : 'Boom',
	'XML' : 'XML',
	'Invalid export format configured: ': 'Geen valide exporteer formaat geconfigureerd: ',
	'No features available or non-grid display chosen' : 'Geen objecten gevonden of niet in tabel weergave',
	'Choose an Export Format' : 'Kies een exporteer optie',
	'Print Visible Map Area Directly' : 'Print Zichtbaar Kaart Gebied',
	'Direct Print Demo' : 'Direct Print Demo',
	'This is a simple map directly printed.' : 'Dit is een eenvoudige kaart direct afgedrukt.',
	'Print Dialog Popup with Preview Map' : 'Print Dialog Popup met Voorvertoning Kaart',
	'Print Preview' : 'Printen Voorvertoning',
	'Print Preview Demo' : 'Print Preview Demo',
	'Error getting Print options from server: ' : 'Fout in verkrijgen opties van de Print server: ',
	'Error from Print server: ' : 'Fout opgetreden in Print server: ',
	'No print provider url property passed in hropts.' : 'Geen print provider eigenschap url doorgegeven in hropts.',
	'Create PDF...' : 'Produceren PDF...',
	'Loading print data...' : 'Laden van print data...',

	 // 0.71
	'Go to coordinates': 'Ga naar coordinaten',
	'Go!': 'Ga!',
	'Pan and zoom to location': 'Inzoomen naar XY locatie',
	'Enter coordinates to go to location on map': 'Voer coordinaten in om naar punt op kaart te gaan',
	'Active Themes': 'Actieve Lagen',
	'Move up': 'Naar boven',
	'Move down': 'Naar beneden',
	'Opacity': 'Doorzichtigheid',
	'Remove layer from list': 'Verwijder laag van lijst',
	'Tools': 'Tools',
	'Removing': 'Verwijderen',
	'Are you sure you want to remove the layer from your list of layers?': 'Weet u zeker dat u de laag wilt verwijderen uit de lijst met lagen?',
	'You are not allowed to remove the baselayer from your list of layers!': 'Het is niet toegestaan om de basislaag te verwijderen uit de lijst met lagen!',

	// 0.72
	'Draw Features': 'Geo-Objecten Tekenen',

	// 0.73
	'Spatial Search' : 'Ruimtelijk Zoeken',
    'Search by Drawing': 'Zoek door geometrieÃ«n te tekenen',
	'Select the Layer to query' : 'Selecteer de laag om te bevragen',
	'Choose a geometry tool and draw with it to search for objects that touch it.': 'Selecteer een geometrie en teken daarmee om te zoeken naar objecten die daaronder liggen.',
	'Seconds' : 'Seconden',
	'Working on it...': 'Aan het werk...',
	'Still searching, please be patient...': 'Nog steeds zoekende, even geduld a.u.b...',
	'Still searching, have you selected an area with too many objects?' : 'Nog steeds aan het zoeken, is het een gebied met veel objecten?',
	'as' : 'als',
	'Undefined (check your config)': 'Onbekend (check configuratie)',
    'Objects': 'Objecten',
    'objects': 'objecten',
    'Features': 'Features',
    'features': 'features',
    'Result': 'Resultaat',
    'Results': 'Resultaten',
    'of': 'van',
    'Using geometries from the result: ': 'GeometrieÃ«n gebruikt van het resultaat van: ',
    'with': 'met',
    'Too many geometries for spatial filter: ': 'Te veel geometrieÃ«n voor filter: ',
    'Bookmark current map context (layers, zoom, extent)': 'Maak snelkoppelling van kaart context (lagen, zoom, extent)',
    'Add a bookmark': 'Voeg een snelkoppeling toe',
    'Bookmark name cannot be empty': 'Snelkoppeling naam mag niet leeg zijn',
    'Your browser does not support local storage for user-defined bookmarks': 'Uw browser ondersteunt geen lokale opslag voor Bookmarks',
    'Return to map navigation': 'Keer terug naar kaart navigatie',
    'Draw point': 'Teken punt',
    'Draw line': 'Teken lijn',
    'Draw polygon': 'Teken polygoon',
    'Draw circle (click and drag)': 'Teken cirkel (klik en sleep)',
    'Draw Rectangle (click and drag)': 'Teken rechthoek (klik en sleep)',
    'Sketch is saved for use in Search by Selected Features': 'Getekende geometrieï¿½n worden bewaard om selecties te doen',
    'Select a search...': 'Selecteer een zoekmethode...',
    'Clear': 'Wissen',

    // 0.74
    'Project bookmarks': 'Project snelkoppelingen',
    'Your bookmarks': 'Eigen snelkoppelingen',
    'Name': 'Naam',
    'Description': 'Beschrijving',
    'Add': 'Toevoegen',
    'Cancel': 'Stop',
    'Remove bookmark:': 'Verwijder snelkoppeling:',
    'Restore map context:': 'Herstel kaartbeeld:',
    'Error: No \'BookmarksPanel\' found.': 'Fout: Geen \'Snelkoppelingen Paneel\' gevonden.',
    'no zoom': 'Niet inzoomen',
    'Mode': 'Mode',
    'Remember locations': 'Locaties onthouden',
    'Hide markers on close': 'Locaties verbergen bij sluiten',
    'Remove markers on close': 'Locaties verwijderen bij sluiten',
    'Remove markers': 'Locaties verwijderen',
    'Location': 'Locatie',
    'Marker position: ': 'Locatie positie: ',
    'No features found': 'Geen objecten gevonden',
    'Feature Info unavailable (you may need to make some layers visible)': 'Geen object-informatie beschikbaar (u moet mogelijk lagen zichtbaar maken)',
    'Search by Feature Selection': 'Zoek via object-selectie',
    'Download': 'Download',
    'Choose a Download Format': 'Kies een Download formaat',
    'Remove all results': 'Verwijder resultaten',
    'Download URL string too long (max 2048 chars): ': 'Download URL te lang (max 2048 tekens): ',

       // 0.75
    'Query Panel': 'Zoekopdracht paneel',
	'Cancel current search': 'Stop zoekopdracht',
    'Search in target layer using the selected filters': 'Zoek in doel-laag met de geselecteerde criteria',
    'Ready': 'Gereed',
	'Search Failed': 'Zoekopdracht mislukt',
	'Search aborted': 'Zoekopdracht gestopt',

    // 0.76
    'No query layers found': 'Geen zoekbare lagen gevonden',
    'Edit Layer Style': 'Laag-stijlen aanpassen',
    'Zoom to Layer Extent': 'Zoom naar Laag gebied',
    'Get Layer information': 'Laag informatie',
    'Change Layer opacity': 'Laag-doorzichtigheid aanpassen',
    'Select a drawing tool and draw to search immediately': 'Selecteer een teken-tool. Zoeken begint na tekenen.',
    'Search in': 'Zoeken in',
    'Search Canceled': 'Zoeken gestopt',
    'Help and info for this example': 'Help en informatie voor dit voorbeeld',

    // 1.0.1
    'Details': 'Details',
    'Table': 'Tabel',
    'Show record(s) in a table grid': 'Tonen als tabel',
    'Show single record': 'Toon details van een rij',
    'Show next record': 'Toon volgende',
    'Show previous record': 'Toon vorige',
    'Change feature styles': 'Pas geometrie-stijlen aan'

};
