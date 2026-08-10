import { motion } from 'framer-motion';
import { useState } from 'react';

export default function TransformationSection() {
    const [appointments, setAppointments] = useState(15);
    const averagePrice = 250000;
    
    // Logic: With JasaKu, we recover 3 empty slots, increase repeat visits, saving time.
    const currentRevenue = appointments * averagePrice * 30; // 30 days
    const projectedIncrease = Math.floor(currentRevenue * 0.24); // 24% increase
    const newRevenue = currentRevenue + projectedIncrease;

    const formatRupiah = (num) => {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
    };

    return (
        <section id="pricing" className="proof-section section-padding" style={{ background: '#FFFFFF', overflow: 'hidden' }}>
            <div className="max-container responsive-flex-row">
                
                <motion.div 
                    style={{ flex: 1 }}
                    initial={{ opacity: 0, x: -40 }}
                    whileInView={{ opacity: 1, x: 0 }}
                    viewport={{ once: true, margin: '-100px' }}
                >
                    <h2 className="fluid-h2" style={{ fontWeight: 500, letterSpacing: '-0.03em', lineHeight: 1.1, color: '#1A1A1A', marginBottom: '24px' }}>
                        Calculate your business growth potential.
                    </h2>
                    <p className="fluid-p" style={{ color: '#666', marginBottom: '40px' }}>
                        JasaKu is proven to increase average revenue by up to 24% in the first year through automated empty slot filling and increased repeat visit frequency.
                    </p>

                    <div style={{ background: '#FAFAFA', border: '1px solid #E5E7EB', padding: '32px', borderRadius: '24px' }}>
                        <label style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 600, color: '#1A1A1A', marginBottom: '16px' }}>
                            Average Daily Bookings: <span style={{ color: '#6C63FF' }}>{appointments} Customers</span>
                        </label>
                        <input 
                            type="range" 
                            min="5" 
                            max="100" 
                            value={appointments} 
                            onChange={(e) => setAppointments(parseInt(e.target.value))}
                            style={{ width: '100%', accentColor: '#6C63FF', height: '8px', borderRadius: '4px', cursor: 'pointer' }}
                        />
                        <p style={{ fontSize: '13px', color: '#9CA3AF', marginTop: '12px', textAlign: 'center' }}>
                            (Assuming an average service price of Rp 250.000)
                        </p>
                    </div>
                </motion.div>

                <motion.div 
                    style={{ flex: 1 }}
                    initial={{ opacity: 0, scale: 0.95 }}
                    whileInView={{ opacity: 1, scale: 1 }}
                    viewport={{ once: true }}
                >
                    <div style={{ background: 'linear-gradient(135deg, #1A1A1A 0%, #262626 100%)', color: '#FFFFFF', padding: '48px', borderRadius: '32px', boxShadow: '0 32px 64px rgba(0,0,0,0.15)' }}>
                        <h3 style={{ fontSize: '20px', fontWeight: 500, color: '#A3A3A3', marginBottom: '8px' }}>Projected Monthly Revenue</h3>
                        <div className="fluid-h2" style={{ fontWeight: 700, color: '#FFFFFF', marginBottom: '32px', borderBottom: '1px solid rgba(255,255,255,0.1)', paddingBottom: '32px' }}>
                            {formatRupiah(newRevenue)}
                        </div>

                        <div className="responsive-grid-2" style={{ display: 'grid', gap: '24px' }}>
                            <div>
                                <div style={{ fontSize: '14px', color: '#A3A3A3', marginBottom: '4px' }}>Potential Increase</div>
                                <div style={{ fontSize: '24px', fontWeight: 600, color: '#10B981' }}>+ {formatRupiah(projectedIncrease)}</div>
                            </div>
                            <div>
                                <div style={{ fontSize: '14px', color: '#A3A3A3', marginBottom: '4px' }}>Admin Time Saved</div>
                                <div style={{ fontSize: '24px', fontWeight: 600, color: '#A78BFA' }}>34 Hours / mo</div>
                            </div>
                        </div>
                    </div>
                </motion.div>
                
            </div>
        </section>
    );
}
