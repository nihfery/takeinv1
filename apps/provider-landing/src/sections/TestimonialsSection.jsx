import { motion } from 'framer-motion';
import { useRef, useState, useEffect } from 'react';
import { Icon } from '../components/Icons.jsx';

const reviewsRow1 = [
    { name: "Aura Studio", text: "Since using JasaKu, empty slots in the afternoon are always fully booked thanks to the auto-offer feature. Very helpful!" },
    { name: "The Grooming Co.", text: "I can monitor my 3 barbershop branches at once from my phone. Transactions are transparent and there are no more leaks." },
    { name: "Wellness Spa", text: "Customers love it because they can book at 2 AM. When we wake up, the schedule is full!" },
    { name: "Glossy Nails", text: "The WhatsApp reminder feature makes the no-show rate drop drastically. Business becomes much more efficient." },
    { name: "Barber King", text: "Moving to JasaKu is the best decision. Clients easily book and I can focus on cutting hair." },
    { name: "Serenity Massage", text: "The system is very user friendly, even my clueless staff can use it in a day. Highly recommended!" },
];

const reviewsRow2 = [
    { name: "Beauty Lounge", text: "No more receptionist needed! JasaKu manages all schedules automatically without clashes." },
    { name: "Klinik Estetika", text: "The treatment history feature really helps us maintain the loyalty of our VIP customers." },
    { name: "Tirta Spa", text: "The personal booking website looks very luxurious and professional. Customers think we paid millions!" },
    { name: "Gentleman's Cut", text: "The financial reporting is very neat. I no longer need to calculate manually every time I close the shop." },
    { name: "Lash & Brow", text: "Really like the deposit booking feature. Now there are no more fake customers who don't show up." },
    { name: "Derma Care", text: "JasaKu support is incredibly responsive. The features continue to grow according to input from us business owners." },
];

const StarRating = () => (
    <div style={{ display: 'flex', gap: '4px', marginBottom: '16px' }}>
        {[1,2,3,4,5].map(i => (
            <Icon key={i} name="star" size={16} color="#1A1A1A" className="star-filled" />
        ))}
    </div>
);

const TestimonialCard = ({ review }) => {
    return (
        <div 
            style={{ 
                background: '#FFFFFF', 
                border: '1px solid #E5E7EB',
                boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
                padding: '32px', 
                borderRadius: '16px', 
                width: '320px',
                height: '100%',
                display: 'flex',
                flexDirection: 'column',
                flexShrink: 0
            }}
        >
            <StarRating />
            <p style={{ fontSize: '15px', color: '#4B5563', lineHeight: 1.5, marginBottom: '24px', flex: 1, fontWeight: 500 }}>
                {review.text}
            </p>
            <div style={{ fontSize: '14px', color: '#1A1A1A', fontWeight: 600 }}>
                {review.name}
            </div>
        </div>
    );
};

const MarqueeRow = ({ items, reverse }) => {
    const scrollRef = useRef(null);
    const [isDragging, setIsDragging] = useState(false);
    const [startX, setStartX] = useState(0);
    const [scrollLeft, setScrollLeft] = useState(0);

    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;
        let animationFrameId;
        let lastTime = performance.now();
        let exactScroll = el.scrollLeft;
        const speed = 15; // Pixels per second (very slow and relaxing)

        const scroll = (time) => {
            if (!isDragging) {
                const delta = (time - lastTime) / 1000;
                const moveBy = speed * delta;
                
                if (reverse) {
                    exactScroll -= moveBy;
                    if (exactScroll <= 0) exactScroll = el.scrollWidth / 2;
                } else {
                    exactScroll += moveBy;
                    if (exactScroll >= el.scrollWidth / 2) exactScroll = 0;
                }
                el.scrollLeft = exactScroll;
            } else {
                exactScroll = el.scrollLeft; // Sync exactScroll when dragging
            }
            lastTime = time;
            animationFrameId = requestAnimationFrame(scroll);
        };
        animationFrameId = requestAnimationFrame(scroll);
        return () => cancelAnimationFrame(animationFrameId);
    }, [isDragging, reverse]);

    const startDrag = (e) => {
        setIsDragging(true);
        setStartX(e.pageX - scrollRef.current.offsetLeft);
        setScrollLeft(scrollRef.current.scrollLeft);
    };
    
    const stopDrag = () => {
        setIsDragging(false);
    };
    
    const onDrag = (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const x = e.pageX - scrollRef.current.offsetLeft;
        const walk = (x - startX) * 2;
        scrollRef.current.scrollLeft = scrollLeft - walk;
    };

    return (
        <div 
            ref={scrollRef}
            onMouseDown={startDrag}
            onMouseLeave={stopDrag}
            onMouseUp={stopDrag}
            onMouseMove={onDrag}
            style={{ 
                display: 'flex', 
                gap: '24px', 
                overflowX: 'auto', 
                scrollbarWidth: 'none', 
                msOverflowStyle: 'none', 
                padding: '12px 0',
                cursor: isDragging ? 'grabbing' : 'grab',
                userSelect: 'none'
            }}
            className="no-scrollbar"
        >
            <style>{`
                .no-scrollbar::-webkit-scrollbar { display: none; }
                .star-filled path { fill: #1A1A1A; stroke: none; }
            `}</style>
            {[...items, ...items, ...items].map((rev, i) => (
                <TestimonialCard key={i} review={rev} />
            ))}
        </div>
    );
};

export default function TestimonialsSection() {
    return (
        <section style={{ padding: '120px 0', background: '#FAFAFA', textAlign: 'center', overflow: 'hidden' }}>
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: '-100px' }}
                style={{ padding: '0 30px', marginBottom: '64px' }}
            >
                <p style={{ fontSize: '16px', fontWeight: 600, color: '#6C63FF', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    Trusted by Hundreds of Partners
                </p>
                <h2 style={{ fontSize: '48px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.03em', lineHeight: 1.1 }}>
                    Don't just hear it from us.
                </h2>
            </motion.div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                <MarqueeRow items={reviewsRow1} />
                <MarqueeRow items={reviewsRow2} reverse={true} />
            </div>
        </section>
    );
}
