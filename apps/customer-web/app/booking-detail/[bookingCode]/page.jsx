'use client';

import { use, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getBookingsList } from '../../../src/lib/mock-state.js';
import { getCustomerBookingByCode, submitCustomerBookingReview } from '../../../src/lib/auth-api.js';
import { Calendar, ChevronLeft, Clock, CreditCard, Home, ListCollapse, MapPin, QrCode, ReceiptText, Star, User, X } from 'lucide-react';

function formatDetailPrice(value) {
    return `IDR ${Number(value || 0).toLocaleString('en-US')}`;
}

function formatDetailDuration(minutes) {
    const value = Number(minutes || 0);
    if (!value) return 'Not calculated yet';
    if (value < 60) return `${value} min`;

    const hours = Math.floor(value / 60);
    const rest = value % 60;
    return rest ? `${hours} hr ${rest} min` : `${hours} hr`;
}

function formatDetailDate(date) {
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

function isPaidBooking(booking) {
    return ['Paid', 'paid'].includes(booking?.paymentStatus);
}

function isWaitingPayment(booking) {
    if (booking?.paymentType === 'pay_at_salon' || booking?.paymentMethod === 'Pay at Venue') return false;

    return ['Waiting Payment', 'pending_payment'].includes(booking?.status)
        || ['pending', 'Pending', 'unpaid'].includes(booking?.paymentStatus);
}

function bookingStatusLabel(status) {
    if (['Confirmed', 'confirmed'].includes(status)) return 'Terkonfirmasi';
    if (['Waiting Payment', 'pending_payment'].includes(status)) return 'Awaiting payment';
    if (['pending_hold', 'Pending Hold'].includes(status)) return 'Slot Dikunci';
    if (['Pending', 'pending'].includes(status)) return 'Awaiting confirmation';
    if (status === 'rescheduled') return 'Rescheduled';
    if (['Completed', 'completed', 'order_completed'].includes(status)) return 'Completed';
    if (['expired_hold', 'payment_expired'].includes(status)) return 'Kedaluwarsa';
    if (['Cancelled', 'cancelled', 'customer_cancelled', 'provider_cancelled'].includes(status)) return 'Dibatalkan';
    return status || '-';
}

export default function BookingDetailPage({ params }) {
    const { bookingCode } = use(params);
    const router = useRouter();
    const [booking, setBooking] = useState(null);
    const [loadingBooking, setLoadingBooking] = useState(true);
    const [reviewRating, setReviewRating] = useState(0);
    const [staffRating, setStaffRating] = useState(0);
    const [reviewComment, setReviewComment] = useState('');
    const [staffReviewComment, setStaffReviewComment] = useState('');
    const [reviewImages, setReviewImages] = useState([]);
    const [reviewError, setReviewError] = useState('');
    const [reviewSubmitting, setReviewSubmitting] = useState(false);

    function handleBack() {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            router.back();
            return;
        }

        router.push('/activity');
    }

    const detailToolbar = (
        <header className="booking-route-toolbar">
            <div className="booking-route-toolbar-inner">
                <button className="booking-route-toolbar-button" type="button" onClick={handleBack} aria-label="Back">
                    <ChevronLeft size={28} />
                </button>
                <button className="booking-route-toolbar-button" type="button" onClick={() => router.push('/activity')} aria-label="Tutup detail booking">
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
            const found = bookings.find((item) => item.code === bookingCode);

            try {
                const backendBooking = await getCustomerBookingByCode(bookingCode, found || {});
                if (!cancelled) setBooking(backendBooking);
            } catch {
                if (!cancelled) setBooking(found || null);
            } finally {
                if (!cancelled) setLoadingBooking(false);
            }
        }

        loadBooking();

        return () => {
            cancelled = true;
        };
    }, [bookingCode]);

    if (loadingBooking) {
        return (
            <div className="fresh-landing success-route-shell">
                {detailToolbar}
                <main className="booking-container success-empty-state">
                    <ReceiptText size={48} />
                    <h3>Memuat detail booking</h3>
                    <p>Kami sedang mengambil data booking {bookingCode}.</p>
                </main>
            </div>
        );
    }

    if (!booking) {
        return (
            <div className="fresh-landing success-route-shell">
                {detailToolbar}
                <main className="booking-container success-empty-state">
                    <ReceiptText size={48} />
                    <h3>Booking details were not found</h3>
                    <p>Kode booking {bookingCode} tidak valid atau bukan milik akun ini.</p>
                    <button className="booking-action-btn success-empty-action" onClick={() => router.push('/activity')}>
                        Back to activity
                    </button>
                </main>
            </div>
        );
    }

    const services = booking.services || [];
    const addons = booking.addons || [];
    const participantCount = Math.max(1, Number(booking.participantCount || 1));
    const participantSelections = Array.isArray(booking.participantSelections)
        ? booking.participantSelections
        : [];
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
    const isPaid = isPaidBooking(booking);
    const waitingPayment = isWaitingPayment(booking);
    const payAtVenue = booking.paymentType === 'pay_at_salon' || booking.paymentMethod === 'Pay at Venue';
    const paymentStatusLabel = isPaid ? 'Lunas' : payAtVenue ? 'Bayar di salon' : 'Menunggu pembayaran';
    const isCompleted = ['completed', 'order_completed'].includes(String(booking.status || '').toLowerCase());
    const reviewStaffId = Number(booking?.staff?.id || 0) || null;
    const canReview = isCompleted && !booking.reviewed;

    async function handleReviewSubmit(event) {
        event.preventDefault();

        if (reviewRating < 1) {
            setReviewError('Please choose a rating for the venue first.');
            return;
        }

        if (staffReviewComment.trim() && staffRating < 1) {
            setReviewError('Please choose a staff rating before writing a staff review.');
            return;
        }

        setReviewSubmitting(true);
        setReviewError('');

        try {
            const updatedBooking = await submitCustomerBookingReview(booking.code, {
                rating: reviewRating,
                comment: reviewComment.trim() || null,
                images: reviewImages,
                ...(reviewStaffId && staffRating > 0 ? {
                    staff_id: reviewStaffId,
                    staff_rating: staffRating,
                    staff_comment: staffReviewComment.trim() || null,
                } : {}),
            });

            setBooking(updatedBooking);
            setReviewComment('');
            setStaffReviewComment('');
            setReviewImages([]);
        } catch (error) {
            setReviewError(error?.message || 'Your review could not be submitted.');
        } finally {
            setReviewSubmitting(false);
        }
    }

    return (
        <div className="fresh-landing success-route-shell detail-route-shell">
            {detailToolbar}
            <main className="booking-container success-modern-page detail-modern-page">
                <section className="success-modern-hero detail-modern-hero">
                    <div className="success-modern-icon">
                        <ReceiptText size={38} />
                    </div>
                    <div className="success-modern-copy">
                        <span>Booking details</span>
                        <h1>Informasi reservasi</h1>
                        <p>Pantau jadwal, layanan, peserta, dan status pembayaran reservasi ini.</p>
                    </div>
                </section>

                <div className="success-modern-grid detail-modern-grid">
                    <section className="success-modern-ticket detail-modern-ticket">
                        <header className="success-modern-ticket-head">
                            <div>
                                <span>Salon name</span>
                                <strong>{booking.salonName}</strong>
                                <small>
                                    <MapPin size={13} />
                                    {booking.salonAddress || 'Salon address is not available'}
                                </small>
                            </div>
                            <div className="success-modern-code">
                                <span>Kode booking</span>
                                <b>{booking.code}</b>
                            </div>
                        </header>

                        <div className="success-modern-meta">
                            <div>
                                <span>Main schedule</span>
                                <b><Calendar size={14} /> {formatDetailDate(booking.date)}</b>
                                <b><Clock size={14} /> {booking.time || '-'} WIB</b>
                                <b><Clock size={14} /> {formatDetailDuration(booking.duration)}</b>
                            </div>
                            <div>
                                <span>Status reservasi</span>
                                <b><ListCollapse size={14} /> {bookingStatusLabel(booking.status)}</b>
                                <b><User size={14} /> {getStaffName(booking.staff)}</b>
                                <b><User size={14} /> {participantCount} peserta</b>
                            </div>
                        </div>

                        <div className="success-modern-services">
                            <span>Order details</span>
                            {hasParticipantDetails ? participantSelections.map((participant, participantIndex) => (
                                <section className="success-participant-services" key={participant.position || participantIndex}>
                                    <div className="success-participant-services-heading">
                                        <b>{participant.name || `Participant ${participantIndex + 1}`}</b>
                                        <small>
                                            {getStaffName(participant.staff)}
                                            {' - '}{formatDetailDate(participant.date)}, {participant.time || '-'} WIB
                                        </small>
                                    </div>
                                    {(participant.services || []).map((service, serviceIndex) => (
                                        <div className="success-modern-service-line" key={`${participantIndex}-${service.id || serviceIndex}`}>
                                            <div>
                                                <b>{service.name}</b>
                                                <small>{formatDetailDuration(service.duration)}</small>
                                            </div>
                                            <strong>{formatDetailPrice(service.discountPrice || service.price)}</strong>
                                        </div>
                                    ))}
                                </section>
                            )) : services.map((service, index) => (
                                <div className="success-modern-service-line" key={`service-${service.id || index}`}>
                                    <div>
                                        <b>{service.name}</b>
                                        <small>
                                            {formatDetailDuration(service.duration)}
                                            {participantCount > 1 ? ` × ${participantCount} peserta` : ''}
                                        </small>
                                    </div>
                                    <strong>{formatDetailPrice(Number(service.discountPrice || service.price) * participantCount)}</strong>
                                </div>
                            ))}
                            {addons.map((addon, index) => (
                                <div className="success-modern-service-line" key={`addon-${addon.id || index}`}>
                                    <div>
                                        <b>{addon.name}</b>
                                        <small>Add-on, +{formatDetailDuration(addon.duration)}</small>
                                    </div>
                                    <strong>+ {formatDetailPrice(Number(addon.price) * (hasParticipantDetails ? 1 : participantCount))}</strong>
                                </div>
                            ))}
                            {!services.length && !addons.length && !hasParticipantDetails && (
                                <p className="activity-empty-line">Services are not available yet.</p>
                            )}
                        </div>

                        <div className="success-modern-qr">
                            <div>
                                <QrCode size={58} strokeWidth={1.7} />
                            </div>
                            <section>
                                <strong>QR Code check-in</strong>
                                <p>Tunjukkan QR ini kepada resepsionis salon saat kamu tiba.</p>
                            </section>
                        </div>
                    </section>

                    <aside className="success-modern-summary detail-modern-summary">
                        <h2>Ringkasan pembayaran</h2>
                        <div className="success-modern-total-list">
                            <p><span>Subtotal layanan</span><b>{formatDetailPrice(orderSubtotal)}</b></p>
                            {discountAmount > 0 && (
                                <p className="discount"><span>Diskon voucher</span><b>- {formatDetailPrice(discountAmount)}</b></p>
                            )}
                            {feeAmount > 0 && (
                                <p><span>Pajak &amp; biaya</span><b>{formatDetailPrice(feeAmount)}</b></p>
                            )}
                            <p><span>Metode</span><b>{booking.paymentMethod || '-'}</b></p>
                            <p><span>Status</span><b className={isPaid ? 'paid' : 'pending'}>{paymentStatusLabel}</b></p>
                            <p className="grand"><span>Total</span><b>{formatDetailPrice(totalAmount)}</b></p>
                        </div>

                        <div className="success-modern-actions">
                            {waitingPayment && (
                                <button className="booking-action-btn" onClick={() => router.push(`/payment/${booking.code}`)}>
                                    <CreditCard size={16} />
                                    Bayar sekarang
                                </button>
                            )}
                            {canReview && (
                                <form className="detail-review-card" onSubmit={handleReviewSubmit}>
                                    <div className="detail-review-heading">
                                        <Star size={18} fill="currentColor" />
                                        <div>
                                            <b>Bagikan pengalamanmu</b>
                                            <small>Nilai tempat dan profesional secara terpisah.</small>
                                        </div>
                                    </div>

                                    <label className="detail-review-field">
                                        <span>Rating tempat</span>
                                        <div className="detail-review-stars" aria-label="Rating tempat">
                                            {[1, 2, 3, 4, 5].map((value) => (
                                                <button
                                                    key={value}
                                                    type="button"
                                                    className={reviewRating >= value ? 'is-selected' : ''}
                                                    onClick={() => setReviewRating(value)}
                                                    aria-label={`${value} bintang untuk tempat`}
                                                >
                                                    <Star size={22} fill="currentColor" />
                                                </button>
                                            ))}
                                        </div>
                                    </label>

                                    {reviewStaffId && (
                                        <label className="detail-review-field">
                                            <span>Rating staff untuk {getStaffName(booking.staff)} <em>(opsional)</em></span>
                                            <div className="detail-review-stars" aria-label="Rating profesional">
                                                {[1, 2, 3, 4, 5].map((value) => (
                                                    <button
                                                        key={value}
                                                        type="button"
                                                        className={staffRating >= value ? 'is-selected' : ''}
                                                        onClick={() => setStaffRating(value)}
                                                        aria-label={`${value} bintang untuk staff`}
                                                    >
                                                        <Star size={22} fill="currentColor" />
                                                    </button>
                                                ))}
                                            </div>
                                            <textarea
                                                value={staffReviewComment}
                                                onChange={(event) => setStaffReviewComment(event.target.value)}
                                                maxLength={1000}
                                                placeholder={`Ceritakan pelayanan ${getStaffName(booking.staff)}`}
                                            />
                                        </label>
                                    )}

                                    <label className="detail-review-field">
                                        <span>Venue review <em>(optional)</em></span>
                                        <textarea
                                            value={reviewComment}
                                            onChange={(event) => setReviewComment(event.target.value)}
                                            maxLength={1000}
                                            placeholder="Ceritakan kondisi tempat, kenyamanan, dan fasilitas salon"
                                        />
                                    </label>

                                    <label className="detail-review-upload">
                                        <span>Foto tempat <em>(opsional, maksimal 5 foto)</em></span>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            multiple
                                            onChange={(event) => setReviewImages(Array.from(event.target.files || []).slice(0, 5))}
                                        />
                                        {reviewImages.length > 0 && (
                                            <small>{reviewImages.map((image) => image.name).join(', ')}</small>
                                        )}
                                    </label>

                                    {reviewError && <p className="detail-review-error">{reviewError}</p>}
                                    <button className="booking-action-btn detail-review-submit" type="submit" disabled={reviewSubmitting}>
                                        {reviewSubmitting ? 'Mengirim ulasan...' : 'Kirim ulasan'}
                                    </button>
                                </form>
                            )}
                            {isCompleted && booking.reviewed && (
                                <div className="detail-review-complete">
                                    <Star size={17} fill="currentColor" />
                                    <span>Ulasanmu sudah dikirim. Terima kasih!</span>
                                </div>
                            )}
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
