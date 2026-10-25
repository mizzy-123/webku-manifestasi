import { Head, router } from '@inertiajs/react';
import { Lock, LockOpen } from 'lucide-react';
import { toggleLock } from '@/actions/App/Http/Controllers/Admin/CustomizedPreviewController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type PreviewItem = {
    id: number;
    unique_code: string;
    url: string;
    template_name: string;
    order_clicked_at: string | null;
    is_locked_by_admin: boolean;
    is_expired: boolean;
    last_accessed_at: string;
    expires_at: string;
};

type Props = {
    previews: PreviewItem[];
};

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function PreviewsIndex({ previews }: Props) {
    return (
        <>
            <Head title="Preview Klien" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Preview Klien</h1>
                    <p className="text-sm text-muted-foreground">
                        Kunci preview milik klien yang sudah memesan agar tidak
                        terhapus otomatis setelah 30 hari.
                    </p>
                </div>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">Kode</th>
                                    <th className="p-3 font-medium">
                                        Template
                                    </th>
                                    <th className="p-3 font-medium">
                                        Order WA
                                    </th>
                                    <th className="p-3 font-medium">
                                        Terakhir Diakses
                                    </th>
                                    <th className="p-3 font-medium">
                                        Kedaluwarsa
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {previews.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="p-6 text-center text-muted-foreground"
                                        >
                                            Belum ada preview yang disimpan
                                            pengunjung.
                                        </td>
                                    </tr>
                                )}
                                {previews.map((preview) => (
                                    <tr
                                        key={preview.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono">
                                            <a
                                                href={preview.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-primary hover:underline"
                                            >
                                                {preview.unique_code}
                                            </a>
                                        </td>
                                        <td className="p-3">
                                            {preview.template_name}
                                        </td>
                                        <td className="p-3">
                                            {preview.order_clicked_at ? (
                                                <Badge>Sudah klik</Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Belum
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {dateFormatter.format(
                                                new Date(
                                                    preview.last_accessed_at,
                                                ),
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {preview.is_locked_by_admin ? (
                                                <Badge variant="secondary">
                                                    Dikunci
                                                </Badge>
                                            ) : (
                                                <span
                                                    className={
                                                        preview.is_expired
                                                            ? 'text-destructive'
                                                            : undefined
                                                    }
                                                >
                                                    {dateFormatter.format(
                                                        new Date(
                                                            preview.expires_at,
                                                        ),
                                                    )}
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3 text-right">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.patch(
                                                        toggleLock.url(
                                                            preview.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {preview.is_locked_by_admin ? (
                                                    <LockOpen />
                                                ) : (
                                                    <Lock />
                                                )}
                                                {preview.is_locked_by_admin
                                                    ? 'Buka Kunci'
                                                    : 'Kunci'}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
