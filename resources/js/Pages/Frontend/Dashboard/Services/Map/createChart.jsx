import * as am4core from "@amcharts/amcharts4/core";
import * as am4maps from "@amcharts/amcharts4/maps";

export function createChart(root) {
    // Create a new MapChart instance
    let chart = root.createChild(am4maps.MapChart);

    // Set map projection
    chart.projection = new am4maps.projections.NaturalEarth1();

    // Disable zooming with the mouse wheel
    chart.wheelable = false;

    // Optional: Add other chart configurations if needed

    return chart;
}
