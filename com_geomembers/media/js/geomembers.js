/**
 * GeoMembers – rendu des cartes de répartition (SVG, d3-geo + topojson-client).
 *
 * @copyright  Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
(() => {
  'use strict';

  const W = 1000;
  const topoCache = {};
  const loadTopo = (url) => {
    if (!topoCache[url]) {
      topoCache[url] = fetch(url).then((r) => {
        if (!r.ok) { throw new Error(url + ' : ' + r.status); }
        return r.json();
      });
    }
    return topoCache[url];
  };

  const palette = ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f', '#edc948', '#b07aa1', '#ff9da7',
    '#9c755f', '#bab0ac', '#86bcb6', '#d37295', '#8cd17d', '#b6992d', '#499894', '#f1ce63', '#a0cbe8', '#ffbe7d'];

  function makeProjection(type, fc) {
    switch (type) {
      case 'lambert93':
        return d3.geoConicConformal().parallels([44, 49]).rotate([-3, 0]);
      case 'mercator':
        return d3.geoMercator();
      case 'naturalEarth':
        return d3.geoNaturalEarth1();
      case 'conic': {
        const b = d3.geoBounds(fc);
        const lat0 = b[0][1];
        const lat1 = b[1][1];
        return d3.geoConicConformal()
          .rotate([-(b[0][0] + b[1][0]) / 2, 0])
          .parallels([lat0 + (lat1 - lat0) / 6, lat1 - (lat1 - lat0) / 6]);
      }
      default:
        return d3.geoEqualEarth();
    }
  }

  /** Plus grand polygone d'une géométrie (pour placer une étiquette). */
  function largestPolygon(geometry, path) {
    if (!geometry) { return null; }
    if (geometry.type === 'Polygon') { return geometry; }
    if (geometry.type !== 'MultiPolygon') { return geometry; }
    let best = null;
    let bestArea = -1;
    geometry.coordinates.forEach((coords) => {
      const poly = { type: 'Polygon', coordinates: coords };
      const area = path.area(poly);
      if (area > bestArea) { bestArea = area; best = poly; }
    });
    return best;
  }

  /** Oriente les anneaux comme l'attend d3 (sinon un pays devient « le monde sauf ce pays »). */
  function rewind(g) {
    if (!g) { return g; }
    const polys = g.type === 'Polygon' ? [g.coordinates] : (g.type === 'MultiPolygon' ? g.coordinates : []);
    polys.forEach((rings) => {
      if (d3.geoArea({ type: 'Polygon', coordinates: rings }) > 2 * Math.PI) { rings.forEach((ring) => ring.reverse()); }
    });
    return g;
  }

  /** Partie principale d'une entité (ex. France métropolitaine sans l'outre-mer), pour cadrer un encart. */
  function mainPart(feature) {
    const g = feature.geometry;
    if (!g || g.type !== 'MultiPolygon') { return feature; }
    let best = null;
    let bestArea = -1;
    g.coordinates.forEach((coords) => {
      const poly = { type: 'Polygon', coordinates: coords };
      const area = d3.geoArea(poly);
      if (area > bestArea) { bestArea = area; best = poly; }
    });
    return { type: 'Feature', properties: feature.properties, geometry: best };
  }

  function el(tag, attrs, parent) {
    const node = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.entries(attrs || {}).forEach(([k, v]) => { if (v !== null && v !== undefined) { node.setAttribute(k, v); } });
    if (parent) { parent.appendChild(node); }
    return node;
  }

  function html(tag, attrs, parent, text) {
    const node = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => { node.setAttribute(k, v); });
    if (text !== undefined) { node.textContent = text; }
    if (parent) { parent.appendChild(node); }
    return node;
  }

  class GeoMembersMap {
    constructor(container, cfg) {
      this.container = container;
      this.cfg = cfg;
      this.options = cfg.options || {};
      this.strings = cfg.strings || {};
      this.uid = container.id.replace(/[^a-z0-9_-]/gi, '');
      this.grouping = cfg.grouping || 0;
    }

    async init() {
      const cfg = this.cfg;
      const [topo, ftopo] = await Promise.all([
        loadTopo(cfg.layer.geometry),
        cfg.foreign ? loadTopo(cfg.foreign.geometry) : Promise.resolve(null),
      ]);
      this.topo = topo;
      this.object = topo.objects[cfg.layer.object] || Object.values(topo.objects)[0];
      this.object.geometries = this.object.geometries.filter((g) => g.type);
      this.object.geometries.forEach((g) => { g.code = String((g.properties || {})[cfg.layer.codeProperty] ?? g.id); });
      this.features = topojson.feature(topo, this.object).features;
      this.features.forEach((f, i) => { f.code = this.object.geometries[i].code; rewind(f.geometry); });
      this.byCode = new Map(this.features.map((f) => [f.code, f]));

      if (ftopo) {
        const fobj = ftopo.objects[cfg.foreign.object] || Object.values(ftopo.objects)[0];
        this.foreignFeatures = topojson.feature(ftopo, fobj).features.filter((f) => f.geometry);
        this.foreignFeatures.forEach((f) => { f.code = String((f.properties || {})[cfg.foreign.codeProperty] ?? f.id); rewind(f.geometry); });
      }

      this.buildDom();
      this.render();

      // Re-rendu lors d'un changement de thème clair / sombre
      const rerender = () => { window.requestAnimationFrame(() => this.render()); };
      new MutationObserver(rerender).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme', 'class'] });
      let width = this.figure.clientWidth;
      if (window.ResizeObserver) {
        new ResizeObserver(() => {
          if (Math.abs(this.figure.clientWidth - width) > 40) { width = this.figure.clientWidth; rerender(); }
        }).observe(this.figure);
      }
      if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', rerender);
      }
    }

    name(code, props, fallback, nameProperty) {
      const lang = this.cfg.lang;
      if (props && props[nameProperty + '_' + lang]) { return props[nameProperty + '_' + lang]; }
      return fallback || (props && props[nameProperty]) || code;
    }

    format(value) {
      const t = this.options.threshold || 0;
      if (t > 0 && value > 0 && value < t) { return (this.strings.less || '<') + t; }
      return String(value);
    }

    buildDom() {
      const c = this.container;
      c.innerHTML = '';
      const header = html('div', { class: 'geomembers-header' }, c);

      if (this.options.title) {
        html('div', { class: 'geomembers-title' }, header, this.cfg.dataset.title);
      }

      if (this.options.switcher && this.cfg.groupings.length > 1) {
        const label = html('label', { class: 'geomembers-switcher' }, header);
        html('span', {}, label, this.strings.grouping + ' ');
        const select = html('select', { class: 'form-select form-select-sm' }, label);
        this.cfg.groupings.forEach((g) => {
          const opt = html('option', { value: g.id }, select, g.title);
          if (g.id === this.grouping) { opt.selected = true; }
        });
        select.addEventListener('change', () => { this.grouping = parseInt(select.value, 10); this.render(); });
      }

      this.figure = html('div', { class: 'geomembers-figure' }, c);
      this.tooltip = html('div', { class: 'geomembers-tooltip', role: 'status' }, this.figure);
      this.tooltip.hidden = true;
      this.legend = html('div', { class: 'geomembers-legend' }, c);
      this.footer = html('div', { class: 'geomembers-footer' }, c);
      this.tableBox = html('div', { class: 'geomembers-table' }, c);
    }

    colors() {
      // Couleurs réellement affichées : texte du conteneur et premier fond opaque parmi ses ancêtres
      const cs = getComputedStyle(this.container);
      const text = cs.color || '#212529';
      let bg = '#ffffff';
      for (let el = this.container; el; el = el.parentElement) {
        const c = d3.color(getComputedStyle(el).backgroundColor);
        if (c && c.opacity > 0.5) { bg = c.formatHex(); break; }
      }
      const mix = (t) => d3.interpolateRgb(bg, text)(t);
      // Variables du thème : Bootstrap (--bs-*) ou T4 (--t4-*), sinon teintes calculées
      const get = (name, fallback) => {
        for (const prefix of ['--bs-', '--t4-']) {
          const v = (cs.getPropertyValue(prefix + name) || '').trim();
          if (v) { return v; }
        }
        return fallback;
      };
      return {
        text,
        bg,
        border: get('border-color', mix(0.18)),
        land: get('secondary-bg', mix(0.1)),
        foreign: get('tertiary-bg', mix(0.05)),
        muted: get('secondary-color', mix(0.6)),
      };
    }

    /** Groupes du regroupement courant : [{key, title, color, zones:Set, foreign:[], value, unassigned}] */
    currentGroups() {
      const cfg = this.cfg;
      const grouping = cfg.groupings.find((g) => g.id === this.grouping) || cfg.groupings[0];
      const groups = [];
      const zoneGroup = new Map();

      if (grouping && grouping.groups) {
        grouping.groups.forEach((g, i) => {
          const group = {
            key: 'g' + g.id, title: g.title, color: g.color || palette[i % palette.length],
            zones: g.zones.filter((code) => this.byCode.has(code)), foreign: g.foreign || [], value: 0,
          };
          groups.push(group);
          group.zones.forEach((code) => zoneGroup.set(code, group));
        });
      }

      this.features.forEach((f) => {
        if (!zoneGroup.has(f.code)) {
          const z = cfg.zones[f.code];
          const group = {
            key: 'z' + f.code, title: this.name(f.code, f.properties, z && z.name, cfg.layer.nameProperty),
            color: null, zones: [f.code], foreign: [], value: 0, single: true, unassigned: !!(grouping && grouping.groups),
          };
          groups.push(group);
          zoneGroup.set(f.code, group);
        }
      });

      const foreignGroup = new Map();
      groups.forEach((g) => {
        g.value = g.zones.reduce((s, code) => s + ((cfg.zones[code] || {}).value || 0), 0);
        g.foreign.forEach((code) => {
          foreignGroup.set(code, g);
          g.value += ((cfg.foreign && cfg.foreign.zones[code]) || {}).value || 0;
        });
      });

      return { grouping, groups, zoneGroup, foreignGroup };
    }

    /**
     * @param {{left: boolean, right: boolean}} sides  marges autorisées (une marge vide est retirée au second passage)
     */
    render(sides = { left: true, right: true }) {
      const cfg = this.cfg;
      const opt = this.options;
      const col = this.colors();
      // Couleurs partagées avec la feuille de style (tooltip, légende, pied)
      Object.entries(col).forEach(([k, v]) => this.container.style.setProperty('--gm-' + k, v));
      this.container.classList.toggle('is-dark', d3.hsl(col.bg).l < 0.5);
      // Taille de texte lisible quelle que soit la largeur d'affichage
      this.fontK = Math.min(2.2, Math.max(1, (W / (this.figure.clientWidth || W)) * 1));
      const { grouping, groups, zoneGroup, foreignGroup } = this.currentGroups();
      const isGrouped = !!(grouping && grouping.groups);
      this.state = { groups, zoneGroup, foreignGroup, isGrouped };

      // --- Mise en page
      const insets = (cfg.layer.insets || []).map((i) => ({ ...i, codes: i.codes.filter((code) => this.byCode.has(code)) }))
        .filter((i) => i.codes.length);
      const showInsets = opt.insets !== 0;
      const detached = showInsets ? insets.filter((i) => i.type === 'detached') : [];
      const zooms = showInsets ? insets.filter((i) => i.type === 'zoom') : [];
      const detachedCodes = new Set(insets.filter((i) => i.type === 'detached').flatMap((i) => i.codes));
      const mainFeatures = this.features.filter((f) => !detachedCodes.has(f.code));
      const mainFC = { type: 'FeatureCollection', features: mainFeatures };

      const foreignEntries = cfg.foreign ? Object.entries(cfg.foreign.zones).filter(([, z]) => z.value > 0) : [];
      const hasForeign = !!cfg.foreign;
      // Sans pays en marge, les encarts « zoom » se placent sous la carte (ex. Europe sur la carte du monde)
      const zoomsBelow = !hasForeign && zooms.length > 0;
      // Encarts « zoom » dans la colonne de droite : elle est alors toujours nécessaire
      const zoomsRight = zooms.length > 0 && !zoomsBelow;
      const sideL = hasForeign && sides.left ? 160 : 0;
      const sideR = ((hasForeign && sides.right) || zoomsRight) ? 210 : 0;
      const bottom = detached.length ? 140 : 0;
      const mainX = sideL + 10;
      const mainW = W - sideL - sideR - 20;
      const zoomCodes = new Set(zooms.flatMap((i) => i.codes));

      const projection = makeProjection(cfg.layer.projection, mainFC);
      projection.fitWidth(mainW, mainFC);
      const b0 = d3.geoPath(projection).bounds(mainFC);
      const mainH = Math.min(Math.ceil(b0[1][1] - b0[0][1]), Math.round(W * 0.95));
      projection.fitExtent([[mainX, 10], [mainX + mainW, 10 + mainH]], mainFC);
      const path = d3.geoPath(projection);
      let H = 10 + mainH + 10 + bottom;

      // --- Couleurs (échelle logarithmique quand les effectifs sont très dispersés)
      const max = d3.max(groups, (g) => g.value) || 1;
      const interp = d3['interpolate' + (opt.scheme || 'Blues')] || d3.interpolateBlues;
      const ramp = (t) => interp(0.15 + 0.85 * t);
      const nonzero = groups.map((g) => g.value).filter((v) => v > 0);
      const useLog = max >= 20 && max / (d3.median(nonzero) || 1) > 8;
      const scale = useLog
        ? d3.scaleSequentialLog([1, max], ramp)
        : d3.scaleSequential([0, max], ramp);
      scale.isLog = useLog;
      const fillFor = (group) => {
        if (opt.mode === 'groups' && isGrouped) {
          return group.unassigned ? col.land : group.color;
        }
        if (opt.mode === 'circles') { return col.land; }
        if (group.value <= 0) { return opt.hatchZero ? `url(#gm-hatch-${this.uid})` : col.land; }
        return scale(group.value);
      };

      // --- SVG
      const svg = el('svg', {
        xmlns: 'http://www.w3.org/2000/svg', viewBox: `0 0 ${W} ${H}`, role: 'img',
        'aria-label': cfg.dataset.title + ' – ' + this.strings.total + ' ' + cfg.dataset.total,
        'font-family': getComputedStyle(this.container).fontFamily || 'sans-serif',
      });
      const defs = el('defs', {}, svg);
      const hatch = el('pattern', { id: `gm-hatch-${this.uid}`, width: 6, height: 6, patternUnits: 'userSpaceOnUse', patternTransform: 'rotate(45)' }, defs);
      el('rect', { width: 6, height: 6, fill: col.land }, hatch);
      el('line', { x1: 0, y1: 0, x2: 0, y2: 6, stroke: col.muted, 'stroke-width': 1.2, 'stroke-opacity': 0.6 }, hatch);
      const bgRect = el('rect', { x: 0, y: 0, width: W, height: H, fill: col.bg }, svg);

      const panels = [];
      const mainClip = el('clipPath', { id: `gm-clip-main-${this.uid}` }, defs);
      el('rect', { x: mainX, y: 10, width: mainW, height: mainH }, mainClip);
      const mainG = el('g', { 'clip-path': hasForeign ? `url(#gm-clip-main-${this.uid})` : null }, svg);

      // Pays étrangers en fond (vue avec pays limitrophes)
      const onMapForeign = new Set();
      const drawnForeign = new Set();
      if (hasForeign) {
        const fg = el('g', { class: 'geomembers-foreign' }, mainG);
        // seuls les pays proches de la carte sont dessinés (évite les polygones qui débordent, ex. Antarctique)
        const vb = d3.geoBounds(mainFC);
        const near = (f) => {
          const b = d3.geoBounds(f);
          return b[1][1] > vb[0][1] - 12 && b[0][1] < vb[1][1] + 12 && b[0][0] <= b[1][0]
            && b[1][0] > vb[0][0] - 25 && b[0][0] < vb[1][0] + 25;
        };
        this.foreignFeatures.forEach((f) => {
          if (f.code === cfg.foreign.parentCode || !near(f)) { return; }
          const d = path(f);
          if (!d) { return; }
          const z = cfg.foreign.zones[f.code];
          const grp = foreignGroup.get(f.code);
          let fill = d3.interpolateRgb(col.bg, col.land)(0.75);
          if (z && z.value > 0) {
            fill = (opt.mode === 'groups' && grp) ? grp.color : d3.interpolateRgb(d3.interpolateRgb(col.bg, col.land)(0.75), interp(0.5))(0.3);
          }
          const p = el('path', { d, fill, stroke: col.bg, 'stroke-width': 0.8 }, fg);
          drawnForeign.add(f.code);
          if (z) { this.bindTip(p, () => this.foreignTip(f, z, grp)); }
        });
      }
      panels.push({ g: mainG, path, features: mainFeatures, main: true });

      // Encarts « zoom » (ex. Île-de-France) dans la colonne de droite
      let rightY = 10;
      let belowX = mainX;
      let belowH = 0;
      const belowY = H;
      zooms.forEach((inset) => {
        const feats = inset.codes.map((code) => this.byCode.get(code));
        const fc = { type: 'FeatureCollection', features: zoomsBelow ? feats.map(mainPart) : feats };
        let x;
        let y;
        let w;
        let h;
        let proj;
        if (zoomsBelow) {
          // conique centrée sur l'encart : déformation limitée (ex. Europe)
          w = Math.min(mainW, Math.max(320, (mainW - (zooms.length - 1) * 10) / zooms.length));
          proj = makeProjection('conic', fc);
          proj.fitWidth(w - 16, fc);
          const bb = d3.geoPath(proj).bounds(fc);
          h = Math.min(Math.ceil(bb[1][1] - bb[0][1]) + 34, 520);
          x = belowX;
          y = belowY;
          belowX += w + 10;
          belowH = Math.max(belowH, h + 10);
        } else {
          w = h = sideR - 20;
          x = W - sideR + 10;
          y = rightY;
          proj = makeProjection(cfg.layer.projection, fc);
          rightY += h + 10;
        }
        proj.fitExtent([[x + 8, y + 26], [x + w - 8, y + h - 8]], fc);
        const clipId = `gm-clip-${this.uid}-${x}-${y}`;
        el('rect', { x, y, width: w, height: h }, el('clipPath', { id: clipId }, defs));
        el('rect', { x, y, width: w, height: h, fill: col.bg, stroke: col.border, rx: 6 }, svg);
        const t = el('text', { x: x + 8, y: y + 17, 'font-size': 13 * Math.min(this.fontK, 1.4), 'font-weight': 600, fill: col.text }, svg);
        t.textContent = inset.title;
        // l'encart montre aussi les zones voisines, découpées au cadre
        const insetFeatures = zoomsBelow ? this.features.filter((f) => {
          const c = d3.geoPath(proj).bounds(f);
          return c[1][0] > x && c[0][0] < x + w && c[1][1] > y && c[0][1] < y + h;
        }) : feats;
        panels.push({ g: el('g', { 'clip-path': `url(#${clipId})` }, svg), path: d3.geoPath(proj), features: insetFeatures, zoom: true, box: [x, y, x + w, y + h] });
        // repère sur la carte principale
        const bb = path.bounds(fc);
        el('rect', {
          x: bb[0][0] - 3, y: bb[0][1] - 3, width: bb[1][0] - bb[0][0] + 6, height: bb[1][1] - bb[0][1] + 6,
          fill: 'none', stroke: col.muted, 'stroke-dasharray': '3 2', 'stroke-width': 1, 'pointer-events': 'none', class: 'gm-zoom-mark',
        }, svg);
      });
      H += belowH;

      // Encarts « détachés » (ex. outre-mer) en bas
      if (detached.length) {
        const n = detached.length;
        const bw = Math.min(160, (mainW - (n - 1) * 10) / n);
        const by = 10 + mainH + 10;
        detached.forEach((inset, i) => {
          const x = mainX + i * (bw + 10);
          const feats = inset.codes.map((code) => this.byCode.get(code));
          const fc = { type: 'FeatureCollection', features: feats };
          const proj = d3.geoMercator().fitExtent([[x + 8, by + 24], [x + bw - 8, by + bottom - 18]], fc);
          el('rect', { x, y: by, width: bw, height: bottom - 10, fill: col.bg, stroke: col.border, rx: 6 }, svg);
          const t = el('text', { x: x + 8, y: by + 16, 'font-size': 12, 'font-weight': 600, fill: col.text }, svg);
          t.textContent = inset.title;
          panels.push({ g: el('g', {}, svg), path: d3.geoPath(proj), features: feats });
        });
      }

      // --- Zones, frontières et étiquettes de chaque panneau
      const labelLayer = el('g', { class: 'geomembers-labels', 'pointer-events': 'none' }, svg);
      const labelled = new Set();
      const gkey = (g) => (zoneGroup.get(g.code) || {}).key;

      panels.forEach((panel) => {
        const zg = el('g', { class: 'geomembers-zones' }, panel.g);
        panel.features.forEach((f) => {
          const group = zoneGroup.get(f.code);
          const p = el('path', { d: panel.path(f), fill: fillFor(group), 'data-group': group.key, class: 'gm-zone' }, zg);
          this.bindTip(p, () => this.zoneTip(f, group), group.key);
        });
        const codes = new Set(panel.features.map((f) => f.code));
        const geoms = this.object.geometries.filter((g) => codes.has(g.code));
        const sub = { type: 'GeometryCollection', geometries: geoms };
        el('path', {
          d: panel.path(topojson.mesh(this.topo, sub, (a, b) => a !== b && gkey(a) === gkey(b))),
          fill: 'none', stroke: col.bg, 'stroke-width': isGrouped ? 0.4 : 0.7, 'stroke-opacity': isGrouped ? 0.6 : 1, 'pointer-events': 'none',
        }, panel.g);
        el('path', {
          d: panel.path(topojson.mesh(this.topo, sub, (a, b) => a !== b && gkey(a) !== gkey(b))),
          fill: 'none', stroke: isGrouped ? col.text : col.bg, 'stroke-width': isGrouped ? 1.1 : 0.7, 'stroke-opacity': isGrouped ? 0.55 : 1,
          'stroke-linejoin': 'round', 'pointer-events': 'none',
        }, panel.g);
        el('path', {
          d: panel.path(topojson.mesh(this.topo, sub, (a, b) => a === b)),
          fill: 'none', stroke: col.text, 'stroke-width': 0.9, 'stroke-opacity': 0.5, 'pointer-events': 'none',
        }, panel.g);

        // Étiquettes : un point par groupe présent dans le panneau
        const panelGroups = new Map();
        geoms.forEach((g) => {
          const group = zoneGroup.get(g.code);
          if (!panelGroups.has(group)) { panelGroups.set(group, []); }
          panelGroups.get(group).push(g);
        });
        panelGroups.forEach((list, group) => {
          if (group.value <= 0 && !opt.zeroLabels) { return; }
          if (group.unassigned && group.value <= 0) { return; }
          // sur la carte principale, les zones agrandies dans un encart sont étiquetées dans l'encart seulement
          if (panel.main && zooms.length && group.zones.every((code) => zoomCodes.has(code))
            && (group.single || zoomsBelow)) { return; }
          // dans un encart, seules les zones de l'encart sont étiquetées
          if (panel.zoom && !group.zones.some((code) => zoomCodes.has(code))) { return; }
          if (panel.zoom) { list = list.filter((g) => zoomCodes.has(g.code)); }
          if (!list.length) { return; }
          let point;
          if (list.length === 1 && group.single && list[0].properties && list[0].properties.lx !== undefined) {
            point = panel.path.projection()([list[0].properties.lx, list[0].properties.ly]);
          }
          if (!point) {
            const merged = rewind(topojson.merge(this.topo, list));
            const poly = largestPolygon(merged, panel.path);
            point = poly ? panel.path.centroid(poly) : null;
          }
          if (!point || Number.isNaN(point[0])) { return; }
          if (panel.box && (point[0] < panel.box[0] || point[0] > panel.box[2] || point[1] < panel.box[1] || point[1] > panel.box[3])) { return; }
          labelled.add(group.key);
          this.drawLabel(labelLayer, point, group, col, scale, max, isGrouped, panel.main ? 1 : 0.9);
        });
      });

      // --- Pays étrangers : étiquette sur la carte ou badge en marge
      if (hasForeign && !foreignEntries.length && (sideL || (sideR && !zoomsRight))) {
        return this.render({ left: false, right: false });
      }
      if (hasForeign && foreignEntries.length) {
        const left = [];
        const right = [];
        const center = projection.invert([mainX + mainW / 2, 10 + mainH / 2]);
        const placements = cfg.foreign.placements || {};
        const fallback = placements['*'] || null; // ligne « *|… » : réglage des pays non cités
        // Zone où une étiquette tient entièrement dans le cadre de la carte
        const mx = 26 * this.fontK;
        const my = 13 * this.fontK;
        const box = [[mainX + mx, 10 + my], [mainX + mainW - mx, 10 + mainH - my]];
        const inBox = (p) => p && p[0] >= box[0][0] && p[0] <= box[1][0] && p[1] >= box[0][1] && p[1] <= box[1][1];
        const clamp = (p) => [Math.min(box[1][0], Math.max(box[0][0], p[0])), Math.min(box[1][1], Math.max(box[0][1], p[1]))];
        // Chemin limité au cadre visible : sert à trouver la partie visible d'un pays (même un petit bout)
        projection.clipExtent([[mainX, 10], [mainX + mainW, 10 + mainH]]);
        const clipPath = d3.geoPath(projection);
        const visiblePoint = (f) => {
          if (!f || !drawnForeign.has(f.code)) { return null; }
          const poly = largestPolygon(f.geometry, clipPath);
          if (!poly || clipPath.area(poly) < 20) { return null; }
          const c = clipPath.centroid(poly);
          return Number.isFinite(c[0]) ? clamp(c) : null;
        };
        const placed = [];
        const items = [];
        foreignEntries.forEach(([code, z]) => {
          const f = this.foreignFeatures.find((ff) => ff.code === code);
          const props = f ? f.properties : {};
          const lonlat = (props && props.lx !== undefined) ? [props.lx, props.ly] : (f ? d3.geoCentroid(f) : null);
          if (!lonlat) { return; }
          const rule = code !== '*' ? placements[code] || null : null;
          const labelPt = projection(lonlat);
          const item = { code, z, f, lonlat, rule, name: this.name(code, props, z.name, cfg.foreign.nameProperty), group: foreignGroup.get(code) };
          let pt = null;
          if (rule && rule.position !== 'map') {
            // marge imposée
          } else if (rule && rule.point) {
            pt = clamp(projection(rule.point) || labelPt);
          } else if (inBox(labelPt)) {
            pt = labelPt;
          } else {
            pt = visiblePoint(f) || (rule || (fallback && fallback.position === 'map') ? clamp(labelPt) : null);
          }
          if (pt) {
            item.pt = pt;
            items.push(item);
          } else {
            // marge : réglage du pays, sinon réglage « * », sinon côté où se trouve le pays
            const side = rule && rule.position !== 'map' ? rule.position : (fallback && fallback.position !== 'map' ? fallback.position : null);
            const wantLeft = side ? side === 'left' : lonlat[0] < center[0];
            // marge souhaitée absente (retirée car vide au premier passage) : l'autre marge, sinon sur la carte
            const order = wantLeft ? [[sideL, left], [sideR, right]] : [[sideR, right], [sideL, left]];
            const target = order.find(([width]) => width > 0);
            if (target) {
              target[1].push(item);
            } else {
              item.pt = clamp(labelPt || [box[0][0], box[0][1]]);
              items.push(item);
            }
          }
        });
        projection.clipExtent(null);
        // Marge vide : nouveau rendu sans elle, la carte prend la place
        const dropLeft = sideL > 0 && left.length === 0;
        const dropRight = sideR > 0 && right.length === 0 && !zoomsRight;
        if (dropLeft || dropRight) {
          return this.render({ left: sides.left && !dropLeft, right: sides.right && !dropRight });
        }
        // Étiquettes sur la carte, décalées verticalement si elles se chevauchent
        items.forEach((item) => {
          const w = (38 + this.format(item.z.value).length * 9) * this.fontK;
          const h = 24 * this.fontK;
          const free = (p) => placed.every((q) => Math.abs(p[0] - q[0]) > (w + q[2]) / 2 || Math.abs(p[1] - q[1]) > h);
          let pt = item.pt;
          for (const k of [0, 1, -1, 2, -2, 3, -3]) {
            const cand = clamp([item.pt[0], item.pt[1] + k * h]);
            if (free(cand)) { pt = cand; break; }
          }
          placed.push([pt[0], pt[1], w]);
          onMapForeign.add(item.code);
          this.drawForeignLabel(labelLayer, pt, item, col);
        });
        const place = (list, x, y0, width) => {
          // rangs imposés d'abord, puis du nord au sud
          list.sort((a, b) => {
            const ra = a.rule && a.rule.order !== null && a.rule.order !== undefined ? a.rule.order : Infinity;
            const rb = b.rule && b.rule.order !== null && b.rule.order !== undefined ? b.rule.order : Infinity;
            return ra !== rb ? ra - rb : b.lonlat[1] - a.lonlat[1];
          });
          let y = y0;
          list.forEach((item) => { this.drawBadge(svg, x, y, width, item, col); y += 50; });
          return y;
        };
        const endL = place(left, 10, 10, sideL - 20);
        const endR = place(right, W - sideR + 10, rightY, sideR - 20);
        H = Math.max(H, endL, endR);
      }
      svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
      bgRect.setAttribute('height', H);

      // Déplace les étiquettes au premier plan
      svg.appendChild(labelLayer);

      this.figure.querySelectorAll('svg').forEach((s) => s.remove());
      this.figure.insertBefore(svg, this.tooltip);
      this.svg = svg;

      this.renderLegend(groups, scale, max, col, isGrouped);
      this.renderTable(groups, foreignEntries, isGrouped);
      this.renderFooter();
    }

    drawLabel(layer, [x, y], group, col, scale, max, isGrouped, k) {
      const opt = this.options;
      const value = this.format(group.value);
      const showName = opt.labels === 'name_value';
      if (opt.labels === 'none' && opt.mode !== 'circles') { return; }

      if (opt.mode === 'circles') {
        if (group.value <= 0) { return; }
        const r = Math.max(3, 34 * Math.sqrt(group.value / max)) * k;
        const fill = d3['interpolate' + (opt.scheme || 'Blues')] ? d3['interpolate' + (opt.scheme || 'Blues')](0.7) : '#3182bd';
        el('circle', { cx: x, cy: y, r, fill, 'fill-opacity': 0.75, stroke: col.bg, 'stroke-width': 1 }, layer);
        if (opt.labels !== 'none') {
          const t = el('text', {
            x, y: r > 11 ? y + 4 : y - r - 3, 'text-anchor': 'middle', 'font-size': 11 * k * this.fontK, 'font-weight': 700,
            fill: r > 11 ? '#fff' : col.text,
          }, layer);
          t.textContent = (showName ? group.title + ' ' : '') + value;
        }
        return;
      }

      const big = isGrouped && !group.unassigned;
      if (!big) {
        // étiquette discrète (zones fines, ex. départements)
        const t = el('text', {
          x, y: y + 4 * this.fontK, 'text-anchor': 'middle', 'font-size': 11 * k * this.fontK, 'font-weight': 700, fill: col.text,
          stroke: col.bg, 'stroke-width': 3, 'paint-order': 'stroke', 'stroke-linejoin': 'round',
        }, layer);
        t.textContent = (showName ? group.title + ' ' : '') + value;
        return;
      }

      const g = el('g', { transform: `translate(${x},${y}) scale(${this.fontK})` }, layer);
      const lines = showName ? [group.title, value] : [value];
      const fs = 16 * k;
      const width = Math.max(...lines.map((l, i) => l.length * (i === lines.length - 1 ? fs * 0.62 : 11 * 0.58))) + 14;
      const height = showName ? fs + 22 : fs + 10;
      el('rect', {
        x: -width / 2, y: -height / 2, width, height, rx: 7, fill: col.bg, 'fill-opacity': 0.92,
        stroke: col.text, 'stroke-opacity': 0.35,
      }, g);
      if (showName) {
        const n = el('text', { x: 0, y: -height / 2 + 13, 'text-anchor': 'middle', 'font-size': 11, fill: col.text }, g);
        n.textContent = group.title;
      }
      const t = el('text', { x: 0, y: height / 2 - 7, 'text-anchor': 'middle', 'font-size': fs, 'font-weight': 700, fill: col.text }, g);
      t.textContent = value;
    }

    flagHref(code) {
      return /^[A-Z]{2}$/.test(code) ? this.cfg.flags + code.toLowerCase() + '.svg' : null;
    }

    drawForeignLabel(layer, [x, y], item, col) {
      // la couche des étiquettes ignore la souris : celle-ci la reçoit pour afficher le bon pays au survol
      const g = el('g', { transform: `translate(${x},${y}) scale(${this.fontK})`, 'pointer-events': 'all', style: 'cursor:default' }, layer);
      const value = this.format(item.z.value);
      const w = 30 + value.length * 9 + 8;
      el('rect', { x: -w / 2, y: -11, width: w, height: 22, rx: 11, fill: col.bg, 'fill-opacity': 0.92, stroke: item.group ? item.group.color : col.text, 'stroke-opacity': item.group ? 1 : 0.35 }, g);
      const href = this.flagHref(item.code);
      if (href) { el('image', { href, x: -w / 2 + 5, y: -7, width: 19, height: 14, preserveAspectRatio: 'none' }, g); }
      const t = el('text', { x: -w / 2 + 28, y: 5, 'font-size': 14, 'font-weight': 700, fill: col.text }, g);
      t.textContent = value;
      this.bindTip(g, () => this.itemTip(item));
    }

    itemTip(item) {
      const lines = [['strong', item.name], ['span', this.strings.value + ' : ' + this.format(item.z.value)]];
      if (item.group) { lines.push(['span', item.group.title]); }
      return lines;
    }

    drawBadge(svg, x, y, width, item, col) {
      const g = el('g', { transform: `translate(${x},${y})`, class: 'gm-badge' }, svg);
      el('rect', {
        width, height: 42, rx: 8, fill: col.bg, stroke: item.group ? item.group.color : col.border,
        'stroke-width': item.group ? 2 : 1,
      }, g);
      const href = this.flagHref(item.code);
      if (href) {
        el('image', { href, x: 8, y: 9, width: 32, height: 24, preserveAspectRatio: 'none' }, g);
        el('rect', { x: 8, y: 9, width: 32, height: 24, fill: 'none', stroke: col.border }, g);
      } else {
        const c = el('text', { x: 24, y: 26, 'text-anchor': 'middle', 'font-size': 11, fill: col.muted }, g);
        c.textContent = item.code;
      }
      const v = el('text', { x: 48, y: 20, 'font-size': 17, 'font-weight': 700, fill: col.text }, g);
      v.textContent = this.format(item.z.value);
      const n = el('text', { x: 48, y: 35, 'font-size': 11, fill: col.muted }, g);
      const maxChars = Math.floor((width - 54) / 6);
      n.textContent = item.name.length > maxChars ? item.name.slice(0, maxChars - 1) + '…' : item.name;
      this.bindTip(g, () => this.itemTip(item));
    }

    zoneTip(f, group) {
      const z = this.cfg.zones[f.code] || {};
      const name = this.name(f.code, f.properties, z.name, this.cfg.layer.nameProperty);
      const lines = [];
      if (!group.single) {
        lines.push(['strong', group.title + ' : ' + this.format(group.value)]);
        lines.push(['span', name + ' : ' + this.format(z.value || 0)]);
      } else {
        lines.push(['strong', name]);
        lines.push(['span', this.strings.value + ' : ' + this.format(z.value || 0)]);
      }
      return lines;
    }

    foreignTip(f, z, grp) {
      const name = this.name(f.code, f.properties, z.name, this.cfg.foreign.nameProperty);
      const lines = [['strong', name], ['span', this.strings.value + ' : ' + this.format(z.value)]];
      if (grp) { lines.push(['span', grp.title]); }
      return lines;
    }

    bindTip(node, content, groupKey) {
      node.addEventListener('mouseenter', () => {
        this.tooltip.replaceChildren(...content().map(([tag, text]) => html(tag, {}, null, text)));
        this.tooltip.hidden = false;
        if (groupKey) {
          this.svg.querySelectorAll(`[data-group="${groupKey}"]`).forEach((p) => p.classList.add('is-active'));
        }
      });
      node.addEventListener('mousemove', (e) => {
        const r = this.figure.getBoundingClientRect();
        let x = e.clientX - r.left + 14;
        const y = e.clientY - r.top + 14;
        if (x + this.tooltip.offsetWidth > r.width) { x = e.clientX - r.left - this.tooltip.offsetWidth - 10; }
        this.tooltip.style.transform = `translate(${x}px, ${y}px)`;
      });
      node.addEventListener('mouseleave', () => {
        this.tooltip.hidden = true;
        this.svg.querySelectorAll('.is-active').forEach((p) => p.classList.remove('is-active'));
      });
    }

    renderLegend(groups, scale, max, col, isGrouped) {
      const opt = this.options;
      const box = this.legend;
      box.replaceChildren();
      if (!opt.legend) { return; }

      if (opt.mode === 'groups' && isGrouped) {
        const list = html('ul', { class: 'geomembers-legend-groups' }, box);
        groups.filter((g) => !g.unassigned).forEach((g) => {
          const li = html('li', {}, list);
          const sw = html('span', { class: 'geomembers-swatch' }, li);
          sw.style.background = g.color;
          html('span', {}, li, g.title + ' ');
          html('strong', {}, li, this.format(g.value));
        });
        return;
      }

      if (opt.mode === 'circles') { return; }

      const bar = html('div', { class: 'geomembers-legend-scale' }, box);
      const stops = d3.range(0, 1.0001, 0.1).map((t) => scale.interpolator()(t) + ' ' + Math.round(t * 100) + '%');
      const grad = html('span', { class: 'geomembers-gradient' }, bar);
      grad.style.background = `linear-gradient(to right, ${stops.join(',')})`;
      const labels = html('span', { class: 'geomembers-gradient-labels' }, bar);
      html('span', {}, labels, scale.isLog ? '1' : '0');
      if (scale.isLog) { html('span', {}, labels, String(Math.round(Math.sqrt(max)))); }
      html('span', {}, labels, String(max));
      if (opt.hatchZero) {
        const zero = html('span', { class: 'geomembers-legend-zero' }, box);
        const sw = html('span', { class: 'geomembers-swatch geomembers-hatch' }, zero);
        sw.style.setProperty('--gm-land', col.land);
        html('span', {}, zero, this.strings.none);
      }
    }

    renderTable(groups, foreignEntries, isGrouped) {
      const box = this.tableBox;
      box.replaceChildren();
      if (!this.options.table) { return; }
      const details = html('details', {}, box);
      html('summary', {}, details, this.strings.table);
      const table = html('table', { class: 'table table-sm table-striped geomembers-data' }, details);
      const thead = html('thead', {}, table);
      const hr = html('tr', {}, thead);
      html('th', { scope: 'col' }, hr, isGrouped ? (this.cfg.groupings.find((g) => g.id === this.grouping) || {}).title : this.strings.zone);
      html('th', { scope: 'col', class: 'text-end' }, hr, this.strings.value);
      const tbody = html('tbody', {}, table);
      const total = this.cfg.dataset.total || 1;
      const row = (label, value, cls) => {
        const tr = html('tr', cls ? { class: cls } : {}, tbody);
        html('th', { scope: 'row' }, tr, label);
        html('td', { class: 'text-end' }, tr, this.format(value) + '  (' + (Math.round(value * 1000 / total) / 10) + ' %)');
      };
      groups.filter((g) => g.value > 0 || (isGrouped && !g.unassigned))
        .sort((a, b) => b.value - a.value || a.title.localeCompare(b.title))
        .forEach((g) => row(g.title, g.value));
      if (this.cfg.unallocated) {
        row(this.cfg.unallocated.name + ' – ' + this.strings.unallocated, this.cfg.unallocated.value);
      }
      if (foreignEntries.length) {
        const tr = html('tr', { class: 'geomembers-subhead' }, tbody);
        html('th', { colspan: 2, scope: 'rowgroup' }, tr, this.strings.abroad);
        foreignEntries.slice().sort((a, b) => b[1].value - a[1].value).forEach(([code, z]) => {
          const f = this.foreignFeatures.find((ff) => ff.code === code);
          row(this.name(code, f && f.properties, z.name, this.cfg.foreign.nameProperty), z.value);
        });
      }
      const tfoot = html('tfoot', {}, table);
      const tr = html('tr', {}, tfoot);
      html('th', { scope: 'row' }, tr, this.strings.total);
      html('td', { class: 'text-end fw-bold' }, tr, String(this.cfg.dataset.total));
    }

    renderFooter() {
      const box = this.footer;
      box.replaceChildren();
      html('span', { class: 'geomembers-total' }, box, this.strings.total + ' : ' + this.cfg.dataset.total);
      if (this.cfg.layer.attribution) {
        html('span', { class: 'geomembers-attribution' }, box, this.strings.sources + ' : ' + this.cfg.layer.attribution);
      }
      if (this.options.download) {
        const wrap = html('span', { class: 'geomembers-download' }, box);
        const svgBtn = html('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary' }, wrap, 'SVG');
        const pngBtn = html('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary' }, wrap, 'PNG');
        svgBtn.title = pngBtn.title = this.strings.download;
        svgBtn.addEventListener('click', () => this.download('svg'));
        pngBtn.addEventListener('click', () => this.download('png'));
      }
    }

    async download(type) {
      const clone = this.svg.cloneNode(true);
      // Drapeaux intégrés en data URI pour un fichier autonome
      await Promise.all([...clone.querySelectorAll('image')].map(async (img) => {
        try {
          const text = await (await fetch(img.getAttribute('href'))).text();
          img.setAttribute('href', 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(text))));
        } catch (e) { /* drapeau ignoré */ }
      }));
      clone.querySelectorAll('.is-active').forEach((p) => p.classList.remove('is-active'));
      const source = new XMLSerializer().serializeToString(clone);
      const name = (this.cfg.dataset.title || 'carte').replace(/[^\w-]+/g, '-').toLowerCase();
      const save = (href, ext) => {
        const a = document.createElement('a');
        a.href = href;
        a.download = name + '.' + ext;
        document.body.appendChild(a);
        a.click();
        a.remove();
      };
      const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      if (type === 'svg') {
        save(url, 'svg');
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        return;
      }
      const vb = this.svg.viewBox.baseVal;
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = vb.width * 2;
        canvas.height = vb.height * 2;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        save(canvas.toDataURL('image/png'), 'png');
      };
      img.src = url;
    }
  }

  const start = () => {
    document.querySelectorAll('[data-geomembers]').forEach((container) => {
      if (container.geomembers) { return; }
      const cfg = Joomla.getOptions('com_geomembers.' + container.dataset.geomembers);
      if (!cfg) { return; }
      container.geomembers = new GeoMembersMap(container, cfg);
      container.geomembers.init().catch((e) => {
        container.textContent = e.message;
        // eslint-disable-next-line no-console
        console.error(e);
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
