import { motion } from 'framer-motion';
import { Icon } from '../components/Icons.jsx';

const features = [
    {
        title: '24/7 Booking Website',
        desc: 'Accept bookings anytime even while you sleep. No more missed chat messages.',
        icon: 'calendar'
    },
    {
        title: 'Integrated POS System',
        desc: 'Process payments, manage discounts, and calculate staff commissions automatically in one click.',
        icon: 'credit-card'
    },
    {
        title: 'Staff & Schedule Management',
        desc: 'Manage work shifts, commissions, and individual staff schedules so there are no scheduling conflicts.',
        icon: 'users'
    },
    {
        title: 'Automated Email Notifications',
        desc: 'Send appointment reminders to customers automatically to reduce no-shows by up to 90%.',
        icon: 'mail'
    },
    {
        title: 'Financial Reports & Analytics',
        desc: 'Monitor daily revenue, best-selling services, and staff performance from an interactive dashboard.',
        icon: 'chart'
    },
    {
        title: 'Manage from Mobile App',
        desc: 'Monitor and manage your business directly from the palm of your hand, anywhere, anytime.',
        icon: 'phone'
    }
];

export default function FeatureGridSection() {
    return (
        <section id="features-grid" style={{ background: '#FFFFFF', padding: '120px 0', borderTop: '1px solid #E5E7EB', position: 'relative' }}>
            <div className="max-container">
                <style>{`
                    .feature-card {
                        transition: background-color 0.4s ease;
                    }
                    .feature-card .icon-box {
                        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                    }
                    .feature-card h3 {
                        transition: color 0.3s ease;
                    }
                    .feature-card:hover {
                        background: #FFFFFF !important;
                    }
                    .feature-card:hover .icon-box {
                        background: #6C63FF !important;
                        color: #FFFFFF !important;
                        transform: scale(1.15) rotate(-5deg);
                        box-shadow: 0 12px 24px rgba(108, 99, 255, 0.3);
                    }
                    .feature-card:hover h3 {
                        color: #6C63FF !important;
                    }
                `}</style>

                <div style={{ textAlign: 'center', marginBottom: '80px', maxWidth: '700px', margin: '0 auto 80px' }}>
                    <p style={{ fontSize: '14px', fontWeight: 600, color: '#6C63FF', margin: 0, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                        Everything You Need
                    </p>
                    <h2 className="fluid-h2" style={{ color: '#1A1A1A', fontWeight: 700, letterSpacing: '-0.02em', marginTop: '12px' }}>
                        The all-in-one platform to grow your business
                    </h2>
                </div>

                <div className="feature-grid">
                    {features.map((feature, idx) => (
                        <motion.div
                            key={idx}
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, margin: '-50px' }}
                            transition={{ duration: 0.5, delay: idx * 0.1 }}
                            className="feature-card"
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'flex-start',
                                padding: '36px',
                                background: '#FAFAFA',
                                borderRadius: '24px',
                                border: '1px solid #E5E7EB',
                                cursor: 'pointer',
                                zIndex: 1,
                                position: 'relative'
                            }}
                            whileHover={{ 
                                y: -12, 
                                scale: 1.02,
                                borderColor: 'rgba(108,99,255,0.4)', 
                                boxShadow: '0 30px 60px -15px rgba(108,99,255,0.25)',
                                zIndex: 10
                            }}
                        >
                            <div className="icon-box" style={{
                                width: '64px', height: '64px',
                                borderRadius: '18px',
                                background: 'rgba(108, 99, 255, 0.1)',
                                color: '#6C63FF',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                marginBottom: '24px'
                            }}>
                                <Icon name={feature.icon} size={32} />
                            </div>
                            <h3 style={{ margin: '0 0 14px 0', fontSize: '22px', fontWeight: 700, color: '#1A1A1A' }}>
                                {feature.title}
                            </h3>
                            <p style={{ margin: 0, fontSize: '16px', color: '#6B7280', lineHeight: 1.6 }}>
                                {feature.desc}
                            </p>
                        </motion.div>
                    ))}
                </div>
            </div>
        </section>
    );
}
