import { notFound, redirect } from 'next/navigation';
import { getSearchPayload } from '../../../src/lib/landing-data.js';
import { findBranchByRoute, getSalonPath } from '../../../src/lib/salon-routes.js';

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
    const { salonSlug } = await params;
    const { payload, branch } = await getBranchForRoute(salonSlug);

    if (!branch) {
        return {
            title: 'Salon tidak ditemukan | YouYaku',
        };
    }

    const location = [branch.city, branch.state].filter(Boolean).join(', ') || 'Indonesia';
    const category = (branch.serviceCategories && branch.serviceCategories[0]) || branch.provider || 'Salon';
    const title = `${branch.name} - ${category} di ${location} | YouYaku`;
    const description = `Booking ${branch.name} di ${location}. Lihat layanan, harga, ulasan, lokasi, dan jadwal tersedia.`;
    const canonicalPath = getSalonPath(branch);

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

export default async function SalonDetailPage({ params }) {
    const { salonSlug } = await params;
    const { branch } = await getBranchForRoute(salonSlug);

    if (!branch) notFound();

    redirect(getSalonPath(branch));
}
