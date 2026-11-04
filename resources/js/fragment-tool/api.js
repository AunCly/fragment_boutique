const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Error raised for a refused or failed tool request, with a message to display.
 */
export class ToolError extends Error {}

async function errorFrom(response) {
    let message = `Erreur ${response.status}`;
    try {
        const data = await response.json();
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        message = firstError ?? data.message ?? message;
    } catch {
        // Not JSON: keep the HTTP status.
    }
    return new ToolError(message);
}

function send(url, body, signal) {
    return fetch(url, {
        method: 'POST',
        body,
        signal,
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
}

export async function postJson(url, body, signal) {
    const response = await send(url, body, signal);
    if (!response.ok) {
        throw await errorFrom(response);
    }
    return response.json();
}

/**
 * Autosave request: JSON body, JSON answer.
 */
export async function patchJson(url, payload) {
    const response = await fetch(url, {
        method: 'PATCH',
        body: JSON.stringify(payload),
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json', 'Content-Type': 'application/json' },
    });
    if (!response.ok) {
        throw await errorFrom(response);
    }
    return response.json();
}

export async function postBlob(url, body, signal) {
    const response = await send(url, body, signal);
    if (!response.ok) {
        throw await errorFrom(response);
    }
    const disposition = response.headers.get('Content-Disposition') ?? '';
    const filename = disposition.match(/filename="([^"]+)"/)?.[1] ?? 'fragment.png';
    return { blob: await response.blob(), filename };
}

/**
 * Reads an NDJSON stream: one JSON event per line, handled as it arrives.
 */
export async function postStream(url, body, onEvent, signal) {
    const response = await send(url, body, signal);
    if (!response.ok) {
        throw await errorFrom(response);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();
        buffer += decoder.decode(value ?? new Uint8Array(), { stream: !done });
        let newline;
        while ((newline = buffer.indexOf('\n')) >= 0) {
            const line = buffer.slice(0, newline).trim();
            buffer = buffer.slice(newline + 1);
            if (line) {
                onEvent(JSON.parse(line));
            }
        }
        if (done) {
            if (buffer.trim()) {
                onEvent(JSON.parse(buffer));
            }
            return;
        }
    }
}

export function download(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
