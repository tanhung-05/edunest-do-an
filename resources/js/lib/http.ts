/**
 * Gọi JSON trong cùng miền. Trang Inertia không có thẻ meta CSRF (mã đổi sau đăng nhập), nên đọc cookie
 * XSRF-TOKEN hiện tại và gửi qua tiêu đề X-XSRF-TOKEN.
 */
export function xsrf(): string {
    const raw = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return raw ? decodeURIComponent(raw) : '';
}

export class HttpError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

type Validation = { message?: string; errors?: Record<string, string[]> };

/** Lỗi 422 trả câu thông báo đầu tiên; 429 báo gửi quá nhanh; lỗi khác báo chung. */
async function parse<T>(res: Response): Promise<T> {
    if (res.ok) {
        return (await res.json()) as T;
    }

    if (res.status === 422) {
        const body = (await res.json().catch(() => ({}))) as Validation;
        const first = body.errors
            ? Object.values(body.errors)[0]?.[0]
            : undefined;
        throw new HttpError(
            first ?? body.message ?? 'Dữ liệu chưa hợp lệ.',
            422,
        );
    }

    if (res.status === 429) {
        throw new HttpError(
            'Bạn gửi hơi nhanh, đợi một chút rồi thử lại.',
            429,
        );
    }

    if (res.status === 419) {
        throw new HttpError('Phiên đã hết hạn, bạn tải lại trang giúp.', 419);
    }

    throw new HttpError('Không gửi được, bạn thử lại sau.', res.status);
}

export async function getJson<T>(url: string): Promise<T> {
    const res = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    return parse<T>(res);
}

/** P10: gửi JSON và đọc kết quả; lỗi ném `HttpError` có câu thông báo dễ hiểu (khác `postJson` chỉ trả đúng/sai). */
export async function sendJson<T>(url: string, body: unknown): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrf(),
        },
        body: JSON.stringify(body),
    });

    return parse<T>(res);
}

export function newRequestKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

/** Gửi JSON tới tuyến web (kèm XSRF từ cookie Laravel), dùng cho ghi nhận nền không cần tải lại trang. */
export async function postJson(url: string, body: unknown): Promise<boolean> {
    const token = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    try {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
            },
            body: JSON.stringify(body),
        });

        return res.ok;
    } catch {
        return false;
    }
}
