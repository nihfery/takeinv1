'use client';

import { useEffect, useState } from 'react';
import { MapPin, Share2, Star } from 'lucide-react';
import { FreshNavigation, Footer } from './LandingPage.jsx';
import { BranchLocationMap } from './BranchLocationMap.jsx';
import { getBookingPath, getSalonRouteSlug } from '../lib/salon-routes.js';
import { saveBookingDraft } from '../lib/mock-state.js';

function formatPrice(value) {
    return `IDR ${Number(value || 0).toLocaleString('en-US')}`;
}

function staffName(staff) {
    return staff?.name || staff?.full_name || [staff?.first_name, staff?.last_name].filter(Boolean).join(' ') || 'Professional';
}

function normalizedServices(staff, services) {
    const byId = new Map(services.map((service) => [String(service.id), service]));

    return (staff?.skills || []).map((skill) => {
        const service = byId.get(String(skill.id)) || skill;
        return {
            id: service.id || skill.id,
            name: service.name || service.title || skill.name || skill.title || 'Service',
            duration: Number(service.duration || service.estimated_duration || skill.estimated_duration || skill.duration || 0),
            price: Number(service.price || skill.price || 0),
            category: service.category || skill.category || '',
            desc: service.description || service.desc || '',
        };
    });
}

function Rating({ value, size = 15 }) {
    const rating = Number(value || 0);
    if (rating <= 0) return <span className="staff-profile-rating-empty">No ratings yet</span>;

    return <span className="staff-profile-rating"><b>{rating.toFixed(1)}</b><Star size={size} fill="currentColor" strokeWidth={0} /></span>;
}

export function StaffProfileView({ branch, staff, services = [], providerUrl, customerAppUrl }) {
    const [activeSection, setActiveSection] = useState('services');
    const name = staffName(staff);
    const photo = staff?.image_url || staff?.photo || staff?.image || '';
    const staffServices = normalizedServices(staff, services);
    const reviews = Array.isArray(staff?.reviews) ? staff.reviews : [];
    const completedBookings = Number(staff?.completed_bookings_count || staff?.completedBookings || 0);
    const clientsServed = Number(staff?.clients_served_count || staff?.clientsServed || 0);
    const address = branch?.address || [branch?.city, branch?.state, 'Indonesia'].filter(Boolean).join(', ');

    useEffect(() => {
        const sectionIds = ['services', 'portfolio', 'reviews', 'location'];
        const syncActiveSection = () => {
            const scrollLine = window.scrollY + 170;
            const current = sectionIds.reduce((active, id) => {
                const section = document.getElementById(id);
                return section && section.offsetTop <= scrollLine ? id : active;
            }, 'services');
            setActiveSection(current);
        };

        syncActiveSection();
        window.addEventListener('scroll', syncActiveSection, { passive: true });
        window.addEventListener('resize', syncActiveSection);
        return () => {
            window.removeEventListener('scroll', syncActiveSection);
            window.removeEventListener('resize', syncActiveSection);
        };
    }, []);

    function openBooking(selectedService = null) {
        const bookingPath = getBookingPath(branch);
        const draftServices = selectedService ? [selectedService] : [];
        saveBookingDraft({
            salonId: String(branch.id),
            salonSlug: getSalonRouteSlug(branch),
            salonName: branch.name,
            salonImage: branch.image,
            salonAddress: address,
            salonRating: Number(branch.rating || 0),
            salonReviews: Number(branch.reviews || 0),
            availableServices: services,
            services: draftServices,
            addons: [],
            staff: {
                id: staff.id,
                name,
                role: staff.role || 'Professional',
                photo,
                rating: Number(staff.rating || 0),
                reviews: Number(staff.review_count || reviews.length || 0),
                skills: staff.skills || [],
            },
            date: '',
            time: '',
            currentStep: draftServices.length ? 1 : 2,
        });

        const bookingTab = window.open(bookingPath, '_blank');
        if (bookingTab) bookingTab.opener = null;
    }

    function shareProfile() {
        navigator.clipboard?.writeText(window.location.href);
    }

    return (
        <div className="fresh-landing staff-profile-page">
            <FreshNavigation providerUrl={providerUrl} customerAppUrl={customerAppUrl} />
            <main className="staff-profile-shell">
                <aside className="staff-profile-sidebar">
                    <button className="staff-profile-share" type="button" onClick={shareProfile} aria-label="Bagikan profil"><Share2 size={17} /></button>
                    {photo ? <img className="staff-profile-avatar" src={photo} alt={name} /> : <span className="staff-profile-avatar staff-profile-avatar-fallback">{name.slice(0, 1)}</span>}
                    <h1>{name}</h1>
                    <p className="staff-profile-role">{staff?.role && String(staff.role).toLowerCase() !== 'staff' ? staff.role : 'Professional'}</p>
                    <p className="staff-profile-salon">Professional di {branch?.name}</p>
                    <button className="staff-profile-book-button" type="button" onClick={() => openBooking()}>Book now</button>
                    <dl className="staff-profile-stats">
                        <div><dt>Appointments completed</dt><dd>{completedBookings.toLocaleString('en-US')}</dd></div>
                        <div><dt>Clients served</dt><dd>{clientsServed.toLocaleString('en-US')}</dd></div>
                    </dl>
                    <div className="staff-profile-workplace">
                        <h2>Bekerja di</h2>
                        <b>{branch?.name}</b>
                        <p>{address}</p>
                    </div>
                    {(staff?.bio || staff?.skills?.length) && <div className="staff-profile-expertise">
                        <h2>Keahlian</h2>
                        <p>{staff.bio || staffServices.map((service) => service.name).slice(0, 3).join(', ')}</p>
                    </div>}
                </aside>

                <section className="staff-profile-content">
                    <nav className="staff-profile-nav" aria-label="Navigasi profil">
                        <a className={activeSection === 'services' ? 'is-active' : ''} href="#services" onClick={() => setActiveSection('services')}>Services <span>{staffServices.length}</span></a>
                        <a className={activeSection === 'portfolio' ? 'is-active' : ''} href="#portfolio" onClick={() => setActiveSection('portfolio')}>Portofolio</a>
                        <a className={activeSection === 'reviews' ? 'is-active' : ''} href="#reviews" onClick={() => setActiveSection('reviews')}>Reviews <span>{Number(staff?.review_count || reviews.length || 0)}</span></a>
                        <a className={activeSection === 'location' ? 'is-active' : ''} href="#location" onClick={() => setActiveSection('location')}>Location</a>
                    </nav>

                    <section id="services" className="staff-profile-section">
                        <h2>Services</h2>
                        <div className="staff-profile-service-list">
                            {staffServices.length ? staffServices.map((service) => (
                                <article key={service.id}>
                                    <div>
                                        <h3>{service.name}</h3>
                                        {service.duration > 0 && <p>{service.duration} mnt</p>}
                                        <b>{formatPrice(service.price)}</b>
                                    </div>
                                    <button type="button" onClick={() => openBooking(service)}>Book</button>
                                </article>
                            )) : <p className="staff-profile-empty">Professional ini belum memiliki layanan aktif.</p>}
                        </div>
                    </section>

                    <section id="portfolio" className="staff-profile-section">
                        <h2>Portofolio</h2>
                        <p className="staff-profile-empty">Professional ini belum memiliki portofolio.</p>
                    </section>

                    <section id="reviews" className="staff-profile-section">
                        <h2>Reviews</h2>
                        {reviews.length ? <div className="staff-profile-review-list">{reviews.map((review) => (
                            <article key={review.id}>
                                <span>{String(review.customer_name || 'C').slice(0, 1).toUpperCase()}</span>
                                <div><b>{review.customer_name || 'Customer terverifikasi'}</b><Rating value={review.rating} size={12} />{review.comment && <p>{review.comment}</p>}</div>
                            </article>
                        ))}</div> : <p className="staff-profile-empty">Professional ini belum memiliki ulasan.</p>}
                    </section>

                    <section id="location" className="staff-profile-section">
                        <h2>Location</h2>
                        <BranchLocationMap branch={branch} />
                        <b className="staff-profile-location-name">{branch?.name}</b>
                        <p className="staff-profile-location-address"><MapPin size={14} /> {address}</p>
                    </section>
                </section>
            </main>
            <Footer providerUrl={providerUrl} customerAppUrl={customerAppUrl} />
        </div>
    );
}
