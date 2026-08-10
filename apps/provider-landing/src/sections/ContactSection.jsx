import { motion } from 'framer-motion';
import { Icon } from '../components/Icons.jsx';

export default function ContactSection() {
    return (
        <section id="contact" className="section-padding" style={{ background: '#FAFAFA', position: 'relative', overflow: 'hidden' }}>
            {/* Background Accents */}
            <div style={{ position: 'absolute', top: '-10%', left: '-5%', width: '400px', height: '400px', background: '#e0c3fc', filter: 'blur(100px)', opacity: 0.4, borderRadius: '50%' }}></div>
            
            <div className="max-container responsive-flex-row" style={{ 
                background: 'rgba(255, 255, 255, 0.7)', 
                backdropFilter: 'blur(20px)',
                borderRadius: '40px', 
                padding: '64px', 
                border: '1px solid rgba(255,255,255,0.5)',
                boxShadow: '0 24px 64px rgba(0,0,0,0.04)',
                position: 'relative',
                zIndex: 1
            }}>
                
                {/* Left: Text & CTA */}
                <div className="responsive-widget" style={{ flex: 1, paddingRight: '40px' }}>
                    <motion.div initial={{ opacity: 0, x: -30 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }} transition={{ duration: 0.6 }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', background: '#EEF2FF', padding: '8px 16px', borderRadius: '100px', marginBottom: '24px' }}>
                            <div style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#6C63FF' }}></div>
                            <span style={{ fontSize: '13px', fontWeight: 600, color: '#4F46E5', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Contact Us</span>
                        </div>
                        
                        <h2 className="fluid-h2" style={{ fontWeight: 800, letterSpacing: '-0.03em', lineHeight: 1.1, marginBottom: '24px', color: '#1A1A1A' }}>
                            Still unsure or need a <br/> <span style={{ color: '#6C63FF' }}>Custom Plan?</span>
                        </h2>
                        
                        <p className="fluid-p" style={{ color: '#4B5563', lineHeight: 1.6, marginBottom: '40px', fontSize: '18px', maxWidth: '90%' }}>
                            Every business is unique. Our expert consulting team is ready to help you design a JasaKu ecosystem that best fits your salon or clinic's operational workflow. Free with no commitment.
                        </p>
                        
                        <div className="stack-on-mobile" style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
                            <motion.a 
                                href="https://wa.me/6281234567890" 
                                target="_blank" 
                                rel="noreferrer" 
                                whileHover={{ scale: 1.05, y: -2 }}
                                whileTap={{ scale: 0.95 }}
                                style={{ 
                                    display: 'inline-flex', alignItems: 'center', gap: '12px', justifyContent: 'center',
                                    padding: '16px 32px', background: '#25D366', color: 'white', borderRadius: '100px', 
                                    fontWeight: 600, fontSize: '16px', textDecoration: 'none', boxShadow: '0 12px 24px rgba(37,211,102,0.3)',
                                }}
                            >
                                <Icon name="message-circle" size={20} /> Consult via WhatsApp
                            </motion.a>
                            <div style={{ color: '#9CA3AF', fontSize: '14px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <Icon name="clock" size={16} /> Instant replies 24/7
                            </div>
                        </div>
                    </motion.div>
                </div>

                {/* Right: Glassmorphism Contact Card */}
                <div className="responsive-widget" style={{ flex: '0 0 420px', position: 'relative' }}>
                    {/* Glowing blobs behind the card */}
                    <div style={{ position: 'absolute', top: -40, right: -40, width: 250, height: 250, background: 'linear-gradient(135deg, #6C63FF 0%, #A78BFA 100%)', borderRadius: '50%', filter: 'blur(60px)', opacity: 0.5, zIndex: -1 }}></div>
                    <div style={{ position: 'absolute', bottom: -40, left: -40, width: 200, height: 200, background: 'linear-gradient(135deg, #fbc2eb 0%, #a18cd1 100%)', borderRadius: '50%', filter: 'blur(60px)', opacity: 0.5, zIndex: -1 }}></div>
                    
                    <motion.div 
                        initial={{ opacity: 0, y: 30 }} 
                        whileInView={{ opacity: 1, y: 0 }} 
                        viewport={{ once: true }} 
                        transition={{ duration: 0.6, delay: 0.2 }}
                        style={{ 
                            background: 'rgba(255, 255, 255, 0.9)', 
                            backdropFilter: 'blur(24px)',
                            border: '1px solid rgba(255, 255, 255, 0.8)',
                            borderRadius: '32px', 
                            padding: '40px', 
                            color: '#1A1A1A', 
                            boxShadow: '0 30px 60px rgba(0,0,0,0.08)',
                            position: 'relative',
                            zIndex: 1
                        }}
                    >
                        <div style={{ fontSize: '24px', fontWeight: 800, marginBottom: '8px', color: '#1A1A1A', letterSpacing: '-0.02em' }}>Free Live Demo</div>
                        <div style={{ fontSize: '15px', color: '#6B7280', marginBottom: '32px', lineHeight: 1.6 }}>
                            Our experts will demonstrate directly how JasaKu works, specifically for your business scale.
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            <div style={{ position: 'relative' }}>
                                <input 
                                    type="text" 
                                    placeholder="Your Full Name" 
                                    style={{ 
                                        width: '100%', boxSizing: 'border-box',
                                        background: '#F9FAFB', border: '1px solid #E5E7EB', padding: '16px 20px', 
                                        borderRadius: '16px', fontSize: '15px', color: '#1A1A1A', outline: 'none' 
                                    }} 
                                />
                            </div>
                            <div style={{ position: 'relative' }}>
                                <input 
                                    type="text" 
                                    placeholder="Business / Salon Name" 
                                    style={{ 
                                        width: '100%', boxSizing: 'border-box',
                                        background: '#F9FAFB', border: '1px solid #E5E7EB', padding: '16px 20px', 
                                        borderRadius: '16px', fontSize: '15px', color: '#1A1A1A', outline: 'none' 
                                    }} 
                                />
                            </div>
                            <motion.button 
                                whileHover={{ scale: 1.02, boxShadow: '0 15px 30px rgba(108, 99, 255, 0.3)' }}
                                whileTap={{ scale: 0.98 }}
                                style={{ 
                                    background: 'linear-gradient(135deg, #6C63FF 0%, #4F46E5 100%)', 
                                    padding: '18px', 
                                    borderRadius: '16px', 
                                    fontSize: '16px', 
                                    fontWeight: 700, 
                                    textAlign: 'center', 
                                    color: 'white', 
                                    marginTop: '8px',
                                    border: 'none',
                                    cursor: 'pointer',
                                    boxShadow: '0 10px 20px rgba(108, 99, 255, 0.2)',
                                    transition: 'box-shadow 0.3s'
                                }}
                            >
                                Schedule Now
                            </motion.button>
                        </div>
                    </motion.div>
                </div>

            </div>
        </section>
    );
}
