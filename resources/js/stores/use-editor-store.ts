import { create } from 'zustand';
import {
    sanitizeCustomData,
    sanitizeText,
    sanitizeUrl,
} from '@/lib/editor-sanitize';
import type {
    ActiveElement,
    CustomData,
    EditLink,
    EditScope,
    ElementGroup,
    Viewport,
} from '@/types/editor';

type EditorState = {
    customData: CustomData;
    currentPageSlug: string;
    isEditMode: boolean;
    viewport: Viewport;
    isDirty: boolean;
    activeElement: ActiveElement | null;
    /** Bumped whenever the iframe must be re-synced (everything except live text typing). */
    syncVersion: number;
    /** Bumped to force the iframe to reload from scratch (used by reset). */
    reloadNonce: number;
    /** Code of the saved link (or one reserved by an image upload). */
    uniqueCode: string | null;
    /** True once the preview record exists on the server, so saves must overwrite it. */
    isSaved: boolean;
    expiresAt: string | null;

    init: (
        initial: Partial<CustomData> | null,
        firstPageSlug: string,
        preview?: { unique_code: string; expires_at: string } | null,
    ) => void;
    reserveCode: (code: string) => void;
    setSavedPreview: (code: string, expiresAt: string) => void;
    setText: (scope: EditScope, id: string, value: string) => void;
    setImage: (scope: EditScope, id: string, src: string) => void;
    setLink: (scope: EditScope, id: string, link: EditLink) => void;
    setColor: (variable: string, value: string) => void;
    clearColor: (variable: string) => void;
    setCurrentPage: (slug: string) => void;
    setEditMode: (value: boolean) => void;
    setViewport: (viewport: Viewport) => void;
    setActiveElement: (element: ActiveElement | null) => void;
    markSaved: () => void;
    reset: () => void;
};

const emptyGroup = (): ElementGroup => ({ texts: {}, images: {}, links: {} });

const emptyCustomData = (): CustomData => ({
    colors: {},
    global: emptyGroup(),
    pages: {},
});

let initialData: CustomData = emptyCustomData();
let firstPage = 'home';

type GroupKey = keyof ElementGroup;

function updateGroup<T extends GroupKey>(
    data: CustomData,
    scope: EditScope,
    pageSlug: string,
    key: T,
    id: string,
    value: ElementGroup[T][string],
): CustomData {
    const group =
        scope === 'global'
            ? data.global
            : (data.pages[pageSlug] ?? emptyGroup());
    const nextGroup: ElementGroup = {
        ...group,
        [key]: { ...group[key], [id]: value },
    };

    return scope === 'global'
        ? { ...data, global: nextGroup }
        : { ...data, pages: { ...data.pages, [pageSlug]: nextGroup } };
}

export const useEditorStore = create<EditorState>()((set, get) => ({
    customData: emptyCustomData(),
    currentPageSlug: 'home',
    isEditMode: true,
    viewport: 'desktop',
    isDirty: false,
    activeElement: null,
    syncVersion: 0,
    reloadNonce: 0,
    uniqueCode: null,
    isSaved: false,
    expiresAt: null,

    reserveCode: (code) =>
        set((state) => ({ uniqueCode: state.uniqueCode ?? code })),

    setSavedPreview: (code, expiresAt) =>
        set({ uniqueCode: code, isSaved: true, expiresAt, isDirty: false }),

    init: (initial, firstPageSlug, preview) => {
        initialData = sanitizeCustomData(initial);
        firstPage = firstPageSlug;

        set({
            uniqueCode: preview?.unique_code ?? null,
            isSaved: preview != null,
            expiresAt: preview?.expires_at ?? null,
            customData: initialData,
            currentPageSlug: firstPageSlug,
            isEditMode: true,
            viewport: 'desktop',
            isDirty: false,
            activeElement: null,
            syncVersion: 0,
            reloadNonce: 0,
        });
    },

    // Text edits only touch the store. They must NOT bump syncVersion, otherwise the value
    // would be written back into the iframe while the visitor types and the caret would jump.
    setText: (scope, id, value) =>
        set((state) => ({
            customData: updateGroup(
                state.customData,
                scope,
                state.currentPageSlug,
                'texts',
                id,
                sanitizeText(value),
            ),
            isDirty: true,
        })),

    setImage: (scope, id, src) =>
        set((state) => ({
            customData: updateGroup(
                state.customData,
                scope,
                state.currentPageSlug,
                'images',
                id,
                sanitizeUrl(src, true),
            ),
            isDirty: true,
            syncVersion: state.syncVersion + 1,
        })),

    setLink: (scope, id, link) =>
        set((state) => ({
            customData: updateGroup(
                state.customData,
                scope,
                state.currentPageSlug,
                'links',
                id,
                {
                    text: sanitizeText(link.text ?? ''),
                    href: sanitizeUrl(link.href ?? ''),
                },
            ),
            isDirty: true,
            syncVersion: state.syncVersion + 1,
        })),

    setColor: (variable, value) =>
        set((state) => ({
            customData: {
                ...state.customData,
                colors: { ...state.customData.colors, [variable]: value },
            },
            isDirty: true,
            syncVersion: state.syncVersion + 1,
        })),

    clearColor: (variable) =>
        set((state) => {
            const { [variable]: removed, ...colors } = state.customData.colors;
            void removed;

            return {
                customData: { ...state.customData, colors },
                isDirty: true,
                // The iframe still has the old inline value, so reload it to restore the template default.
                reloadNonce: state.reloadNonce + 1,
            };
        }),

    // The new page is loaded in the iframe, which announces IFRAME_READY and receives the data then.
    setCurrentPage: (slug) => set({ currentPageSlug: slug }),

    setEditMode: (value) => set({ isEditMode: value }),

    setViewport: (viewport) => set({ viewport }),

    setActiveElement: (element) => set({ activeElement: element }),

    markSaved: () => set({ isDirty: false }),

    reset: () =>
        set({
            customData: initialData,
            currentPageSlug: firstPage,
            isDirty: false,
            activeElement: null,
            reloadNonce: get().reloadNonce + 1,
        }),
}));
