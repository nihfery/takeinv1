import { motion, useAnimation } from 'framer-motion';
import { useRef, useEffect } from 'react';
import { Icon } from '../components/Icons.jsx';

export default function HeroSection({ onRegister }) {
    const cursorControls = useAnimation();
    const step0Controls = useAnimation();
    const step1Controls = useAnimation();
    const step2Controls = useAnimation();
    
    const serviceControls = useAnimation();
    const timeControls = useAnimation();
    const btnControls = useAnimation();

    // Simulate auto-booking loop with precise cursor and interactive elements
    useEffect(() => {
        let isMounted = true;
        const runDemo = async () => {
            while (isMounted) {
                // Reset to Initial State
                await cursorControls.start({ left: '80%', top: '90%', opacity: 0, transition: { duration: 0 } });
                await step0Controls.start({ opacity: 1, x: 0, transition: { duration: 0 } });
                await step1Controls.start({ opacity: 0, x: 20, transition: { duration: 0 } });
                await step2Controls.start({ opacity: 0, scale: 0.9, transition: { duration: 0 } });
                
                await serviceControls.start({ borderColor: '#E5E7EB', backgroundColor: '#F9FAFB', scale: 1, transition: { duration: 0 } });
                await timeControls.start({ borderColor: '#E5E7EB', backgroundColor: '#FFFFFF', color: '#1A1A1A', scale: 1, transition: { duration: 0 } });
                await btnControls.start({ scale: 1, backgroundColor: '#6C63FF', transition: { duration: 0 } });
                
                await new Promise(r => setTimeout(r, 500));
                
                // --- STEP 0: Select Service ---
                // Cursor moves to click the service (middle of the card)
                await cursorControls.start({ opacity: 1, left: 'calc(100% - 80px)', top: '100px', transition: { duration: 0.8, ease: 'easeOut' } });
                await cursorControls.start({ scale: 0.9, transition: { duration: 0.1 } });
                await serviceControls.start({ borderColor: '#6C63FF', backgroundColor: '#EEF2FF', scale: 0.98, transition: { duration: 0.1 } });
                await cursorControls.start({ scale: 1, transition: { duration: 0.1 } });
                await serviceControls.start({ scale: 1, transition: { duration: 0.1 } });
                if (!isMounted) break;

                await new Promise(r => setTimeout(r, 400));
                
                // Transition to Step 1
                step0Controls.start({ opacity: 0, x: -20, transition: { duration: 0.4 } });
                await step1Controls.start({ opacity: 1, x: 0, transition: { duration: 0.4 } });

                // --- STEP 1: Select Time ---
                // Cursor moves to 13:00 (right column, top row).
                await cursorControls.start({ left: 'calc(100% - 100px)', top: '90px', transition: { duration: 0.8, ease: 'easeOut' } });
                await cursorControls.start({ scale: 0.9, transition: { duration: 0.1 } });
                await timeControls.start({ borderColor: '#6C63FF', backgroundColor: '#EEF2FF', color: '#6C63FF', scale: 0.95, transition: { duration: 0.1 } });
                await cursorControls.start({ scale: 1, transition: { duration: 0.1 } });
                await timeControls.start({ scale: 1, transition: { duration: 0.1 } });
                
                // Cursor moves to the continue booking button.
                await cursorControls.start({ left: '50%', top: '220px', transition: { duration: 0.8, delay: 0.3, ease: 'easeOut' } });
                await cursorControls.start({ scale: 0.9, transition: { duration: 0.1 } });
                btnControls.start({ scale: 0.95, backgroundColor: '#5b54d6', transition: { duration: 0.1 } });
                await cursorControls.start({ scale: 1, transition: { duration: 0.1 } });
                btnControls.start({ scale: 1, transition: { duration: 0.1 } });
                if (!isMounted) break;

                // --- STEP 2: Success ---
                step1Controls.start({ opacity: 0, x: -20, transition: { duration: 0.3 } });
                await step2Controls.start({ opacity: 1, scale: 1, transition: { duration: 0.4 } });
                await cursorControls.start({ opacity: 0, transition: { duration: 0.3 } });
                
                // Wait before looping
                await new Promise(r => setTimeout(r, 2500));
            }
        };
        runDemo();
        return () => { isMounted = false; };
    }, [cursorControls, step0Controls, step1Controls, step2Controls, serviceControls, timeControls, btnControls]);

    return (
        <section className="hero-section section-padding" style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', background: '#FAFAFA', position: 'relative', overflow: 'hidden' }}>
            <div className="max-container responsive-flex-row" style={{ zIndex: 10 }}>
                
                {/* Copy / CTA */}
                <motion.div 
                    style={{ flex: 1 }}
                    initial={{ opacity: 0, x: -40 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ duration: 0.8, ease: 'easeOut' }}
                >
                    <p style={{ fontSize: '16px', fontWeight: 600, color: '#6C63FF', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                        JasaKu Smart System
                    </p>
                    <h1 className="fluid-h1" style={{ fontWeight: 600, letterSpacing: '-0.04em', color: '#1A1A1A', marginBottom: '32px' }}>
                        Make your salon & clinic business <span style={{ color: '#6C63FF' }}>run automatically.</span>
                    </h1>
                    <p className="fluid-p" style={{ color: '#666', marginBottom: '40px', maxWidth: '90%' }}>
                        From customers booking schedules, to daily financial recap reports. All integrated in one premium system.
                    </p>
                    <div className="stack-on-mobile" style={{ display: 'flex', gap: '16px' }}>
                        <button className="btn dark-pill" type="button" onClick={onRegister} style={{ padding: '0 32px', minHeight: '52px', fontSize: '16px' }}>
                            Start for Free Now
                        </button>
                        <button className="btn outline-pill" type="button" onClick={onRegister} style={{ padding: '0 32px', minHeight: '52px', fontSize: '16px', borderColor: '#E5E7EB', color: '#1A1A1A', background: '#FFFFFF' }}>
                            Request Demo
                        </button>
                    </div>
                    <div style={{ marginTop: '24px', display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-start', gap: '24px', fontSize: '14px', color: '#9CA3AF', fontWeight: 500 }}>
                        <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}><Icon name="check" size={16} /> No Credit Card Required</span>
                        <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}><Icon name="check" size={16} /> Cancel Anytime</span>
                    </div>
                </motion.div>

                {/* Live Booking Demo Widget */}
                <motion.div 
                    className="responsive-widget"
                    initial={{ opacity: 0, y: 40 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.8, delay: 0.2, ease: 'easeOut' }}
                >
                    {/* Background glow */}
                    <div style={{ position: 'absolute', inset: '-20px', background: 'radial-gradient(circle, rgba(108,99,255,0.15) 0%, rgba(250,250,250,0) 70%)', zIndex: -1, borderRadius: '50%' }}></div>
                    
                    <div style={{ background: '#FFFFFF', borderRadius: '32px', padding: '32px', boxShadow: '0 24px 64px rgba(0,0,0,0.08)', border: '1px solid rgba(0,0,0,0.04)', height: '480px', display: 'flex', flexDirection: 'column' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '32px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: '#F3F4F6', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '18px', fontWeight: 700, color: '#1A1A1A' }}>A</div>
                                <div>
                                    <h4 style={{ margin: 0, fontSize: '16px', fontWeight: 600, color: '#1A1A1A' }}>Aura Beauty Studio</h4>
                                    <span style={{ fontSize: '12px', color: '#6C63FF', fontWeight: 500 }}>Live Booking</span>
                                </div>
                            </div>
                        </div>

                        {/* Interactive UI with overlapping absolute steps */}
                        <div style={{ flex: 1, position: 'relative' }}>
                            
                            {/* Step 0: Choose services */}
                            <motion.div animate={step0Controls} style={{ position: 'absolute', inset: 0, pointerEvents: 'none' }}>
                                <p style={{ fontWeight: 600, fontSize: '18px', marginBottom: '20px', color: '#1A1A1A' }}>Select Service</p>
                                <motion.div animate={serviceControls} style={{ padding: '16px', borderRadius: '16px', border: '1px solid', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <div>
                                        <div style={{ fontWeight: 600, color: '#1A1A1A' }}>Hair Spa & Treatment</div>
                                        <div style={{ fontSize: '13px', color: '#6B7280', marginTop: '4px' }}>60 min &bull; Therapist: Sarah</div>
                                    </div>
                                    <div style={{ fontWeight: 600, color: '#6C63FF' }}>Rp 250k</div>
                                </motion.div>
                            </motion.div>

                            {/* Step 1: Choose time */}
                            <motion.div animate={step1Controls} style={{ position: 'absolute', inset: 0, pointerEvents: 'none', opacity: 0 }}>
                                <p style={{ fontWeight: 600, fontSize: '18px', marginBottom: '20px', color: '#1A1A1A' }}>Select Time</p>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                                    <div style={{ padding: '16px', borderRadius: '12px', border: '1px solid #E5E7EB', textAlign: 'center', color: '#9CA3AF' }}>10:00</div>
                                    <motion.div animate={timeControls} style={{ padding: '16px', borderRadius: '12px', border: '2px solid', textAlign: 'center', fontWeight: 600 }}>13:00</motion.div>
                                    <div style={{ padding: '16px', borderRadius: '12px', border: '1px solid #E5E7EB', textAlign: 'center', color: '#1A1A1A' }}>14:30</div>
                                    <div style={{ padding: '16px', borderRadius: '12px', border: '1px solid #E5E7EB', textAlign: 'center', color: '#1A1A1A' }}>16:00</div>
                                </div>
                                <motion.div animate={btnControls} style={{ marginTop: '32px', color: 'white', padding: '16px', borderRadius: '100px', textAlign: 'center', fontWeight: 600 }}>Continue Booking</motion.div>
                            </motion.div>

                            {/* Step 2: Success */}
                            <motion.div animate={step2Controls} style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', opacity: 0, pointerEvents: 'none' }}>
                                <div style={{ width: '64px', height: '64px', borderRadius: '50%', background: '#10B981', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'white', marginBottom: '24px' }}>
                                    <Icon name="check" size={32} />
                                </div>
                                <h3 style={{ fontSize: '24px', fontWeight: 600, marginBottom: '12px' }}>Booking Successful!</h3>
                                <p style={{ textAlign: 'center', color: '#6B7280', fontSize: '15px' }}>The schedule is automatically added to your calendar.</p>
                            </motion.div>
                            
                            {/* Cursor */}
                            <motion.div animate={cursorControls} style={{ position: 'absolute', zIndex: 100, pointerEvents: 'none', marginLeft: '-6px', marginTop: '-4px' }}>
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style={{ filter: 'drop-shadow(0 4px 6px rgba(0,0,0,0.2))' }}>
                                    <path d="M5.5 3.21V20.8C5.5 21.65 6.5 22.1 7.14 21.54L10.93 18.2C11.13 18.02 11.39 17.92 11.66 17.92H18.5C19.33 17.92 19.75 16.92 19.16 16.33L6.16 3.33C5.58 2.74 4.5 3.16 4.5 3.99V3.21H5.5Z" fill="#1A1A1A"/>
                                    <path d="M6 4L18 16H11.66C11.13 16 10.62 16.2 10.22 16.55L6 20.25V4Z" fill="white"/>
                                </svg>
                            </motion.div>
                        </div>
                    </div>
                </motion.div>

            </div>
        </section>
    );
}
