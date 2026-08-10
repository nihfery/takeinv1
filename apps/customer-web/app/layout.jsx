import 'maplibre-gl/dist/maplibre-gl.css';
import './globals.css';
import { CustomerSessionProvider } from '../src/components/CustomerSessionProvider.jsx';

export const metadata = {
    title: 'YouYaku - Book Local Salon & Beauty Services',
    description: 'Find and book the best beauty, wellness, and salon services near you instantly with YouYaku.',
};

export default function RootLayout({ children }) {
    return (
        <html lang="en" data-scroll-behavior="smooth">
            <head>
                <link
                    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
                    rel="stylesheet"
                />
                <link
                    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
                    rel="stylesheet"
                />
            </head>
            <body>
                <CustomerSessionProvider>{children}</CustomerSessionProvider>
            </body>
        </html>
    );
}
