(function (window) {
    'use strict';

    function asNumber(v) {
        const n = Number(v);
        return Number.isFinite(n) ? n : 0;
    }

    function moneyBR(v) {
        return asNumber(v).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function normalizeUF(props, feature = null) {
        const candidates = [
            feature?.id,
            props?.SIGLA,
            props?.PK_sigla,
            props?.sigla,
            props?.uf,
            props?.UF,
            props?.SG_UF,
            props?.SIGLA_UF,
            props?.id,
            props?.ID
        ];

        for (const v of candidates) {
            const s = String(v || '').trim().toUpperCase();
            if (/^[A-Z]{2}$/.test(s)) return s;
        }

        return '';
    }

    function getMapFillColor(value, max) {
        const ratio = max > 0 ? (value / max) : 0;

        if (ratio >= 0.85) return '#5c2c8c';
        if (ratio >= 0.65) return '#75509c';
        if (ratio >= 0.45) return '#9478b3';
        if (ratio >= 0.25) return '#b7a2cd';
        if (ratio > 0) return '#ddd3ec';
        return '#e5e7eb';
    }

    function renderList(wrap, items, labelKey = 'regiao', badgeEl = null) {
        if (!wrap) return;

        const list = Array.isArray(items) ? items : [];
        const valid = list.filter(item => asNumber(item?.valor) > 0);
        const total = valid.reduce((acc, x) => acc + asNumber(x.valor), 0);
        const max = valid.length ? asNumber(valid[0].valor) : 0;

        if (badgeEl) {
            badgeEl.textContent = valid.length ? `Top ${valid.length}` : '—';
        }

        wrap.innerHTML = '';

        if (!valid.length) {
            wrap.innerHTML = '<div class="geo-vendas-empty">Sem dados</div>';
            return;
        }

        valid.forEach((item, idx) => {
            const nome = String(item[labelKey] ?? item.uf ?? '—');
            const valor = asNumber(item.valor);
            const pct = total > 0 ? (valor / total) * 100 : 0;
            const width = max > 0 ? (valor / max) * 100 : 0;

            const row = document.createElement('div');
            row.className = 'geo-vendas-row';
            row.innerHTML = `
                <div class="geo-vendas-rank">${idx + 1}</div>
                <div class="geo-vendas-main">
                    <div class="geo-vendas-name" title="${escapeHtml(nome)}">${escapeHtml(nome)}</div>
                    <div class="geo-vendas-share">${pct.toFixed(1)}%</div>
                    <div class="geo-vendas-bar"><i style="width:${width.toFixed(1)}%"></i></div>
                </div>
                <div class="geo-vendas-value">${moneyBR(valor)}</div>
            `;
            wrap.appendChild(row);
        });
    }

    function renderTopClientesTooltip(items) {
        const list = Array.isArray(items)
            ? items.filter(x => asNumber(x?.valor) > 0)
            : [];

        if (!list.length) {
            return `
                <div style="margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.18)">
                    <div style="font-weight:700;font-size:12px;margin-bottom:4px">
                        Melhores clientes da região
                    </div>
                    <div style="font-size:12px;opacity:.85">Sem dados</div>
                </div>
            `;
        }

        return `
            <div style="margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.18)">
                <div style="font-weight:700;font-size:12px;margin-bottom:6px">
                    Melhores clientes da região
                </div>
                <div style="display:flex;flex-direction:column;gap:6px">
                    ${list.slice(0, 5).map((item, idx) => `
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:12px">
                            <span style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                ${idx + 1}. ${escapeHtml(item?.cliente || item?.nome || '—')}
                            </span>
                            <strong style="white-space:nowrap">${escapeHtml(moneyBR(item?.valor))}</strong>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    function createApp(root, options = {}) {
        let map = null;
        let salesLayer = null;
        let labelsLayer = null;
        let currentGeoJson = null;
        let currentUfsMap = null;

        const endpoint = options.endpoint || root.dataset.endpoint || '/api/clientes_insights.php';
        const geojsonUrl = options.geojson || root.dataset.geojson || '/assets/maps/brasil-ufs.geojson';
        const ym = options.ym || root.dataset.ym || new Date().toISOString().slice(0, 7);
        const title = options.title || root.dataset.title || 'Top Regiões e Mapa de Vendas';
        const subtitlePrefix = options.subtitlePrefix || root.dataset.subtitlePrefix || 'Distribuição por UF e região';
        const mapTitle = options.mapTitle || root.dataset.mapTitle || 'Mapa do Brasil por venda';
        const regionsTitle = options.regionsTitle || root.dataset.regionsTitle || 'Top Regiões';
        const statesTitle = options.statesTitle || root.dataset.statesTitle || 'Top Estados';

        root.innerHTML = `
            <div class="geo-vendas">
                <div class="geo-vendas__header">
                    <div>
                        <h3 class="geo-vendas__title">${escapeHtml(title)}</h3>
                        <div class="geo-vendas__subtitle">
                            ${escapeHtml(subtitlePrefix)} — <span data-role="updated">--</span>
                        </div>
                    </div>
                </div>

                <div class="geo-vendas__grid">
                    <section class="geo-vendas__card">
                        <div class="geo-vendas__card-head">
                            <h4>${escapeHtml(regionsTitle)}</h4>
                            <span class="geo-vendas__badge" data-role="badge-regioes">--</span>
                        </div>
                        <div class="geo-vendas__list" data-role="regioes"></div>
                    </section>

                    <section class="geo-vendas__card geo-vendas__card--map">
                        <div class="geo-vendas__card-head">
                            <h4>${escapeHtml(mapTitle)}</h4>
                            <span class="geo-vendas__badge">UFs</span>
                        </div>

                        <div class="geo-vendas__map" data-role="map"></div>

                        <div class="geo-vendas__legend">
                            <span>Menor venda</span>
                            <div class="geo-vendas__legend-bar"></div>
                            <span>Maior venda</span>
                        </div>
                    </section>

                    <section class="geo-vendas__card">
                        <div class="geo-vendas__card-head">
                            <h4>${escapeHtml(statesTitle)}</h4>
                            <span class="geo-vendas__badge" data-role="badge-estados">--</span>
                        </div>
                        <div class="geo-vendas__list" data-role="estados"></div>
                    </section>
                </div>
            </div>
        `;

        const updatedEl = root.querySelector('[data-role="updated"]');
        const listRegioes = root.querySelector('[data-role="regioes"]');
        const listEstados = root.querySelector('[data-role="estados"]');
        const badgeRegioes = root.querySelector('[data-role="badge-regioes"]');
        const badgeEstados = root.querySelector('[data-role="badge-estados"]');
        const mapEl = root.querySelector('[data-role="map"]');

        function renderLabels(geojson, ufsMap) {
            if (!map) return;

            if (labelsLayer) {
                try { map.removeLayer(labelsLayer); } catch (_) {}
                labelsLayer = null;
            }

            const labels = [];

            L.geoJSON(geojson, {
                onEachFeature(feature, layer) {
                    const props = feature?.properties || {};
                    const uf = normalizeUF(props, feature);
                    if (!uf) return;

                    const info = ufsMap[uf] || {};
                    const valor = asNumber(info.valor);
                    const pct = asNumber(info.pct) * 100;

                    if (valor <= 0) return;

                    const center = layer.getBounds().getCenter();

                    labels.push(
                        L.marker(center, {
                            interactive: false,
                            icon: L.divIcon({
                                className: 'geo-vendas-label-marker',
                                html: `
                                    <div class="geo-vendas-label">
                                        <span class="geo-vendas-label-uf">${uf}</span>
                                        <span class="geo-vendas-label-pct">${pct.toFixed(1)}%</span>
                                    </div>
                                `,
                                iconSize: [52, 30],
                                iconAnchor: [26, 15]
                            })
                        })
                    );
                }
            });

            labelsLayer = L.layerGroup(labels).addTo(map);
        }

        async function load() {
            const [geojson, payload] = await Promise.all([
                fetch(geojsonUrl, { cache: 'no-store' }).then(async r => {
                    if (!r.ok) throw new Error('GeoJSON não encontrado');
                    return await r.json();
                }),
                fetch(`${endpoint}?ym=${encodeURIComponent(ym)}`, { cache: 'no-store' }).then(async r => {
                    if (!r.ok) throw new Error('Erro ao buscar dados do mapa');
                    return await r.json();
                })
            ]);

            currentGeoJson = geojson;

            const geo = payload?.geografia || {};
            currentUfsMap = geo.ufs_map || {};

            if (updatedEl) updatedEl.textContent = payload?.updated_at || '--';

            renderList(listRegioes, geo.regioes || [], 'regiao', badgeRegioes);
            renderList(listEstados, geo.ufs_ranking || [], 'uf', badgeEstados);

            const values = Object.values(currentUfsMap)
                .map(x => asNumber(x?.valor))
                .filter(v => v > 0);

            const max = values.length ? Math.max(...values) : 0;

            if (!map) {
                map = L.map(mapEl, {
                    zoomControl: false,
                    attributionControl: false,
                    dragging: false,
                    scrollWheelZoom: false,
                    doubleClickZoom: false,
                    boxZoom: false,
                    keyboard: false,
                    tap: false
                });
            }

            if (salesLayer) {
                try { map.removeLayer(salesLayer); } catch (_) {}
                salesLayer = null;
            }

            salesLayer = L.geoJSON(geojson, {
                style(feature) {
                    const uf = normalizeUF(feature?.properties || {}, feature);
                    const info = currentUfsMap[uf] || {};
                    const valor = asNumber(info.valor);

                    return {
                        fillColor: getMapFillColor(valor, max),
                        weight: 1.4,
                        color: '#ffffff',
                        opacity: 1,
                        fillOpacity: 0.96
                    };
                },
                onEachFeature(feature, layer) {
                    const props = feature?.properties || {};
                    const uf = normalizeUF(props, feature);
                    const info = currentUfsMap[uf] || {};
                    const valor = asNumber(info.valor);
                    const pct = asNumber(info.pct) * 100;
                    const regiao = String(info.regiao || '—');
                    const topClientesRegiao = Array.isArray(info.top_clientes_regiao)
                        ? info.top_clientes_regiao
                        : [];

                    const estado =
                        props.Estado ||
                        props.estado ||
                        props.NM_UF ||
                        props.name ||
                        props.geometry_name ||
                        uf;

                    layer.bindTooltip(`
                        <div style="min-width:260px;max-width:320px">
                            <div style="font-weight:800;font-size:14px;margin-bottom:6px">
                                ${escapeHtml(String(estado))} (${escapeHtml(uf)})
                            </div>

                            <div style="font-size:12px;line-height:1.45">
                                <div><strong>Região:</strong> ${escapeHtml(regiao)}</div>
                                <div><strong>Vendas:</strong> ${escapeHtml(moneyBR(valor))}</div>
                                <div><strong>Share:</strong> ${pct.toFixed(1)}%</div>
                            </div>

                            ${renderTopClientesTooltip(topClientesRegiao)}
                        </div>
                    `, {
                        sticky: true,
                        direction: 'top',
                        opacity: 1
                    });

                    layer.on({
                        mouseover() {
                            layer.setStyle({
                                weight: 2.4,
                                color: '#2f1847',
                                fillOpacity: 1
                            });

                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                layer.bringToFront();
                            }
                        },
                        mouseout() {
                            if (salesLayer && typeof salesLayer.resetStyle === 'function') {
                                salesLayer.resetStyle(layer);
                            }
                        }
                    });
                }
            }).addTo(map);

            const bounds = salesLayer.getBounds();
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [10, 10] });
            }

            renderLabels(currentGeoJson, currentUfsMap);

            setTimeout(() => {
                if (map) {
                    map.invalidateSize();
                    renderLabels(currentGeoJson, currentUfsMap);
                }
            }, 300);
        }

        function refreshSize() {
            if (map) {
                map.invalidateSize();
                if (currentGeoJson && currentUfsMap) {
                    renderLabels(currentGeoJson, currentUfsMap);
                }
            }
        }

        return {
            load,
            refreshSize,
            getMap: () => map
        };
    }

    const GeoVendasApp = {
        create(selectorOrElement, options = {}) {
            const root = typeof selectorOrElement === 'string'
                ? document.querySelector(selectorOrElement)
                : selectorOrElement;

            if (!root) throw new Error('Container do GeoVendasApp não encontrado');
            return createApp(root, options);
        },

        async autoInit() {
            const els = document.querySelectorAll('[data-geo-vendas-app]');
            const instances = [];

            for (const el of els) {
                const app = createApp(el, {});
                await app.load();
                instances.push(app);
            }

            return instances;
        }
    };

    window.GeoVendasApp = GeoVendasApp;
})(window);