<?php
namespace app\inc;
class ColorBrewer
{
    static function getQualitative($n = null)
    {
        $r = array(
            array(
                "#a6cee3",
                "#1f78b4",
                "#b2df8a",
                "#33a02c",
                "#fb9a99",
                "#e31a1c",
                "#fdbf6f",
                "#ff7f00",
                "#cab2d6",
                "#6a3d9a",
                "#ffff99",
                "#b15928",
            ),
            array(
                "#8dd3c7",
                "#ffffb3",
                "#bebada",
                "#fb8072",
                "#80b1d3",
                "#fdb462",
                "#b3de69",
                "#fccde5",
                "#d9d9d9",
                "#bc80bd",
                "#ccebc5",
                "#ffed6f",
            ),
            array(
                "#fbb4ae",
                "#b3cde3",
                "#ccebc5",
                "#decbe4",
                "#fed9a6",
                "#ffffcc",
                "#e5d8bd",
                "#fddaec",
                "#f2f2f2",
            ),
            array(
                "#e41a1c",
                "#377eb8",
                "#4daf4a",
                "#984ea3",
                "#ff7f00",
                "#ffff33",
                "#a65628",
                "#f781bf",
                "#999999",
            ),
            array(
                "#7fc97f",
                "#beaed4",
                "#fdc086",
                "#ffff99",
                "#386cb0",
                "#f0027f",
                "#bf5b17",
                "#666666",
            ),
            array(
                "#1b9e77",
                "#d95f02",
                "#7570b3",
                "#e7298a",
                "#66a61e",
                "#e6ab02",
                "#a6761d",
                "#666666",
            ),
            array(
                "#a6cee3",
                "#1f78b4",
                "#b2df8a",
                "#33a02c",
                "#fb9a99",
                "#e31a1c",
                "#fdbf6f",
                "#ff7f00",
            ),
            array(
                "#b3e2cd",
                "#fdcdac",
                "#cbd5e8",
                "#f4cae4",
                "#e6f5c9",
                "#fff2ae",
                "#f1e2cc",
                "#cccccc",
            ),
            array(
                "#66c2a5",
                "#fc8d62",
                "#8da0cb",
                "#e78ac3",
                "#a6d854",
                "#ffd92f",
                "#e5c494",
                "#b3b3b3",
            ),
            array(
                "#8dd3c7",
                "#ffffb3",
                "#bebada",
                "#fb8072",
                "#80b1d3",
                "#fdb462",
                "#b3de69",
                "#fccde5",
            )
        );
        if ($n == null) {
            return $r;
        } else {
            return $r[$n];
        }
    }

    static function getJavaScript()
    {
        $r = self::getQualitative();
        foreach ($r as $k=>$v) {
            echo "{";
            echo "  name: '' +";
            foreach ($v as $c) {
                echo "
                     '<span class=\"color-ramp\" style=\"background-color:{$c};\"></span>' +";
            }
            echo ",\n  value: '{$k}'";
            echo "\n},\n";
        }
    }
}

//ColorBrewer::getJavaScript();