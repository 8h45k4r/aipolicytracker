import * as am4core from "@amcharts/amcharts4/core";
import * as am4maps from "@amcharts/amcharts4/maps";
import am4geodata_worldLow from "@amcharts/amcharts4-geodata/worldLow";

export function createPolygonSeries(chart) {
    // Create the MapPolygonSeries
    let polygonSeries = chart.series.push(new am4maps.MapPolygonSeries());

    // Define the map polygon template
    let polygonTemplate = polygonSeries.mapPolygons.template;

    // Set up the tooltip
    polygonTemplate.tooltipText = "{name}\n[font-size: 15px; color: #fff;]{infoHTML}[/]";
    polygonTemplate.tooltipHTML = `
        <div style="font-size: 15px; color: #fff;">
            {name}
        </div>
        <div style="font-size: 15px; color: #fff;">
            <a href="{infoURL}" target="_blank" style="color: #fff;">More Info</a>
        </div>
    `;
    polygonTemplate.tooltip.pointerOrientation = "vertical";
    polygonTemplate.tooltip.background.fill = am4core.color("#000");
    polygonTemplate.tooltip.background.strokeWidth = 0;
    polygonTemplate.tooltip.label.interactionsEnabled = true;
    polygonTemplate.tooltip.keepTargetHover = true;

    // Enable visual center calculation for better positioning
    polygonSeries.calculateVisualCenter = true;

    // Define your data with clickable links
    const data = [
        {
            id: "US",
            name: "United States",
            infoURL: "http://www.example.com",
            polygonSettings: {
                fill: am4core.color(0xff3c38),
            },
        },
        {
            id: "CA",
            name: "Canada",
            infoURL: "http://www.example.com",
            polygonSettings: {
                fill: am4core.color(0xa23e48),
            },
        },
        {
            id: "MX",
            name: "Mexico",
            infoURL: "http://www.example.com",
            polygonSettings: {
                fill: am4core.color(0xff8c42),
            },
        },
        {
            id: "NP",
            name: "Nepal",
            infoURL: "http://www.example.com",
            polygonSettings: {
                fill: am4core.color(0x3498db),
            },
        },
    ];

    // Set data to the series
    polygonSeries.data = data;

    // Add hover state
    let hoverState = polygonTemplate.states.create("hover");
    hoverState.properties.fill = am4core.color("#7997C3");
    hoverState.properties.fillOpacity = 0.8;

    // Configure map polygon settings
    polygonTemplate.propertyFields.fill = "fill"; // Ensure the fill color is applied from data
    polygonSeries.useGeodata = true;
    polygonSeries.geodata = am4geodata_worldLow;

    return polygonSeries;
}
