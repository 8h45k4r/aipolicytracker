import * as am4core from "@amcharts/amcharts4/core";
import * as am4themes_animated from "@amcharts/amcharts4/themes/animated";

export function setThemes(chart) {
    // Apply the animated theme to the chart
    am4core.useTheme(am4themes_animated);
}
