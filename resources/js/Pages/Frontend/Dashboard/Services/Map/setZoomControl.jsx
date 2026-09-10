import * as am4core from "@amcharts/amcharts4/core";
import * as am4maps from "@amcharts/amcharts4/maps";

export function setZoomControl(chart) {
    // Create a new ZoomControl and add it to the chart
    let zoomControl = new am4maps.ZoomControl();
    chart.zoomControl = zoomControl;

    // Position the zoom control in the top right corner
    zoomControl.align = "right";
    zoomControl.valign = "top";
    zoomControl.marginRight = 15;
    zoomControl.marginTop = 15;

    // Customize the appearance if needed
    zoomControl.slider.height = 100;
}
