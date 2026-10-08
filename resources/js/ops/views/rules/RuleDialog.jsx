import { useForm } from '@inertiajs/react'

import { t } from '../../i18n'
import { Button } from '../../ui/Button'
import { Dialog } from '../../ui/Dialog'
import { FormField, Input, Select } from '../../ui/form'
import { blankDraft, errorFor, toDraft, toPayload } from './form'
import { typeLabel } from './summary'

const SIGNAL_LIST_ID = 'ops-rule-signals'

function Checkbox({ checked, onChange, children }) {
    return (
        <label className="inline-flex items-center gap-1.5 text-xs text-zinc-700">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="size-3.5 accent-ops-accent" />
            {children}
        </label>
    )
}

function Field({ form, name, label, hint, type = 'text', ...props }) {
    return (
        <FormField label={label} hint={hint} error={errorFor(form.errors, name)} htmlFor={`rule-${name}`}>
            <Input id={`rule-${name}`} type={type} value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} {...props} />
        </FormField>
    )
}

function SignalField({ form, name, label, signals }) {
    const known = signals.find((signal) => signal.value === form.data[name])

    return <Field form={form} name={name} label={label} hint={known?.description ?? t('A built-in signal or any name passed to Ops::signal()')} list={SIGNAL_LIST_ID} autoComplete="off" />
}

function TypeFields({ form, signals, levels }) {
    switch (form.data.type) {
        case 'rate':
            return (
                <>
                    <SignalField form={form} name="signal" label={t('Failure signal')} signals={signals} />
                    <SignalField form={form} name="denominator_signal" label={t('Total signal')} signals={signals} />
                    <div className="grid grid-cols-3 gap-2">
                        <Field form={form} name="threshold" label={t('Error rate ≥ (%)')} type="number" min="0" max="100" step="any" />
                        <Field form={form} name="min_samples" label={t('Min samples')} type="number" min="1" />
                        <Field form={form} name="window_minutes" label={t('Window (min)')} type="number" min="1" step="any" />
                    </div>
                </>
            )
        case 'absence':
            return (
                <>
                    <SignalField form={form} name="signal" label={t('Signal')} signals={signals} />
                    <Field form={form} name="silent_minutes" label={t('Silent for ≥ (min)')} type="number" min="1" step="any" />
                </>
            )
        case 'state':
            return (
                <>
                    <SignalField form={form} name="signal" label={t('Probe signal')} signals={signals} />
                    <FormField label={t('Alert at level')} error={form.errors.min_level}>
                        <Select value={form.data.min_level} onChange={(event) => form.setData('min_level', event.target.value)}>
                            {levels.map((level) => (
                                <option key={level} value={level}>
                                    {t(level === 'critical' ? 'Critical' : 'Warning')}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                </>
            )
        default:
            return (
                <>
                    <SignalField form={form} name="signal" label={t('Signal')} signals={signals} />
                    <div className="grid grid-cols-3 gap-2">
                        <Field form={form} name="threshold" label={t('Count ≥')} type="number" min="1" step="any" />
                        <Field form={form} name="window_minutes" label={t('Window (min)')} type="number" min="1" step="any" />
                        <Field form={form} name="distinct_field" label={t('Distinct field')} placeholder={t('optional')} />
                    </div>
                </>
            )
    }
}

function ChannelsField({ form, channels }) {
    function toggle(channel, checked) {
        form.setData('channels', checked ? [...form.data.channels, channel] : form.data.channels.filter((name) => name !== channel))
    }

    return (
        <FormField label={t('Channels')} hint={t('None ticked = every enabled channel')} error={errorFor(form.errors, 'channels')}>
            <div className="flex flex-wrap gap-3">
                {channels.map((channel) => (
                    <Checkbox key={channel} checked={form.data.channels.includes(channel)} onChange={(checked) => toggle(channel, checked)}>
                        {channel}
                    </Checkbox>
                ))}
            </div>
        </FormField>
    )
}

export function RuleDialog({ rule, options, onClose }) {
    const form = useForm(rule ? toDraft(rule) : { ...blankDraft(), environments_text: options.environment })

    function submit(event) {
        event.preventDefault()
        form.transform(toPayload)

        const visit = { preserveScroll: true, onSuccess: onClose }

        if (rule) {
            form.put(rule.update_url, visit)

            return
        }

        form.post(options.store_url, visit)
    }

    return (
        <Dialog
            open
            onClose={onClose}
            title={rule ? t('Edit rule') : t('New rule')}
            footer={
                <>
                    <Button variant="ghost" size="sm" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" form="ops-rule-form" size="sm" disabled={form.processing}>
                        {t('Save')}
                    </Button>
                </>
            }
        >
            <form id="ops-rule-form" onSubmit={submit} className="space-y-3">
                <datalist id={SIGNAL_LIST_ID}>
                    {options.signals.map((signal) => (
                        <option key={signal.value} value={signal.value}>
                            {signal.description}
                        </option>
                    ))}
                </datalist>

                <Field form={form} name="name" label={t('Name')} required />
                <div className="grid grid-cols-2 gap-2">
                    <FormField label={t('Type')} error={form.errors.type}>
                        <Select value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                            {options.types.map((type) => (
                                <option key={type} value={type}>
                                    {typeLabel(type)}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                    <FormField label={t('Severity')} error={form.errors.severity}>
                        <Select value={form.data.severity} onChange={(event) => form.setData('severity', event.target.value)} disabled={form.data.type === 'state'}>
                            {options.severities.map((severity) => (
                                <option key={severity} value={severity}>
                                    {t(severity === 'critical' ? 'Critical' : 'Warning')}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                </div>

                <TypeFields form={form} signals={options.signals} levels={options.levels} />

                <ChannelsField form={form} channels={options.channels} />
                <div className="grid grid-cols-2 gap-2">
                    <Field form={form} name="environments_text" label={t('Environments')} hint={t('Comma separated; empty = every environment')} />
                    <Field form={form} name="keys_text" label={t('Only these keys')} hint={t('Comma separated; empty = every key')} />
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <Field form={form} name="cooldown_minutes" label={t('Remind every (min)')} type="number" min="0" step="any" />
                    <Field form={form} name="resolve_after_minutes" label={t('Resolve after quiet (min)')} type="number" min="0" step="any" />
                </div>
                <div className="flex flex-wrap gap-4">
                    <Checkbox checked={form.data.active} onChange={(checked) => form.setData('active', checked)}>
                        {t('Active')}
                    </Checkbox>
                    <Checkbox checked={form.data.notify_on_resolve} onChange={(checked) => form.setData('notify_on_resolve', checked)}>
                        {t('Notify when resolved')}
                    </Checkbox>
                </div>
            </form>
        </Dialog>
    )
}
