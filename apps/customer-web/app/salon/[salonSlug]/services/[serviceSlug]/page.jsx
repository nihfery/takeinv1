import { notFound, redirect } from 'next/navigation';
import { getSearchPayload } from '../../../../../src/lib/landing-data.js';
import { findBranchByRoute, findServiceByRoute, getServicePath } from '../../../../../src/lib/salon-routes.js';

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
    const { branch } = await getBranchForRoute(salonSlug);
    const service = branch ? findServiceByRoute(branch.services || [], serviceSlug) : null;

    if (!branch || !service) notFound();

    redirect(getServicePath(branch, service));
}
