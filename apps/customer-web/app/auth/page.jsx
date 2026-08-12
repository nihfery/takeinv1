'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Eye, EyeOff, Sparkles } from 'lucide-react';
import { loginCustomer, registerCustomer } from '../../src/lib/auth-api.js';
import { saveUserProfile, setSessionUser } from '../../src/lib/mock-state.js';

export default function AuthPage() {
    const router = useRouter();
    const [loading, setLoading] = useState(false);
    const [isRegistering, setIsRegistering] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [name, setName] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        const syncModeFromUrl = () => {
            const registering = new URLSearchParams(window.location.search).get('mode') === 'register';
            setIsRegistering(registering);
        };

        syncModeFromUrl();
        window.addEventListener('popstate', syncModeFromUrl);
        return () => window.removeEventListener('popstate', syncModeFromUrl);
    }, []);

    useEffect(() => {
        const previousHtmlOverflow = document.documentElement.style.overflow;
        const previousBodyOverflow = document.body.style.overflow;
        const previousBodyHeight = document.body.style.height;

        document.documentElement.style.overflow = isRegistering ? 'auto' : 'hidden';
        document.body.style.overflow = isRegistering ? 'auto' : 'hidden';
        document.body.style.height = isRegistering ? 'auto' : '100dvh';

        return () => {
            document.documentElement.style.overflow = previousHtmlOverflow;
            document.body.style.overflow = previousBodyOverflow;
            document.body.style.height = previousBodyHeight;
        };
    }, [isRegistering]);

    async function redirectAfterAuthentication() {
        const params = new URLSearchParams(window.location.search);
        const next = params.get('next') || '/';
        const nextUrl = next.startsWith('/') && !next.startsWith('//') ? next : '/';
        router.push(nextUrl);
        router.refresh();
    }

    function switchMode(event) {
        event.preventDefault();
        const nextIsRegistering = !isRegistering;
        const params = new URLSearchParams(window.location.search);

        if (nextIsRegistering) {
            params.set('mode', 'register');
        } else {
            params.delete('mode');
        }

        setError('');
        setShowPassword(false);
        setShowConfirmation(false);
        setIsRegistering(nextIsRegistering);
        router.replace(`/auth${params.size ? `?${params.toString()}` : ''}`, { scroll: false });
    }

    async function handleAuthentication(event) {
        event.preventDefault();
        setError('');

        if (isRegistering && password.length < 8) {
            setError('Password minimal terdiri dari 8 karakter.');
            return;
        }

        if (isRegistering && password !== passwordConfirmation) {
            setError('Password confirmation must match.');
            return;
        }

        setLoading(true);

        try {
            const auth = isRegistering
                ? await registerCustomer({
                    name: name.trim(),
                    email: email.trim(),
                    password,
                    passwordConfirmation,
                })
                : await loginCustomer({
                    email: email.trim(),
                    password,
                    remember,
                });

            setSessionUser({ loggedIn: true, user: auth.profile });
            saveUserProfile(auth.profile);
            await redirectAfterAuthentication();
        } catch (authError) {
            setError(authError?.message || (isRegistering ? 'Pendaftaran gagal. Coba lagi.' : 'Login gagal. Periksa email dan password.'));
            setLoading(false);
        }
    }

    return (
        <main className={`auth-page${isRegistering ? ' is-registering' : ''}`}>
            <section className="auth-visual" aria-label="YouYaku visual artwork">
                <img src="/images/auth-login-art.png" alt="" />
            </section>

            <section className="auth-panel" aria-labelledby="auth-title">
                <a className="auth-brand" href="/" aria-label="YouYaku home">
                    <span><Sparkles size={14} /></span>
                    YouYaku
                </a>

                <div className="auth-heading">
                    <h1 id="auth-title">{isRegistering ? 'Buat akun customer' : 'Selamat datang kembali'}</h1>
                    <p>{isRegistering ? 'Create an account to book your favourite services. You can complete your profile later.' : 'Sign in to manage your bookings and profile.'}</p>
                </div>

                <form className="auth-form" onSubmit={handleAuthentication} noValidate>
                    {isRegistering && <>
                        <label className="auth-field">
                            <span>Full name <em>*</em></span>
                            <input type="text" value={name} autoComplete="name" onChange={(event) => setName(event.target.value)} required disabled={loading} />
                        </label>

                    </>}

                    <label className="auth-field">
                        <span>Email <em>*</em></span>
                        <input type="email" value={email} autoComplete="email" onChange={(event) => setEmail(event.target.value)} required disabled={loading} />
                    </label>

                    <label className="auth-field">
                        <span>Password <em>*</em></span>
                        <div className="auth-password">
                            <input type={showPassword ? 'text' : 'password'} value={password} autoComplete={isRegistering ? 'new-password' : 'current-password'} minLength={isRegistering ? 8 : undefined} onChange={(event) => setPassword(event.target.value)} required disabled={loading} />
                            <button type="button" aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'} onClick={() => setShowPassword((value) => !value)} disabled={loading}>
                                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                            </button>
                        </div>
                        {isRegistering && <small className="auth-help">Minimal 8 karakter.</small>}
                    </label>

                    {isRegistering && <>
                        <label className="auth-field">
                            <span>Confirm password <em>*</em></span>
                            <div className="auth-password">
                                <input type={showConfirmation ? 'text' : 'password'} value={passwordConfirmation} autoComplete="new-password" minLength={8} onChange={(event) => setPasswordConfirmation(event.target.value)} required disabled={loading} />
                                <button type="button" aria-label={showConfirmation ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'} onClick={() => setShowConfirmation((value) => !value)} disabled={loading}>
                                    {showConfirmation ? <EyeOff size={20} /> : <Eye size={20} />}
                                </button>
                            </div>
                        </label>

                    </>}

                    {!isRegistering && (
                        <label className="auth-options">
                            <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} disabled={loading} />
                            <span>Ingat saya di perangkat ini</span>
                        </label>
                    )}

                    {error && <p className="auth-error" role="alert">{error}</p>}

                    <button className="auth-submit" type="submit" disabled={loading}>
                        {loading ? (isRegistering ? 'Creating account...' : 'Signing in...') : (isRegistering ? 'Create account' : 'Sign in')}
                    </button>
                </form>

                <p className="auth-signup">
                    {isRegistering ? 'Already have an account? ' : "Don't have an account? "}
                    <a href={isRegistering ? '/auth' : '/auth?mode=register'} onClick={switchMode}>{isRegistering ? 'Sign in' : 'Create one now'}</a>
                </p>
            </section>
        </main>
    );
}
