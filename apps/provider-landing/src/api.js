const defaultHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
};

export async function registerProvider(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: defaultHeaders,
        credentials: 'include',
        body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message || 'Registrasi belum berhasil.');
        error.errors = data.errors || {};
        throw error;
    }

    return data;
}
