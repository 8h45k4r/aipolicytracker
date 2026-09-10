import React, { useEffect } from "react";
import * as am5 from "@amcharts/amcharts5";
import * as am5xy from "@amcharts/amcharts5/xy";
import am5themes_Animated from "@amcharts/amcharts5/themes/Animated";
import { list } from "postcss";

const GraphChart = ({ graphData }) => {
    useEffect(() => {
        let root = am5.Root.new("chartdiv");

        // Set themes
        root.setThemes([am5themes_Animated.new(root)]);

        // Create chart
        let chart = root.container.children.push(
            am5xy.XYChart.new(root, {
                panX: true,
                panY: true,
                wheelX: "panX",
                wheelY: "zoomX",
                pinchZoomX: true,
                paddingLeft: 0,
                paddingRight: 1,
            })
        );

        // Add cursor
        let cursor = chart.set("cursor", am5xy.XYCursor.new(root, {}));
        cursor.lineY.set("visible", false);

        // Create X-axis (Months)
        let xRenderer = am5xy.AxisRendererX.new(root, {
            minGridDistance: 30,
            minorGridEnabled: true,
        });

        xRenderer.labels.template.setAll({
            rotation: -90,
            centerY: am5.p50,
            centerX: am5.p100,
            paddingRight: 15,
        });

        let xAxis = chart.xAxes.push(
            am5xy.CategoryAxis.new(root, {
                maxDeviation: 0.3,
                categoryField: "month",
                renderer: xRenderer,
                tooltip: am5.Tooltip.new(root, {}),
            })
        );

        // Create Y-axis (Values)
        let yRenderer = am5xy.AxisRendererY.new(root, {
            strokeOpacity: 0.1,
        });

        let yAxis = chart.yAxes.push(
            am5xy.ValueAxis.new(root, {
                maxDeviation: 0.3,
                renderer: yRenderer,
            })
        );

        // Create series
        let series = chart.series.push(
            am5xy.ColumnSeries.new(root, {
                name: "Series 1",
                xAxis: xAxis,
                yAxis: yAxis,
                valueYField: "value",
                sequencedInterpolation: true,
                categoryXField: "month",
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{valueY}",
                }),
            })
        );

        series.columns.template.setAll({
            cornerRadiusTL: 5,
            cornerRadiusTR: 5,
            strokeOpacity: 0,
        });
        series.columns.template.adapters.add("fill", function (fill, target) {
            return chart.get("colors").getIndex(series.columns.indexOf(target));
        });

        series.columns.template.adapters.add(
            "stroke",
            function (stroke, target) {
                return chart
                    .get("colors")
                    .getIndex(series.columns.indexOf(target));
            }
        );

        let data = graphData.map((list) => {
            return { month: list.month, value: list.value };
        });

        xAxis.data.setAll(data);
        series.data.setAll(data);

        // Make stuff animate on load
        series.appear(1000);
        chart.appear(1000, 100);

        return () => {
            root.dispose();
        };
    }, []);

    return <div id="chartdiv" style={{ width: "100%", height: "400px" }}></div>;
};

export default GraphChart;
