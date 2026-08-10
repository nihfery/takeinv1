'use client';

import { useState } from 'react';
import { Header, Footer } from '../../src/components/LandingPage.jsx';
import { mockPromos } from '../../src/lib/mock-state.js';
import { Ticket, Copy, Check, Calendar, AlertCircle } from 'lucide-react';

export default function PromosPage() {
    const [copiedCode, setCopiedCode] = useState('');

    const handleCopyCode = (code) => {
        navigator.clipboard.writeText(code);
        setCopiedCode(code);
        setTimeout(() => setCopiedCode(''), 2000);
    };

    return (
        <div className="page-shell">
            <Header />
            <main className="booking-container">
                <h1 className="promos-title">Promo & Voucher Spesial</h1>
                <p className="promos-subtitle">
                    Salin kode promo kecantikan di bawah untuk ditukarkan dengan potongan harga spesial saat melakukan reservasi.
                </p>

                <div className="promos-grid">
                    {mockPromos.map(promo => {
                        const isCopied = copiedCode === promo.code;
                        return (
                            <div key={promo.code} className="promo-card">
                                {/* Banner top style */}
                                <div className="promo-card-banner">
                                    <div className="promo-card-icon">
                                        <Ticket size={28} />
                                    </div>
                                    <div>
                                        <span className="promo-card-type">
                                            {promo.type === 'percentage' ? `${promo.val}% off` : `Save IDR ${promo.val.toLocaleString('en-US')}`}
                                        </span>
                                        <h3 className="promo-card-name">{promo.title}</h3>
                                    </div>
                                </div>

                                {/* Details body */}
                                <div className="promo-card-body">
                                    <div>
                                        <p className="promo-card-desc">{promo.desc}</p>
                                        
                                        <div className="promo-card-meta">
                                            <div className="promo-card-meta-row">
                                                <AlertCircle size={14} />
                                                <span>Minimum spend: <strong>IDR {promo.minTx.toLocaleString('en-US')}</strong></span>
                                            </div>
                                            <div className="promo-card-meta-row">
                                                <Calendar size={14} />
                                                <span>Berlaku hingga: <strong>{promo.expiry}</strong></span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Action Row */}
                                    <div className="promo-action-row">
                                        <div style={{ flex: 1 }}>
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
                            </div>
                        );
                    })}
                </div>
            </main>
            <Footer />
        </div>
    );
}
