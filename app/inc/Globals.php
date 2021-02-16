<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


/**
 * Class that is a namespace for all global GC2 variables
 *
 * Class Globals
 * @package app\inc
 */
class Globals
{
    /**
     * @var int
     */
    public static $cacheTtl = 1;

    /**
     * @var array[]
     */
    static public $metaConfig =[
        [
            "fieldsetName" => "Konflikt",
            "fields" => [
                [
                    "name" => "short_conflict_meta_desc",
                    "type" => "text",
                    "title" => "Short description",
                ],
                [
                    "name" => "long_conflict_meta_desc",
                    "type" => "textarea",
                    "title" => "Long description",
                ],
            ]
        ],
        [
            "fieldsetName" => "Info pop-up",
            "fields" => [
                [
                    "name" => "info_template",
                    "type" => "textarea",
                    "title" => "Pop-up template",
                ],
                [
                    "name" => "info_element_selector",
                    "type" => "text",
                    "title" => "Element selector",
                ],
                [
                    "name" => "info_function",
                    "type" => "textarea",
                    "title" => "Function",
                ],
                [
                    "name" => "select_function",
                    "type" => "textarea",
                    "title" => "Select function",
                ],
                [
                    "name" => "accordion_summery_prefix",
                    "type" => "text",
                    "title" => "Accordion summery prefix",
                ],
                [
                    "name" => "accordion_summery",
                    "type" => "text",
                    "title" => "Accordion summery",
                ],

            ]

        ],
        [
            "fieldsetName" => "Layer type",
            "fields" => [
                [
                    "name" => "vidi_layer_type",
                    "type" => "checkboxgroup",
                    "title" => "Type",
                    "values" => [
                        ["name" => "Tile", "value" => "t"],
                        ["name" => "Vector", "value" => "v"],
                        ["name" => "WebGL", "value" => "w"],
                        ["name" => "MVT", "value" => "mvt"],
                    ],
                    "default" => "t",
                ],
                [
                    "name" => "default_layer_type",
                    "type" => "combo",
                    "title" => "Default",
                    "values" => [
                        ["name" => "Tile", "value" => "t"],
                        ["name" => "Vector", "value" => "v"],
                        ["name" => "WebGL", "value" => "w"],
                        ["name" => "MVT", "value" => "mvt"],
                    ],
                    "default" => "t",
                ],
            ]

        ],
        [
            "fieldsetName" => "Tables",
            "fields" => [
                [
                    "name" => "zoom_on_table_click",
                    "type" => "checkbox",
                    "title" => "Zoom on select",
                    "default" => false,
                ],
                [
                    "name" => "max_zoom_level_table_click",
                    "type" => "text",
                    "title" => "Max zoom level",
                    "default" => "17",
                ],
            ]
        ],
        [
            "fieldsetName" => "Editor",
            "fields" => [
                [
                    "name" => "vidi_layer_editable",
                    "type" => "checkbox",
                    "title" => "Editable",
                    "default" => false,
                ],
            ]

        ],
        [
            "fieldsetName" => "Tile settings",
            "fields" => [
                [
                    "name" => "single_tile",
                    "type" => "checkbox",
                    "title" => "Use tile cache",
                    "default" => false,
                ],
                [
                    "name" => "tiles_service_uri",
                    "type" => "text",
                    "title" => "Tiles service uri",
                ],
                [
                    "name" => "tiles_selected_style",
                    "type" => "textarea",
                    "title" => "Selected style",
                ],
            ]

        ],
        [
            "fieldsetName" => "Vector settings",
            "fields" => [
                [
                    "name" => "load_strategy",
                    "type" => "combo",
                    "title" => "Load strategy",
                    "values" => [
                        ["name" => "Static", "value" => "s"],
                        ["name" => "Dynamic", "value" => "d"],
                    ],
                    "default" => "s",
                ],
                [
                    "name" => "max_features",
                    "type" => "text",
                    "title" => "Max features",
                    "default" => "500",
                ],
                [
                    "name" => "use_clustering",
                    "type" => "checkbox",
                    "title" => "Use clustering",
                    "default" => false,
                ],
                [
                    "name" => "point_to_layer",
                    "type" => "textarea",
                    "title" => "Point to layer",
                ],
                [
                    "name" => "vector_style",
                    "type" => "textarea",
                    "title" => "Style function",
                ],
                [
                    "name" => "show_table_on_side",
                    "type" => "checkbox",
                    "title" => "Show table",
                    "default" => false,
                ],
                [
                    "name" => "reload_interval",
                    "type" => "text",
                    "title" => "Reload Interval",
                ],
                [
                    "name" => "reload_callback",
                    "type" => "textarea",
                    "title" => "Reload callback",
                ],
                [
                    "name" => "disable_vector_feature_info",
                    "type" => "checkbox",
                    "title" => "Disable feature info",
                    "default" => false,
                ],
            ]

        ],
        [
            "fieldsetName" => "Filters",
            "fields" => [
                [
                    "name" => "filter_config",
                    "type" => "textarea",
                    "title" => "Filter config",
                ],
                [
                    "name" => "predefined_filters",
                    "type" => "textarea",
                    "title" => "Predefined filters",
                ],
                [
                    "name" => "default_match",
                    "type" => "combo",
                    "title" => "Default match",
                    "values" => [
                        ["name" => "All", "value" => "all"],
                        ["name" => "Any", "value" => "any"],
                    ],
                    "default" => "any",
                ],
                [
                    "name" => "filter_immutable",
                    "type" => "checkbox",
                    "title" => "Immutable",
                    "default" => false,
                ],
            ]

        ],
        [
            "fieldsetName" => "References",
            "fields" => [
                [
                    "name" => "referenced_by",
                    "type" => "textarea",
                    "title" => "Referenced by",
                ],
            ]
        ],
        [
            "fieldsetName" => "Layer tree",
            "fields" => [
                [
                    "name" => "vidi_sub_group",
                    "type" => "text",
                    "title" => "Sub group",
                ],
                [
                    "name" => "default_open_tools",
                    "type" => "textarea",
                    "title" => "Open tools",
                ],
                [
                    "name" => "disable_check_box",
                    "type" => "checkbox",
                    "title" => "Disable check box",
                    "default" => false,
                ],
            ],
        ],
    ];
}