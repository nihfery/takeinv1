'use client';

import { use, useEffect, useRef, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { 
    mockSalons, 
    mockAddons, 
    addBookingToList,
    getBookingDraft, 
    getSessionUser,
    saveBookingDraft,
    clearBookingDraft,
    setSessionUser,
} from '../../../src/lib/mock-state.js';
import {
    cancelCustomerBooking,
    checkCustomerBookingAvailability,
    checkCustomerBookingEligibleStaff,
    extendCustomerBookingHold,
    fetchCurrentCustomer,
    finalizeCustomerBooking,
    getPublicBranchDetail,
    getPublicBranches,
    holdCustomerBooking,
    pingCustomerBookingInteraction,
    validateCustomerCoupon,
} from '../../../src/lib/auth-api.js';
import { findBranchByRoute, getSalonPath, getSalonRouteSlug } from '../../../src/lib/salon-routes.js';
import { StaffProfileModal } from '../../../src/components/StaffProfileModal.jsx';
import { 
    ChevronLeft, 
    X,
    User, 
    Users,
    Calendar, 
    Clock, 
    Sparkles, 
    Check, 
    Plus,
    Minus,
    ListFilter,
    ArrowRight,
    Star, 
    AlertCircle,
    Ticket,
} from 'lucide-react';

const TIME_PERIOD_DETAILS = {
    Pagi: { title: 'Morning', range: '06:00 – 11:59', label: 'Start your day feeling refreshed' },
    Siang: { title: 'Afternoon', range: '12:00 – 14:59', label: 'A comfortable break-time slot' },
    Sore: { title: 'Late afternoon', range: '15:00 – 17:59', label: 'A slot after your daily activities' },
    Malam: { title: 'Evening', range: '18:00 – 21:00', label: 'A convenient after-work option' },
};

function formatBookingPrice(value) {
    return `IDR ${Number(value || 0).toLocaleString('en-US')}`;
}

function validImageSource(value) {
    const source = String(value || '').trim();
    return source.length ? source : null;
}

function avatarInitial(name) {
    return String(name || 'P').trim().charAt(0).toUpperCase() || 'P';
}

function createEmptyGuest() {
    return { name: '', phone: '', email: '', gender: '', age_group: '', description: '' };
}

function createEmptyParticipantSelection(position = 1) {
    return {
        position,
        services: [],
        addons: [],
        staff: null,
        date: '',
        time: '',
    };
}

function normalizeBookingServices(services = []) {
    return services.map((service, index) => ({
        id: service.id || `service-${index}`,
        name: service.name || service.title || `Service ${index + 1}`,
        category: service.category_name || service.category || 'Featured',
        desc: service.desc || service.description || 'Treatment profesional dengan konsultasi singkat dan pengerjaan sesuai kebutuhan customer.',
        duration: Number(service.duration || service.estimated_duration || service.minimum_duration || 60),
        price: Number(service.price || 90000),
        discountPrice: service.discountPrice,
        slug: service.slug,
        code: service.code,
        popular: Boolean(service.popular || service.featured || index === 0),
    }));
}

function normalizeServiceMatchValue(value) {
    return String(value || '').trim().toLowerCase();
}

function reconcileServicesForBranch(selected = [], backendServices = []) {
    if (!Array.isArray(selected) || selected.length === 0) return [];
    if (!Array.isArray(backendServices) || backendServices.length === 0) return selected;

    const usedIds = new Set();

    return selected
        .map((service) => {
            const exact = backendServices.find((candidate) => String(candidate.id) === String(service.id));
            const slugMatch = service.slug
                ? backendServices.find((candidate) => candidate.slug && candidate.slug === service.slug)
                : null;
            const codeMatch = service.code
                ? backendServices.find((candidate) => candidate.code && candidate.code === service.code)
                : null;
            const title = normalizeServiceMatchValue(service.name || service.title);
            const category = normalizeServiceMatchValue(service.category || service.category_name);
            const titleMatch = backendServices.find((candidate) => (
                normalizeServiceMatchValue(candidate.name || candidate.title) === title
                && (!category || normalizeServiceMatchValue(candidate.category || candidate.category_name) === category)
            )) || backendServices.find((candidate) => normalizeServiceMatchValue(candidate.name || candidate.title) === title);
            const resolved = exact || slugMatch || codeMatch || titleMatch || null;

            if (!resolved || usedIds.has(String(resolved.id))) return null;

            usedIds.add(String(resolved.id));
            return resolved;
        })
        .filter(Boolean);
}

function getStaffPriceAdjustmentValue(staff) {
    if (!staff || staff === 'any') return 0;
    const role = String(staff.role || '');
    if (role.includes('Senior')) return 15000;
    if (role.includes('Director')) return 30000;
    return 0;
}

function normalizeBookingStaff(staff = []) {
    return staff.map((member, index) => ({
        id: member.id,
        name: member.name || member.full_name || [member.first_name, member.last_name].filter(Boolean).join(' ') || `Professional ${index + 1}`,
        role: member.role || 'Professional',
        photo: member.photo || member.image_url || member.image || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&h=200&q=80',
        rating: Number(member.rating || 4.8),
        reviews: Number(member.reviews || member.review_count || 0),
        reviewItems: Array.isArray(member.reviewItems) ? member.reviewItems : (Array.isArray(member.reviews) ? member.reviews : []),
        bio: member.bio || '',
        completedBookings: Number(member.completedBookings || member.completed_bookings_count || 0),
        clientsServed: Number(member.clientsServed || member.clients_served_count || 0),
        skills: member.skills || [],
        schedules: member.schedules || [],
    }));
}

function serviceNumericIds(services = []) {
    return services
        .map((service) => Number(service?.id))
        .filter((id) => Number.isInteger(id) && id > 0);
}

function staffCanServeServices(staff, services = []) {
    const serviceIds = serviceNumericIds(services);
    if (!serviceIds.length) return true;

    const skillIds = new Set((staff?.skills || [])
        .map((skill) => Number(skill?.id ?? skill))
        .filter((id) => Number.isInteger(id) && id > 0));

    return serviceIds.every((serviceId) => skillIds.has(serviceId));
}

function slotMatchesStaff(slot, staff) {
    if (!slot?.start) return false;
    if (!staff || staff === 'any') return true;

    return Number(slot.staffId) === Number(staff.id);
}

function deriveBookingProgressStep({ services = [], staff = null, date = '', time = '' }) {
    if (staff && (date || time)) return 3;
    if (staff) return 2;
    if (services.length > 0) return 1;
    return 1;
}

function normalizeRequestedBookingDate(value) {
    const date = String(value || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';

    const parsed = new Date(`${date}T00:00:00`);
    return Number.isNaN(parsed.getTime()) ? '' : date;
}

export default function BookingFlowPage({ params }) {
    const { salonSlug } = use(params);
    const router = useRouter();
    const searchParams = useSearchParams();
    const requestedBookingDate = normalizeRequestedBookingDate(searchParams.get('date'));

    const [salon, setSalon] = useState(null);
    const [currentStep, setCurrentStep] = useState(1); // 1: Services/Add-ons, 2: Staff, 3: Date & Time
    const [savedProgressStep, setSavedProgressStep] = useState(1);

    // Stepper State
    const [selectedServices, setSelectedServices] = useState([]);
    const [selectedAddons, setSelectedAddons] = useState([]);
    const [selectedStaff, setSelectedStaff] = useState(null); // null means not selected, 'any' means Any Staff, or staff object
    const [activeStaffProfile, setActiveStaffProfile] = useState(null);
    const [selectedDate, setSelectedDate] = useState(''); // YYYY-MM-DD
    const [selectedTime, setSelectedTime] = useState(''); // HH:MM
    const [activeCategory, setActiveCategory] = useState('Featured');

    // Horizontal date selector helper (7 days starting from today)
    const [datesList, setDatesList] = useState([]);
    const [timeSlots, setTimeSlots] = useState([]);
    const [loadingAvailability, setLoadingAvailability] = useState(false);
    const [availabilityError, setAvailabilityError] = useState('');
    const [validatingTime, setValidatingTime] = useState('');
    const [eligibleStaffIds, setEligibleStaffIds] = useState(null);
    const [eligibleStaffOptions, setEligibleStaffOptions] = useState(null);
    const [loadingEligibleStaff, setLoadingEligibleStaff] = useState(false);
    const [preparingProfessionalStep, setPreparingProfessionalStep] = useState(false);
    const [showExitDialog, setShowExitDialog] = useState(false);
    const [submittingBooking, setSubmittingBooking] = useState(false);
    const [holdingBooking, setHoldingBooking] = useState(false);
    const [heldBooking, setHeldBooking] = useState(null);
    const [holdReleaseVersion, setHoldReleaseVersion] = useState(0);
    const [holdError, setHoldError] = useState('');
    const [notes, setNotes] = useState('');
    const [bookingMode, setBookingMode] = useState(null);
    const [bookingModeSelected, setBookingModeSelected] = useState(false);
    const [participantCount, setParticipantCount] = useState(1);
    const [guests, setGuests] = useState([]);
    const [participantSelections, setParticipantSelections] = useState([createEmptyParticipantSelection(1)]);
    // Keep the latest participant selections available to event handlers. React
    // state updates are asynchronous, while selecting a time updates both the
    // current time and the active participant in one interaction.
    const participantSelectionsRef = useRef([createEmptyParticipantSelection(1)]);
    const [activeParticipantIndex, setActiveParticipantIndex] = useState(0);
    const [paymentMethod, setPaymentMethod] = useState('QRIS');
    const [agreedToTerms, setAgreedToTerms] = useState(false);
    const [voucherCode, setVoucherCode] = useState('');
    const [appliedVoucher, setAppliedVoucher] = useState(null);
    const [voucherError, setVoucherError] = useState('');
    const [voucherSuccess, setVoucherSuccess] = useState('');
    const [validatingVoucher, setValidatingVoucher] = useState(false);
    const allowExitRef = useRef(false);
    const exitGuardArmedRef = useRef(false);
    const shouldWarnBeforeExitRef = useRef(false);
    const exitTargetPathRef = useRef('/search');
    const activeHoldRequestRef = useRef(null);
    const holdSelectionVersionRef = useRef(0);
    const timeValidationRequestRef = useRef(0);
    const selectedTimeRef = useRef('');
    const bookingCompletedRef = useRef(false);

    useEffect(() => {
        setAppliedVoucher(null);
        setVoucherCode('');
        setVoucherError('');
        setVoucherSuccess('');
    }, [selectedServices, participantSelections]);

    const commitParticipantSelections = (nextSelections) => {
        participantSelectionsRef.current = nextSelections;
        setParticipantSelections(nextSelections);
        return nextSelections;
    };

    useEffect(() => {
        let cancelled = false;
        const loadBookingContext = async () => {
            const localSession = getSessionUser();

            try {
                const auth = await fetchCurrentCustomer();
                if (cancelled) return;

                if (!localSession.loggedIn || String(localSession.user?.id || '') !== String(auth.profile?.id || '')) {
                    setSessionUser({ loggedIn: true, user: auth.profile });
                }
            } catch {
                // Keep guest browsing usable; protected booking actions still require backend auth.
            }

            if (cancelled) return;

            const draft = getBookingDraft();
        const routeSlug = decodeURIComponent(String(salonSlug || ''));
        const draftSlug = draft?.salonSlug || '';
        const draftMatchesRoute = draft && (
            String(draft.salonId) === routeSlug
            || String(draftSlug) === routeSlug
            || String(draftSlug).toLowerCase() === String(routeSlug).toLowerCase()
        );
        const matchedMockSalon = findBranchByRoute(mockSalons, salonSlug);
        const draftSalon = draftMatchesRoute ? {
            ...mockSalons[0],
            id: String(draft.salonId),
            slug: draft.salonSlug || routeSlug,
            name: draft.salonName,
            image: draft.salonImage || mockSalons[0].image,
            address: draft.salonAddress || mockSalons[0].address,
            city: draft.salonAddress || mockSalons[0].city,
            rating: Number(draft.salonRating || mockSalons[0].rating),
            reviews: Number(draft.salonReviews || mockSalons[0].reviews),
            services: normalizeBookingServices(draft.availableServices?.length ? draft.availableServices : draft.services),
            staff: [],
        } : null;
        let catalogSalon = null;

        if (!draftSalon) {
            try {
                const catalogBranches = await getPublicBranches();
                if (cancelled) return;
                catalogSalon = findBranchByRoute(catalogBranches, salonSlug);
            } catch {
                // The route fallback below keeps an invalid or unavailable
                // catalog link from silently booking the wrong salon.
            }
        }

        const activeSalon = draftSalon
            || catalogSalon
            || (process.env.NODE_ENV !== 'production' ? matchedMockSalon : null);

        if (!activeSalon) {
            allowExitRef.current = true;
            router.replace('/search?notice=salon-not-found');
            return;
        }
        const draftBookingDate = draft && String(draft.salonId) === String(activeSalon.id)
            ? normalizeRequestedBookingDate(draft.date)
            : '';
        const initialBookingDate = requestedBookingDate || draftBookingDate;
        setSalon(activeSalon);
        // Load draft if matches current salon
        if (draft && String(draft.salonId) === String(activeSalon.id)) {
            setSelectedServices(normalizeBookingServices(draft.services || []));
            setSelectedAddons(draft.addons || []);
            setSelectedStaff(draft.staff);
            const restoredParticipantCount = Math.min(5, Math.max(1, Number(draft.participantCount || 1)));
            setBookingMode(draft.bookingMode || (restoredParticipantCount > 1 ? 'group' : 'personal'));
            setBookingModeSelected(Boolean(draft.bookingModeSelected));
            setParticipantCount(restoredParticipantCount);
            setGuests(Array.from({ length: restoredParticipantCount - 1 }, (_, index) => ({
                ...createEmptyGuest(),
                ...(draft.guests?.[index] || {}),
            })));
            const restoredParticipantSelections = Array.from({ length: restoredParticipantCount }, (_, index) => ({
                ...createEmptyParticipantSelection(index + 1),
                ...(draft.participantSelections?.[index] || {}),
                position: index + 1,
                services: normalizeBookingServices(
                    draft.participantSelections?.[index]?.services
                    || (index === 0 ? draft.services : [])
                    || []
                ),
                addons: draft.participantSelections?.[index]?.addons || (index === 0 ? draft.addons : []) || [],
            }));
            commitParticipantSelections(restoredParticipantSelections);
            setActiveParticipantIndex(0);
            setSelectedDate(initialBookingDate);
            setSelectedTime(draft.time || '');
            selectedTimeRef.current = draft.time || '';
            const hasRestorableHold = Boolean(draft.holdBookingId && draft.holdExpiresAt && new Date(draft.holdExpiresAt).getTime() > Date.now());
            if (draft.holdBookingId && draft.holdExpiresAt && new Date(draft.holdExpiresAt).getTime() > Date.now()) {
                setHeldBooking({
                    id: draft.holdBookingId,
                    holdStartedAt: draft.holdStartedAt || null,
                    holdExpiresAt: draft.holdExpiresAt,
                    date: draft.date || '',
                    time: draft.time || '',
                });
            }
            if (Number(draft.currentStep) >= 1 && Number(draft.currentStep) <= 4) {
                const restoredStep = Number(draft.currentStep) === 4 && (!draft.time || !hasRestorableHold)
                    ? 3
                    : Number(draft.currentStep);
                setCurrentStep(restoredStep);
                setSavedProgressStep(restoredStep);
            }
        }

        if (!draft || String(draft.salonId) !== String(activeSalon.id)) {
            setSelectedDate(initialBookingDate);
        }

        async function hydrateBackendBranch() {
            const branchId = Number(activeSalon.id);
            if (!Number.isInteger(branchId) || branchId <= 0) return;

            try {
                const branch = await getPublicBranchDetail(branchId);
                if (cancelled || !branch) return;

                const backendServices = normalizeBookingServices(branch.services || activeSalon.services || []);
                const backendStaff = normalizeBookingStaff(branch.staff || branch.staffs || []);

                setSalon((currentSalon) => ({
                    ...(currentSalon || activeSalon),
                    id: branch.id || activeSalon.id,
                    slug: branch.slug || activeSalon.slug,
                    name: branch.name || branch.branch_name || activeSalon.name,
                    image: branch.image_url || branch.image || activeSalon.image,
                    address: branch.address || activeSalon.address,
                    city: branch.city || branch.city_id || activeSalon.city,
                    rating: Number(branch.rating || activeSalon.rating || 4.8),
                    reviews: Number(branch.review_count || branch.reviews || activeSalon.reviews || 0),
                    services: backendServices,
                    staff: backendStaff,
                }));

                const selectedSource = selectedServices.length
                    ? selectedServices
                    : (draft?.services || []);
                const nextServices = reconcileServicesForBranch(normalizeBookingServices(selectedSource), backendServices);

                if (nextServices.length) {
                    const previousIds = selectedSource.map((service) => String(service.id)).join('|');
                    const nextIds = nextServices.map((service) => String(service.id)).join('|');
                    setSelectedServices(nextServices);

                    if (previousIds !== nextIds) {
                        saveBookingDraft({
                            ...(draft || {}),
                            salonId: branch.id || activeSalon.id,
                            salonSlug: branch.slug || activeSalon.slug,
                            salonName: branch.name || branch.branch_name || activeSalon.name,
                            availableServices: backendServices,
                            services: nextServices,
                            staff: draft?.staff ?? selectedStaff,
                            date: draft?.date ?? selectedDate,
                            time: draft?.time ?? selectedTimeRef.current,
                        });
                    }
                } else if (draft && String(draft.salonId) === String(branch.id)) {
                    setSelectedServices(backendServices.slice(0, 1));
                }

                setSelectedStaff((currentStaff) => {
                    if (!currentStaff || currentStaff === 'any') return currentStaff;
                    const matchingStaff = backendStaff.find((member) => String(member.id) === String(currentStaff.id));
                    return matchingStaff || null;
                });
                commitParticipantSelections(participantSelectionsRef.current.map((selection, index) => ({
                    ...selection,
                    position: index + 1,
                    services: reconcileServicesForBranch(
                        normalizeBookingServices(selection.services || []),
                        backendServices
                    ),
                    staff: !selection.staff || selection.staff === 'any'
                        ? selection.staff
                        : (backendStaff.find((member) => String(member.id) === String(selection.staff.id)) || null),
                })));
            } catch {
                // Keep the local draft usable for browsing; backend submit will still validate.
            }
        }

        hydrateBackendBranch();

        // Generate at least seven days, expanding the list when a date was
        // selected from search so the same date is visible and preselected.
        const list = [];
        const days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const requestedDate = initialBookingDate ? new Date(`${initialBookingDate}T00:00:00`) : null;
        const daysUntilRequested = requestedDate && requestedDate >= today
            ? Math.round((requestedDate.getTime() - today.getTime()) / 86400000)
            : 0;
        const dayCount = Math.min(Math.max(7, daysUntilRequested + 1), 366);

        for (let i = 0; i < dayCount; i++) {
            const d = new Date();
            d.setDate(d.getDate() + i);
            
            const year = d.getFullYear();
            const monthVal = String(d.getMonth() + 1).padStart(2, '0');
            const dateVal = String(d.getDate()).padStart(2, '0');
            const dateStr = `${year}-${monthVal}-${dateVal}`;

            list.push({
                dateStr,
                dayName: days[d.getDay()],
                dayNum: d.getDate(),
                monthName: months[d.getMonth()],
                displayLabel: `${d.getDate()} ${months[d.getMonth()]}`
            });
        }
        setDatesList(list);

        };

        loadBookingContext();

        return () => {
            cancelled = true;
        };
    }, [salonSlug, requestedBookingDate]);

    // Update available time slots from backend when date, staff, or services change.
    useEffect(() => {
        let cancelled = false;

        async function loadAvailability() {
            if (currentStep !== 3) {
                setLoadingAvailability(false);
                return;
            }

            if (!selectedDate || !salon || selectedServices.length === 0) {
                setTimeSlots([]);
                setEligibleStaffIds(null);
                setEligibleStaffOptions(null);
                setAvailabilityError('');
                setLoadingAvailability(false);
                return;
            }

            setLoadingAvailability(true);
            setAvailabilityError('');

            try {
                const draft = getBookingDraft();
                const draftHoldExpiresAt = new Date(draft?.holdExpiresAt || '').getTime();
                const draftHeldBookingId = draft?.holdBookingId
                    && Number.isFinite(draftHoldExpiresAt)
                    && draftHoldExpiresAt > Date.now()
                    ? draft.holdBookingId
                    : null;
                const availability = await checkCustomerBookingAvailability({
                    branchId: salon.id,
                    serviceIds: selectedServices.map((service) => service.id),
                    bookingDate: selectedDate,
                    staffId: selectedStaff && selectedStaff !== 'any' ? selectedStaff.id : null,
                    heldBookingId: heldBooking?.id || draftHeldBookingId || null,
                    participantCount: bookingMode === 'group' ? 1 : participantCount,
                    force: true,
                });

                if (cancelled) return;

                const nextSlots = availability.available_slots || [];
                setTimeSlots(nextSlots);
                const nextEligibleStaff = availability.eligible_staff || [];
                const nextEligibleStaffIds = new Set(nextEligibleStaff.map((staff) => Number(staff.id)));
                setEligibleStaffIds(nextEligibleStaffIds);
                setEligibleStaffOptions(nextEligibleStaff);

                if (currentStep === 3 && selectedStaff && selectedStaff !== 'any' && !nextEligibleStaffIds.has(Number(selectedStaff.id))) {
                    setSelectedStaff(null);
                    updateSelectedTime('');
                }

                if (currentStep === 3 && selectedTime) {
                    const found = findSlotForSelection(nextSlots, selectedTime, selectedStaff);
                    if (!found) updateSelectedTime('');
                }
            } catch (error) {
                if (cancelled) return;

                setTimeSlots([]);
                setEligibleStaffIds(null);
                setEligibleStaffOptions(null);
                setAvailabilityError(error?.message || 'Available times could not be loaded.');
                if (currentStep === 3) {
                    updateSelectedTime('');
                }
            } finally {
                if (!cancelled) setLoadingAvailability(false);
            }
        }

        loadAvailability();

        return () => {
            cancelled = true;
        };
    }, [currentStep, activeParticipantIndex, selectedDate, selectedTime, selectedStaff, selectedServices, salon, heldBooking?.id, heldBooking?.holdExpiresAt, participantCount, bookingMode, holdReleaseVersion]);

    useEffect(() => {
        let cancelled = false;

        async function loadEligibleStaffPreview() {
            if (!salon || selectedServices.length === 0) {
                if (selectedServices.length === 0) {
                    setEligibleStaffOptions(null);
                    setEligibleStaffIds(null);
                }
                setLoadingEligibleStaff(false);
                return;
            }

            setLoadingEligibleStaff(true);
            try {
                const availability = await checkCustomerBookingEligibleStaff({
                    branchId: salon.id,
                    serviceIds: selectedServices.map((service) => service.id),
                    bookingDate: null,
                    staffId: null,
                });

                if (cancelled) return;

                const nextEligibleStaff = availability.eligible_staff || [];
                setEligibleStaffOptions(nextEligibleStaff);
                setEligibleStaffIds(new Set(nextEligibleStaff.map((staff) => Number(staff.id))));
            } catch {
                if (cancelled) return;
                setEligibleStaffOptions(null);
                setEligibleStaffIds(null);
            } finally {
                if (!cancelled) setLoadingEligibleStaff(false);
            }
        }

        loadEligibleStaffPreview();

        return () => {
            cancelled = true;
        };
    }, [selectedServices, salon]);

    useEffect(() => {
        if (!selectedStaff || selectedStaff === 'any') return;
        if (staffCanServeServices(selectedStaff, selectedServices)) return;

        setSelectedStaff(null);
        setSelectedDate('');
        updateSelectedTime('');
    }, [selectedServices, selectedStaff]);

    useEffect(() => {
        if (selectedStaff !== 'any') return;

        const hasEligibleProfessional = (salon?.staff || []).some((member) => staffCanServeServices(member, selectedServices));
        if (hasEligibleProfessional) return;

        setSelectedStaff(null);
        setSelectedDate('');
        updateSelectedTime('');
    }, [salon, selectedServices, selectedStaff]);

    useEffect(() => {
        if (!selectedDate) {
            setTimeSlots([]);
            setAvailabilityError('');
            setEligibleStaffIds(null);
        }
    }, [selectedDate, selectedStaff, salon]);

    const hasBookingProgress = currentStep > 1
        || selectedServices.length > 0
        || selectedAddons.length > 0
        || Boolean(selectedStaff)
        || Boolean(selectedDate)
        || Boolean(selectedTime || selectedTimeRef.current);
    const persistedProgressStep = Math.max(
        savedProgressStep,
        currentStep,
        deriveBookingProgressStep({
            services: selectedServices,
            staff: selectedStaff,
            date: selectedDate,
            time: selectedTime || selectedTimeRef.current,
        })
    );
    const shouldWarnBeforeExit = Boolean(salon && hasBookingProgress);

    function pushExitGuardState() {
        if (typeof window === 'undefined') return;

        const currentState = typeof window.history.state === 'object' && window.history.state !== null
            ? window.history.state
            : {};
        window.history.pushState({ ...currentState, bookingExitGuard: true }, '', window.location.href);
        exitGuardArmedRef.current = true;
    }

    useEffect(() => {
        shouldWarnBeforeExitRef.current = shouldWarnBeforeExit;
        if (salon) {
            exitTargetPathRef.current = getSalonPath(salon);
        }
    }, [salon, shouldWarnBeforeExit]);

    useEffect(() => {
        if (!shouldWarnBeforeExit || exitGuardArmedRef.current) return;

        pushExitGuardState();
    }, [shouldWarnBeforeExit]);

    useEffect(() => {
        if (typeof window === 'undefined') return undefined;

        const targetPathFromAnchor = (anchor) => {
            const rawHref = anchor?.getAttribute('href');
            if (!rawHref || rawHref.startsWith('#')) return null;
            if (/^(mailto:|tel:|javascript:)/i.test(rawHref)) return null;

            const url = new URL(rawHref, window.location.href);
            if (url.origin !== window.location.origin) return null;

            const targetPath = `${url.pathname}${url.search}${url.hash}`;
            const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;

            return targetPath === currentPath ? null : targetPath;
        };

        const handleDocumentClick = (event) => {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
                || allowExitRef.current
                || !shouldWarnBeforeExitRef.current
            ) {
                return;
            }

            const anchor = event.target?.closest?.('a[href]');
            const targetPath = targetPathFromAnchor(anchor);
            if (!targetPath) return;

            event.preventDefault();
            exitTargetPathRef.current = targetPath;
            setShowExitDialog(true);
        };

        const handlePopState = () => {
            if (allowExitRef.current) return;

            if (!shouldWarnBeforeExitRef.current) {
                exitGuardArmedRef.current = false;
                return;
            }

            pushExitGuardState();
            setShowExitDialog(true);
        };

        const handleBeforeUnload = (event) => {
            if (!shouldWarnBeforeExitRef.current || allowExitRef.current) return;

            event.preventDefault();
            event.returnValue = '';
        };

        document.addEventListener('click', handleDocumentClick, true);
        window.addEventListener('popstate', handlePopState);
        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            document.removeEventListener('click', handleDocumentClick, true);
            window.removeEventListener('popstate', handlePopState);
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, []);

    useEffect(() => {
        if (!salon || !hasBookingProgress || allowExitRef.current || bookingCompletedRef.current) {
            return undefined;
        }

        const nextStaffAdjustment = getStaffPriceAdjustmentValue(selectedStaff);
        const nextSubtotal = selectedServices.reduce((sum, service) => sum + (service.discountPrice || service.price), 0)
            + selectedAddons.reduce((sum, addon) => sum + addon.price, 0)
            + nextStaffAdjustment;
        const nextDuration = selectedServices.reduce((sum, service) => sum + service.duration, 0)
            + selectedAddons.reduce((sum, addon) => sum + addon.duration, 0);
        const draft = {
            salonId: salon.id,
            salonSlug: getSalonRouteSlug(salon),
            salonName: salon.name,
            salonImage: salon.image,
            salonAddress: salon.address,
            salonRating: salon.rating,
            salonReviews: salon.reviews,
            availableServices: normalizeBookingServices(salon.services || []),
            services: selectedServices,
            addons: selectedAddons,
            staff: selectedStaff,
            staffAdjustment: nextStaffAdjustment,
            date: selectedDate,
            time: selectedTime || heldBooking?.time || selectedTimeRef.current,
            duration: nextDuration,
            subtotal: nextSubtotal,
            discount: 0,
            total: nextSubtotal,
            currentStep: currentStep === 4 && !heldBooking?.id ? 3 : persistedProgressStep,
            holdBookingId: heldBooking?.id || null,
            holdStartedAt: heldBooking?.holdStartedAt || null,
            holdExpiresAt: heldBooking?.holdExpiresAt || null,
        };

        saveBookingDraft(draft);
    }, [
        salon,
        hasBookingProgress,
        persistedProgressStep,
        currentStep,
        selectedServices,
        selectedAddons,
        selectedStaff,
        selectedDate,
        selectedTime,
        heldBooking,
    ]);

    useEffect(() => {
        const hasHeldTime = Boolean(selectedTime || heldBooking?.time || selectedTimeRef.current);
        if (!heldBooking?.id || (selectedDate && hasHeldTime)) return;

        releaseHeldBooking();
    }, [heldBooking?.id, heldBooking?.time, selectedDate, selectedTime]);

    useEffect(() => {
        if (currentStep !== 4 || bookingCompletedRef.current) return;

        const timeForConfirm = selectedTime || heldBooking?.time || selectedTimeRef.current;
        if (timeForConfirm && heldBooking?.id) return;

        setCurrentStep(3);
        setHoldError('Please choose a time again.');
    }, [currentStep, selectedTime, heldBooking?.id, heldBooking?.time]);

    useEffect(() => {
        if (!salon || currentStep !== 4 || !heldBooking?.id || submittingBooking || bookingCompletedRef.current) {
            return undefined;
        }

        let cancelled = false;
        let renewing = false;

        const renewHold = async () => {
            if (cancelled || renewing || bookingCompletedRef.current) return;

            renewing = true;

            try {
                const draft = createCurrentBookingDraft({ currentStep: 4 });
                const extendedHold = await extendCustomerBookingHold(heldBooking.id, draft);

                if (cancelled || bookingCompletedRef.current) return;

                const nextHold = {
                    ...heldBooking,
                    ...extendedHold,
                    id: extendedHold.id || heldBooking.id,
                    date: extendedHold.date || heldBooking.date || selectedDate,
                    time: extendedHold.time || heldBooking.time || selectedTimeRef.current,
                    holdStartedAt: extendedHold.holdStartedAt || heldBooking.holdStartedAt || null,
                    holdExpiresAt: extendedHold.holdExpiresAt || heldBooking.holdExpiresAt || null,
                };

                selectedTimeRef.current = nextHold.time || '';
                setHeldBooking(nextHold);
                saveBookingDraft(createCurrentBookingDraft({
                    currentStep: 4,
                    time: nextHold.time,
                    holdBookingId: nextHold.id,
                    holdStartedAt: nextHold.holdStartedAt,
                    holdExpiresAt: nextHold.holdExpiresAt,
                }));
            } catch (error) {
                if (cancelled || bookingCompletedRef.current) return;

                invalidateActiveHoldSelection();
                setHeldBooking(null);
                updateSelectedTime('');
                setCurrentStep(3);
                setHoldError(error?.message || 'Your booking hold has expired. Please choose a time again.');
                saveBookingDraft(createCurrentBookingDraft({
                    time: '',
                    currentStep: 3,
                    holdBookingId: null,
                    holdStartedAt: null,
                    holdExpiresAt: null,
                }));
            } finally {
                renewing = false;
            }
        };

        const holdExpiresAt = new Date(heldBooking.holdExpiresAt || '').getTime();
        if (!Number.isFinite(holdExpiresAt) || holdExpiresAt - Date.now() < 90000) {
            renewHold();
        }

        const timer = window.setInterval(renewHold, 60000);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [
        currentStep,
        heldBooking?.id,
        heldBooking?.holdExpiresAt,
        salon,
        selectedDate,
        submittingBooking,
    ]);

    if (!salon) {
        return (
            <div className="fresh-landing booking-route-shell">
                <main className="booking-loading-state" aria-busy="true" aria-label="Preparing booking">
                    <div className="booking-loading-indicator" aria-hidden="true">
                        <span />
                        <span />
                        <span />
                    </div>
                </main>
            </div>
        );
    }

    // Add-ons list for currently selected services
    const availableAddons = [];
    selectedServices.forEach(service => {
        const addonsForService = mockAddons[service.id];
        if (addonsForService) {
            addonsForService.forEach(addon => {
                // Prevent duplicate add-ons if multiple services trigger same ID (unlikely but safe)
                if (!availableAddons.some(a => a.id === addon.id)) {
                    availableAddons.push({
                        ...addon,
                        parentService: service.name
                    });
                }
            });
        }
    });

    const serviceCategories = ['Featured', ...new Set(salon.services.map(service => service.category).filter(Boolean))].slice(0, 5);
    const filteredServices = activeCategory === 'Featured'
        ? salon.services.filter(service => service.popular).concat(salon.services.filter(service => !service.popular)).filter((service, index, list) => list.findIndex(item => item.id === service.id) === index)
        : salon.services.filter(service => service.category === activeCategory);
    const hasEligibleStaffOptions = Array.isArray(eligibleStaffOptions) && eligibleStaffOptions.length > 0;
    const professionalSource = hasEligibleStaffOptions ? eligibleStaffOptions : (salon.staff || []);
    const shouldFilterByEligibleIds = hasEligibleStaffOptions && eligibleStaffIds?.size > 0;
    const baseProfessionalOptions = professionalSource
        .filter((member) => staffCanServeServices(member, selectedServices))
        .filter((member) => !shouldFilterByEligibleIds || eligibleStaffIds.has(Number(member.id)));
    const professionalOptions = selectedStaff
        && selectedStaff !== 'any'
        && staffCanServeServices(selectedStaff, selectedServices)
        && !baseProfessionalOptions.some((member) => Number(member.id) === Number(selectedStaff.id))
        ? [selectedStaff, ...baseProfessionalOptions]
        : baseProfessionalOptions;
    const salonImageSource = validImageSource(salon.image);
    const findStaffForSlot = (slot, fallbackStaff = professionalOptions) => {
        if (!slot?.staffId) return null;

        return (fallbackStaff || []).find((member) => Number(member.id) === Number(slot.staffId))
            || (salon.staff || []).find((member) => Number(member.id) === Number(slot.staffId))
            || null;
    };
    const findSlotForSelection = (slots = timeSlots, time = selectedTime, staff = selectedStaff, { requireAvailable = true } = {}) => (
        (slots || []).find((slot) => slot.start === time
            && (!requireAvailable || slot.status !== 'Not available')
            && slotMatchesStaff(slot, staff)) || null
    );
    const timeStepError = availabilityError || holdError;
    const stepTitles = {
        1: 'Select services',
        2: 'Select professional',
        3: 'Select time',
        4: 'Confirm booking',
    };

    const participantSelectionsWithCurrent = (overrides = {}) => {
        const nextSelections = Array.from({ length: participantCount }, (_, index) => ({
            ...createEmptyParticipantSelection(index + 1),
            ...(participantSelectionsRef.current[index] || {}),
            position: index + 1,
        }));

        if (Object.keys(overrides).length > 0) {
            nextSelections[activeParticipantIndex] = {
                ...nextSelections[activeParticipantIndex],
                ...overrides,
                position: activeParticipantIndex + 1,
                ...(Object.prototype.hasOwnProperty.call(overrides, 'time')
                    ? { time: String(overrides.time || '').slice(0, 5) }
                    : {}),
            };
        }

        return nextSelections;
    };

    const updateActiveParticipantSelection = (overrides = {}) => {
        const nextSelections = participantSelectionsWithCurrent(overrides);
        return commitParticipantSelections(nextSelections);
    };

    const switchActiveParticipant = (index) => {
        const nextIndex = Math.min(participantCount - 1, Math.max(0, Number(index || 0)));
        if (nextIndex === activeParticipantIndex) return;

        cancelTimeValidation();
        const nextSelections = participantSelectionsWithCurrent();
        const target = nextSelections[nextIndex] || createEmptyParticipantSelection(nextIndex + 1);

        commitParticipantSelections(nextSelections);
        setActiveParticipantIndex(nextIndex);
        setSelectedServices(target.services || []);
        setSelectedAddons(target.addons || []);
        setSelectedStaff(target.staff || null);
        setSelectedDate(target.date || '');
        selectedTimeRef.current = String(target.time || '').slice(0, 5);
        setSelectedTime(selectedTimeRef.current);
        setAvailabilityError('');
        setHoldError('');
        setTimeSlots([]);

        saveBookingDraft(createCurrentBookingDraft({
            services: target.services || [],
            addons: target.addons || [],
            staff: target.staff || null,
            date: target.date || '',
            time: target.time || '',
            participantSelections: nextSelections,
        }));
    };

    const toggleAddon = (addon) => {
        cancelTimeValidation();
        invalidateActiveHoldSelection();
        if (heldBooking?.id) {
            releaseHeldBooking();
        }

        const nextAddons = selectedAddons.find(a => a.id === addon.id)
            ? selectedAddons.filter(a => a.id !== addon.id)
            : [...selectedAddons, addon];
        setSelectedAddons(nextAddons);
        updateActiveParticipantSelection({ addons: nextAddons });
    };

    const pingBookingStep = (event, overrides = {}) => {
        pingCustomerBookingInteraction({
            event,
            branchId: salon?.id,
            serviceIds: (overrides.services ?? selectedServices).map((service) => service.id),
            staffId: overrides.staff === 'any'
                ? null
                : (overrides.staff?.id ?? (selectedStaff && selectedStaff !== 'any' ? selectedStaff.id : null)),
            bookingDate: (overrides.date ?? selectedDate) || null,
            startTime: (overrides.time ?? selectedTime) || null,
        });
    };

    const toggleService = (service) => {
        cancelTimeValidation();
        invalidateActiveHoldSelection();
        if (heldBooking?.id) {
            releaseHeldBooking();
        }

        let nextServices;

        let nextAddons = selectedAddons;
        if (selectedServices.find(s => s.id === service.id)) {
            nextServices = selectedServices.filter(s => s.id !== service.id);
            setSelectedServices(nextServices);
            // Also clean up any add-ons linked to this service
            const remainingServiceIds = nextServices.map(s => s.id);
            nextAddons = selectedAddons.filter(addon => {
                // Find if this addon belongs to any remaining services
                return remainingServiceIds.some(sid => {
                    const parentAddons = mockAddons[sid] || [];
                    return parentAddons.some(pa => pa.id === addon.id);
                });
            });
            setSelectedAddons(nextAddons);
        } else {
            nextServices = [...selectedServices, service];
            setSelectedServices(nextServices);
        }

        const nextStaff = selectedStaff && selectedStaff !== 'any' && !staffCanServeServices(selectedStaff, nextServices)
            ? null
            : selectedStaff;
        if (nextStaff !== selectedStaff) setSelectedStaff(nextStaff);

        if (selectedDate || selectedTime) {
            setSelectedDate('');
            updateSelectedTime('');
        }

        updateActiveParticipantSelection({
            services: nextServices,
            addons: nextAddons,
            staff: nextStaff,
            date: '',
            time: '',
        });
    };

    const getStaffPriceAdjustment = getStaffPriceAdjustmentValue;

    // Calculations
    const servicesSubtotal = selectedServices.reduce((sum, s) => sum + (s.discountPrice || s.price), 0);
    const addonsSubtotal = selectedAddons.reduce((sum, a) => sum + a.price, 0);
    const staffAdjustment = getStaffPriceAdjustment(selectedStaff);
    const currentParticipantDuration = selectedServices.reduce((sum, s) => sum + s.duration, 0)
        + selectedAddons.reduce((sum, a) => sum + a.duration, 0);
    const effectiveParticipantSelections = bookingMode === 'group'
        ? participantSelections
        : [];
    const groupSelectionTotals = effectiveParticipantSelections.reduce((totals, selection) => ({
        subtotal: totals.subtotal
            + (selection.services || []).reduce((sum, service) => sum + (service.discountPrice || service.price), 0)
            + (selection.addons || []).reduce((sum, addon) => sum + addon.price, 0)
            + getStaffPriceAdjustment(selection.staff),
        duration: totals.duration
            + (selection.services || []).reduce((sum, service) => sum + service.duration, 0)
            + (selection.addons || []).reduce((sum, addon) => sum + addon.duration, 0),
        staffAdjustment: totals.staffAdjustment + getStaffPriceAdjustment(selection.staff),
    }), { subtotal: 0, duration: 0, staffAdjustment: 0 });
    const subtotal = bookingMode === 'group'
        ? groupSelectionTotals.subtotal
        : servicesSubtotal + addonsSubtotal + staffAdjustment;
    const totalDuration = bookingMode === 'group'
        ? groupSelectionTotals.duration
        : currentParticipantDuration;
    const totalStaffAdjustment = bookingMode === 'group'
        ? groupSelectionTotals.staffAdjustment
        : staffAdjustment;
    const bookingTimeToMinutes = (time) => {
        const [hours, minutes] = String(time || '').slice(0, 5).split(':').map(Number);
        return Number.isFinite(hours) && Number.isFinite(minutes) ? (hours * 60) + minutes : null;
    };
    const participantSelectionDuration = (selection) => (
        (selection.services || []).reduce((sum, service) => sum + Number(service.duration || 0), 0)
        + (selection.addons || []).reduce((sum, addon) => sum + Number(addon.duration || 0), 0)
    );
    const slotConflictsWithOtherParticipant = (slot, staff = selectedStaff) => {
        if (bookingMode !== 'group' || !selectedDate || !slot?.start) return false;

        const candidateStaffId = staff && staff !== 'any'
            ? Number(staff.id)
            : Number(slot.staffId);
        const candidateStart = bookingTimeToMinutes(slot.start);
        const candidateEnd = candidateStart === null ? null : candidateStart + currentParticipantDuration;

        if (!Number.isInteger(candidateStaffId) || candidateStaffId <= 0 || candidateStart === null || candidateEnd === null) {
            return false;
        }

        return effectiveParticipantSelections.some((selection, index) => {
            if (index === activeParticipantIndex || !selection.date || !selection.time || selection.date !== selectedDate) {
                return false;
            }

            const otherStaffId = selection.staff && selection.staff !== 'any'
                ? Number(selection.staff.id)
                : null;
            if (!Number.isInteger(otherStaffId) || otherStaffId !== candidateStaffId) return false;

            const otherStart = bookingTimeToMinutes(selection.time);
            const otherEnd = otherStart === null
                ? null
                : otherStart + participantSelectionDuration(selection);

            return otherStart !== null
                && otherEnd !== null
                && candidateStart < otherEnd
                && candidateEnd > otherStart;
        });
    };
    const discountAmount = Number(appliedVoucher?.discountAmount || 0);
    const totalToPay = Math.max(0, subtotal - discountAmount);
    const paymentOptions = [
        { value: 'QRIS', title: 'QRIS', desc: 'GoPay, OVO, ShopeePay, Dana, LinkAja' },
        { value: 'Bank Transfer', title: 'Virtual Account', desc: 'Mandiri, BCA, BNI, BRI' },
        { value: 'Pay at Venue', title: 'Bayar di Tempat', desc: 'Bayar langsung setelah treatment' },
    ];
    const selectedStaffName = !selectedStaff
        ? 'Not selected'
        : selectedStaff === 'any'
            ? 'Siapa Saja'
            : selectedStaff.name;
    const selectedBookingTime = selectedTime || heldBooking?.time || (currentStep === 4 ? selectedTimeRef.current : '');

    const createCurrentBookingDraft = (overrides = {}) => {
        const draftServices = overrides.services ?? selectedServices;
        const draftAddons = overrides.addons ?? selectedAddons;
        const draftStaff = overrides.staff ?? selectedStaff;
        const hasDateOverride = Object.prototype.hasOwnProperty.call(overrides, 'date');
        const hasTimeOverride = Object.prototype.hasOwnProperty.call(overrides, 'time');
        const draftDate = hasDateOverride ? overrides.date : (selectedDate || heldBooking?.date || '');
        const draftTime = hasTimeOverride ? overrides.time : (selectedTime || heldBooking?.time || selectedTimeRef.current || '');
        const draftStaffAdjustment = getStaffPriceAdjustment(draftStaff);
        const draftParticipantCount = Math.min(5, Math.max(1, Number(overrides.participantCount ?? participantCount)));
        const draftGuests = overrides.guests ?? guests;
        const draftBookingMode = overrides.bookingMode ?? bookingMode ?? (draftParticipantCount > 1 ? 'group' : 'personal');
        const draftParticipantSelections = overrides.participantSelections
            ?? (draftBookingMode === 'group' ? participantSelectionsRef.current : []);
        const sharedSubtotal = draftServices.reduce((sum, service) => sum + (service.discountPrice || service.price), 0)
            + draftAddons.reduce((sum, addon) => sum + addon.price, 0)
            + draftStaffAdjustment;
        const groupDraftTotals = draftParticipantSelections.reduce((totals, selection) => ({
            subtotal: totals.subtotal
                + (selection.services || []).reduce((sum, service) => sum + (service.discountPrice || service.price), 0)
                + (selection.addons || []).reduce((sum, addon) => sum + addon.price, 0)
                + getStaffPriceAdjustment(selection.staff),
            duration: totals.duration
                + (selection.services || []).reduce((sum, service) => sum + service.duration, 0)
                + (selection.addons || []).reduce((sum, addon) => sum + addon.duration, 0),
        }), { subtotal: 0, duration: 0 });
        const draftSubtotal = draftBookingMode === 'group' ? groupDraftTotals.subtotal : sharedSubtotal;
        const draftDuration = draftBookingMode === 'group'
            ? groupDraftTotals.duration
            : draftServices.reduce((sum, service) => sum + service.duration, 0)
                + draftAddons.reduce((sum, addon) => sum + addon.duration, 0);
        const draftProgressStep = Math.max(
            overrides.currentStep ?? persistedProgressStep,
            deriveBookingProgressStep({
                services: draftServices,
                staff: draftStaff,
                date: draftDate,
                time: draftTime,
            })
        );

        return {
            salonId: salon.id,
            salonSlug: getSalonRouteSlug(salon),
            salonName: salon.name,
            salonImage: salon.image,
            salonAddress: salon.address,
            salonRating: salon.rating,
            salonReviews: salon.reviews,
            availableServices: normalizeBookingServices(salon.services || []),
            services: draftServices,
            addons: draftAddons,
            staff: draftStaff,
            staffAdjustment: draftStaffAdjustment,
            date: draftDate,
            time: draftTime,
            duration: draftDuration,
            subtotal: draftSubtotal,
            discount: 0,
            total: draftSubtotal,
            participantCount: draftParticipantCount,
            guests: draftGuests,
            participantSelections: draftParticipantSelections,
            bookingMode: draftBookingMode,
            bookingModeSelected: overrides.bookingModeSelected ?? bookingModeSelected,
            currentStep: draftProgressStep,
            holdBookingId: overrides.holdBookingId ?? heldBooking?.id ?? null,
            holdStartedAt: overrides.holdStartedAt ?? heldBooking?.holdStartedAt ?? null,
            holdExpiresAt: overrides.holdExpiresAt ?? heldBooking?.holdExpiresAt ?? null,
        };
    };

    const holdSlotKey = (draft) => {
        const serviceKey = (draft.services || []).map((service) => service.id).join(',');
        const addonKey = (draft.addons || []).map((addon) => addon.id).join(',');
        const staffKey = draft.staff && draft.staff !== 'any' ? draft.staff.id : 'any';
        const participantKey = (draft.participantSelections || []).map((selection) => [
            selection.position,
            (selection.services || []).map((service) => service.id).join(','),
            selection.staff && selection.staff !== 'any' ? selection.staff.id : 'any',
            selection.date || '',
            selection.time || '',
        ].join(':')).join(';');

        return [
            draft.salonId,
            draft.date || '',
            draft.time || '',
            staffKey,
            serviceKey,
            addonKey,
            draft.participantCount || 1,
            participantKey,
        ].join('|');
    };

    const invalidateActiveHoldSelection = () => {
        holdSelectionVersionRef.current += 1;
        activeHoldRequestRef.current = null;
        setHoldingBooking(false);
    };

    const cancelTimeValidation = () => {
        timeValidationRequestRef.current += 1;
        setValidatingTime('');
    };

    const updateSelectedTime = (time) => {
        const nextTime = String(time || '').slice(0, 5);
        selectedTimeRef.current = nextTime;
        setSelectedTime(nextTime);
    };

    const releaseHeldBooking = async () => {
        const bookingId = heldBooking?.id;
        if (!bookingId) return false;

        invalidateActiveHoldSelection();
        setHeldBooking(null);
        saveBookingDraft(createCurrentBookingDraft({
            holdBookingId: null,
            holdStartedAt: null,
            holdExpiresAt: null,
        }));
        try {
            await cancelCustomerBooking(bookingId);
            // The first availability request may run before the cancellation
            // returns. Bump this version so the time slots are fetched again
            // only after the server has released the hold.
            setHoldReleaseVersion((version) => version + 1);
            return true;
        } catch {
            return false;
        }
    };

    const changeParticipantCount = async (value) => {
        const nextCount = Math.min(5, Math.max(1, Number(value || 1)));
        if (nextCount === participantCount) return;

        await releaseHeldBooking();
        const nextGuests = Array.from({ length: nextCount - 1 }, (_, index) => ({
            ...createEmptyGuest(),
            ...(guests[index] || {}),
        }));
        const selectionsBeforeResize = participantSelectionsWithCurrent();
        const nextParticipantSelections = Array.from({ length: nextCount }, (_, index) => ({
            ...createEmptyParticipantSelection(index + 1),
            ...(selectionsBeforeResize[index] || {}),
            position: index + 1,
        }));
        const primarySelection = nextParticipantSelections[0];

        setParticipantCount(nextCount);
        setGuests(nextGuests);
        commitParticipantSelections(nextParticipantSelections);
        setActiveParticipantIndex(0);
        setSelectedServices(primarySelection.services || []);
        setSelectedAddons(primarySelection.addons || []);
        setSelectedStaff(primarySelection.staff || null);
        setSelectedDate(primarySelection.date || '');
        updateSelectedTime(primarySelection.time || '');
        setHoldError('');
        setAvailabilityError('');
        saveBookingDraft(createCurrentBookingDraft({
            participantCount: nextCount,
            guests: nextGuests,
            services: primarySelection.services || [],
            addons: primarySelection.addons || [],
            staff: primarySelection.staff || null,
            date: primarySelection.date || '',
            time: primarySelection.time || '',
            participantSelections: nextParticipantSelections,
            currentStep: 1,
            holdBookingId: null,
            holdStartedAt: null,
            holdExpiresAt: null,
        }));
    };

    const updateGuest = (index, field, value) => {
        const nextGuests = guests.map((guest, guestIndex) => (
            guestIndex === index ? { ...guest, [field]: value } : guest
        ));

        setGuests(nextGuests);
        saveBookingDraft(createCurrentBookingDraft({ guests: nextGuests }));
    };

    const selectBookingMode = async (mode) => {
        const nextMode = mode === 'group' ? 'group' : 'personal';
        const nextCount = 1;
        const nextGuests = nextMode === 'group'
            ? Array.from({ length: nextCount - 1 }, (_, index) => ({
                ...createEmptyGuest(),
                ...(guests[index] || {}),
            }))
            : [];
        const selectionsBeforeModeChange = participantSelectionsWithCurrent();
        const nextParticipantSelections = Array.from({ length: nextCount }, (_, index) => ({
            ...createEmptyParticipantSelection(index + 1),
            ...(selectionsBeforeModeChange[index] || {}),
            position: index + 1,
        }));

        setBookingMode(nextMode);
        await changeParticipantCount(nextCount);
        saveBookingDraft(createCurrentBookingDraft({
            bookingMode: nextMode,
            bookingModeSelected: false,
            participantCount: nextCount,
            guests: nextGuests,
            participantSelections: nextParticipantSelections,
        }));
    };

    const continueFromBookingMode = async () => {
        if (!bookingMode) return;

        await releaseHeldBooking();
        const nextParticipantSelections = Array.from({ length: participantCount }, (_, index) => ({
            ...createEmptyParticipantSelection(index + 1),
            ...(participantSelectionsWithCurrent()[index] || {}),
            position: index + 1,
            staff: null,
            date: '',
            time: '',
        }));
        commitParticipantSelections(nextParticipantSelections);
        setActiveParticipantIndex(0);
        setSelectedServices(nextParticipantSelections[0]?.services || []);
        setSelectedAddons(nextParticipantSelections[0]?.addons || []);
        setSelectedStaff(null);
        setSelectedDate('');
        updateSelectedTime('');
        setCurrentStep(1);
        setSavedProgressStep(1);
        setBookingModeSelected(true);
        saveBookingDraft(createCurrentBookingDraft({
            bookingMode,
            bookingModeSelected: true,
            staff: null,
            date: '',
            time: '',
            participantSelections: nextParticipantSelections,
            currentStep: 1,
            holdBookingId: null,
            holdStartedAt: null,
            holdExpiresAt: null,
        }));
    };

    const ensureActiveHold = async ({ currentStep: holdStep = 4, redirectIfGuest = true, silent = false, draftOverrides = {}, forceNew = false } = {}) => {
        const draft = createCurrentBookingDraft({ ...draftOverrides, currentStep: holdStep });
        saveBookingDraft(draft);
        const requestKey = holdSlotKey(draft);
        const requestVersion = holdSelectionVersionRef.current;

        const heldUntil = new Date(heldBooking?.holdExpiresAt || '').getTime();
        const holdMatchesSelectedSlot = !forceNew
            && heldBooking?.id
            && heldBooking.date === draft.date
            && heldBooking.time === draft.time
            && Number.isFinite(heldUntil)
            && heldUntil > Date.now();

        if (holdMatchesSelectedSlot) {
            return heldBooking;
        }

        if (activeHoldRequestRef.current?.key === requestKey) {
            try {
                return await activeHoldRequestRef.current.promise;
            } catch (error) {
                const message = error?.message || 'This time could not be held. Please choose another time.';
                setHoldError(message);
                updateSelectedTime('');
                setCurrentStep(3);
                saveBookingDraft(createCurrentBookingDraft({
                    time: '',
                    currentStep: 3,
                    holdBookingId: null,
                    holdStartedAt: null,
                    holdExpiresAt: null,
                }));
                if (!silent) {
                    alert(message);
                }
                return null;
            }
        }

        if (!getSessionUser().loggedIn) {
            if (redirectIfGuest) {
                router.push(`/auth?next=${encodeURIComponent(`/booking/${getSalonRouteSlug(salon)}`)}`);
            }
            return null;
        }

        setHoldingBooking(true);
        setHoldError('');

        const holdPromise = (async () => {
            const bookingHold = await holdCustomerBooking({ draft });
            const stillSameSelection = holdSelectionVersionRef.current === requestVersion
                && holdSlotKey(createCurrentBookingDraft({ ...draftOverrides, currentStep: holdStep })) === requestKey;

            if (!stillSameSelection) {
                if (bookingHold?.id) {
                    cancelCustomerBooking(bookingHold.id).catch(() => {});
                }

                return null;
            }

            const normalizedHold = {
                ...bookingHold,
                date: bookingHold.date || draft.date,
                time: bookingHold.time || draft.time,
                holdStartedAt: bookingHold.holdStartedAt || null,
                holdExpiresAt: bookingHold.holdExpiresAt || null,
            };

            selectedTimeRef.current = normalizedHold.time || draft.time || '';
            setHeldBooking(normalizedHold);
            saveBookingDraft({
                ...draft,
                time: normalizedHold.time,
                holdBookingId: normalizedHold.id,
                holdStartedAt: normalizedHold.holdStartedAt,
                holdExpiresAt: normalizedHold.holdExpiresAt,
            });

            return normalizedHold;
        })();

        activeHoldRequestRef.current = {
            key: requestKey,
            promise: holdPromise,
        };

        try {
            return await holdPromise;
        } catch (error) {
            const message = error?.message || 'This time could not be held. Please choose another time.';
            setHoldError(message);
            updateSelectedTime('');
            setCurrentStep(3);
            saveBookingDraft({
                ...draft,
                time: '',
                currentStep: 3,
                holdBookingId: null,
                holdStartedAt: null,
                holdExpiresAt: null,
            });
            if (!silent) {
                alert(message);
            }
            return null;
        } finally {
            if (activeHoldRequestRef.current?.key === requestKey) {
                activeHoldRequestRef.current = null;
                setHoldingBooking(false);
            }
        }
    };

    const goToEditStep = async (step) => {
        await releaseHeldBooking();
        setHoldError('');
        setCurrentStep(step);
    };

    const selectStaff = (staff) => {
        cancelTimeValidation();
        invalidateActiveHoldSelection();
        if (heldBooking?.id) {
            releaseHeldBooking();
        }
        setSelectedStaff(staff);
        const nextParticipantSelections = updateActiveParticipantSelection({ staff, date: '', time: '' });
        setSavedProgressStep((step) => Math.max(step, 2));
        saveBookingDraft(createCurrentBookingDraft({
            staff,
            date: '',
            time: '',
            participantSelections: nextParticipantSelections,
            currentStep: 2,
            holdBookingId: null,
            holdStartedAt: null,
            holdExpiresAt: null,
        }));
    };

    const selectDate = (date) => {
        cancelTimeValidation();
        invalidateActiveHoldSelection();
        const shouldReleaseHold = Boolean(heldBooking?.id);
        if (shouldReleaseHold) {
            releaseHeldBooking();
        }
        setSelectedDate(date);
        updateSelectedTime('');
        const nextParticipantSelections = updateActiveParticipantSelection({ date, time: '' });
        setSavedProgressStep((step) => Math.max(step, 3));
        saveBookingDraft(createCurrentBookingDraft({
            date,
            time: '',
            participantSelections: nextParticipantSelections,
            currentStep: 3,
            holdBookingId: shouldReleaseHold ? null : undefined,
            holdStartedAt: shouldReleaseHold ? null : undefined,
            holdExpiresAt: shouldReleaseHold ? null : undefined,
        }));
    };

    const selectTime = async (time, selectedSlot = null) => {
        if (!salon || !selectedDate || selectedServices.length === 0) {
            setAvailabilityError('Lengkapi data dulu.');
            return;
        }

        const slotForSelection = selectedSlot
            || timeSlots.find((slot) => slot.start === time && slotMatchesStaff(slot, selectedStaff));
        if (slotConflictsWithOtherParticipant(slotForSelection, selectedStaff)) {
            setAvailabilityError('This time has already been selected by another participant with the same professional.');
            return;
        }

        const staffForTime = selectedStaff === 'any' && slotForSelection
            ? (findStaffForSlot(slotForSelection, eligibleStaffOptions || []) || selectedStaff)
            : selectedStaff;

        if (time !== selectedTime) {
            invalidateActiveHoldSelection();
        }
        const shouldReleaseHold = Boolean(heldBooking?.id && (heldBooking.date !== selectedDate || heldBooking.time !== time));
        if (shouldReleaseHold) {
            releaseHeldBooking();
        }

        if (staffForTime !== selectedStaff) setSelectedStaff(staffForTime);
        updateSelectedTime(time);
        const nextParticipantSelections = updateActiveParticipantSelection({ time, staff: staffForTime });
        setSavedProgressStep((step) => Math.max(step, 3));
        saveBookingDraft(createCurrentBookingDraft({
            staff: staffForTime,
            time,
            participantSelections: nextParticipantSelections,
            currentStep: 3,
            holdBookingId: null,
            holdStartedAt: null,
            holdExpiresAt: null,
        }));

        setAvailabilityError('');
        setHoldError('');

        const requestVersion = timeValidationRequestRef.current + 1;
        timeValidationRequestRef.current = requestVersion;
        setValidatingTime(time);

        checkCustomerBookingAvailability({
            branchId: salon.id,
            serviceIds: selectedServices.map((service) => service.id),
            bookingDate: selectedDate,
            staffId: staffForTime && staffForTime !== 'any' ? staffForTime.id : null,
            heldBookingId: heldBooking?.id && heldBooking.date === selectedDate && heldBooking.time === time
                ? heldBooking.id
                : null,
            participantCount: bookingMode === 'group' ? 1 : participantCount,
            force: true,
        }).then((availability) => {
            if (timeValidationRequestRef.current !== requestVersion) return;

            const nextSlots = availability.available_slots || [];
            const nextEligibleStaff = availability.eligible_staff || [];
            const nextEligibleStaffIds = new Set(nextEligibleStaff.map((staff) => Number(staff.id)));

            setTimeSlots(nextSlots);
            setEligibleStaffIds(nextEligibleStaffIds);
            setEligibleStaffOptions(nextEligibleStaff);

            if (staffForTime && staffForTime !== 'any' && !nextEligibleStaffIds.has(Number(staffForTime.id))) {
                setSelectedStaff(null);
                updateSelectedTime('');
                setAvailabilityError('This professional is unavailable. Please choose another professional or time.');
                saveBookingDraft(createCurrentBookingDraft({
                    staff: null,
                    time: '',
                    currentStep: 3,
                    holdBookingId: null,
                    holdStartedAt: null,
                    holdExpiresAt: null,
                }));
                return;
            }

            const slotStillAvailable = findSlotForSelection(nextSlots, time, staffForTime)
                || (staffForTime === 'any'
                    ? nextSlots.find((slot) => slot.start === time && slot.status !== 'Not available')
                    : null);

            if (!slotStillAvailable || slotConflictsWithOtherParticipant(slotStillAvailable, staffForTime)) {
                updateSelectedTime('');
                setAvailabilityError(slotStillAvailable
                    ? 'This time has already been selected by another participant with the same professional.'
                    : 'This time is no longer available. Please choose another one.');
                saveBookingDraft(createCurrentBookingDraft({
                    time: '',
                    currentStep: 3,
                    holdBookingId: null,
                    holdStartedAt: null,
                    holdExpiresAt: null,
                }));
                return;
            }

            setAvailabilityError('');
        }).catch(() => {
            if (timeValidationRequestRef.current !== requestVersion) return;
            setAvailabilityError('The latest availability could not be checked. We will check again when you continue.');
        }).finally(() => {
            if (timeValidationRequestRef.current === requestVersion) {
                setValidatingTime('');
            }
        });
    };

    const refreshSelectedSlotFromBackend = async (timeOverride = selectedTime || selectedTimeRef.current) => {
        const timeToValidate = String(timeOverride || '').slice(0, 5);

        if (!salon || !selectedDate || !timeToValidate || selectedServices.length === 0) {
            return null;
        }

        setLoadingAvailability(true);
        setAvailabilityError('');
        setHoldError('');

        try {
            const draft = getBookingDraft();
            const draftHoldExpiresAt = new Date(draft?.holdExpiresAt || '').getTime();
            const draftHeldBookingId = draft?.holdBookingId
                && Number.isFinite(draftHoldExpiresAt)
                && draftHoldExpiresAt > Date.now()
                ? draft.holdBookingId
                : null;
            const availability = await checkCustomerBookingAvailability({
                branchId: salon.id,
                    serviceIds: selectedServices.map((service) => service.id),
                    bookingDate: selectedDate,
                    staffId: selectedStaff && selectedStaff !== 'any' ? selectedStaff.id : null,
                    heldBookingId: heldBooking?.id || draftHeldBookingId || null,
                    participantCount: bookingMode === 'group' ? 1 : participantCount,
                    force: true,
                });
            const nextSlots = availability.available_slots || [];
            const nextEligibleStaff = availability.eligible_staff || [];
            const nextEligibleStaffIds = new Set(nextEligibleStaff.map((staff) => Number(staff.id)));

            setTimeSlots(nextSlots);
            setEligibleStaffIds(nextEligibleStaffIds);
            setEligibleStaffOptions(nextEligibleStaff);

            if (selectedStaff && selectedStaff !== 'any' && !nextEligibleStaffIds.has(Number(selectedStaff.id))) {
                const message = 'Professional tidak tersedia.';
                setSelectedStaff(null);
                updateSelectedTime('');
                setAvailabilityError(message);
                saveBookingDraft(createCurrentBookingDraft({
                    staff: null,
                    time: '',
                    currentStep: 3,
                    holdBookingId: null,
                    holdStartedAt: null,
                    holdExpiresAt: null,
                }));
                return null;
            }

            const slot = findSlotForSelection(nextSlots, timeToValidate, selectedStaff)
                || (selectedStaff === 'any'
                    ? nextSlots.find((item) => item.start === timeToValidate && item.status !== 'Not available')
                    : null);

            if (!slot) {
                const message = 'This time is not available.';
                updateSelectedTime('');
                setAvailabilityError(message);
                saveBookingDraft(createCurrentBookingDraft({
                    time: '',
                    currentStep: 3,
                    holdBookingId: null,
                    holdStartedAt: null,
                    holdExpiresAt: null,
                }));
                return null;
            }

            const backendStaff = findStaffForSlot(slot, nextEligibleStaff);
            const nextStaff = selectedStaff === 'any' && backendStaff
                ? backendStaff
                : (selectedStaff && selectedStaff !== 'any'
                    ? (nextEligibleStaff.find((staff) => Number(staff.id) === Number(selectedStaff.id)) || selectedStaff)
                    : selectedStaff);

            if (nextStaff !== selectedStaff) {
                setSelectedStaff(nextStaff);
            }

            return { slot, staff: nextStaff };
        } catch (error) {
            const message = 'This schedule is not available.';
            updateSelectedTime('');
            setAvailabilityError(message);
            saveBookingDraft(createCurrentBookingDraft({
                time: '',
                currentStep: 3,
                holdBookingId: null,
                holdStartedAt: null,
                holdExpiresAt: null,
            }));
            return null;
        } finally {
            setLoadingAvailability(false);
        }
    };

    const groupSelectionsForStep = () => participantSelectionsRef.current;

    const firstIncompleteGroupParticipant = (step, selections = groupSelectionsForStep()) => selections.findIndex((selection) => {
        if (step === 1) return !(selection.services || []).length;
        if (step === 2) return !selection.staff;
        if (step === 3) return !selection.date || !selection.time;
        return false;
    });

    const validateGroupParticipantSlots = async (selections) => {
        const validatedSelections = [];

        for (let index = 0; index < selections.length; index += 1) {
            const selection = selections[index];
            const availability = await checkCustomerBookingAvailability({
                branchId: salon.id,
                serviceIds: (selection.services || []).map((service) => service.id),
                bookingDate: selection.date,
                staffId: selection.staff && selection.staff !== 'any' ? selection.staff.id : null,
                heldBookingId: heldBooking?.id || null,
                participantCount: 1,
                force: true,
            });
            const slots = availability.available_slots || [];
            const slot = findSlotForSelection(slots, selection.time, selection.staff)
                || (selection.staff === 'any'
                    ? slots.find((item) => item.start === selection.time && item.status !== 'Not available')
                    : null);

            if (!slot) {
                throw new Error(`The schedule for participant ${index + 1} is no longer available. Please choose another time.`);
            }

            const availableStaff = availability.eligible_staff || [];
            const resolvedStaff = selection.staff === 'any'
                ? (findStaffForSlot(slot, availableStaff) || selection.staff)
                : (availableStaff.find((staff) => Number(staff.id) === Number(selection.staff?.id)) || selection.staff);

            const candidateDuration = participantSelectionDuration(selection);
            const candidateStart = bookingTimeToMinutes(slot.start);
            const hasGroupConflict = validatedSelections.some((otherSelection) => {
                if (otherSelection.date !== selection.date
                    || !resolvedStaff
                    || resolvedStaff === 'any'
                    || !otherSelection.staff
                    || otherSelection.staff === 'any'
                    || Number(otherSelection.staff.id) !== Number(resolvedStaff.id)) {
                    return false;
                }

                const otherStart = bookingTimeToMinutes(otherSelection.time);
                return candidateStart !== null
                    && otherStart !== null
                    && candidateStart < otherStart + participantSelectionDuration(otherSelection)
                    && candidateStart + candidateDuration > otherStart;
            });

            if (hasGroupConflict) {
                throw new Error(`The schedule for participant ${index + 1} overlaps with another participant using the same professional.`);
            }

            validatedSelections.push({
                ...selection,
                position: index + 1,
                staff: resolvedStaff,
                time: slot.start,
            });
        }

        return validatedSelections;
    };

    const handleNextStep = async () => {
        if (bookingMode === 'group' && currentStep <= 3) {
            const selections = groupSelectionsForStep();
            const incompleteIndex = firstIncompleteGroupParticipant(currentStep, selections);

            if (incompleteIndex >= 0) {
                switchActiveParticipant(incompleteIndex);
                const missingLabel = currentStep === 1
                    ? 'layanan'
                    : currentStep === 2
                        ? 'professional'
                        : 'tanggal dan jam';
                alert(`Please select ${missingLabel} for participant ${incompleteIndex + 1}.`);
                return;
            }

            if (currentStep === 1) {
                const primary = selections[0];
                commitParticipantSelections(selections);
                setActiveParticipantIndex(0);
                setSelectedServices(primary.services || []);
                setSelectedAddons(primary.addons || []);
                setSelectedStaff(primary.staff || null);
                setSelectedDate(primary.date || '');
                updateSelectedTime(primary.time || '');
                setSavedProgressStep((step) => Math.max(step, 2));
                setCurrentStep(2);
                setPreparingProfessionalStep(true);
                setLoadingEligibleStaff(true);

                try {
                    const availability = await checkCustomerBookingEligibleStaff({
                        branchId: salon.id,
                        serviceIds: (primary.services || []).map((service) => service.id),
                        bookingDate: null,
                        staffId: null,
                    });
                    const nextEligibleStaff = availability.eligible_staff || [];
                    setEligibleStaffOptions(nextEligibleStaff);
                    setEligibleStaffIds(new Set(nextEligibleStaff.map((staff) => Number(staff.id))));
                } catch {
                    setEligibleStaffOptions(null);
                    setEligibleStaffIds(null);
                } finally {
                    setLoadingEligibleStaff(false);
                    setPreparingProfessionalStep(false);
                }
                return;
            }

            if (currentStep === 2) {
                const primary = selections[0];
                commitParticipantSelections(selections);
                setActiveParticipantIndex(0);
                setSelectedServices(primary.services || []);
                setSelectedAddons(primary.addons || []);
                setSelectedStaff(primary.staff || null);
                setSelectedDate(primary.date || '');
                updateSelectedTime(primary.time || '');
                setSavedProgressStep((step) => Math.max(step, 3));
                setCurrentStep(3);
                return;
            }

            if (!getSessionUser().loggedIn) {
                saveBookingDraft(createCurrentBookingDraft({ participantSelections: selections, currentStep: 3 }));
                router.push(`/auth?next=${encodeURIComponent(`/booking/${getSalonRouteSlug(salon)}`)}`);
                return;
            }

            setHoldingBooking(true);
            setHoldError('');

            try {
                const validatedSelections = await validateGroupParticipantSlots(selections);
                const primary = validatedSelections[0];
                commitParticipantSelections(validatedSelections);
                setActiveParticipantIndex(0);
                setSelectedServices(primary.services || []);
                setSelectedAddons(primary.addons || []);
                setSelectedStaff(primary.staff || null);
                setSelectedDate(primary.date || '');
                updateSelectedTime(primary.time || '');

                const bookingHold = await ensureActiveHold({
                    currentStep: 4,
                    redirectIfGuest: false,
                    silent: true,
                    draftOverrides: {
                        services: primary.services || [],
                        addons: primary.addons || [],
                        staff: primary.staff || null,
                        date: primary.date || '',
                        time: primary.time || '',
                        participantSelections: validatedSelections,
                        holdBookingId: null,
                        holdStartedAt: null,
                        holdExpiresAt: null,
                    },
                });

                if (!bookingHold?.id) return;

                setHeldBooking(bookingHold);
                setSavedProgressStep((step) => Math.max(step, 4));
                saveBookingDraft(createCurrentBookingDraft({
                    services: primary.services || [],
                    addons: primary.addons || [],
                    staff: primary.staff || null,
                    date: primary.date || '',
                    time: primary.time || '',
                    participantSelections: validatedSelections,
                    currentStep: 4,
                    holdBookingId: bookingHold.id,
                    holdStartedAt: bookingHold.holdStartedAt,
                    holdExpiresAt: bookingHold.holdExpiresAt,
                }));
                setCurrentStep(4);
            } catch (error) {
                const message = error?.message || 'A participant’s schedule is no longer available.';
                setHoldError(message);
                alert(message);
            } finally {
                setHoldingBooking(false);
            }
            return;
        }

        if (currentStep === 1) {
            setSavedProgressStep((step) => Math.max(step, 2));
            setCurrentStep(2);
            setPreparingProfessionalStep(true);
            setLoadingEligibleStaff(true);

            const startedAt = Date.now();

            try {
                const availability = await checkCustomerBookingEligibleStaff({
                    branchId: salon.id,
                    serviceIds: selectedServices.map((service) => service.id),
                    bookingDate: null,
                    staffId: null,
                });
                const nextEligibleStaff = availability.eligible_staff || [];

                setEligibleStaffOptions(nextEligibleStaff);
                setEligibleStaffIds(new Set(nextEligibleStaff.map((staff) => Number(staff.id))));
            } catch {
                setEligibleStaffOptions(null);
                setEligibleStaffIds(null);
            } finally {
                const remainingDuration = 420 - (Date.now() - startedAt);
                if (remainingDuration > 0) {
                    await new Promise((resolve) => window.setTimeout(resolve, remainingDuration));
                }

                setLoadingEligibleStaff(false);
                setPreparingProfessionalStep(false);
            }
        } else if (currentStep === 2) {
            // Must select staff
            if (!selectedStaff) {
                alert('Silakan pilih specialist atau "Siapa Saja".');
                return;
            }
            if (professionalOptions.length === 0) {
            alert('No professional has the skills required for your selected services yet.');
                return;
            }
            setSavedProgressStep((step) => Math.max(step, 3));
            setCurrentStep(3);
        } else if (currentStep === 3) {
            // Must select date & time
            const timeForBooking = selectedTime || selectedTimeRef.current;

            if (!selectedDate || !timeForBooking) {
                alert('Silakan pilih tanggal dan jam yang tersedia.');
                return;
            }

            pingBookingStep('continue_to_confirm', { time: timeForBooking });

            const refreshedSelection = await refreshSelectedSlotFromBackend(timeForBooking);
            if (!refreshedSelection?.slot) {
                setCurrentStep(3);
                return;
            }

            const selectedSlot = refreshedSelection.slot;
            const nextStaff = refreshedSelection.staff || selectedStaff;
            const nextTime = selectedSlot.start;
            const draftOverrides = {
                staff: nextStaff,
                time: nextTime,
                holdBookingId: null,
                holdStartedAt: null,
                holdExpiresAt: null,
            };

            if (nextStaff !== selectedStaff) {
                setSelectedStaff(nextStaff);
            }
            if (nextTime && nextTime !== selectedTime) {
                updateSelectedTime(nextTime);
            }

            if (!getSessionUser().loggedIn) {
                router.push(`/auth?next=${encodeURIComponent(`/booking/${getSalonRouteSlug(salon)}`)}`);
                return;
            }

            const bookingHold = await ensureActiveHold({
                currentStep: 4,
                redirectIfGuest: false,
                silent: true,
                draftOverrides,
            });

            if (!bookingHold?.id) {
                setCurrentStep(3);
                return;
            }

            const lockedBookingHold = {
                ...bookingHold,
                date: bookingHold.date || selectedDate,
                time: bookingHold.time || nextTime,
                holdStartedAt: bookingHold.holdStartedAt || null,
                holdExpiresAt: bookingHold.holdExpiresAt || null,
            };
            selectedTimeRef.current = lockedBookingHold.time || nextTime;
            setHeldBooking(lockedBookingHold);
            setSavedProgressStep((step) => Math.max(step, 4));
            saveBookingDraft(createCurrentBookingDraft({
                ...draftOverrides,
                currentStep: 4,
                time: lockedBookingHold.time || nextTime,
                holdBookingId: lockedBookingHold.id,
                holdStartedAt: lockedBookingHold.holdStartedAt,
                holdExpiresAt: lockedBookingHold.holdExpiresAt,
            }));
            setCurrentStep(4);
        } else if (currentStep === 4) {
            handleConfirmBooking();
        }
    };

    const handlePrevStep = async () => {
        if (currentStep === 1 && bookingModeSelected) {
            setBookingModeSelected(false);
            saveBookingDraft(createCurrentBookingDraft({ bookingModeSelected: false, currentStep: 1 }));
            return;
        }

        if (currentStep > 1) {
            if (currentStep >= 3) {
                await releaseHeldBooking();
                setHoldError('');
            }

            if (currentStep === 3) {
                setCurrentStep(2);
                setPreparingProfessionalStep(true);
                setLoadingEligibleStaff(true);
                const startedAt = Date.now();

                try {
                    const availability = await checkCustomerBookingEligibleStaff({
                        branchId: salon.id,
                        serviceIds: selectedServices.map((service) => service.id),
                        bookingDate: null,
                        staffId: null,
                    });
                    const nextEligibleStaff = availability.eligible_staff || [];

                    setEligibleStaffOptions(nextEligibleStaff);
                    setEligibleStaffIds(new Set(nextEligibleStaff.map((staff) => Number(staff.id))));
                } catch {
                    setEligibleStaffOptions(null);
                    setEligibleStaffIds(null);
                } finally {
                    const remainingDuration = 420 - (Date.now() - startedAt);
                    if (remainingDuration > 0) {
                        await new Promise((resolve) => window.setTimeout(resolve, remainingDuration));
                    }

                    setLoadingEligibleStaff(false);
                    setPreparingProfessionalStep(false);
                }
                return;
            }

            setCurrentStep(currentStep - 1);
        } else {
            requestExitBooking();
        }
    };

    const navigateAfterBookingExit = (targetPath) => {
        if (typeof window === 'undefined' || !exitGuardArmedRef.current) {
            router.replace(targetPath);
            return;
        }

        const bookingUrl = window.location.href;
        const targetUrl = new URL(targetPath, bookingUrl);
        const targetLocation = `${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`;
        const finishAtTarget = () => {
            const currentLocation = `${window.location.pathname}${window.location.search}${window.location.hash}`;

            if (currentLocation !== targetLocation) {
                window.location.replace(targetPath);
            }
        };

        // The guarded booking flow owns two consecutive history entries: the
        // booking route and its sentinel. Move behind both when the booking was
        // opened from a salon; a directly opened tab only needs its sentinel
        // removed. Back can therefore never reopen a finished booking session.
        const historySteps = window.history.length > 2 ? -2 : -1;
        window.addEventListener('popstate', finishAtTarget, { once: true });
        window.history.go(historySteps);

        // A directly opened tab may not have two earlier entries. In that case,
        // replace the current location after the history traversal has failed.
        window.setTimeout(() => {
            if (window.location.href === bookingUrl) {
                window.removeEventListener('popstate', finishAtTarget);
                window.location.replace(targetPath);
            }
        }, 400);
    };

    const requestExitBooking = () => {
        const salonPath = getSalonPath(salon);
        exitTargetPathRef.current = salonPath;

        if (shouldWarnBeforeExit) {
            setShowExitDialog(true);
            return;
        }

        allowExitRef.current = true;
        navigateAfterBookingExit(salonPath);
    };

    const cancelExitBooking = () => {
        exitTargetPathRef.current = getSalonPath(salon);
        setShowExitDialog(false);
    };

    const confirmExitBooking = async () => {
        const salonPath = getSalonPath(salon);
        const targetPath = exitTargetPathRef.current || salonPath;

        allowExitRef.current = true;
        await releaseHeldBooking();
        clearBookingDraft();
        setShowExitDialog(false);
        navigateAfterBookingExit(targetPath);
    };

    const handleApplyVoucher = async (event) => {
        event.preventDefault();
        if (validatingVoucher) return;

        setVoucherError('');
        setVoucherSuccess('');

        const code = voucherCode.trim().toUpperCase();
        if (!code) {
            setVoucherError('Masukkan kode voucher terlebih dahulu.');
            return;
        }

        const couponServiceIds = bookingMode === 'group'
            ? participantSelections.flatMap((selection) => (selection.services || []).map((service) => service.id))
            : selectedServices.map((service) => service.id);

        if (couponServiceIds.length === 0) {
            setAppliedVoucher(null);
            setVoucherError('Pilih minimal satu layanan sebelum menerapkan voucher.');
            return;
        }

        setValidatingVoucher(true);

        try {
            const validation = await validateCustomerCoupon({
                couponCode: code,
                serviceIds: couponServiceIds,
            });
            const coupon = validation?.coupon;

            if (!coupon) throw new Error('Data voucher dari server tidak lengkap.');

            setAppliedVoucher({
                ...coupon,
                code: coupon.code || code,
                discountAmount: Number(validation.discount_amount || 0),
            });
            setVoucherCode(coupon.code || code);
            setVoucherSuccess(`Voucher ${coupon.code || code} berhasil diterapkan.`);
        } catch (applyError) {
            setAppliedVoucher(null);
            setVoucherError(applyError.message || 'Voucher tidak dapat diterapkan.');
        } finally {
            setValidatingVoucher(false);
        }
    };

    const handleRemoveVoucher = () => {
        setAppliedVoucher(null);
        setVoucherCode('');
        setVoucherError('');
        setVoucherSuccess('');
    };

    const handleConfirmBooking = async () => {
        if (submittingBooking) return;

        const incompleteGuestIndex = guests.findIndex((guest) => !guest.name.trim() || !guest.phone.trim());
        if (incompleteGuestIndex >= 0) {
            alert(`Lengkapi nama dan nomor telepon orang tambahan ke-${incompleteGuestIndex + 1}.`);
            return;
        }

        const invalidEmailIndex = guests.findIndex((guest) => guest.email.trim() && !/^\S+@\S+\.\S+$/.test(guest.email.trim()));
        if (invalidEmailIndex >= 0) {
            alert(`Email orang tambahan ke-${invalidEmailIndex + 1} belum valid.`);
            return;
        }

        const missingGenderIndex = guests.findIndex((guest) => !guest.gender);
        if (missingGenderIndex >= 0) {
            alert(`Pilih gender orang tambahan ke-${missingGenderIndex + 1}.`);
            return;
        }

        const missingAgeGroupIndex = guests.findIndex((guest) => !guest.age_group);
        if (missingAgeGroupIndex >= 0) {
            alert(`Pilih kelompok usia orang tambahan ke-${missingAgeGroupIndex + 1}.`);
            return;
        }

        const draft = createCurrentBookingDraft({ currentStep: 4 });
        saveBookingDraft(draft);

        if (!agreedToTerms) {
            alert('You must agree to the cancellation policy and service terms.');
            return;
        }

        if (!getSessionUser().loggedIn) {
            router.push(`/auth?next=${encodeURIComponent(`/booking/${getSalonRouteSlug(salon)}`)}`);
            return;
        }

        setSubmittingBooking(true);

        try {
            const activeHold = heldBooking?.id ? heldBooking : await ensureActiveHold();

            if (!activeHold?.id) {
                setSubmittingBooking(false);
                return;
            }

            const finalizeHold = (bookingHold) => finalizeCustomerBooking({
                bookingId: bookingHold.id,
                draft: {
                    ...draft,
                    discount: discountAmount,
                    total: totalToPay,
                },
                paymentMethod,
                couponCode: appliedVoucher?.code || '',
                notes: notes.trim(),
                guests,
            });
            const bookingObject = await finalizeHold(activeHold);
            const bookingCode = bookingObject.code;
            addBookingToList({
                ...bookingObject,
                holdStartedAt: null,
                holdExpiresAt: null,
                reviewed: false,
                status: paymentMethod === 'Pay at Venue' ? 'Confirmed' : 'Waiting Payment',
                paymentStatus: paymentMethod === 'Pay at Venue' ? 'Unpaid' : 'Waiting Payment',
            });
            bookingCompletedRef.current = true;
            allowExitRef.current = true;
            invalidateActiveHoldSelection();
            clearBookingDraft();
            setHeldBooking(null);
            window.dispatchEvent(new Event('salonku-activity-change'));

            router.replace(paymentMethod === 'Pay at Venue'
                ? `/booking-success/${bookingCode}`
                : `/payment/${bookingCode}`);
        } catch (error) {
            const message = error?.message || 'Booking belum berhasil disimpan ke backend. Coba lagi.';
            alert(message);

            if (message.toLowerCase().includes('booking sementara') || message.toLowerCase().includes('waktu booking')) {
                invalidateActiveHoldSelection();
                setHeldBooking(null);
                updateSelectedTime('');
                setCurrentStep(3);
                saveBookingDraft(createCurrentBookingDraft({
                    time: '',
                    currentStep: 3,
                    holdBookingId: null,
                    holdStartedAt: null,
                    holdExpiresAt: null,
                }));
                setSubmittingBooking(false);
                return;
            }

            setSubmittingBooking(false);
        }
    };

    const isNextEnabled = () => {
        if (bookingMode === 'group' && currentStep <= 3) {
            return firstIncompleteGroupParticipant(currentStep) === -1;
        }
        if (currentStep === 1) return selectedServices.length > 0;
        if (currentStep === 2) return !!selectedStaff;
        if (currentStep === 3) return !!selectedDate && Boolean(selectedTime || selectedTimeRef.current) && !holdingBooking;
        if (currentStep === 4) return agreedToTerms && Boolean(heldBooking?.id) && !holdingBooking;
        return false;
    };

    const continueButtonLabel = () => {
        if (submittingBooking) return 'Memproses booking...';
        if (currentStep === 3 && holdingBooking) return 'Mengunci jadwal...';
        if (currentStep === 4) {
            if (!getSessionUser().loggedIn) return 'Login untuk konfirmasi';
            return paymentMethod === 'Pay at Venue' ? 'Confirm booking' : 'Pay now';
        }

        return 'Continue';
    };

    return (
        <div className="fresh-landing booking-route-shell">
            <header className="booking-route-toolbar">
                <div className="booking-route-toolbar-inner">
                    <button className="booking-route-toolbar-button" type="button" onClick={handlePrevStep} aria-label="Back">
                        <ChevronLeft size={28} />
                    </button>
                    <button className="booking-route-toolbar-button" type="button" onClick={requestExitBooking} aria-label="Tutup booking">
                        <X size={28} />
                    </button>
                </div>
            </header>
            <main className="booking-container booking-flow-page">
                <div className={`booking-grid ${!bookingModeSelected ? 'booking-mode-selection-grid' : ''}`}>
                    {/* Left Stepper Forms */}
                    <div className="booking-flow-main">
                        <h1 className="booking-flow-title">
                            {bookingModeSelected ? stepTitles[currentStep] : 'Choose booking type'}
                        </h1>
                        {bookingModeSelected && bookingMode === 'group' && currentStep <= 3 && (
                            <section className="booking-participant-switcher" aria-label="Choose the participant you are setting up">
                                <div className="booking-participant-switcher-heading">
                                    <div className="booking-participant-copy">
                                        <b>Atur per peserta</b>
                                        <span>Setiap peserta dapat memilih layanan, professional, dan waktu berbeda.</span>
                                    </div>
                                    <div className="booking-participant-count-controls">
                                        <em>Participant {activeParticipantIndex + 1} of {participantCount}</em>
                                        <button
                                            type="button"
                                            className="booking-participant-remove"
                                            disabled={participantCount <= 1}
                                            onClick={() => changeParticipantCount(participantCount - 1)}
                                            aria-label="Kurangi peserta"
                                            title={participantCount <= 1 ? 'Minimal 1 peserta' : 'Hapus peserta terakhir'}
                                        >
                                            <Minus size={15} aria-hidden="true" />
                                        </button>
                                        <button
                                            type="button"
                                            className="booking-participant-add"
                                            disabled={participantCount >= 5}
                                            onClick={() => changeParticipantCount(participantCount + 1)}
                                        >
                                            <Plus size={15} /> Tambah peserta
                                        </button>
                                    </div>
                                </div>
                                <div className="booking-participant-switcher-tabs">
                                    {effectiveParticipantSelections.map((selection, index) => {
                                        const guestName = index === 0
                                            ? (getSessionUser().user?.name || 'Saya')
                                            : (guests[index - 1]?.name || `Participant ${index + 1}`);
                                        const stepComplete = currentStep === 1
                                            ? (selection.services || []).length > 0
                                            : currentStep === 2
                                                ? Boolean(selection.staff)
                                                : Boolean(selection.date && selection.time);

                                        return (
                                            <button
                                                type="button"
                                                key={index}
                                                className={`${index === activeParticipantIndex ? 'active' : ''} ${stepComplete ? 'complete' : ''}`}
                                                onClick={() => switchActiveParticipant(index)}
                                                aria-pressed={index === activeParticipantIndex}
                                            >
                                                <span>{index + 1}</span>
                                                <div>
                                                    <b>{guestName}</b>
                                                    <small>{stepComplete ? 'Selected' : 'Incomplete'}</small>
                                                </div>
                                                {stepComplete && <Check size={16} />}
                                            </button>
                                        );
                                    })}
                                </div>
                            </section>
                        )}
                        {!bookingModeSelected && (
                            <section className="booking-mode-full-page" aria-labelledby="booking-type-title">
                                <div className="booking-type-heading">
                                    <span className="booking-mode-eyebrow">Start your reservation</span>
                                    <h2 id="booking-type-title">Who is this booking for?</h2>
                                    <p>Choose Personal for yourself or Group when booking with other people.</p>
                                </div>
                                <div className="booking-type-options">
                                    <button
                                        type="button"
                                        className={`booking-type-option ${bookingMode === 'personal' ? 'selected' : ''}`}
                                        aria-pressed={bookingMode === 'personal'}
                                        onClick={() => selectBookingMode('personal')}
                                    >
                                        <User size={30} />
                                        <span>
                                            <b>Personal</b>
                                            <small>Book services for myself</small>
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className={`booking-type-option ${bookingMode === 'group' ? 'selected' : ''}`}
                                        aria-pressed={bookingMode === 'group'}
                                        onClick={() => selectBookingMode('group')}
                                    >
                                        <Users size={30} />
                                        <span>
                                            <b>Group</b>
                                            <small>Book together; you can set the number of participants next</small>
                                        </span>
                                    </button>
                                </div>
                                <button
                                    className="booking-action-btn booking-mode-continue"
                                    type="button"
                                    disabled={!bookingMode}
                                    onClick={continueFromBookingMode}
                                >
                                    Continue to services <ArrowRight size={18} />
                                </button>
                            </section>
                        )}
                        {currentStep === 1 && bookingModeSelected && (
                            <div className="booking-service-step">

                                <div className="booking-service-sticky-head">
                                    <div className="booking-service-tabs">
                                        {serviceCategories.map(category => (
                                            <button
                                                className={activeCategory === category ? 'active' : ''}
                                                type="button"
                                                key={category}
                                                onClick={() => setActiveCategory(category)}
                                            >
                                                {category}
                                            </button>
                                        ))}
                                        <button className="icon-only" type="button" aria-label="All categories">
                                            <ListFilter size={20} />
                                        </button>
                                    </div>
                                    <div className="booking-category-intro">
                                        <h2>{activeCategory === 'Featured' ? 'Featured services' : activeCategory}</h2>
                                    </div>
                                </div>
                                <div className="booking-service-list-scroll">
                                    {filteredServices.map(service => {
                                        const isSelected = selectedServices.some(s => s.id === service.id);
                                        return (
                                            <div
                                                key={service.id}
                                                className={`service-booking-card ${isSelected ? 'selected' : ''}`}
                                                onClick={() => toggleService(service)}
                                                role="button"
                                                tabIndex={0}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter' || event.key === ' ') {
                                                        event.preventDefault();
                                                        toggleService(service);
                                                    }
                                                }}
                                            >
                                                <div className="service-details">
                                                    <div className="service-name">{service.name}</div>
                                                    <div className="service-duration-line">
                                                        {service.duration >= 60 ? `${Math.floor(service.duration / 60)} hr${service.duration % 60 ? `, ${service.duration % 60} min` : ''}` : `${service.duration} min`}
                                                    </div>
                                                    <div className="service-desc">{service.desc}</div>
                                                    <div className="service-meta">
                                                        <span className="service-price">
                                                            {service.discountPrice ? (
                                                                <>
                                                                    <span style={{ textDecoration: 'line-through', color: '#6f6862', fontSize: '12px', marginRight: '6px' }}>
                                                                        {formatBookingPrice(service.price)}
                                                                    </span>
                                                                    {formatBookingPrice(service.discountPrice)}
                                                                </>
                                                            ) : (
                                                                formatBookingPrice(service.price)
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                                <button 
                                                    className="booking-service-plus-btn"
                                                    type="button"
                                                    aria-label={isSelected ? 'Batalkan layanan' : 'Tambah layanan'}
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        toggleService(service);
                                                    }}
                                                >
                                                    {isSelected ? <Check size={18} /> : <Plus size={21} />}
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>

                                {availableAddons.length > 0 && (
                                    <div>
                                        <h2 className="booking-section-title" style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                            <Sparkles size={18} style={{ color: 'var(--color-accent)' }} />
                                            Tambahan Opsional (Add-ons)
                                        </h2>
                                        <p style={{ fontSize: '14px', color: '#6f6862', marginBottom: '16px' }}>
                                            Rekomendasi tambahan khusus untuk layanan utama yang telah kamu pilih.
                                        </p>
                                        <div style={{ display: 'grid', gap: '12px' }}>
                                            {availableAddons.map(addon => {
                                                const isSelected = selectedAddons.some(a => a.id === addon.id);
                                                return (
                                                    <div 
                                                        key={addon.id} 
                                                        className={`service-booking-card ${isSelected ? 'selected' : ''}`}
                                                        style={{ cursor: 'pointer' }}
                                                        onClick={() => toggleAddon(addon)}
                                                    >
                                                        <div className="service-details">
                                                            <span style={{ fontSize: '11px', fontWeight: '700', color: 'var(--color-accent-text)', background: 'var(--color-accent-soft)', padding: '1px 6px', borderRadius: '4px', display: 'inline-block', marginBottom: '4px' }}>
                                                                For: {addon.parentService}
                                                            </span>
                                                            <div className="service-name">{addon.name}</div>
                                                            <div className="service-desc">{addon.desc}</div>
                                                            <div className="service-meta">
                                                                <span className="service-price">
                                                                    + IDR {addon.price.toLocaleString('en-US')}
                                                                </span>
                                                                <span className="service-duration">
                                                                    <Clock size={14} /> +{addon.duration} mnt
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div style={{ padding: '4px' }}>
                                                            <div style={{ 
                                                                width: '20px', 
                                                                height: '20px', 
                                                                border: isSelected ? '2px solid var(--color-accent)' : '2px solid var(--color-border-neutral)', 
                                                                borderRadius: '4px',
                                                                background: isSelected ? 'var(--color-accent)' : 'transparent',
                                                                display: 'flex',
                                                                alignItems: 'center',
                                                                justifyContent: 'center',
                                                                color: '#ffffff'
                                                            }}>
                                                                {isSelected && <Check size={14} strokeWidth={3} />}
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {currentStep === 2 && (
                            <div className="booking-professional-step" aria-busy={preparingProfessionalStep || loadingEligibleStaff}>
                                {preparingProfessionalStep || loadingEligibleStaff ? (
                                    <div className="booking-professional-loading" role="status">
                                        <span className="booking-loading-indicator" aria-hidden="true">
                                            <span />
                                            <span />
                                            <span />
                                        </span>
                                        <strong>Finding available professionals</strong>
                                        <p>Checking skills for your selected services.</p>
                                    </div>
                                ) : (
                                    <>
                                <h2 className="booking-section-title">Choose a stylist or specialist</h2>
                                <p style={{ fontSize: '14px', color: '#6f6862', marginBottom: '20px' }}>
                                    Choose your preferred professional or select “Anyone” for the earliest available time.
                                </p>

                                <div className="staff-grid">
                                    {/* Any Staff Option */}
                                    <div 
                                        className={`staff-select-card ${selectedStaff === 'any' ? 'selected' : ''}`}
                                        onClick={() => {
                                            if (professionalOptions.length > 0) selectStaff('any');
                                        }}
                                        style={{
                                            opacity: professionalOptions.length > 0 ? 1 : 0.48,
                                            pointerEvents: professionalOptions.length > 0 ? 'auto' : 'none',
                                        }}
                                    >
                                        <div style={{ 
                                            width: '68px', 
                                            height: '68px', 
                                            borderRadius: '50%', 
                                            background: '#f2eae1', 
                                            display: 'flex', 
                                            alignItems: 'center', 
                                            justifyContent: 'center', 
                                            marginBottom: '12px',
                                            border: selectedStaff === 'any' ? '2px solid var(--color-accent)' : '2px solid transparent'
                                        }}>
                                            <User size={32} style={{ color: '#8c8075' }} />
                                        </div>
                                        <strong>Siapa Saja (Any Staff)</strong>
                                        <span>Ketersediaan tercepat</span>
                                        <div style={{ fontSize: '11px', color: '#4f8a00', fontWeight: '700', marginTop: '8px' }}>
                                            Tanpa biaya tambahan
                                        </div>
                                    </div>

                                    {/* Salon Staff */}
                                    {professionalOptions.map(member => {
                                        const isSelected = selectedStaff && selectedStaff.id === member.id;
                                        const adjustment = getStaffPriceAdjustment(member);
                                        const memberPhoto = validImageSource(member.photo);
                                        return (
                                            <div 
                                                key={member.id} 
                                                className={`staff-select-card ${isSelected ? 'selected' : ''}`}
                                                onClick={() => selectStaff(member)}
                                            >
                                                {memberPhoto ? (
                                                    <img className="staff-avatar" src={memberPhoto} alt={member.name} />
                                                ) : (
                                                    <div className="staff-avatar staff-avatar-placeholder" aria-hidden="true">
                                                        {avatarInitial(member.name)}
                                                    </div>
                                                )}
                                                <strong>{member.name}</strong>
                                                <span>{member.role}</span>
                                                <div className="staff-rating">
                                                    <Star size={13} fill="currentColor" strokeWidth={0} />
                                                    {member.rating.toFixed(1)} <span style={{ color: '#6f6862', fontWeight: 'normal' }}>({member.reviews})</span>
                                                </div>
                                                {adjustment > 0 && (
                                                    <div style={{ fontSize: '11px', color: 'var(--color-accent-text)', fontWeight: '700', marginTop: '6px' }}>
                                                        + IDR {adjustment.toLocaleString('en-US')}
                                                    </div>
                                                )}
                                                <button
                                                    className="booking-staff-profile-button"
                                                    type="button"
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        setActiveStaffProfile(member);
                                                    }}
                                                >
                                                    Lihat profil
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>

                                {!loadingEligibleStaff && professionalOptions.length === 0 && (
                                    <div style={{ display: 'flex', gap: '10px', background: '#fff8f0', border: '1px solid #f1d4b8', borderRadius: '8px', padding: '14px', fontSize: '13px', lineHeight: '18px', color: '#7a4b20', marginTop: '14px' }}>
                                        <AlertCircle size={18} style={{ color: 'var(--color-accent-text)', flexShrink: 0 }} />
                                        <div>
                                            No professional currently has all the skills required for your selected services. Try selecting fewer services or a different combination.
                                        </div>
                                    </div>
                                )}

                                {selectedStaff && selectedStaff !== 'any' && (
                                    <div style={{ display: 'flex', gap: '10px', background: 'var(--color-background-faded)', border: '1px solid var(--color-border-faded)', borderRadius: '8px', padding: '14px', fontSize: '13px', lineHeight: '18px', color: 'var(--color-foreground-muted)' }}>
                                        <AlertCircle size={18} style={{ color: 'var(--color-accent)', flexShrink: 0 }} />
                                        <div>
                                            You selected <strong>{selectedStaff.name}</strong>. The next step will show times based on this professional’s working hours and availability.
                                        </div>
                                    </div>
                                )}
                                    </>
                                )}
                            </div>
                        )}

                        {currentStep === 3 && (
                            <div className="booking-time-step">
                                <h2 className="booking-section-title">Choose date and time</h2>
                                <p className="booking-step-helper" style={{ fontSize: '14px', color: '#6f6862', marginBottom: '20px' }}>
                                    Choose an available time below. This participant needs approximately <strong>{currentParticipantDuration} minutes</strong>.
                                </p>

                                {/* 7 Days row */}
                                <h4 className="booking-time-subtitle" style={{ fontSize: '14px', margin: '0 0 10px' }}>Choose a date</h4>
                                <div className="date-selector-row">
                                    {datesList.map(item => {
                                        const isSelected = selectedDate === item.dateStr;
                                        return (
                                            <button
                                                key={item.dateStr}
                                                type="button"
                                                className={`date-select-btn ${isSelected ? 'selected' : ''}`}
                                                onClick={() => {
                                                    selectDate(item.dateStr);
                                                }}
                                            >
                                                <span className="date-day-name">{item.dayName}</span>
                                                <span className="date-day-num">{item.dayNum}</span>
                                                <span style={{ fontSize: '9px', fontWeight: '700' }}>{item.monthName}</span>
                                            </button>
                                        );
                                    })}
                                </div>

                                {/* Custom Date Picker (Optional Calendar Trigger) */}
                                <div className="booking-custom-date-row" style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '24px', fontSize: '13px' }}>
                                    <span style={{ color: '#6f6862' }}>Atau cari tanggal lainnya:</span>
                                    <input 
                                        type="date" 
                                        min={datesList[0]?.dateStr}
                                        value={selectedDate}
                                        onChange={(e) => {
                                            selectDate(e.target.value);
                                        }}
                                        style={{ 
                                            padding: '6px 12px', 
                                            borderRadius: '6px', 
                                            border: '1px solid var(--color-border-neutral)',
                                            fontSize: '13px',
                                            outline: 0
                                        }}
                                    />
                                </div>

                                {selectedDate ? (
                                    <div>
                                        <h4 className="booking-time-subtitle booking-slots-title" style={{ fontSize: '14px', margin: '0 0 12px', display: 'flex', gap: '6px', alignItems: 'center' }}>
                                            <Clock size={16} /> Available times ({new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })})
                                        </h4>

                                        {loadingAvailability && (
                                            <div className="booking-time-loading" role="status">
                                                <span className="booking-loading-indicator" aria-hidden="true">
                                                    <span />
                                                    <span />
                                                    <span />
                                                </span>
                                                <span>Checking available times...</span>
                                            </div>
                                        )}

                                        {/* Period groupings: Pagi, Siang, Sore, Malam */}
                                        {!loadingAvailability && timeStepError && (
                                            <div className="booking-time-message error" style={{ display: 'flex', gap: '10px', background: '#fff8f0', border: '1px solid #f1d4b8', borderRadius: '8px', padding: '14px', fontSize: '13px', lineHeight: '18px', color: '#7a4b20', marginBottom: '12px' }}>
                                                <AlertCircle size={18} style={{ color: 'var(--color-accent-text)', flexShrink: 0 }} />
                                                <div>{timeStepError}</div>
                                            </div>
                                        )}

                                        {!loadingAvailability && !timeStepError && timeSlots.length === 0 && (
                                            <div className="booking-time-empty" style={{ border: '1px dashed var(--color-border-faded)', borderRadius: '12px', padding: '24px', textAlign: 'center', color: 'var(--color-foreground-muted)', marginBottom: '12px' }}>
                                                <Clock size={28} style={{ margin: '0 auto 10px', opacity: 0.5 }} />
                                                <p style={{ margin: 0, fontSize: '13px' }}>There are no available times for this service, professional, and date.</p>
                                            </div>
                                        )}

                                        {!loadingAvailability && timeSlots.length > 0 && ['Pagi', 'Siang', 'Sore', 'Malam'].map(period => {
                                            const slotsInPeriod = timeSlots.filter(s => s.period === period);
                                            if (!slotsInPeriod.length) return null;
                                            const periodDetails = TIME_PERIOD_DETAILS[period];

                                            return (
                                                <section key={period} className={`time-slots-group time-slots-group--${period.toLowerCase()}`} aria-label={`${periodDetails?.title || period} time options`}>
                                                    <div className="booking-period-head">
                                                        <div>
                                                            <h5 className="booking-period-title">{periodDetails?.title || period}</h5>
                                                            <p className="booking-period-range">{periodDetails?.range} · {periodDetails?.label}</p>
                                                        </div>
                                                        <span className="booking-period-count">{slotsInPeriod.length} jam</span>
                                                    </div>
                                                    <div className="time-slots-grid">
                                                        {slotsInPeriod.map(slot => {
                                                            const isSelected = selectedTime === slot.start;
                                                            const isGroupConflict = slotConflictsWithOtherParticipant(slot, selectedStaff);
                                                            const isUnavailable = slot.status === 'Not available' || isGroupConflict;
                                                            const isAlmostFull = slot.status === 'Almost full';
                                                            const isValidatingThisSlot = validatingTime === slot.start;
                                                            const availabilityLabel = isValidatingThisSlot
                                                                ? 'Mengecek'
                                                                : isSelected
                                                                    ? 'Dipilih'
                                                                    : isGroupConflict
                                                                        ? 'Another participant'
                                                                        : isUnavailable
                                                                            ? 'Unavailable'
                                                                            : isAlmostFull
                                                                                ? 'Hampir penuh'
                                                                                : 'Available';

                                                            return (
                                                                <button
                                                                    key={`${slot.start}-${slot.staffId || 'any'}`}
                                                                    type="button"
                                                                    disabled={isUnavailable || isValidatingThisSlot}
                                                                    aria-pressed={isSelected}
                                                                    className={`time-slot-btn ${isSelected ? 'selected' : ''} ${isValidatingThisSlot ? 'validating' : ''} ${isUnavailable ? 'unavailable' : ''} ${isAlmostFull ? 'almost-full' : ''}`}
                                                                    title={isGroupConflict ? 'Sudah dipilih peserta lain dengan professional yang sama' : undefined}
                                                                    onClick={() => selectTime(slot.start, slot)}
                                                                >
                                                                    <span className="time-slot-content">
                                                                        <strong className="time-slot-time">{slot.start}</strong>
                                                                        {isValidatingThisSlot && (
                                                                            <span className="time-slot-loading-dots" aria-label="Mengecek jadwal">
                                                                                <span />
                                                                                <span />
                                                                                <span />
                                                                            </span>
                                                                        )}
                                                                        {!isValidatingThisSlot && <small className="time-slot-hint">{availabilityLabel}</small>}
                                                                    </span>
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                </section>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="booking-time-empty" style={{ border: '1px dashed var(--color-border-faded)', borderRadius: '12px', padding: '32px', textAlign: 'center', color: 'var(--color-foreground-muted)' }}>
                                        <Calendar size={32} style={{ margin: '0 auto 10px', opacity: 0.5 }} />
                                        <p style={{ margin: 0, fontSize: '13px' }}>Choose a date first to load available times.</p>
                                    </div>
                                )}
                            </div>
                        )}

                        {currentStep === 4 && (
                            <div className="booking-confirm-step">
                                <section className="booking-confirm-section">
                                    <div className="booking-confirm-heading">
                                        <span>Reservation details</span>
                                        <button type="button" onClick={() => goToEditStep(3)}>Change time</button>
                                    </div>
                                    {bookingMode === 'group' ? (
                                        <div className="booking-group-confirm-list">
                                            {effectiveParticipantSelections.map((selection, index) => (
                                                <article key={index} className="booking-group-confirm-card">
                                                    <div className="booking-group-confirm-person">
                                                        <span>{index + 1}</span>
                                                        <div>
                                                            <b>{index === 0 ? (getSessionUser().user?.name || 'You') : (guests[index - 1]?.name || `Participant ${index + 1}`)}</b>
                                                            <small>{(selection.services || []).map((service) => service.name).join(', ')}</small>
                                                        </div>
                                                    </div>
                                                    <dl>
                                                        <div>
                                                            <dt><User size={15} /> Professional</dt>
                                                            <dd>{selection.staff === 'any' ? 'Anyone' : (selection.staff?.name || '-')}</dd>
                                                        </div>
                                                        <div>
                                                            <dt><Calendar size={15} /> Schedule</dt>
                                                            <dd>{selection.date ? new Date(`${selection.date}T00:00:00`).toLocaleDateString('en-US', { day: 'numeric', month: 'long' }) : '-'}, {selection.time || '-'} WIB</dd>
                                                        </div>
                                                    </dl>
                                                </article>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="booking-confirm-grid">
                                            <div className="booking-confirm-tile">
                                                <Calendar size={18} />
                                                <span>Date</span>
                                                <b>{new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long' })}</b>
                                            </div>
                                            <div className="booking-confirm-tile">
                                                <Clock size={18} />
                                                <span>Time</span>
                                                <b>{selectedBookingTime ? `${selectedBookingTime} WIB` : '-'}</b>
                                            </div>
                                            <div className="booking-confirm-tile">
                                                <User size={18} />
                                                <span>Professional</span>
                                                <b>{selectedStaffName}</b>
                                            </div>
                                        </div>
                                    )}
                                </section>

                                <section className="booking-confirm-section">
                                    <div className="booking-confirm-heading">
                                        <span>Participant details</span>
                                        <em>{participantCount} people</em>
                                    </div>
                                    <div className="booking-participant-list">
                                        <div className="booking-primary-participant">
                                            <User size={18} />
                                            <div>
                                                <b>{getSessionUser().user?.name || 'You'}</b>
                                                <small>Primary booker</small>
                                            </div>
                                        </div>
                                        {guests.map((guest, index) => (
                                            <fieldset className="booking-guest-fields" key={index}>
                                                <legend>Additional person {index + 1}</legend>
                                                <label>
                                                    <span>Full name *</span>
                                                    <input
                                                        type="text"
                                                        value={guest.name}
                                                        onChange={(event) => updateGuest(index, 'name', event.target.value)}
                                                        placeholder="Full name"
                                                        maxLength={100}
                                                        required
                                                    />
                                                </label>
                                                <label>
                                                    <span>Phone number *</span>
                                                    <input
                                                        type="tel"
                                                        value={guest.phone}
                                                        onChange={(event) => updateGuest(index, 'phone', event.target.value)}
                                                        placeholder="Example: 081234567890"
                                                        maxLength={30}
                                                        required
                                                    />
                                                </label>
                                                <label className="booking-guest-email">
                                                    <span>Email (optional)</span>
                                                    <input
                                                        type="email"
                                                        value={guest.email}
                                                        onChange={(event) => updateGuest(index, 'email', event.target.value)}
                                                        placeholder="nama@email.com"
                                                        maxLength={255}
                                                    />
                                                </label>
                                                <label>
                                                    <span>Gender *</span>
                                                    <select
                                                        value={guest.gender}
                                                        onChange={(event) => updateGuest(index, 'gender', event.target.value)}
                                                        required
                                                    >
                                                        <option value="">Choose gender</option>
                                                        <option value="female">Female</option>
                                                        <option value="male">Male</option>
                                                    </select>
                                                </label>
                                                <label>
                                                    <span>Age group *</span>
                                                    <select
                                                        value={guest.age_group}
                                                        onChange={(event) => updateGuest(index, 'age_group', event.target.value)}
                                                        required
                                                    >
                                                        <option value="">Choose age group</option>
                                                        <option value="child">Kids (0-12)</option>
                                                        <option value="teen">Teen (13-17)</option>
                                                        <option value="adult">Adult (18-59)</option>
                                                        <option value="senior">Senior (60+)</option>
                                                    </select>
                                                </label>
                                                <label className="booking-guest-description">
                                                    <span>Description / special notes (optional)</span>
                                                    <textarea
                                                        value={guest.description}
                                                        onChange={(event) => updateGuest(index, 'description', event.target.value)}
                                                        placeholder="Example: allergies, accessibility needs, or service preferences"
                                                        maxLength={1000}
                                                        rows={3}
                                                    />
                                                    <small>{guest.description.length}/1000</small>
                                                </label>
                                            </fieldset>
                                        ))}
                                    </div>
                                </section>

                                <section className="booking-confirm-section">
                                    <div className="booking-confirm-heading">
                                        <span>Services</span>
                                        <button type="button" onClick={() => goToEditStep(1)}>Change services</button>
                                    </div>
                                    <div className="booking-confirm-list">
                                        {selectedServices.map((service) => (
                                            <div className="booking-confirm-line" key={service.id}>
                                                <div>
                                                    <b>{service.name}</b>
                                                    <small>{service.duration >= 60 ? `${Math.floor(service.duration / 60)} hr${service.duration % 60 ? `, ${service.duration % 60} min` : ''}` : `${service.duration} min`}</small>
                                                </div>
                                                <strong>{formatBookingPrice(service.discountPrice || service.price)}</strong>
                                            </div>
                                        ))}
                                        {selectedAddons.map((addon) => (
                                            <div className="booking-confirm-line" key={addon.id}>
                                                <div>
                                                    <b>{addon.name}</b>
                                                    <small>Add-on, +{addon.duration} min</small>
                                                </div>
                                                <strong>+ {formatBookingPrice(addon.price)}</strong>
                                            </div>
                                        ))}
                                    </div>
                                </section>

                                <section className="booking-confirm-section">
                                    <div className="booking-confirm-heading">
                                        <span>Payment</span>
                                        <em>{paymentMethod}</em>
                                    </div>
                                    <div className="booking-payment-list">
                                        {paymentOptions.map((option) => (
                                            <label
                                                className={`booking-payment-row ${paymentMethod === option.value ? 'selected' : ''}`}
                                                key={option.value}
                                            >
                                                <input
                                                    type="radio"
                                                    name="payment-method"
                                                    checked={paymentMethod === option.value}
                                                    onChange={() => setPaymentMethod(option.value)}
                                                />
                                                <span>
                                                    <b>{option.title}</b>
                                                    <small>{option.desc}</small>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </section>

                            </div>
                        )}

                    </div>

                    {/* Right Booking Sidebar Summary */}
                    {bookingModeSelected && <div className="booking-summary-column">
                        <div className="booking-summary-sidebar">
                            <div className="booking-summary-venue">
                                {salonImageSource ? (
                                    <img src={salonImageSource} alt={salon.name} />
                                ) : (
                                    <div className="booking-summary-venue-placeholder" aria-hidden="true">
                                        {avatarInitial(salon.name)}
                                    </div>
                                )}
                                <div>
                                    <strong>{salon.name}</strong>
                                    <span className="booking-summary-rating">
                                        {Number(salon.rating || 5).toFixed(1)}
                                        <span className="booking-summary-stars">
                                            {Array.from({ length: 5 }).map((_, index) => (
                                                <Star key={index} size={17} fill="currentColor" strokeWidth={0} />
                                            ))}
                                        </span>
                                        ({salon.reviews || 0})
                                    </span>
                                    <small>{salon.address || salon.city}</small>
                                </div>
                            </div>

                            <div className="summary-divider" />

                            <div className="summary-services-list">
                                {selectedServices.length ? selectedServices.map(service => (
                                    <div key={service.id} className="summary-item">
                                        <div className="item-details">
                                            <div>{service.name}</div>
                                            <small>{service.duration >= 60 ? `${Math.floor(service.duration / 60)} hr${service.duration % 60 ? `, ${service.duration % 60} min` : ''}` : `${service.duration} min`}</small>
                                        </div>
                                        <div className="item-price">
                                            {formatBookingPrice(service.discountPrice || service.price)}
                                        </div>
                                    </div>
                                )) : (
                                    <p className="booking-summary-empty">No services selected</p>
                                )}

                                {selectedAddons.length > 0 && (
                                    <div style={{ marginTop: '14px' }}>
                                        {selectedAddons.map(addon => (
                                            <div key={addon.id} className="summary-item">
                                                <div className="item-details">
                                                    <div>{addon.name}</div>
                                                    <small>+{addon.duration} min</small>
                                                </div>
                                                <div className="item-price">
                                                    + {formatBookingPrice(addon.price)}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="booking-summary-mini">
                                <span>Participants</span>
                                <div><User size={14} /><span>{participantCount} people</span></div>
                            </div>

                            {currentStep >= 2 && (
                                <>
                                    <div className="summary-divider" />

                                    <div className="booking-summary-mini">
                                        <span>Professional</span>
                                        {selectedStaff ? (
                                            <div>
                                                {selectedStaff === 'any' ? (
                                                    <span>Anyone</span>
                                                ) : (
                                                    <span>{selectedStaff.name}</span>
                                                )}
                                            </div>
                                        ) : (
                                            <em>Not selected</em>
                                        )}
                                    </div>
                                </>
                            )}

                            {currentStep >= 3 && (
                                bookingMode === 'group' ? (
                                    <div className="booking-summary-mini booking-summary-group-times">
                                        <span>Participant schedules</span>
                                        {effectiveParticipantSelections.every((selection) => selection.date && selection.time) ? (
                                            <div className="booking-summary-group-time-list">
                                                {effectiveParticipantSelections.map((selection, index) => (
                                                    <div key={selection.position || index} className="booking-summary-time-row">
                                                        <b>{index === 0 ? 'Participant 1' : `Participant ${index + 1}`}</b>
                                                        <span>
                                                            <Calendar size={14} style={{ color: 'var(--color-accent)' }} />
                                                            {new Date(`${selection.date}T00:00:00`).toLocaleDateString('en-US', { day: 'numeric', month: 'short' })}, {selection.time}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <em>Each participant’s schedule is incomplete</em>
                                        )}
                                    </div>
                                ) : (
                                    <div className="booking-summary-mini">
                                        <span>Time</span>
                                        {selectedDate && selectedBookingTime ? (
                                            <div>
                                                <Calendar size={14} style={{ color: 'var(--color-accent)' }} />
                                                <span>
                                                    {new Date(selectedDate).toLocaleDateString('en-US', { day: 'numeric', month: 'short' })}, {selectedBookingTime}
                                                </span>
                                            </div>
                                        ) : (
                                            <em>Not selected</em>
                                        )}
                                    </div>
                                )
                            )}

                            <div className="summary-divider" />

                            {currentStep === 4 && (
                                <>
                                    <div className="booking-summary-extras">
                                        <div className="booking-summary-extras-heading">
                                            <span>Voucher</span>
                                            {appliedVoucher && <em>{appliedVoucher.code}</em>}
                                        </div>
                                        {!appliedVoucher ? (
                                            <form className="booking-voucher-row compact" onSubmit={handleApplyVoucher}>
                                                <input
                                                    type="text"
                                                    value={voucherCode}
                                                    onChange={(event) => setVoucherCode(event.target.value)}
                                                    placeholder="NEWUSER"
                                                />
                                                <button type="submit" disabled={validatingVoucher}>
                                                    {validatingVoucher ? 'Checking...' : 'Apply'}
                                                </button>
                                            </form>
                                        ) : (
                                            <div className="booking-applied-voucher compact">
                                                <span><Ticket size={14} /> {appliedVoucher.code}</span>
                                                <button type="button" onClick={handleRemoveVoucher}>Remove</button>
                                            </div>
                                        )}
                                        {voucherError && <p className="booking-confirm-message error compact">{voucherError}</p>}
                                        {voucherSuccess && <p className="booking-confirm-message success compact">{voucherSuccess}</p>}
                                        <textarea
                                            className="booking-confirm-notes compact"
                                            rows={2}
                                            value={notes}
                                            onChange={(event) => setNotes(event.target.value)}
                                            placeholder="Note for the salon"
                                        />
                                        <label className="booking-confirm-terms compact">
                                            <input
                                                type="checkbox"
                                                checked={agreedToTerms}
                                                onChange={(event) => setAgreedToTerms(event.target.checked)}
                                            />
                                            <span>I agree to the salon service terms.</span>
                                        </label>
                                    </div>

                                    <div className="summary-divider" />
                                </>
                            )}

                            <div className="summary-totals">
                                <div className="summary-total-row">
                                    <span>Total duration</span>
                                    <span>{totalDuration} min</span>
                                </div>
                                {participantCount > 1 && (
                                    <div className="summary-total-row">
                                        <span>Services for</span>
                                        <span>{participantCount} people</span>
                                    </div>
                                )}
                                {totalStaffAdjustment > 0 && (
                                    <div className="summary-total-row">
                                        <span>Professional fee</span>
                                        <span>{formatBookingPrice(totalStaffAdjustment)}</span>
                                    </div>
                                )}
                                {discountAmount > 0 && (
                                    <div className="summary-total-row discount">
                                        <span>Promo</span>
                                        <span>- {formatBookingPrice(discountAmount)}</span>
                                    </div>
                                )}
                                <div className="summary-total-row grand-total">
                                    <span>Total</span>
                                    <span>{formatBookingPrice(totalToPay)}</span>
                                </div>
                            </div>
                            <button
                                className="booking-action-btn booking-summary-continue"
                                type="button"
                                disabled={!isNextEnabled() || submittingBooking}
                                onClick={handleNextStep}
                            >
                                {continueButtonLabel()}
                                {!submittingBooking && !(currentStep === 3 && holdingBooking) && <ArrowRight size={18} />}
                            </button>
                        </div>
                    </div>}
                </div>
            </main>
            {showExitDialog && (
                <div className="booking-exit-overlay" role="presentation">
                    <div
                        className="modal-content-box booking-exit-modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="booking-exit-title"
                    >
                        <div className="booking-exit-icon" aria-hidden="true">
                            <AlertCircle size={24} />
                        </div>
                        <h2 id="booking-exit-title">Are you sure you want to exit?</h2>
                        <p>Your booking isn't complete and any progress will be lost.</p>
                        <div className="booking-exit-actions">
                            <button className="booking-exit-stay-btn" type="button" onClick={cancelExitBooking}>
                                Close
                            </button>
                            <button className="booking-exit-confirm-btn" type="button" onClick={confirmExitBooking}>
                                Yes, exit
                            </button>
                        </div>
                    </div>
                </div>
            )}
            {activeStaffProfile && (
                <StaffProfileModal
                    staff={activeStaffProfile}
                    onClose={() => setActiveStaffProfile(null)}
                    onBook={(staff) => {
                        selectStaff(staff);
                        setActiveStaffProfile(null);
                    }}
                />
            )}
        </div>
    );
}
