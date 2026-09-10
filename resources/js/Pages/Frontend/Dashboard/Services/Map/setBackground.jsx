import * as am4core from "@amcharts/amcharts4/core";
import * as am4maps from "@amcharts/amcharts4/maps";

export function setBackground(chart) {
    // Create a new background color for the map container
    chart.background.fill = am4core.color("#d4f1f9"); // Set the background color
    chart.background.fillOpacity = 1; // Set the background opacity
}
