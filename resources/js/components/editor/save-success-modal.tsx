import { Check, Copy, MessageCircle } from 'lucide-react';
import { useState } from 'react';
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
import type { SavedPreview } from '@/lib/preview-api';

type Props = {
    preview: SavedPreview | null;
    isOrdering: boolean;
    onClose: () => void;
    onOrder: () => void;
};

export default function SaveSuccessModal({
    preview,
    isOrdering,
    onClose,
    onOrder,
}: Props) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        if (!preview) {
            return;
        }

        await navigator.clipboard.writeText(preview.url);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Dialog
            open={preview !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Desain berhasil disimpan</DialogTitle>
                    <DialogDescription>
                        Simpan link ini untuk membuka dan mengedit desain Anda
                        kembali. Link aktif 30 hari sejak terakhir dibuka atau
                        disimpan.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex gap-2">
                    <Input
                        readOnly
                        value={preview?.url ?? ''}
                        onFocus={(event) => event.target.select()}
                    />
                    <Button type="button" variant="outline" onClick={copy}>
                        {copied ? <Check /> : <Copy />}
                        {copied ? 'Tersalin' : 'Salin Link'}
                    </Button>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Lanjut Edit
                    </Button>
                    <Button
                        type="button"
                        disabled={isOrdering}
                        onClick={onOrder}
                    >
                        <MessageCircle />
                        Lanjut Pesan via WhatsApp
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
