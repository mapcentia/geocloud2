.. module:: gxp.util.color
    :synopsis: A collection of color utility functions.

:mod:`gxp.util.color`
=====================

The :mod:`gxp.util.color` module contains color utility functions for use in
GXP applications.


Functions
---------

Public functions.


.. function:: gxp.util.color.hex

    :arg rgb: ``Array`` Decimal r, g and b values of a color
    :returns: ``String`` hex value for the color
    
    Converts rgb color values to a hex color.

.. function:: gxp.util.color.hsl2rgb

    :arg hsl: ``Array`` h, s and l color values
    :returns: ``Array`` Decimal r, g and b values of a color
    
    Converts hsl values to rgb values.

.. function:: gxp.util.color.rgb

    :arg hex: ``String`` A hex css color or a css color name
    :returns: ``Array`` Decimal r, g, and b values for the color
    
    Converts a hex color or color name to rgb values.

.. function:: gxp.util.color.rgb2hsl

    :arg rgb: ``Array`` Decimal r, g and b values of a color
    :returns: ``Array`` h, s and l color values
    
    Converts rgb values to hsl values.


