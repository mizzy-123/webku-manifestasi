import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

export default function Unavailable() {
    return (
        <>
            <Head title="Preview tidak tersedia" />
            <main className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-background p-6 text-center">
                <h1 className="font-heading text-2xl font-semibold">
                    Link preview tidak ditemukan
                </h1>
                <p className="max-w-md text-muted-foreground">
                    Link ini tidak valid atau sudah melewati masa aktif 30 hari.
                    Anda bisa memilih template dan membuat desain baru dari
                    katalog.
                </p>
                <Button asChild>
                    <Link href={home()}>Lihat Katalog Template</Link>
                </Button>
            </main>
        </>
    );
}
