import { Form, Head } from '@inertiajs/react';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Admin/SettingController';
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

type Props = {
    settings: {
        whatsapp_number: string;
        whatsapp_message_template: string;
        wp_theme_author: string;
        wp_theme_author_uri: string;
    };
};

export default function SettingsEdit({ settings }: Props) {
    return (
        <>
            <Head title="Pengaturan" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Pengaturan</h1>
                    <p className="text-sm text-muted-foreground">
                        Atur kontak WhatsApp pemesanan dan author tema
                        WordPress.
                    </p>
                </div>

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="grid max-w-2xl gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>WhatsApp Pemesanan</CardTitle>
                                    <CardDescription>
                                        Gunakan placeholder {'{template_name}'}{' '}
                                        dan {'{preview_url}'} pada template
                                        pesan.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="whatsapp_number">
                                            Nomor WhatsApp Admin
                                        </Label>
                                        <Input
                                            id="whatsapp_number"
                                            name="whatsapp_number"
                                            inputMode="numeric"
                                            defaultValue={
                                                settings.whatsapp_number
                                            }
                                            placeholder="6281234567890"
                                            required
                                        />
                                        <InputError
                                            message={errors.whatsapp_number}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="whatsapp_message_template">
                                            Template Pesan
                                        </Label>
                                        <textarea
                                            id="whatsapp_message_template"
                                            name="whatsapp_message_template"
                                            rows={5}
                                            defaultValue={
                                                settings.whatsapp_message_template
                                            }
                                            required
                                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <InputError
                                            message={
                                                errors.whatsapp_message_template
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Tema WordPress</CardTitle>
                                    <CardDescription>
                                        Dipakai pada header style.css tema hasil
                                        export.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="wp_theme_author">
                                            Nama Author
                                        </Label>
                                        <Input
                                            id="wp_theme_author"
                                            name="wp_theme_author"
                                            defaultValue={
                                                settings.wp_theme_author
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.wp_theme_author}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="wp_theme_author_uri">
                                            Author URI
                                        </Label>
                                        <Input
                                            id="wp_theme_author_uri"
                                            name="wp_theme_author_uri"
                                            type="url"
                                            defaultValue={
                                                settings.wp_theme_author_uri
                                            }
                                            placeholder="https://example.com"
                                        />
                                        <InputError
                                            message={errors.wp_theme_author_uri}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Simpan Pengaturan
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SettingsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan',
            href: edit(),
        },
    ],
};
