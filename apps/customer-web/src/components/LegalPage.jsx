'use client';

import Link from 'next/link';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { FreshNavigation, Footer } from './LandingPage.jsx';
import { PROVIDER_FRONTEND_URL } from '../lib/app-urls.js';

export function LegalPage({ eyebrow, title, updatedAt, sections }) {
    return (
        <div className="page-shell legal-page">
            <FreshNavigation providerUrl={PROVIDER_FRONTEND_URL} customerAppUrl="/" />
            <main className="legal-main">
                <Link className="legal-back" href="/">
                    <ArrowLeft size={16} />
                    Kembali ke beranda
                </Link>
                <header className="legal-header">
                    <span><ShieldCheck size={17} /> {eyebrow}</span>
                    <h1>{title}</h1>
                    <p>Terakhir diperbarui: {updatedAt}</p>
                </header>
                <div className="legal-content">
                    {sections.map((section) => (
                        <section key={section.title}>
                            <h2>{section.title}</h2>
                            {section.paragraphs.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
                        </section>
                    ))}
                </div>
            </main>
            <Footer />
        </div>
    );
}
