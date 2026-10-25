import { Palette, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useEditorStore } from '@/stores/use-editor-store';
import type { EditorColor } from '@/types/editor';

type Props = {
    colors: EditorColor[];
};

export default function ColorPalettePopover({ colors }: Props) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const customColors = useEditorStore((state) => state.customData.colors);
    const setColor = useEditorStore((state) => state.setColor);
    const clearColor = useEditorStore((state) => state.clearColor);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    if (colors.length === 0) {
        return null;
    }

    return (
        <div ref={containerRef} className="relative">
            <Button
                type="button"
                variant="outline"
                size="sm"
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => setOpen((value) => !value)}
            >
                <Palette />
                <span className="hidden lg:inline">Warna</span>
            </Button>

            {open && (
                <div
                    role="dialog"
                    aria-label="Palet warna"
                    className="absolute top-full right-0 z-50 mt-2 w-72 rounded-xl border bg-popover p-4 text-popover-foreground shadow-lg"
                >
                    <p className="mb-3 text-sm font-semibold">
                        Palet Warna Website
                    </p>
                    <ul className="grid gap-3">
                        {colors.map((color) => {
                            const value =
                                customColors[color.variable] ?? color.default;
                            const isCustom = color.variable in customColors;

                            return (
                                <li
                                    key={color.variable}
                                    className="flex items-center gap-3"
                                >
                                    <input
                                        type="color"
                                        value={normalizeHex(value)}
                                        onChange={(event) =>
                                            setColor(
                                                color.variable,
                                                event.target.value,
                                            )
                                        }
                                        aria-label={color.label}
                                        className="size-9 shrink-0 cursor-pointer rounded-md border bg-transparent p-0.5"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {color.label}
                                        </p>
                                        <p className="text-xs text-muted-foreground uppercase">
                                            {value}
                                        </p>
                                    </div>
                                    {isCustom && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                clearColor(color.variable)
                                            }
                                            title="Kembalikan warna asli"
                                            aria-label={`Kembalikan ${color.label} ke warna asli`}
                                            className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                                        >
                                            <RotateCcw className="size-4" />
                                        </button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </div>
    );
}

/** `<input type="color">` only accepts #rrggbb, so expand #rgb shorthand values. */
function normalizeHex(value: string): string {
    if (/^#[0-9a-f]{3}$/i.test(value)) {
        return `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`;
    }

    return value;
}
