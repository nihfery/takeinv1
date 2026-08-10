import { motion, AnimatePresence } from 'framer-motion';
import { useState } from 'react';
import { Icon } from '../components/Icons.jsx';

const visibilityData = [
    { id: 'maps', name: 'Google Maps Search', desc: 'Appear in top search results when customers search for salons near them.', icon: 'map-pin' },
    { id: 'ig', name: 'Link in Instagram Bio', desc: 'Turn followers into customers with a single click from your Instagram profile.', icon: 'heart' },
    { id: 'web', name: 'Personal Booking Website', desc: 'Get a professional website with your own brand name instantly.', icon: 'link' },
    { id: 'qr', name: 'Scan QR Code at Cashier', desc: 'Walk-in customers can instantly book their next appointment just by scanning a QR code.', icon: 'grid' },
    { id: 'tiktok', name: 'Tiktok Profile', desc: 'Reach Gen-Z audiences and let them book directly from your videos.', icon: 'users' }
];

export default function BenefitsSection() {
    const [activeIndex, setActiveIndex] = useState(0);

    return (
        <section id="benefits" style={{ position: 'relative', background: '#FAFAFA', color: '#1A1A1A', padding: '120px 0' }}>
            <div className="max-container">
                <div style={{ textAlign: 'center', marginBottom: '80px' }}>
                    <p style={{ fontSize: '14px', fontWeight: 600, color: '#6C63FF', margin: 0, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                        Extra Visibility
                    </p>
                    <h2 className="fluid-h2" style={{ fontWeight: 700, letterSpacing: '-0.02em', marginTop: '12px', color: '#1A1A1A' }}>
                        Easily found everywhere.
                    </h2>
                </div>

                <div className="interactive-split-layout reverse" style={{ display: 'flex', gap: '80px', alignItems: 'center', flexDirection: 'row-reverse' }}>
                    
                    {/* Right: Interactive Tabs (now on the right due to row-reverse, but conceptually it's the text col) */}
                    <div className="interactive-tabs-col" style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        {visibilityData.map((item, idx) => {
                            const isActive = activeIndex === idx;
                            return (
                                <button
                                    key={item.id}
                                    onClick={() => setActiveIndex(idx)}
                                    style={{
                                        textAlign: 'left',
                                        padding: '24px',
                                        background: isActive ? '#FFFFFF' : 'transparent',
                                        border: '1px solid',
                                        borderColor: isActive ? '#E5E7EB' : 'transparent',
                                        borderRadius: '20px',
                                        cursor: 'pointer',
                                        transition: 'all 0.3s ease',
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: '16px',
                                        boxShadow: isActive ? '0 10px 30px rgba(0,0,0,0.05)' : 'none'
                                    }}
                                >
                                    <div style={{
                                        width: '40px', height: '40px', borderRadius: '12px',
                                        background: isActive ? '#EEF2FF' : '#F3F4F6',
                                        color: isActive ? '#6C63FF' : '#9CA3AF',
                                        display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                                        transition: 'all 0.3s ease'
                                    }}>
                                        <Icon name={item.icon} size={20} />
                                    </div>
                                    <div>
                                        <h3 style={{ 
                                            margin: 0, fontSize: '20px', fontWeight: 600, 
                                            color: isActive ? '#1A1A1A' : '#6B7280',
                                            transition: 'color 0.3s ease'
                                        }}>
                                            {item.name}
                                        </h3>
                                        <motion.div
                                            initial={{ height: 0, opacity: 0 }}
                                            animate={{ height: isActive ? 'auto' : 0, opacity: isActive ? 1 : 0 }}
                                            transition={{ duration: 0.3 }}
                                            style={{ overflow: 'hidden' }}
                                        >
                                            <p style={{ margin: '8px 0 0 0', color: '#4B5563', lineHeight: 1.5, fontSize: '15px' }}>
                                                {item.desc}
                                            </p>
                                        </motion.div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    {/* Left: Dynamic UI Mockups (now on the left) */}
                    <div className="interactive-visual-col" style={{ flex: 1, display: 'flex', justifyContent: 'center' }}>
                        <div style={{ width: '100%', maxWidth: '360px', aspectRatio: '360/540', borderRadius: '40px', overflow: 'hidden', position: 'relative', background: '#FFFFFF', boxShadow: '0 32px 80px rgba(0,0,0,0.1)', border: '10px solid #FFFFFF' }}>
                            <AnimatePresence mode="wait">
                                {activeIndex === 0 && <MockupMaps key="maps" />}
                                {activeIndex === 1 && <MockupInstagram key="ig" />}
                                {activeIndex === 2 && <MockupWebsite key="web" />}
                                {activeIndex === 3 && <MockupQR key="qr" />}
                                {activeIndex === 4 && <MockupTikTok key="tiktok" />}
                            </AnimatePresence>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    );
}

// ==========================================
// MOCKUPS
// ==========================================

function MockupMaps() {
    return (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} style={{ width: '100%', height: '100%', background: '#E5E7EB', position: 'relative' }}>
            {/* Fake Map Background */}
            <div style={{ position: 'absolute', inset: 0, backgroundImage: 'url(/map-bg.png)', backgroundSize: 'cover', backgroundPosition: 'center', opacity: 0.9 }}></div>
            
            {/* Map Pin */}
            <motion.div 
                initial={{ y: -50, opacity: 0 }} animate={{ y: 0, opacity: 1 }} transition={{ type: 'spring', bounce: 0.5 }}
                style={{ position: 'absolute', top: '40%', left: '50%', transform: 'translate(-50%, -50%)', display: 'flex', flexDirection: 'column', alignItems: 'center' }}
            >
                <div style={{ padding: '8px 16px', background: 'white', borderRadius: '100px', fontWeight: 600, fontSize: '14px', color: '#1A1A1A', boxShadow: '0 4px 12px rgba(0,0,0,0.1)', marginBottom: '8px' }}>
                    Aura Studio
                </div>
                <Icon name="map-pin" size={40} color="#EF4444" />
            </motion.div>

            {/* Bottom Sheet */}
            <motion.div 
                initial={{ y: '100%' }} animate={{ y: 0 }} transition={{ type: 'spring', delay: 0.3 }}
                style={{ position: 'absolute', bottom: 0, left: 0, right: 0, background: 'white', borderTopLeftRadius: '24px', borderTopRightRadius: '24px', padding: '24px', boxShadow: '0 -10px 40px rgba(0,0,0,0.1)' }}
            >
                <div style={{ fontSize: '20px', fontWeight: 700, color: '#1A1A1A', marginBottom: '4px' }}>Aura Beauty Studio</div>
                <div style={{ fontSize: '14px', color: '#6B7280', marginBottom: '16px' }}>Salon & Spa • Open 09:00 - 21:00</div>
                
                <div style={{ display: 'flex', gap: '12px' }}>
                    <div style={{ flex: 1, padding: '12px', borderRadius: '100px', background: '#6C63FF', color: 'white', textAlign: 'center', fontWeight: 600, fontSize: '14px' }}>Book Online</div>
                    <div style={{ padding: '12px', borderRadius: '100px', background: '#F3F4F6', color: '#1A1A1A', display: 'flex', alignItems: 'center', justifyContent: 'center', width: '44px' }}>
                        <Icon name="phone" size={18} />
                    </div>
                </div>
            </motion.div>
        </motion.div>
    );
}

function MockupInstagram() {
    return (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} style={{ width: '100%', height: '100%', background: 'white', padding: '24px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '16px', marginBottom: '24px' }}>
                <div style={{ width: '80px', height: '80px', borderRadius: '50%', background: 'linear-gradient(45deg, #F59E0B, #EC4899, #8B5CF6)', padding: '3px', flexShrink: 0 }}>
                    <div style={{ width: '100%', height: '100%', borderRadius: '50%', background: 'white', border: '2px solid white', overflow: 'hidden' }}>
                        <img src="/salon-bg.png" alt="Aura Studio Profile" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    </div>
                </div>
                <div style={{ flex: 1, display: 'flex', justifyContent: 'space-around', textAlign: 'center' }}>
                    <div><div style={{ fontWeight: 700, color: '#1A1A1A' }}>120</div><div style={{ fontSize: '12px', color: '#6B7280' }}>Posts</div></div>
                    <div><div style={{ fontWeight: 700, color: '#1A1A1A' }}>14.5k</div><div style={{ fontSize: '12px', color: '#6B7280' }}>Followers</div></div>
                </div>
            </div>
            
            <div style={{ fontWeight: 600, color: '#1A1A1A', marginBottom: '4px' }}>Aura Beauty Studio</div>
            <div style={{ fontSize: '14px', color: '#4B5563', marginBottom: '12px' }}>✨ Premium Hair & Nail Treatments<br/>📍 Jakarta Selatan<br/>👇 Book your slot below!</div>
            
            <motion.div 
                animate={{ scale: [1, 1.05, 1] }} transition={{ repeat: Infinity, duration: 2 }}
                style={{ background: '#EEF2FF', padding: '12px', borderRadius: '8px', marginBottom: '24px', display: 'flex', alignItems: 'center', gap: '8px' }}
            >
                <Icon name="link" size={16} color="#6C63FF" />
                <span style={{ color: '#6C63FF', fontWeight: 600, fontSize: '14px' }}>jasaku.id/aurastudio</span>
            </motion.div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '2px', borderRadius: '8px', overflow: 'hidden' }}>
                {['/ig-post-1.png', '/ig-post-2.png', '/salon-bg.png', '/salon-bg.png', '/ig-post-1.png', '/ig-post-2.png'].map((src, i) => (
                    <div key={i} style={{ aspectRatio: '1', background: '#F3F4F6' }}>
                        <img src={src} alt="Post" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    </div>
                ))}
            </div>
        </motion.div>
    );
}

function MockupWebsite() {
    return (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} style={{ width: '100%', height: '100%', background: '#F9FAFB', position: 'relative' }}>
            {/* Browser Header */}
            <div style={{ background: '#E5E7EB', height: '40px', display: 'flex', alignItems: 'center', padding: '0 16px', gap: '8px' }}>
                <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: '#EF4444' }}></div>
                <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: '#F59E0B' }}></div>
                <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: '#10B981' }}></div>
            </div>
            
            <div style={{ padding: '16px 20px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                    <div style={{ fontWeight: 800, fontSize: '20px', color: '#1A1A1A' }}>AURA.</div>
                    <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: '#F3F4F6' }}></div>
                </div>

                <motion.div initial={{ y: 20, opacity: 0 }} animate={{ y: 0, opacity: 1 }} transition={{ delay: 0.2 }}>
                    <div style={{ width: '100%', height: '110px', borderRadius: '12px', overflow: 'hidden', marginBottom: '16px' }}>
                        <img src="/salon-bg.png" alt="Aura Studio" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    </div>
                    <div style={{ fontSize: '24px', fontWeight: 700, lineHeight: 1.2, color: '#1A1A1A', marginBottom: '16px' }}>Elevate your<br/>beauty routine.</div>
                    <div style={{ padding: '10px 20px', background: '#1A1A1A', color: 'white', borderRadius: '100px', display: 'inline-block', fontWeight: 600, fontSize: '13px' }}>Book Appointment</div>
                </motion.div>

                <div style={{ marginTop: '20px', background: 'white', borderRadius: '20px', padding: '16px', boxShadow: '0 10px 25px rgba(0,0,0,0.05)' }}>
                    <div style={{ fontWeight: 600, marginBottom: '12px', color: '#1A1A1A', fontSize: '14px' }}>Popular Services</div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0', borderBottom: '1px solid #E5E7EB' }}>
                        <div><div style={{ fontWeight: 600, fontSize: '13px', color: '#1A1A1A' }}>Hair Spa</div><div style={{ fontSize: '12px', color: '#6B7280', marginTop: '2px' }}>60 mins</div></div>
                        <div style={{ background: '#F3F4F6', color: '#1A1A1A', padding: '6px 12px', borderRadius: '100px', fontSize: '12px', fontWeight: 600 }}>Book</div>
                    </div>
                </div>
            </div>
        </motion.div>
    );
}

function MockupQR() {
    return (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} style={{ width: '100%', height: '100%', background: '#111827', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
            <div style={{ textAlign: 'center', color: 'white', marginBottom: '40px' }}>
                <div style={{ fontSize: '24px', fontWeight: 600, marginBottom: '8px' }}>Scan to Book</div>
                <div style={{ color: '#9CA3AF' }}>Align QR code within the frame to scan</div>
            </div>

            <div style={{ position: 'relative', padding: '24px' }}>
                {/* Viewfinder Brackets */}
                <div style={{ position: 'absolute', top: 0, left: 0, width: '40px', height: '40px', borderTop: '4px solid #10B981', borderLeft: '4px solid #10B981', borderRadius: '16px 0 0 0' }}></div>
                <div style={{ position: 'absolute', top: 0, right: 0, width: '40px', height: '40px', borderTop: '4px solid #10B981', borderRight: '4px solid #10B981', borderRadius: '0 16px 0 0' }}></div>
                <div style={{ position: 'absolute', bottom: 0, left: 0, width: '40px', height: '40px', borderBottom: '4px solid #10B981', borderLeft: '4px solid #10B981', borderRadius: '0 0 0 16px' }}></div>
                <div style={{ position: 'absolute', bottom: 0, right: 0, width: '40px', height: '40px', borderBottom: '4px solid #10B981', borderRight: '4px solid #10B981', borderRadius: '0 0 16px 0' }}></div>

                {/* The QR Code Container */}
                <div style={{ width: '220px', height: '220px', background: 'white', borderRadius: '16px', padding: '20px', position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 0 40px rgba(16, 185, 129, 0.1)' }}>
                    
                    {/* Dense Random Grid */}
                    <div style={{ width: '100%', height: '100%', display: 'grid', gridTemplateColumns: 'repeat(12, 1fr)', gap: '4px' }}>
                        {Array(144).fill(0).map((_, i) => (
                            <div key={i} style={{ background: Math.random() > 0.4 ? '#1A1A1A' : 'transparent', borderRadius: '2px' }}></div>
                        ))}
                    </div>

                    {/* QR Code Anchor 1 (Top Left) */}
                    <div style={{ position: 'absolute', top: '20px', left: '20px', width: '56px', height: '56px', background: 'white', border: '6px solid #1A1A1A', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <div style={{ width: '24px', height: '24px', background: '#1A1A1A', borderRadius: '4px' }}></div>
                    </div>

                    {/* QR Code Anchor 2 (Top Right) */}
                    <div style={{ position: 'absolute', top: '20px', right: '20px', width: '56px', height: '56px', background: 'white', border: '6px solid #1A1A1A', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <div style={{ width: '24px', height: '24px', background: '#1A1A1A', borderRadius: '4px' }}></div>
                    </div>

                    {/* QR Code Anchor 3 (Bottom Left) */}
                    <div style={{ position: 'absolute', bottom: '20px', left: '20px', width: '56px', height: '56px', background: 'white', border: '6px solid #1A1A1A', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <div style={{ width: '24px', height: '24px', background: '#1A1A1A', borderRadius: '4px' }}></div>
                    </div>
                </div>

                {/* Scanning Laser */}
                <motion.div 
                    animate={{ y: [0, 260, 0] }} transition={{ repeat: Infinity, duration: 2.5, ease: "linear" }}
                    style={{ position: 'absolute', top: '4px', left: '-10px', right: '-10px', height: '3px', background: '#10B981', boxShadow: '0 0 20px 4px rgba(16, 185, 129, 0.6)', zIndex: 10, borderRadius: '4px' }}
                />
            </div>
            
            {/* Fake Camera Options */}
            <div style={{ display: 'flex', gap: '24px', marginTop: '48px' }}>
                <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: 'rgba(255,255,255,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <Icon name="image" size={20} color="white" />
                </div>
                <div style={{ width: '64px', height: '64px', borderRadius: '50%', border: '4px solid #10B981', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '4px' }}>
                    <div style={{ width: '100%', height: '100%', borderRadius: '50%', background: '#10B981' }}></div>
                </div>
                <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: 'rgba(255,255,255,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <Icon name="zap" size={20} color="white" />
                </div>
            </div>
        </motion.div>
    );
}

function MockupTikTok() {
    return (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} style={{ width: '100%', height: '100%', background: '#1A1A1A', position: 'relative' }}>
            {/* Real Video Background */}
            <div style={{ position: 'absolute', inset: 0, overflow: 'hidden' }}>
                <video 
                    src="/tiktok-video.mp4" 
                    autoPlay 
                    loop 
                    muted 
                    playsInline 
                    style={{ width: '100%', height: '100%', objectFit: 'cover' }} 
                />
            </div>
            
            {/* Dark overlay for text readability */}
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 60%)' }}></div>
            
            {/* Sidebar actions */}
            <div style={{ position: 'absolute', right: '16px', bottom: '100px', display: 'flex', flexDirection: 'column', gap: '24px', alignItems: 'center' }}>
                <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'white', border: '2px solid #EF4444', flexShrink: 0, overflow: 'hidden' }}>
                    <img src="/salon-bg.png" alt="Profile" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                </div>
                <Icon name="heart" size={32} color="white" />
                <Icon name="message-circle" size={32} color="white" />
                <Icon name="share-2" size={32} color="white" />
            </div>

            {/* Bottom info */}
            <div style={{ position: 'absolute', bottom: '24px', left: '16px', right: '80px' }}>
                <div style={{ fontWeight: 600, color: 'white', fontSize: '16px', marginBottom: '8px' }}>@aurastudio</div>
                <div style={{ color: 'white', fontSize: '14px', marginBottom: '16px' }}>POV: You finally booked that hair spa you deserve ✨💅 #salon #selfcare</div>
                
                <motion.div 
                    animate={{ scale: [1, 1.05, 1] }} transition={{ repeat: Infinity, duration: 2 }}
                    style={{ background: 'rgba(255,255,255,0.2)', backdropFilter: 'blur(10px)', padding: '12px 16px', borderRadius: '8px', display: 'flex', alignItems: 'center', gap: '8px', color: 'white' }}
                >
                    <Icon name="link" size={16} />
                    <span style={{ fontWeight: 600, fontSize: '14px' }}>Book Appointment (Link in Bio)</span>
                </motion.div>
            </div>
        </motion.div>
    );
}

function MockupWA() {
    const [messages, setMessages] = useState([]);

    useEffect(() => {
        let isMounted = true;
        const run = async () => {
            while (isMounted) {
                setMessages([]);
                await new Promise(r => setTimeout(r, 1000));
                if (!isMounted) return;
                setMessages([{ text: 'Hi, can I book for tomorrow at 2 PM?', sender: 'user' }]);
                await new Promise(r => setTimeout(r, 1500));
                if (!isMounted) return;
                setMessages(prev => [...prev, { text: 'Hi! 👋 For tomorrow at 2:00 PM, slots are still available. Please complete your booking via the following link: jasaku.id/book', sender: 'bot' }]);
                await new Promise(r => setTimeout(r, 4000));
            }
        };
        run();
        return () => { isMounted = false; };
    }, []);

    return (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} style={{ width: '100%', height: '100%', background: '#EFEAE2', display: 'flex', flexDirection: 'column' }}>
            {/* Header */}
            <div style={{ background: '#075E54', padding: '16px 24px', display: 'flex', alignItems: 'center', gap: '12px', color: 'white' }}>
                <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: 'white', flexShrink: 0 }}></div>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 600, fontSize: '16px' }}>Aura Studio</div>
                    <div style={{ fontSize: '12px', color: 'rgba(255,255,255,0.8)' }}>Bot Online</div>
                </div>
            </div>

            {/* Chat Area */}
            <div style={{ flex: 1, padding: '24px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                <AnimatePresence>
                    {messages.map((msg, idx) => (
                        <motion.div 
                            key={idx}
                            initial={{ opacity: 0, y: 10, scale: 0.95 }} animate={{ opacity: 1, y: 0, scale: 1 }}
                            style={{ 
                                alignSelf: msg.sender === 'user' ? 'flex-end' : 'flex-start',
                                background: msg.sender === 'user' ? '#DCF8C6' : 'white',
                                padding: '12px 16px',
                                borderRadius: '16px',
                                borderTopRightRadius: msg.sender === 'user' ? '4px' : '16px',
                                borderTopLeftRadius: msg.sender === 'bot' ? '4px' : '16px',
                                maxWidth: '85%',
                                fontSize: '14px',
                                color: '#1A1A1A',
                                boxShadow: '0 2px 4px rgba(0,0,0,0.05)'
                            }}
                        >
                            {msg.text}
                        </motion.div>
                    ))}
                </AnimatePresence>
                
                {messages.length === 1 && (
                    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} style={{ alignSelf: 'flex-start', color: '#6B7280', fontSize: '12px', fontStyle: 'italic', marginLeft: '8px' }}>
                        Typing...
                    </motion.div>
                )}
            </div>

            {/* Input */}
            <div style={{ padding: '16px', background: '#F0F0F0', display: 'flex', gap: '12px' }}>
                <div style={{ flex: 1, background: 'white', borderRadius: '100px', height: '40px' }}></div>
                <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: '#075E54', flexShrink: 0 }}></div>
            </div>
        </motion.div>
    );
}
