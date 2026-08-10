@php
    $showDemoLoginHints = config('app.demo_login_hints') && ! app()->environment('production');
    $adminLoginEmail = $showDemoLoginHints ? config('app.demo_admin_email') : '';
    $adminLoginPassword = $showDemoLoginHints ? config('app.demo_admin_password') : '';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - JasaKu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('admin/css/admin-auth.css') }}">
</head>
<body>

    <main
        class="auth-wrapper"
        @if ($showDemoLoginHints)
            data-admin-email="{{ $adminLoginEmail }}"
            data-admin-password="{{ $adminLoginPassword }}"
        @endif
    >
        <section class="auth-shell" aria-label="Admin sign in">
            <aside class="auth-visual" aria-hidden="true">
                <div class="visual-copy">
                    <h2>About Admin</h2>
                    <p>Monitor daily operations, approve providers, organize services, review bookings, and keep every dashboard workflow under control.</p>

                    <h3>Features</h3>
                    <ul>
                        <li>Manage bookings, schedules, providers, and staff activity.</li>
                        <li>Review services, categories, coupons, tickets, and reports.</li>
                        <li>Track operational updates with clean and secure access.</li>
                    </ul>
                </div>

                <div class="visual-scene">
                    <span class="scene-cloud scene-cloud-one"></span>
                    <span class="scene-cloud scene-cloud-two"></span>
                    <span class="scene-cloud scene-cloud-three"></span>
                    <span class="scene-fence"></span>
                    <span class="scene-house"></span>
                    <span class="scene-tree scene-tree-one"></span>
                    <span class="scene-tree scene-tree-two"></span>
                    <span class="scene-ground"></span>
                </div>
            </aside>

            <section class="auth-panel">
                <div class="auth-card">
                    <div class="auth-kicker">
                        <span>Admin Dashboard</span>
                    </div>

                    <h1>Welcome to admin</h1>
                    <p>Sign in to continue managing the dashboard.</p>

                    @if ($errors->any())
                        <div class="alert-error">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form action="{{ route('admin.login.post') }}" method="POST">
                        @csrf

                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-wrapper">
                                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email">
                                <span class="input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 6h16v12H4z"></path>
                                        <path d="m4 7 8 6 8-6"></path>
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="label-row">
                                <label>Password</label>
                            </div>

                            <div class="input-wrapper">
                                <input type="password" name="password" id="password">
                                <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Show password" aria-pressed="false">
                                    <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 3l18 18"></path>
                                        <path d="M10.6 10.6a3 3 0 0 0 3.8 3.8"></path>
                                        <path d="M7.1 7.5C4.1 9.2 2.5 12 2.5 12s3.5 6 9.5 6c1.8 0 3.3-.4 4.6-1"></path>
                                        <path d="M13.4 6.1c5 .7 8.1 5.9 8.1 5.9a15.8 15.8 0 0 1-2.4 3"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-options">
                            <label>
                                <input type="checkbox" name="remember">
                                <span>Remember me</span>
                            </label>
                            <a href="#">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-login">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 12h14"></path>
                                <path d="m13 6 6 6-6 6"></path>
                            </svg>
                            Sign in
                        </button>
                    </form>

                    @if ($showDemoLoginHints)
                        <div class="login-info" aria-label="Admin login credentials">
                            <span>Admin: <strong>{{ $adminLoginEmail }}</strong></span>
                            <span>Password: <strong>{{ $adminLoginPassword }}</strong></span>
                            <button type="button" class="info-copy-btn" onclick="copyLoginInfo()" aria-label="Copy admin login info">
                                <span id="copyFeedback">Copy</span>
                            </button>
                        </div>
                    @endif
                </div>

                <footer>
                    FAQ | Features | Support
                </footer>
            </section>
        </section>
    </main>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const toggle = document.querySelector('.password-toggle');
            const shouldShow = password.type === 'password';

            password.type = shouldShow ? 'text' : 'password';

            if (toggle) {
                toggle.classList.toggle('showing', shouldShow);
                toggle.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
                toggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
            }
        }

        @if ($showDemoLoginHints)
            function copyLoginInfo() {
                const wrapper = document.querySelector('.auth-wrapper');
                const email = wrapper ? wrapper.dataset.adminEmail : '';
                const password = wrapper ? wrapper.dataset.adminPassword : '';
                const text = 'Email: ' + email + '\nPassword: ' + password;
                const button = document.querySelector('.info-copy-btn');
                const feedback = document.getElementById('copyFeedback');
                const emailInput = document.querySelector('input[name="email"]');
                const passwordInput = document.getElementById('password');

                if (emailInput) {
                    emailInput.value = email;
                }

                if (passwordInput) {
                    passwordInput.value = password;
                }

                function markCopied() {
                    if (!button || !feedback) {
                        return;
                    }

                    button.classList.add('copied');
                    feedback.textContent = 'Filled';

                    window.setTimeout(function () {
                        button.classList.remove('copied');
                        feedback.textContent = 'Copy';
                    }, 1400);
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(markCopied);
                    return;
                }

                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                markCopied();
            }
        @endif
    </script>

</body>
</html>
