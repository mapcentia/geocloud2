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
 *  class = Heron.i18n.dict (es_ES)
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.form.ComboBox>`_
 */

/**
 * Define dictionary for the ES locale.
 * Maintained by: Jose Luis Racero gilmoth@gmail.com
 */
Heron.i18n.dict = {
    // 0.67
	'Active Layers' : 'Capas Activas',
	'Base Layer': 'Capa Base',
	'Base Layers': 'Capas Base',
	'BaseMaps': 'Mapas Base',
	'Choose a Base Layer': 'Elija Capa Base',
	'Legend': 'Leyenda',
	'Feature Info': 'Informaci&oacute;n del elemento ',
	'Feature Data': 'Datos del elemento',
	'Feature(s)': 'elementos(s)',
	'No layer selected': 'Ninguna capa selecionada',
	'Save Features': 'Guardar Forma',
	'Get Features': 'Obtener Forma',
	'Feature information': 'Informaci&oacute;n del elemento',
	'No Information found': 'No se ha encontrado informaci&oacute;n',
	'Layer not added': 'Capa no a&ntilde;adida',
	'Attribute': 'Atributos',
	'Value': 'Valor',
	'Recieving data':'Recibiendo datos',
	'Layers': 'Capas',
	'No match': 'No match',
	'Loading...': 'Cargando...',
	'Bookmarks': 'Localizaciones',
	'Places': 'Lugares',
	'Unknown': 'Desconocido',
	'Feature Info unavailable':'Información del elemento no disponible',
	'Pan': 'Mover mapa',
	'Leg' : 'Etapa',
	'Measure length': 'Medir distancia',
	'Measure area': 'Medir Area',
	'Length': 'Longitud',
	'Area': 'Area',
	'Result >': 'Resultado >',
	'< Search': '< Busqueda',
	'Search': 'Busqueda',
	'Search Nominatim': 'Busqueda (OSM) por nombre y direcci&oacute;n',
	'Search OpenLS' : 'Buscar con servicio OpenLS',
	'Search PDOK': 'Type (parts of) Dutch national address',
	'Searching...': 'Buscando...',
	'Search Completed: ': 'Busqueda completada: ',
	'services':'servicios',
	'service':'servicio',
	'Type Nominatim': 'Escribe un lugar o direcci&oacute;n...',
	'Overlays': 'Overlays',
	'Waiting for': 'Esperando',
	'Warning': 'Warning',
	'Zoom in': 'Acercar Zoom',
	'Zoom out': 'Alejar Zoom',
	'Zoom to full extent':'Zoom vista general',
	'Zoom previous': 'Zoom anterior',
	'Zoom next': 'Zoom siguiente',

	// 0.68
	'Scale': 'Escala',
	'Resolution': 'Resoluci&#211;n',
	'Zoom': 'Nivel de zoom',

    // 0.70
	'Export': 'Exportar',
	'Choose a Display Option' : 'Elija una vista',
	'Display' : 'Mostrar',
	'Grid' : 'Grilla',
	'Tree' : 'Arbol',
	'XML' : 'XML',
	'Invalid export format configured: ' : 'Formato de exportacion invalido: ',
	'No features available or non-grid display chosen' : 'No hay objetos que mostrar o no se eligió vista de grilla',
	'Choose an Export Format' : 'Elija un formato de exportación',
	'Print Visible Map Area Directly' : 'Imprimir area visible del mapa',
	'Direct Print Demo' : 'Demo de Impresión Directa',
	'This is a simple map directly printed.' : 'Este es un mapa impreso directamente.',
	'Print Dialog Popup with Preview Map' : 'Dialogo de impresion con previsualizacion',
	'Print Preview' : 'Previsualizacion de impresion',
	'Print Preview Demo' : 'Demo: Previsualizacion de impresion',
	'Error getting Print options from server: ' : 'Error en opciones de impresión del servidor: ',
	'Error from Print server: ' : 'Error del servidor de impresion: ',
	'No print provider url property passed in hropts.' : 'No se paso proveedor de impresion en hropts.',
	'Create PDF...' : 'Crear PDF...',
	'Loading print data...' : 'Cargando datos de impresion...',

	 // 0.71
	'Go to coordinates': 'Ir a coordenadas',
	'Go!': 'Ir!',
	'Pan and zoom to location': 'Ir al lugar',
	'Enter coordinates to go to location on map': 'Ingrese coordenadas para ir a un lugar del mapa',
	'Active Themes': 'Temas Activos',
	'Move up': 'Subir',
	'Move down': 'Bajar',
	'Opacity': 'Opacidad',
	'Remove layer from list': 'Quitar capa de la lista',
	'Tools': 'Herramientas',
	'Removing': 'Quitando',
	'Are you sure you want to remove the layer from your list of layers?': 'Está seguro que desea quitar la capa de su lista?',
	'You are not allowed to remove the baselayer from your list of layers!': 'No está permitido quitar capas base de la lista!',

	// 0.72
	//'Draw Features': 'Draw Features',

    // 0.73
    'Spatial Search': 'Busqueda espacial',
    'Search by Drawing': 'Busqueda por dibujo',
    'Select the Layer to query': 'Elegir la capa para buscar',
    'Choose a geometry tool and draw with it to search for objects that touch it.': 'Elija una herramienta y dibuje para buscar objetos que toquen la geometría.',
    'Seconds': 'Segundos',
	'Working on it...':'Trabajando...',
	'Still searching, please be patient...':'Todavía buscando, por favor tenga paciencia...',
	'Still searching, have you selected an area with too many objects?':'En progreso, puede que haya seleccionado un area con demasiados objetos?',
	'as': 'como',
    'Undefined (check your config)': 'Indefinido (vea su configuracion)',
    'Objects': 'Objetos',
    'objects': 'objetos',
    'Features': 'Objetos',
    'features': 'objetos',
    'Result': 'Resultado',
    'Results': 'Resultados',
    'of': 'de',
    'Using geometries from the result: ': 'Usando geometrias del resultado: ',
    'with': 'con',
    'Too many geometries for spatial filter: ': 'Demasiadas geometrías para el filtro espacial: ',
    'Bookmark current map context (layers, zoom, extent)': 'Guardar vista del mapa (capas, zoom, recuadro)',
    'Add a bookmark': 'Agregar marcador',
    'Bookmark name cannot be empty': 'El nombre del marcador no puede ser nulo',
    'Your browser does not support local storage for user-defined bookmarks': 'Su navegador no soporta almacenamiento local para marcadores de usuario',
    'Return to map navigation': 'Volver al mapa',
    'Draw point': 'Dibujar punto',
    'Draw line': 'Dibujar línea',
    'Draw polygon': 'Dibujar poligono',
    'Draw circle (click and drag)': 'Dibujar círculo (click y arrastre)',
    'Draw Rectangle (click and drag)': 'Dibujar Rectangulo (click y arrastre)',
    'Sketch is saved for use in Search by Selected Features': 'El borrador fue guardado para su uso en la busqueda por cruce de capas',
    'Select a search...': 'Seleccione una búsqueda...',
    'Clear': 'Limpiar',

    // 0.74
    'Project bookmarks': 'Marcadores del proyecto',
    'Your bookmarks': 'Sus marcadores',
    'Name': 'Nombre',
    'Description': 'Descripción',
    'Add': 'Agregar',
    'Cancel': 'Cancelar',
    'Remove bookmark:': 'Quitar marcador:',
    'Restore map context:': 'Restaurar mapa:',
    'Error: No \'BookmarksPanel\' found.': 'Error: No se encontró \'Panel de marcadores\'.',
    'Input system': 'Sistema de entrada',
    'Choose input system...': 'Elegir sistema de entrada...',
    'Map system': 'Sistema del mapa',
    'X': 'X',
    'Y': 'Y',
    'Enter X-coordinate...': 'Ingrese coordenada X...',
    'Enter Y-coordinate...': 'Ingrese coordenada Y...',
    'Choose scale...': 'Elija escala...',
    'no zoom': 'sin zoom',
    'Mode': 'Modo',
    'Remember locations': 'Recordar posiciones',
    'Hide markers on close': 'Esconder marcas al cerrar',
    'Remove markers on close': 'Quitar marcas al cerrar',
    'Remove markers': 'Quitar marcas',
    'Location': 'Posicion',
    'Marker position: ': 'Posicion de la marca: ',
    'No features found': 'No se encontraron objetos',
    'Feature Info unavailable (you may need to make some layers visible)': 'No es posible dar información (puede que tenga que encender alguna capa)',
    'Search by Feature Selection': 'Busqueda por seleccion de objetos',
    'Download': 'Descarga',
    'Choose a Download Format': 'Elija formato de descarga',
    'Remove all results': 'Quitar resultados',
    'Download URL string too long (max 2048 chars): ': 'Dirección de descarga demasiado larga(maximo 2048 caracteres): ',

	// 0.75
	'Draw Features': 'Herramientas de dibujo',
	'Search in target layer using the selected filters':'Buscar en la capa destino usando los filtros seleccionados',
	'Cancel current search':'Cancelar busqueda en curso',
	'Cancel ongoing search':'Cancelar busqueda en curso',
	'This field is required':'Campo requerido',
	'Select a drawing tool and draw to search immediately.':'Seleccione una herramienta y dibuje para buscar.',
	'Choose Layer to select with':'Elegir capa para seleccionar',
	'Select a source Layer and then draw to select objects from that layer. <br/>Then select a target Layer to search in using the geometries of the selected objects.':'Dibuje sobre una capa para seleccionar. <br/>Luego elija una capa para buscar usando la geometría de los objetos seleccionados.',
	'Choose a Layer':'Elija una capa',
	'Select a target layer to search using the geometries of the selected objects':'Elija una capa para buscar usando la geometría de los objetos seleccionados',
	'Select a draw tool and draw to select objects from':'Elija una herramienta y dibuje para seleccionar objetos de la capa',
	'No objects selected':'No se han seleccionado objetos',

	//0.76
	'My Upload':'Capa Agregada',
	'Upload features from local file':'Cargar geometrias desde archivo',
	'oleUploadFeatureReplace':'Reemplazar geometrias en la capa',
    'No query layers found': 'No se encontraron layers de búsqueda',
    'Edit Layer Style': 'Editar estilo de la capa',
    'Zoom to Layer Extent': 'Zoom a la extensión de la capa',
    'Get Layer information': 'Información de la capa',
    'Change Layer opacity': 'Cambiar opacidad',
    'Select a drawing tool and draw to search immediately': 'Seleccione una herramienta y dibuje para buscar',
    'Search in': 'Buscar en',
    'Search Canceled': 'Búsqueda cancelada',
    'Help and info for this example': 'Ayuda para este ejemplo'
};
