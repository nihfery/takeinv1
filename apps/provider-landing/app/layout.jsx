import '../src/styles.css';

export const metadata = {
  title: 'YouYaku Partners - Booking, Payments & Admin',
  description: 'Everything from bookings and payments to reminders and reports.',
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
