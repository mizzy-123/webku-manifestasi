import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    MessageCircle,
    MousePointerClick,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard, home, login } from '@/routes';
import editor from '@/routes/editor';

type TemplateItem = {
    id: number;
    name: string;
    slug: string;
    type: 'single-page' | 'multi-page';
    description: string | null;
    category: string;
    thumbnail_url: string;
    pages_count: number;
};

type Category = {
    id: number;
    name: string;
    slug: string;
};

type Filters = {
    category: string;
    type: string;
    search: string;
};

type Props = {
    templates: TemplateItem[];
    categories: Category[];
    filters: Filters;
};

const steps = [
    {
        icon: Search,
        title: '1. Pilih Template',
        text: 'Jelajahi katalog dan pilih desain yang paling cocok.',
    },
    {
        icon: MousePointerClick,
        title: '2. Edit Sesuka Hati',
        text: 'Ubah teks, warna, dan gambar langsung tanpa login.',
    },
    {
        icon: MessageCircle,
        title: '3. Simpan & Pesan',
        text: 'Simpan link desainmu, lalu pesan via WhatsApp.',
    },
];

const typeOptions = [
    { value: '', label: 'Semua' },
    { value: 'single-page', label: 'Single-Page' },
    { value: 'multi-page', label: 'Multi-Page' },
];

export default function CatalogIndex({
    templates,
    categories,
    filters,
}: Props) {
    const { auth, name } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const latestFilters = useRef(filters);

    latestFilters.current = filters;

    const applyFilters = (next: Partial<Filters>) => {
        const merged = { ...latestFilters.current, ...next };

        router.get(
            home().url,
            Object.fromEntries(
                Object.entries(merged).filter(([, value]) => value !== ''),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['templates', 'categories', 'filters'],
            },
        );
    };

    useEffect(() => {
        if (search === latestFilters.current.search) {
            return;
        }

        const timeout = setTimeout(() => applyFilters({ search }), 300);

        return () => clearTimeout(timeout);
    }, [search]);

    const hasFilters =
        filters.category !== '' || filters.type !== '' || filters.search !== '';

    return (
        <>
            <Head title="Katalog Template Website" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                        <Link
                            href={home()}
                            className="flex items-center gap-2 font-heading text-lg font-semibold"
                        >
                            <AppLogoIcon className="size-9 rounded-md" />
                            {name}
                        </Link>
                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="rounded-lg border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                        >
                            {auth.user ? 'Dashboard' : 'Login Admin'}
                        </Link>
                    </div>
                </header>

                <section className="bg-secondary/60">
                    <div className="mx-auto w-full max-w-6xl px-6 py-16 text-center">
                        <h1 className="mx-auto max-w-3xl text-4xl font-bold md:text-5xl">
                            Website Impian,{' '}
                            <span className="text-primary">Edit Sendiri</span>{' '}
                            Sebelum Dipesan
                        </h1>
                        <p className="mx-auto mt-4 max-w-2xl text-muted-foreground">
                            Pilih template Landing Page atau Company Profile,
                            sesuaikan sesuka hati secara langsung, lalu kirim
                            desainmu ke kami via WhatsApp.
                        </p>
                        <ol className="mt-10 grid gap-4 text-left md:grid-cols-3">
                            {steps.map((step) => (
                                <li
                                    key={step.title}
                                    className="rounded-xl border bg-background p-5"
                                >
                                    <span className="mb-3 flex size-10 items-center justify-center rounded-lg bg-secondary text-primary">
                                        <step.icon className="size-5" />
                                    </span>
                                    <h2 className="text-base font-semibold">
                                        {step.title}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {step.text}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                <main className="mx-auto w-full max-w-6xl flex-1 px-6 py-12">
                    <div className="flex flex-col gap-4">
                        <div className="relative max-w-md">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari template..."
                                aria-label="Cari template"
                                className="pl-9"
                            />
                        </div>

                        <div
                            className="flex flex-wrap items-center gap-2"
                            role="group"
                            aria-label="Filter kategori"
                        >
                            <FilterChip
                                active={filters.category === ''}
                                onClick={() => applyFilters({ category: '' })}
                            >
                                Semua Kategori
                            </FilterChip>
                            {categories.map((category) => (
                                <FilterChip
                                    key={category.id}
                                    active={filters.category === category.slug}
                                    onClick={() =>
                                        applyFilters({
                                            category: category.slug,
                                        })
                                    }
                                >
                                    {category.name}
                                </FilterChip>
                            ))}
                        </div>

                        <div
                            className="flex flex-wrap items-center gap-2"
                            role="group"
                            aria-label="Filter tipe"
                        >
                            {typeOptions.map((option) => (
                                <FilterChip
                                    key={option.value}
                                    active={filters.type === option.value}
                                    onClick={() =>
                                        applyFilters({ type: option.value })
                                    }
                                >
                                    {option.label}
                                </FilterChip>
                            ))}
                        </div>
                    </div>

                    {templates.length === 0 ? (
                        <div className="mt-12 rounded-xl border border-dashed p-12 text-center text-muted-foreground">
                            {hasFilters
                                ? 'Tidak ada template yang cocok dengan filter.'
                                : 'Belum ada template tersedia.'}
                        </div>
                    ) : (
                        <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {templates.map((template) => (
                                <article
                                    key={template.id}
                                    className="group flex flex-col overflow-hidden rounded-xl border bg-card transition hover:shadow-lg"
                                >
                                    <div className="aspect-[16/10] overflow-hidden border-b bg-muted">
                                        <img
                                            src={template.thumbnail_url}
                                            alt={`Tampilan ${template.name}`}
                                            loading="lazy"
                                            className="size-full object-cover transition duration-300 group-hover:scale-105"
                                        />
                                    </div>
                                    <div className="flex flex-1 flex-col gap-3 p-5">
                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="secondary">
                                                {template.type === 'multi-page'
                                                    ? `Multi-Page (${template.pages_count} halaman)`
                                                    : 'Single-Page'}
                                            </Badge>
                                            <Badge variant="outline">
                                                {template.category}
                                            </Badge>
                                        </div>
                                        <h3 className="text-lg font-semibold">
                                            {template.name}
                                        </h3>
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {template.description ??
                                                'Template siap pakai dan bisa dikustomisasi.'}
                                        </p>
                                        <Link
                                            href={editor.show(template.slug)}
                                            className="mt-auto inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                                        >
                                            Live Preview & Edit Sekarang
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </main>

                <footer className="border-t py-6 text-center text-sm text-muted-foreground">
                    © {new Date().getFullYear()} {name}. Seluruh hak cipta
                    dilindungi.
                </footer>
            </div>
        </>
    );
}

function FilterChip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'rounded-full border px-4 py-1.5 text-sm font-medium transition',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'hover:bg-accent',
            )}
        >
            {children}
        </button>
    );
}
