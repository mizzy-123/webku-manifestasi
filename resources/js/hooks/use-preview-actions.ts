import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import {
    createPreview,
    overwritePreview,
    recordOrderClick,
} from '@/lib/preview-api';
import type { SavedPreview } from '@/lib/preview-api';
import { useEditorStore } from '@/stores/use-editor-store';

export function usePreviewActions(templateSlug: string) {
    const [isSaving, setIsSaving] = useState(false);
    const [isOrdering, setIsOrdering] = useState(false);
    const [savedPreview, setSavedPreview] = useState<SavedPreview | null>(null);

    /**
     * First save creates `/p/{code}`; every save after that overwrites the same link.
     */
    const persist = useCallback(async (): Promise<SavedPreview> => {
        const state = useEditorStore.getState();
        const result =
            state.isSaved && state.uniqueCode
                ? await overwritePreview(state.uniqueCode, state.customData)
                : await createPreview(
                      templateSlug,
                      state.customData,
                      state.uniqueCode,
                  );

        state.setSavedPreview(result.unique_code, result.expires_at);

        if (window.location.pathname !== new URL(result.url).pathname) {
            window.history.replaceState({}, '', new URL(result.url).pathname);
        }

        return result;
    }, [templateSlug]);

    const save = useCallback(async () => {
        setIsSaving(true);

        try {
            setSavedPreview(await persist());
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : 'Gagal menyimpan.',
            );
        } finally {
            setIsSaving(false);
        }
    }, [persist]);

    const order = useCallback(async () => {
        setIsOrdering(true);
        // Open the tab synchronously so the browser does not treat it as a blocked popup.
        const tab = window.open('', '_blank');

        try {
            const { isDirty, isSaved, uniqueCode } = useEditorStore.getState();
            const code =
                isDirty || !isSaved || !uniqueCode
                    ? (await persist()).unique_code
                    : uniqueCode;
            const { whatsapp_url: whatsappUrl } = await recordOrderClick(code);

            if (tab) {
                tab.location.href = whatsappUrl;
            } else {
                window.location.href = whatsappUrl;
            }
        } catch (error) {
            tab?.close();
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Gagal membuka WhatsApp.',
            );
        } finally {
            setIsOrdering(false);
        }
    }, [persist]);

    return {
        isSaving,
        isOrdering,
        savedPreview,
        closeSavedPreview: () => setSavedPreview(null),
        save,
        order,
    };
}
