import { Form, Head } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/CategoryController';
import InputError from '@/components/input-error';
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

type CategoryItem = {
    id: number;
    name: string;
    slug: string;
    templates_count: number;
};

type Props = {
    categories: CategoryItem[];
};

export default function CategoriesIndex({ categories }: Props) {
    return (
        <>
            <Head title="Kategori" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Kategori</h1>
                    <p className="text-sm text-muted-foreground">
                        Kategori yang ditambahkan di sini langsung muncul saat
                        mengunggah template dan di filter katalog publik.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Tambah Kategori</CardTitle>
                        <CardDescription>
                            Slug dibuat otomatis dari nama.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            resetOnSuccess
                            className="flex flex-wrap items-end gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid min-w-64 flex-1 gap-2">
                                        <Label htmlFor="category-name">
                                            Nama
                                        </Label>
                                        <Input
                                            id="category-name"
                                            name="name"
                                            required
                                            maxLength={100}
                                            placeholder="Kafe & Restoran"
                                        />
                                        <InputError
                                            message={errors.name ?? errors.slug}
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Tambah
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="grid gap-4 p-4">
                        {categories.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Belum ada kategori.
                            </p>
                        )}
                        {categories.map((category) => (
                            <div
                                key={category.id}
                                className="flex flex-wrap items-start gap-3 border-b pb-4 last:border-0 last:pb-0"
                            >
                                <Form
                                    key={`${category.id}-${category.slug}`}
                                    {...update.form(category.id)}
                                    options={{ preserveScroll: true }}
                                    className="grid min-w-0 flex-1 gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-center"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1">
                                                <Input
                                                    name="name"
                                                    defaultValue={category.name}
                                                    required
                                                    maxLength={100}
                                                    aria-label={`Nama ${category.name}`}
                                                />
                                                <InputError
                                                    message={
                                                        errors.name ??
                                                        errors.slug
                                                    }
                                                />
                                                <p className="font-mono text-xs text-muted-foreground sm:hidden">
                                                    {category.slug} ·{' '}
                                                    {category.templates_count}{' '}
                                                    template
                                                </p>
                                            </div>
                                            <p className="hidden font-mono text-xs text-muted-foreground sm:block">
                                                {category.slug}
                                                <span className="mt-1 block font-sans">
                                                    {category.templates_count}{' '}
                                                    template
                                                </span>
                                            </p>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    'Simpan'
                                                )}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                <Form
                                    {...destroy.form(category.id)}
                                    options={{ preserveScroll: true }}
                                    onBefore={() =>
                                        category.templates_count === 0 &&
                                        window.confirm(
                                            `Hapus kategori "${category.name}"?`,
                                        )
                                    }
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="destructive"
                                            disabled={
                                                processing ||
                                                category.templates_count > 0
                                            }
                                            title={
                                                category.templates_count > 0
                                                    ? 'Masih dipakai template'
                                                    : 'Hapus kategori'
                                            }
                                        >
                                            <Trash2 />
                                            Hapus
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Kategori',
            href: index(),
        },
    ],
};
