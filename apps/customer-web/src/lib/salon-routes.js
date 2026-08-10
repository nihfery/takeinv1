export function createSalonSlug(value, fallback = 'salon') {
    const slug = String(value || '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/&/g, ' and ')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-');

    return slug || fallback;
}

const ROUTE_CODE_MIN_LENGTH = 6;
const ROUTE_CODE_PATTERN = /^(?=.*\d)[a-z0-9]{6,12}$/;
const HASH_ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

function fnv1a(value) {
    let hash = 0x811c9dc5;
    const input = String(value || '');

    for (let index = 0; index < input.length; index += 1) {
        hash ^= input.charCodeAt(index);
        hash = Math.imul(hash, 0x01000193);
    }

    return hash >>> 0;
}

function toFixedBase36(value, length = 7) {
    let number = value >>> 0;
    let output = '';

    do {
        output = HASH_ALPHABET[number % 36] + output;
        number = Math.floor(number / 36);
    } while (number > 0);

    return output.padStart(length, '0').slice(-length);
}

export function createPublicRouteCode(branch) {
    const existingCode = String(
        branch?.publicCode
        || branch?.public_code
        || branch?.routeCode
        || branch?.route_code
        || branch?.uid
        || branch?.code
        || ''
    ).trim().toLowerCase().replace(/[^a-z0-9]/g, '');

    if (existingCode.length >= ROUTE_CODE_MIN_LENGTH) {
        return existingCode.slice(0, 12);
    }

    const stableIdentity = branch?.id !== undefined && branch?.id !== null
        ? `branch:${branch.id}`
        : [
            branch?.slug,
            branch?.name,
            branch?.branch_name,
            branch?.city,
            branch?.city_id,
            branch?.address,
        ].filter(Boolean).join('|');

    const hash = fnv1a(`salonku:${stableIdentity || 'branch'}`);
    return `y${toFixedBase36(hash, 6)}${hash % 10}`;
}

export function createServiceRouteCode(service) {
    const existingCode = String(
        service?.publicCode
        || service?.public_code
        || service?.routeCode
        || service?.route_code
        || service?.code
        || ''
    ).trim().toLowerCase().replace(/[^a-z0-9]/g, '');

    if (existingCode.length >= ROUTE_CODE_MIN_LENGTH) {
        return existingCode.slice(0, 12);
    }

    const stableIdentity = service?.id !== undefined && service?.id !== null
        ? `service:${service.id}`
        : [
            service?.slug,
            service?.name,
            service?.title,
            service?.category,
        ].filter(Boolean).join('|');

    const hash = fnv1a(`salonku:${stableIdentity || 'service'}`);
    return `s${toFixedBase36(hash, 6)}${hash % 10}`;
}

export function getSalonSlug(branch) {
    const rawSlug = String(branch?.slug || '').trim();

    if (rawSlug && !/^\d+$/.test(rawSlug) && !extractRouteCode(rawSlug)) {
        return createSalonSlug(rawSlug);
    }

    return createSalonSlug(branch?.name || branch?.branch_name || branch?.businessName || branch?.business_name || branch?.id);
}

export function getServiceSlug(service) {
    const rawSlug = String(service?.slug || '').trim();

    if (rawSlug && !/^\d+$/.test(rawSlug) && !extractRouteCode(rawSlug)) {
        return createSalonSlug(rawSlug, 'service');
    }

    return createSalonSlug(service?.name || service?.title || service?.id, 'service');
}

export function getSalonRouteSlug(branch) {
    const slug = getSalonSlug(branch);
    const code = createPublicRouteCode(branch);

    return `${slug}-${code}`;
}

export function getServiceRouteSlug(service) {
    return `${getServiceSlug(service)}-${createServiceRouteCode(service)}`;
}

export function getSalonPath(branch) {
    return `/a/${encodeURIComponent(getSalonRouteSlug(branch))}`;
}

export function getBookingPath(branch) {
    return `/booking/${encodeURIComponent(getSalonRouteSlug(branch))}`;
}

export function getServicePath(branch, service) {
    return `${getSalonPath(branch)}/services/${encodeURIComponent(getServiceRouteSlug(service))}`;
}

export function getStaffPath(branch, staff) {
    const staffName = staff?.name || staff?.full_name || [staff?.first_name, staff?.last_name].filter(Boolean).join(' ') || 'professional';
    const staffSlug = `${createSalonSlug(staffName, 'professional')}-${encodeURIComponent(staff?.id)}`;

    return `/p/${staffSlug}`;
}

export function staffIdFromRoute(value) {
    const routeValue = decodeURIComponent(String(value || '')).trim();
    const matched = routeValue.match(/(?:^|-)(\d+)$/);

    return matched ? matched[1] : routeValue;
}

export function staffNameFromRoute(value) {
    const routeValue = decodeURIComponent(String(value || '')).trim();
    const withoutId = routeValue.replace(/(?:^|-)(\d+)$/, '');

    return withoutId
        .split('-')
        .filter(Boolean)
        .map((part) => part.slice(0, 1).toUpperCase() + part.slice(1))
        .join(' ') || 'Professional';
}

export function extractRouteCode(routeSlug) {
    const decoded = decodeURIComponent(String(routeSlug || '')).trim().toLowerCase();
    const parts = decoded.split('-').filter(Boolean);
    const candidate = parts[parts.length - 1] || '';

    return ROUTE_CODE_PATTERN.test(candidate) ? candidate : '';
}

export function stripRouteCode(routeSlug) {
    const decoded = decodeURIComponent(String(routeSlug || '')).trim();
    const code = extractRouteCode(decoded);

    if (!code) return decoded;

    return decoded.slice(0, Math.max(0, decoded.length - code.length - 1));
}

export function findBranchByRoute(branches, routeSlug) {
    const decoded = decodeURIComponent(String(routeSlug || '')).trim();
    const routeCode = extractRouteCode(decoded);
    const decodedWithoutCode = stripRouteCode(decoded);
    const normalizedRouteSlug = createSalonSlug(decodedWithoutCode || decoded);
    const normalizedRouteLabel = decoded.toLowerCase();
    const normalizedLegacyRouteLabel = decodedWithoutCode.toLowerCase();

    return (routeCode ? branches.find((branch) => createPublicRouteCode(branch) === routeCode) : null)
        || branches.find((branch) => getSalonRouteSlug(branch) === createSalonSlug(decoded))
        || branches.find((branch) => getSalonSlug(branch) === normalizedRouteSlug)
        || branches.find((branch) => String(branch?.id || '').toLowerCase() === normalizedRouteLabel)
        || branches.find((branch) => String(branch?.id || '').toLowerCase() === normalizedLegacyRouteLabel)
        || branches.find((branch) => String(branch?.slug || '').toLowerCase() === normalizedRouteLabel)
        || branches.find((branch) => String(branch?.slug || '').toLowerCase() === normalizedLegacyRouteLabel);
}

export function findServiceByRoute(services, routeSlug) {
    const decoded = decodeURIComponent(String(routeSlug || '')).trim();
    const routeCode = extractRouteCode(decoded);
    const decodedWithoutCode = stripRouteCode(decoded);
    const normalizedRouteSlug = createSalonSlug(decodedWithoutCode || decoded, 'service');
    const normalizedRouteLabel = decoded.toLowerCase();
    const normalizedLegacyRouteLabel = decodedWithoutCode.toLowerCase();

    return (routeCode ? services.find((service) => createServiceRouteCode(service) === routeCode) : null)
        || services.find((service) => getServiceRouteSlug(service) === createSalonSlug(decoded, 'service'))
        || services.find((service) => getServiceSlug(service) === normalizedRouteSlug)
        || services.find((service) => String(service?.id || '').toLowerCase() === normalizedRouteLabel)
        || services.find((service) => String(service?.id || '').toLowerCase() === normalizedLegacyRouteLabel)
        || services.find((service) => String(service?.slug || '').toLowerCase() === normalizedRouteLabel)
        || services.find((service) => String(service?.slug || '').toLowerCase() === normalizedLegacyRouteLabel);
}
