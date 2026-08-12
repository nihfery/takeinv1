export const PROVIDER_FRONTEND_URL = (
    process.env.NEXT_PUBLIC_PROVIDER_FRONTEND_URL
    || 'http://127.0.0.1:5173'
).replace(/\/$/, '');
