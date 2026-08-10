'use client';

import { use, useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getBookingsList, saveBookingsList } from '../../../src/lib/mock-state.js';
import {
    chargeCustomerBookingPayment,
    confirmCustomerPaymentByCode,
    getCustomerBookingByCode,
    refreshCustomerBookingPaymentStatus,
} from '../../../src/lib/auth-api.js';
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    Clock,
    Copy,
    ExternalLink,
    LoaderCircle,
    ReceiptText,
    RefreshCw,
    ShieldCheck,
    X,
} from 'lucide-react';

const ALLOW_MANUAL_PAYMENT_CONFIRMATION = process.env.NEXT_PUBLIC_ALLOW_MANUAL_PAYMENT_CONFIRMATION === 'true';

function formatPaymentPrice(value) {
    return `IDR ${Number(value || 0).toLocaleString('en-US')}`;
}

function formatPaymentDuration(minutes) {
    const value = Number(minutes || 0);
    if (!value) return 'Not calculated yet';
    if (value < 60) return `${value} min`;

    const hours = Math.floor(value / 60);
    const rest = value % 60;
    return rest ? `${hours} hr ${rest} min` : `${hours} hr`;
}

function formatPaymentDate(date) {
    if (!date) return 'Not selected';

    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return 'Not selected';

    return parsed.toLocaleDateString('en-US', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

function normalizedPaymentStatus(value) {
    return String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
}

function paymentIsPaid(booking) {
    return normalizedPaymentStatus(booking?.paymentStatus) === 'paid';
}

function paymentIsExpired(booking) {
    const paymentStatus = normalizedPaymentStatus(booking?.paymentStatus);
    const bookingStatus = normalizedPaymentStatus(booking?.status);

    if (['expired', 'failed'].includes(paymentStatus) || bookingStatus === 'payment_expired') return true;
    if (!booking?.paymentExpiresAt) return false;

    const expiresAt = new Date(booking.paymentExpiresAt);
    return !Number.isNaN(expiresAt.getTime()) && expiresAt.getTime() <= Date.now();
}

function paymentChannelLabel(channel) {
    const labels = {
        qris: 'QRIS',
        bca_va: 'BCA Virtual Account',
        bni_va: 'BNI Virtual Account',
        bri_va: 'BRI Virtual Account',
        permata_va: 'Permata Virtual Account',
        cimb_va: 'CIMB Virtual Account',
        mandiri_bill: 'Mandiri Bill Payment',
    };

    return labels[channel] || 'Midtrans';
}

function formatPaymentExpiry(value) {
    if (!value) return '';

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '';

    return parsed.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZoneName: 'short',
    });
}

function safeGatewayLink(value) {
    if (!value) return '';

    try {
        const url = new URL(value);
        return ['https:', 'gojek:'].includes(url.protocol) ? url.href : '';
    } catch {
        return '';
    }
}

export default function PaymentPage({ params }) {
    const { bookingCode } = use(params);
    const router = useRouter();
    const [booking, setBooking] = useState(null);
    const [loadingBooking, setLoadingBooking] = useState(true);
    const [startingPayment, setStartingPayment] = useState(false);
    const [refreshingStatus, setRefreshingStatus] = useState(false);
    const [paymentError, setPaymentError] = useState('');
    const [copiedField, setCopiedField] = useState('');
    const bookingRef = useRef(null);
    const initiatedBookingRef = useRef(null);
    const statusRequestRef = useRef(false);

    const persistBooking = useCallback((nextBooking) => {
        if (!nextBooking) return;

        bookingRef.current = nextBooking;
        setBooking(nextBooking);

        const bookings = getBookingsList();
        const hasExisting = bookings.some((item) => item.code === bookingCode);
        saveBookingsList(hasExisting
            ? bookings.map((item) => (item.code === bookingCode ? nextBooking : item))
            : [nextBooking, ...bookings]);
        window.dispatchEvent(new Event('salonku-activity-change'));
    }, [bookingCode]);

    const applyAuthoritativeBooking = useCallback((nextBooking) => {
        persistBooking(nextBooking);

        if (paymentIsPaid(nextBooking)) {
            router.replace(`/booking-success/${bookingCode}`);
        }
    }, [bookingCode, persistBooking, router]);

    const paymentToolbar = (
        <header className="booking-route-toolbar">
            <div className="booking-route-toolbar-inner">
                <button className="booking-route-toolbar-button" type="button" onClick={() => router.back()} aria-label="Back">
                    <ChevronLeft size={28} />
                </button>
                <button className="booking-route-toolbar-button" type="button" onClick={() => router.push('/activity')} aria-label="Tutup pembayaran">
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
                bookingRef.current = found;
            }

            try {
                const backendBooking = await getCustomerBookingByCode(bookingCode, found || {});
                if (!cancelled) {
                    persistBooking(backendBooking);
                    setPaymentError('');
                }
            } catch (error) {
                if (!cancelled) {
                    if (!found) setBooking(null);
                    setPaymentError(error?.message || 'Data pembayaran terbaru belum bisa dimuat.');
                }
            } finally {
                if (!cancelled) setLoadingBooking(false);
            }
        }

        loadBooking();

        return () => {
            cancelled = true;
        };
    }, [bookingCode, persistBooking]);

    useEffect(() => {
        bookingRef.current = booking;
    }, [booking]);

    const startGatewayPayment = useCallback(async ({ automatic = false } = {}) => {
        const currentBooking = bookingRef.current;
        if (!currentBooking?.id || startingPayment || paymentIsPaid(currentBooking) || paymentIsExpired(currentBooking)) return;
        if (currentBooking.paymentType === 'pay_at_salon') return;

        setStartingPayment(true);
        if (!automatic) setPaymentError('');

        try {
            const chargedBooking = await chargeCustomerBookingPayment(
                currentBooking.id,
                currentBooking,
                currentBooking.paymentChannel || ''
            );
            setPaymentError('');
            applyAuthoritativeBooking(chargedBooking);
        } catch (error) {
            setPaymentError(error?.message || 'Instruksi pembayaran belum berhasil dibuat.');
        } finally {
            setStartingPayment(false);
        }
    }, [applyAuthoritativeBooking, startingPayment]);

    const checkGatewayStatus = useCallback(async ({ silent = false } = {}) => {
        const currentBooking = bookingRef.current;
        if (!currentBooking?.id || statusRequestRef.current || paymentIsPaid(currentBooking)) return;

        statusRequestRef.current = true;
        if (!silent) {
            setRefreshingStatus(true);
            setPaymentError('');
        }

        try {
            const refreshedBooking = await refreshCustomerBookingPaymentStatus(currentBooking.id, currentBooking);
            setPaymentError('');
            applyAuthoritativeBooking(refreshedBooking);
        } catch (error) {
            if (!silent) {
                setPaymentError(error?.message || 'Status pembayaran belum bisa diperiksa.');
            }
        } finally {
            statusRequestRef.current = false;
            if (!silent) setRefreshingStatus(false);
        }
    }, [applyAuthoritativeBooking]);

    useEffect(() => {
        if (loadingBooking || !booking?.id || paymentIsPaid(booking) || paymentIsExpired(booking)) return;
        if (booking.paymentType === 'pay_at_salon') return;

        const hasGatewayTransaction = Boolean(
            booking.paymentOrderId
            || booking.paymentTransactionId
            || booking.paymentQrUrl
            || booking.paymentCode
            || booking.paymentProviderStatus
        );
        if (hasGatewayTransaction || initiatedBookingRef.current === booking.id) return;

        initiatedBookingRef.current = booking.id;
        startGatewayPayment({ automatic: true });
    }, [booking, loadingBooking, startGatewayPayment]);

    useEffect(() => {
        if (!booking?.id || paymentIsPaid(booking) || paymentIsExpired(booking)) return undefined;

        const hasGatewayTransaction = Boolean(booking.paymentOrderId || booking.paymentTransactionId);
        if (!hasGatewayTransaction) return undefined;

        const interval = window.setInterval(() => checkGatewayStatus({ silent: true }), 15000);
        const handleVisibility = () => {
            if (document.visibilityState === 'visible') checkGatewayStatus({ silent: true });
        };
        document.addEventListener('visibilitychange', handleVisibility);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', handleVisibility);
        };
    }, [booking?.id, booking?.paymentOrderId, booking?.paymentStatus, booking?.paymentTransactionId, checkGatewayStatus]);

    useEffect(() => {
        if (!loadingBooking && paymentIsPaid(booking)) {
            router.replace(`/booking-success/${bookingCode}`);
        }
    }, [booking, bookingCode, loadingBooking, router]);

    if (loadingBooking) {
        return (
            <div className="fresh-landing payment-route-shell">
                {paymentToolbar}
                <main className="booking-container payment-empty-state">
                    <ReceiptText size={48} />
                    <h3>Memuat pembayaran</h3>
                    <p>Kami sedang mengambil data booking {bookingCode}.</p>
                </main>
            </div>
        );
    }

    if (!booking) {
        return (
            <div className="fresh-landing payment-route-shell">
                {paymentToolbar}
                <main className="booking-container payment-empty-state">
                    <ReceiptText size={48} />
                    <h3>Transaksi tidak ditemukan</h3>
                    <p>Kode booking {bookingCode} tidak valid.</p>
                    <button className="booking-action-btn payment-empty-action" onClick={() => router.push('/')}>
                        Back to home
                    </button>
                </main>
            </div>
        );
    }

    const handleConfirmPayment = async () => {
        if (!ALLOW_MANUAL_PAYMENT_CONFIRMATION || startingPayment || isPaymentExpired) return;

        setStartingPayment(true);
        setPaymentError('');

        try {
            const paidBooking = await confirmCustomerPaymentByCode(bookingCode, booking);
            applyAuthoritativeBooking(paidBooking);

            if (!paymentIsPaid(paidBooking)) {
                setPaymentError('Backend belum menyatakan pembayaran ini berhasil. Silakan cek kembali statusnya.');
            }
        } catch (error) {
            setPaymentError(error?.message || 'Payment could not be confirmed. Please try again.');
        } finally {
            setStartingPayment(false);
        }
    };

    const handleCopy = async (label, value) => {
        if (!value) return;

        try {
            await navigator.clipboard.writeText(String(value));
            setCopiedField(label);
            window.setTimeout(() => setCopiedField(''), 1800);
        } catch {
            setPaymentError('Kode belum berhasil disalin. Silakan salin secara manual.');
        }
    };

    const services = booking.services || [];
    const addons = booking.addons || [];
    const participantCount = Math.max(1, Number(booking.participantCount || 1));
    const participantSelections = Array.isArray(booking.participantSelections)
        ? booking.participantSelections
        : [];
    const hasParticipantOrderDetails = participantSelections.length > 0
        && participantSelections.every((participant) => (participant.services || []).length > 0);
    const sharedServicesSubtotal = services.reduce(
        (sum, service) => sum + Number(service.discountPrice || service.price || 0),
        0
    );
    const sharedAddonsSubtotal = addons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
    const participantOrderSubtotal = participantSelections.reduce((sum, participant) => (
        sum + Number(
            participant.subtotal
            || (participant.services || []).reduce(
                (serviceSum, service) => serviceSum + Number(service.discountPrice || service.price || 0),
                0
            )
        )
    ), 0);
    const orderSubtotal = hasParticipantOrderDetails
        ? participantOrderSubtotal + sharedAddonsSubtotal
        : (sharedServicesSubtotal + sharedAddonsSubtotal) * participantCount;
    const discountAmount = Number(booking.discount || 0);
    const totalAmount = Number(booking.total || 0);
    const feeAmount = Math.max(0, totalAmount - Math.max(0, orderSubtotal - discountAmount));
    const isPaymentExpired = paymentIsExpired(booking);
    const isPayAtSalon = booking.paymentType === 'pay_at_salon';
    const gatewayQrUrl = safeGatewayLink(booking.paymentQrUrl);
    const gatewayDeeplinkUrl = safeGatewayLink(booking.paymentDeeplinkUrl);
    const channelLabel = paymentChannelLabel(booking.paymentChannel);
    const expiryLabel = formatPaymentExpiry(booking.paymentExpiresAt);
    const providerStatus = normalizedPaymentStatus(booking.paymentProviderStatus || booking.paymentStatus || 'pending');
    const hasGatewayTransaction = Boolean(
        booking.paymentOrderId
        || booking.paymentTransactionId
        || gatewayQrUrl
        || booking.paymentCode
        || booking.paymentProviderStatus
    );

    return (
        <div className="fresh-landing payment-route-shell">
            {paymentToolbar}
            <main className="booking-container payment-page">
                <div className="payment-grid">
                    <section className="payment-main">
                        <header className="payment-header">
                            <span>Payment</span>
                            <h1>Confirm payment</h1>
                            <p>Kode booking <strong>{bookingCode}</strong></p>
                        </header>

                        <section className="payment-amount-card">
                            <span>Total pembayaran</span>
                            <strong>{formatPaymentPrice(booking.total)}</strong>
                            <small>{isPayAtSalon ? 'Bayar langsung di salon' : `${channelLabel} via Midtrans`}</small>
                        </section>

                        <section className="payment-method-card gateway-payment-card">
                            <div className="payment-section-heading">
                                <span>{isPayAtSalon ? 'Pembayaran di lokasi' : 'Pembayaran aman'}</span>
                                <em className={`payment-status-chip ${isPaymentExpired ? 'expired' : providerStatus}`}>
                                    {isPayAtSalon
                                        ? 'Bayar di salon'
                                        : (isPaymentExpired ? 'Kedaluwarsa' : (providerStatus === 'paid' ? 'Berhasil' : 'Menunggu pembayaran'))}
                                </em>
                            </div>
                            <p>
                                {isPayAtSalon
                                    ? 'Booking ini menggunakan metode bayar di salon. Tidak ada transaksi gateway yang perlu dibuat atau dikonfirmasi dari halaman ini.'
                                    : isPaymentExpired
                                    ? 'The payment window for this booking has expired. Please make a new reservation to choose another time.'
                                    : `Selesaikan pembayaran melalui ${channelLabel}. Status hanya akan dinyatakan berhasil setelah diverifikasi langsung oleh backend ke Midtrans.`}
                            </p>

                            {paymentError && (
                                <div className="payment-error-banner" role="alert">
                                    <strong>Pembayaran belum dapat dilanjutkan</strong>
                                    <span>{paymentError}</span>
                                </div>
                            )}

                            {isPayAtSalon && (
                                <div className="payment-gateway-placeholder payment-at-salon-panel">
                                    <ReceiptText size={25} />
                                    <div>
                                        <strong>Tunjukkan kode booking {bookingCode}</strong>
                                        <span>Selesaikan pembayaran langsung kepada pihak salon saat kunjungan.</span>
                                    </div>
                                </div>
                            )}

                            {!isPayAtSalon && startingPayment && !hasGatewayTransaction && (
                                <div className="payment-gateway-loading" aria-live="polite">
                                    <LoaderCircle className="payment-spin" size={28} />
                                    <div>
                                        <strong>Membuat instruksi pembayaran</strong>
                                        <span>Mohon tunggu, transaksi dibuat secara aman melalui Midtrans.</span>
                                    </div>
                                </div>
                            )}

                            {gatewayQrUrl && (
                                <div className="payment-qris-panel payment-qris-live">
                                    <img src={gatewayQrUrl} alt="Kode QRIS untuk pembayaran booking" />
                                    <strong>SCAN QRIS</strong>
                                    <span>Buka aplikasi pembayaran lalu pindai kode di atas.</span>
                                </div>
                            )}

                            {booking.paymentCode && (
                                <div className="payment-va-card">
                                    <div className="payment-va-bank">
                                        <div>
                                            <span>Metode pembayaran</span>
                                            <strong>{booking.paymentCodeLabel || channelLabel}</strong>
                                        </div>
                                        <ShieldCheck size={22} />
                                    </div>
                                    {booking.paymentBillerCode && (
                                        <div className="payment-va-number">
                                            <div>
                                                <span>Biller code</span>
                                                <strong>{booking.paymentBillerCode}</strong>
                                            </div>
                                            <button type="button" onClick={() => handleCopy('biller', booking.paymentBillerCode)}>
                                                <Copy size={14} />
                                                {copiedField === 'biller' ? 'Tersalin' : 'Salin'}
                                            </button>
                                        </div>
                                    )}
                                    <div className="payment-va-number">
                                        <div>
                                            <span>{booking.paymentCodeLabel || 'Kode pembayaran'}</span>
                                            <strong>{booking.paymentCode}</strong>
                                        </div>
                                        <button type="button" onClick={() => handleCopy('payment', booking.paymentCode)}>
                                            <Copy size={14} />
                                            {copiedField === 'payment' ? 'Tersalin' : 'Salin'}
                                        </button>
                                    </div>
                                    <small>Pastikan nominal dan penerima sesuai sebelum menyelesaikan pembayaran.</small>
                                </div>
                            )}

                            {gatewayDeeplinkUrl && !isPaymentExpired && (
                                <a className="payment-deeplink" href={gatewayDeeplinkUrl} target="_blank" rel="noreferrer">
                                    Buka aplikasi pembayaran
                                    <ExternalLink size={15} />
                                </a>
                            )}

                            {!isPayAtSalon && !hasGatewayTransaction && !startingPayment && !isPaymentExpired && (
                                <div className="payment-gateway-placeholder">
                                    <ShieldCheck size={25} />
                                    <div>
                                        <strong>Instruksi pembayaran belum tersedia</strong>
                                        <span>Coba buat ulang transaksi tanpa mengubah booking Anda.</span>
                                    </div>
                                </div>
                            )}

                            {!isPayAtSalon && hasGatewayTransaction && (
                                <div className="payment-gateway-meta">
                                    <div>
                                        <span>Status gateway</span>
                                        <strong>{booking.paymentProviderStatus || booking.paymentStatus || 'pending'}</strong>
                                    </div>
                                    {expiryLabel && (
                                        <div>
                                            <span>Batas pembayaran</span>
                                            <strong>{expiryLabel}</strong>
                                        </div>
                                    )}
                                </div>
                            )}
                        </section>

                        <div className="payment-actions">
                            {!isPayAtSalon && !hasGatewayTransaction && !isPaymentExpired && (
                                <button
                                    className="booking-action-btn"
                                    type="button"
                                    onClick={() => startGatewayPayment()}
                                    disabled={startingPayment}
                                >
                                    {startingPayment ? <LoaderCircle className="payment-spin" size={16} /> : <ShieldCheck size={16} />}
                                    {startingPayment ? 'Menyiapkan pembayaran' : 'Buat instruksi pembayaran'}
                                </button>
                            )}
                            {!isPayAtSalon && hasGatewayTransaction && !isPaymentExpired && (
                                <button
                                    className="booking-action-btn"
                                    type="button"
                                    onClick={() => checkGatewayStatus()}
                                    disabled={refreshingStatus}
                                >
                                    <RefreshCw className={refreshingStatus ? 'payment-spin' : ''} size={16} />
                                    {refreshingStatus ? 'Memverifikasi ke Midtrans' : 'Saya sudah bayar, cek status'}
                                </button>
                            )}
                            {ALLOW_MANUAL_PAYMENT_CONFIRMATION && !isPayAtSalon && !isPaymentExpired && (
                                <button
                                    className="payment-secondary-btn payment-manual-confirm"
                                    type="button"
                                    onClick={handleConfirmPayment}
                                    disabled={startingPayment}
                                >
                                    Konfirmasi manual (local/demo)
                                    <ChevronRight size={16} />
                                </button>
                            )}
                            <button type="button" className="payment-secondary-btn" onClick={() => router.push('/activity')}>
                                Bayar nanti
                            </button>
                        </div>
                    </section>

                    <aside className="payment-summary-card">
                        <div className="payment-summary-heading">
                            <span className="payment-summary-heading-icon"><ReceiptText size={18} /></span>
                            <div>
                                <strong>Order details</strong>
                                <small>{bookingCode} · {participantCount} peserta</small>
                            </div>
                        </div>

                        <div className="payment-summary-venue">
                            {booking.salonImage ? (
                                <img src={booking.salonImage} alt={booking.salonName} />
                            ) : (
                                <div className="payment-summary-placeholder" aria-hidden="true">
                                    {String(booking.salonName || 'S').charAt(0)}
                                </div>
                            )}
                            <div>
                                <span>Salon</span>
                                <strong>{booking.salonName}</strong>
                                <small>{booking.salonAddress}</small>
                            </div>
                        </div>

                        <div className="payment-summary-meta">
                            <div>
                                <Calendar size={14} />
                                <span>{formatPaymentDate(booking.date)}, {booking.time} WIB</span>
                            </div>
                            <div>
                                <Clock size={14} />
                                <span>{formatPaymentDuration(booking.duration)}</span>
                            </div>
                        </div>

                        <div className="payment-summary-list">
                            {hasParticipantOrderDetails ? participantSelections.map((participant, participantIndex) => (
                                <section className="payment-participant-order" key={participant.position || participantIndex}>
                                    <div className="payment-participant-order-heading">
                                        <span>{participantIndex + 1}</span>
                                        <div>
                                            <b>{participant.name || `Participant ${participantIndex + 1}`}</b>
                                            <small>
                                                {participant.staff === 'any' ? 'Siapa Saja' : (participant.staff?.name || 'Professional')}
                                                {' · '}{formatPaymentDate(participant.date)}, {participant.time} WIB
                                            </small>
                                        </div>
                                    </div>
                                    {(participant.services || []).map((service) => (
                                        <div className="payment-summary-line participant-service" key={`${participantIndex}-${service.id}`}>
                                            <div>
                                                <b>{service.name}</b>
                                                <small>{formatPaymentDuration(service.duration)}</small>
                                            </div>
                                            <strong>{formatPaymentPrice(service.discountPrice || service.price)}</strong>
                                        </div>
                                    ))}
                                </section>
                            )) : services.map((service) => (
                                <div className="payment-summary-line" key={service.id}>
                                    <div>
                                        <b>{service.name}</b>
                                        <small>
                                            {formatPaymentDuration(service.duration)}
                                            {participantCount > 1 ? ` × ${participantCount} peserta` : ''}
                                        </small>
                                    </div>
                                    <strong>{formatPaymentPrice(Number(service.discountPrice || service.price) * participantCount)}</strong>
                                </div>
                            ))}
                            {addons.map((addon) => (
                                <div className="payment-summary-line" key={addon.id}>
                                    <div>
                                        <b>{addon.name}</b>
                                        <small>
                                            Tambahan, +{formatPaymentDuration(addon.duration)}
                                            {!hasParticipantOrderDetails && participantCount > 1 ? ` × ${participantCount} peserta` : ''}
                                        </small>
                                    </div>
                                    <strong>+ {formatPaymentPrice(Number(addon.price) * (hasParticipantOrderDetails ? 1 : participantCount))}</strong>
                                </div>
                            ))}
                        </div>

                        <div className="payment-summary-total">
                            <p><span>Subtotal layanan</span><b>{formatPaymentPrice(orderSubtotal)}</b></p>
                            {discountAmount > 0 && (
                                <p className="discount"><span>Promo</span><b>- {formatPaymentPrice(discountAmount)}</b></p>
                            )}
                            {feeAmount > 0 && (
                                <p><span>Pajak &amp; biaya</span><b>{formatPaymentPrice(feeAmount)}</b></p>
                            )}
                            <p className="grand"><span>Total</span><b>{formatPaymentPrice(totalAmount)}</b></p>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    );
}
