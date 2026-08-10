import { useRef } from 'react';
import { motion } from 'framer-motion';
import { Icon } from '../components/Icons.jsx';

const impacts = [
    {
        metric: '80%',
        title: 'Decrease in No-Shows',
        desc: 'Our automated reminder system and online deposit feature ensure customers arrive on time, saving you time.',
        color: 'linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%)', // Pinkish
        icon: 'calendar'
    },
    {
        metric: '300%',
        title: 'Surge in Bookings',
        desc: 'With the convenience of 24/7 booking through various channels, your business will always receive orders even while you sleep.',
        color: 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)', // Purple
        icon: 'chart'
    },
    {
        metric: '90%',
        title: 'Loyal Client Retention',
        desc: 'Increase loyalty with membership programs, discount packages, and a comprehensive track record of client preferences.',
        color: 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)', // Mint/Blue
        icon: 'heart'
    },
    {
        metric: '12%',
        title: 'Operational Efficiency',
        desc: 'Reduce admin costs and staff overtime with an automated system that takes over 90% of your repetitive tasks.',
        color: 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)', // Yellow/Orange
        icon: 'zap'
    },
    {
        metric: '50%',
        title: 'Profit Increase',
        desc: 'Smart schedule optimization eliminates idle time for your staff, maximizing daily revenue potential.',
        color: 'linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)', // Blue
        icon: 'chart'
    },
    {
        metric: '24/7',
        title: 'Nonstop Service',
        desc: 'Your clients can book services whenever they want, providing maximum satisfaction and flexibility.',
        color: 'linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)', // Light Purple/Blue
        icon: 'users'
    }
];

export default function BusinessImpactSection() {
    const scrollRef = useRef(null);

    const scroll = (direction) => {
        if (scrollRef.current) {
            const { current } = scrollRef;
            // Get exact width of one card + the gap (40px)
            const card = current.querySelector('.impact-card');
            const scrollAmount = card ? card.offsetWidth + 40 : 380;
            current.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
        }
    };

    return (
        <section style={{ background: '#FFFFFF', padding: '120px 0', overflow: 'hidden' }}>
            <div className="max-container">
                <style>{`
                    .scroll-hide-bar::-webkit-scrollbar {
                        display: none;
                    }
                    /* Responsive Card Sizing */
                    .impact-card {
                        flex: 0 0 calc(33.333% - 26.66px); /* 3 cards, gap 40px */
                    }
                    @media (max-width: 1024px) {
                        .impact-card {
                            flex: 0 0 calc(50% - 20px); /* 2 cards */
                        }
                    }
                    @media (max-width: 640px) {
                        .impact-card {
                            flex: 0 0 100%; /* 1 card */
                        }
                    }
                `}</style>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: '60px', flexWrap: 'wrap', gap: '24px' }}>
                    <div style={{ maxWidth: '800px' }}>
                        <p style={{ fontSize: '14px', fontWeight: 600, color: '#6C63FF', margin: 0, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Real Impact
                        </p>
                        <h2 className="fluid-h2" style={{ color: '#1A1A1A', fontWeight: 700, letterSpacing: '-0.02em', marginTop: '12px', marginBottom: '24px' }}>
                            Control & Explode Your Business Scale
                        </h2>
                        <p className="fluid-p" style={{ color: '#6B7280', margin: 0 }}>
                            JasaKu is not just a calendar app. It's a smart ecosystem designed specifically to accelerate the growth and profitability of your business in every aspect.
                        </p>
                    </div>

                    {/* Navigation Buttons */}
                    <div style={{ display: 'flex', gap: '16px', paddingBottom: '16px' }} className="hide-on-mobile">
                        <button 
                            onClick={() => scroll('left')}
                            aria-label="Scroll Left"
                            style={{ width: '56px', height: '56px', borderRadius: '50%', border: '1px solid #E5E7EB', background: '#FFFFFF', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', transition: 'all 0.3s', boxShadow: '0 4px 6px rgba(0,0,0,0.05)' }}
                            onMouseOver={(e) => { e.currentTarget.style.background = '#F9FAFB'; e.currentTarget.style.transform = 'scale(1.05)'; e.currentTarget.style.borderColor = '#6C63FF'; e.currentTarget.style.color = '#6C63FF'; }}
                            onMouseOut={(e) => { e.currentTarget.style.background = '#FFFFFF'; e.currentTarget.style.transform = 'scale(1)'; e.currentTarget.style.borderColor = '#E5E7EB'; e.currentTarget.style.color = 'inherit'; }}
                        >
                            <Icon name="arrow" size={24} style={{ transform: 'rotate(180deg)' }} />
                        </button>
                        <button 
                            onClick={() => scroll('right')}
                            aria-label="Scroll Right"
                            style={{ width: '56px', height: '56px', borderRadius: '50%', border: '1px solid #E5E7EB', background: '#FFFFFF', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', transition: 'all 0.3s', boxShadow: '0 4px 6px rgba(0,0,0,0.05)' }}
                            onMouseOver={(e) => { e.currentTarget.style.background = '#F9FAFB'; e.currentTarget.style.transform = 'scale(1.05)'; e.currentTarget.style.borderColor = '#6C63FF'; e.currentTarget.style.color = '#6C63FF'; }}
                            onMouseOut={(e) => { e.currentTarget.style.background = '#FFFFFF'; e.currentTarget.style.transform = 'scale(1)'; e.currentTarget.style.borderColor = '#E5E7EB'; e.currentTarget.style.color = 'inherit'; }}
                        >
                            <Icon name="arrow" size={24} />
                        </button>
                    </div>
                </div>

                <div 
                    ref={scrollRef}
                    className="scroll-hide-bar"
                    style={{ 
                        display: 'flex', 
                        gap: '40px', 
                        overflowX: 'auto',
                        padding: '30px 40px 80px 40px',
                        margin: '0 -40px',
                        snapType: 'x mandatory',
                        WebkitOverflowScrolling: 'touch',
                        scrollbarWidth: 'none',
                        msOverflowStyle: 'none',
                        scrollBehavior: 'smooth'
                    }}>
                    {impacts.map((item, idx) => (
                        <motion.div
                            key={idx}
                            initial={{ opacity: 0, y: 30 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, margin: "-50px" }}
                            transition={{ duration: 0.6, delay: idx * 0.1 }}
                            whileHover={{ 
                                y: -15, 
                                scale: 1.02,
                                boxShadow: '0 30px 60px rgba(108,99,255,0.15)',
                                borderColor: 'rgba(108,99,255,0.3)'
                            }}
                            className="impact-card"
                            style={{
                                background: '#FAFAFA',
                                borderRadius: '32px',
                                padding: '48px 32px',
                                border: '1px solid #E5E7EB',
                                position: 'relative',
                                overflow: 'hidden',
                                boxShadow: '0 20px 40px rgba(0,0,0,0.04)',
                                zIndex: 1,
                                scrollSnapAlign: 'start',
                                transition: 'border-color 0.3s ease, box-shadow 0.3s ease'
                            }}
                        >
                            {/* Abstract gradient blur in background */}
                            <div style={{
                                position: 'absolute',
                                top: '-50px',
                                right: '-50px',
                                width: '200px',
                                height: '200px',
                                background: item.color,
                                filter: 'blur(60px)',
                                opacity: 0.6,
                                borderRadius: '50%',
                                zIndex: -1
                            }}></div>
                            
                            <div style={{ position: 'relative', zIndex: 1 }}>
                                <div style={{ 
                                    width: '64px', height: '64px', borderRadius: '16px', 
                                    background: '#FFFFFF', display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    boxShadow: '0 10px 20px rgba(0,0,0,0.06)', marginBottom: '32px',
                                    color: '#1A1A1A'
                                }}>
                                    <Icon name={item.icon} size={32} />
                                </div>
                                <h3 style={{ fontSize: '72px', fontWeight: 800, color: '#6C63FF', margin: '0 0 16px 0', letterSpacing: '-0.04em', lineHeight: 1 }}>
                                    {item.metric}
                                </h3>
                                <h4 style={{ fontSize: '26px', fontWeight: 700, color: '#1A1A1A', margin: '0 0 16px 0', lineHeight: 1.3 }}>
                                    {item.title}
                                </h4>
                                <p style={{ color: '#4B5563', fontSize: '17px', lineHeight: 1.6, margin: 0 }}>
                                    {item.desc}
                                </p>
                            </div>
                        </motion.div>
                    ))}
                </div>
            </div>
        </section>
    );
}
