import Link from 'next/link';
import { ArrowRight, BookOpen, CalendarCheck, CircleHelp, CreditCard, Search, ShieldCheck } from 'lucide-react';
import { FreshNavigation, Footer } from '../../src/components/LandingPage.jsx';
import { PROVIDER_FRONTEND_URL } from '../../src/lib/app-urls.js';

const guides = [
    {
        icon: Search,
        title: 'Menemukan salon yang tepat',
        copy: 'Gunakan kategori, lokasi, dan waktu untuk membandingkan salon serta layanan yang tersedia.',
        href: '/search',
        linkLabel: 'Cari salon',
    },
    {
        icon: CalendarCheck,
        title: 'Mengelola reservasi',
        copy: 'Lihat jadwal mendatang, status pembayaran, dan riwayat perawatan dalam satu halaman.',
        href: '/activity',
        linkLabel: 'Buka aktivitas',
    },
    {
        icon: CreditCard,
        title: 'Promo dan pembayaran',
        copy: 'Pelajari promo aktif lalu terapkan kodenya ketika mengonfirmasi reservasi.',
        href: '/promos',
        linkLabel: 'Lihat promo',
    },
];

const faqs = [
    ['Bagaimana cara membuat reservasi?', 'Pilih salon, layanan, profesional, serta waktu yang tersedia. Periksa ringkasan sebelum mengonfirmasi pembayaran.'],
    ['Apakah jadwal yang tampil masih tersedia?', 'Ketersediaan diperiksa kembali oleh sistem saat kamu memilih waktu dan ketika reservasi dikonfirmasi.'],
    ['Di mana saya melihat reservasi saya?', 'Masuk ke akun customer lalu buka halaman Aktivitas untuk melihat reservasi mendatang dan riwayatnya.'],
    ['Bagaimana memakai voucher?', 'Salin kode dari halaman Promo, lalu masukkan pada bagian Voucher di langkah konfirmasi booking.'],
];

export default function ArticlesPage() {
    return (
        <div className="page-shell articles-page">
            <FreshNavigation providerUrl={PROVIDER_FRONTEND_URL} />
            <main className="articles-main">
                <section className="articles-hero">
                    <span className="articles-eyebrow"><BookOpen size={17} /> Pusat bantuan YouYaku</span>
                    <h1>Panduan untuk booking yang lebih mudah</h1>
                    <p>Temukan jawaban singkat tentang pencarian salon, reservasi, promo, dan keamanan akun.</p>
                    <div className="articles-hero-actions">
                        <Link href="/search" className="articles-primary-link">Mulai cari salon <ArrowRight size={17} /></Link>
                        <Link href="/auth" className="articles-secondary-link">Masuk ke akun</Link>
                    </div>
                </section>

                <section className="articles-section" aria-labelledby="guide-title">
                    <div className="articles-section-heading">
                        <div>
                            <span className="articles-kicker">Panduan utama</span>
                            <h2 id="guide-title">Apa yang ingin kamu lakukan?</h2>
                        </div>
                        <ShieldCheck aria-hidden="true" />
                    </div>
                    <div className="articles-guide-grid">
                        {guides.map(({ icon: Icon, title, copy, href, linkLabel }) => (
                            <article className="articles-guide-card" key={title}>
                                <span className="articles-guide-icon"><Icon size={22} /></span>
                                <h3>{title}</h3>
                                <p>{copy}</p>
                                <Link href={href}>{linkLabel} <ArrowRight size={15} /></Link>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="articles-section articles-faq" aria-labelledby="faq-title">
                    <div className="articles-section-heading">
                        <div>
                            <span className="articles-kicker">Pertanyaan populer</span>
                            <h2 id="faq-title">Jawaban cepat</h2>
                        </div>
                        <CircleHelp aria-hidden="true" />
                    </div>
                    <div className="articles-faq-list">
                        {faqs.map(([question, answer]) => (
                            <details key={question}>
                                <summary>{question}</summary>
                                <p>{answer}</p>
                            </details>
                        ))}
                    </div>
                </section>
            </main>
            <Footer />
        </div>
    );
}
