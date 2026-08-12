import { LegalPage } from '../../src/components/LegalPage.jsx';

export const metadata = {
    title: 'Kebijakan Privasi | YouYaku',
    description: 'Cara YouYaku mengumpulkan, menggunakan, dan melindungi data customer.',
};

const sections = [
    {
        title: 'Data yang kami proses',
        paragraphs: [
            'Kami memproses informasi akun, detail kontak, pilihan layanan, jadwal reservasi, serta data transaksi yang diperlukan untuk menjalankan layanan YouYaku.',
            'Informasi pembayaran sensitif diproses oleh penyedia pembayaran terkait. YouYaku hanya menyimpan referensi dan status transaksi yang dibutuhkan untuk menampilkan riwayat reservasi.',
        ],
    },
    {
        title: 'Cara data digunakan',
        paragraphs: [
            'Data digunakan untuk mengautentikasi akun, menghubungkan customer dengan salon, mengelola reservasi dan pembayaran, menangani dukungan, serta menjaga keamanan platform.',
            'Kami tidak menjual data pribadi customer. Akses diberikan hanya kepada pihak yang membutuhkannya untuk menyediakan layanan atau memenuhi kewajiban hukum.',
        ],
    },
    {
        title: 'Kontrol dan keamanan',
        paragraphs: [
            'Customer dapat memperbarui data profil dari halaman Profil. Permintaan terkait akses, koreksi, atau penghapusan data dapat diajukan melalui kanal dukungan YouYaku.',
            'Kami menerapkan kontrol akses, sesi aman, validasi permintaan, dan pencatatan operasional untuk membantu melindungi data dari akses yang tidak sah.',
        ],
    },
];

export default function PrivacyPage() {
    return <LegalPage eyebrow="Privasi customer" title="Kebijakan Privasi" updatedAt="12 Agustus 2026" sections={sections} />;
}
