'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { FreshNavigation } from '../../src/components/LandingPage.jsx';
import { saveUserProfile, setSessionUser } from '../../src/lib/mock-state.js';
import { fetchCurrentCustomer, getCustomerActivity } from '../../src/lib/auth-api.js';
import { PROVIDER_FRONTEND_URL } from '../../src/lib/app-urls.js';

function formatPrice(value) {
    return `IDR ${Number(value || 0).toLocaleString('en-US')}`;
}

function formatDate(date) {
    if (!date) return '-';
    const parsed = new Date(`${date}T00:00:00`);
    return Number.isNaN(parsed.getTime()) ? '-' : parsed.toLocaleDateString('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function statusLabel(status) {
    const labels = {
        pending_payment: 'Awaiting payment',
        confirmed: 'Terkonfirmasi',
        waiting: 'Menunggu Dilayani',
        checked_in: 'Sudah Check-in',
        in_progress: 'Sedang Dilayani',
        inprogress: 'Sedang Dilayani',
        rescheduled: 'Rescheduled',
        completed: 'Completed',
        order_completed: 'Completed',
        cancelled: 'Dibatalkan',
        customer_cancelled: 'Dibatalkan Customer',
        provider_cancelled: 'Dibatalkan Salon',
        payment_expired: 'Payment expired',
        no_show: 'No show',
    };

    return labels[String(status || '').toLowerCase()] || status || '-';
}

export default function ActivityPage() {
    const router = useRouter();
    const [bookings, setBookings] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;
        let redirecting = false;

        async function loadBookings() {
            try {
                const auth = await fetchCurrentCustomer();
                if (cancelled) return;

                setSessionUser({ loggedIn: true, user: auth.profile });
                saveUserProfile(auth.profile);

                const result = await getCustomerActivity();
                if (!cancelled) {
                    setBookings(result.filter((activity) => activity.booking_id));
                }
            } catch {
                if (!cancelled) {
                    redirecting = true;
                    setSessionUser({ loggedIn: false, user: null });
                    router.replace('/auth?next=/activity');
                }
            } finally {
                if (!cancelled && !redirecting) setLoading(false);
            }
        }

        loadBookings();
        return () => {
            cancelled = true;
        };
    }, [router]);

    return (
        <div className="fresh-landing activity-route-shell">
            <FreshNavigation providerUrl={PROVIDER_FRONTEND_URL} customerAppUrl="/" />
            <main className="booking-container activity-page-container activity-simple-page">
                <header className="activity-simple-header">
                    <span>Activity</span>
                    <h1>Booking saya</h1>
                    <p>All bookings you make will appear here.</p>
                </header>

                <section className="activity-group activity-booking-group">
                    {loading && (
                        <div className="activity-service-list activity-empty-card">
                            <p>Memuat booking...</p>
                        </div>
                    )}
                    {!loading && bookings.length === 0 && (
                        <div className="activity-service-list activity-empty-card">
                            <h3>No bookings yet</h3>
                            <p>Booking yang sudah dibuat akan ditampilkan di sini.</p>
                            <button className="booking-action-btn" type="button" onClick={() => router.push('/search')}>
                                Find a salon
                            </button>
                        </div>
                    )}
                    {!loading && bookings.map((booking) => (
                        <article className="activity-service-list activity-booking-card" key={booking.id}>
                            <div className="activity-service-row">
                                <div>
                                    <b>{booking.salon_name}</b>
                                    <small>
                                        {statusLabel(booking.status)} · {formatDate(booking.date)} · {booking.time || '-'}
                                    </small>
                                    <small>
                                        {(booking.services || []).map((service) => service.name).join(', ') || 'Services are not available yet'}
                                    </small>
                                </div>
                                <div className="activity-booking-actions">
                                    <strong>{formatPrice(booking.total)}</strong>
                                    <button
                                        className="booking-action-btn"
                                        type="button"
                                        onClick={() => router.push(
                                            booking.status === 'pending_payment'
                                                ? `/payment/${booking.code}`
                                                : `/booking-detail/${booking.code}`
                                        )}
                                    >
                                        {booking.status === 'pending_payment'
                                            ? 'Bayar'
                                            : booking.can_review
                                                ? 'Leave a review'
                                                : 'View details'}
                                    </button>
                                </div>
                            </div>
                        </article>
                    ))}
                </section>
            </main>
        </div>
    );
}
