import { LegalPage } from '../../src/components/LegalPage.jsx';

export const metadata = {
    title: 'Ketentuan Layanan | YouYaku',
    description: 'Ketentuan penggunaan marketplace booking salon dan beauty YouYaku.',
};

const sections = [
    {
        title: 'Penggunaan layanan',
        paragraphs: [
            'Customer wajib memberikan informasi yang akurat, menjaga keamanan akun, dan menggunakan platform hanya untuk reservasi yang sah. Aktivitas yang mengganggu layanan atau merugikan pengguna lain tidak diperbolehkan.',
            'Ketersediaan, durasi, harga, serta kebijakan layanan ditetapkan oleh salon dan dikonfirmasi kembali ketika reservasi dibuat.',
        ],
    },
    {
        title: 'Reservasi, pembayaran, dan pembatalan',
        paragraphs: [
            'Reservasi dianggap aktif setelah sistem menampilkan konfirmasi. Untuk metode pembayaran online, status reservasi dapat menunggu sampai penyedia pembayaran mengonfirmasi transaksi.',
            'Perubahan jadwal, pembatalan, pengembalian dana, keterlambatan, dan ketidakhadiran mengikuti kebijakan salon yang ditampilkan dalam alur booking.',
        ],
    },
    {
        title: 'Tanggung jawab platform',
        paragraphs: [
            'YouYaku menyediakan sarana untuk menemukan dan memesan layanan dari mitra independen. Salon bertanggung jawab atas pelaksanaan, mutu, keselamatan, serta informasi layanan yang mereka tawarkan.',
            'Kami dapat memperbarui ketentuan ini untuk mengikuti perubahan fitur, keamanan, atau peraturan. Versi terbaru akan ditampilkan pada halaman ini.',
        ],
    },
];

export default function TermsPage() {
    return <LegalPage eyebrow="Ketentuan customer" title="Ketentuan Layanan" updatedAt="12 Agustus 2026" sections={sections} />;
}
