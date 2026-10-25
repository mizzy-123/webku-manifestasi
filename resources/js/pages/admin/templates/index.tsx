import { Form, Head } from '@inertiajs/react';
import { Download, ImageUp, Sparkles, Trash2, Upload } from 'lucide-react';
import {
    destroy,
    generateSamples,
    index,
    store,
    update,
    updateThumbnail,
} from '@/actions/App/Http/Controllers/Admin/TemplateController';
import { template as exportTemplate } from '@/actions/App/Http/Controllers/Admin/WordPressExportController';
import InputError from '@/components/input-error';
import RupiahInput from '@/components/rupiah-input';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';

type TemplateItem = {
    id: number;
    name: string;
    slug: string;
    type: 'single-page' | 'multi-page';
    description: string | null;
    category: string;
    thumbnail_url: string;
    pages_count: number;
    colors_count: number;
    price: string | null;
    is_active: boolean;
};

type Category = {
    id: number;
    name: string;
};

type Props = {
    templates: TemplateItem[];
    categories: Category[];
};

const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function TemplatesIndex({ templates, categories }: Props) {
    return (
        <>
            <Head title="Kelola Template" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Kelola Template
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Unggah template .zip atau buat sample template untuk
                            langsung menguji sistem.
                        </p>
                    </div>
                    <Form {...generateSamples.form()}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing ? <Spinner /> : <Sparkles />}
                                Generate 2 Sample Templates
                            </Button>
                        )}
                    </Form>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Unggah Template Baru</CardTitle>
                        <CardDescription>
                            ZIP wajib berisi manifest.json, index.html, dan
                            thumbnail.jpg (maks 20 MB).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            resetOnSuccess
                            className="grid gap-4 md:grid-cols-2"
                        >
                            {({ processing, errors, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="file">File ZIP</Label>
                                        <Input
                                            id="file"
                                            type="file"
                                            name="file"
                                            accept=".zip"
                                            required
                                        />
                                        <InputError message={errors.file} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="category_id">
                                            Kategori
                                        </Label>
                                        <select
                                            id="category_id"
                                            name="category_id"
                                            required
                                            defaultValue=""
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <option value="" disabled>
                                                Pilih kategori
                                            </option>
                                            {categories.map((category) => (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.category_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="price">
                                            Harga referensi (opsional)
                                        </Label>
                                        <RupiahInput
                                            id="price"
                                            recentlySuccessful={
                                                recentlySuccessful
                                            }
                                        />
                                        <InputError message={errors.price} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Deskripsi (opsional)
                                        </Label>
                                        <Input
                                            id="description"
                                            name="description"
                                            maxLength={1000}
                                            placeholder="Kosongkan untuk memakai deskripsi dari manifest.json"
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Upload />
                                            )}
                                            Unggah Template
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {templates.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            Belum ada template. Klik "Generate 2 Sample
                            Templates" atau unggah file ZIP.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {templates.map((template) => (
                            <Card key={template.id} className="overflow-hidden">
                                <img
                                    src={template.thumbnail_url}
                                    alt={`Thumbnail ${template.name}`}
                                    className="aspect-[16/10] w-full border-b object-cover"
                                />
                                <CardHeader>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="secondary">
                                            {template.type === 'multi-page'
                                                ? `Multi-Page (${template.pages_count})`
                                                : 'Single-Page'}
                                        </Badge>
                                        <Badge variant="outline">
                                            {template.category}
                                        </Badge>
                                        <Badge
                                            variant={
                                                template.is_active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {template.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </Badge>
                                    </div>
                                    <CardTitle className="text-base">
                                        {template.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {template.description ??
                                            'Tanpa deskripsi.'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <Form
                                        {...updateThumbnail.form(template.id)}
                                        options={{ preserveScroll: true }}
                                        className="grid gap-2"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <Label
                                                    htmlFor={`thumbnail-${template.id}`}
                                                >
                                                    Gambar katalog
                                                </Label>
                                                <Input
                                                    id={`thumbnail-${template.id}`}
                                                    type="file"
                                                    name="thumbnail"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.thumbnail}
                                                />
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={processing}
                                                    className="w-fit"
                                                >
                                                    {processing ? (
                                                        <Spinner />
                                                    ) : (
                                                        <ImageUp />
                                                    )}
                                                    Ganti Gambar Katalog
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                    <p className="text-xs text-muted-foreground">
                                        {template.colors_count} warna dapat
                                        dikustomisasi
                                        {template.price !== null &&
                                            ` · ${currencyFormatter.format(Number(template.price))}`}
                                    </p>
                                    <div className="flex gap-2">
                                        <Form
                                            {...update.form(template.id)}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="is_active"
                                                        value={
                                                            template.is_active
                                                                ? '0'
                                                                : '1'
                                                        }
                                                    />
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        {template.is_active
                                                            ? 'Nonaktifkan'
                                                            : 'Aktifkan'}
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <a
                                                href={exportTemplate.url(
                                                    template.id,
                                                )}
                                            >
                                                <Download />
                                                Export WP (.zip)
                                            </a>
                                        </Button>
                                        <Form
                                            {...destroy.form(template.id)}
                                            options={{ preserveScroll: true }}
                                            onBefore={() =>
                                                window.confirm(
                                                    `Hapus template "${template.name}" beserta seluruh file fisiknya?`,
                                                )
                                            }
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    <Trash2 />
                                                    Hapus
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

TemplatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Kelola Template',
            href: index(),
        },
    ],
};
