import { SearchResults } from '../../src/components/SearchResults.jsx';
import { getSearchPayload } from '../../src/lib/landing-data.js';

export const dynamic = 'force-dynamic';

function pickParam(value) {
    if (Array.isArray(value)) return value[0] || '';
    return value || '';
}

export default async function SearchPage({ searchParams }) {
    const params = (await searchParams) || {};

    const service = pickParam(params.service || params.keyword || params.q);
    const location = pickParam(params.location || params['result-location']);
    const date = pickParam(params.date);
    const time = pickParam(params.time);
    const lat = location ? pickParam(params.lat) : '';
    const lng = location ? pickParam(params.lng) : '';
    const minPrice = pickParam(params.min_price);
    const maxPrice = pickParam(params.max_price);
    const minRating = pickParam(params.min_rating);
    const sort = pickParam(params.sort) || 'recommended';
    const categoryId = pickParam(params.category_id);
    const categorySlug = pickParam(params.category);
    const subcategoryId = pickParam(params.subcategory_id);
    const subcategorySlug = pickParam(params.subcategory);

    const payload = await getSearchPayload({
        service,
        location,
        date,
        lat,
        lng,
        minPrice,
        maxPrice,
        minRating,
        sort,
        categoryId,
        categorySlug,
        subcategoryId,
        subcategorySlug,
    });

    return (
        <SearchResults
            {...payload}
            initialService={service}
            initialLocation={location}
            initialDate={date}
            initialTime={time}
            initialLat={lat}
            initialLng={lng}
            initialMinPrice={minPrice}
            initialMaxPrice={maxPrice}
            initialMinRating={minRating}
            initialSort={sort}
            initialCategoryId={categoryId}
            initialCategorySlug={categorySlug}
            initialSubcategoryId={subcategoryId}
            initialSubcategorySlug={subcategorySlug}
        />
    );
}
