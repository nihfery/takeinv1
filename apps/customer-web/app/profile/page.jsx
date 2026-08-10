'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { FreshNavigation, Footer } from '../../src/components/LandingPage.jsx';
import { getUserProfile, saveUserProfile, setSessionUser } from '../../src/lib/mock-state.js';
import { fetchCurrentCustomer, updateCustomerProfile } from '../../src/lib/auth-api.js';
import { Calendar, ClipboardList, Heart, MessageCircle, Pencil, Plus, Settings, Trash2, User, Wallet } from 'lucide-react';

const genderLabels = {
    male: 'Laki-laki',
    female: 'Perempuan',
    other: 'Lainnya',
};

const religionOptions = [
    'Islam',
    'Kristen',
    'Katolik',
    'Hindu',
    'Buddha',
    'Konghucu',
    'Lainnya',
];

function parseAllergies(value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item || '').trim()).filter(Boolean);
    }

    return String(value || '')
        .split(/\r?\n|,/)
        .map((item) => item.trim())
        .filter(Boolean);
}

export default function ProfilePage() {
    const router = useRouter();
    const [activeSection, setActiveSection] = useState('profile');
    const [authChecked, setAuthChecked] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [profileError, setProfileError] = useState('');
    const [profileMessage, setProfileMessage] = useState('');

    // Form Fields
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [city, setCity] = useState('');
    const [state, setState] = useState('');
    const [country, setCountry] = useState('Indonesia');
    const [gender, setGender] = useState('');
    const [birth, setBirth] = useState('');
    const [religion, setReligion] = useState('');
    const [allergyItems, setAllergyItems] = useState([]);
    const [allergyDraft, setAllergyDraft] = useState('');

    // Settings Fields
    const [emailAlerts, setEmailAlerts] = useState(true);
    const [waAlerts, setWaAlerts] = useState(true);
    const [promoNewsletter, setPromoNewsletter] = useState(false);

    const hydrateProfile = (p) => {
        if (!p) return;

        setName(p.name || '');
        setEmail(p.email || '');
        setPhone(p.phone || '');
        setAddress(p.address || '');
        setCity(p.city || '');
        setState(p.state || '');
        setCountry(p.country || 'Indonesia');
        setGender(p.gender || '');
        setBirth(p.birth || '');
        setReligion(p.religion || '');
        setAllergyItems(parseAllergies(p.allergies));
        setAllergyDraft('');
    };

    useEffect(() => {
        let cancelled = false;

        async function verifySession() {
            try {
                const auth = await fetchCurrentCustomer();

                if (cancelled) return;

                saveUserProfile(auth.profile);
                setSessionUser({ loggedIn: true, user: auth.profile });
                setAuthChecked(true);
                const p = auth.profile || getUserProfile();

                hydrateProfile(p);
            } catch {
                setSessionUser({ loggedIn: false, user: null });
                router.replace('/auth?next=/profile');
            }
        }

        verifySession();

        return () => {
            cancelled = true;
        };
    }, [router]);

    useEffect(() => {
        if (!authChecked) return;

        const p = getUserProfile();
        hydrateProfile(p);
    }, [authChecked]);

    const handleProfileSave = async () => {
        setIsSaving(true);
        setProfileError('');
        setProfileMessage('');

        try {
            const auth = await updateCustomerProfile({
                name,
                email,
                phone_number: phone,
                address_line_1: address,
                city,
                state,
                country,
                gender: gender || null,
                date_of_birth: birth || null,
                religion,
                allergies: allergyItems.join('\n'),
            });

            saveUserProfile(auth.profile);
            setSessionUser({ loggedIn: true, user: auth.profile });
            hydrateProfile(auth.profile);
            setProfileMessage(auth.message);
            setIsEditing(false);
        } catch (error) {
            setProfileError(error?.message || 'Profil belum berhasil diperbarui.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleAddAllergy = () => {
        const value = allergyDraft.trim();
        if (!value) return;

        setAllergyItems((items) => [...items, value]);
        setAllergyDraft('');
    };

    const handleRemoveAllergy = (indexToRemove) => {
        setAllergyItems((items) => items.filter((_, index) => index !== indexToRemove));
    };

    const profileMenu = [
        { id: 'profile', label: 'Profile', icon: User },
        { id: 'activity', label: 'Activity', icon: Calendar, href: '/activity' },
        { id: 'wallet', label: 'Wallet', icon: Wallet },
        { id: 'messages', label: 'Messages', icon: MessageCircle },
        { id: 'favorites', label: 'Favorites', icon: Heart, href: '/favorites' },
        { id: 'forms', label: 'Forms', icon: ClipboardList },
        { id: 'settings', label: 'Settings', icon: Settings },
    ];
    const nameParts = name.trim().split(/\s+/).filter(Boolean);
    const firstName = nameParts[0] || '-';
    const lastName = nameParts.slice(1).join(' ') || '-';
    const locationText = address || 'Indonesia';
    const profilePhoto = getUserProfile()?.photo || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&h=200&q=80';
    const customerId = getUserProfile()?.customerId;

    if (!authChecked) {
        return null;
    }

    return (
        <div className="page-shell profile-route-shell">
            <FreshNavigation providerUrl="/provider" customerAppUrl="/" />
            <main className="booking-container">
                <div className="profile-grid">
                    {/* Left Side Navigation Menu */}
                    <div className="profile-sidebar">
                        {/* Navigation Links */}
                        <div className="profile-nav">
                            {profileMenu.map((item) => {
                                const Icon = item.icon;
                                const isActive = activeSection === item.id;

                                return (
                                    <button
                                        type="button"
                                        key={item.id}
                                        onClick={() => item.href ? router.push(item.href) : setActiveSection(item.id)}
                                        className={`profile-nav-btn ${isActive ? 'active' : ''}`}
                                    >
                                        <Icon size={21} />
                                        {item.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Right Side Content Block */}
                    <div className="profile-content">
                        {activeSection === 'profile' && (
                            <div className="profile-settings">
                                <h1 className="profile-page-title">My Profile</h1>
                                {profileError && <div className="profile-alert error">{profileError}</div>}
                                {profileMessage && <div className="profile-alert success">{profileMessage}</div>}

                                <section className="profile-summary-card">
                                    <img src={profilePhoto} alt={name || 'Customer'} className="profile-summary-avatar" />
                                    <div className="profile-summary-copy">
                                        <div className="profile-summary-name-row">
                                            <strong>{name || 'Customer'}</strong>
                                            {customerId && <span className="profile-customer-id">ID: {customerId}</span>}
                                        </div>
                                        <span>Customer</span>
                                        <small>{locationText}</small>
                                    </div>
                                    <div className="profile-action-row">
                                        {isEditing && (
                                            <button
                                                type="button"
                                                className="profile-edit-btn"
                                                onClick={() => {
                                                    hydrateProfile(getUserProfile());
                                                    setProfileError('');
                                                    setProfileMessage('');
                                                    setIsEditing(false);
                                                }}
                                                disabled={isSaving}
                                            >
                                                Cancel
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            className="profile-edit-btn"
                                            onClick={() => isEditing ? handleProfileSave() : setIsEditing(true)}
                                            disabled={isSaving}
                                        >
                                            {isEditing ? (isSaving ? 'Saving...' : 'Save') : 'Edit'}
                                            {!isEditing && <Pencil size={14} />}
                                        </button>
                                    </div>
                                </section>

                                <section className="profile-info-card">
                                    <div className="profile-card-header">
                                        <h2>Personal Information</h2>
                                        <button type="button" className="profile-edit-btn" onClick={() => setIsEditing(true)}>
                                            Edit
                                            <Pencil size={14} />
                                        </button>
                                    </div>
                                    <div className="profile-info-grid">
                                        <div>
                                            <span>Full Name</span>
                                            {isEditing ? (
                                                <input
                                                    className="profile-input"
                                                    value={name}
                                                    onChange={(event) => setName(event.target.value)}
                                                />
                                            ) : (
                                                <strong>{firstName}</strong>
                                            )}
                                        </div>
                                        <div>
                                            <span>Last Name</span>
                                            <strong>{lastName}</strong>
                                        </div>
                                        <div>
                                            <span>Email address</span>
                                            {isEditing ? (
                                                <input
                                                    className="profile-input"
                                                    type="email"
                                                    value={email}
                                                    onChange={(event) => setEmail(event.target.value)}
                                                />
                                            ) : (
                                                <strong>{email || '-'}</strong>
                                            )}
                                        </div>
                                        <div>
                                            <span>Phone</span>
                                            {isEditing ? (
                                                <input
                                                    className="profile-input"
                                                    value={phone}
                                                    onChange={(event) => setPhone(event.target.value)}
                                                />
                                            ) : (
                                                <strong>{phone || '-'}</strong>
                                            )}
                                        </div>
                                        <div>
                                            <span>Agama</span>
                                            {isEditing ? (
                                                <select
                                                    className="profile-input"
                                                    value={religion}
                                                    onChange={(event) => setReligion(event.target.value)}
                                                >
                                                    <option value="">Choose religion</option>
                                                    {religionOptions.map((option) => (
                                                        <option key={option} value={option}>
                                                            {option}
                                                        </option>
                                                    ))}
                                                    {religion && !religionOptions.includes(religion) && (
                                                        <option value={religion}>{religion}</option>
                                                    )}
                                                </select>
                                            ) : (
                                                <strong>{religion || '-'}</strong>
                                            )}
                                        </div>
                                        <div>
                                            <span>Date of Birth</span>
                                            {isEditing ? (
                                                <input
                                                    className="profile-input"
                                                    type="date"
                                                    value={birth || ''}
                                                    onChange={(event) => setBirth(event.target.value)}
                                                />
                                            ) : (
                                                <strong>{birth || '-'}</strong>
                                            )}
                                        </div>
                                        <div className="profile-info-wide">
                                            <span>Alergi</span>
                                            {isEditing ? (
                                                <div className="profile-list-editor">
                                                    <div className="profile-list-input-row">
                                                        <input
                                                            className="profile-input"
                                                            value={allergyDraft}
                                                            onChange={(event) => setAllergyDraft(event.target.value)}
                                                            onKeyDown={(event) => {
                                                                if (event.key === 'Enter') {
                                                                    event.preventDefault();
                                                                    handleAddAllergy();
                                                                }
                                                            }}
                                                            placeholder="Contoh: Alergi pewarna rambut"
                                                        />
                                                        <button
                                                            type="button"
                                                            className="profile-icon-btn"
                                                            onClick={handleAddAllergy}
                                                            aria-label="Tambah alergi"
                                                        >
                                                            <Plus size={16} />
                                                        </button>
                                                    </div>
                                                    {allergyItems.length > 0 ? (
                                                        <ul className="profile-chip-list">
                                                            {allergyItems.map((item, index) => (
                                                                <li key={`${item}-${index}`} className="profile-chip-item">
                                                                    <span>{item}</span>
                                                                    <button
                                                                        type="button"
                                                                        className="profile-chip-remove"
                                                                        onClick={() => handleRemoveAllergy(index)}
                                                                        aria-label={`Hapus ${item}`}
                                                                    >
                                                                        <Trash2 size={14} />
                                                                    </button>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    ) : (
                                                        <small className="profile-list-empty">No allergies recorded yet.</small>
                                                    )}
                                                </div>
                                            ) : (
                                                allergyItems.length > 0 ? (
                                                    <ul className="profile-read-list">
                                                        {allergyItems.map((item, index) => (
                                                            <li key={`${item}-${index}`}>{item}</li>
                                                        ))}
                                                    </ul>
                                                ) : (
                                                    <strong>-</strong>
                                                )
                                            )}
                                        </div>
                                    </div>
                                </section>

                                <section className="profile-info-card">
                                    <div className="profile-card-header">
                                        <h2>Address</h2>
                                        <button type="button" className="profile-edit-btn">
                                            Edit
                                            <Pencil size={14} />
                                        </button>
                                    </div>
                                    <div className="profile-info-grid">
                                        <div>
                                            <span>Country</span>
                                            {isEditing ? (
                                                <input
                                                    className="profile-input"
                                                    value={country}
                                                    onChange={(event) => setCountry(event.target.value)}
                                                />
                                            ) : (
                                                <strong>{country || 'Indonesia'}</strong>
                                            )}
                                        </div>
                                        <div>
                                            <span>City</span>
                                            {isEditing ? (
                                                <input
                                                    className="profile-input"
                                                    value={city}
                                                    onChange={(event) => setCity(event.target.value)}
                                                />
                                            ) : (
                                                <strong>{city || '-'}</strong>
                                            )}
                                        </div>
                                        <div>
                                            <span>State</span>
                                            {isEditing ? (
                                                <input
                                                    className="profile-input"
                                                    value={state}
                                                    onChange={(event) => setState(event.target.value)}
                                                />
                                            ) : (
                                                <strong>{state || '-'}</strong>
                                            )}
                                        </div>
                                        <div>
                                            <span>Gender</span>
                                            {isEditing ? (
                                                <select
                                                    className="profile-input"
                                                    value={gender}
                                                    onChange={(event) => setGender(event.target.value)}
                                                >
                                                    <option value="">Choose gender</option>
                                                    <option value="male">Laki-laki</option>
                                                    <option value="female">Perempuan</option>
                                                    <option value="other">Lainnya</option>
                                                </select>
                                            ) : (
                                                <strong>{genderLabels[gender] || gender || '-'}</strong>
                                            )}
                                        </div>
                                        <div className="profile-info-wide">
                                            <span>Address</span>
                                            {isEditing ? (
                                                <textarea
                                                    className="profile-input profile-textarea"
                                                    value={address}
                                                    onChange={(event) => setAddress(event.target.value)}
                                                    rows={3}
                                                />
                                            ) : (
                                                <strong>{address || '-'}</strong>
                                            )}
                                        </div>
                                    </div>
                                </section>
                            </div>
                        )}

                        {activeSection !== 'profile' && activeSection !== 'settings' && (
                            <div className="profile-empty-panel">
                                <h2 className="profile-page-title">
                                    {profileMenu.find((item) => item.id === activeSection)?.label}
                                </h2>
                                <div className="profile-empty-card">
                                    <Wallet size={28} />
                                    <strong>Section ini sudah disiapkan untuk fitur akun berikutnya.</strong>
                                </div>
                            </div>
                        )}

                        {activeSection === 'settings' && (
                            <div>
                                <h2 className="profile-page-title">Settings</h2>
                                <p className="profile-notif-desc">
                                    Choose the notifications you would like to receive from YouYaku.
                                </p>

                                <div className="profile-notif-group">
                                    <div className="profile-notif-row">
                                        <input 
                                            type="checkbox" 
                                            id="alert-email" 
                                            checked={emailAlerts}
                                            onChange={(e) => setEmailAlerts(e.target.checked)}
                                            className="profile-notif-checkbox"
                                        />
                                        <div>
                                            <label htmlFor="alert-email" className="profile-notif-label">Notifikasi Email</label>
                                            <span className="profile-notif-hint">Dapatkan invoice digital, bukti pembayaran, dan konfirmasi booking via email.</span>
                                        </div>
                                    </div>

                                    <div className="profile-notif-row">
                                        <input 
                                            type="checkbox" 
                                            id="alert-wa" 
                                            checked={waAlerts}
                                            onChange={(e) => setWaAlerts(e.target.checked)}
                                            className="profile-notif-checkbox"
                                        />
                                        <div>
                                            <label htmlFor="alert-wa" className="profile-notif-label">Pengingat WhatsApp (Instan)</label>
                                            <span className="profile-notif-hint">Kirimkan pengingat jadwal otomatis H-1 atau 3 jam sebelum jadwal perawatanmu via WA.</span>
                                        </div>
                                    </div>

                                    <div className="profile-notif-row">
                                        <input 
                                            type="checkbox" 
                                            id="alert-newsletter" 
                                            checked={promoNewsletter}
                                            onChange={(e) => setPromoNewsletter(e.target.checked)}
                                            className="profile-notif-checkbox"
                                        />
                                        <div>
                                            <label htmlFor="alert-newsletter" className="profile-notif-label">Newsletter & Promo Kecantikan</label>
                                            <span className="profile-notif-hint">Terima info voucher potongan harga mingguan dan rekomendasi salon kecantikan terdekat.</span>
                                        </div>
                                    </div>
                                </div>

                                <button 
                                    type="button" 
                                    className="booking-action-btn profile-save-btn"
                                    onClick={() => {
                                        alert('Pengaturan notifikasi berhasil diperbarui!');
                                    }}
                                >
                                    Save settings
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </main>
            <Footer />
        </div>
    );
}
