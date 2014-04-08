Under this directory you will find complete ExtJS themes. Each theme has a set of CSS and a set of images.

Mostly these themes have been created using an online ExtJS theme-builder: http://extbuilder.dynalias.com.

To work with Heron and for some tweaks, each theme below will have a corresponding file
"default-theme-<theme name>.css" e.g. default-theme-greenery.css under heron/resources/css.

Making a new theme is two steps:
- create a theme using http://extbuilder.dynalias.com
- put the generated directory with css and images dirs under this dir with the name of the theme
- create a default-theme-<theme name>.css under heron/resources/css, starting with a copy of an existing theme
css like default-theme-gray.css

To include a theme in your app, see examples/theming/index.html
Example online: http://lib.heron-mc.org/heron/latest/examples/theming

Example index.html head content.

<!-- 1) under ext CSS  -->
	<link rel="stylesheet" type="text/css" href="http://lib.heron-mc.org/ext/3.4.0/resources/css/ext-all.css"/>
	<link rel="stylesheet" type="text/css" href="....resources/themes/greenery/css/xtheme-greenery.css" media="all" />
    <!--[if IE 6]>
    <link rel="stylesheet" type="text/css" media="all" href="resources/themes/greenery/css/xtheme-greenery_ie6.css" />
     <![endif]-->
.
.
<!-- 1) under Heron default CSS  -->
    <link rel="stylesheet" type="text/css" href="resources/css/default.css"/>

    <!-- Need to override some Heron-CSS -->
	<link rel="stylesheet" type="text/css" href="resources/css/default-theme-greenery.css"/>
