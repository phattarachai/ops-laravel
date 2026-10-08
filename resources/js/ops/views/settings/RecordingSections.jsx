import { t } from '../../i18n'
import { Section } from '../../ui/Section'
import { ListField, TextField, Toggle } from './fields'

function Recorder({ form, name, label, hint, children }) {
    return (
        <div className="space-y-2 border-b border-zinc-100 pb-3 last:border-0 last:pb-0">
            <Toggle form={form} path={`recorders.${name}.enabled`} label={label} hint={hint} />
            {children && <div className="space-y-2 pl-5">{children}</div>}
        </div>
    )
}

export function RecordersSection({ form }) {
    return (
        <Section title={t('Recorders')}>
            <div className="space-y-3">
                <Recorder form={form} name="requests" label={t('Incoming requests')}>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <ListField form={form} path="recorders.requests.paths" label={t('Record paths')} hint={t('One pattern per line, * matches anything')} />
                        <ListField form={form} path="recorders.requests.ignore" label={t('Ignore paths')} hint={t('One pattern per line')} />
                    </div>
                </Recorder>
                <Recorder form={form} name="outgoing" label={t('Outgoing HTTP calls')}>
                    <ListField form={form} path="recorders.outgoing.ignore_hosts" label={t('Ignore hosts')} hint={t('One host per line')} rows={2} />
                </Recorder>
                <Recorder form={form} name="jobs" label={t('Queued jobs')}>
                    <ListField form={form} path="recorders.jobs.ignore" label={t('Ignore job classes')} hint={t('One fully-qualified class per line')} rows={2} />
                </Recorder>
                <Recorder form={form} name="schedule" label={t('Scheduled tasks')} />
                <Recorder form={form} name="security" label={t('Security signals')} hint={t('Failed logins and the response statuses below, per IP and user')}>
                    <TextField form={form} path="recorders.security.statuses" label={t('Statuses')} hint={t('Comma separated, e.g. 401, 403, 429')} />
                </Recorder>
            </div>
        </Section>
    )
}

export function RetentionSection({ form }) {
    const days = { type: 'number', min: 1, max: 3650 }

    return (
        <Section title={t('Retention & signals')}>
            <div className="space-y-3">
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <TextField form={form} path="retention.minute_days" label={t('Minute rollups (days)')} {...days} />
                    <TextField form={form} path="retention.hour_days" label={t('Hour rollups (days)')} {...days} />
                    <TextField form={form} path="retention.user_days" label={t('Per-user rollups (days)')} {...days} />
                    <TextField form={form} path="retention.runs_days" label={t('Job & task runs (days)')} {...days} />
                    <TextField form={form} path="retention.signal_days" label={t('Signals (days)')} {...days} />
                </div>
                <TextField
                    form={form}
                    path="signals.user_route_floor"
                    label={t('User-route floor')}
                    hint={t('Requests per minute by one user on one route before it is recorded as a signal')}
                    type="number"
                    min={1}
                />
            </div>
        </Section>
    )
}
