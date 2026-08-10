'use client';

// Dynamic mock state manager utilizing browser storage.
// Ensures that search page, detail page, cart, payment, success, profile, 
// and dashboard screens can interact and persist state without needing a database.

const fallbackImages = [
    'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=900&q=86',
    'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?auto=format&fit=crop&w=900&q=86',
    'https://images.unsplash.com/photo-1600948836101-f9ffda59d250?auto=format&fit=crop&w=900&q=86',
    'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=900&q=86',
    'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=900&q=86',
    'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=900&q=86',
    'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=900&q=86',
    'https://images.unsplash.com/photo-1552693673-1bf958298935?auto=format&fit=crop&w=900&q=86',
];

const mockStaffPhotos = [
    'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&h=200&q=80',
    'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&h=200&q=80',
    'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&h=200&q=80',
    'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&h=200&q=80',
];

export const mockPromos = [
    { code: 'NEWUSER', title: 'Promo Pengguna Baru', desc: 'Diskon 15% untuk transaksi pertamamu di YouYaku.', type: 'percentage', val: 15, minTx: 50000, expiry: '31 Des 2026' },
    { code: 'SPA10', title: 'Relaksasi Spa 10%', desc: 'Potongan Rp10.000 khusus untuk kategori Spa & Massage.', type: 'fixed', val: 10000, minTx: 80000, expiry: '31 Okt 2026' },
    { code: 'CANTIK20', title: 'Cantik Hemat 20%', desc: 'Diskon 20% tanpa batas untuk layanan kuku dan makeup.', type: 'percentage', val: 20, minTx: 100000, expiry: '30 Sep 2026' }
];

export const mockSalons = [
    {
        id: 'glow-hair-studio',
        name: 'Glow Hair Studio',
        provider: 'Salon Rambut',
        city: 'Jakarta Selatan',
        state: 'DKI Jakarta',
        minPrice: 85000,
        rating: 5,
        reviews: 168,
        image: fallbackImages[0],
        tag: 'Unggulan',
        about: 'Glow Hair Studio adalah salon rambut modern yang menyediakan layanan penataan rambut kelas dunia. Kami berfokus pada teknik potongan rambut presisi dan teknik pewarnaan premium (balayage, highlight) yang disesuaikan untuk setiap jenis rambut.',
        address: 'Jl. Kemang Raya No. 45, Mampang Prapatan, Jakarta Selatan, 12730',
        phone: '021-7890123',
        hours: { weekdays: '09:00 - 20:00', weekend: '09:00 - 21:00' },
        facilities: ['AC', 'Free WiFi', 'Kopi & Teh Gratis', 'Tempat Parkir', 'Charging Station'],
        policies: {
            cancel: 'Pembatalan gratis hingga 24 jam sebelum jadwal. Pembatalan setelah 24 jam dikenakan biaya 50%.',
            reschedule: 'Reschedule dapat dilakukan maksimal 1 kali paling lambat 12 jam sebelum jadwal.',
            late: 'Toleransi keterlambatan maksimal 15 menit. Jika melebihi, slot dapat dialihkan atau disesuaikan.'
        },
        services: [
            { id: 'cut-men', name: 'Premium Men Haircut', category: 'Haircut', desc: 'Potongan rambut eksklusif termasuk cuci rambut, pijat kepala rileks, handuk hangat, dan styling dengan pomade.', duration: 45, price: 95000, discountPrice: 85000, popular: true },
            { id: 'cut-women', name: 'Precision Women Haircut', category: 'Haircut', desc: 'Konsultasi gaya rambut, keramas, potongan presisi, pijat bahu, blow dry, dan styling akhir.', duration: 60, price: 180000, popular: true },
            { id: 'color-balayage', name: 'Balayage Signature Color', category: 'Hair Coloring', desc: 'Pewarnaan rambut artistik balayage menggunakan produk L\'Oreal Professionnel premium. Termasuk vitamin pelindung rambut.', duration: 150, price: 650000, discountPrice: 590000 },
            { id: 'treatment-keratin', name: 'Keratin Intense Smooth', category: 'Hair Treatment', desc: 'Perawatan keratin untuk meluruskan rambut mengembang, melembutkan helai rambut kusam, dan berkilau hingga 3 bulan.', duration: 120, price: 450000 }
        ],
        staff: [
            { id: 'st-budi', name: 'Budi Hartono', role: 'Art Director / Senior Stylist', rating: 4.9, reviews: 104, photo: mockStaffPhotos[1] },
            { id: 'st-sari', name: 'Sari Wijaya', role: 'Color Expert Stylist', rating: 5.0, reviews: 64, photo: mockStaffPhotos[0] }
        ]
    },
    {
        id: 'luna-nails-beauty',
        name: 'Luna Nails & Beauty',
        provider: 'Kuku, Eyelash',
        city: 'Jakarta Barat',
        state: 'DKI Jakarta',
        minPrice: 65000,
        rating: 4.9,
        reviews: 94,
        image: fallbackImages[1],
        tag: 'Baru',
        about: 'Luna Nails & Beauty offers a comfortable, hygienic setting for premium manicures, pedicures, gel art, and eyelash extensions. Every tool is fully sterilised to keep your visit safe.',
        address: 'Ruko Central Park, Blok AA No. 12, Grogol Petamburan, Jakarta Barat, 11470',
        phone: '021-5678901',
        hours: { weekdays: '10:00 - 21:00', weekend: '10:00 - 22:00' },
        facilities: ['AC', 'Free WiFi', 'Sofa Pijat', 'Minuman Sambutan', 'Sanitized Tools'],
        policies: {
            cancel: 'Pembatalan gratis hingga 12 jam sebelum jadwal. DP hangus jika tidak datang.',
            reschedule: 'Reschedule gratis maksimal 6 jam sebelum jadwal.',
            late: 'Toleransi keterlambatan 10 menit karena jadwal yang sangat padat.'
        },
        services: [
            { id: 'nail-manicure', name: 'Classic Manicure & Pedicure', category: 'Nail Care', desc: 'Pemotongan kuku, perapian kutikula, scrubbing halus tangan dan kaki, pijat pelembab, dan pengkilapan alami.', duration: 50, price: 90000, discountPrice: 75000, popular: true },
            { id: 'nail-gel', name: 'Gel Polish Single Color', category: 'Nail Care', desc: 'Aplikasi nail gel premium dengan ketahanan warna hingga 4 minggu. Pilihan lebih dari 150 warna impor.', duration: 40, price: 110000 },
            { id: 'nail-art', name: 'Custom Gel Nail Art (10 Fingers)', category: 'Nail Care', desc: 'Beautiful custom nail art with painting, glitter, or embellishments based on your photo reference.', duration: 90, price: 220000, popular: true },
            { id: 'lash-extension', name: 'Eyelash Extension Volume Natural', category: 'Eyebrow & Lashes', desc: 'Pemasangan bulu mata palsu helai demi helai menggunakan lem sensitif-ringan medis agar nyaman dipakai dan tahan lama.', duration: 90, price: 195000 }
        ],
        staff: [
            { id: 'st-rini', name: 'Rini Safitri', role: 'Senior Nail Artist', rating: 4.9, reviews: 52, photo: mockStaffPhotos[2] },
            { id: 'st-anita', name: 'Anita Putri', role: 'Lash Expert Stylist', rating: 4.8, reviews: 42, photo: mockStaffPhotos[0] }
        ]
    },
    {
        id: 'maison-de-beaute',
        name: 'Maison de Beaute',
        provider: 'Facial, Spa',
        city: 'Jakarta Pusat',
        state: 'DKI Jakarta',
        minPrice: 95000,
        rating: 5,
        reviews: 226,
        image: fallbackImages[2],
        tag: 'Unggulan',
        about: 'Maison de Beaute is an exclusive facial aesthetics clinic and spa in the city centre. Enjoy a calm lavender aromatherapy setting that refreshes your face and body after a busy day.',
        address: 'Jl. Menteng Raya No. 88, Menteng, Jakarta Pusat, 10310',
        phone: '021-3456789',
        hours: { weekdays: '09:00 - 21:00', weekend: '09:00 - 21:00' },
        facilities: ['AC', 'VIP Room', 'Welcome Drink Aromaterapi', 'Shower Room', 'Private Lockers'],
        policies: {
            cancel: 'Pembatalan harus dilakukan minimal 24 jam sebelumnya untuk refund penuh.',
            reschedule: 'Reschedule dapat diajukan 12 jam sebelum janji temu.',
            late: 'Toleransi keterlambatan 15 menit, silakan hubungi kami jika terlambat.'
        },
        services: [
            { id: 'facial-detox', name: 'Deep Clean Charcoal Facial', category: 'Facial', desc: 'Perawatan wajah mendalam: pembersihan komedo, ekstraksi, masker charcoal detoks, serum dingin, dan pijat relaksasi wajah.', duration: 75, price: 220000, discountPrice: 195000, popular: true },
            { id: 'spa-massage', name: 'Traditional Balinese Massage', category: 'Massage', desc: 'Pijat seluruh tubuh ala Bali menggunakan minyak aromaterapi kelapa hangat untuk melancarkan peredaran darah.', duration: 90, price: 180000, popular: true },
            { id: 'makeup-event', name: 'Flawless Makeup for Event', category: 'Makeup', desc: 'Professional makeup for parties, graduations, or special occasions using high-end products that last up to 8 hours.', duration: 75, price: 350000 }
        ],
        staff: [
            { id: 'st-maya', name: 'Maya Amelia', role: 'Licensed Aesthetician', rating: 5.0, reviews: 124, photo: mockStaffPhotos[2] },
            { id: 'st-yanti', name: 'Yanti Lestari', role: 'Therapist Expert', rating: 4.9, reviews: 102, photo: mockStaffPhotos[0] }
        ]
    },
    {
        id: 'serene-spa-house',
        name: 'Serene Spa House',
        provider: 'Pijat, Spa',
        city: 'Bandung',
        state: 'Jawa Barat',
        minPrice: 120000,
        rating: 4.9,
        reviews: 132,
        image: fallbackImages[4],
        tag: 'Tren',
        about: 'Serene Spa House menawarkan liburan relaksasi singkat di pegunungan Bandung yang sejuk. Spesialisasi kami adalah lulur tradisional Jawa, pijat batu hangat (hot stone massage), dan refleksologi kaki yang menenangkan.',
        address: 'Jl. Dago Pakar No. 102, Ciburial, Cimenyan, Bandung, 40198',
        phone: '022-2501234',
        hours: { weekdays: '08:00 - 21:00', weekend: '08:00 - 22:00' },
        facilities: ['AC', 'Pemandangan Alam', 'Teh Jahe Hangat', 'Sauna Room', 'Bathing Tub'],
        policies: {
            cancel: 'Pembatalan gratis hingga 24 jam sebelum jadwal.',
            reschedule: 'Dapat dijadwalkan ulang gratis maksimal 12 jam sebelumnya.',
            late: 'Toleransi keterlambatan 15 menit.'
        },
        services: [
            { id: 'spa-hotstone', name: 'Aroma Hot Stone Therapy', category: 'Spa', desc: 'Pijatan meggunakan batu basalt vulkanik hangat untuk meredakan ketegangan otot punggung secara mendalam.', duration: 90, price: 260000, popular: true },
            { id: 'spa-lulur', name: 'Traditional Javanese Lulur & Scrub', category: 'Spa', desc: 'Pemijatan tradisional disusul dengan scrub lulur rempah untuk mengangkat sel kulit mati dan mencerahkan kulit.', duration: 100, price: 210000 },
            { id: 'reflexology', name: 'Foot Reflexology & Soaking', category: 'Massage', desc: 'Pijat refleksi titik saraf kaki diawali dengan perendaman kaki dalam air garam laut hangat dan minyak peppermint.', duration: 60, price: 120000, popular: true }
        ],
        staff: [
            { id: 'st-yadi', name: 'Yadi Mulyadi', role: 'Master Masseur', rating: 4.9, reviews: 88, photo: mockStaffPhotos[3] },
            { id: 'st-ratna', name: 'Ratna Dewi', role: 'Spa Therapist expert', rating: 4.8, reviews: 44, photo: mockStaffPhotos[2] }
        ]
    },
    {
        id: 'urban-gents-barber',
        name: 'Urban Gents Barber',
        provider: 'Barbershop',
        city: 'Jakarta Utara',
        state: 'DKI Jakarta',
        minPrice: 55000,
        rating: 4.8,
        reviews: 119,
        image: fallbackImages[7],
        tag: 'Tren',
        about: 'Urban Gents Barber adalah tempat pangkas rambut pria bergaya klasik dengan sentuhan industrial. Kami menyediakan potong rambut trendi, cukur jenggot (shaving) yang bersih, hingga pewarnaan rambut pria.',
        address: 'Jl. Boulevard Raya Blok WE2 No. 8, Kelapa Gading, Jakarta Utara, 14240',
        phone: '021-4567890',
        hours: { weekdays: '10:00 - 21:00', weekend: '10:00 - 21:00' },
        facilities: ['AC', 'Free WiFi', 'Minuman Soda Gratis', 'PlayStation Lounge', 'Comfortable Barber Chair'],
        policies: {
            cancel: 'Pembatalan gratis kapan saja sebelum waktu pemesanan.',
            reschedule: 'Reschedule instan melalui website.',
            late: 'Toleransi keterlambatan 15 menit.'
        },
        services: [
            { id: 'barber-cut', name: 'Gentleman Haircut & Shave', category: 'Barber', desc: 'Potongan rambut trendi sesuai bentuk wajah, cuci rambut, pijat leher cepat, dan cukur jenggot menggunakan pisau cukur sekali pakai.', duration: 45, price: 75000, discountPrice: 65000, popular: true },
            { id: 'barber-shave', name: 'Hot Towel Royal Shave', category: 'Barber', desc: 'Cukur kumis/jenggot eksklusif dengan kompres handuk hangat, krim busa melimpah, pijat wajah rileks, dan aftershave dingin.', duration: 30, price: 55000 },
            { id: 'barber-color', name: 'Men Hair Color (Black/Brown)', category: 'Barber', desc: 'Pewarnaan rambut pria untuk menutup uban atau mengubah warna alami rambut menggunakan pewarna cepat 30 menit.', duration: 60, price: 150000, popular: true }
        ],
        staff: [
            { id: 'st-andre', name: 'Andre Wijaya', role: 'Head Barber Stylist', rating: 4.8, reviews: 78, photo: mockStaffPhotos[1] },
            { id: 'st-ricky', name: 'Ricky Kurniawan', role: 'Senior Barber', rating: 4.8, reviews: 41, photo: mockStaffPhotos[3] }
        ]
    }
];

// Reusable Service Add-ons list
export const mockAddons = {
    'cut-men': [
        { id: 'add-wash', name: 'Hair Wash & Tonic', price: 20000, duration: 10, desc: 'Pencucian rambut dengan sampo khusus dan hair tonic antipasang.' },
        { id: 'add-massage', name: 'Extra Scalp Massage (15m)', price: 30000, duration: 15, desc: 'Pijat kepala ekstra rileks 15 menit untuk melepas penat.' }
    ],
    'cut-women': [
        { id: 'add-hairmask', name: 'Creambath L\'Oreal Quick Mask', price: 50000, duration: 20, desc: 'Masker creambath L\'Oreal cepat untuk kelembutan rambut ekstra.' },
        { id: 'add-blow', name: 'Style Blow Dry Out', price: 30000, duration: 15, desc: 'Styling blow rambut keluar yang tahan sepanjang hari.' }
    ],
    'nail-manicure': [
        { id: 'add-paraffin', name: 'Warm Paraffin Wax Treatment', price: 60000, duration: 15, desc: 'Lilin parafin hangat untuk melembutkan kulit tangan yang kasar.' },
        { id: 'add-nailart-simple', name: 'Simple Nail Art (2 Fingers)', price: 40000, duration: 15, desc: 'Desain gambar/hiasan kuku sederhana pada 2 jari pilihan.' }
    ]
};

// Generates time slots dynamically for the calendar
export function generateTimeSlots(dateStr, staffId) {
    const slots = [];
    const baseHours = [
        { start: '09:00', end: '10:00', period: 'Pagi' },
        { start: '10:00', end: '11:00', period: 'Pagi' },
        { start: '11:00', end: '12:00', period: 'Pagi' },
        { start: '12:00', end: '13:00', period: 'Siang' },
        { start: '13:00', end: '14:00', period: 'Siang' },
        { start: '14:00', end: '15:00', period: 'Siang' },
        { start: '15:00', end: '16:00', period: 'Sore' },
        { start: '16:00', end: '17:00', period: 'Sore' },
        { start: '17:00', end: '18:00', period: 'Sore' },
        { start: '18:00', end: '19:00', period: 'Malam' },
        { start: '19:00', end: '20:00', period: 'Malam' }
    ];
    
    // Deterministic random seeding based on date and staff to make availability look real
    let seed = 0;
    for (let i = 0; i < dateStr.length; i++) seed += dateStr.charCodeAt(i);
    if (staffId) {
        for (let i = 0; i < staffId.length; i++) seed += staffId.charCodeAt(i);
    }

    baseHours.forEach((slot, index) => {
        const randomVal = ((seed * (index + 1)) % 100) / 100;
        let status = 'Available';
        if (randomVal > 0.8) {
            status = 'Not available';
        } else if (randomVal > 0.6) {
            status = 'Almost full';
        }
        
        slots.push({
            start: slot.start,
            end: slot.end,
            period: slot.period,
            status: status
        });
    });

    return slots;
}

// Local Storage Helper Utilities
function getStoredData(key, fallbackValue) {
    if (typeof window === 'undefined') return fallbackValue;
    const value = localStorage.getItem(`salonku_${key}`);
    return value ? JSON.parse(value) : fallbackValue;
}

function setStoredData(key, value) {
    if (typeof window === 'undefined') return;
    localStorage.setItem(`salonku_${key}`, JSON.stringify(value));
}

function staffProfileSnapshotKey(staffId) {
    return `staff_profile_${String(staffId || '')}`;
}

export function saveStaffProfileSnapshot({ branch, staff, services = [] }) {
    if (!staff?.id) return;

    setStoredData(staffProfileSnapshotKey(staff.id), {
        branch,
        staff,
        services,
    });
}

export function getStaffProfileSnapshot(staffId) {
    try {
        return getStoredData(staffProfileSnapshotKey(staffId), null);
    } catch {
        return null;
    }
}

function getSessionData(key, fallbackValue) {
    if (typeof window === 'undefined') return fallbackValue;

    try {
        const value = sessionStorage.getItem(`salonku_${key}`);
        return value ? JSON.parse(value) : fallbackValue;
    } catch {
        sessionStorage.removeItem(`salonku_${key}`);
        return fallbackValue;
    }
}

function setSessionData(key, value) {
    if (typeof window === 'undefined') return;
    sessionStorage.setItem(`salonku_${key}`, JSON.stringify(value));
}

function migrateLegacyLocalData(key, fallbackValue) {
    const value = getStoredData(key, fallbackValue);
    if (typeof window !== 'undefined') {
        localStorage.removeItem(`salonku_${key}`);
    }
    return value;
}

// Active Cart/Booking Draft Manager
function bookingDraftSessionKey() {
    const session = getSessionUser();
    const identity = session.loggedIn
        ? (session.user?.id ?? session.user?.email ?? 'customer')
        : 'guest';

    return `draft_booking_${String(identity).replace(/[^a-zA-Z0-9_-]/g, '_')}`;
}

export function getBookingDraft() {
    if (typeof window === 'undefined') return null;

    const key = bookingDraftSessionKey();
    const draft = getSessionData(key, null);
    if (draft) return draft;

    const session = getSessionUser();
    if (session.loggedIn) {
        const guestDraftKey = 'draft_booking_guest';
        const guestDraft = getSessionData(guestDraftKey, null);

        if (guestDraft) {
            setSessionData(key, guestDraft);
            sessionStorage.removeItem(`salonku_${guestDraftKey}`);
            return guestDraft;
        }
    }

    const legacyDraft = getStoredData('draft_booking', null);
    localStorage.removeItem('salonku_draft_booking');
    sessionStorage.removeItem('salonku_draft_booking');

    if (legacyDraft && !session.loggedIn) {
        setSessionData(key, legacyDraft);
        return legacyDraft;
    }

    return null;
}

export function saveBookingDraft(draft) {
    if (typeof window !== 'undefined') {
        localStorage.removeItem('salonku_draft_booking');
        sessionStorage.removeItem('salonku_draft_booking');
    }
    setSessionData(bookingDraftSessionKey(), draft);
}

export function clearBookingDraft() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem('salonku_draft_booking');
    sessionStorage.removeItem('salonku_draft_booking');
    sessionStorage.removeItem(`salonku_${bookingDraftSessionKey()}`);
    sessionStorage.removeItem('salonku_draft_booking_guest');
}

// Completed/Active Bookings List Manager
export function getBookingsList() {
    if (!getSessionUser().loggedIn) return [];

    let list = getSessionData('bookings_list', null);
    if (!list) {
        list = migrateLegacyLocalData('bookings_list', []);
        setSessionData('bookings_list', list);
    }
    
    // Sort chronologically (latest first)
    return list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
}

export function saveBookingsList(list) {
    if (typeof window !== 'undefined') {
        localStorage.removeItem('salonku_bookings_list');
    }
    setSessionData('bookings_list', list);
}

export function addBookingToList(booking) {
    const list = getBookingsList();
    list.push({
        ...booking,
        created_at: new Date().toISOString()
    });
    saveBookingsList(list);
}

// User Profiles & Auth Session Manager
export function getUserProfile() {
    const defaultProfile = {
        name: 'Dian Permana',
        email: 'dian.permana@mail.com',
        phone: '081234567890',
        photo: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&h=200&q=80',
        address: 'Jl. Sudirman No. 12, Kel. Senayan, Kec. Kebayoran Baru, Jakarta Selatan',
        gender: 'male',
        birth: '1995-08-17',
        religion: 'Islam',
        allergies: 'None',
        city: 'Jakarta Selatan',
        state: 'DKI Jakarta',
        country: 'Indonesia'
    };
    if (typeof window !== 'undefined') {
        localStorage.removeItem('salonku_user_profile');
    }
    return getSessionData('user_profile', defaultProfile);
}

export function saveUserProfile(profile) {
    if (typeof window !== 'undefined') {
        localStorage.removeItem('salonku_user_profile');
    }
    setSessionData('user_profile', profile);
}

export function getSessionUser() {
    if (typeof window === 'undefined') return { loggedIn: false, user: null };

    localStorage.removeItem('salonku_user_session');

    const value = sessionStorage.getItem('salonku_user_session');
    try {
        const session = value ? JSON.parse(value) : { loggedIn: false, user: null };
        return session?.loggedIn && session.authVersion === 1
            ? session
            : { loggedIn: false, user: null };
    } catch {
        sessionStorage.removeItem('salonku_user_session');
        return { loggedIn: false, user: null };
    }
}

function publicSessionUser(user) {
    if (!user) return null;

    return {
        id: user.id ?? null,
        name: user.name || '',
        email: user.email || '',
        photo: user.photo || '',
    };
}

export function setSessionUser(session) {
    if (typeof window === 'undefined') return;

    localStorage.removeItem('salonku_user_session');
    localStorage.removeItem('salonku_api_token');
    sessionStorage.removeItem('salonku_api_token');

    if (!session?.loggedIn) {
        const nextSession = { loggedIn: false, user: null };
        sessionStorage.setItem('salonku_user_session', JSON.stringify(nextSession));
        window.dispatchEvent(new CustomEvent('salonku-session-change', { detail: nextSession }));
        return;
    }

    const nextSession = {
        loggedIn: true,
        user: publicSessionUser(session.user),
        authVersion: 1,
    };
    sessionStorage.setItem('salonku_user_session', JSON.stringify(nextSession));
    window.dispatchEvent(new CustomEvent('salonku-session-change', { detail: nextSession }));
}

// Favorite Salons Manager
export function getFavoritesList() {
    const list = getSessionData('favorites_list', null);
    if (list) return list;

    const legacyList = migrateLegacyLocalData('favorites_list', []);
    setSessionData('favorites_list', legacyList);
    return legacyList;
}

export function saveFavoritesList(list) {
    if (typeof window !== 'undefined') {
        localStorage.removeItem('salonku_favorites_list');
    }
    setSessionData('favorites_list', list);
}

export function toggleFavoriteSalon(salonId) {
    let list = getFavoritesList();
    if (list.includes(salonId)) {
        list = list.filter(id => id !== salonId);
    } else {
        list.push(salonId);
    }
    saveFavoritesList(list);
    return list;
}

// Reviews Submissions Manager
export function submitSalonReview(bookingCode, rating, comment) {
    const list = getBookingsList();
    const booking = list.find(b => b.code === bookingCode);
    if (booking) {
        booking.reviewed = true;
        booking.rating = rating;
        booking.comment = comment;
        saveBookingsList(list);
        
        // Dynamically update computed salon rating inside our local cache!
        const salon = mockSalons.find(s => s.id === booking.salonId);
        if (salon) {
            salon.reviews += 1;
            // Weighted average mock update
            salon.rating = Number((((salon.rating * (salon.reviews - 1)) + rating) / salon.reviews).toFixed(1));
        }
        return true;
    }
    return false;
}
