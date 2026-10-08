import { FormField, Input, Select, Textarea } from '../../ui/form'
import { assign, fieldError, get } from './form'

const idOf = (path) => `ops-setting-${path.replaceAll('.', '-')}`

const update = (form, path, value) => form.setData((data) => assign(data, path, value))

export function Toggle({ form, path, label, hint }) {
    return (
        <label className="flex items-start gap-2 text-xs text-zinc-700">
            <input type="checkbox" checked={Boolean(get(form.data, path))} onChange={(event) => update(form, path, event.target.checked)} className="mt-0.5 size-3.5 accent-ops-accent" />
            <span>
                <span className="font-medium text-zinc-800">{label}</span>
                {hint && <span className="block text-zinc-400">{hint}</span>}
            </span>
        </label>
    )
}

export function TextField({ form, path, label, hint, type = 'text', ...props }) {
    return (
        <FormField label={label} hint={hint} error={fieldError(form.errors, path)} htmlFor={idOf(path)}>
            <Input id={idOf(path)} type={type} value={get(form.data, path) ?? ''} onChange={(event) => update(form, path, event.target.value)} {...props} />
        </FormField>
    )
}

export function ListField({ form, path, label, hint, rows = 3, ...props }) {
    return (
        <FormField label={label} hint={hint} error={fieldError(form.errors, path)} htmlFor={idOf(path)}>
            <Textarea id={idOf(path)} rows={rows} value={get(form.data, path) ?? ''} onChange={(event) => update(form, path, event.target.value)} className="min-h-0 font-mono text-xs" {...props} />
        </FormField>
    )
}

export function SelectField({ form, path, label, hint, options }) {
    return (
        <FormField label={label} hint={hint} error={fieldError(form.errors, path)} htmlFor={idOf(path)}>
            <Select id={idOf(path)} value={get(form.data, path) ?? ''} onChange={(event) => update(form, path, event.target.value === '' ? null : event.target.value)}>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </Select>
        </FormField>
    )
}
