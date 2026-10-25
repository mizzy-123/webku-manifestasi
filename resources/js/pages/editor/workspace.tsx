import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import ImageEditModal from '@/components/editor/image-edit-modal';
import LinkEditModal from '@/components/editor/link-edit-modal';
import Topbar from '@/components/editor/topbar';
import { cn } from '@/lib/utils';
import { render } from '@/routes/templates';
import { useEditorStore } from '@/stores/use-editor-store';
import type { BridgeMessage, EditorTemplate, Viewport } from '@/types/editor';

type Props = {
    template: EditorTemplate;
};

const viewportWidth: Record<Viewport, string> = {
    desktop: 'w-full',
    tablet: 'w-[768px] max-w-full',
    mobile: 'w-[390px] max-w-full',
};

export default function Workspace({ template }: Props) {
    const iframeRef = useRef<HTMLIFrameElement>(null);

    const init = useEditorStore((state) => state.init);
    const currentPageSlug = useEditorStore((state) => state.currentPageSlug);
    const viewport = useEditorStore((state) => state.viewport);
    const isEditMode = useEditorStore((state) => state.isEditMode);
    const syncVersion = useEditorStore((state) => state.syncVersion);
    const reloadNonce = useEditorStore((state) => state.reloadNonce);

    const currentPage = useMemo(
        () =>
            template.pages.find((page) => page.slug === currentPageSlug) ??
            template.pages[0],
        [template.pages, currentPageSlug],
    );

    useEffect(() => {
        init(null, template.pages[0]?.slug ?? 'home');
    }, [init, template.pages]);

    const postToIframe = useCallback((message: Record<string, unknown>) => {
        iframeRef.current?.contentWindow?.postMessage(
            message,
            window.location.origin,
        );
    }, []);

    const syncIframe = useCallback(() => {
        const state = useEditorStore.getState();

        postToIframe({ type: 'SET_EDIT_MODE', value: state.isEditMode });
        postToIframe({
            type: 'APPLY_CUSTOM_DATA',
            payload: state.customData,
            currentPage: state.currentPageSlug,
        });
    }, [postToIframe]);

    useEffect(() => {
        const onMessage = (event: MessageEvent<BridgeMessage>) => {
            if (
                event.origin !== window.location.origin ||
                event.source !== iframeRef.current?.contentWindow
            ) {
                return;
            }

            const message = event.data;
            const store = useEditorStore.getState();

            switch (message?.type) {
                case 'IFRAME_READY':
                    syncIframe();
                    break;
                case 'TEXT_UPDATED':
                    store.setText(
                        message.scope,
                        String(message.id),
                        String(message.value),
                    );
                    break;
                case 'IMAGE_CLICKED':
                    store.setActiveElement({
                        type: 'image',
                        id: String(message.id),
                        scope: message.scope,
                        src: String(message.src),
                    });
                    break;
                case 'LINK_CLICKED':
                    store.setActiveElement({
                        type: 'link',
                        id: String(message.id),
                        scope: message.scope,
                        text: String(message.text),
                        href: String(message.href),
                    });
                    break;
                case 'NAVIGATE_PAGE': {
                    const target = template.pages.find(
                        (page) => page.file === message.file,
                    );

                    if (target) {
                        store.setCurrentPage(target.slug);
                    }

                    break;
                }
            }
        };

        window.addEventListener('message', onMessage);

        return () => window.removeEventListener('message', onMessage);
    }, [syncIframe, template.pages]);

    // Re-sync when images/links/colors changed or edit mode toggled.
    useEffect(() => {
        syncIframe();
    }, [syncIframe, syncVersion, isEditMode]);

    const iframeSrc = render.url({
        template: template.slug,
        pageFile: currentPage?.file ?? 'index.html',
    });

    return (
        <>
            <Head title={`Edit ${template.name}`} />

            <div className="flex h-dvh flex-col bg-muted/40">
                <Topbar template={template} />

                <main className="flex min-h-0 flex-1 justify-center overflow-auto">
                    <iframe
                        key={`${iframeSrc}-${reloadNonce}`}
                        ref={iframeRef}
                        src={iframeSrc}
                        title={`Pratinjau ${template.name}`}
                        className={cn(
                            'h-full border-x bg-white shadow-sm transition-[width] duration-200',
                            viewportWidth[viewport],
                        )}
                    />
                </main>
            </div>

            <ImageEditModal />
            <LinkEditModal pages={template.pages} />
        </>
    );
}
