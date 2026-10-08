import { useForm } from '@inertiajs/react'

import { t } from '../i18n'
import { OpsPage } from '../shell/OpsPage'
import { Button } from '../ui/Button'
import { SectionGrid, SectionStack } from '../ui/SectionGrid'
import { MailSection, SlackSection } from './settings/ChannelSections'
import { toDraft, toPayload } from './settings/form'
import { RecordersSection, RetentionSection } from './settings/RecordingSections'

const FORM_ID = 'ops-settings-form'

function SaveButton({ form }) {
    return (
        <span className="flex items-center gap-2">
            {form.isDirty && <span className="hidden text-[0.6875rem] text-zinc-500 sm:inline">{t('Unsaved changes')}</span>}
            <Button type="submit" form={FORM_ID} size="sm" disabled={form.processing || !form.isDirty}>
                {t('Save settings')}
            </Button>
        </span>
    )
}

export default function Settings({ settings, slack_webhook_configured, mailers, default_mailer, deliveries, update_url, test_urls }) {
    const form = useForm(toDraft(settings))

    function submit(event) {
        event.preventDefault()
        form.transform(toPayload)
        form.put(update_url, {
            preserveScroll: true,
            onSuccess: (page) => {
                const next = toDraft(page.props.settings)
                form.setDefaults(next)
                form.setData(next)
            },
        })
    }

    return (
        <OpsPage title="Settings" toolbar={<SaveButton form={form} />}>
            <form id={FORM_ID} onSubmit={submit}>
                <SectionGrid>
                    <SectionStack>
                        <MailSection form={form} mailers={mailers} defaultMailer={default_mailer} delivery={deliveries.mail} testUrl={test_urls.mail} />
                        <SlackSection form={form} configured={slack_webhook_configured} delivery={deliveries.slack} testUrl={test_urls.slack} />
                    </SectionStack>
                    <SectionStack>
                        <RecordersSection form={form} />
                        <RetentionSection form={form} />
                    </SectionStack>
                </SectionGrid>
            </form>
        </OpsPage>
    )
}
