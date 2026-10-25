import { ImageUp } from 'lucide-react';
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
import { sanitizeUrl } from '@/lib/editor-sanitize';
import { useEditorStore } from '@/stores/use-editor-store';

const MAX_FILE_BYTES = 1024 * 1024;
const ACCEPTED_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

export default function ImageEditModal() {
    const activeElement = useEditorStore((state) => state.activeElement);
    const setActiveElement = useEditorStore((state) => state.setActiveElement);
    const setImage = useEditorStore((state) => state.setImage);

    const element = activeElement?.type === 'image' ? activeElement : null;
    const [src, setSrc] = useState('');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setSrc(element?.src ?? '');
        setError(null);
    }, [element?.id, element?.src]);

    const close = () => setActiveElement(null);

    const handleFile = (file: File | undefined) => {
        if (!file) {
            return;
        }

        if (!ACCEPTED_TYPES.includes(file.type)) {
            setError('Format gambar harus PNG, JPG, WebP, atau GIF.');

            return;
        }

        if (file.size > MAX_FILE_BYTES) {
            setError('Ukuran gambar maksimal 1 MB.');

            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result === 'string') {
                setSrc(reader.result);
                setError(null);
            }
        };
        reader.readAsDataURL(file);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!element) {
            return;
        }

        const clean = sanitizeUrl(src, true);

        if (clean === '') {
            setError('URL gambar tidak valid.');

            return;
        }

        setImage(element.scope, element.id, clean);
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
                        <DialogTitle>Ganti Gambar</DialogTitle>
                        <DialogDescription>
                            Unggah gambar dari komputer atau tempel URL gambar.
                            {element?.scope === 'global' &&
                                ' Perubahan ini berlaku di semua halaman.'}
                        </DialogDescription>
                    </DialogHeader>

                    {src !== '' && (
                        <div className="flex h-40 items-center justify-center overflow-hidden rounded-lg border bg-muted">
                            <img
                                src={src}
                                alt="Pratinjau gambar"
                                className="max-h-full max-w-full object-contain"
                            />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="image-file">
                            Unggah dari komputer (maks 1 MB)
                        </Label>
                        <Input
                            id="image-file"
                            type="file"
                            accept={ACCEPTED_TYPES.join(',')}
                            onChange={(event) =>
                                handleFile(event.target.files?.[0])
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="image-url">Atau URL gambar</Label>
                        <Input
                            id="image-url"
                            value={src.startsWith('data:') ? '' : src}
                            onChange={(event) => {
                                setSrc(event.target.value);
                                setError(null);
                            }}
                            placeholder="https://contoh.com/gambar.jpg"
                        />
                    </div>

                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Batal
                        </Button>
                        <Button type="submit">
                            <ImageUp />
                            Terapkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
