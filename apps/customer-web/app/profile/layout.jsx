export const metadata = {
    title: 'Profile | YouYaku',
    description: 'Manage your YouYaku customer profile, account details, saved preferences, and notification settings.',
    robots: {
        index: false,
        follow: false,
        googleBot: {
            index: false,
            follow: false,
        },
    },
    alternates: {
        canonical: '/profile',
    },
    openGraph: {
        title: 'Profile | YouYaku',
        description: 'Manage your YouYaku customer profile and account preferences.',
        url: '/profile',
        siteName: 'YouYaku',
        type: 'website',
    },
};

export default function ProfileLayout({ children }) {
    return children;
}
