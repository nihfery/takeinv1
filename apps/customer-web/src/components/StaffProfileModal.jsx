'use client';

import { useEffect, useRef, useState } from 'react';
import { Share2, Star, X } from 'lucide-react';

function formatPrice(value) {
    return `IDR ${Number(value || 0).toLocaleString('en-US')}`;
}

function Stars({ rating = 0 }) {
    return (
        <span className="staff-profile-modal-stars" aria-label={`Rating ${Number(rating || 0).toFixed(1)} dari 5`}>
            <Star size={13} fill="currentColor" strokeWidth={0} />
            {Number(rating || 0).toFixed(1)}
        </span>
    );
}

export function StaffProfileModal({ staff, onClose, onBook }) {
    const [activeTab, setActiveTab] = useState('profile');
    const contentRef = useRef(null);
    const tabNavigationRef = useRef(null);

    useEffect(() => {
        const previousBodyOverflow = document.body.style.overflow;
        const previousDocumentOverflow = document.documentElement.style.overflow;

        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousBodyOverflow;
            document.documentElement.style.overflow = previousDocumentOverflow;
        };
    }, []);

    function scrollToSection(section) {
        const container = contentRef.current;
        if (!container) return;

        setActiveTab(section);
        tabNavigationRef.current = section;
        const target = document.getElementById(`staff-profile-modal-${section}-${staff.id}`);
        if (!target) return;

        const targetTop = container.scrollTop
            + target.getBoundingClientRect().top
            - container.getBoundingClientRect().top;
        container.scrollTo({ top: Math.max(0, targetTop - 8), behavior: 'smooth' });
    }

    function syncTabWithScroll() {
        const container = contentRef.current;
        if (!container) return;

        const triggerLine = container.getBoundingClientRect().top + 48;
        const sections = ['profile', 'services', 'reviews'];

        if (tabNavigationRef.current) {
            const target = document.getElementById(`staff-profile-modal-${tabNavigationRef.current}-${staff.id}`);
            const reachedTarget = target
                && Math.abs(target.getBoundingClientRect().top - container.getBoundingClientRect().top) <= 12;
            const reachedBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 2;
            if (!reachedTarget && !reachedBottom) return;
            tabNavigationRef.current = null;
        }

        const nextTab = sections.reduce((current, section) => {
            const element = document.getElementById(`staff-profile-modal-${section}-${staff.id}`);
            return element && element.getBoundingClientRect().top <= triggerLine ? section : current;
        }, 'profile');

        setActiveTab((current) => current === nextTab ? current : nextTab);
    }

    function shareProfile() {
        navigator.clipboard?.writeText(window.location.href);
    }

    return (
        <div className="salon-professional-modal" role="dialog" aria-modal="true" aria-labelledby="staff-profile-modal-title">
            <button className="salon-professional-modal-backdrop" type="button" aria-label="Tutup profil profesional" onClick={onClose} />
            <section className="salon-professional-modal-card">
                <header className="salon-professional-modal-hero">
                    <button className="salon-professional-modal-share" type="button" aria-label="Bagikan profil" onClick={shareProfile}>
                        <Share2 size={23} strokeWidth={2} />
                    </button>
                    <button className="salon-professional-modal-close" type="button" aria-label="Tutup" onClick={onClose}>
                        <X size={25} strokeWidth={2} />
                    </button>
                    {staff.photo ? (
                        <img src={staff.photo} alt={staff.name} />
                    ) : (
                        <span className="salon-professional-modal-avatar-fallback">{String(staff.name || 'P').slice(0, 1)}</span>
                    )}
                    <h3 id="staff-profile-modal-title">{staff.name}</h3>
                    <p>{staff.role}</p>
                    <div className="salon-professional-modal-tabs" role="tablist" aria-label="Informasi profesional">
                        {[
                            ['profile', 'Profil'],
                            ['services', 'Services'],
                            ['reviews', `Reviews${staff.reviews ? ` (${staff.reviews})` : ''}`],
                        ].map(([tab, label]) => (
                            <button key={tab} type="button" role="tab" aria-selected={activeTab === tab}
                                className={activeTab === tab ? 'active' : ''} onClick={() => scrollToSection(tab)}>
                                {label}
                            </button>
                        ))}
                    </div>
                </header>

                <div className="salon-professional-modal-content" ref={contentRef} onScroll={syncTabWithScroll}>
                    <section className="salon-professional-modal-panel" id={`staff-profile-modal-profile-${staff.id}`}>
                        {staff.bio && <p className="salon-professional-modal-bio">{staff.bio}</p>}
                        <div className="salon-professional-modal-stats">
                            <p><span>Appointments completed</span><b>{Number(staff.completedBookings || 0).toLocaleString('en-US')}</b></p>
                            <p><span>Clients served</span><b>{Number(staff.clientsServed || 0).toLocaleString('en-US')}</b></p>
                            {Number(staff.rating || 0) > 0 && <p><span>Rating pelanggan</span><b><Stars rating={staff.rating} /></b></p>}
                        </div>
                    </section>

                    <section className="salon-professional-modal-panel salon-professional-modal-services" id={`staff-profile-modal-services-${staff.id}`}>
                        <h4>Services</h4>
                        {(staff.skills || []).length ? staff.skills.map((skill) => (
                            <article key={skill.id || skill.title}>
                                <div>
                                    <b>{skill.title || skill.name || 'Service'}</b>
                                    <span>{Number(skill.estimated_duration || skill.duration || 0)} mnt</span>
                                    <strong>{formatPrice(skill.price)}</strong>
                                </div>
                            </article>
                        )) : <p className="salon-professional-modal-empty">No services have been added yet.</p>}
                    </section>

                    <section className="salon-professional-modal-panel salon-professional-modal-reviews" id={`staff-profile-modal-reviews-${staff.id}`}>
                        <h4>Reviews</h4>
                        {(staff.reviewItems || []).length ? staff.reviewItems.map((review) => (
                            <article key={review.id}>
                                <span>{String(review.customer_name || 'C').slice(0, 1).toUpperCase()}</span>
                                <div>
                                    <b>{review.customer_name || 'Verified customer'}</b>
                                    <Stars rating={review.rating} />
                                    {review.comment && <p>{review.comment}</p>}
                                </div>
                            </article>
                        )) : <p className="salon-professional-modal-empty">There are no reviews for this professional yet.</p>}
                    </section>
                </div>

                <footer className="salon-professional-modal-footer">
                    <button type="button" onClick={() => onBook(staff)}>Choose this professional</button>
                </footer>
            </section>
        </div>
    );
}
