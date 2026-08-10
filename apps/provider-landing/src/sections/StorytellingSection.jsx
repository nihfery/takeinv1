import { motion, AnimatePresence } from 'framer-motion';
import { useState, useEffect } from 'react';
import { Icon } from '../components/Icons.jsx';

const steps = [
    {
        title: 'Booking 24/7',
        desc: 'Customers choose services, staff, and schedules without waiting for chat replies.'
    },
    {
        title: 'Synchronized Schedules',
        desc: 'Orders automatically enter the calendar without double-booking risks. Staff can prepare immediately.'
    },
    {
        title: 'Execution Notifications',
        desc: 'Reminders to customers reduce no-shows by 80%. Operations run smoothly.'
    }
];

export default function StorytellingSection() {
    const [phase, setPhase] = useState(0);

    // Auto-loop the simulation phases
    useEffect(() => {
        const interval = setInterval(() => {
            setPhase((prev) => (prev + 1) % 3);
        }, 2800); // Faster 2.8 seconds per phase
        return () => clearInterval(interval);
    }, []);

    return (
        <section id="how-it-works" style={{ background: '#FFFFFF', color: '#1A1A1A', padding: '120px 0', position: 'relative' }}>
            <div className="max-container">
                <div style={{ textAlign: 'center', marginBottom: '60px' }}>
                    <p style={{ fontSize: '14px', fontWeight: 600, color: '#6C63FF', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                        Perfect Workflow
                    </p>
                    <h2 className="fluid-h2" style={{ fontWeight: 700, letterSpacing: '-0.02em', lineHeight: 1.1, color: '#1A1A1A' }}>
                        Business automation from start to finish
                    </h2>
                </div>

                {/* MacOS Browser Window Wrapper */}
                <div style={{ 
                    background: '#FFFFFF', 
                    borderRadius: '24px', 
                    border: '1px solid #E5E7EB', 
                    boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.15)',
                    overflow: 'hidden',
                    maxWidth: '1000px', // Constrain the width
                    margin: '0 auto' // Center it
                }}>
                    {/* Browser Header */}
                    <div style={{ 
                        background: '#F9FAFB', 
                        borderBottom: '1px solid #E5E7EB', 
                        padding: '16px 24px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between'
                    }}>
                        <div style={{ display: 'flex', gap: '8px', flex: 1 }}>
                            <div style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#EF4444' }}></div>
                            <div style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#F59E0B' }}></div>
                            <div style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#10B981' }}></div>
                        </div>
                        <div style={{ 
                            background: '#FFFFFF', 
                            border: '1px solid #E5E7EB',
                            borderRadius: '8px',
                            padding: '6px 32px',
                            fontSize: '13px',
                            color: '#6B7280',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            fontWeight: 500,
                            flex: 2,
                            justifyContent: 'center',
                            maxWidth: '400px'
                        }}>
                            <Icon name="lock" size={14} /> admin.jasaku.id/dashboard
                        </div>
                        <div style={{ flex: 1 }}></div>
                    </div>
                    
                    {/* Browser Body - Split Layout */}
                    <div className="browser-body" style={{ padding: '60px 40px', background: '#FAFAFA', display: 'flex', gap: '48px', alignItems: 'center' }}>
                        
                        {/* Left: Dynamic Text Highlighting */}
                        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '32px' }}>
                            {steps.map((step, idx) => {
                                const isActive = phase === idx;
                                return (
                                    <div key={idx} style={{ 
                                        opacity: isActive ? 1 : 0.4,
                                        transform: isActive ? 'scale(1.02)' : 'scale(1)',
                                        transition: 'all 0.4s ease',
                                        padding: '16px',
                                        borderRadius: '16px',
                                        background: isActive ? '#FFFFFF' : 'transparent',
                                        boxShadow: isActive ? '0 10px 30px rgba(0,0,0,0.05)' : 'none',
                                        border: isActive ? '1px solid #E5E7EB' : '1px solid transparent'
                                    }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '8px' }}>
                                            <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: isActive ? '#6C63FF' : '#E5E7EB', color: isActive ? 'white' : '#6B7280', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: '14px', transition: 'all 0.4s ease' }}>
                                                {idx + 1}
                                            </div>
                                            <h3 style={{ margin: 0, fontSize: '20px', fontWeight: 700, color: '#1A1A1A' }}>{step.title}</h3>
                                        </div>
                                        <p style={{ margin: 0, color: '#4B5563', lineHeight: 1.6, fontSize: '15px', paddingLeft: '44px' }}>
                                            {step.desc}
                                        </p>
                                    </div>
                                )
                            })}
                        </div>

                        {/* Right: Simulation Canvas */}
                        <div style={{ flex: 1.2, height: '450px', background: '#FFFFFF', borderRadius: '24px', border: '1px solid #E5E7EB', boxShadow: 'inset 0 2px 10px rgba(0,0,0,0.02)', position: 'relative', overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            
                            <AnimatePresence mode="wait">
                                {/* PHASE 0: Phone Booking Simulation */}
                                {phase === 0 && (
                                    <motion.div 
                                        key="phase0"
                                        initial={{ opacity: 0, y: 20 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        exit={{ opacity: 0, y: -20, filter: 'blur(4px)' }}
                                        transition={{ duration: 0.3 }}
                                        style={{ position: 'relative', width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                                    >
                                        {/* Mobile Phone Mockup */}
                                        <div style={{ width: '260px', height: '380px', background: '#FFFFFF', borderRadius: '32px', border: '8px solid #1A1A1A', padding: '16px', display: 'flex', flexDirection: 'column', boxShadow: '0 20px 40px rgba(0,0,0,0.1)' }}>
                                            <div style={{ fontWeight: 600, fontSize: '14px', textAlign: 'center', marginBottom: '16px' }}>Aura Studio</div>
                                            
                                            <div style={{ background: '#F9FAFB', borderRadius: '12px', padding: '12px', marginBottom: '12px', border: '1px solid #F3F4F6' }}>
                                                <div style={{ fontWeight: 600, fontSize: '12px' }}>Hair Spa & Treatment</div>
                                                <div style={{ fontSize: '10px', color: '#6C63FF', marginTop: '4px', fontWeight: 500 }}>Rp 150.000 • 60 Menit</div>
                                            </div>

                                            <div style={{ background: '#EEF2FF', borderRadius: '12px', padding: '10px 12px', display: 'flex', alignItems: 'center', gap: '8px', border: '1px solid #6C63FF' }}>
                                                <div style={{ width: '20px', height: '20px', borderRadius: '50%', background: '#FFFFFF', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 600, fontSize: '10px', color: '#4F46E5' }}>A</div>
                                                <div style={{ fontWeight: 600, fontSize: '11px', flex: 1 }}>Anya (Top Stylist)</div>
                                                <div style={{ width: '16px', height: '16px', borderRadius: '50%', background: '#6C63FF', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'white' }}>
                                                    <Icon name="check" size={10} />
                                                </div>
                                            </div>
                                            
                                            {/* Button being clicked */}
                                            <motion.div 
                                                animate={{ 
                                                    scale: [1, 1, 0.95, 1, 1],
                                                    backgroundColor: ['#6C63FF', '#6C63FF', '#4F46E5', '#6C63FF', '#6C63FF']
                                                }}
                                                transition={{ duration: 2.8, times: [0, 0.4, 0.5, 0.6, 1], ease: "easeInOut", repeat: Infinity }}
                                                style={{ background: '#6C63FF', color: 'white', padding: '12px', borderRadius: '100px', textAlign: 'center', fontWeight: 600, marginTop: 'auto', fontSize: '12px', position: 'relative' }}
                                            >
                                                Continue Booking
                                            </motion.div>
                                        </div>

                                        {/* Animated Cursor */}
                                        <motion.div
                                            initial={{ x: 100, y: 150, opacity: 0 }}
                                            animate={{ 
                                                x: [100, 0, 0, 100], 
                                                y: [150, 140, 140, 150],
                                                opacity: [0, 1, 1, 0],
                                                scale: [1, 1, 0.8, 1] // Cursor scales down slightly on click
                                            }}
                                            transition={{ duration: 2.8, times: [0, 0.3, 0.5, 0.8], ease: "easeInOut", repeat: Infinity }}
                                            style={{ position: 'absolute', zIndex: 10, filter: 'drop-shadow(0 4px 4px rgba(0,0,0,0.2))' }}
                                        >
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="#1A1A1A" stroke="#FFFFFF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                <path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51L3 3z"></path>
                                                <path d="M13 13l6 6"></path>
                                            </svg>
                                        </motion.div>
                                    </motion.div>
                                )}

                                {/* PHASE 1: Calendar Sync Simulation */}
                                {phase === 1 && (
                                    <motion.div 
                                        key="phase1"
                                        initial={{ opacity: 0, scale: 0.95 }}
                                        animate={{ opacity: 1, scale: 1 }}
                                        exit={{ opacity: 0, scale: 1.05, filter: 'blur(4px)' }}
                                        transition={{ duration: 0.5 }}
                                        style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '32px' }}
                                    >
                                        <div style={{ background: '#FFFFFF', width: '100%', height: '100%', borderRadius: '16px', padding: '24px', display: 'flex', gap: '16px', boxShadow: '0 10px 40px rgba(0,0,0,0.08)', border: '1px solid #E5E7EB' }}>
                                            <div style={{ flex: 1, borderRight: '1px solid #F3F4F6', paddingRight: '16px' }}>
                                                <div style={{ width: '80%', height: '12px', background: '#F3F4F6', borderRadius: '4px', marginBottom: '32px' }}></div>
                                                <div style={{ width: '100%', height: '8px', background: '#F3F4F6', borderRadius: '4px', marginBottom: '16px' }}></div>
                                                <div style={{ width: '60%', height: '8px', background: '#F3F4F6', borderRadius: '4px', marginBottom: '16px' }}></div>
                                                <div style={{ width: '80%', height: '8px', background: '#F3F4F6', borderRadius: '4px', marginBottom: '16px' }}></div>
                                                <div style={{ width: '40%', height: '8px', background: '#F3F4F6', borderRadius: '4px', marginBottom: '16px' }}></div>
                                            </div>
                                            <div style={{ flex: 3, position: 'relative' }}>
                                                {/* Background Grid Lines */}
                                                <div style={{ position: 'absolute', inset: 0, backgroundSize: '100% 40px', backgroundImage: 'linear-gradient(to bottom, #F9FAFB 1px, transparent 1px)' }}></div>
                                                
                                                {/* Pre-existing block */}
                                                <div style={{ background: '#F3F4F6', borderLeft: '4px solid #9CA3AF', borderRadius: '8px', width: '80%', height: '60px', padding: '8px', position: 'absolute', top: '40px', left: '10%' }}></div>

                                                {/* Animated New Booking Block */}
                                                <motion.div 
                                                    initial={{ height: 0, opacity: 0, y: -20 }}
                                                    animate={{ height: '80px', opacity: 1, y: 0 }}
                                                    transition={{ delay: 0.5, duration: 0.6, type: 'spring', bounce: 0.4 }}
                                                    style={{ background: '#EEF2FF', borderLeft: '4px solid #6C63FF', borderRadius: '8px', width: '90%', padding: '12px', position: 'absolute', top: '120px', left: '5%', boxShadow: '0 10px 20px rgba(108,99,255,0.15)' }}
                                                >
                                                    <div style={{ fontWeight: 600, color: '#4F46E5', fontSize: '13px' }}>New Booking!</div>
                                                    <div style={{ fontSize: '11px', color: '#6366F1', marginTop: '4px' }}>Hair Spa & Treatment</div>
                                                    <div style={{ fontSize: '10px', color: '#818CF8', marginTop: '8px', fontWeight: 500 }}>Anya • 14:00 - 15:00</div>
                                                </motion.div>
                                            </div>
                                        </div>
                                    </motion.div>
                                )}

                                {/* PHASE 2: Notifications Simulation */}
                                {phase === 2 && (
                                    <motion.div 
                                        key="phase2"
                                        initial={{ opacity: 0 }}
                                        animate={{ opacity: 1 }}
                                        exit={{ opacity: 0 }}
                                        transition={{ duration: 0.5 }}
                                        style={{ width: '100%', height: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '24px', background: '#F9FAFB' }}
                                    >
                                        <motion.div 
                                            initial={{ y: -50, opacity: 0, scale: 0.9 }}
                                            animate={{ y: 0, opacity: 1, scale: 1 }}
                                            transition={{ delay: 0.3, type: 'spring', bounce: 0.5 }}
                                            style={{ background: '#FFFFFF', padding: '20px', borderRadius: '24px', width: '85%', display: 'flex', alignItems: 'center', gap: '16px', boxShadow: '0 20px 40px rgba(0,0,0,0.08)', border: '1px solid #E5E7EB' }}
                                        >
                                            <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: '#ECFDF5', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, color: '#10B981' }}><Icon name="check" size={24} /></div>
                                            <div>
                                                <div style={{ fontWeight: 600, fontSize: '16px', color: '#1A1A1A' }}>Email Reminder Sent</div>
                                                <div style={{ color: '#6B7280', fontSize: '13px', marginTop: '4px' }}>Automatically sent to customers 1 day prior</div>
                                            </div>
                                        </motion.div>

                                        <motion.div 
                                            initial={{ y: 50, opacity: 0, scale: 0.9 }}
                                            animate={{ y: 0, opacity: 1, scale: 1 }}
                                            transition={{ delay: 0.8, type: 'spring', bounce: 0.5 }}
                                            style={{ background: '#FFFFFF', padding: '20px', borderRadius: '24px', width: '85%', display: 'flex', alignItems: 'center', gap: '16px', boxShadow: '0 20px 40px rgba(0,0,0,0.08)', border: '1px solid #E5E7EB' }}
                                        >
                                            <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: '#EEF2FF', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, color: '#6C63FF' }}><Icon name="users" size={24} /></div>
                                            <div>
                                                <div style={{ fontWeight: 600, fontSize: '16px', color: '#1A1A1A' }}>Therapist Notification</div>
                                                <div style={{ color: '#6B7280', fontSize: '13px', marginTop: '4px' }}>Anya (Top Stylist) is ready to serve</div>
                                            </div>
                                        </motion.div>
                                    </motion.div>
                                )}
                            </AnimatePresence>

                        </div>
                    </div>
                </div>

            </div>
        </section>
    );
}
