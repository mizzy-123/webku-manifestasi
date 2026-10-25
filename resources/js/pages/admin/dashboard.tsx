import { Head, usePage } from '@inertiajs/react';
import {
    Download,
    FolderOpen,
    LayoutTemplate,
    Link2,
    MessageCircle,
    type LucideIcon,
} from 'lucide-react';
import { quick as quickExport } from '@/actions/App/Http/Controllers/Admin/WordPressExportController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Stats = {
    templates: number;
    categories: number;
    activePreviews: number;
    orderClicks: number;
};

type RecentOrder = {
    id: number;
    unique_code: string;
    template_name: string;
    order_clicked_at: string;
};

type Props = {
    stats: Stats;
    recentOrders: RecentOrder[];
};

type StatCard = {
    title: string;
    value: number;
    icon: LucideIcon;
};

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function AdminDashboard({ stats, recentOrders }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const cards: StatCard[] = [
        {
            title: 'Total Template',
            value: stats.templates,
            icon: LayoutTemplate,
        },
        { title: 'Kategori', value: stats.categories, icon: FolderOpen },
        {
            title: 'Link Preview Aktif',
            value: stats.activePreviews,
            icon: Link2,
        },
        {
            title: 'Klik Pesan Sekarang',
            value: stats.orderClicks,
            icon: MessageCircle,
        },
    ];

    return (
        <>
            <Head title="Dashboard Admin" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <Card key={card.title}>
                            <CardHeader className="flex flex-row items-center justify-between gap-2">
                                <CardDescription>{card.title}</CardDescription>
                                <span className="flex size-9 items-center justify-center rounded-lg bg-secondary text-primary">
                                    <card.icon className="size-5" />
                                </span>
                            </CardHeader>
                            <CardContent>
                                <p className="font-heading text-3xl font-semibold">
                                    {card.value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Quick Export to WordPress Theme</CardTitle>
                        <CardDescription>
                            Paste URL preview klien (atau kodenya) untuk
                            mengunduh tema WordPress siap pasang.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Plain GET form on purpose: the response is a file download, not an Inertia page. */}
                        <form
                            method="get"
                            action={quickExport.url()}
                            className="grid gap-4 md:grid-cols-[2fr_1fr_auto] md:items-end"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="export-source">
                                    URL atau kode preview
                                </Label>
                                <Input
                                    id="export-source"
                                    name="source"
                                    required
                                    placeholder="https://domain.com/p/k9Xm2pL8qR"
                                />
                                {errors.source && (
                                    <p className="text-sm text-destructive">
                                        {errors.source}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="export-theme-name">
                                    Nama tema (opsional)
                                </Label>
                                <Input
                                    id="export-theme-name"
                                    name="theme_name"
                                    maxLength={100}
                                />
                            </div>
                            <Button type="submit">
                                <Download />
                                Download WordPress Theme (.zip)
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Klien yang baru klik "Pesan Sekarang"
                        </CardTitle>
                        <CardDescription>
                            5 pesanan terakhir dari link preview klien.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentOrders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada klien yang memesan.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {recentOrders.map((order) => (
                                    <li
                                        key={order.id}
                                        className="flex items-center justify-between gap-4 py-3 text-sm"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {order.template_name}
                                            </p>
                                            <p className="text-muted-foreground">
                                                /p/{order.unique_code}
                                            </p>
                                        </div>
                                        <time className="text-muted-foreground">
                                            {dateFormatter.format(
                                                new Date(
                                                    order.order_clicked_at,
                                                ),
                                            )}
                                        </time>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
