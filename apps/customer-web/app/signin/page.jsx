import { redirect } from 'next/navigation';

export default async function LegacySignInPage({ searchParams }) {
    const params = await searchParams;
    const next = params?.next;
    const query = next ? `?next=${encodeURIComponent(next)}` : '';

    redirect(`/auth${query}`);
}
