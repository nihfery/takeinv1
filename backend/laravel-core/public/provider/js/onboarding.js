document.addEventListener('DOMContentLoaded', () => {
    const config = window.ProviderOnboarding || null;
    const getDriverFactory = () => {
        if (typeof window.driver?.js?.driver === 'function') return window.driver.js.driver;
        if (typeof window.driver?.driver === 'function') return window.driver.driver;
        return null;
    };

    if (!config || config.enabled === false || !getDriverFactory()) return;

    const replayStorageKey = 'providerTutorialCurrentStep';
    const modeStorageKey = 'providerTutorialMode';
    const pausedStorageKey = 'providerTutorialPaused';
    const completedGuidesStorageKey = 'providerTutorialCompletedGuides';
    const routes = config.routes || {};
    const savedReplayStep = window.sessionStorage.getItem(replayStorageKey);
    const savedMode = window.sessionStorage.getItem(modeStorageKey) || 'menu';
    const persistedTutorialIsActive = !['completed', 'skipped'].includes(config.status);

    let welcomeModal = null;
    let driverInstance = null;
    let shouldPersistProgress = persistedTutorialIsActive;
    let isTransitioning = false;
    let activeMode = 'menu';
    let activeSteps = [];
    let practiceBanner = null;
    let practiceSafeAreaCleanup = null;
    let practiceScopeSelector = '';
    let lockedWindowScroll = { x: 0, y: 0 };

    const richDescription = (lead, items = [], tip = '') => `
        <div class="provider-tour-copy">
            <p>${lead}</p>
            ${items.length ? `<ul>${items.map((item) => `<li>${item}</li>`).join('')}</ul>` : ''}
            ${tip ? `<div class="provider-tour-tip"><strong>Tips</strong>${tip}</div>` : ''}
        </div>
    `;

    const menuTourSteps = [
        {
            id: 'step_overview',
            route: routes.dashboard,
            element: '[data-menu-group="overview"]',
            popover: {
                title: 'Mulai dari Overview',
                description: richDescription(
                    'Menu utama di bagian atas membagi aplikasi berdasarkan pekerjaan. <strong>Overview</strong> adalah tempat Anda melihat kondisi bisnis secara cepat.',
                    ['Ringkasan booking dan pendapatan.', 'Aktivitas operasional hari ini.', 'Akses cepat ke pekerjaan yang paling sering dilakukan.'],
                    'Jika bingung harus mulai dari mana, kembali ke Overview.'
                ),
                side: 'bottom',
                align: 'start',
            },
        },
        {
            id: 'step_dashboard',
            route: routes.dashboard,
            element: '[data-menu-key="dashboard"]',
            popover: {
                title: 'Dashboard bisnis Anda',
                description: richDescription(
                    'Ikon pada sidebar kiri adalah submenu dari kelompok yang sedang aktif. Saat Overview aktif, ikon ini membuka Dashboard.',
                    ['Periksa booking hari ini.', 'Pantau performa bisnis.', 'Lihat hal yang perlu segera ditangani.']
                ),
                side: 'right',
                align: 'start',
            },
        },
        {
            id: 'step_business',
            route: routes.dashboard,
            element: '[data-menu-group="business"]',
            popover: {
                title: '1. Siapkan bisnis',
                description: richDescription(
                    'Buka <strong>Business</strong> untuk menyiapkan semua hal yang dibutuhkan sebelum menerima booking.',
                    ['Layanan dan harga.', 'Tim, keahlian, serta jadwal kerja.', 'Lokasi atau cabang usaha.'],
                    'Kerjakan bagian ini terlebih dahulu agar slot booking pelanggan akurat.'
                ),
                side: 'bottom',
                align: 'center',
            },
        },
        {
            id: 'step_services',
            route: routes.services,
            element: '[data-menu-key="services"]',
            popover: {
                title: 'Buat layanan',
                description: richDescription(
                    'Masukkan layanan yang dapat dipesan pelanggan. Informasi di sini akan tampil pada halaman booking.',
                    ['Gunakan nama layanan yang mudah dipahami.', 'Isi harga dan durasi dengan tepat.', 'Aktifkan layanan yang sudah siap dijual.'],
                    'Durasi layanan dipakai sistem untuk menghitung slot kalender.'
                ),
                side: 'right',
                align: 'start',
            },
        },
        {
            id: 'step_branch',
            route: routes.services,
            element: '[data-menu-key="branch"]',
            popover: {
                title: 'Tambahkan lokasi',
                description: richDescription(
                    'Menu <strong>Locations</strong> menyimpan cabang dan alamat tempat pelanggan menerima layanan.',
                    ['Lengkapi alamat dan jam operasional.', 'Pastikan cabang berstatus aktif.', 'Hubungkan layanan dan staf ke lokasi yang benar.']
                ),
                side: 'right',
                align: 'end',
            },
        },
        {
            id: 'step_staff',
            route: routes.services,
            element: '[data-menu-key="staffs"]',
            popover: {
                title: 'Susun tim',
                description: richDescription(
                    'Tambahkan orang yang akan menjalankan layanan melalui menu <strong>Team</strong>.',
                    ['Tentukan cabang tempat staf bekerja.', 'Lengkapi profil agar mudah dikenali.', 'Nonaktifkan staf yang sedang tidak menerima booking.']
                ),
                side: 'right',
                align: 'center',
            },
        },
        {
            id: 'step_setup',
            route: routes.services,
            element: '.sidebar-main-nav',
            popover: {
                title: 'Hubungkan keahlian dan jadwal',
                description: richDescription(
                    'Selesaikan pengaturan tim melalui dua submenu berikut:',
                    ['<strong>Skills:</strong> pilih layanan yang bisa dikerjakan setiap staf.', '<strong>Work schedules:</strong> atur hari dan jam kerja staf.'],
                    'Layanan hanya dapat dipesan jika staf, skill, lokasi, dan jadwalnya saling sesuai.'
                ),
                side: 'right',
                align: 'center',
            },
        },
        {
            id: 'step_appointments',
            route: routes.services,
            element: '[data-menu-group="appointments"]',
            popover: {
                title: '2. Jalankan operasional',
                description: richDescription(
                    'Setelah bisnis siap, gunakan <strong>Appointments</strong> untuk menangani pesanan sehari-hari.',
                    ['Periksa pesanan yang baru masuk.', 'Lihat jadwal melalui kalender.', 'Kelola antrean dan pelanggan walk-in.']
                ),
                side: 'bottom',
                align: 'center',
            },
        },
        {
            id: 'step_bookings',
            route: routes.bookings,
            element: '[data-menu-key="bookings"]',
            popover: {
                title: 'Kelola semua booking',
                description: richDescription(
                    '<strong>Bookings</strong> adalah daftar lengkap pesanan pelanggan dan pusat perubahan status appointment.',
                    ['Konfirmasi dan check-in pelanggan.', 'Mulai atau selesaikan layanan.', 'Cari booking berdasarkan tanggal, status, atau pembayaran.']
                ),
                side: 'right',
                align: 'start',
            },
        },
        {
            id: 'step_calendar',
            route: routes.bookings,
            element: '[data-menu-key="calendar"]',
            popover: {
                title: 'Baca jadwal dari Calendar',
                description: richDescription(
                    'Calendar menampilkan appointment dalam tampilan bulanan agar jadwal lebih mudah dipahami.',
                    ['Maksimal tiga pesanan tampil pada setiap tanggal.', 'Klik tanggal untuk membuka detail lengkap.', 'Gunakan “+ more” jika pesanan pada hari itu banyak.'],
                    'Periksa kalender sebelum menerima pesanan manual agar tidak terjadi jadwal bertabrakan.'
                ),
                side: 'right',
                align: 'center',
            },
        },
        {
            id: 'step_queue',
            route: routes.bookings,
            element: '[data-menu-key="queue"]',
            popover: {
                title: 'Antrean dan pelanggan walk-in',
                description: richDescription(
                    'Gunakan <strong>Queue</strong> untuk memanggil dan memantau antrean aktif. Ikon berikutnya, <strong>Walk-in</strong>, digunakan untuk membuat booking pelanggan yang datang langsung.',
                    ['Tambahkan walk-in dari meja depan.', 'Panggil pelanggan sesuai nomor antrean.', 'Perbarui status agar tim melihat kondisi terbaru.']
                ),
                side: 'right',
                align: 'end',
            },
        },
        {
            id: 'step_customers',
            route: routes.bookings,
            element: '[data-menu-group="customers"]',
            popover: {
                title: 'Kenali pelanggan',
                description: richDescription(
                    'Kelompok <strong>Customers</strong> menyimpan direktori pelanggan, riwayat kunjungan, serta ulasan.',
                    ['Gunakan riwayat untuk memahami pelanggan tetap.', 'Baca review sebagai masukan kualitas layanan.']
                ),
                side: 'bottom',
                align: 'center',
            },
        },
        {
            id: 'step_finance',
            route: routes.bookings,
            element: '[data-menu-group="finance"]',
            popover: {
                title: 'Pantau transaksi',
                description: richDescription(
                    'Kelompok <strong>Finance</strong> membantu Anda memeriksa pembayaran, status transaksi, dan invoice booking.',
                    ['Cocokkan pembayaran dengan booking.', 'Periksa transaksi pending atau gagal.', 'Gunakan data ini untuk rekonsiliasi operasional.']
                ),
                side: 'bottom',
                align: 'end',
            },
        },
        {
            id: 'step_help',
            route: routes.bookings,
            element: '[data-provider-help-menu]',
            popover: {
                title: 'Bantuan selalu tersedia',
                description: richDescription(
                    'Gunakan <strong>Help center</strong> jika menemui kendala. Tutorial ini juga bisa dibuka ulang kapan saja dari menu profil di kanan atas.',
                    ['Baca pertanyaan yang sering diajukan.', 'Kirim tiket bantuan jika masalah belum selesai.'],
                    'Anda tidak perlu menghafal semuanya sekarang—ikuti urutan kerja yang baru saja ditunjukkan.'
                ),
                side: 'right',
                align: 'end',
            },
        },
        {
            id: 'step_finish',
            popover: {
                title: 'Siap menjalankan YouYaku 🎉',
                description: richDescription(
                    'Urutan paling aman untuk memulai adalah:',
                    ['<strong>Siapkan:</strong> lokasi → layanan → tim → skill → jadwal.', '<strong>Operasikan:</strong> bookings → calendar → queue.', '<strong>Pantau:</strong> customers → finance → dashboard.'],
                    'Mulai dengan membuat satu layanan percobaan, lalu cek hasilnya dari Calendar.'
                ),
                side: 'over',
                align: 'center',
            },
        },
    ];

    const setupGuideSteps = [
        {
            id: 'setup_branch_add',
            route: routes.branches,
            element: '[data-setup-add-branch]',
            popover: {
                title: '1. Buat lokasi usaha',
                description: richDescription(
                    'Mulai dengan membuat <strong>Location</strong>. Layanan dan staf membutuhkan lokasi agar sistem tahu tempat mereka tersedia.',
                    ['Klik <strong>Add Branch</strong> untuk membuka form.', 'Satu lokasi aktif sudah cukup untuk memulai.', 'Tambahkan cabang lain nanti jika bisnis berkembang.'],
                    'Panduan akan membuka form pada langkah berikutnya; data tidak akan disimpan otomatis.'
                ),
                nextBtnText: 'Lihat form',
                side: 'bottom',
                align: 'end',
            },
        },
        {
            id: 'setup_branch_basic',
            route: routes.branchCreate,
            element: '[data-setup-branch-basic]',
            popover: {
                title: 'Isi identitas lokasi',
                description: richDescription(
                    'Bagian pertama berisi informasi yang digunakan untuk mengenali dan menghubungi cabang.',
                    ['<strong>Wajib:</strong> nama cabang, email, kode negara, dan nomor telepon.', 'Gunakan email serta nomor yang aktif.', 'Nama cabang akan tampil pada pilihan lokasi pelanggan.']
                ),
                side: 'right',
                align: 'start',
            },
        },
        {
            id: 'setup_branch_location',
            route: routes.branchCreate,
            element: '[data-setup-branch-location]',
            popover: {
                title: 'Tentukan alamat yang akurat',
                description: richDescription(
                    'Lengkapi alamat, negara, provinsi, kota, dan kode pos. Pin peta membantu pelanggan menemukan lokasi.',
                    ['Gunakan <strong>Use My Current Position</strong> jika berada di tempat usaha.', 'Periksa kembali titik peta sebelum menyimpan.', 'Alamat inilah yang akan dilihat pelanggan.']
                ),
                side: 'left',
                align: 'start',
            },
        },
        {
            id: 'setup_branch_schedule',
            route: routes.branchCreate,
            element: '[data-setup-branch-schedule]',
            popover: {
                title: 'Atur jam operasional',
                description: richDescription(
                    'Tentukan jam buka, jam tutup, hari kerja, dan tanggal libur untuk cabang ini.',
                    ['Jam staf nantinya sebaiknya berada di dalam jam cabang.', 'Centang hanya hari ketika cabang menerima pelanggan.', 'Tambahkan hari libur agar slot tidak ditawarkan pada tanggal tersebut.']
                ),
                side: 'right',
                align: 'center',
            },
        },
        {
            id: 'setup_branch_save',
            route: routes.branchCreate,
            element: '[data-setup-branch-save]',
            practice: {
                title: 'Lengkapi dan simpan Branch',
                message: 'Panduan dijeda. Isi seluruh kolom wajib, periksa jadwal dan foto, lalu tekan Save Branch. Penempatan staff dilakukan nanti dari form Add Staff.',
                scope: '.provider-branch-editor-form',
                start: '[data-setup-branch-basic]',
            },
            popover: {
                title: 'Simpan Branch',
                description: richDescription(
                    'Branch disimpan langsung dalam satu tahap. Anda tidak perlu memilih staff dari halaman ini.',
                    ['Periksa identitas, alamat, jam operasional, hari libur, dan foto.', 'Tekan <strong>Save Branch</strong> untuk menyimpan Location.', 'Saat membuat staff nanti, pilih Branch pada kolom <strong>Work Location</strong>.']
                ),
                nextBtnText: 'Isi & simpan',
                side: 'top',
                align: 'end',
            },
        },
        {
            id: 'setup_branch_manage',
            route: routes.branches,
            element: '[data-setup-branch-list]',
            popover: {
                title: 'Kelola lokasi yang sudah dibuat',
                description: richDescription(
                    'Semua location muncul pada daftar ini. Kolom Action menyediakan tombol edit dan hapus/nonaktifkan.',
                    ['<strong>Edit:</strong> perbarui alamat, jam, atau foto.', '<strong>Status:</strong> hentikan sementara penerimaan booking.', '<strong>Staff:</strong> ubah Work Location melalui Edit Staff agar penempatannya tetap konsisten.']
                ),
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'setup_service_add',
            route: routes.services,
            element: '[data-setup-add-service]',
            popover: {
                title: '2. Tambahkan layanan',
                description: richDescription(
                    'Setelah lokasi tersedia, buat layanan yang bisa dipilih pelanggan.',
                    ['Klik <strong>Add Service</strong>.', 'Siapkan nama, kategori, harga, dan durasi.', 'Satu layanan percobaan sudah cukup untuk menguji alur booking.']
                ),
                nextBtnText: 'Lihat form',
                side: 'bottom',
                align: 'end',
            },
        },
        {
            id: 'setup_service_basic',
            route: routes.serviceCreate,
            element: '[data-setup-service-basic]',
            popover: {
                title: 'Isi informasi layanan',
                description: richDescription(
                    'Berikan informasi yang membuat pelanggan langsung memahami layanan Anda.',
                    ['<strong>Wajib:</strong> Service Name dan Category.', 'Description menjelaskan manfaat atau proses layanan.', 'Includes memberitahu pelanggan apa saja yang sudah termasuk.'],
                    'Gunakan nama spesifik, misalnya “Hair Spa 60 Menit”, bukan hanya “Perawatan”.'
                ),
                side: 'right',
                align: 'start',
            },
        },
        {
            id: 'setup_service_pricing',
            route: routes.serviceCreate,
            element: '[data-setup-service-pricing]',
            popover: {
                title: 'Tentukan harga',
                description: richDescription(
                    'Pilih jenis harga lalu isi nominal yang akan dilihat pelanggan.',
                    ['<strong>Fixed:</strong> satu harga tetap untuk layanan.', '<strong>Hourly:</strong> harga dihitung per jam.', 'Pastikan nominal sudah mencakup komponen yang ditulis pada Includes.']
                ),
                side: 'right',
                align: 'center',
            },
        },
        {
            id: 'setup_service_slots',
            route: routes.serviceCreate,
            element: '[data-setup-service-slots]',
            popover: {
                title: 'Atur ketersediaan layanan',
                description: richDescription(
                    'Centang hari layanan tersedia dan tambahkan rentang waktu jika layanan memiliki slot khusus.',
                    ['Tombol <strong>+</strong> menambah rentang waktu.', 'Hindari slot di luar jam operasional cabang.', 'Additional service dan holiday bersifat opsional.'],
                    'Ketersediaan akhir tetap mengikuti kecocokan location, skill, dan jadwal staf.'
                ),
                side: 'right',
                align: 'center',
            },
        },
        {
            id: 'setup_service_continue',
            route: routes.serviceCreate,
            element: '[data-setup-service-continue]',
            practice: {
                title: 'Lengkapi dan simpan Service',
                message: 'Panduan dijeda. Selesaikan Service Information, pilih Location pada Branch Information, tambahkan Gallery, lalu tekan Save.',
                scope: '.provider-service-create-page form',
                start: '[data-setup-service-basic]',
            },
            popover: {
                title: 'Selesaikan tiga tahap layanan',
                description: richDescription(
                    'Tekan <strong>Continue</strong>, lalu pilih branch tempat layanan tersedia dan tambahkan foto pada tahap Gallery.',
                    ['Service Information → isi detail dan harga.', 'Branch Information → pilih location.', 'Gallery → unggah foto lalu Save.'],
                    'Layanan baru belum lengkap sebelum ketiga tahap selesai.'
                ),
                nextBtnText: 'Isi & simpan',
                side: 'top',
                align: 'end',
            },
        },
        {
            id: 'setup_service_manage',
            route: routes.services,
            element: '[data-setup-service-list]',
            popover: {
                title: 'Edit, aktifkan, atau hapus layanan',
                description: richDescription(
                    'Daftar layanan memperlihatkan harga, status, cabang, serta mode booking.',
                    ['Gunakan ikon pensil untuk mengedit.', 'Gunakan toggle status untuk menampilkan/menyembunyikan layanan.', 'Ikon hapus hanya tersedia jika layanan aman untuk dihapus.']
                ),
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'setup_staff_add',
            route: routes.staffs,
            element: '[data-setup-add-staff]',
            popover: {
                title: '3. Tambahkan staf',
                description: richDescription(
                    'Sekarang buat profil orang yang akan mengerjakan layanan.',
                    ['Klik <strong>Add Staff</strong>.', 'Setiap staf harus dihubungkan ke location.', 'Setelah staf dibuat, lanjutkan ke Skills lalu Work schedules.']
                ),
                nextBtnText: 'Buka form staf',
                side: 'bottom',
                align: 'end',
            },
        },
        {
            id: 'setup_staff_form',
            route: routes.staffs,
            element: '[data-setup-staff-form]',
            prepare: 'openStaffModal',
            popover: {
                title: 'Lengkapi profil staf',
                description: richDescription(
                    'Isi identitas staf dan pilih tempat ia bekerja.',
                    ['<strong>Wajib:</strong> nama depan, nama belakang, email, Category, dan Branch.', 'Foto, telepon, alamat, dan bio membantu profil lebih lengkap.', 'Gunakan status Active agar staf dapat menerima assignment.'],
                    'Category bukan Skills. Layanan yang bisa dikerjakan dipilih pada langkah berikutnya.'
                ),
                side: 'left',
                align: 'start',
            },
        },
        {
            id: 'setup_staff_save',
            route: routes.staffs,
            element: '[data-setup-staff-save]',
            prepare: 'openStaffModal',
            practice: {
                title: 'Lengkapi dan simpan Staff',
                message: 'Panduan dijeda. Isi semua kolom wajib pada form Staff, periksa Location dan statusnya, lalu tekan Save.',
                scope: '#staffForm',
                start: '#staffForm',
            },
            popover: {
                title: 'Simpan staf',
                description: richDescription(
                    'Tekan <strong>Save</strong> setelah kolom wajib lengkap. Staf yang tersimpan akan muncul pada daftar Team.',
                    ['Jika email sudah digunakan, pilih email lain.', 'Pastikan Branch sesuai lokasi kerja.', 'Profil masih perlu Skills dan Work schedules sebelum siap menerima booking.']
                ),
                nextBtnText: 'Isi & simpan',
                side: 'top',
                align: 'end',
            },
        },
        {
            id: 'setup_staff_manage',
            route: routes.staffs,
            element: '[data-setup-staff-list]',
            prepare: 'closeStaffModal',
            popover: {
                title: 'Kelola staf dari daftar Team',
                description: richDescription(
                    'Di sini Anda dapat mencari, mengedit, mengubah status, atau menghapus staf.',
                    ['Edit jika profil atau penempatan cabang berubah.', 'Nonaktifkan staf yang sedang tidak menerima pelanggan.', 'Jangan lupa memperbarui Skills dan Schedule setelah perubahan.']
                ),
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'setup_skills',
            route: routes.skills,
            element: '[data-setup-skill-list]',
            practice: {
                title: 'Pilih dan simpan Skills',
                message: 'Panduan dijeda. Cari Staff yang baru dibuat, pilih layanan yang dapat dikerjakan, lalu tekan ikon Save pada baris tersebut.',
                scope: '[data-setup-skill-list], .provider-staff-skill-mobile-list',
                start: '[data-setup-skill-list]',
            },
            popover: {
                title: '4. Hubungkan staf dengan layanan',
                description: richDescription(
                    'Pada menu <strong>Skills</strong>, cari staf yang baru dibuat lalu centang layanan yang dapat ia kerjakan.',
                    ['Centang satu atau beberapa layanan.', 'Select all memilih seluruh layanan aktif.', 'Tekan ikon Save pada baris staf.'],
                    'Tanpa skill, staf tidak akan ditawarkan untuk layanan tersebut pada proses booking.'
                ),
                nextBtnText: 'Atur skills',
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'setup_schedules',
            route: routes.schedules,
            element: '[data-setup-schedule-list]',
            practice: {
                title: 'Atur dan simpan Schedule',
                message: 'Panduan dijeda. Pilih hari dan jam kerja Staff yang sama, lalu tekan ikon Save. Setelah tersimpan, panduan berlanjut ke Calendar.',
                scope: '[data-setup-schedule-list], .provider-staff-schedule-mobile-list',
                start: '[data-setup-schedule-list]',
            },
            popover: {
                title: '5. Atur jadwal kerja staf',
                description: richDescription(
                    'Cari staf yang sama, pilih hari kerja, lalu isi jam mulai dan selesai.',
                    ['Gunakan preset Weekdays, Weekend, atau All jika sesuai.', 'Jam kerja harus berada di dalam jam operasional location.', 'Tekan Save pada baris staf setelah selesai.'],
                    'Slot pelanggan dihitung dari gabungan jadwal staf, layanan, location, dan booking yang sudah ada.'
                ),
                nextBtnText: 'Atur jadwal',
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'setup_calendar_check',
            route: routes.calendar,
            element: '.provider-month-calendar-card',
            popover: {
                title: '6. Periksa hasilnya di Calendar',
                description: richDescription(
                    'Setup dasar selesai. Calendar akan menampilkan pesanan pada tanggalnya setelah pelanggan mulai booking.',
                    ['Klik tanggal untuk melihat detail appointment.', 'Pastikan layanan, staf, skill, dan jadwal sudah aktif.', 'Gunakan Walk-in untuk mencoba alur pesanan dari meja depan.']
                ),
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'setup_finish',
            popover: {
                title: 'Bisnis siap menerima booking 🎉',
                description: richDescription(
                    'Checklist setup yang perlu Anda pastikan:',
                    ['Minimal satu location aktif.', 'Minimal satu layanan aktif dan terhubung ke location.', 'Staf aktif memiliki skill serta jadwal kerja.', 'Kalender sudah diperiksa untuk menghindari benturan.'],
                    'Anda bisa membuka Panduan aplikasi lagi dari menu profil kapan saja.'
                ),
                side: 'over',
                align: 'center',
            },
        },
    ];

    const legacyStepAliases = {
        step_skills: 'step_setup',
        step_schedules: 'step_setup',
    };

    const guideDefinitions = {
        branch: {
            title: 'Membuat Branch',
            description: 'Identitas lokasi, alamat, jam operasional, foto, dan penyimpanan Branch.',
            start: 'setup_branch_add',
            end: 'setup_branch_manage',
            estimate: '3–4 menit',
        },
        service: {
            title: 'Membuat Service',
            description: 'Informasi layanan, harga, slot, pemilihan Branch, dan Gallery.',
            start: 'setup_service_add',
            end: 'setup_service_manage',
            estimate: '4–5 menit',
            requires: 'branch',
        },
        staff: {
            title: 'Menambahkan Staff',
            description: 'Profil staff, kategori, status, serta Work Location tempat staff bekerja.',
            start: 'setup_staff_add',
            end: 'setup_staff_manage',
            estimate: '2–3 menit',
            requires: 'branch',
        },
        skills: {
            title: 'Mengatur Skills',
            description: 'Hubungkan staff dengan service yang dapat dikerjakannya.',
            start: 'setup_skills',
            end: 'setup_skills',
            estimate: '1–2 menit',
            requires: 'staff',
        },
        schedules: {
            title: 'Mengatur Work Schedule',
            description: 'Tentukan hari dan jam kerja setiap staff.',
            start: 'setup_schedules',
            end: 'setup_schedules',
            estimate: '1–2 menit',
            requires: 'staff',
        },
        calendar: {
            title: 'Memahami Calendar',
            description: 'Periksa jadwal dan buka daftar appointment pada setiap tanggal.',
            start: 'setup_calendar_check',
            end: 'setup_calendar_check',
            estimate: '1 menit',
        },
    };

    // When a provider skips a setup stage, take them straight to the working
    // screen of the next stage instead of treating Skip like another Next.
    const setupGuideOrder = ['branch', 'service', 'staff', 'skills', 'schedules', 'calendar'];
    const setupGuideEntrySteps = {
        branch: 'setup_branch_basic',
        service: 'setup_service_basic',
        staff: 'setup_staff_form',
        skills: 'setup_skills',
        schedules: 'setup_schedules',
        calendar: 'setup_calendar_check',
    };

    const guideIdForSetupStep = (stepId) => {
        const stepIndex = setupGuideSteps.findIndex((step) => step.id === stepId);
        if (stepIndex < 0) return null;

        return setupGuideOrder.find((guideId) => {
            const definition = guideDefinitions[guideId];
            const startIndex = setupGuideSteps.findIndex((step) => step.id === definition.start);
            const endIndex = setupGuideSteps.findIndex((step) => step.id === definition.end);
            return stepIndex >= startIndex && stepIndex <= endIndex;
        }) || null;
    };

    const nextSetupStageEntry = (stepId) => {
        const currentGuideId = guideIdForSetupStep(stepId);
        if (!currentGuideId) return null;
        const nextGuideId = setupGuideOrder[setupGuideOrder.indexOf(currentGuideId) + 1];
        if (!nextGuideId) return setupGuideSteps.find((step) => step.id === 'setup_finish') || null;
        const entryStepId = setupGuideEntrySteps[nextGuideId] || guideDefinitions[nextGuideId].start;
        return setupGuideSteps.find((step) => step.id === entryStepId) || null;
    };

    const readCompletedGuides = () => {
        try {
            const value = JSON.parse(window.localStorage.getItem(completedGuidesStorageKey) || '[]');
            return Array.isArray(value) ? value : [];
        } catch (error) {
            return [];
        }
    };

    const markGuideComplete = (guideId) => {
        if (!guideId) return;
        const completed = new Set(readCompletedGuides());
        completed.add(guideId);
        window.localStorage.setItem(completedGuidesStorageKey, JSON.stringify([...completed]));
    };

    const guideIdForMode = (mode) => String(mode || '').startsWith('guide-')
        ? String(mode).slice('guide-'.length)
        : null;

    const stepsForGuide = (guideId) => {
        const definition = guideDefinitions[guideId];
        if (!definition) return [];
        const startIndex = setupGuideSteps.findIndex((step) => step.id === definition.start);
        const endIndex = setupGuideSteps.findIndex((step) => step.id === definition.end);
        if (startIndex < 0 || endIndex < startIndex) return [];
        return setupGuideSteps.slice(startIndex, endIndex + 1);
    };

    const stepsForMode = (mode) => {
        const guideId = guideIdForMode(mode);
        if (guideId) return stepsForGuide(guideId);
        return mode === 'setup' ? setupGuideSteps : menuTourSteps;
    };

    const prerequisiteState = (requirement) => {
        const state = config.setupState || {};
        if (requirement === 'branch') return Boolean(state.hasBranches);
        if (requirement === 'service') return Boolean(state.hasServices);
        if (requirement === 'staff') return Boolean(state.hasStaff);
        return true;
    };

    const prerequisiteCopy = (requirement) => ({
        branch: 'Buat minimal satu Branch terlebih dahulu.',
        service: 'Buat minimal satu Service terlebih dahulu.',
        staff: 'Buat minimal satu Staff terlebih dahulu.',
    }[requirement] || 'Selesaikan data yang dibutuhkan terlebih dahulu.');

    const contextualGuideId = () => {
        if (document.querySelector('.provider-service-create-page, .provider-my-service-category-page')) return 'service';
        if (document.querySelector('.provider-branch-form-page, .provider-branch-category-page')) return 'branch';
        if (document.querySelector('[data-setup-skill-list]')) return 'skills';
        if (document.querySelector('[data-setup-schedule-list]')) return 'schedules';
        if (document.querySelector('[data-setup-staff-list], [data-setup-staff-form]')) return 'staff';
        if (document.querySelector('.provider-month-calendar-card')) return 'calendar';
        return null;
    };

    const resolveStepId = (stepId) => legacyStepAliases[stepId] || stepId || 'step_overview';
    const modeForStep = (stepId) => String(stepId || '').startsWith('setup_') ? 'setup' : 'menu';
    const stepIndex = (stepId) => Math.max(0, activeSteps.findIndex((step) => step.id === resolveStepId(stepId)));
    const urlPath = (url) => {
        try {
            return new URL(url, window.location.href).pathname.replace(/\/$/, '') || '/';
        } catch (error) {
            return '';
        }
    };
    const requiresNavigation = (step) => Boolean(step.route) && urlPath(step.route) !== urlPath(window.location.href);
    const visibleElement = (selector) => {
        if (!selector) return null;
        return Array.from(document.querySelectorAll(selector)).find((element) => {
            const rect = element.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0 && window.getComputedStyle(element).visibility !== 'hidden';
        }) || document.querySelector(selector);
    };

    const prepareStep = (step) => {
        if (step?.prepare === 'openStaffModal') {
            const modal = document.getElementById('staffModal');
            if (modal && !modal.classList.contains('active')) {
                document.dispatchEvent(new CustomEvent('provider:staff-modal-open-create'));
            }
        }

        if (step?.prepare === 'closeStaffModal') {
            document.dispatchEvent(new CustomEvent('provider:staff-modal-close'));
        }
    };

    const isTutorialControl = (target) => (
        target instanceof Element
        && Boolean(target.closest('.driver-popover, .provider-tour-welcome-card'))
    );

    const isPracticeControl = (target) => (
        target instanceof Element
        && Boolean(
            target.closest('.provider-tour-practice')
            || (practiceScopeSelector && target.closest(practiceScopeSelector))
        )
    );

    const stopLockedInteraction = (event) => {
        const tutorialLocked = document.body.classList.contains('provider-tour-screen-locked');
        const practiceLocked = document.body.classList.contains('provider-tour-practice-active');

        if (!tutorialLocked && !practiceLocked) return;
        if (tutorialLocked && isTutorialControl(event.target)) return;
        if (practiceLocked && isPracticeControl(event.target)) return;
        if (
            practiceLocked
            && ['wheel', 'touchmove'].includes(event.type)
            && event.target instanceof Element
            && event.target.closest('.provider-content-area')
        ) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
    };

    ['pointerdown', 'mousedown', 'pointerup', 'mouseup', 'click'].forEach((eventName) => {
        document.addEventListener(eventName, stopLockedInteraction, true);
    });
    document.addEventListener('wheel', stopLockedInteraction, { capture: true, passive: false });
    document.addEventListener('touchmove', stopLockedInteraction, { capture: true, passive: false });
    document.addEventListener('keydown', (event) => {
        const tutorialLocked = document.body.classList.contains('provider-tour-screen-locked');
        const practiceLocked = document.body.classList.contains('provider-tour-practice-active');

        if (!tutorialLocked && !practiceLocked) return;

        if (
            event.key === 'Escape'
            || (tutorialLocked && !isTutorialControl(event.target))
            || (practiceLocked && !isPracticeControl(event.target))
        ) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        }
    }, true);

    document.addEventListener('focusin', (event) => {
        if (!document.body.classList.contains('provider-tour-practice-active')) return;
        if (isPracticeControl(event.target)) return;

        const firstControl = Array.from(document.querySelectorAll(practiceScopeSelector))
            .flatMap((scope) => Array.from(scope.querySelectorAll(
                'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'
            )))
            .find((control) => control.getClientRects().length > 0);
        firstControl?.focus({ preventScroll: true });
    }, true);

    window.addEventListener('scroll', () => {
        if (
            !document.body.classList.contains('provider-tour-screen-locked')
            && !document.body.classList.contains('provider-tour-practice-active')
        ) return;
        if (window.scrollX === lockedWindowScroll.x && window.scrollY === lockedWindowScroll.y) return;
        window.scrollTo(lockedWindowScroll.x, lockedWindowScroll.y);
    }, { passive: true });

    const lockTutorialScreen = () => {
        // The provider shell scrolls inside .provider-content-area. The root
        // viewport must stay at (0, 0), otherwise scrollIntoView can shift the
        // floating header and sidebar away from their normal position.
        lockedWindowScroll = { x: 0, y: 0 };
        document.body.classList.add('provider-tour-screen-locked');
        window.scrollTo(lockedWindowScroll.x, lockedWindowScroll.y);
    };

    const unlockTutorialScreen = () => {
        document.body.classList.remove('provider-tour-screen-locked');
        window.scrollTo(lockedWindowScroll.x, lockedWindowScroll.y);
    };

    const clearPracticeSafeArea = () => {
        practiceSafeAreaCleanup?.();
        practiceSafeAreaCleanup = null;
        document.querySelector('.provider-content-area')
            ?.style.removeProperty('--provider-tour-practice-safe-area');
    };

    const reservePracticeSafeArea = () => {
        clearPracticeSafeArea();

        // Inline guidance participates in the form layout and therefore does
        // not need extra space on the dashboard scroll container.
        if (practiceBanner?.classList.contains('provider-tour-practice--inline')) return;

        const scrollArea = document.querySelector('.provider-content-area');
        if (!scrollArea || !practiceBanner) return;

        const syncSafeArea = () => {
            if (!practiceBanner?.isConnected) return;

            // Leave enough room after the form for its action buttons to scroll
            // completely above the fixed practice card.
            const bannerHeight = Math.ceil(practiceBanner.getBoundingClientRect().height);
            scrollArea.style.setProperty(
                '--provider-tour-practice-safe-area',
                `${Math.max(156, bannerHeight + 44)}px`
            );
        };

        const resizeObserver = typeof ResizeObserver === 'function'
            ? new ResizeObserver(syncSafeArea)
            : null;

        resizeObserver?.observe(practiceBanner);
        window.addEventListener('resize', syncSafeArea);
        syncSafeArea();

        practiceSafeAreaCleanup = () => {
            resizeObserver?.disconnect();
            window.removeEventListener('resize', syncSafeArea);
        };
    };

    const removePracticeBanner = () => {
        clearPracticeSafeArea();
        practiceBanner?.remove();
        practiceBanner = null;
        practiceScopeSelector = '';
        document.body.classList.remove('provider-tour-practice-active');
    };

    const exitPractice = () => {
        const currentStep = window.sessionStorage.getItem(replayStorageKey);
        window.sessionStorage.setItem(pausedStorageKey, '1');
        removePracticeBanner();
        if (shouldPersistProgress && currentStep) updateOnboardingStatus('in_progress', currentStep);
    };

    const skipPracticeStep = async (step) => {
        const currentIndex = activeSteps.findIndex((item) => item.id === step.id);
        const followingStep = activeSteps[currentIndex + 1];
        const guideId = guideIdForMode(activeMode);

        removePracticeBanner();

        if (!followingStep) {
            window.sessionStorage.removeItem(replayStorageKey);
            window.sessionStorage.removeItem(modeStorageKey);
            window.sessionStorage.removeItem(pausedStorageKey);

            if (guideId) {
                markGuideComplete(guideId);
            } else if (shouldPersistProgress) {
                await updateOnboardingStatus('completed', 'done');
            }
            return;
        }

        window.sessionStorage.setItem(replayStorageKey, followingStep.id);
        window.sessionStorage.setItem(modeStorageKey, activeMode);
        window.sessionStorage.removeItem(pausedStorageKey);

        if (shouldPersistProgress && !guideId) {
            await updateOnboardingStatus('in_progress', followingStep.id);
        }

        if (requiresNavigation(followingStep)) {
            window.location.assign(followingStep.route);
            return;
        }

        startDriver(followingStep.id, activeMode);
    };

    const showPracticeBanner = (step) => {
        removePracticeBanner();
        practiceScopeSelector = step.practice.scope;
        document.body.classList.add('provider-tour-practice-active');
        document.body.insertAdjacentHTML('beforeend', `
            <aside class="provider-tour-practice" role="status" aria-live="polite">
                <span class="provider-tour-practice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6"></path></svg>
                </span>
                <span>
                    <strong>${step.practice.title}</strong>
                    <small>${step.practice.message}</small>
                </span>
                <div class="provider-tour-practice-actions">
                    <button type="button" data-tour-practice-skip>Lewati langkah</button>
                </div>
                <button type="button" data-tour-practice-close aria-label="Keluar dari panduan">&times;</button>
            </aside>
        `);
        practiceBanner = document.querySelector('.provider-tour-practice');

        // The Staff form lives in its own scrollable modal. Put the practice
        // card inside the form, immediately before its actions, so it pushes
        // Cancel/Save down instead of floating over those buttons.
        if (step.practice.scope === '#staffForm' && practiceBanner) {
            const staffActions = document.querySelector('#staffForm .staff-form-actions');
            if (staffActions) {
                practiceBanner.classList.add('provider-tour-practice--inline');
                staffActions.before(practiceBanner);
            }
        }

        practiceBanner?.querySelector('[data-tour-practice-close]')?.addEventListener('click', exitPractice);
        practiceBanner?.querySelector('[data-tour-practice-skip]')?.addEventListener('click', () => skipPracticeStep(step));
        reservePracticeSafeArea();
    };

    const pauseForPractice = (step) => {
        window.sessionStorage.setItem(replayStorageKey, step.id);
        window.sessionStorage.setItem(modeStorageKey, activeMode);
        window.sessionStorage.removeItem(pausedStorageKey);
        driverInstance?.destroy();
        driverInstance = null;
        unlockTutorialScreen();
        showPracticeBanner(step);

        window.requestAnimationFrame(() => {
            const target = visibleElement(step.practice.start) || visibleElement(step.element);
            const scrollArea = target?.closest('.provider-content-area');
            if (!target || !scrollArea) return;

            const targetRect = target.getBoundingClientRect();
            const scrollRect = scrollArea.getBoundingClientRect();
            const topbarRect = document.querySelector('.floating-topbar')?.getBoundingClientRect();
            const safeTopOffset = topbarRect
                ? Math.max(24, topbarRect.bottom - scrollRect.top + 24)
                : 104;
            const formStartTop = scrollArea.scrollTop
                + targetRect.top
                - scrollRect.top
                - safeTopOffset;

            scrollArea.scrollTo({ top: Math.max(0, formStartTop), behavior: 'auto' });
            window.scrollTo(lockedWindowScroll.x, lockedWindowScroll.y);

            const firstControl = Array.from(document.querySelectorAll(practiceScopeSelector))
                .flatMap((scope) => Array.from(scope.querySelectorAll(
                    'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'
                )))
                .find((control) => control.getClientRects().length > 0);
            firstControl?.focus({ preventScroll: true });
        });
    };

    function closeWelcomeModal() {
        welcomeModal?.remove();
        welcomeModal = null;
        if (!driverInstance?.isActive()) unlockTutorialScreen();
    }

    function createWelcomeModal({ replay = false, preferredGuide = null } = {}) {
        if (welcomeModal?.isConnected) return welcomeModal;

        lockTutorialScreen();

        const planNote = config.isPaid
            ? 'Paket Anda aktif—semua menu yang tersedia dapat langsung digunakan.'
            : 'Paket gratis tetap dapat menggunakan alur utama untuk menyiapkan dan menjalankan bisnis.';

        const pausedStep = window.sessionStorage.getItem(replayStorageKey);
        const pausedMode = window.sessionStorage.getItem(modeStorageKey);
        const hasPausedGuide = window.sessionStorage.getItem(pausedStorageKey) === '1' && pausedStep;
        const completedGuides = new Set(readCompletedGuides());
        const guideCards = Object.entries(guideDefinitions).map(([guideId, guide]) => {
            const blocked = guide.requires && !prerequisiteState(guide.requires);
            const completed = completedGuides.has(guideId);
            const preferred = preferredGuide === guideId;
            const stateLabel = blocked
                ? `Perlu ${guide.requires === 'branch' ? 'Branch' : guide.requires === 'staff' ? 'Staff' : 'data awal'}`
                : completed ? 'Selesai · Ulangi' : 'Mulai';

            return `
                <button type="button" class="provider-tour-module-card ${preferred ? 'is-recommended' : ''} ${blocked ? 'is-blocked' : ''}" data-tour-guide="${guideId}">
                    <span class="provider-tour-module-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"></path><path d="m8 12 2.5 2.5L16 9"></path></svg>
                    </span>
                    <span><strong>${guide.title}</strong><small>${guide.description}</small><em>${guide.estimate}</em></span>
                    <b>${stateLabel}</b>
                </button>
            `;
        }).join('');

        document.body.insertAdjacentHTML('beforeend', `
            <div class="provider-tour-welcome" id="provider-onboarding-modal" role="dialog" aria-modal="true" aria-labelledby="provider-onboarding-title">
                <div class="provider-tour-welcome-card">
                    <button type="button" class="provider-tour-welcome-close" data-tour-welcome-close aria-label="Tutup panduan">&times;</button>
                    <div class="provider-tour-welcome-head">
                        <span class="provider-tour-welcome-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 3 8l9 5 9-5-9-5Z"></path><path d="m5 11 7 4 7-4M5 15l7 4 7-4"></path></svg>
                        </span>
                        <span class="provider-tour-welcome-badge">Pusat panduan interaktif</span>
                        <h2 id="provider-onboarding-title">${replay ? 'Buka kembali panduan YouYaku' : 'Selamat datang di YouYaku'}</h2>
                        <p>Kami akan menunjukkan menu penting berdasarkan urutan kerja yang mudah diikuti—mulai dari menyiapkan bisnis sampai memantau transaksi.</p>
                    </div>

                    <div class="provider-tour-guide-options">
                        ${hasPausedGuide ? `
                            <button type="button" class="provider-tour-resume-card" data-tour-resume>
                                <span class="provider-tour-guide-icon resume">
                                    <svg viewBox="0 0 24 24" fill="none"><path d="M8 5v14l11-7-11-7Z"></path></svg>
                                </span>
                                <span><em>Progres tersimpan</em><strong>Lanjutkan panduan terakhir</strong><small>Kembali ke langkah yang terakhir dibuka tanpa mengulang dari awal.</small></span>
                                <b>Lanjutkan <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></b>
                            </button>
                        ` : ''}
                        <button type="button" class="recommended" data-tour-start-setup>
                            <span class="provider-tour-guide-icon">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M4 19V5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"></path><path d="M9 13h6M9 17h4M14 3v6h6"></path></svg>
                            </span>
                            <span><em>Direkomendasikan · ± 8 menit</em><strong>Siapkan bisnis langkah demi langkah</strong><small>Membimbing Location → Service → Staff → Skills → Schedule → Calendar, termasuk form dan cara mengelola datanya.</small></span>
                            <b>Mulai setup <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></b>
                        </button>
                        <button type="button" data-tour-start-menu>
                            <span class="provider-tour-guide-icon secondary">
                                <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
                            </span>
                            <span><em>Tur singkat · ± 3 menit</em><strong>Kenali fungsi setiap menu</strong><small>Pelajari navigasi dan kegunaan menu tanpa membahas setiap form secara mendalam.</small></span>
                            <b>Mulai tur <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></b>
                        </button>
                    </div>

                    <section class="provider-tour-module-section" aria-labelledby="provider-tour-module-title">
                        <div class="provider-tour-module-head">
                            <div>
                                <span>Panduan per fitur</span>
                                <h3 id="provider-tour-module-title">Pelajari bagian yang Anda perlukan</h3>
                            </div>
                            <small>Tutorial yang selesai tetap dapat diulang.</small>
                        </div>
                        <div class="provider-tour-module-grid">${guideCards}</div>
                        <div class="provider-tour-dependency" data-tour-dependency hidden></div>
                    </section>

                    <div class="provider-tour-plan-note">
                        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>
                        <span>${planNote}</span>
                    </div>

                    <div class="provider-tour-welcome-actions">
                        <button type="button" class="secondary" data-tour-welcome-skip>${replay ? 'Tutup' : 'Lewati dulu'}</button>
                    </div>
                </div>
            </div>
        `);

        welcomeModal = document.getElementById('provider-onboarding-modal');
        const closeButton = welcomeModal.querySelector('[data-tour-welcome-close]');
        const skipButton = welcomeModal.querySelector('[data-tour-welcome-skip]');
        const setupButton = welcomeModal.querySelector('[data-tour-start-setup]');
        const menuButton = welcomeModal.querySelector('[data-tour-start-menu]');
        const resumeButton = welcomeModal.querySelector('[data-tour-resume]');
        const dependencyNotice = welcomeModal.querySelector('[data-tour-dependency]');

        const dismiss = () => {
            if (!hasPausedGuide) {
                window.sessionStorage.removeItem(replayStorageKey);
                window.sessionStorage.removeItem(modeStorageKey);
                window.sessionStorage.removeItem(pausedStorageKey);
            }
            if (!replay && shouldPersistProgress) updateOnboardingStatus('skipped');
            closeWelcomeModal();
        };

        const launchGuide = async (mode) => {
            const guideSteps = stepsForMode(mode);
            const firstStep = guideSteps[0];
            if (!firstStep) return;
            window.sessionStorage.setItem(replayStorageKey, firstStep.id);
            window.sessionStorage.setItem(modeStorageKey, mode);
            window.sessionStorage.removeItem(pausedStorageKey);
            if (shouldPersistProgress) await updateOnboardingStatus('in_progress', firstStep.id);
            closeWelcomeModal();
            startDriver(firstStep.id, mode);
        };

        const launchModuleGuide = (guideId) => {
            const guide = guideDefinitions[guideId];
            if (!guide) return;

            if (guide.requires && !prerequisiteState(guide.requires)) {
                dependencyNotice.hidden = false;
                dependencyNotice.innerHTML = `
                    <strong>Data awal belum siap</strong>
                    <span>${prerequisiteCopy(guide.requires)}</span>
                    ${guide.requires === 'branch' ? '<button type="button" data-tour-open-prerequisite="branch">Buka panduan Branch</button>' : ''}
                    ${guide.requires === 'staff' ? '<button type="button" data-tour-open-prerequisite="staff">Buka panduan Staff</button>' : ''}
                `;
                dependencyNotice.querySelector('[data-tour-open-prerequisite]')?.addEventListener('click', (event) => {
                    launchModuleGuide(event.currentTarget.dataset.tourOpenPrerequisite);
                });
                dependencyNotice.scrollIntoView({ block: 'nearest' });
                return;
            }

            shouldPersistProgress = false;
            launchGuide(`guide-${guideId}`);
        };

        closeButton.addEventListener('click', dismiss);
        skipButton.addEventListener('click', dismiss);
        setupButton.addEventListener('click', () => launchGuide('setup'));
        menuButton.addEventListener('click', () => launchGuide('menu'));
        resumeButton?.addEventListener('click', () => {
            if (guideIdForMode(pausedMode)) shouldPersistProgress = false;
            window.sessionStorage.removeItem(pausedStorageKey);
            closeWelcomeModal();
            startDriver(pausedStep, pausedMode || modeForStep(pausedStep));
        });
        welcomeModal.querySelectorAll('[data-tour-guide]').forEach((button) => {
            button.addEventListener('click', () => launchModuleGuide(button.dataset.tourGuide));
        });

        const preferredButton = preferredGuide
            ? welcomeModal.querySelector(`[data-tour-guide="${preferredGuide}"]`)
            : null;
        (resumeButton || preferredButton || setupButton).focus();
        preferredButton?.scrollIntoView({ block: 'nearest' });
        return welcomeModal;
    }

    function openTutorial({ preferredGuide = null } = {}) {
        if (!getDriverFactory()) {
            window.alert('Tutorial belum dapat dimuat. Silakan muat ulang halaman lalu coba kembali.');
            return;
        }

        document.getElementById('profileMenu')?.classList.remove('show', 'open', 'active');
        shouldPersistProgress = !['completed', 'skipped'].includes(window.ProviderOnboarding?.status);
        createWelcomeModal({ replay: !shouldPersistProgress, preferredGuide });
    }

    async function transitionTo(step, driver) {
        if (!step || isTransitioning) return;
        isTransitioning = true;
        window.sessionStorage.setItem(replayStorageKey, step.id);
        window.sessionStorage.setItem(modeStorageKey, activeMode);

        if (shouldPersistProgress) {
            await updateOnboardingStatus('in_progress', step.id);
        }

        if (requiresNavigation(step)) {
            driver.destroy();
            window.location.assign(step.route);
            return;
        }

        prepareStep(step);
        const targetExists = !step.element || visibleElement(step.element);
        if (!targetExists) {
            // Permissions can hide a menu. Move past it rather than pointing to
            // Driver.js's invisible fallback element.
            const currentIndex = activeSteps.findIndex((item) => item.id === step.id);
            const followingStep = activeSteps[currentIndex + 1];
            isTransitioning = false;
            if (followingStep) return transitionTo(followingStep, driver);
        }

        isTransitioning = false;
        driver.moveTo(stepIndex(step.id));
    }

    function startDriver(startId = 'step_overview', requestedMode = null) {
        activeMode = requestedMode || modeForStep(startId);
        activeSteps = stepsForMode(activeMode);
        if (!activeSteps.length) return;
        const index = stepIndex(startId);
        const startStep = activeSteps[index];
        window.sessionStorage.setItem(modeStorageKey, activeMode);
        window.sessionStorage.removeItem(pausedStorageKey);

        if (requiresNavigation(startStep)) {
            window.sessionStorage.setItem(replayStorageKey, startStep.id);
            window.location.assign(startStep.route);
            return;
        }

        prepareStep(startStep);

        if (startStep.element && !visibleElement(startStep.element)) {
            const followingStep = activeSteps[index + 1];
            if (followingStep) {
                window.sessionStorage.setItem(replayStorageKey, followingStep.id);
                startDriver(followingStep.id, activeMode);
            }
            return;
        }

        const driverFactory = getDriverFactory();
        if (!driverFactory) return;

        const finishActiveGuide = async () => {
            const guideId = guideIdForMode(activeMode);
            window.sessionStorage.removeItem(replayStorageKey);
            window.sessionStorage.removeItem(modeStorageKey);
            window.sessionStorage.removeItem(pausedStorageKey);

            if (guideId) {
                markGuideComplete(guideId);
            } else if (activeMode === 'setup') {
                Object.keys(guideDefinitions).forEach(markGuideComplete);
            }

            if (!guideId && shouldPersistProgress) {
                await updateOnboardingStatus('completed', 'done');
            }

            driverInstance?.destroy();
            driverInstance = null;
            unlockTutorialScreen();
        };

        const movePastActiveStep = async () => {
            const currentIndex = driverInstance?.getActiveIndex() ?? index;

            // In the complete setup tutorial, Skip means skip the whole current
            // business stage (Branch, Service, Staff, and so on). The provider
            // lands directly on the next stage's form or working screen.
            if (activeMode === 'setup') {
                const nextStage = nextSetupStageEntry(activeSteps[currentIndex]?.id);
                if (nextStage) {
                    await transitionTo(nextStage, driverInstance);
                    return;
                }
            }

            // A contextual guide represents one complete stage. Skipping it
            // should finish that guide, not advance through its remaining tips.
            if (guideIdForMode(activeMode)) {
                await finishActiveGuide();
                return;
            }

            if (currentIndex >= activeSteps.length - 1) {
                await finishActiveGuide();
                return;
            }
            await transitionTo(activeSteps[currentIndex + 1], driverInstance);
        };

        const pauseActiveGuide = async () => {
            const activeIndex = driverInstance?.getActiveIndex() ?? index;
            const activeStep = activeSteps[activeIndex];
            if (activeStep) {
                window.sessionStorage.setItem(replayStorageKey, activeStep.id);
                window.sessionStorage.setItem(modeStorageKey, activeMode);
                window.sessionStorage.setItem(pausedStorageKey, '1');
                if (shouldPersistProgress && !guideIdForMode(activeMode)) {
                    await updateOnboardingStatus('in_progress', activeStep.id);
                }
            }
            driverInstance?.destroy();
            driverInstance = null;
            unlockTutorialScreen();
        };

        removePracticeBanner();
        driverInstance?.destroy();
        driverInstance = driverFactory({
            showProgress: true,
            progressText: 'Langkah {{current}} dari {{total}}',
            animate: true,
            smoothScroll: false,
            allowClose: true,
            allowKeyboardControl: false,
            disableActiveInteraction: true,
            overlayOpacity: 0.68,
            overlayColor: '#171421',
            stagePadding: 9,
            stageRadius: 16,
            popoverClass: 'provider-tour-popover',
            doneBtnText: 'Selesai',
            nextBtnText: 'Lanjut',
            prevBtnText: 'Kembali',
            onPopoverRender: (popover) => {
                if (popover.footerButtons.querySelector('.provider-tour-skip-btn')) return;
                const skipButton = document.createElement('button');
                skipButton.type = 'button';
                // Do not use a `driver-popover-*` class here. Driver.js captures
                // clicks on that namespace before custom listeners can run.
                skipButton.className = 'provider-tour-skip-btn';
                skipButton.textContent = activeMode === 'menu' ? 'Lewati langkah' : 'Lewati tahap';
                skipButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    movePastActiveStep();
                });
                popover.footerButtons.insertBefore(skipButton, popover.nextButton);
            },
            onNextClick: async () => {
                const currentIndex = driverInstance.getActiveIndex() ?? index;
                const currentStep = activeSteps[currentIndex];

                if (currentStep?.practice) {
                    pauseForPractice(currentStep);
                    return;
                }

                if (currentIndex >= activeSteps.length - 1) {
                    await finishActiveGuide();
                    return;
                }

                await transitionTo(activeSteps[currentIndex + 1], driverInstance);
            },
            onPrevClick: async () => {
                const currentIndex = driverInstance.getActiveIndex() ?? index;
                if (currentIndex <= 0) return;
                await transitionTo(activeSteps[currentIndex - 1], driverInstance);
            },
            onDestroyStarted: async () => {
                await pauseActiveGuide();
            },
        });

        driverInstance.setSteps(activeSteps.map((step) => ({
            element: visibleElement(step.element) || step.element,
            popover: step.popover,
        })));
        lockTutorialScreen();
        driverInstance.drive(index);
    }

    function updateOnboardingStatus(newStatus, step = null) {
        if (!config.updateUrl) return Promise.resolve(null);

        return fetch(config.updateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ status: newStatus, step }),
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) throw new Error(data.message || `HTTP ${response.status}`);

                window.ProviderOnboarding.status = newStatus;
                window.ProviderOnboarding.current_step = step;
                return data;
            })
            .catch((error) => {
                console.error('Gagal memperbarui status tutorial.', error);
                return null;
            });
    }

    const rememberSetupStep = (stepId) => {
        window.sessionStorage.setItem(replayStorageKey, stepId);
        const currentMode = window.sessionStorage.getItem(modeStorageKey);
        window.sessionStorage.setItem(
            modeStorageKey,
            guideIdForMode(currentMode) ? currentMode : 'setup'
        );
    };

    // If a user follows the highlighted control instead of pressing “Lanjut”,
    // resume at the appropriate explanation on the destination page.
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-setup-add-branch]')) rememberSetupStep('setup_branch_basic');
        if (event.target.closest('[data-setup-add-service]')) rememberSetupStep('setup_service_basic');
        if (event.target.closest('[data-setup-add-staff]')) rememberSetupStep('setup_staff_form');
    }, true);

    document.addEventListener('submit', (event) => {
        const currentStep = window.sessionStorage.getItem(replayStorageKey);
        if (!currentStep?.startsWith('setup_')) return;
        const currentMode = window.sessionStorage.getItem(modeStorageKey);
        const currentGuide = guideIdForMode(currentMode);

        if (
            (currentGuide === 'skills' && currentStep === 'setup_skills')
            || (currentGuide === 'schedules' && currentStep === 'setup_schedules')
        ) {
            markGuideComplete(currentGuide);
            window.sessionStorage.removeItem(replayStorageKey);
            window.sessionStorage.removeItem(modeStorageKey);
            window.sessionStorage.removeItem(pausedStorageKey);
            return;
        }

        if (currentStep === 'setup_branch_save' && event.target.matches('.provider-branch-editor-form')) {
            rememberSetupStep('setup_branch_manage');
        }

        if (currentStep === 'setup_service_continue' && event.target.querySelector('#galleryImageInput')) {
            rememberSetupStep('setup_service_manage');
        }

        if (currentStep === 'setup_staff_save' && event.target.matches('[data-setup-staff-form]')) {
            rememberSetupStep('setup_staff_manage');
        }

        if (currentStep === 'setup_skills' && event.target.matches('.provider-staff-skill-form')) {
            rememberSetupStep('setup_schedules');
        }

        if (currentStep === 'setup_schedules' && event.target.matches('.provider-staff-schedule-form')) {
            rememberSetupStep('setup_calendar_check');
        }
    }, true);

    window.startProviderTutorial = openTutorial;
    document.querySelectorAll('[data-provider-tutorial-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openTutorial();
        });
    });

    document.querySelectorAll('[data-provider-context-guide]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openTutorial({ preferredGuide: contextualGuideId() });
        });
    });

    const tutorialIsPaused = window.sessionStorage.getItem(pausedStorageKey) === '1';

    if (savedReplayStep && !tutorialIsPaused) {
        shouldPersistProgress = guideIdForMode(savedMode) ? false : persistedTutorialIsActive;
        // Older sessions may point to the removed branch-side staff selector.
        // Return them to the single Branch save step without losing form data.
        const resumeStep = ['setup_branch_staff', 'setup_branch_staff_save'].includes(savedReplayStep)
            ? 'setup_branch_save'
            : savedReplayStep;
        startDriver(resumeStep, savedMode);
    } else if (config.status === 'not_started' && !tutorialIsPaused) {
        shouldPersistProgress = true;
        createWelcomeModal();
    } else if (config.status === 'in_progress' && !tutorialIsPaused) {
        shouldPersistProgress = true;
        const currentStep = ['setup_branch_staff', 'setup_branch_staff_save'].includes(config.current_step)
            ? 'setup_branch_save'
            : (config.current_step || 'step_overview');
        startDriver(currentStep, modeForStep(currentStep));
    }
});
