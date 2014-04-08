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
 *  class = Heron.i18n.dict (fr_FR)
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.form.ComboBox>`_
 */

/**
 * Define dictionary for the FR locale.
 * Maintained by: Heron devs
 */
Heron.i18n.dict = {
    // 0.67
	'Active Layers' : 'Couches actives',
	'Base Layer': 'Couche de base',
	'Base Layers': 'Couches de base',
	'BaseMaps': 'Carte de base',
	'Choose a Base Layer': 'Choisissez une couche de base',
	'Legend': 'Légende',
	'Feature Info': 'Élément Information',
	'Feature Data': 'Élément Données',
	'Feature(s)': 'Élément(s)',
	'No layer selected': 'Aucune couche sélectionée',
	'Save Features': 'Sauver les attributs',
	'Get Features': 'Obtenir les attributs',
	'Feature information': 'Attribut introuvable',
	'No Information found': 'Aucune information',
	'Layer not added': 'Couche non ajoutée',
	'Attribute': 'Attribut',
	'Value': 'Valeur',
	'Recieving data':'Réception en cours',
	'Layers': 'Couchess',
	'No match': 'Aucun résultat',
	'Loading...': 'Chargement...',
	'Bookmarks': 'Signets',
	'Places': 'Places',
	'Unknown': 'Inconnu',
	'Feature Info unavailable':'Pas d\'information disponible pour l\'élément',
	'Pan': 'Déplacer',
	'Leg' : 'Branche',
	'Measure length': 'Mesure de la longueur',
	'Measure area': 'Mesusre de surface',
	'Length': 'Longueur',
	'Area': 'Surface',
	'Result >': 'Résultat >',
	'< Search': '< Recherche',
	'Search': 'Recherche',
	'Search Nominatim': 'Recherche (OSM) par nom et adresse',
	'Search OpenLS' : 'Recherche avec le service OpenLS',
	'Search PDOK': 'Type (partie des) adresses nationales Pays-Bas',
	'Searching...': 'Recherche en cours...',
	'Search Completed: ': 'Recherche terminée: ',
	'services':'services',
	'service':'service',
	'Type Nominatim': 'Entrez un lieu dît ou une adresse...',
	'Overlays': 'Superpositions',
	'Waiting for': 'En attente de',
	'Warning': 'Attention',
	'Zoom in': 'Zoom avant',
	'Zoom out': 'Zoom arrière',
	'Zoom to full extent':'Zoom carte complète',
	'Zoom previous': 'Zoom précédent',
	'Zoom next': 'Zoom suivant',

    // 0.68
	'Scale': 'Echelle',
	'Resolution': 'Résolution',
	'Zoom': 'Niveau de zoom',

    // 0.70
	'Export': 'Export',
	'Choose a Display Option' : 'Choisissez une option d\'affichage',
	'Display' : 'Affichage',
	'Grid' : 'Tableau',
	'Tree' : 'Arbre',
	'XML' : 'XML',
	'Invalid export format configured: ' : 'Format d\'export invalide: ',
	'No features available or non-grid display chosen' : 'Pas d\'élément disponible ou option d\'affichage en mode tableau non sélectionnée',
	'Choose an Export Format' : 'Choisissez un format d\'export',
	'Print Visible Map Area Directly' : 'Imprimer directement la carte visible',
	'Direct Print Demo' : 'Impression directe démo',
	'This is a simple map directly printed.' : 'Ceci est une simple carte imprimée directement.',
	'Print Dialog Popup with Preview Map' : 'Fenêtre d\'option d\'impression avec prévisualisation',
	'Print Preview' : 'Prévisualisation d\'impression',
	'Print Preview Demo' : 'Prévisualisation d\'impression Démo',
	'Error getting Print options from server: ' : 'Erreur de récupération des options d\'impression du serveur: ',
	'Error from Print server: ' : 'Erreurs du serveur d\'impression: ',
	'No print provider url property passed in hropts.' : 'Aucune url d\'un fournisseur d\'impression n\'est renseignée dans hropts.',
	'Create PDF...' : 'Créer PDF...',
	'Loading print data...' : 'Chargement des données d\'impressions...',

	 // 0.71
	'Go to coordinates': 'Aller aux coordonnées',
	'Go!': 'Ok',
	'Pan and zoom to location': 'Déplacement et zoom vers l\'endroit',
	'Enter coordinates to go to location on map': 'Entrer les coordonnées pour recentrage',
	'Active Themes': 'Thèmes actifs',
	'Move up': 'Vers le haut',
	'Move down': 'Vers le bas',
	'Opacity': 'Opacité',
	'Remove layer from list': 'Supprimer la couche de la liste',
	'Tools': 'Outils',
	'Removing': 'Suppresion',
	'Are you sure you want to remove the layer from your list of layers?': 'Êtes-vous sûr de supprimer cette couche de votre liste de couches ? :',
	'You are not allowed to remove the baselayer from your list of layers!': 'Vous n\'êtes pas autorisé à supprimer la couche de base de votre liste de couches !',

	// 0.72
	'Draw Features': 'Outils dessin',

    // 0.73
    'Spatial Search': 'Recherche spatiale',
    'Search by Drawing': 'Recherche par dessin',
    'Select the Layer to query': 'Sélectionner la couche à interroger',
    'Choose a geometry tool and draw with it to search for objects that touch it.': 'Choisisser un outil géométrique pour dessiner une recherche d\'éléments qui le touche.',
    'Seconds': 'Secondes',
    'Working on it...': 'Travail en cours...',
    'Still searching, please be patient...': 'La recheche continue, merci de votre patience ...',
    'Still searching, have you selected an area with too many objects?': 'Recherche toujours en cours, Auriez-vous sélectionné une zone avec trop d\'objets ?',
    'as': 'comme',
    'Undefined (check your config)': 'Indéfini(e) (vérifier votre configuration)',
    'Objects': 'Objets',
    'objects': 'objets',
    'Features': 'Éléments',
    'features': 'éléments',
    'Result': 'Résultat',
    'Results': 'Résultats',
    'of': 'de',
    'Using geometries from the result: ': 'Utiliser les géométries du résultat: ',
    'with': 'avec',
    'Too many geometries for spatial filter: ': 'Trop de géométries pour le filtre spatial : ',
    'Bookmark current map context (layers, zoom, extent)': 'Ajouter un signet de la carte courant (couches, niveau de zoom, étendue)',
    'Add a bookmark': 'Créer un signet',
    'Bookmark name cannot be empty': 'Le nom du signet ne peut être vide',
    'Your browser does not support local storage for user-defined bookmarks': 'Votre navigateur ne dipose pas du support de stockage local pour les signets utilisateurs',
    'Return to map navigation': 'Retourner à la carte de navigation',
    'Draw point': 'Dessiner point',
    'Draw line': 'Dessin ligne',
    'Draw polygon': 'Dessin polygone',
    'Draw circle (click and drag)': 'Dessin cercle (clic et tirer)',
    'Draw Rectangle (click and drag)': 'Dessin rectangle (clic et tirer)',
    'Sketch is saved for use in Search by Selected Features': 'Ébauche sauvée pour utilisation dans la recherche by éléments sélectionnés',
    'Select a search...': 'Selectionner une recherche ...',
    'Clear': 'Annuler'
};
