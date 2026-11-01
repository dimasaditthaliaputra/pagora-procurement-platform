import { useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Lock, Save, ShieldCheck, Users } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Badge } from '@/Components/ui/badge';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { LoadingButton } from '@/Components/loading-button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';

const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Manajemen', href: '#' },
    { label: 'Role & Hak Akses', href: '#' },
];

function RoleAccessForm({ role, permissionGroups }) {
    const canUpdate = role.can?.update ?? false;

    const { data, setData, put, processing, errors, isDirty, setDefaults } = useForm({
        description: role.description ?? '',
        permissions: role.permissions ?? [],
    });

    const granted = useMemo(() => new Set(data.permissions), [data.permissions]);

    const togglePermission = (name, checked) => {
        setData(
            'permissions',
            checked ? [...data.permissions, name] : data.permissions.filter((item) => item !== name)
        );
    };

    const toggleGroup = (permissions, checked) => {
        const names = permissions.map((permission) => permission.name);
        const rest = data.permissions.filter((item) => !names.includes(item));
        setData('permissions', checked ? [...rest, ...names] : rest);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/manajemen/role-akses/${role.name}`, {
            preserveScroll: true,
            onSuccess: () => setDefaults(),
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <form noValidate onSubmit={handleSubmit} className="w-full">
            <Card className="w-full shadow-sm flex flex-col">
                <CardHeader className="py-3.5 px-5 border-b">
                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                        {role.label}
                        <Badge variant="secondary" className="gap-1 font-normal">
                            <Users className="size-3" />
                            {role.users_count} pengguna
                        </Badge>
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Perubahan hak akses langsung berlaku untuk seluruh pengguna dengan role ini.
                    </CardDescription>
                </CardHeader>

                <CardContent className="p-5 space-y-5">
                    <div className="grid gap-1.5">
                        <Label htmlFor="description" className={cn('text-sm', errors.description && 'text-destructive')}>
                            Deskripsi Role
                        </Label>
                        <Textarea
                            id="description"
                            rows={2}
                            value={data.description}
                            disabled={!canUpdate}
                            onChange={(e) => setData('description', e.target.value)}
                            aria-invalid={Boolean(errors.description)}
                        />
                        {errors.description && (
                            <p className="text-xs font-medium text-destructive">{errors.description}</p>
                        )}
                    </div>

                    {errors.permissions && (
                        <p className="text-xs font-medium text-destructive">{errors.permissions}</p>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {permissionGroups.map((group) => {
                            const assignable = group.permissions.filter(
                                (permission) =>
                                    permission.assignable_roles.includes(role.name) &&
                                    !permission.locked_roles.includes(role.name)
                            );
                            const allChecked =
                                assignable.length > 0 && assignable.every((permission) => granted.has(permission.name));

                            return (
                                <div key={group.module} className="rounded-lg border p-4 space-y-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <h3 className="text-sm font-semibold">{group.label}</h3>
                                        {canUpdate && assignable.length > 0 && (
                                            <label className="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer">
                                                <Checkbox
                                                    checked={allChecked}
                                                    onCheckedChange={(checked) => toggleGroup(assignable, checked === true)}
                                                />
                                                Pilih semua
                                            </label>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        {group.permissions.map((permission) => {
                                            const isAssignable = permission.assignable_roles.includes(role.name);
                                            const isLocked = permission.locked_roles.includes(role.name);
                                            const id = `${role.name}-${permission.name}`;

                                            return (
                                                <div key={permission.name} className="flex items-center gap-2">
                                                    <Checkbox
                                                        id={id}
                                                        checked={granted.has(permission.name)}
                                                        disabled={!canUpdate || !isAssignable || isLocked}
                                                        onCheckedChange={(checked) =>
                                                            togglePermission(permission.name, checked === true)
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={id}
                                                        className={cn(
                                                            'text-sm font-normal flex items-center gap-1.5',
                                                            !isAssignable && 'text-muted-foreground'
                                                        )}
                                                    >
                                                        {permission.label}
                                                        {isLocked && <Lock className="size-3 text-muted-foreground" />}
                                                        {!isAssignable && (
                                                            <span className="text-[11px]">(tidak tersedia untuk role ini)</span>
                                                        )}
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </CardContent>

                {canUpdate && (
                    <CardFooter className="py-3 px-5 border-t bg-muted/20 flex justify-end">
                        <LoadingButton
                            type="submit"
                            loading={processing}
                            disabled={!isDirty}
                            loadingText="Menyimpan Perubahan..."
                            className="gap-2 cursor-pointer shadow-sm h-9"
                        >
                            <Save className="size-4" />
                            Simpan Hak Akses
                        </LoadingButton>
                    </CardFooter>
                )}
            </Card>
        </form>
    );
}

export default function RoleAksesIndex({ roles, permission_groups: permissionGroups = [] }) {
    const roleList = roles?.data ?? roles ?? [];
    const [selectedName, setSelectedName] = useState(roleList[0]?.name ?? null);
    const selectedRole = roleList.find((role) => role.name === selectedName) ?? roleList[0];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Role & Hak Akses" />

            <div className="flex flex-col gap-4 w-full">
                <div>
                    <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                        <ShieldCheck className="size-5 text-primary" />
                        Role & Hak Akses
                    </h1>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Atur hak akses setiap role per modul. Daftar role bersifat tetap; batas kepemilikan data
                        Tenant/Vendor tetap berlaku.
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4 items-start">
                    <nav className="flex lg:flex-col gap-2 overflow-x-auto">
                        {roleList.map((role) => (
                            <button
                                key={role.name}
                                type="button"
                                onClick={() => setSelectedName(role.name)}
                                className={cn(
                                    'text-left rounded-lg border px-3 py-2.5 transition-colors min-w-48 lg:min-w-0 cursor-pointer',
                                    selectedRole?.name === role.name
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted/50'
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-sm font-semibold">{role.label}</span>
                                    <span className="text-[11px] text-muted-foreground">{role.users_count} pengguna</span>
                                </div>
                                <p className="text-[11px] text-muted-foreground mt-0.5">
                                    {role.permissions?.length ?? 0} hak akses
                                </p>
                            </button>
                        ))}
                    </nav>

                    {selectedRole && (
                        <RoleAccessForm key={selectedRole.name} role={selectedRole} permissionGroups={permissionGroups} />
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}
