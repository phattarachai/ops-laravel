import { router } from '@inertiajs/react'
import { useState } from 'react'

import { t } from '../../i18n'
import { Badge } from '../../ui/Badge'
import { Button } from '../../ui/Button'
import { FormField, Input } from '../../ui/form'
import { Section } from '../../ui/Section'
import { ListField, SelectField, TextField, Toggle } from './fields'
import { assign, fieldError } from './form'

function LastDelivery({ delivery }) {
    if (!delivery) {
        return null
    }

    return (
        <Badge tone={delivery.delivered ? 'green' : 'red'}>
            <span title={delivery.error ?? undefined}>{t(delivery.delivered ? 'Last sent :time' : 'Last attempt failed :time', { time: delivery.at })}</span>
        </Badge>
    )
}

function TestButton({ url, dirty }) {
    const [sending, setSending] = useState(false)

    function send() {
        router.post(url, {}, { preserveScroll: true, preserveState: true, onStart: () => setSending(true), onFinish: () => setSending(false) })
    }

    return (
        <Button size="sm" variant="outline" icon="send" onClick={send} disabled={sending || dirty} title={dirty ? t('Save your changes before sending a test') : undefined}>
            {t('Send test')}
        </Button>
    )
}

function channelActions(delivery, url, dirty) {
    return (
        <>
            <LastDelivery delivery={delivery} />
            <TestButton url={url} dirty={dirty} />
        </>
    )
}

export function MailSection({ form, mailers, defaultMailer, delivery, testUrl }) {
    const options = [{ value: '', label: t('Default mailer (:name)', { name: defaultMailer ?? '—' }) }, ...mailers.map((mailer) => ({ value: mailer, label: mailer }))]

    return (
        <Section title={t('Mail')} actions={channelActions(delivery, testUrl, form.isDirty)}>
            <div className="space-y-3">
                <Toggle form={form} path="channels.mail.enabled" label={t('Send alerts by mail')} />
                <ListField form={form} path="channels.mail.recipients" label={t('Recipients')} hint={t('One address per line')} />
                <SelectField form={form} path="channels.mail.mailer" label={t('Mailer')} options={options} />
                <div className="grid gap-2 sm:grid-cols-2">
                    <TextField form={form} path="channels.mail.from_address" label={t('From address')} type="email" placeholder={t('Mailer default')} />
                    <TextField form={form} path="channels.mail.from_name" label={t('From name')} placeholder={t('Mailer default')} />
                </div>
            </div>
        </Section>
    )
}

function WebhookField({ form, configured }) {
    const path = 'channels.slack.webhook_url'
    const clearing = form.data.channels.slack.clear_webhook

    return (
        <FormField
            label={t('Webhook URL')}
            hint={configured ? t('Leave empty to keep the saved webhook') : t('An incoming-webhook URL from your Slack app')}
            error={fieldError(form.errors, path)}
            htmlFor="ops-setting-slack-webhook"
        >
            {configured && (
                <div className="mb-1 flex items-center gap-2">
                    <Badge tone={clearing ? 'red' : 'green'}>{clearing ? t('Will be removed') : t('Configured ••••')}</Badge>
                    <button type="button" onClick={() => form.setData((data) => assign(data, 'channels.slack.clear_webhook', !clearing))} className="text-[0.6875rem] text-zinc-500 hover:text-zinc-800">
                        {clearing ? t('Keep it') : t('Remove')}
                    </button>
                </div>
            )}
            <Input
                id="ops-setting-slack-webhook"
                type="url"
                autoComplete="off"
                value={form.data.channels.slack.webhook_url}
                onChange={(event) => form.setData((data) => assign(data, path, event.target.value))}
                placeholder={configured ? t('Paste a new URL to replace it') : 'https://hooks.slack.com/services/…'}
            />
        </FormField>
    )
}

export function SlackSection({ form, configured, delivery, testUrl }) {
    return (
        <Section title="Slack" actions={channelActions(delivery, testUrl, form.isDirty)}>
            <div className="space-y-3">
                <Toggle form={form} path="channels.slack.enabled" label={t('Send alerts to Slack')} />
                <WebhookField form={form} configured={configured} />
            </div>
        </Section>
    )
}
