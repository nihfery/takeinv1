import { motion } from 'framer-motion';

const partners = [
    "AURA STUDIO", "THE GROOMING CO.", "WELLNESS SPA", "GLOW CLINIC", "NAIL BAR", "HAIR & CO.", 
    "AURA STUDIO", "THE GROOMING CO.", "WELLNESS SPA", "GLOW CLINIC", "NAIL BAR", "HAIR & CO."
];

export default function PartnerMarquee() {
    return (
        <section style={{ padding: '80px 0', background: '#FFFFFF', borderBottom: '1px solid #E5E7EB', overflow: 'hidden' }}>
            <div style={{ textAlign: 'center', marginBottom: '48px' }}>
                <p style={{ fontSize: '15px', fontWeight: 700, color: '#6C63FF', margin: 0, textTransform: 'uppercase', letterSpacing: '0.15em' }}>
                    TRUSTED BY 500+ LEADING BRANDS
                </p>
            </div>
            
            <div style={{ display: 'flex', whiteSpace: 'nowrap', overflow: 'hidden', position: 'relative' }}>
                {/* Gradient Fades for seamless looping effect */}
                <div style={{ position: 'absolute', top: 0, left: 0, width: '200px', height: '100%', background: 'linear-gradient(to right, #FFFFFF, transparent)', zIndex: 10 }}></div>
                <div style={{ position: 'absolute', top: 0, right: 0, width: '200px', height: '100%', background: 'linear-gradient(to left, #FFFFFF, transparent)', zIndex: 10 }}></div>

                <motion.div 
                    animate={{ x: [0, -2800] }} 
                    transition={{ repeat: Infinity, ease: "linear", duration: 35 }}
                    style={{ display: 'flex', gap: '120px', paddingRight: '120px' }}
                >
                    {partners.map((partner, index) => (
                        <div key={index} style={{ 
                            fontSize: '40px', 
                            fontWeight: 800, 
                            color: '#9CA3AF', 
                            letterSpacing: '-0.03em',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '24px'
                        }}>
                            <div style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#6C63FF' }}></div>
                            {partner}
                        </div>
                    ))}
                    {/* Duplicate list for seamless loop */}
                    {partners.map((partner, index) => (
                        <div key={`dup-${index}`} style={{ 
                            fontSize: '40px', 
                            fontWeight: 800, 
                            color: '#9CA3AF', 
                            letterSpacing: '-0.03em',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '24px'
                        }}>
                            <div style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#6C63FF' }}></div>
                            {partner}
                        </div>
                    ))}
                </motion.div>
            </div>
        </section>
    );
}
