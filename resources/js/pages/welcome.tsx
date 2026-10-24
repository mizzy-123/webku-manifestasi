import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Beranda" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between p-6">
                    <span className="font-heading text-lg font-semibold text-primary">
                        {name}
                    </span>
                    <Link
                        href={auth.user ? dashboard() : login()}
                        className="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        {auth.user ? 'Dashboard' : 'Login Admin'}
                    </Link>
                </header>
                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                    <span className="rounded-full bg-secondary px-4 py-1 text-sm font-medium text-secondary-foreground">
                        Segera hadir
                    </span>
                    <h1 className="text-4xl font-bold md:text-5xl">
                        Katalog Template Website
                    </h1>
                    <p className="max-w-xl text-muted-foreground">
                        Pilih template, edit sesuka hati tanpa login, simpan
                        link desainmu, lalu pesan langsung via WhatsApp.
                    </p>
                </main>
            </div>
        </>
    );
}
