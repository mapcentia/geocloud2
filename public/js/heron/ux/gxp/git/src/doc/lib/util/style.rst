.. module:: gxp.util.style
    :synopsis: A collection of style utility functions.

:mod:`gxp.util.style`
=====================

The :mod:`gxp.util.style` module contains style utility functions for use in
GXP applications.


Functions
---------

Public functions.


.. function:: gxp.util.style.interpolateSymbolizers

    :arg start: ``Array`` Array of ``OpenLayers.Symbolizer`` instances
    :arg end: ``Array`` Array of ``OpenLayers.Symbolizer`` instances
    :arg fraction: ``Float`` Number between 0 and 1, which is the distance
        between ``start`` and ``end``
    :returns: ``Array`` Array of ``OpenLayers.Symbolizer instances
    
    Interpolates an array of symbolizers between start and end values.


