'use client';

import { useEffect, useState } from 'react';
import { FreshNavigation, Footer } from '../../src/components/LandingPage.jsx';
import { getPublicCoupons } from '../../src/lib/auth-api.js';
import { PROVIDER_FRONTEND_URL } from '../../src/lib/app-urls.js';
import { Ticket, Copy, Check, Calendar, AlertCircle } from 'lucide-react';

function formatCouponValue(promo) {
    if (promo.coupon_type === 'percentage') return `${Number(promo.coupon_value || 0)}% off`;

    return `Hemat IDR ${Number(promo.coupon_value || 0).toLocaleString('id-ID')}`;
}

function formatPromoDate(value) {
    if (!value) return '-';

    const date = new Date(`${value}T00:00:00Z`);
    if (Number.isNaN(date.getTime())) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

function formatPromoQuantity(value) {
    return value === null || value === undefined
        ? 'Tidak terbatas'
        : Number(value || 0).toLocaleString('id-ID');
}

export default function PromosPage() {
    const [copiedCode, setCopiedCode] = useState('');
    const [promos, setPromos] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        let active = true;

        getPublicCoupons()
            .then((items) => {
                if (active) setPromos(items);
            })
            .catch((loadError) => {
                if (active) setError(loadError.message || 'Promo aktif belum dapat dimuat.');
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, []);

    const handleCopyCode = async (code) => {
        try {
            await navigator.clipboard.writeText(code);
            setCopiedCode(code);
            setTimeout(() => setCopiedCode(''), 2000);
        } catch {
            setError('Kode belum berhasil disalin. Silakan pilih dan salin kode secara manual.');
        }
    };

    return (
        <div className="page-shell">
            <FreshNavigation providerUrl={PROVIDER_FRONTEND_URL} />
            <main className="booking-container">
                <h1 className="promos-title">Promo & Voucher Spesial</h1>
                <p className="promos-subtitle">
                    Salin kode promo kecantikan di bawah untuk ditukarkan dengan potongan harga spesial saat melakukan reservasi.
                </p>

                {error && <div className="promos-status error" role="alert">{error}</div>}
                {loading && (
                    <div className="promos-status" role="status">
                        <span className="booking-loading-indicator" aria-hidden="true"><span /><span /><span /></span>
                        Memuat promo aktif...
                    </div>
                )}

                <div className="promos-grid">
                    {!loading && promos.map(promo => {
                        const isCopied = copiedCode === promo.code;
                        return (
                            <article key={promo.id || promo.code} className="promo-card">
                                <div className="promo-card-banner">
                                    <div className="promo-card-icon">
                                        <Ticket size={28} />
                                    </div>
                                    <div>
                                        <span className="promo-card-type">
                                            {formatCouponValue(promo)}
                                        </span>
                                        <h2 className="promo-card-name">{promo.code}</h2>
                                    </div>
                                </div>

                                <div className="promo-card-body">
                                    <div>
                                        <p className="promo-card-desc">
                                            Berlaku untuk {promo.product_label || 'layanan yang dipilih'}.
                                        </p>
                                        
                                        <div className="promo-card-meta">
                                            <div className="promo-card-meta-row">
                                                <AlertCircle size={14} />
                                                <span>Sisa kuota: <strong>{formatPromoQuantity(promo.remaining_quantity)}</strong></span>
                                            </div>
                                            <div className="promo-card-meta-row">
                                                <Calendar size={14} />
                                                <span>Berlaku hingga: <strong>{formatPromoDate(promo.end_date)}</strong></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="promo-action-row">
                                        <div className="promo-code-copy">
                                            <span className="promo-code-label">Kode Promo</span>
                                            <strong className="promo-code-value">{promo.code}</strong>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleCopyCode(promo.code)}
                                            className={`promo-copy-btn ${isCopied ? 'copied' : ''}`}
                                        >
                                            {isCopied ? <Check size={14} /> : <Copy size={14} />}
                                            {isCopied ? 'Tersalin' : 'Salin Kode'}
                                        </button>
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>

                {!loading && !error && promos.length === 0 && (
                    <div className="promos-status empty">Belum ada promo aktif saat ini.</div>
                )}
            </main>
            <Footer />
        </div>
    );
}
