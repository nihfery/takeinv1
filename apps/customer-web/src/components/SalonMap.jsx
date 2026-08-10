'use client';

import { useEffect, useRef, useState } from 'react';
import maplibregl from 'maplibre-gl';
import { Map as MapIcon, Layers, Plus, Minus, Locate, Maximize2, Minimize2, MousePointer2 } from 'lucide-react';

const EASE_OUT = 'cubic-bezier(0, 0.55, 0.45, 1)';

function buildStyle() {
    return {
        version: 8,
        sources: {
            osm: {
                type: 'raster',
                tiles: [
                    'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    'https://b.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    'https://c.tile.openstreetmap.org/{z}/{x}/{y}.png',
                ],
                tileSize: 256,
                attribution: '© OpenStreetMap contributors',
            },
            satellite: {
                type: 'raster',
                tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
                tileSize: 256,
                attribution: '© Esri',
            },
        },
        layers: [
            { id: 'osm', type: 'raster', source: 'osm' },
            { id: 'satellite', type: 'raster', source: 'satellite', layout: { visibility: 'none' } },
        ],
    };
}

export default function SalonMap({
    branches = [],
    focusBranches = [],
    focusCoords = null,
    activeId = null,
    mapExpanded = false,
    onToggleExpand,
    onHoverBranch,
    onLeaveBranch,
    onAreaChange,
    onSelectBranch,
    demoActiveId = null,
}) {
    const containerRef = useRef(null);
    const mapRef = useRef(null);
    const markersRef = useRef(new Map());
    const readyRef = useRef(false);
    const areaTrackingRef = useRef(false);
    const loadingTimerRef = useRef(null);
    const demoZoomTimerRef = useRef(null);
    const demoPanTimerRef = useRef(null);
    const demoMoveTimerRef = useRef(null);
    const demoClickTimerRef = useRef(null);
    const [baseLayer, setBaseLayer] = useState('maps');
    const [tilesLoading, setTilesLoading] = useState(true);
    const [attributionOpen, setAttributionOpen] = useState(false);
    const [demoCursor, setDemoCursor] = useState(null);
    const [mapReady, setMapReady] = useState(false);

    const located = branches.filter((branch) => Number.isFinite(branch.latitude) && Number.isFinite(branch.longitude));
    const focusLocated = (focusBranches.length ? focusBranches : branches)
        .filter((branch) => Number.isFinite(branch.latitude) && Number.isFinite(branch.longitude));

    // Keep latest values reachable from the map event handlers registered once.
    const locatedRef = useRef(located);
    locatedRef.current = located;
    const onAreaChangeRef = useRef(onAreaChange);
    onAreaChangeRef.current = onAreaChange;
    const onSelectBranchRef = useRef(onSelectBranch);
    onSelectBranchRef.current = onSelectBranch;

    function resizeMapSoon() {
        const map = mapRef.current;
        if (!map) return;

        map.resize();
        requestAnimationFrame(() => map.resize());
        window.setTimeout(() => map.resize(), 120);
        window.setTimeout(() => map.resize(), 360);
    }

    function finishLoadingSoon(delay = 180) {
        window.clearTimeout(loadingTimerRef.current);
        loadingTimerRef.current = window.setTimeout(() => {
            setTilesLoading(false);
        }, delay);
    }

    function emitArea() {
        const map = mapRef.current;
        if (!map || !onAreaChangeRef.current) return;
        const bounds = map.getBounds();
        const ids = locatedRef.current
            .filter((branch) => bounds.contains([branch.longitude, branch.latitude]))
            .map((branch) => branch.id);
        onAreaChangeRef.current(ids);
    }

    // Initialise the map lazily, once the container actually has a width. Creating
    // it before the flex layout settles is what made the canvas render at half size.
    useEffect(() => {
        const container = containerRef.current;
        if (!container) return undefined;

        function ensureMap() {
            if (mapRef.current || container.clientWidth === 0 || container.clientHeight === 0) {
                return;
            }

            const map = new maplibregl.Map({
                container,
                style: buildStyle(),
                center: [118, -2.2],
                zoom: 4,
                fadeDuration: 0,
                attributionControl: false,
            });
            mapRef.current = map;

            // Raster tiles are loaded from a third-party OpenStreetMap server.
            // MapLibre writes every failed tile request to the console when no
            // error listener exists, which made a successful logout look like
            // it had failed after the landing page was loaded again. Handling
            // the event keeps the map non-blocking when that server is offline,
            // blocked, or temporarily unavailable.
            map.on('error', () => {
                finishLoadingSoon(0);
            });

            map.on('load', () => {
                readyRef.current = true;
                addMarkers();
                resizeMapSoon();
                fitToBranches();
                finishLoadingSoon();
                setMapReady(true);
            });

            map.once('render', () => finishLoadingSoon(320));

            // Update the result list whenever the map stops moving (Airbnb-style
            // "search this area"). Hovering a card only highlights the marker and no
            // longer moves the map, so every moveend here reflects the visible area.
            map.on('dragstart', () => {
                areaTrackingRef.current = true;
            });
            map.on('zoomstart', (event) => {
                if (event.originalEvent) areaTrackingRef.current = true;
            });
            map.on('moveend', () => {
                if (!areaTrackingRef.current) return;
                emitArea();
            });
        }

        const resizeObserver = new ResizeObserver(() => {
            if (!mapRef.current) {
                ensureMap();
            } else {
                resizeMapSoon();
            }
        });
        resizeObserver.observe(container);
        ensureMap();

        return () => {
            window.clearTimeout(loadingTimerRef.current);
            window.clearTimeout(demoZoomTimerRef.current);
            window.clearTimeout(demoPanTimerRef.current);
            window.clearTimeout(demoMoveTimerRef.current);
            window.clearTimeout(demoClickTimerRef.current);
            resizeObserver.disconnect();
            if (mapRef.current) {
                mapRef.current.remove();
            }
            mapRef.current = null;
            readyRef.current = false;
            setMapReady(false);
            markersRef.current.clear();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function addMarkers() {
        const map = mapRef.current;
        if (!map) return;

        markersRef.current.forEach((marker) => marker.remove());
        markersRef.current.clear();

        located.forEach((branch) => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'map-marker';
            el.innerHTML = `<span class="${''}">★</span>${Number(branch.rating || 5).toFixed(1)}`;
            el.addEventListener('mouseenter', (event) => onHoverBranch?.(branch, event));
            el.addEventListener('mousemove', (event) => onHoverBranch?.(branch, event));
            el.addEventListener('mouseleave', () => onLeaveBranch?.());
            el.addEventListener('click', () => {
                onSelectBranchRef.current?.(branch);
                map.flyTo({ center: [branch.longitude, branch.latitude], zoom: 13, duration: 700 });
            });

            const marker = new maplibregl.Marker({ element: el, anchor: 'bottom', offset: [0, -6] })
                .setLngLat([branch.longitude, branch.latitude])
                .addTo(map);
            markersRef.current.set(branch.id, marker);
        });
    }

    function fitToBranches() {
        const map = mapRef.current;
        resizeMapSoon();
        areaTrackingRef.current = false;

        if (map && Number.isFinite(focusCoords?.lat) && Number.isFinite(focusCoords?.lng)) {
            map.flyTo({ center: [focusCoords.lng, focusCoords.lat], zoom: 12, duration: 0 });
            return;
        }

        const points = focusLocated.length ? focusLocated : located;
        if (!map || points.length === 0) return;

        if (points.length === 1) {
            map.flyTo({ center: [points[0].longitude, points[0].latitude], zoom: 12, duration: 0 });
            return;
        }

        const bounds = new maplibregl.LngLatBounds();
        points.forEach((branch) => bounds.extend([branch.longitude, branch.latitude]));
        map.fitBounds(bounds, { padding: 70, maxZoom: 13, duration: 0 });
    }

    // Rebuild markers and refit when the branch list changes. focusBranches is
    // included so the map refits when only the location filter changes too.
    useEffect(() => {
        if (!readyRef.current) return;
        addMarkers();
        fitToBranches();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [branches, focusBranches, focusCoords]);

    // Highlight the hovered branch's marker (without moving the map).
    useEffect(() => {
        markersRef.current.forEach((marker, id) => {
            marker.getElement().classList.toggle('active', id === activeId);
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeId]);

    // Landing preview journey: begin in a different city, zoom out to Indonesia,
    // move to the target city, then click its real marker.
    useEffect(() => {
        const map = mapRef.current;
        const branch = located.find((item) => item.id === demoActiveId);
        if (!mapReady || !map || !branch || !Number.isFinite(branch.latitude) || !Number.isFinite(branch.longitude)) {
            return undefined;
        }

        window.clearTimeout(demoZoomTimerRef.current);
        window.clearTimeout(demoPanTimerRef.current);
        window.clearTimeout(demoMoveTimerRef.current);
        window.clearTimeout(demoClickTimerRef.current);
        setDemoCursor(null);

        // Use Jakarta as the simulated starting city, except when the chosen
        // salon is already near Jakarta; then begin in Surabaya instead.
        const nearJakarta = Math.abs(branch.latitude + 6.2088) < 1.3
            && Math.abs(branch.longitude - 106.8456) < 1.8;
        const startingCity = nearJakarta
            ? { center: [112.7521, -7.2575], zoom: 10.5 }
            : { center: [106.8456, -6.2088], zoom: 10.5 };

        map.flyTo({ ...startingCity, duration: 700, essential: true });

        demoZoomTimerRef.current = window.setTimeout(() => {
            map.zoomTo(4.7, { duration: 680, essential: true });
        }, 820);

        demoPanTimerRef.current = window.setTimeout(() => {
            map.flyTo({
                center: [branch.longitude, branch.latitude],
                zoom: 11.4,
                duration: 1300,
                essential: true,
            });
        }, 1700);

        demoClickTimerRef.current = window.setTimeout(() => {
            const marker = markersRef.current.get(branch.id);
            const mapBounds = containerRef.current?.getBoundingClientRect();
            const markerBounds = marker?.getElement().getBoundingClientRect();
            const projectedPoint = map.project([branch.longitude, branch.latitude]);
            // Measure the marker element itself, not just its geographic point,
            // so the cursor lands at the visible centre of the marker badge.
            const target = markerBounds && mapBounds
                ? {
                    x: markerBounds.left - mapBounds.left + markerBounds.width / 2,
                    y: markerBounds.top - mapBounds.top + markerBounds.height / 2,
                }
                : { x: projectedPoint.x, y: projectedPoint.y };
            const start = {
                x: Math.max(34, (mapBounds?.width || 0) - 54),
                y: Math.max(34, (mapBounds?.height || 0) - 64),
            };

            // Start at the lower-right corner, then visibly travel to the marker
            // before the click ripple and the actual marker click are triggered.
            setDemoCursor({ ...start, clicking: false, clickId: null });
            demoMoveTimerRef.current = window.setTimeout(() => {
                setDemoCursor({ ...target, clicking: false, clickId: null });
            }, 90);

            demoClickTimerRef.current = window.setTimeout(() => {
                const clickId = `${branch.id}-${Date.now()}`;
                setDemoCursor({ ...target, clicking: true, clickId });
                if (marker) {
                    marker.getElement().click();
                } else {
                    onSelectBranchRef.current?.(branch);
                    map.flyTo({
                        center: [branch.longitude, branch.latitude],
                        zoom: 13,
                        duration: 760,
                        essential: true,
                    });
                }
                setDemoCursor(null);
            }, 780);
        }, 3200);

        return () => {
            window.clearTimeout(demoZoomTimerRef.current);
            window.clearTimeout(demoPanTimerRef.current);
            window.clearTimeout(demoMoveTimerRef.current);
            window.clearTimeout(demoClickTimerRef.current);
        };
        // `located` is derived from `branches`; demoActiveId deliberately drives
        // this animation so normal search-map marker highlighting stays still.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [demoActiveId, mapReady]);

    // Keep the map sized correctly when the panel expands/collapses.
    useEffect(() => {
        const map = mapRef.current;
        if (!map) return undefined;
        resizeMapSoon();
        const timer = window.setTimeout(resizeMapSoon, 420);
        return () => window.clearTimeout(timer);
    }, [mapExpanded]);

    function switchLayer(next) {
        setBaseLayer(next);
        const map = mapRef.current;
        if (!map) return;
        setTilesLoading(true);
        finishLoadingSoon(520);
        map.setLayoutProperty('osm', 'visibility', next === 'maps' ? 'visible' : 'none');
        map.setLayoutProperty('satellite', 'visibility', next === 'satellite' ? 'visible' : 'none');
    }

    function locateMe() {
        const map = mapRef.current;
        if (!map || !navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((position) => {
            areaTrackingRef.current = true;
            map.flyTo({ center: [position.coords.longitude, position.coords.latitude], zoom: 12, duration: 800 });
        });
    }

    function zoomBy(direction) {
        const map = mapRef.current;
        if (!map) return;
        areaTrackingRef.current = true;
        if (direction > 0) {
            map.zoomIn();
        } else {
            map.zoomOut();
        }
    }

    return (
        <div className="map-canvas">
            <div
                ref={containerRef}
                className={tilesLoading ? `${'map-gl'} ${'map-gl-blurred'}` : 'map-gl'}
            />

            {demoCursor && (
                <span
                    className="map-demo-cursor"
                    aria-hidden="true"
                    style={{ left: demoCursor.x, top: demoCursor.y }}
                >
                    <MousePointer2 size={24} fill="currentColor" />
                    {demoCursor.clicking && <i key={demoCursor.clickId} />}
                </span>
            )}

            {tilesLoading && (
                <div className={'map-loading-overlay'} aria-hidden="true">
                    <span />
                    <span />
                    <span />
                </div>
            )}

            <button
                className={'map-expand'}
                type="button"
                aria-pressed={mapExpanded}
                aria-label={mapExpanded ? 'Minimize map' : 'Expand map'}
                onClick={onToggleExpand}
            >
                {mapExpanded ? <Minimize2 size={15} /> : <Maximize2 size={15} />}
                <span>{mapExpanded ? 'Minimize' : 'Expand'}</span>
            </button>

            <div className={'map-toggle'}>
                <button className={baseLayer === 'maps' ? 'active' : ''} type="button" onClick={() => switchLayer('maps')}>
                    <MapIcon size={15} />Maps
                </button>
                <button className={baseLayer === 'satellite' ? 'active' : ''} type="button" onClick={() => switchLayer('satellite')}>
                    <Layers size={15} />Satelit
                </button>
            </div>

            <div className={'map-zoom'}>
                <button type="button" aria-label="Perbesar" onClick={() => zoomBy(1)}><Plus size={16} /></button>
                <button type="button" aria-label="Perkecil" onClick={() => zoomBy(-1)}><Minus size={16} /></button>
            </div>
            <button className={'map-locate'} type="button" aria-label="My location" onClick={locateMe}><Locate size={16} /></button>

            <div className={attributionOpen ? 'map-attribution open' : 'map-attribution'}>
                <button
                    type="button"
                    aria-label="Attribution peta"
                    aria-expanded={attributionOpen}
                    onClick={() => setAttributionOpen((value) => !value)}
                />
                {attributionOpen && (
                    <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">
                        © OpenStreetMap
                    </a>
                )}
            </div>
        </div>
    );
}
