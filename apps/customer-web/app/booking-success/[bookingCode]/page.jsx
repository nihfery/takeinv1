'use client';

import { use, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getBookingsList } from '../../../src/lib/mock-state.js';
import { getCustomerBookingByCode } from '../../../src/lib/auth-api.js';
import { Calendar, CheckCircle, ChevronLeft, Clock, Home, ListCollapse, MapPin, QrCode, ReceiptText, User, X } from 'lucide-react';

function formatSuccessPrice(value) {
    return `IDR ${Number(value || 0).toLocaleString('en-US')}`;
}

function formatSuccessDuration(minutes) {
    const value = Number(minutes || 0);
    if (!value) return 'Not calculated yet';
    if (value < 60) return `${value} min`;

    const hours = Math.floor(value / 60);
    const rest = value % 60;
    return rest ? `${hours} hr ${rest} min` : `${hours} hr`;
}

function formatSuccessDate(date) {
    if (!date) return 'Not selected';

    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return 'Not selected';

    return parsed.toLocaleDateString('en-US', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function getStaffName(staff) {
    if (!staff || staff === 'any') return 'Siapa Saja (Any Staff)';
    return staff.name || String(staff);
}

export default function BookingSuccessPage({ params }) {
    const { bookingCode } = use(params);
    const router = useRouter();
    const [booking, setBooking] = useState(null);
    const [loadingBooking, setLoadingBooking] = useState(true);
    const successToolbar = (
        <header className="booking-route-toolbar">
            <div className="booking-route-toolbar-inner">
                <button className="booking-route-toolbar-button" type="button" onClick={() => router.push('/activity')} aria-label="Back to activity">
                    <ChevronLeft size={28} />
                </button>
                <button className="booking-route-toolbar-button" type="button" onClick={() => router.replace('/')} aria-label="Tutup halaman sukses">
                    <X size={28} />
                </button>
            </div>
        </header>
    );

    useEffect(() => {
        let cancelled = false;

        async function loadBooking() {
            setLoadingBooking(true);
            const bookings = getBookingsList();
            const found = bookings.find(b => b.code === bookingCode);
            if (found) {
                setBooking(found);
                setLoadingBooking(false);
                return;
            }

            try {
                const backendBooking = await getCustomerBookingByCode(bookingCode);
                if (!cancelled) setBooking(backendBooking);
            } catch {
                if (!cancelled) setBooking(null);
            } finally {
                if (!cancelled) setLoadingBooking(false);
            }
        }

        loadBooking();

        return () => {
            cancelled = true;
        };
    }, [bookingCode]);

    useEffect(() => {
        if (loadingBooking || !booking) return undefined;

        window.dispatchEvent(new Event('salonku-activity-change'));
        return undefined;
    }, [booking, loadingBooking]);

    if (loadingBooking) {
        return (
            <div className="fresh-landing success-route-shell">
                {successToolbar}
                <main className="booking-container success-empty-state">
                    <ReceiptText size={48} />
                    <h3>Memuat reservasi</h3>
                    <p>Kami sedang mengambil data booking {bookingCode}.</p>
                </main>
            </div>
        );
    }

    if (!booking) {
        return (
            <div className="fresh-landing success-route-shell">
                {successToolbar}
                <main className="booking-container success-empty-state">
                    <ReceiptText size={48} />
                    <h3>Data reservasi tidak ditemukan</h3>
                    <p>Kode booking {bookingCode} tidak valid.</p>
                    <button className="booking-action-btn success-empty-action" onClick={() => router.push('/')}>
                        Back to home
                    </button>
                </main>
            </div>
        );
    }

    const services = booking.services || [];
    const addons = booking.addons || [];
    const participantCount = Math.max(1, Number(booking.participantCount || 1));
    const participantSelections = Array.isArray(booking.participantSelections) ? booking.participantSelections : [];
    const hasParticipantDetails = participantSelections.length > 0
        && participantSelections.every((participant) => (participant.services || []).length > 0);
    const sharedSubtotal = (
        services.reduce((sum, service) => sum + Number(service.discountPrice || service.price || 0), 0)
        + addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0)
    ) * participantCount;
    const participantSubtotal = participantSelections.reduce((sum, participant) => (
        sum + Number(participant.subtotal || (participant.services || []).reduce(
            (serviceSum, service) => serviceSum + Number(service.discountPrice || service.price || 0),
            0
        ))
    ), 0) + addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
    const orderSubtotal = hasParticipantDetails ? participantSubtotal : sharedSubtotal;
    const discountAmount = Number(booking.discount || 0);
    const totalAmount = Number(booking.total || 0);
    const feeAmount = Math.max(0, totalAmount - Math.max(0, orderSubtotal - discountAmount));
    const isPaid = ['Paid', 'paid'].includes(booking.paymentStatus);

    const handleAddToCalendar = () => {
        alert('Appointment added to your Google & Apple calendars (simulation).');
    };

    return (
        <div className="fresh-landing success-route-shell">
            {successToolbar}
            <main className="booking-container success-page success-modern-page">
                <section className="success-modern-hero">
                    <div className="success-modern-icon">
                        <CheckCircle size={44} />
                    </div>
                    <div className="success-modern-copy">
                        <span>Booking Confirmed</span>
                        <h1>Reservasi berhasil dibuat</h1>
                        <p>Your check-in ticket is ready. Reservation details are saved in your account activity.</p>
                    </div>
                </section>

                <div className="success-modern-grid">
                    <section className="success-modern-ticket">
                        <header className="success-modern-ticket-head">
                            <div>
                                <span>Salon name</span>
                                <strong>{booking.salonName}</strong>
                                <small>
                                    <MapPin size={13} />
                                    {booking.salonAddress}
                                </small>
                            </div>
                            <div className="success-modern-code">
                                <span>Kode Booking</span>
                                <b>{booking.code}</b>
                            </div>
                        </header>

                        <div className="success-modern-meta">
                            <div>
                                <span>Date and time</span>
                                <b><Calendar size={14} /> {formatSuccessDate(booking.date)}</b>
                                <b><Clock size={14} /> {booking.time} WIB</b>
                            </div>
                            <div>
                                <span>Durasi & Profesional</span>
                                <b><Clock size={14} /> {formatSuccessDuration(booking.duration)}</b>
                                <b><User size={14} /> {getStaffName(booking.staff)}</b>
                                <b><User size={14} /> {booking.participantCount || 1} peserta</b>
                            </div>
                        </div>

                        <div className="success-modern-services">
                            <span>Services</span>
                            {hasParticipantDetails ? participantSelections.map((participant, participantIndex) => (
                                <section className="success-participant-services" key={participant.position || participantIndex}>
                                    <div className="success-participant-services-heading">
                                        <b>{participant.name || `Participant ${participantIndex + 1}`}</b>
                                        <small>
                                            {participant.staff === 'any' ? 'Siapa Saja' : (participant.staff?.name || 'Professional')}
                                            {' - '}{formatSuccessDate(participant.date)}, {participant.time || '-'} WIB
                                        </small>
                                    </div>
                                    {(participant.services || []).map((service) => (
                                        <div className="success-modern-service-line" key={`${participantIndex}-${service.id}`}>
                                            <div>
                                                <b>{service.name}</b>
                                                <small>{formatSuccessDuration(service.duration)}</small>
                                            </div>
                                            <strong>{formatSuccessPrice(service.discountPrice || service.price)}</strong>
                                        </div>
                                    ))}
                                </section>
                            )) : services.map((service) => (
                                <div className="success-modern-service-line" key={service.id}>
                                    <div>
                                        <b>{service.name}</b>
                                        <small>
                                            {formatSuccessDuration(service.duration)}
                                            {participantCount > 1 ? ` × ${participantCount} peserta` : ''}
                                        </small>
                                    </div>
                                    <strong>{formatSuccessPrice(Number(service.discountPrice || service.price) * participantCount)}</strong>
                                </div>
                            ))}
                            {addons.map((addon) => (
                                <div className="success-modern-service-line" key={addon.id}>
                                    <div>
                                        <b>{addon.name}</b>
                                        <small>Add-on, +{formatSuccessDuration(addon.duration)}</small>
                                    </div>
                                    <strong>+ {formatSuccessPrice(Number(addon.price) * (hasParticipantDetails ? 1 : participantCount))}</strong>
                                </div>
                            ))}
                        </div>

                        <div className="success-modern-qr">
                            <div>
                                <QrCode size={58} strokeWidth={1.7} />
                            </div>
                            <section>
                                <strong>Scan QR Code Check-in</strong>
                                <p>Tunjukkan QR ini kepada resepsionis salon saat kamu tiba.</p>
                            </section>
                        </div>
                    </section>

                    <aside className="success-modern-summary">
                        <h2>Ringkasan pembayaran</h2>
                        <div className="success-modern-total-list">
                            <p><span>Subtotal layanan</span><b>{formatSuccessPrice(orderSubtotal)}</b></p>
                            {discountAmount > 0 && (
                                <p className="discount"><span>Diskon Voucher</span><b>- {formatSuccessPrice(discountAmount)}</b></p>
                            )}
                            {feeAmount > 0 && (
                                <p><span>Pajak &amp; biaya</span><b>{formatSuccessPrice(feeAmount)}</b></p>
                            )}
                            <p><span>Metode</span><b>{booking.paymentMethod}</b></p>
                            <p><span>Status</span><b className={isPaid ? 'paid' : 'pending'}>{isPaid ? 'Paid' : 'Awaiting payment'}</b></p>
                            <p className="grand"><span>Total</span><b>{formatSuccessPrice(totalAmount)}</b></p>
                        </div>

                        <div className="success-modern-actions">
                            <button className="booking-action-btn" onClick={handleAddToCalendar}>
                                <Calendar size={16} />
                                Tambahkan ke Kalender
                            </button>
                            <button type="button" className="payment-secondary-btn" onClick={() => router.push('/activity')}>
                                <ListCollapse size={16} />
                                Lihat Aktivitas
                            </button>
                            <button type="button" className="payment-secondary-btn" onClick={() => router.push('/')}>
                                <Home size={16} />
                                Back to home
                            </button>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    );
}
