import { motion } from 'framer-motion';
import { Icon } from '../components/Icons.jsx';

const categories = [
    { id: 1, name: 'Hair Salon', desc: 'Manage hair cut & wash queues', icon: 'users', color: 'linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%)', span: 'col-span-2 row-span-2', image: '/images/cat_salon_1783484297124.png' },
    { id: 2, name: 'Barbershop', desc: 'Hassle-free booking system', icon: 'zap', color: 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)', span: 'col-span-1 row-span-1', image: '/images/cat_barber_1783484306582.png' },
    { id: 3, name: 'Spa & Massage', desc: 'Easily manage therapist schedules', icon: 'heart', color: 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)', span: 'col-span-1 row-span-1', image: '/images/cat_spa_1783484318465.png' },
    { id: 4, name: 'Beauty Clinic', desc: 'Medical records & treatments', icon: 'spark', color: 'linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)', span: 'col-span-1 row-span-2', image: '/images/cat_beauty_1783484328713.png' },
    { id: 5, name: 'Make Up Artist', desc: 'Online portfolio & deposits', icon: 'star', color: 'linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)', span: 'col-span-1 row-span-1', image: '/images/cat_makeup_1783484345660.png' },
    { id: 6, name: 'Dental Clinic', desc: 'Doctor & patient schedules', icon: 'shield', color: 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)', span: 'col-span-2 row-span-1', image: '/images/cat_dental_1783484354957.png' },
    { id: 7, name: 'Veterinary Clinic', desc: 'Grooming & doctor schedules', icon: 'zap', color: 'linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)', span: 'col-span-1 row-span-1', image: '/images/cat_vet_1783484364922.png' },
];

export default function CategoriesSection() {
    return (
        <section id="audience" style={{ background: '#FAFAFA', padding: '120px 0', overflow: 'hidden' }}>
            <div className="max-container">
                <div style={{ textAlign: 'center', marginBottom: '60px' }}>
                    <p style={{ fontSize: '14px', fontWeight: 600, color: '#6C63FF', margin: 0, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                        Perfect For Everyone
                    </p>
                    <h2 className="fluid-h2" style={{ color: '#1A1A1A', fontWeight: 700, letterSpacing: '-0.02em', marginTop: '12px' }}>
                        Whatever your business is, <br/> JasaKu is the solution.
                    </h2>
                </div>

                <div className="bento-grid">
                    {categories.map((cat, idx) => (
                        <motion.div
                            key={cat.id}
                            initial={{ opacity: 0, y: 30 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, margin: '-50px' }}
                            transition={{ duration: 0.6, delay: idx * 0.1, type: 'spring' }}
                            whileHover={{ y: -8, scale: 0.98 }}
                            className={`bento-item ${cat.span}`}
                            style={{
                                borderRadius: '24px',
                                padding: '32px',
                                position: 'relative',
                                overflow: 'hidden',
                                display: 'flex',
                                flexDirection: 'column',
                                justifyContent: 'flex-end',
                                cursor: 'pointer',
                                boxShadow: '0 10px 30px rgba(0,0,0,0.05)'
                            }}
                        >
                            {/* Background Image */}
                            <motion.div 
                                style={{
                                    position: 'absolute',
                                    inset: 0,
                                    backgroundImage: `url(${cat.image})`,
                                    backgroundSize: 'cover',
                                    backgroundPosition: 'center',
                                    zIndex: 0
                                }}
                                whileHover={{ scale: 1.1 }}
                                transition={{ duration: 0.5 }}
                            />
                            
                            {/* Gradient Overlay */}
                            <div style={{
                                position: 'absolute',
                                inset: 0,
                                background: cat.color,
                                opacity: 0.8,
                                zIndex: 1
                            }}></div>

                            {/* Glassmorphism overlay for text readability */}
                            <div style={{
                                position: 'absolute',
                                bottom: 0, left: 0, right: 0,
                                padding: '24px',
                                background: 'rgba(255, 255, 255, 0.4)',
                                backdropFilter: 'blur(10px)',
                                WebkitBackdropFilter: 'blur(10px)',
                                borderTop: '1px solid rgba(255, 255, 255, 0.5)',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '16px',
                                zIndex: 2
                            }}>
                                <div style={{ 
                                    background: '#1A1A1A', color: '#FFF', 
                                    width: '48px', height: '48px', borderRadius: '14px', 
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    flexShrink: 0
                                }}>
                                    <Icon name={cat.icon} size={24} />
                                </div>
                                <div>
                                    <h3 style={{ margin: 0, color: '#1A1A1A', fontSize: '20px', fontWeight: 700 }}>{cat.name}</h3>
                                    <p style={{ margin: '4px 0 0 0', color: '#333', fontSize: '14px', fontWeight: 500 }}>{cat.desc}</p>
                                </div>
                            </div>
                        </motion.div>
                    ))}
                </div>
            </div>
        </section>
    );
}
