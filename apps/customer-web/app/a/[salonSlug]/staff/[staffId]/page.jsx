import { redirect } from 'next/navigation';

export default async function LegacyStaffProfilePage({ params }) {
    const { staffId } = await params;

    redirect(`/p/${encodeURIComponent(staffId)}`);
}
