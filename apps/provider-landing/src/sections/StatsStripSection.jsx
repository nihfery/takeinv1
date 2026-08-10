import { motion } from 'framer-motion';

const stats = [
    { value: '130.000+', label: 'Partner businesses' },
    { value: '450.000+', label: 'Active professionals' },
    { value: '1 Billion+', label: 'Appointments booked' },
    { value: '120+', label: 'Countries' },
];

export default function StatsStripSection() {
    return (
        <section style={{ 
            background: 'linear-gradient(135deg, #6C63FF 0%, #A78BFA 100%)', 
            padding: '60px 0',
            color: '#FFFFFF'
        }}>
            <div className="max-container">
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '40px', justifyContent: 'space-around', alignItems: 'center', textAlign: 'center' }}>
                    {stats.map((stat, idx) => (
                        <motion.div 
                            key={idx}
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.5, delay: idx * 0.1 }}
                            style={{ flex: '1 1 200px' }}
                        >
                            <h3 style={{ fontSize: '48px', fontWeight: 800, margin: '0 0 8px 0', letterSpacing: '-0.03em' }}>{stat.value}</h3>
                            <p style={{ fontSize: '18px', fontWeight: 500, margin: 0, opacity: 0.9 }}>{stat.label}</p>
                        </motion.div>
                    ))}
                </div>
            </div>
        </section>
    );
}
