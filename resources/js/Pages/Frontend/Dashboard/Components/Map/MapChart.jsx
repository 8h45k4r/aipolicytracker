import React, { useEffect } from "react";
import * as am4core from "@amcharts/amcharts4/core";
import * as am4maps from "@amcharts/amcharts4/maps";
import am4geodata_worldLow from "@amcharts/amcharts4-geodata/worldLow";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";

// Define colors for categories
const COLORS = {
    cancelled: "#ec8596",
    research: "#B5DED7",
    whitepaper: "#F4F8FC",
    pilot: "#F1CDB2",
    development: "#AFA3DA",
    launched: "#667A91"
    // launched: "#67A8EF"
};

export function MapChart({
    countrywithStatus,
    countrywithMap,
    countryWithAiPolicies
}) {

    // Define countries and their associated colors
    const COLOR_MAP = Object.keys(countrywithStatus).reduce((acc, countryCode) => {
        const status = countrywithStatus[countryCode];
        acc[countryCode] = COLORS[status] || '#FFFFFF'; // Default color if status not found
        return acc;
    }, {});

    const truncateText = (text, wordLimit) => {
        const words = text.split(' ');
        if (words.length > wordLimit) {
            return words.slice(0, wordLimit).join(' ') + '...';
        }
        return text;
    };

    useEffect(() => {
        // Apply themes
        am4core.useTheme(am4themes_animated);

        // Create map instance
        let chart = am4core.create("chartdiv", am4maps.MapChart);

        // Set map definition
        chart.geodata = am4geodata_worldLow;

        // Set projection
        chart.projection = new am4maps.projections.Miller();

        // Create map polygon series
        let polygonSeries = chart.series.push(new am4maps.MapPolygonSeries());
        polygonSeries.useGeodata = true;

        // Exclude Antarctica
        polygonSeries.exclude = ["AQ"];

        // Configure series
        let polygonTemplate = polygonSeries.mapPolygons.template;
        polygonTemplate.tooltipText = "{name}";

        // Add colors to countries
        polygonTemplate.adapter.add("fill", (fill, target) => {
            const countryId = target.dataItem && target.dataItem.dataContext ? target.dataItem.dataContext.id : null;
            return COLOR_MAP[countryId] || fill; // Use predefined color or default fill color
        });

        // Enable tooltip interaction and keep target hovered
        polygonSeries.tooltip.label.interactionsEnabled = true;
        polygonSeries.tooltip.keepTargetHover = true;

        // Fix tooltip position and enable visual center calculation
        polygonSeries.calculateVisualCenter = true;
        polygonTemplate.tooltipPosition = "fixed";

        // Zoom control
        let zoomControl = new am4maps.ZoomControl();
        chart.zoomControl = zoomControl;

        // Position the zoom control at the top right
        zoomControl.layout = "vertical";
        zoomControl.valign = "top";
        zoomControl.align = "right";
        zoomControl.marginRight = 15; // Adjust as needed
        zoomControl.marginTop = 15;   // Adjust as needed

        // Set URLs in tooltips and dynamically generate the HTML for policy links
        polygonTemplate.adapter.add("tooltipHTML", (html, target) => {
            const countryId = target.dataItem && target.dataItem.dataContext ? target.dataItem.dataContext.id : null;
            const policies = countryWithAiPolicies[countryId] || [];

            let policyLinks = policies.map(policy => `
                <div style="padding: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <a href="${policy.url}" target="_blank" style="text-decoration: none; color: #002147;">
                        <i className="fa fa-regular fa-star" style="margin-right: 0.75rem;"></i>
                        ${truncateText(policy.name, 5)}
                    </a>
                    <div style="display: flex; align-items: center;">
                        <a href="${policy.url}" style="font-size: 1rem; font-weight: 400; text-align: center; text-decoration: none; color: #002147;" target="_blank">
                            <i className="fa fa-arrow-circle-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            `).join('');

            if (!policyLinks) {
                policyLinks = "<div style='padding: 8px;'>No policies available</div>";
            }

            return `
                <div style="width: 18rem; border-radius: 0.25rem; padding: 0;">
                    <div style="padding: 0.75rem 1.25rem; border-bottom: 2px solid #f8f9fa; border-radius: 0.25rem 0.25rem 0 0; margin: 0;">
                        <h2 style="margin: 0; color: #002147; font-size: 1rem; font-weight: 400;">{name}</h2>
                    </div>
                    <div style="padding: 12px;">
                        <div>
                            ${policyLinks}
                        </div>
                    </div>
                </div>
            `;
        });

        return () => {
            if (chart) {
                chart.dispose();
            }
        };
    }, [countrywithStatus, countryWithAiPolicies]);

    return (
        <div id="chartdiv" className="map-chart-wrapper" style={{ width: "100%", height: "500px" }}></div>
    );
}
