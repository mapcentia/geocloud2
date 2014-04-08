.. module:: gxp.plugins
    :synopsis: A collection of plugin classes.

:mod:`gxp.plugins`
==================

The :mod:`gxp.plugins` module contains components and classes that add
functionality to other classes. There are two groups of special plugins that
plug into :class:`gxp.Viewer`:

* :class:`gxp.plugins.Tool` and its subclasses are viewer tools. These usually
  consist of one ore more ``Ext.Action`` actions that the viewer adds to a
  toolbar or context menu, and output in an ``Ext.Container`` that will be
  added to the viewer's layout.

* :class:`gxp.plugins.LayerSource` and its subclasses add support for specific
  layer sources to the viewer, and allows it to build layers from a simple
  configuration object.

.. toctree::
   :maxdepth: 1
   :glob:
   
   plugins/*
