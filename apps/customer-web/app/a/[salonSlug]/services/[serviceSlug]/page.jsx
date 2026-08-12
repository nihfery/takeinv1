import { notFound, redirect } from 'next/navigation';
import { getBranchInitialDetail, getSearchPayload } from '../../../../../src/lib/landing-data.js';
import {
    findBranchByRoute,
    findServiceByRoute,
    getSalonPath,
    getSalonRouteSlug,
    getServicePath,
    getServiceRouteSlug,
} from '../../../../../src/lib/salon-routes.js';
import { SalonDetailView } from '../../../../../src/components/SalonDetailView.jsx';

export const dynamic = 'force-dynamic';

async function getBranchForRoute(salonSlug) {
    const payload = await getSearchPayload();
    const branches = [
        ...(payload.allBranches?.length ? payload.allBranches : payload.branches),
        ...(payload.legacyBranches || []),
    ];
    const branch = findBranchByRoute(branches, salonSlug);

    return { payload, branches, branch };
}

function absoluteUrl(baseUrl, path) {
    const base = String(baseUrl || '').replace(/\/$/, '');
    return base ? `${base}${path}` : path;
}

export async function generateMetadata({ params }) {
    const { salonSlug, serviceSlug } = await params;
    const { payload, branch } = await getBranchForRoute(salonSlug);
    const service = branch ? findServiceByRoute(branch.services || [], serviceSlug) : null;

    if (!branch || !service) {
        return {
            title: 'Service not found | YouYaku',
        };
    }

    const serviceTitle = service.name || service.title || 'Service';
    const location = [branch.city, branch.state].filter(Boolean).join(', ') || 'Indonesia';
    const title = `${serviceTitle} di ${branch.name}, ${location} | YouYaku`;
    const description = `Booking ${serviceTitle} di ${branch.name}. Lihat harga, durasi, lokasi, dan jadwal tersedia.`;
    const canonicalPath = getServicePath(branch, service);

    return {
        title,
        description,
        alternates: {
            canonical: absoluteUrl(payload.customerAppUrl, canonicalPath),
        },
        openGraph: {
            title,
            description,
            url: absoluteUrl(payload.customerAppUrl, canonicalPath),
            images: branch.image ? [{ url: branch.image, alt: branch.name }] : [],
        },
    };
}

export default async function SalonServicePage({ params }) {
    const { salonSlug, serviceSlug } = await params;
    const { payload, branches, branch } = await getBranchForRoute(salonSlug);
    const service = branch ? findServiceByRoute(branch.services || [], serviceSlug) : null;

    if (!branch || !service) notFound();

    const canonicalSalonSlug = getSalonRouteSlug(branch);
    const canonicalServiceSlug = getServiceRouteSlug(service);
    if (decodeURIComponent(String(salonSlug || '')) !== canonicalSalonSlug
        || decodeURIComponent(String(serviceSlug || '')) !== canonicalServiceSlug) {
        redirect(getServicePath(branch, service));
    }

    const initialDetail = await getBranchInitialDetail(branch.id);
    const detailBranch = {
        ...branch,
        staff: initialDetail.staff,
        branchReviews: initialDetail.reviews,
        reviewSummary: initialDetail.summary,
        initialStaffLoaded: initialDetail.staff.length > 0,
        initialReviewsLoaded: initialDetail.reviews.length > 0 || Boolean(initialDetail.summary),
    };

    return (
        <SalonDetailView
            branch={detailBranch}
            nearbyBranches={branches.filter((item) => String(item.id) !== String(branch.id)).slice(0, 6)}
            providerUrl={payload.providerUrl}
            customerAppUrl="/"
            initialServiceRoute={canonicalServiceSlug}
        />
    );
}
