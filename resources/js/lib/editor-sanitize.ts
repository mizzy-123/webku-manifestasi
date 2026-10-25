import DOMPurify from 'dompurify';
import type { CustomData, ElementGroup } from '@/types/editor';

const SAFE_DATA_IMAGE =
    /^data:image\/(png|jpe?g|webp|gif);base64,[a-z0-9+/=]+$/i;

export function sanitizeText(value: string): string {
    return DOMPurify.sanitize(value, {
        ALLOWED_TAGS: [
            'br',
            'b',
            'strong',
            'i',
            'em',
            'u',
            'span',
            'small',
            'sup',
            'sub',
        ],
        ALLOWED_ATTR: [],
    });
}

/**
 * Allow http(s), mailto, tel, anchors and relative URLs. Block javascript:, vbscript:, file: and data: (except raster data images).
 */
export function sanitizeUrl(value: string, allowDataImage = false): string {
    const trimmed = value.trim();
    // eslint-disable-next-line no-control-regex
    const compact = trimmed.replace(/[\u0000-\u0020]/g, '');

    if (allowDataImage && SAFE_DATA_IMAGE.test(compact)) {
        return compact;
    }

    if (/^(javascript|vbscript|data|file|blob):/i.test(compact)) {
        return '';
    }

    return trimmed;
}

function sanitizeGroup(group: Partial<ElementGroup> | undefined): ElementGroup {
    const texts: Record<string, string> = {};
    const images: Record<string, string> = {};
    const links: ElementGroup['links'] = {};

    for (const [id, value] of Object.entries(group?.texts ?? {})) {
        texts[id] = sanitizeText(String(value));
    }

    for (const [id, value] of Object.entries(group?.images ?? {})) {
        const url = sanitizeUrl(String(value), true);

        if (url !== '') {
            images[id] = url;
        }
    }

    for (const [id, link] of Object.entries(group?.links ?? {})) {
        links[id] = {
            ...(typeof link.text === 'string'
                ? { text: sanitizeText(link.text) }
                : {}),
            ...(typeof link.href === 'string'
                ? { href: sanitizeUrl(link.href) }
                : {}),
        };
    }

    return { texts, images, links };
}

export function sanitizeCustomData(
    data: Partial<CustomData> | null | undefined,
): CustomData {
    const colors: Record<string, string> = {};

    for (const [variable, value] of Object.entries(data?.colors ?? {})) {
        if (
            /^--[a-z0-9-]+$/i.test(variable) &&
            /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(value))
        ) {
            colors[variable] = String(value);
        }
    }

    const pages: Record<string, ElementGroup> = {};

    for (const [slug, group] of Object.entries(data?.pages ?? {})) {
        pages[slug] = sanitizeGroup(group);
    }

    return { colors, global: sanitizeGroup(data?.global), pages };
}
