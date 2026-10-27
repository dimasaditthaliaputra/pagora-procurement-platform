import { useForm } from '@inertiajs/react';
import { Save, Landmark, CreditCard, CheckCircle2, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { LoadingButton } from '@/Components/loading-button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { PengaturanLayout } from '@/Pages/Pengaturan/components/PengaturanLayout';

const LABELS = {
    harian: 'Harian',
    mingguan: 'Mingguan',
    manual: 'Manual',
    midtrans: 'Midtrans',
    xendit: 'Xendit',
    sandbox: 'Sandbox (Uji Coba)',
    production: 'Production',
};

function Field({ id, label, required = false, hint, error, className, children }) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id} className={cn('text-sm', error && 'text-destructive')}>
                {label} {required && <span className="text-destructive">*</span>}
            </Label>
            {children}
            {error ? (
                <p className="text-xs font-medium text-destructive">{error}</p>
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

export default function PengaturanIntegrasi({
    pengaturan = {},
    provider_options = [],
    mode_options = [],
    jadwal_sinkron_options = [],
}) {
    const { data, setData, put, processing, errors, setError, clearErrors } = useForm({
        bi_aktif: pengaturan.bi_aktif ?? true,
        bi_base_url: pengaturan.bi_base_url ?? '',
        bi_jadwal_sinkron: pengaturan.bi_jadwal_sinkron ?? 'harian',
        pg_provider: pengaturan.pg_provider ?? 'midtrans',
        pg_mode: pengaturan.pg_mode ?? 'sandbox',
        pg_client_key: pengaturan.pg_client_key ?? '',
        pg_server_key: '',
        pg_webhook_token: '',
    });

    const change = (field, value) => {
        setData(field, value);
        if (errors[field]) clearErrors(field);
    };

    const inputClass = (field) =>
        cn('h-9', errors[field] && 'border-destructive focus-visible:ring-destructive');

    const renderSelect = (field, options) => (
        <Select value={data[field]} onValueChange={(val) => change(field, val)} disabled={processing}>
            <SelectTrigger id={field} aria-invalid={Boolean(errors[field])} className={cn('h-9 text-sm w-full', errors[field] && 'border-destructive')}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {options.map((opt) => (
                    <SelectItem key={opt} value={opt}>
                        {LABELS[opt] ?? opt}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const secretHint = (terisi) =>
        terisi
            ? 'Sudah tersimpan. Kosongkan bila tidak ingin mengubah.'
            : 'Belum diisi.';

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!/^https:\/\/\S+$/.test(data.bi_base_url.trim())) {
            setError('bi_base_url', 'URL API Bank Indonesia harus berupa URL https yang valid.');
            notify.validationFailed();
            return;
        }

        put('/pengaturan/integrasi', {
            preserveScroll: true,
            onSuccess: () => setData((prev) => ({ ...prev, pg_server_key: '', pg_webhook_token: '' })),
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <PengaturanLayout title="Integrasi API">
            <form noValidate onSubmit={handleSubmit} className="w-full">
                <Card className="w-full shadow-sm flex flex-col">
                    <CardHeader className="py-3.5 px-5 border-b">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <Landmark className="size-4 text-primary" />
                                    API Harga Pangan Bank Indonesia
                                </CardTitle>
                                <CardDescription className="text-xs mt-1">
                                    Sumber data historis harga komoditas untuk prediksi Harga Pagu.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <Label htmlFor="bi_aktif" className="text-xs text-muted-foreground">
                                    {data.bi_aktif ? 'Aktif' : 'Nonaktif'}
                                </Label>
                                <Switch
                                    id="bi_aktif"
                                    checked={data.bi_aktif}
                                    onCheckedChange={(checked) => change('bi_aktif', checked)}
                                    disabled={processing}
                                />
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <Field id="bi_base_url" label="Base URL API" required error={errors.bi_base_url} className="sm:col-span-2">
                            <Input
                                id="bi_base_url"
                                placeholder="https://www.bi.go.id/hargapangan"
                                value={data.bi_base_url}
                                onChange={(e) => change('bi_base_url', e.target.value)}
                                aria-invalid={Boolean(errors.bi_base_url)}
                                className={inputClass('bi_base_url')}
                            />
                        </Field>
                        <Field id="bi_jadwal_sinkron" label="Jadwal Sinkronisasi" required error={errors.bi_jadwal_sinkron}>
                            {renderSelect('bi_jadwal_sinkron', jadwal_sinkron_options)}
                        </Field>
                    </CardContent>

                    <CardHeader className="py-3.5 px-5 border-y">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <CreditCard className="size-4 text-primary" />
                                    Payment Gateway
                                </CardTitle>
                                <CardDescription className="text-xs mt-1">
                                    Dipakai untuk pelunasan invoice setelah lolos 3-Way Matching.
                                </CardDescription>
                            </div>
                            {data.pg_mode === 'production' ? (
                                <Badge variant="destructive" className="gap-1 shrink-0">
                                    <AlertTriangle className="size-3" /> Production
                                </Badge>
                            ) : (
                                <Badge variant="secondary" className="gap-1 shrink-0">
                                    <CheckCircle2 className="size-3" /> Sandbox
                                </Badge>
                            )}
                        </div>
                    </CardHeader>

                    <CardContent className="p-5 space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field id="pg_provider" label="Provider" required error={errors.pg_provider}>
                                {renderSelect('pg_provider', provider_options)}
                            </Field>
                            <Field
                                id="pg_mode"
                                label="Mode"
                                required
                                error={errors.pg_mode}
                                hint={data.pg_mode === 'production' ? 'Transaksi akan memakai uang sungguhan.' : undefined}
                            >
                                {renderSelect('pg_mode', mode_options)}
                            </Field>
                        </div>

                        <Field id="pg_client_key" label="Client Key" error={errors.pg_client_key} hint="Kunci publik, aman ditampilkan di sisi browser.">
                            <Input
                                id="pg_client_key"
                                autoComplete="off"
                                value={data.pg_client_key}
                                onChange={(e) => change('pg_client_key', e.target.value)}
                                aria-invalid={Boolean(errors.pg_client_key)}
                                className={inputClass('pg_client_key')}
                            />
                        </Field>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field id="pg_server_key" label="Server Key" error={errors.pg_server_key} hint={secretHint(pengaturan.pg_server_key_terisi)}>
                                <Input
                                    id="pg_server_key"
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder={pengaturan.pg_server_key_terisi ? '••••••••••••' : ''}
                                    value={data.pg_server_key}
                                    onChange={(e) => change('pg_server_key', e.target.value)}
                                    aria-invalid={Boolean(errors.pg_server_key)}
                                    className={inputClass('pg_server_key')}
                                />
                            </Field>
                            <Field id="pg_webhook_token" label="Token Webhook / Callback" error={errors.pg_webhook_token} hint={secretHint(pengaturan.pg_webhook_token_terisi)}>
                                <Input
                                    id="pg_webhook_token"
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder={pengaturan.pg_webhook_token_terisi ? '••••••••••••' : ''}
                                    value={data.pg_webhook_token}
                                    onChange={(e) => change('pg_webhook_token', e.target.value)}
                                    aria-invalid={Boolean(errors.pg_webhook_token)}
                                    className={inputClass('pg_webhook_token')}
                                />
                            </Field>
                        </div>
                    </CardContent>

                    <CardFooter className="py-3 px-5 border-t bg-muted/20 flex justify-end">
                        <LoadingButton
                            type="submit"
                            loading={processing}
                            loadingText="Menyimpan Perubahan..."
                            className="gap-2 cursor-pointer shadow-sm h-9"
                        >
                            <Save className="size-4" />
                            Simpan Perubahan
                        </LoadingButton>
                    </CardFooter>
                </Card>
            </form>
        </PengaturanLayout>
    );
}
