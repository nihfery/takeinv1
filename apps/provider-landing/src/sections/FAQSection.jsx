import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Icon } from '../components/Icons.jsx';

const faqs = [
    {
        q: 'How much does it cost to use JasaKu?',
        a: 'JasaKu has a very transparent pricing scheme. We offer a FREE version for growing businesses, and a fully featured, unlimited PRO version for established businesses. You only pay when your business makes money.'
    },
    {
        q: 'Do I need to download an app to use it?',
        a: 'No! JasaKu is Cloud-based (Web App). You and your staff can use it directly via a browser on your phone, tablet, or computer without needing to download any app.'
    },
    {
        q: 'Is my customer data safe on JasaKu?',
        a: 'Very safe. We use bank-grade encryption to protect your data and your customers\' data. We will also never sell or share your data with any third parties.'
    },
    {
        q: 'What if I am not tech-savvy?',
        a: 'JasaKu\'s design is specifically made to be highly user-friendly for anyone, even for staff who are not used to using computers. Our support team is also ready to help you 24/7.'
    },
    {
        q: 'Do customers need an account to book?',
        a: 'Not mandatory! Your customers can book as a "Guest" simply by entering their name and WhatsApp number, making the booking process very fast and frictionless.'
    }
];

function FAQItem({ faq, isOpen, onClick }) {
    return (
        <div style={{ borderBottom: '1px solid #E5E7EB' }}>
            <button
                onClick={onClick}
                style={{
                    width: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '24px 0',
                    background: 'transparent',
                    border: 'none',
                    cursor: 'pointer',
                    textAlign: 'left'
                }}
            >
                <span style={{ fontSize: '18px', fontWeight: 600, color: isOpen ? '#6C63FF' : '#1A1A1A', paddingRight: '24px', transition: 'color 0.3s' }}>
                    {faq.q}
                </span>
                <motion.div
                    animate={{ rotate: isOpen ? 45 : 0 }}
                    transition={{ duration: 0.3, ease: 'easeInOut' }}
                    style={{ 
                        width: '32px', height: '32px', borderRadius: '50%',
                        background: isOpen ? '#6C63FF' : '#F3F4F6',
                        color: isOpen ? '#FFFFFF' : '#1A1A1A',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        flexShrink: 0
                    }}
                >
                    <Icon name="close" size={16} />
                    {/* Re-using close icon but rotating it. At 0deg it looks like an X, wait, a plus is better. I'll use a plus icon by default, but let's check if we have one. If close is an X, rotating it by 45deg makes it a plus! */}
                </motion.div>
            </button>
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.3, ease: 'easeInOut' }}
                        style={{ overflow: 'hidden' }}
                    >
                        <div style={{ paddingBottom: '24px', color: '#4B5563', fontSize: '16px', lineHeight: 1.6 }}>
                            {faq.a}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

export default function FAQSection() {
    const [openIdx, setOpenIdx] = useState(0); // First one open by default

    return (
        <section id="faq" style={{ background: '#FAFAFA', padding: '120px 0' }}>
            <div className="max-container" style={{ maxWidth: '1100px', margin: '0 auto' }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '60px', alignItems: 'flex-start' }}>
                    {/* Left Column: Heading */}
                    <div className="faq-heading-col" style={{ flex: '1 1 400px' }}>
                        <p style={{ fontSize: '14px', fontWeight: 600, color: '#6C63FF', margin: 0, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Have Questions?
                        </p>
                        <h2 className="fluid-h2" style={{ color: '#1A1A1A', fontWeight: 700, letterSpacing: '-0.02em', marginTop: '12px' }}>
                            Frequently Asked Questions
                        </h2>
                        <p style={{ color: '#6B7280', fontSize: '16px', lineHeight: 1.6, marginTop: '16px', maxWidth: '80%' }}>
                            Find answers to common questions about JasaKu's features, pricing, and security. If you don't find the answer you're looking for, contact our support team.
                        </p>
                    </div>

                    {/* Right Column: Accordion */}
                    <div className="faq-accordion-col" style={{ flex: '1 1 500px', width: '100%' }}>
                        {faqs.map((faq, idx) => (
                            <FAQItem 
                                key={idx} 
                                faq={faq} 
                                isOpen={openIdx === idx} 
                                onClick={() => setOpenIdx(openIdx === idx ? -1 : idx)} 
                            />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
