import {
    orderClick,
    store,
    update,
    uploadImage,
} from '@/actions/App/Http/Controllers/Api/PreviewApiController';
import type { CustomData } from '@/types/editor';

export type SavedPreview = {
    unique_code: string;
    url: string;
    expires_at: string;
};

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(
    url: string,
    method: 'POST' | 'PUT',
    body: BodyInit,
    isJson: boolean,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(isJson ? { 'Content-Type': 'application/json' } : {}),
        },
        body,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = data?.errors
            ? (Object.values(data.errors)[0] as string[])[0]
            : null;

        throw new ApiError(
            firstError ?? data?.message ?? 'Terjadi kesalahan. Coba lagi.',
            response.status,
        );
    }

    return data as T;
}

export function createPreview(
    template: string,
    customData: CustomData,
    uniqueCode: string | null,
) {
    return request<SavedPreview>(
        store.url(),
        'POST',
        JSON.stringify({
            template,
            custom_data: customData,
            unique_code: uniqueCode,
        }),
        true,
    );
}

export function overwritePreview(uniqueCode: string, customData: CustomData) {
    return request<SavedPreview>(
        update.url(uniqueCode),
        'PUT',
        JSON.stringify({ custom_data: customData }),
        true,
    );
}

export function uploadPreviewImage(file: File, uniqueCode: string | null) {
    const form = new FormData();
    form.append('image', file);

    if (uniqueCode) {
        form.append('unique_code', uniqueCode);
    }

    return request<{ unique_code: string; url: string }>(
        uploadImage.url(),
        'POST',
        form,
        false,
    );
}

export function recordOrderClick(uniqueCode: string) {
    return request<{ whatsapp_url: string }>(
        orderClick.url(uniqueCode),
        'POST',
        '{}',
        true,
    );
}
