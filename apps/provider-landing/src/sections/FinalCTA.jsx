import { motion } from 'framer-motion';
import { Icon } from '../components/Icons.jsx';

export default function FinalCTA({ onRegister }) {
    return (
        <section className="final-cta">
            <motion.div
                className="cta-marquee cta-marquee-top"
                aria-hidden="true"
                animate={{ x: ['0%', '-50%'] }}
                transition={{ duration: 50, repeat: Infinity, ease: 'linear' }}
            >
                <span>AUTOMATED BOOKING</span>
                <span>SEAMLESS PAYMENT</span>
                <span>HASSLE-FREE ADMIN</span>
                <span>AUTOMATED BOOKING</span>
                <span>SEAMLESS PAYMENT</span>
                <span>HASSLE-FREE ADMIN</span>
            </motion.div>
            <motion.div
                className="cta-marquee cta-marquee-bottom"
                aria-hidden="true"
                animate={{ x: ['-50%', '0%'] }}
                transition={{ duration: 65, repeat: Infinity, ease: 'linear' }}
            >
                <span>REPEAT VISIT</span>
                <span>REMINDER</span>
                <span>REVENUE</span>
                <span>REPEAT VISIT</span>
                <span>REMINDER</span>
                <span>REVENUE</span>
            </motion.div>
            <motion.div
                className="final-cta-copy"
                initial={{ opacity: 0, y: 34 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: false, amount: 0.45 }}
                transition={{ duration: 0.7 }}
            >
                <p className="eyebrow">JasaKu Partner</p>
                <h2>
                    Stop running your business.
                    <span> Get a system that will.</span>
                </h2>
                <p>From booking, payment, reminders, to reports. Everything is in one place.</p>
                <button className="btn dark-pill final-button" type="button" onClick={onRegister}>
                    <Icon name="arrow" size={20} />
                    Start for free
                </button>
            </motion.div>
        </section>
    );
}
