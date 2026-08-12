'use client';

import { useEffect, useMemo, useState } from 'react';
import dynamic from 'next/dynamic';
import { AnimatePresence, motion, useMotionValue, useSpring } from 'framer-motion';
import {
    Map as MapIcon,
    Heart,
    Star,
} from 'lucide-react';
import { FreshNavigation, FreshSearchForm } from './LandingPage.jsx';
import { getSalonPath } from '../lib/salon-routes.js';
import {
    branchMatchesTaxonomy,
    hasTaxonomyFilter,
    normalizeTaxonomyFilter,
    serviceMatchesTaxonomy,
} from '../lib/service-taxonomy.js';

function cx(...classes) {
    return classes.filter(Boolean).join(' ');
}


const EO = 'cubic-bezier(0, 0.55, 0.45, 1)';

const searchLayoutTransition = {
    type: 'tween',
    duration: 0.22,
    ease: [0.2, 0.8, 0.2, 1],
};



const SalonMap = dynamic(() => import('./SalonMap.jsx'), {
    ssr: false,
    loading: () => <div className="map-canvas map-loading" aria-hidden="true" />,
});

const LAST_SEARCH_URL_KEY = 'youyaku_last_search_url';

function formatPrice(value) {
    const price = Number(value || 0);
    if (!price) return 'Hubungi';
    return `IDR ${price.toLocaleString('en-US')}`;
}

// Client-side matchers that mirror the server filters in landing-data.js so that
// Pressing Search can re-filter the already-loaded list instantly (no page reload).
function branchMatchesService(branch, serviceLabel, taxonomyFilter = {}) {
    if (hasTaxonomyFilter(taxonomyFilter)) {
        return branchMatchesTaxonomy(branch, taxonomyFilter);
    }
    if (!serviceLabel) return true;
    const needle = serviceLabel.toLowerCase().trim();
    const categories = (branch.serviceCategories || []).map((category) => String(category).toLowerCase());
    if (categories.some((category) => category === needle || category.includes(needle) || needle.includes(category))) {
        return true;
    }
    return [branch.name, branch.provider].filter(Boolean).join(' ').toLowerCase().includes(needle);
}

function branchMatchesLocation(branch, loc) {
    if (!loc) return true;
    const needle = String(loc || '').toLowerCase().trim();
    const city = String(branch.city || '').toLowerCase().trim();
    const state = String(branch.state || '').toLowerCase().trim();
    const haystack = [city, state].filter(Boolean).join(' ');

    if (!needle) return true;
    if (haystack.includes(needle) || (city && needle.includes(city)) || (state && needle.includes(state))) {
        return true;
    }

    const queryTerms = needle
        .replace(/[^\p{L}\p{N}\s]/gu, ' ')
        .split(/\s+/)
        .filter((term) => term.length >= 3 && !['kota', 'kabupaten', 'kab', 'kecamatan', 'provinsi', 'indonesia', 'jawa', 'barat', 'timur', 'utara', 'selatan', 'tengah'].includes(term));

    return queryTerms.some((term) => haystack.includes(term));
}

function toFiniteNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

function distanceKm(fromLat, fromLng, toLat, toLng) {
    const earthRadiusKm = 6371;
    const toRadians = (value) => (value * Math.PI) / 180;
    const deltaLat = toRadians(toLat - fromLat);
    const deltaLng = toRadians(toLng - fromLng);
    const startLat = toRadians(fromLat);
    const endLat = toRadians(toLat);
    const a = Math.sin(deltaLat / 2) ** 2
        + Math.cos(startLat) * Math.cos(endLat) * Math.sin(deltaLng / 2) ** 2;

    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function branchMatchesCoords(branch, coords, radiusKm = 45) {
    if (!coords) return false;
    if (branch.latitude === null || branch.longitude === null) return false;
    return distanceKm(coords.lat, coords.lng, branch.latitude, branch.longitude) <= radiusKm;
}

function filterAndSortBranches(branches, { minPrice = null, maxPrice = null, minRating = null, sort = 'recommended' } = {}) {
    const minimumPrice = Number(minPrice) > 0 ? Number(minPrice) : null;
    const maximumPrice = Number(maxPrice) > 0 ? Number(maxPrice) : null;
    const minimumRating = Number(minRating) > 0 ? Number(minRating) : null;
    const filtered = branches.filter((branch) => {
        const price = Number(branch.minPrice || 0);
        const rating = Number(branch.rating || 0);

        if ((minimumPrice !== null || maximumPrice !== null) && price <= 0) return false;
        if (minimumPrice !== null && price < minimumPrice) return false;
        if (maximumPrice !== null && price > maximumPrice) return false;
        return minimumRating === null || rating >= minimumRating;
    });

    return [...filtered].sort((first, second) => {
        if (sort === 'rating_desc') return Number(second.rating || 0) - Number(first.rating || 0);
        if (sort === 'price_asc') return Number(first.minPrice || Number.MAX_SAFE_INTEGER) - Number(second.minPrice || Number.MAX_SAFE_INTEGER);
        if (sort === 'price_desc') return Number(second.minPrice || 0) - Number(first.minPrice || 0);
        if (sort === 'name_asc') return String(first.name || '').localeCompare(String(second.name || ''), 'id');
        return 0;
    });
}

function normalizeSearchService(value) {
    const service = String(value || '').trim();
    const label = service.toLowerCase();
    return ['all treatments', 'semua perawatan', 'semua treatment'].includes(label) ? '' : service;
}

function normalizeSearchLocation(value) {
    const location = String(value || '').trim();
    const label = location.toLowerCase();
    return ['current location', 'lokasi saat ini'].includes(label) ? '' : location;
}

function selectBranchesById(source, branchIds, fallback = []) {
    if (!Array.isArray(branchIds)) return fallback;

    const branchesById = new Map(source.map((branch) => [String(branch.id), branch]));
    return branchIds.map((id) => branchesById.get(String(id))).filter(Boolean);
}

function SalonResultCard({ branch, onPreview, onPreviewEnd, searchDate = '', taxonomyFilter = {} }) {
    const matchedService = hasTaxonomyFilter(taxonomyFilter)
        ? branch.services?.find((service) => serviceMatchesTaxonomy(service, taxonomyFilter))
        : null;
    const category = matchedService?.category
        || (branch.serviceCategories && branch.serviceCategories[0])
        || 'Salon';
    const reviews = Number(branch.reviews || 0);
    const rating = Number(branch.rating || 0);
    const hasReviews = reviews > 0 && rating > 0;

    const detailPath = searchDate
        ? `${getSalonPath(branch)}?date=${encodeURIComponent(searchDate)}`
        : getSalonPath(branch);

    return (
        <a className={'result-card'} href={detailPath}>
            <div
                className={'result-image'}
                onMouseEnter={(event) => onPreview(branch, event)}
                onMouseMove={(event) => onPreview(branch, event)}
                onMouseLeave={onPreviewEnd}
            >
                <img src={branch.image} alt={branch.name} loading="lazy" decoding="async" />
                {branch.tag && <span className={'result-badge'}>{branch.tag}</span>}
                <span className={'result-fav'} aria-hidden="true"><Heart size={16} /></span>
            </div>
            <div className={'result-body'}>
                <div className={'result-title-row'}>
                    <b className={'result-name'}>{branch.name}</b>
                    <span className={'result-rating-inline'}>
                        {hasReviews ? (
                            <>
                                <Star size={13} fill="currentColor" strokeWidth={0} />
                                {rating.toFixed(1)}
                            </>
                        ) : 'New'}
                    </span>
                </div>
                <p className={'result-location'}>{branch.city}{branch.state ? `, ${branch.state}` : ''}</p>
                <p className={'result-sub'}>
                    {category}
                    {reviews > 0 ? ` · ${reviews.toLocaleString('en-US')} reviews` : ''}
                </p>
            </div>
        </a>
    );
}

export function SearchResults({
    branches = [],
    mapBranches = [],
    allBranches = [],
    initialBranchIds = null,
    locations = [],
    providerUrl = '/provider',
    initialService = '',
    initialLocation = '',
    initialDate = '',
    initialTime = '',
    initialLat = '',
    initialLng = '',
    initialMinPrice = '',
    initialMaxPrice = '',
    initialMinRating = '',
    initialSort = 'recommended',
    initialCategoryId = '',
    initialCategorySlug = '',
    initialSubcategoryId = '',
    initialSubcategorySlug = '',
}) {
    const branchSource = allBranches.length ? allBranches : mapBranches.length ? mapBranches : branches;
    const [previewBranch, setPreviewBranch] = useState(null);
    const [slideIndex, setSlideIndex] = useState(0);
    const [mapExpanded, setMapExpanded] = useState(false);
    const [mapHidden, setMapHidden] = useState(false);
    const [viewBranches, setViewBranches] = useState(() => (
        selectBranchesById(branchSource, initialBranchIds, branches)
    ));

    // Applied filters drive the map exploration/focus sets. They start from the
    // Server-rendered query and update in place when the user presses Search.
    const [appliedService, setAppliedService] = useState(() => normalizeSearchService(initialService));
    const [appliedLocation, setAppliedLocation] = useState(() => normalizeSearchLocation(initialLocation));
    const [appliedDate, setAppliedDate] = useState(initialDate);
    const [appliedAdvancedFilters, setAppliedAdvancedFilters] = useState({
        minPrice: initialMinPrice,
        maxPrice: initialMaxPrice,
        minRating: initialMinRating,
        sort: initialSort || 'recommended',
    });
    const [appliedCoords, setAppliedCoords] = useState(() => {
        const location = normalizeSearchLocation(initialLocation);
        const lat = toFiniteNumber(initialLat);
        const lng = toFiniteNumber(initialLng);
        return location && lat !== null && lng !== null ? { lat, lng } : null;
    });
    const [appliedTaxonomyFilter, setAppliedTaxonomyFilter] = useState(() => normalizeTaxonomyFilter({
        categoryId: initialCategoryId,
        categorySlug: initialCategorySlug,
        subcategoryId: initialSubcategoryId,
        subcategorySlug: initialSubcategorySlug,
    }));

    const previewX = useMotionValue(0);
    const previewY = useMotionValue(0);
    const springConfig = { stiffness: 550, damping: 42, mass: 0.6 };
    const springX = useSpring(previewX, springConfig);
    const springY = useSpring(previewY, springConfig);

    // The map shows every salon matching the applied service (it can be panned to
    // other cities); the focus set is then narrowed to the applied location so the
    // map fits the searched area first.
    const exploreBranches = useMemo(
        () => filterAndSortBranches(
            branchSource
                .filter((branch) => branchMatchesService(branch, appliedService, appliedTaxonomyFilter)),
            appliedAdvancedFilters
        ),
        [branchSource, appliedService, appliedTaxonomyFilter, appliedAdvancedFilters]
    );
    const focusBranches = useMemo(() => {
        if (!appliedLocation && !appliedCoords) return exploreBranches;

        if (appliedCoords) {
            const nearby = exploreBranches.filter((branch) => branchMatchesCoords(branch, appliedCoords));
            if (nearby.length || !appliedLocation) return nearby;
        }

        return exploreBranches.filter((branch) => branchMatchesLocation(branch, appliedLocation));
    }, [exploreBranches, appliedLocation, appliedCoords]);

    const activeId = previewBranch?.id || null;
    const featured = previewBranch;
    const previewImages = (featured?.images && featured.images.length ? featured.images : [featured?.image]).filter(Boolean);
    const activeSlide = previewImages.length ? slideIndex % previewImages.length : 0;

    useEffect(() => {
        const query = window.location.search || '';
        sessionStorage.setItem(LAST_SEARCH_URL_KEY, `/search${query}`);
    }, []);

    useEffect(() => {
        const nextService = normalizeSearchService(initialService);
        const nextLocation = normalizeSearchLocation(initialLocation);
        const lat = toFiniteNumber(initialLat);
        const lng = toFiniteNumber(initialLng);
        const nextCoords = nextLocation && lat !== null && lng !== null ? { lat, lng } : null;

        setAppliedService(nextService);
        setAppliedLocation(nextLocation);
        setAppliedDate(initialDate);
        setAppliedAdvancedFilters({
            minPrice: initialMinPrice,
            maxPrice: initialMaxPrice,
            minRating: initialMinRating,
            sort: initialSort || 'recommended',
        });
        setAppliedCoords(nextCoords);
        setAppliedTaxonomyFilter(normalizeTaxonomyFilter({
            categoryId: initialCategoryId,
            categorySlug: initialCategorySlug,
            subcategoryId: initialSubcategoryId,
            subcategorySlug: initialSubcategorySlug,
        }));
        setViewBranches(selectBranchesById(branchSource, initialBranchIds, branches));
        setPreviewBranch(null);
    }, [
        branches,
        branchSource,
        initialBranchIds,
        initialService,
        initialLocation,
        initialLat,
        initialLng,
        initialMinPrice,
        initialMaxPrice,
        initialMinRating,
        initialSort,
        initialCategoryId,
        initialCategorySlug,
        initialSubcategoryId,
        initialSubcategorySlug,
    ]);

    // Auto-advance through the branch's photos while its preview is open.
    useEffect(() => {
        setSlideIndex(0);
        if (!previewBranch) return undefined;
        const count = previewBranch.images?.length || 0;
        if (count <= 1) return undefined;
        const timer = window.setInterval(() => {
            setSlideIndex((index) => index + 1);
        }, 1200);
        return () => window.clearInterval(timer);
    }, [previewBranch]);

    function handleFreshSearch(payload) {
        const serviceLabel = normalizeSearchService(payload?.service);
        const cleanLocation = normalizeSearchLocation(payload?.location);
        const coords = cleanLocation && payload?.coords
            && Number.isFinite(payload.coords.lat)
            && Number.isFinite(payload.coords.lng)
            ? { lat: payload.coords.lat, lng: payload.coords.lng }
            : null;

        const source = branchSource;
        const initialTaxonomyFilter = normalizeTaxonomyFilter({
            categoryId: initialCategoryId,
            categorySlug: initialCategorySlug,
            subcategoryId: initialSubcategoryId,
            subcategorySlug: initialSubcategorySlug,
        });
        const keepsSelectedSubcategory = normalizeSearchService(initialService).toLowerCase() === serviceLabel.toLowerCase();
        const nextTaxonomyFilter = keepsSelectedSubcategory
            ? initialTaxonomyFilter
            : normalizeTaxonomyFilter();
        const advancedFilters = {
            minPrice: payload?.minPrice,
            maxPrice: payload?.maxPrice,
            minRating: payload?.minRating,
            sort: payload?.sort || 'recommended',
        };
        const explore = filterAndSortBranches(
            source.filter((branch) => branchMatchesService(branch, serviceLabel, nextTaxonomyFilter)),
            advancedFilters
        );
        let focus = explore;

        if (coords) {
            const nearby = explore.filter((branch) => branchMatchesCoords(branch, coords));
            focus = nearby.length || !cleanLocation
                ? nearby
                : explore.filter((branch) => branchMatchesLocation(branch, cleanLocation));
        } else if (cleanLocation) {
            focus = explore.filter((branch) => branchMatchesLocation(branch, cleanLocation));
        }

        setAppliedService(serviceLabel);
        setAppliedLocation(cleanLocation);
        setAppliedDate(payload?.date || '');
        setAppliedAdvancedFilters(advancedFilters);
        setAppliedCoords(coords);
        setAppliedTaxonomyFilter(nextTaxonomyFilter);
        setViewBranches(focus);

        const query = new URLSearchParams(payload?.query || '');
        if (hasTaxonomyFilter(nextTaxonomyFilter)) {
            if (nextTaxonomyFilter.categoryId) query.set('category_id', nextTaxonomyFilter.categoryId);
            if (nextTaxonomyFilter.categorySlug) query.set('category', nextTaxonomyFilter.categorySlug);
            if (nextTaxonomyFilter.subcategoryId) query.set('subcategory_id', nextTaxonomyFilter.subcategoryId);
            if (nextTaxonomyFilter.subcategorySlug) query.set('subcategory', nextTaxonomyFilter.subcategorySlug);
        }
        const queryString = query.toString();
        const nextUrl = queryString ? `/search?${queryString}` : '/search';
        window.history.pushState({}, '', nextUrl);
        sessionStorage.setItem(LAST_SEARCH_URL_KEY, nextUrl);
    }

    function handleAreaChange(idsInView) {
        const set = new Set(idsInView);
        setViewBranches(exploreBranches.filter((branch) => set.has(branch.id)));
    }

    function handlePreview(branch, event) {
        const width = 320;
        const height = 300;
        const gap = 18;
        let left = event.clientX + gap;
        let top = event.clientY + gap;
        if (left + width > window.innerWidth) left = event.clientX - width - gap;
        if (top + height > window.innerHeight) top = window.innerHeight - height - 12;
        if (top < 12) top = 12;
        if (left < 12) left = 12;

        // When the preview first appears, jump to the cursor so it does not slide in
        // from a stale position; afterwards the spring follows the cursor smoothly.
        if (!previewBranch) {
            previewX.jump(left);
            previewY.jump(top);
            springX.jump(left);
            springY.jump(top);
        } else {
            previewX.set(left);
            previewY.set(top);
        }

        if (!previewBranch || previewBranch.id !== branch.id) {
            setPreviewBranch(branch);
        }
    }

    function clearPreview() {
        setPreviewBranch(null);
    }

    return (
        <div className={'search-page'}>
            <FreshNavigation
                providerUrl={providerUrl}
                searchSlot={(
                    <FreshSearchForm
                        initialService={appliedService}
                        initialLocation={appliedLocation}
                        initialDate={appliedDate}
                        initialTime={initialTime}
                        initialCoords={appliedCoords}
                        initialMinPrice={appliedAdvancedFilters.minPrice}
                        initialMaxPrice={appliedAdvancedFilters.maxPrice}
                        initialMinRating={appliedAdvancedFilters.minRating}
                        initialSort={appliedAdvancedFilters.sort}
                        showFilters
                        locations={locations}
                        onSearchPayload={handleFreshSearch}
                    />
                )}
            />
            <main className={'search-main'}>
                <motion.div
                    className={cx('search-layout', mapHidden && 'map-hidden', mapExpanded && 'map-expanded')}
                    layout
                    transition={searchLayoutTransition}
                >
                    {/* GRID 1 — wadah list branch (di dalamnya pakai flex) */}
                    <motion.div
                        className={'search-cell search-cell-list'}
                        layout
                        animate={{
                            opacity: mapExpanded ? 0 : 1,
                            x: mapExpanded ? -10 : 0,
                        }}
                        transition={searchLayoutTransition}
                        style={{ pointerEvents: mapExpanded ? 'none' : 'auto' }}
                    >
                        <section className={'search-results'}>
                            {viewBranches.length > 0 ? (
                                <div className={mapHidden ? 'result-grid wide' : 'result-grid'}>
                                    {viewBranches.map((branch) => (
                                        <SalonResultCard
                                            branch={branch}
                                            key={branch.id}
                                            searchDate={appliedDate}
                                            taxonomyFilter={appliedTaxonomyFilter}
                                            onPreview={handlePreview}
                                            onPreviewEnd={clearPreview}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className={'search-empty'}>
                                    <b>No matching salons found</b>
                                    <p>Try adjusting your search, changing the location, or exploring another area on the map.</p>
                                    <a className={'search-empty-link'} href="/search">View all salons</a>
                                </div>
                            )}
                        </section>
                    </motion.div>

                    {/* GRID 2 — wadah peta */}
                    <motion.div
                        className={'search-cell search-cell-map'}
                        layout
                        animate={{
                            opacity: mapHidden ? 0 : 1,
                            x: mapHidden ? 12 : 0,
                        }}
                        transition={searchLayoutTransition}
                        style={{ pointerEvents: mapHidden ? 'none' : 'auto' }}
                    >
                        <aside
                            className={'search-map'}
                            aria-label="Peta lokasi salon"
                            aria-hidden={mapHidden}
                        >
                            <div className="search-map-actions">
                                <button
                                    type="button"
                                    className="search-map-visibility"
                                    aria-pressed={mapHidden}
                                    onClick={() => setMapHidden((value) => !value)}
                                >
                                    <MapIcon size={15} />
                                    Hide map
                                </button>
                            </div>
                            <div className={'search-map-canvas'}>
                                <SalonMap
                                    branches={exploreBranches}
                                    focusBranches={focusBranches}
                                    focusCoords={appliedCoords}
                                    activeId={activeId}
                                    mapExpanded={mapExpanded}
                                    onToggleExpand={() => setMapExpanded((value) => !value)}
                                    onHoverBranch={handlePreview}
                                    onLeaveBranch={clearPreview}
                                    onAreaChange={handleAreaChange}
                                />
                            </div>
                        </aside>
                    </motion.div>
                    {mapHidden && (
                        <button
                            type="button"
                            className="search-map-reopen"
                            onClick={() => setMapHidden(false)}
                        >
                            <MapIcon size={15} />
                            Show map
                        </button>
                    )}
                </motion.div>
            </main>

            <AnimatePresence>
                {featured && (
                    <motion.div
                        key="search-preview"
                        className={'search-preview'}
                        style={{ left: springX, top: springY }}
                        initial={{ opacity: 0, scale: 0.92 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.92 }}
                        transition={{ duration: 0.16, ease: 'easeOut' }}
                        aria-hidden="true"
                    >
                        <div className={'search-preview-card'}>
                            <div className={'search-preview-image'}>
                                <AnimatePresence initial={false}>
                                    <motion.img
                                        key={`${featured.id}-${activeSlide}`}
                                        src={previewImages[activeSlide]}
                                        alt={featured.name}
                                        initial={{ opacity: 0 }}
                                        animate={{ opacity: 1 }}
                                        exit={{ opacity: 0 }}
                                        transition={{ duration: 0.45, ease: 'easeInOut' }}
                                        style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }}
                                    />
                                </AnimatePresence>
                                <span className={'search-preview-rating'}>
                                    <Star size={13} fill="currentColor" strokeWidth={0} />
                                    {Number(featured.rating || 5).toFixed(1)}
                                </span>
                                {previewImages.length > 1 && (
                                    <div className={'search-preview-dots'} aria-hidden="true">
                                        {previewImages.map((src, index) => (
                                            <span key={src + index} className={index === activeSlide ? 'active' : ''} />
                                        ))}
                                    </div>
                                )}
                            </div>
                            <div className={'search-preview-body'}>
                                <b>{featured.name}</b>
                                <small>{featured.city}{featured.state ? `, ${featured.state}` : ''}</small>
                                <div className={'search-preview-foot'}>
                                    <span>{featured.serviceCategories?.slice(0, 2).join(' · ') || 'Salon'}</span>
                                    <strong>{formatPrice(featured.minPrice)}</strong>
                                </div>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
