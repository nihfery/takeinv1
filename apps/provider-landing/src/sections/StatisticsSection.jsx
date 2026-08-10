import { motion } from 'framer-motion';
import { useState, useEffect } from 'react';
import { Icon } from '../components/Icons.jsx';

export default function StatisticsSection() {
    const fullText = "Sure! There is still 1 slot with Therapist Sarah at 14:00. Would you like me to help you book it now?";
    const [typedText, setTypedText] = useState('');
    const [typingPhase, setTypingPhase] = useState(0); // 0: waiting, 1: customer asks, 2: typing, 3: typed, 4: reset

    useEffect(() => {
        let timeout;
        if (typingPhase === 0) {
            timeout = setTimeout(() => setTypingPhase(1), 1000);
        } else if (typingPhase === 1) {
            timeout = setTimeout(() => setTypingPhase(2), 1500);
        } else if (typingPhase === 2) {
            let i = 0;
            const typeInterval = setInterval(() => {
                setTypedText(fullText.slice(0, i + 1));
                i++;
                if (i >= fullText.length) {
                    clearInterval(typeInterval);
                    setTypingPhase(3);
                }
            }, 30); // typing speed
            return () => clearInterval(typeInterval);
        } else if (typingPhase === 3) {
            timeout = setTimeout(() => {
                setTypingPhase(0);
                setTypedText('');
            }, 5000);
        }
        return () => clearTimeout(timeout);
    }, [typingPhase]);

    return (
        <section className="automation-section section-padding" style={{ background: '#FAFAFA', overflow: 'hidden' }}>
            <div className="max-container responsive-flex-row">
                
                <motion.div
                    style={{ flex: 1 }}
                    initial={{ opacity: 0, x: -40 }}
                    whileInView={{ opacity: 1, x: 0 }}
                    viewport={{ once: true, margin: '-100px' }}
                    transition={{ duration: 0.7 }}
                >
                    <p style={{ fontSize: '16px', fontWeight: 600, color: '#6C63FF', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                        Automation & AI
                    </p>
                    <h2 className="fluid-h2" style={{ fontWeight: 500, letterSpacing: '-0.03em', color: '#1A1A1A', marginBottom: '24px' }}>
                        Smart Customer Service available 24/7.
                    </h2>
                    <p className="fluid-p" style={{ color: '#666', marginBottom: '32px' }}>
                        Stop wasting time replying to hundreds of repetitive chat questions. JasaKu AI Chatbot can independently answer slot availability, send payment links, and even send D-1 auto-reminders.
                    </p>
                </motion.div>

                {/* Live Chatbot Simulation Visual */}
                <motion.div
                    className="responsive-widget"
                    initial={{ opacity: 0, y: 40 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: false, amount: 0.35 }}
                >
                    <div style={{ background: '#FFFFFF', borderRadius: '32px', padding: '32px', boxShadow: '0 32px 64px rgba(0,0,0,0.08)', border: '1px solid rgba(0,0,0,0.04)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '16px', borderBottom: '1px solid #F3F4F6', paddingBottom: '24px', marginBottom: '24px' }}>
                            <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'linear-gradient(135deg, #6C63FF 0%, #A78BFA 100%)', color: '#FFFFFF', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <Icon name="spark" size={24} />
                            </div>
                            <div>
                                <h4 style={{ margin: 0, fontSize: '18px', fontWeight: 600 }}>JasaKu AI</h4>
                                <span style={{ fontSize: '13px', color: '#10B981', display: 'flex', alignItems: 'center', gap: '6px' }}><div style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#10B981' }}></div> Online</span>
                            </div>
                        </div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px', minHeight: '200px' }}>
                            {typingPhase >= 1 && (
                                <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} style={{ alignSelf: 'flex-start', background: '#F3F4F6', padding: '16px 20px', borderRadius: '20px 20px 20px 4px', fontSize: '15px', color: '#374151', maxWidth: '80%' }}>
                                    "Hello, is the salon open tomorrow at 2 PM for a Hair Spa?"
                                </motion.div>
                            )}

                            {typingPhase === 2 && (
                                <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} style={{ alignSelf: 'flex-end', display: 'flex', gap: '4px', padding: '16px 20px', background: '#6C63FF', borderRadius: '20px 20px 4px 20px' }}>
                                    <motion.div animate={{ y: [0, -5, 0] }} transition={{ repeat: Infinity, duration: 0.6, delay: 0 }} style={{ width: '6px', height: '6px', background: '#FFFFFF', borderRadius: '50%' }}></motion.div>
                                    <motion.div animate={{ y: [0, -5, 0] }} transition={{ repeat: Infinity, duration: 0.6, delay: 0.2 }} style={{ width: '6px', height: '6px', background: '#FFFFFF', borderRadius: '50%' }}></motion.div>
                                    <motion.div animate={{ y: [0, -5, 0] }} transition={{ repeat: Infinity, duration: 0.6, delay: 0.4 }} style={{ width: '6px', height: '6px', background: '#FFFFFF', borderRadius: '50%' }}></motion.div>
                                </motion.div>
                            )}

                            {typingPhase >= 3 && (
                                <div style={{ alignSelf: 'flex-end', background: '#6C63FF', padding: '16px 20px', borderRadius: '20px 20px 4px 20px', fontSize: '15px', color: '#FFFFFF', maxWidth: '80%', lineHeight: 1.5 }}>
                                    {typedText}
                                </div>
                            )}
                        </div>
                    </div>
                </motion.div>
            </div>
        </section>
    );
}
