## Documentation

### About

**OpenLayers Editor** (ole) provides a set of controls for extended editing of spatial data.

### Example

    editor = new OpenLayers.Editor(map, {
        activeControls: ['SnappingSettings', 'SplitFeature', 'MergeFeature', 'CleanFeature'],
        featureTypes: ['polygon', 'path', 'point', 'text']
    });
    editor.startEditMode();

See [`/examples`](https://github.com/geops/ole/tree/master/examples/) for some inspiration.

### Requirements

* [OpenLayers](http://openlayers.org/)

### Server-side processing

Some controls like merge, split and clean depend on server-side processing.

Examples for server-side processing and CRUD operations are available for several backends:

* [Drupal module](https://drupal.org/project/ole)
* [Zend Framework module](https://github.com/geops/ole-zend)
* [MapFish app](https://github.com/geops/ole-mapfish)
* [SharpMap](http://sharpmap.codeplex.com/)

Set `editor.oleUrl` according to your server configuration.
