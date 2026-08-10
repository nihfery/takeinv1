import { motion } from 'framer-motion';
import { useState } from 'react';
import { Icon } from '../components/Icons.jsx';

export default function PricingSection() {
    const [isYearly, setIsYearly] = useState(true);

    return (
        <section id="pricing" className="section-padding" style={{ background: '#FFFFFF', color: '#1A1A1A' }}>
            <div className="max-container">
                
                <div style={{ textAlign: 'center', marginBottom: '64px' }}>
                    <p style={{ fontSize: '16px', fontWeight: 600, color: '#6C63FF', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                        Pricing & Subscription Plans
                    </p>
                    <h2 className="fluid-h2" style={{ fontWeight: 500, letterSpacing: '-0.03em', marginBottom: '24px' }}>
                        Small investment, maximum results.
                    </h2>
                    <p className="fluid-p" style={{ color: '#666', maxWidth: '600px', margin: '0 auto 40px' }}>
                        Choose a plan that fits your business scale. No hidden fees, cancel anytime.
                    </p>

                    {/* Billing Toggle */}
                    <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '64px' }}>
                        <div style={{ display: 'flex', background: '#F3F4F6', padding: '6px', borderRadius: '100px', position: 'relative', width: '100%', maxWidth: '380px' }}>
                            <div 
                                onClick={() => setIsYearly(false)}
                                style={{ flex: 1, padding: '12px 0', textAlign: 'center', borderRadius: '100px', cursor: 'pointer', zIndex: 10, fontWeight: 600, color: !isYearly ? '#FFFFFF' : '#6B7280', transition: 'color 0.3s' }}
                            >
                                Monthly
                            </div>
                            <div 
                                onClick={() => setIsYearly(true)}
                                style={{ flex: 1, padding: '12px 0', display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '8px', borderRadius: '100px', cursor: 'pointer', zIndex: 10, fontWeight: 600, color: isYearly ? '#FFFFFF' : '#6B7280', transition: 'color 0.3s' }}
                            >
                                Yearly <span style={{ background: '#10B981', color: 'white', fontSize: '10px', padding: '2px 8px', borderRadius: '10px' }}>Save 20%</span>
                            </div>
                            <motion.div 
                                animate={{ left: isYearly ? '50%' : '6px' }} 
                                transition={{ type: "spring", stiffness: 400, damping: 30 }}
                                style={{ position: 'absolute', top: '6px', bottom: '6px', width: 'calc(50% - 6px)', background: '#1A1A1A', borderRadius: '100px', zIndex: 5 }}
                            />
                        </div>
                    </div>
                </div>

                {/* Pricing Cards */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '32px', alignItems: 'center' }}>
                    
                    {/* Basic */}
                    <motion.div 
                        initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.5 }}
                        style={{ background: '#FAFAFA', border: '1px solid #E5E7EB', borderRadius: '32px', padding: '48px', height: 'fit-content' }}
                    >
                        <div style={{ fontSize: '24px', fontWeight: 700, marginBottom: '16px' }}>Starter</div>
                        <div style={{ fontSize: '15px', color: '#666', marginBottom: '32px', minHeight: '45px' }}>Perfect for freelancers or small studios just starting out.</div>
                        <div style={{ fontSize: '48px', fontWeight: 800, marginBottom: '8px' }}>
                            {isYearly ? 'Rp 99k' : 'Rp 149k'}
                        </div>
                        <div style={{ fontSize: '14px', color: '#9CA3AF', marginBottom: '32px' }}>/month</div>
                        
                        <motion.a 
                            href="#" 
                            whileHover={{ scale: 1.03, backgroundColor: '#1A1A1A', color: '#FFFFFF', borderColor: '#1A1A1A' }}
                            whileTap={{ scale: 0.97 }}
                            style={{ display: 'block', textAlign: 'center', width: '100%', padding: '16px', background: '#FFFFFF', border: '2px solid #E5E7EB', borderRadius: '100px', fontWeight: 600, color: '#1A1A1A', marginBottom: '40px', textDecoration: 'none', transition: 'background-color 0.3s, color 0.3s, border-color 0.3s' }}>
                            Start for Free
                        </motion.a>
                        
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            {["Max 2 Staff/Therapists", "Digital Booking Calendar", "Instagram Bio Link", "Standard WhatsApp Notifications"].map(feature => (
                                <div key={feature} style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '15px', color: '#4B5563' }}>
                                    <Icon name="check" size={16} color="#6C63FF" /> {feature}
                                </div>
                            ))}
                        </div>
                    </motion.div>

                    {/* Pro (Recommended) */}
                    <motion.div 
                        initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.5, delay: 0.1 }}
                        style={{ background: '#111827', color: 'white', borderRadius: '32px', padding: '48px', position: 'relative', boxShadow: '0 24px 64px rgba(0,0,0,0.2)' }}
                    >
                        <div style={{ position: 'absolute', top: '-16px', left: '50%', transform: 'translateX(-50%)', background: 'linear-gradient(135deg, #6C63FF, #A78BFA)', padding: '8px 24px', borderRadius: '100px', fontSize: '13px', fontWeight: 700, letterSpacing: '0.05em' }}>
                            MOST POPULAR
                        </div>
                        <div style={{ fontSize: '24px', fontWeight: 700, marginBottom: '16px' }}>Professional</div>
                        <div style={{ fontSize: '15px', color: '#9CA3AF', marginBottom: '32px', minHeight: '45px' }}>All essential features for a rapidly growing business.</div>
                        <div style={{ fontSize: '48px', fontWeight: 800, marginBottom: '8px' }}>
                            {isYearly ? 'Rp 299k' : 'Rp 399k'}
                        </div>
                        <div style={{ fontSize: '14px', color: '#9CA3AF', marginBottom: '32px' }}>/month</div>
                        
                        <motion.a 
                            href="#" 
                            whileHover={{ scale: 1.03, boxShadow: '0 15px 30px rgba(108, 99, 255, 0.4)', backgroundColor: '#7C3AED', borderColor: '#7C3AED' }}
                            whileTap={{ scale: 0.97 }}
                            style={{ display: 'block', textAlign: 'center', width: '100%', padding: '16px', background: '#6C63FF', border: '2px solid #6C63FF', borderRadius: '100px', fontWeight: 600, color: '#FFFFFF', marginBottom: '40px', textDecoration: 'none', transition: 'background-color 0.3s, border-color 0.3s, box-shadow 0.3s' }}>
                            Choose Professional
                        </motion.a>
                        
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            {["Max 15 Staff/Therapists", "Financial & Commission Reports", "Google Calendar Integration", "24/7 AI Chatbot", "Promo & Coupon Management"].map(feature => (
                                <div key={feature} style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '15px', color: '#D1D5DB' }}>
                                    <Icon name="check" size={16} color="#A78BFA" /> {feature}
                                </div>
                            ))}
                        </div>
                    </motion.div>

                    {/* Enterprise */}
                    <motion.div 
                        initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.5, delay: 0.2 }}
                        style={{ background: '#FAFAFA', border: '1px solid #E5E7EB', borderRadius: '32px', padding: '48px', height: 'fit-content' }}
                    >
                        <div style={{ fontSize: '24px', fontWeight: 700, marginBottom: '16px' }}>Enterprise</div>
                        <div style={{ fontSize: '15px', color: '#666', marginBottom: '32px', minHeight: '45px' }}>For large clinics or multi-branch franchises.</div>
                        <div style={{ fontSize: '48px', fontWeight: 800, marginBottom: '8px' }}>
                            {isYearly ? 'Rp 899k' : 'Rp 1.1jt'}
                        </div>
                        <div style={{ fontSize: '14px', color: '#9CA3AF', marginBottom: '32px' }}>/month</div>
                        
                        <motion.a 
                            href="#" 
                            whileHover={{ scale: 1.03, backgroundColor: '#1A1A1A', color: '#FFFFFF', borderColor: '#1A1A1A' }}
                            whileTap={{ scale: 0.97 }}
                            style={{ display: 'block', textAlign: 'center', width: '100%', padding: '16px', background: '#FFFFFF', border: '2px solid #E5E7EB', borderRadius: '100px', fontWeight: 600, color: '#1A1A1A', marginBottom: '40px', textDecoration: 'none', transition: 'background-color 0.3s, color 0.3s, border-color 0.3s' }}>
                            Contact Sales
                        </motion.a>
                        
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            {["Unlimited Staff & Services", "Multi-Branch Management", "Custom Access Rights & Roles", "Open API Access", "Dedicated Account Manager"].map(feature => (
                                <div key={feature} style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '15px', color: '#4B5563' }}>
                                    <Icon name="check" size={16} color="#6C63FF" /> {feature}
                                </div>
                            ))}
                        </div>
                    </motion.div>

                </div>
            </div>
        </section>
    );
}
