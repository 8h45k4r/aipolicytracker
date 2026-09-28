// The world map, built with amCharts 5. Imported on demand by public.js, so the
// library (and the geodata) only load on a page that has a [data-world-map] element.
import * as am5 from '@amcharts/amcharts5';
import * as am5map from '@amcharts/amcharts5/map';
import am5geodata_worldLow from '@amcharts/amcharts5-geodata/worldLow';

// Brand colours, one per level; the legend swatches use the same values (public.css).
const COLOURS = { in_force: 0x002147, binding: 0x006aac, guidance: 0x8fb8d8, none: 0xe3e8ee };

export function mount(el) {
    const source = document.getElementById(el.dataset.worldMap);
    if (!source) { return; }
    const countries = JSON.parse(source.textContent || '[]');
    const byId = Object.fromEntries(countries.map((c) => [c.id, c]));

    const root = am5.Root.new(el);
    root.numberFormatter.set('numberFormat', '#');
    const chart = root.container.children.push(am5map.MapChart.new(root, {
        panX: 'rotateX', panY: 'none', projection: am5map.geoNaturalEarth1(), wheelY: 'none', maxPanOut: 0,
    }));

    const series = chart.series.push(am5map.MapPolygonSeries.new(root, {
        geoJSON: am5geodata_worldLow, exclude: ['AQ'], valueField: 'value', calculateAggregates: false,
    }));
    series.mapPolygons.template.setAll({
        tooltipText: '{name}', interactive: true, stroke: am5.color(0xffffff), strokeWidth: 0.5, fill: am5.color(COLOURS.none),
    });
    series.mapPolygons.template.states.create('hover', { fillOpacity: 0.8 });

    series.mapPolygons.template.adapters.add('fill', (fill, target) => {
        const c = byId[target.dataItem?.get('id')];
        return c ? am5.color(COLOURS[c.level] ?? COLOURS.none) : fill;
    });
    series.mapPolygons.template.adapters.add('tooltipText', (text, target) => {
        const c = byId[target.dataItem?.get('id')];
        if (!c) { return '{name}: not covered yet'; }
        return `[bold]${c.name}[/]\n${c.label}${c.via ? ` (via the ${c.via})` : ''}\n${c.instruments} instrument${c.instruments === 1 ? '' : 's'} on record`;
    });
    series.mapPolygons.template.events.on('click', (ev) => {
        const c = byId[ev.target.dataItem?.get('id')];
        if (c) { window.location.href = c.url; }
    });
    series.mapPolygons.template.adapters.add('cursorOverStyle', (style, target) => (byId[target.dataItem?.get('id')] ? 'pointer' : style));

    chart.set('zoomControl', am5map.ZoomControl.new(root, {}));
    chart.appear(600, 100);
}
