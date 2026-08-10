'use client';

import { useEffect, useRef } from 'react';
import maplibregl from 'maplibre-gl';

function mapStyle() {
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
        },
        layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
    };
}

export function BranchLocationMap({ branch }) {
    const containerRef = useRef(null);
    const latitude = Number(branch?.latitude);
    const longitude = Number(branch?.longitude);
    const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude);

    useEffect(() => {
        const container = containerRef.current;
        if (!container || !hasCoordinates) return undefined;

        const map = new maplibregl.Map({
            container,
            style: mapStyle(),
            center: [longitude, latitude],
            zoom: 15,
            fadeDuration: 0,
            attributionControl: false,
        });
        // The map remains optional UI. Register an error listener so a failed
        // external OpenStreetMap tile does not surface as an uncaught browser
        // error (for example immediately after a logout redirects home).
        map.on('error', () => {});
        const marker = document.createElement('button');
        marker.type = 'button';
        marker.className = 'map-marker';
        marker.textContent = branch?.name || 'Salon location';
        marker.setAttribute('aria-label', `Location: ${branch?.name || 'salon'}`);
        new maplibregl.Marker({ element: marker, anchor: 'bottom', offset: [0, -6] })
            .setLngLat([longitude, latitude])
            .addTo(map);
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

        const observer = new ResizeObserver(() => map.resize());
        observer.observe(container);
        map.on('load', () => map.resize());

        return () => {
            observer.disconnect();
            map.remove();
        };
    }, [branch?.name, hasCoordinates, latitude, longitude]);

    if (!hasCoordinates) {
        return <div className="staff-profile-map staff-profile-map-unavailable">Titik lokasi belum tersedia.</div>;
    }

    return <div ref={containerRef} className="staff-profile-map" aria-label={`Peta lokasi ${branch?.name || 'salon'}`} />;
}
