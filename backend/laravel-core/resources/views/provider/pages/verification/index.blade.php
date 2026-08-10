@extends('provider.layouts.dashboard')

@section('title', 'Verifikasi Mitra - Provider Dashboard')
@section('page_title', 'Verifikasi Mitra')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/provider-profile.css') }}?v={{ filemtime(public_path('provider/css/provider-profile.css')) }}">
@endpush

@section('content')
@php
    $status = $profile->document_status ?: 'pending';
    $isSubmitted = $status === 'submitted';
    $isRejected = $status === 'rejected';
    $ktpImage = $ktpDocumentUrl ?? null;
    $businessImage = $profile->business_image ? asset('storage/' . $profile->business_image) : null;
    $nibDocument = $nibDocumentUrl ?? null;
    $storedDocumentCount = collect([$profile->ktp_image, $profile->nib_document, $profile->business_image])->filter()->count();
@endphp

<section class="provider-verification-page" data-verification-page>
    <header class="verification-hero">
        <div class="verification-hero-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <div class="verification-hero-copy">
            <span class="verification-eyebrow">LANGKAH WAJIB SEBELUM MEMULAI</span>
            <h1>{{ $isSubmitted ? 'Dokumen sedang diperiksa' : ($isRejected ? 'Perbaiki dokumen verifikasi' : 'Verifikasi usaha Anda') }}</h1>
            <p>
                @if ($isSubmitted)
                    Tim admin sedang memeriksa identitas dan legalitas usaha Anda. Semua menu akan terbuka otomatis setelah disetujui.
                @elseif ($isRejected)
                    Admin menemukan data yang perlu diperbaiki. Ikuti catatan admin, lalu kirim kembali dokumen yang benar.
                @else
                    Unggah tiga dokumen berikut agar akun mitra dapat ditinjau admin. Proses ini menjaga pelanggan dan mitra tetap aman.
                @endif
            </p>
        </div>
        <span class="verification-status is-{{ $status }}">
            {{ match ($status) { 'submitted' => 'Sedang ditinjau', 'rejected' => 'Perlu diperbaiki', default => 'Belum dikirim' } }}
        </span>
    </header>

    @if (session('success'))
        <div class="verification-alert is-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="verification-alert is-error">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="verification-alert is-error">
            <strong>Dokumen belum dapat dikirim.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <ol class="verification-progress" aria-label="Tahapan verifikasi">
        <li class="is-complete"><span>1</span><div><strong>Akun dibuat</strong><small>Pendaftaran berhasil</small></div></li>
        <li class="{{ $isSubmitted ? 'is-complete' : 'is-current' }}"><span>2</span><div><strong>Kirim dokumen</strong><small>KTP, NIB, dan usaha</small></div></li>
        <li class="{{ $isSubmitted ? 'is-current' : '' }}"><span>3</span><div><strong>Review admin</strong><small>Pemeriksaan data</small></div></li>
        <li><span>4</span><div><strong>Mulai kelola bisnis</strong><small>Semua menu terbuka</small></div></li>
    </ol>

    <div class="verification-layout">
        <main class="verification-main-card">
            <div class="verification-card-heading">
                <div>
                    <span>DATA VERIFIKASI</span>
                    <h2>{{ $isSubmitted ? 'Dokumen yang sedang ditinjau' : 'Lengkapi dokumen usaha' }}</h2>
                </div>
                <small>Format JPG, PNG, WEBP, atau PDF untuk NIB</small>
            </div>

            <div
                class="verification-completeness {{ $storedDocumentCount === 3 ? 'is-complete' : '' }}"
                data-verification-completeness
                data-stored-count="{{ $storedDocumentCount }}"
            >
                <span class="verification-completeness-count" data-document-complete-count>{{ $storedDocumentCount }}/3</span>
                <div>
                    <strong data-document-complete-title>
                        {{ $storedDocumentCount === 3 ? 'Semua dokumen sudah tersimpan' : $storedDocumentCount . ' dari 3 dokumen sudah tersimpan' }}
                    </strong>
                    <small data-document-complete-message>
                        @if ($storedDocumentCount === 3)
                            Dokumen tersimpan di akun Anda. Pilih file baru hanya jika ingin menggantinya.
                        @else
                            Masih ada {{ 3 - $storedDocumentCount }} dokumen yang belum diunggah.
                        @endif
                    </small>
                </div>
            </div>

            @if ($isSubmitted)
                <div class="verification-review-grid">
                    <article><span>Foto KTP</span><strong>{{ $profile->ktp_image ? 'Sudah diunggah' : 'Belum ada' }}</strong></article>
                    <article><span>Nomor NIB</span><strong>{{ $profile->nib_number ?: '-' }}</strong></article>
                    <article><span>Dokumen NIB</span><strong>{{ $profile->nib_document ? 'Sudah diunggah' : 'Belum ada' }}</strong></article>
                    <article><span>Foto Usaha</span><strong>{{ $profile->business_image ? 'Sudah diunggah' : 'Belum ada' }}</strong></article>
                </div>
                <div class="verification-waiting-box">
                    <span class="verification-spinner" aria-hidden="true"></span>
                    <div>
                        <strong>Tidak ada tindakan yang diperlukan sekarang</strong>
                        <p>Anda boleh keluar dari halaman ini. Perubahan status akan muncul melalui notifikasi setelah admin selesai memeriksa.</p>
                    </div>
                </div>
            @endif

            @if ($isRejected && $profile->document_note)
                <div class="verification-admin-note">
                    <strong>Catatan dari admin</strong>
                    <p>{{ $profile->document_note }}</p>
                </div>
            @endif

            <form action="{{ provider_route('provider.profile.documents.update') }}" method="POST" enctype="multipart/form-data" class="verification-form {{ $isSubmitted ? 'is-resubmission' : '' }}">
                @csrf

                @if ($isSubmitted)
                    <details class="verification-resubmit">
                        <summary>Dokumen salah? Perbarui dan kirim ulang</summary>
                        <p>Mengirim ulang dokumen akan memperbarui antrean pemeriksaan Anda.</p>
                        <div class="verification-fields">
                @else
                    <div class="verification-fields">
                @endif

                    <div class="verification-field {{ $ktpImage ? 'has-stored-document' : '' }}" data-document-field>
                        <span class="verification-field-number">01</span>
                        <span class="verification-field-copy">
                            <span class="verification-field-title-row">
                                <strong>Foto KTP pemilik</strong>
                                <span class="verification-document-status {{ $ktpImage ? 'is-stored' : 'is-missing' }}" data-document-status>
                                    {{ $ktpImage ? 'Sudah diunggah' : 'Belum diunggah' }}
                                </span>
                            </span>
                            <small>Pastikan nama, NIK, dan foto terbaca jelas.</small>
                        </span>
                        <div class="verification-upload-panel">
                            <label
                                class="verification-upload {{ $ktpImage ? 'has-file' : '' }}"
                                data-document-upload
                                data-has-stored-file="{{ $ktpImage ? 'true' : 'false' }}"
                                data-max-size="4194304"
                            >
                                <input type="file" name="ktp_image" accept="image/jpeg,image/png,image/webp" data-document-input {{ $profile->ktp_image ? '' : 'required' }}>
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 14v5h14v-5"/></svg>
                                <span class="verification-upload-copy">
                                    <strong data-document-action>{{ $ktpImage ? 'Ganti foto KTP' : 'Pilih foto KTP' }}</strong>
                                    <small data-document-file>{{ $ktpImage ? 'File tersimpan di akun' : 'Belum ada file dipilih' }}</small>
                                </span>
                            </label>
                            @if ($ktpImage)
                                <a href="{{ $ktpImage }}" class="verification-file-link" target="_blank" rel="noopener">Lihat foto yang tersimpan</a>
                            @endif
                            <small class="verification-file-feedback" data-document-feedback aria-live="polite"></small>
                        </div>
                    </div>

                    <div class="verification-field verification-field-nib {{ $nibDocument ? 'has-stored-document' : '' }}" data-document-field>
                        <span class="verification-field-number">02</span>
                        <span class="verification-field-copy">
                            <span class="verification-field-title-row">
                                <strong>Nomor & dokumen NIB</strong>
                                <span class="verification-document-status {{ $nibDocument ? 'is-stored' : 'is-missing' }}" data-document-status>
                                    {{ $nibDocument ? 'Sudah diunggah' : 'Belum diunggah' }}
                                </span>
                            </span>
                            <small>Isi nomor NIB sesuai dokumen OSS yang diunggah.</small>
                        </span>
                        <div class="verification-nib-controls">
                            <input type="text" name="nib_number" value="{{ old('nib_number', $profile->nib_number) }}" inputmode="numeric" placeholder="Contoh: 1234567890123" required>
                            <label
                                class="verification-upload {{ $nibDocument ? 'has-file' : '' }}"
                                data-document-upload
                                data-has-stored-file="{{ $nibDocument ? 'true' : 'false' }}"
                                data-max-size="5242880"
                            >
                                <input type="file" name="nib_document" accept="application/pdf,image/jpeg,image/png,image/webp" data-document-input {{ $profile->nib_document ? '' : 'required' }}>
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 14v5h14v-5"/></svg>
                                <span class="verification-upload-copy">
                                    <strong data-document-action>{{ $nibDocument ? 'Ganti dokumen NIB' : 'Pilih dokumen NIB' }}</strong>
                                    <small data-document-file>{{ $nibDocument ? 'File tersimpan di akun' : 'Belum ada file dipilih' }}</small>
                                </span>
                            </label>
                            @if ($nibDocument)
                                <a href="{{ $nibDocument }}" class="verification-file-link" target="_blank" rel="noopener">Lihat dokumen yang tersimpan</a>
                            @endif
                            <small class="verification-file-feedback" data-document-feedback aria-live="polite"></small>
                        </div>
                    </div>

                    <div class="verification-field {{ $businessImage ? 'has-stored-document' : '' }}" data-document-field>
                        <span class="verification-field-number">03</span>
                        <span class="verification-field-copy">
                            <span class="verification-field-title-row">
                                <strong>Foto tempat usaha</strong>
                                <span class="verification-document-status {{ $businessImage ? 'is-stored' : 'is-missing' }}" data-document-status>
                                    {{ $businessImage ? 'Sudah diunggah' : 'Belum diunggah' }}
                                </span>
                            </span>
                            <small>Gunakan foto area usaha yang terang dan terbaru.</small>
                        </span>
                        <div class="verification-upload-panel">
                            <label
                                class="verification-upload {{ $businessImage ? 'has-file' : '' }}"
                                data-document-upload
                                data-has-stored-file="{{ $businessImage ? 'true' : 'false' }}"
                                data-max-size="4194304"
                            >
                                <input type="file" name="business_image" accept="image/jpeg,image/png,image/webp" data-document-input {{ $profile->business_image ? '' : 'required' }}>
                                <svg viewBox="0 0 24 24" fill="none"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 14v5h14v-5"/></svg>
                                <span class="verification-upload-copy">
                                    <strong data-document-action>{{ $businessImage ? 'Ganti foto usaha' : 'Pilih foto usaha' }}</strong>
                                    <small data-document-file>{{ $businessImage ? 'File tersimpan di akun' : 'Belum ada file dipilih' }}</small>
                                </span>
                            </label>
                            @if ($businessImage)
                                <a href="{{ $businessImage }}" class="verification-file-link" target="_blank" rel="noopener">Lihat foto yang tersimpan</a>
                            @endif
                            <small class="verification-file-feedback" data-document-feedback aria-live="polite"></small>
                        </div>
                    </div>

                    <label class="verification-consent">
                        <input type="checkbox" required>
                        <span>Saya memastikan seluruh data benar dan mengizinkan admin memeriksa dokumen untuk verifikasi mitra.</span>
                    </label>

                    <button type="submit" class="verification-submit">
                        <span data-verification-submit-label>{{ $isSubmitted ? 'Kirim ulang dokumen' : 'Kirim untuk diverifikasi' }}</span>
                        <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"/></svg>
                    </button>

                @if ($isSubmitted)
                        </div>
                    </details>
                @else
                    </div>
                @endif
            </form>
        </main>

        <aside class="verification-side-card">
            <h2>Yang perlu diperhatikan</h2>
            <ul>
                <li><span>1</span><p><strong>Data harus sama</strong>Nama di KTP dan data penanggung jawab harus sesuai.</p></li>
                <li><span>2</span><p><strong>Dokumen terbaca</strong>Hindari foto buram, terpotong, atau terkena pantulan cahaya.</p></li>
                <li><span>3</span><p><strong>NIB aktif</strong>Unggah dokumen NIB resmi dari sistem OSS.</p></li>
            </ul>
            <div class="verification-lock-note">
                <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <p><strong>Menu masih dikunci</strong>Anda tetap bisa masuk ke dashboard, mengelola profil, melihat notifikasi, dan keluar dari akun.</p>
            </div>
        </aside>
    </div>

    <script src="{{ asset('provider/js/provider-verification.js') }}?v={{ filemtime(public_path('provider/js/provider-verification.js')) }}"></script>
</section>
@endsection
