import { StaffProfileRoute } from '../../../src/components/StaffProfileRoute.jsx';
import { staffIdFromRoute, staffNameFromRoute } from '../../../src/lib/salon-routes.js';

export const dynamic = 'force-dynamic';

function apiBaseUrl() {
    return String(
        process.env.BACKEND_PROXY_URL
        || process.env.NEXT_PUBLIC_API_BASE_URL
        || process.env.VITE_API_BASE_URL
        || 'http://127.0.0.1:8000/api'
    ).replace(/\/$/, '');
}

export async function generateMetadata({ params }) {
    const { staffId } = await params;
    const staffName = staffNameFromRoute(staffId);
    const numericStaffId = staffIdFromRoute(staffId);
    let workplace = '';

    try {
        const response = await fetch(`${apiBaseUrl()}/staff/${encodeURIComponent(numericStaffId)}`, {
            next: { revalidate: 3600 },
        });
        const payload = response.ok ? await response.json() : null;
        workplace = String(payload?.data?.name || payload?.data?.branch_name || '').trim();
    } catch {
        // The readable staff URL remains useful even if the catalog is temporarily unavailable.
    }

    const professionalLabel = workplace ? `${staffName} di ${workplace}` : staffName;

    return {
        title: `${professionalLabel} | YouYaku`,
        description: workplace
            ? `Lihat profil, layanan, ulasan, dan lokasi ${staffName}, professional di ${workplace}.`
            : `Lihat profil, layanan, ulasan, dan lokasi ${staffName} di YouYaku.`,
        alternates: {
            canonical: `/p/${encodeURIComponent(staffId)}`,
        },
    };
}

export default async function StaffProfilePage({ params }) {
    const { staffId } = await params;

    return <StaffProfileRoute staffId={staffIdFromRoute(staffId)} />;
}
