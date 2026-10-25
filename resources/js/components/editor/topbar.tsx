import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Eye,
    MessageCircle,
    Monitor,
    Pencil,
    RotateCcw,
    Save,
    Smartphone,
    Tablet,
} from 'lucide-react';
import ColorPalettePopover from '@/components/editor/color-palette-popover';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { useEditorStore } from '@/stores/use-editor-store';
import type { EditorTemplate, Viewport } from '@/types/editor';

type Props = {
    template: EditorTemplate;
    isSaving: boolean;
    isOrdering: boolean;
    onSave: () => void;
    onOrder: () => void;
};

const viewports: { value: Viewport; label: string; icon: typeof Monitor }[] = [
    { value: 'desktop', label: 'Desktop', icon: Monitor },
    { value: 'tablet', label: 'Tablet', icon: Tablet },
    { value: 'mobile', label: 'Mobile', icon: Smartphone },
];

export default function Topbar({
    template,
    isSaving,
    isOrdering,
    onSave,
    onOrder,
}: Props) {
    const viewport = useEditorStore((state) => state.viewport);
    const setViewport = useEditorStore((state) => state.setViewport);
    const isEditMode = useEditorStore((state) => state.isEditMode);
    const setEditMode = useEditorStore((state) => state.setEditMode);
    const currentPageSlug = useEditorStore((state) => state.currentPageSlug);
    const setCurrentPage = useEditorStore((state) => state.setCurrentPage);
    const isDirty = useEditorStore((state) => state.isDirty);
    const reset = useEditorStore((state) => state.reset);

    const handleReset = () => {
        if (
            !isDirty ||
            window.confirm(
                'Semua perubahan yang belum disimpan akan dihapus. Lanjutkan?',
            )
        ) {
            reset();
        }
    };

    return (
        <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-3 sm:px-4">
            <Button asChild variant="ghost" size="sm">
                <Link href={home()}>
                    <ArrowLeft />
                    <span className="hidden sm:inline">Katalog</span>
                </Link>
            </Button>

            <div className="min-w-0 flex-1">
                <p className="truncate font-heading text-sm font-semibold">
                    {template.name}
                </p>
                {isDirty && (
                    <p className="text-xs text-muted-foreground">
                        Ada perubahan
                    </p>
                )}
            </div>

            {template.pages.length > 1 && (
                <select
                    aria-label="Halaman"
                    value={currentPageSlug}
                    onChange={(event) => setCurrentPage(event.target.value)}
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    {template.pages.map((page) => (
                        <option key={page.slug} value={page.slug}>
                            {page.title}
                        </option>
                    ))}
                </select>
            )}

            <div
                className="hidden items-center rounded-lg border p-0.5 md:flex"
                role="group"
                aria-label="Ukuran layar"
            >
                {viewports.map(({ value, label, icon: Icon }) => (
                    <button
                        key={value}
                        type="button"
                        title={label}
                        aria-label={label}
                        aria-pressed={viewport === value}
                        onClick={() => setViewport(value)}
                        className={cn(
                            'rounded-md p-1.5 text-muted-foreground transition hover:text-foreground',
                            viewport === value &&
                                'bg-primary text-primary-foreground hover:text-primary-foreground',
                        )}
                    >
                        <Icon className="size-4" />
                    </button>
                ))}
            </div>

            <ColorPalettePopover colors={template.colors} />

            <Button
                type="button"
                variant={isEditMode ? 'default' : 'outline'}
                size="sm"
                aria-pressed={isEditMode}
                onClick={() => setEditMode(!isEditMode)}
            >
                {isEditMode ? <Pencil /> : <Eye />}
                <span className="hidden lg:inline">
                    {isEditMode ? 'Mode Edit' : 'Mode Preview'}
                </span>
            </Button>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                title="Reset perubahan"
                aria-label="Reset perubahan"
                onClick={handleReset}
            >
                <RotateCcw />
            </Button>

            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={isSaving}
                onClick={onSave}
            >
                {isSaving ? <Spinner /> : <Save />}
                <span className="hidden lg:inline">Simpan</span>
            </Button>

            <Button
                type="button"
                size="sm"
                disabled={isOrdering}
                onClick={onOrder}
            >
                {isOrdering ? <Spinner /> : <MessageCircle />}
                <span className="hidden sm:inline">Pesan Sekarang</span>
            </Button>
        </header>
    );
}
