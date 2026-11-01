/**
 * Terjemahkan state `useForm` Inertia menjadi props feedback untuk <FileDropzone>.
 *
 * @example
 * const form = useForm({ avatar: null });
 * <FileDropzone value={form.data.avatar} {...useUploadFeedback(form, 'avatar')} />
 *
 * @param form  Hasil `useForm()`.
 * @param field Nama field file di form.
 * @returns     { uploading, progress, status, disabled } - status: idle | uploading | success | error
 */
export function useUploadFeedback(form, field) {
    const hasNewFile = form.data[field] instanceof File;
    const uploading = form.processing && hasNewFile;

    let status = 'idle';
    if (uploading) {
        status = 'uploading';
    } else if (form.errors[field]) {
        status = 'error';
    } else if (form.recentlySuccessful && hasNewFile) {
        status = 'success';
    }

    return {
        uploading,
        progress: uploading ? (form.progress?.percentage ?? 0) : null,
        status,
        disabled: form.processing,
    };
}
