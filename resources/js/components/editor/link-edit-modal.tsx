import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { sanitizeText, sanitizeUrl } from '@/lib/editor-sanitize';
import { useEditorStore } from '@/stores/use-editor-store';
import type { EditorPage } from '@/types/editor';

type Props = {
    pages: EditorPage[];
};

export default function LinkEditModal({ pages }: Props) {
    const activeElement = useEditorStore((state) => state.activeElement);
    const setActiveElement = useEditorStore((state) => state.setActiveElement);
    const setLink = useEditorStore((state) => state.setLink);

    const element = activeElement?.type === 'link' ? activeElement : null;
    const [text, setText] = useState('');
    const [href, setHref] = useState('');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setText(element?.text ?? '');
        setHref(element?.href ?? '');
        setError(null);
    }, [element?.id, element?.text, element?.href]);

    const close = () => setActiveElement(null);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!element) {
            return;
        }

        const cleanHref = sanitizeUrl(href);

        if (href.trim() !== '' && cleanHref === '') {
            setError('Tujuan link tidak valid.');

            return;
        }

        setLink(element.scope, element.id, {
            text: sanitizeText(text),
            href: cleanHref,
        });
        close();
    };

    return (
        <Dialog
            open={element !== null}
            onOpenChange={(open) => !open && close()}
        >
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Edit Tombol / Link</DialogTitle>
                        <DialogDescription>
                            Ubah tulisan dan tujuan tombol.
                            {element?.scope === 'global' &&
                                ' Perubahan ini berlaku di semua halaman.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="link-text">Tulisan tombol</Label>
                        <Input
                            id="link-text"
                            value={text}
                            onChange={(event) => setText(event.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="link-href">Tujuan link</Label>
                        <Input
                            id="link-href"
                            value={href}
                            list="editor-page-files"
                            onChange={(event) => {
                                setHref(event.target.value);
                                setError(null);
                            }}
                            placeholder="https://wa.me/628123456789 atau about.html"
                        />
                        <datalist id="editor-page-files">
                            {pages.map((page) => (
                                <option key={page.slug} value={page.file}>
                                    {page.title}
                                </option>
                            ))}
                        </datalist>
                    </div>

                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Batal
                        </Button>
                        <Button type="submit">Terapkan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
