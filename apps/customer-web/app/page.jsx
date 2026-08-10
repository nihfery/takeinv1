import { LandingPage } from '../src/components/LandingPage.jsx';
import { getLandingPayload } from '../src/lib/landing-data.js';

export const dynamic = 'force-dynamic';

export default async function HomePage() {
    const payload = await getLandingPayload();

    return <LandingPage {...payload} />;
}
