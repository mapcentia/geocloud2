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
 *  class = Heron.i18n.dict (cs_CZ)
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.form.ComboBox>`_
 */

/**
 * Define dictionary for the CZ locale.
 * Maintained by: Martin Kokeš and Heron devs
 */
Heron.i18n.dict = {

	// 0.67
	'Active Layers': 'Aktivní vrstvy',
	'Base Layer': 'Základní vrstva',
	'Base Layers': 'Základní vrstvy',
	'BaseMaps': 'Základní mapy',
	'Choose a Base Layer': 'Vyberte základní vrstvu',
	'Legend': 'Legenda',
	'Feature Info': 'Informace o prvku',
	'Feature Data': 'Data prvku',
	'Feature(s)': 'Prvky',
	'No layer selected': 'Nebyla vybrána žádná vrstva',
	'Save Features': 'Uložit prvky',
	'Get Features': 'Získat prvky',
	'Feature information': 'Informace o prvku',
	'No Information found': 'Nenalezeny žádné informace',
	'Layer not added': 'Vrstva nebyla p?idána',
	'Attribute': 'Atribut',
	'Value': 'Hodnota',
	'Recieving data': 'P?ijímám data',
	'Layers': 'Vrstvy',
	'No match': 'Žádná shoda',
	'Loading...': 'Na?ítání...',
	'Bookmarks': 'Záložky',
	'Places': 'Místa',
	'Unknown': 'Neznámý',
	'Feature Info unavailable': 'Informace o prvku nejsou k dispozici',
	'Pan': '<b>Posuv</b><br>Držení levého tla?ítka myši nad mapou posouvá aktuální<br>pohled; kole?ko myši a pozice kurzoru nad mapou<br>zárove? ovládá p?iblížení (i v jiných režimech než Posuv)',
	'Measure length': '<b>M??ení délky</b><br>Každé klepnutí na levé tla?ítko myši nad mapou vytvo?í<br>bod m??eného úseku, dvojité klepnutí m??ení ukon?í',
	'Measure area': '<b>M??ení plochy</b><br>Každé klepnutí na levé tla?ítko myši nad mapou vytvo?í<br>bod m??eného polygonu, dvojité klepnutí m??ení ukon?í',
	'Leg': 'Úsek',
	'Length': 'Délka',
	'Area': 'Plocha',
	'Result >': 'Výsledek >',
	'< Search': '< Hledat',
	'Search': 'Hledat',
	'Search Nominatim': 'Hledat (pomocí OSM Nominatim) podle názvu a adresy',
	'Search OpenLS': 'Hledat pomocí služby OpenLS',
	'Search PDOK': 'Vložit (?ást) ?eské národní adresy',
	'Searching...': 'Hledání...',
	'Search Completed: ': 'Hledání dokon?eno: ',
	'services': 'služby',
	'service': 'službu',
	'Type Nominatim': 'Zadejte název místa nebo adresu...',
	'Overlays': 'P?ekryvné vrstvy',
	'Waiting for': '?ekám na',
	'Warning': 'Varování',
	'Zoom in': '<b>P?iblížit</b><br>Po klepnutí na levé tla?ítko myši na map? p?iblíží pohled,<br>držením tla?ítka a táhnutím lze ozna?it plochu pro p?iblížení',
	'Zoom out': '<b>Oddálit</b><br>Po klepnutí na levé tla?ítko myši na map? oddálí pohled',
	'Zoom to full extent': '<b>Oddálit na plný rozsah</b><br>Oddálí pohled pro plné zobrazení povoleného rozsahu mapy',
	'Zoom previous': '<b>P?edchozí pohled</b><br>P?ejde na p?edchozí pohled (zv?tšení i rozsah)',
	'Zoom next': '<b>Další pohled</b><br>P?ejde na další pohled (zv?tšení i rozsah)',
	'Zoom': 'P?iblížení',

	// 0.68
	'Scale': 'M??ítko',
	'Resolution': 'Rozlišení',
	'Zoom': 'P?iblížení',
	'Create PDF': 'Vytvo?it PDF',
	'Print': 'Tisk',
	'Print Dialog Popup': 'Dialog tisku',
	'Print Visible Map Area': 'Tisk viditelné plochy mapy',

	// 0.70
	'Export': 'Exportovat',
	'Choose a Display Option': 'Vyberte si možnost zobrazení',
	'Display': 'Zobrazení',
	'Grid': 'Tabulka',
	'Tree': 'Strom',
	'XML': 'XML',
	'Invalid export format configured: ': 'Nastaven neplatný formát exportu: ',
	'No features available or none-grid display chosen': 'Nejsou k dispozici žádné prvky nebo nebylo vybráno zobrazení Tabulka',
	'Choose an Export Format': 'Vyberte si formát pro export',
	'Print Visible Map Area Directly': 'P?ímý tisk viditelné plochy mapy',
	'Direct Print Demo': 'Demo P?ímý tisk',
	'This is a simple map directly printed.': 'Toto je jednoduchá p?ímo vytišt?ná mapa.',
	'Print Dialog Popup with Preview Map': '<b>Tisk</b><br>Otev?e okno s náhledem a nastavením tisku',
	'Print Preview': 'Náhled tisku',
	'Print Preview Demo': 'Demo Náhled tisku',
	'Error getting Print options from server: ': 'Chyba p?i získávání nastavení tisku ze serveru: ',
	'Error from Print server: ': 'Chyba tiskového serveru: ',
	'No print provider url property passed in hropts.': 'V hropts není nadefinován url tiskového serveru.',
	'Create PDF...': 'Vytvo?it PDF...',
	'Loading print data...': 'Nahrávání tiskových dat...',

	// 0.71
	'Go to coordinates': 'P?ejít na sou?adnice',
	'Go!': 'P?ejít!',
	'Pan and zoom to location': 'Posunout a p?iblížit na pozici',
	'Enter coordinates to go to location on map': 'Vložte sou?adnice pro p?echod na pozici na map?',
	'Active Themes': 'Aktivní Témata',
	'Move up': 'Posunout nahoru',
	'Move down': 'Posunout dol?',
	'Opacity': 'Opacita',
	'Remove layer from list': 'Odebrat vrstvu ze seznamu',
	'Tools': 'Nástroje',
	'Removing': 'Odebírám',
	'Are you sure you want to remove the layer from your list of layers?': 'Jste si jisti, že chcete odstranit vrstvu z vašeho seznamu vrstev?',
	'You are not allowed to remove the baselayer from your list of layers!': 'Nemáte dovoleno odstranit základní vrstvu z vašeho seznamu vrstev!',
  // 0.72
  'Draw Features': 'Kreslit prvky',

  // 0.73
  'Spatial Search': 'Prostorové vyhledávání',
  'Search by Drawing': 'Hledání podle kresby',
  'Select the Layer to query': 'Vyberte vrstvu na dotaz',
  'Choose a geometry tool and draw with it to search for objects that touch it.': 'Vyberte nástroj pro vytvo?ení geometrie a použijte jej pro hledání objekt?, které se jí dotýkají.',
  'Seconds': 'Sekundy',
  'Working on it...': 'Pracuji na tom...',
  'Still searching, please be patient...': 'Stále hledám, prosím o chvilku strpení...',
  'Still searching, have you selected an area with too many objects?': 'Stále hledám, nevybrali jste oblast s p?íliš mnoha objekty?',
  'as': 'jako',
  'Undefined (check your config)': 'Nedefinován (zkontrolujte konfiguraci)',
  'Objects': 'Objekty',
  'objects': 'objekt?',
  'Features': 'Prvky',
  'features': 'prvk?',
  'Result': 'Výsledek',
  'Results': 'Výsledky',
  'of': 'od',
  'Using geometries from the result: ': 'Pomocí geometrií z výsledku: ',
  'with': 's',
  'Too many geometries for spatial filter: ': 'P?íliš mnoho geometrií pro prostorový filtr: ',
  'Bookmark current map context (layers, zoom, extent)': '<b>Vytvo?it záložku</b><br>Vytvo?í záložku aktuální mapy (vrstvy, p?iblížení, rozsah)',
  'Add a bookmark': 'Vytvo?it záložku',
  'Bookmark name cannot be empty': 'Název záložky nesmí být prázdný',
  'Your browser does not support local storage for user-defined bookmarks': 'Váš prohlíže? neumož?uje lokální úložišt? pro uživatelsky definované záložky',
  'Return to map navigation': 'Zp?t na navigaci v map?',
  'Draw point': 'Kreslit bod',
  'Draw line': 'Kreslit linii',
  'Draw polygon': 'Kreslit polygon',
  'Draw circle (click and drag)': 'Kreslit kruh (klepnutím a tažením)',
  'Draw Rectangle (click and drag)': 'Kreslit pravoúhelník (klepnutím a tažením)',
  'Sketch is saved for use in Search by Selected Features': 'Skica byla uložena pro použití p?i vyhledávání podle vybraných prvk?',
  'Select a search...': 'Vybrat vyhledávání...',
  'Clear': 'Vy?istit',

  // 0.74
  'Project bookmarks': 'Záložky projektu',
  'Your bookmarks': 'Vaše záložky',
  'Name': 'Jméno',
  'Description': 'Popis',
  'Add': 'P?idat',
  'Cancel': 'Storno',
  'Remove bookmark:': 'Odstranit záložku:',
  'Restore map context:': 'Obnovit kontext mapy:',
  'Error: No \'BookmarksPanel\' found.': 'Chyba: Nenalezen \'BookmarksPanel\'.',
  'Input system': 'Sou?adnicový systém',
  'Choose input system...': 'Vyberte sou?adnicový systém...',
  'Map system': 'Sou?adnicový systém',
  'X': 'X',
  'Y': 'Y',
  'Enter X-coordinate...': 'Vložte koordinát X...',
  'Enter Y-coordinate...': 'Vložte koordinát Y...',
  'Choose scale...': 'Vyberte m??ítko...',
  'no zoom': 'žádné p?iblížení',
  'Mode': 'Režim',
  'Remember locations': 'Pamatovat si místa',
  'Hide markers on close': 'P?i zav?ení skrýt zna?ky',
  'Remove markers on close': 'P?i zav?ení odstranit zna?ky',
  'Remove markers': 'Odstranit zna?ky',
  'Location': 'Umíst?ní',
  'Marker position: ': 'Pozice zna?ky: ',
  'No features found': 'Nenalezeny žádné prvky',
  'Feature Info unavailable (you may need to make some layers visible)': 'Informace o prvcích nejsou k dispozici (možná budete muset zviditelnit n?které vrstvy)',
  'Search by Feature Selection': 'Vyhledávání výb?rem prvk?',
  'Download': 'Stažení',
  'Choose a Download Format': 'Vyberte formát souboru',
  'Remove all results': 'Odebrat všechny výsledky',
  'Download URL string too long (max 2048 chars): ': '?et?zec URL ke stažení je p?íliš dlouhý (max 2048 znak?): ',

  // 0.75
  'Query Panel': 'Panel dotaz?',
  'Cancel current search': 'Zrušit aktuální vyhledávání ',
  'Search in target layer using the selected filters': 'Vyhledat v cílové vrtv? za použití vybraných filtr?',
  'Ready': 'P?ipraven',
  'Search Failed': 'Vyhledávání selhalo',
  'Search aborted': 'Vyhledávání zrušeno',

  // 0.76
  'No query layers found': 'Žádné vrstvy dotazu nenalezeny',
  'Edit Layer Style': 'Upravit styl vrstvy',
  'Zoom to Layer Extent': 'P?iblížit na rozsah vrstvy',
  'Get Layer information': 'Získat informace o vrstv?',
  'Change Layer opacity': 'Zm?nit opacitu vrstvy',
  'Select a drawing tool and draw to search immediately': 'Vyberte kreslicí nástroj a nakreslete objekt pro okamžité hledání',
  'Search in': 'Vyhledat v',
  'Search Canceled': 'Vyhledávání zrušeno',
  'Help and info for this example': 'Nápov?da a informace pro tento p?íklad'
};
