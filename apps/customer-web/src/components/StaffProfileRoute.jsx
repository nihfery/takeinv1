'use client';

import { useEffect, useState } from 'react';
import { getPublicStaffProfile } from '../lib/auth-api.js';
import { getStaffProfileSnapshot } from '../lib/mock-state.js';
import { PROVIDER_FRONTEND_URL } from '../lib/app-urls.js';
import { StaffProfileView } from './StaffProfileView.jsx';

export function StaffProfileRoute({ staffId, initialProfile = null }) {
    const [profile, setProfile] = useState(initialProfile);
    const [status, setStatus] = useState(initialProfile ? 'ready' : 'loading');

    useEffect(() => {
        let cancelled = false;
        if (initialProfile?.branch && initialProfile?.staff) {
            setProfile(initialProfile);
            setStatus('ready');
            return undefined;
        }

        const snapshot = getStaffProfileSnapshot(staffId);

        if (snapshot?.branch && snapshot?.staff) {
            setProfile(snapshot);
            setStatus('ready');
            return undefined;
        }

        const numericStaffId = Number(staffId);
        if (!Number.isInteger(numericStaffId) || numericStaffId <= 0) {
            setStatus('unavailable');
            return undefined;
        }

        getPublicStaffProfile(numericStaffId)
            .then((branch) => {
                if (cancelled || !branch) return;
                const staff = (branch.staff || branch.staffs || []).find((member) => String(member.id) === String(staffId));
                if (!staff) {
                    setStatus('unavailable');
                    return;
                }

                setProfile({ branch, staff, services: branch.services || [] });
                setStatus('ready');
            })
            .catch(() => {
                if (!cancelled) setStatus('unavailable');
            });

        return () => {
            cancelled = true;
        };
    }, [staffId, initialProfile]);

    if (status === 'ready' && profile) {
        return <StaffProfileView branch={profile.branch} staff={profile.staff} services={profile.services} providerUrl={PROVIDER_FRONTEND_URL} customerAppUrl="/" />;
    }

    return (
        <main className="staff-profile-route-state">
            {status === 'loading' ? (
                <div className="staff-profile-loading" role="status" aria-live="polite">
                    <span className="booking-loading-indicator" aria-hidden="true"><span /><span /><span /></span>
                    <span className="sr-only">Memuat profil professional...</span>
                </div>
            ) : <p>This staff profile is not available yet. Return to the salon page and choose a staff member again.</p>}
        </main>
    );
}
