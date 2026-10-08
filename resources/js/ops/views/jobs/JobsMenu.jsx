import { SlidersHorizontal } from 'lucide-react'

import { t } from '../../i18n'
import { Dropdown, DropdownOption, DropdownSection } from '../../ui/Dropdown'
import { queuesOf, statusFilters } from './shared'

export const DEFAULTS = { status: 'all', queue: 'all', grouped: true, showClass: false, showSilenced: false }

export function JobsMenu({ mode, rows, settings, onChange, silencedCount }) {
    const { status, queue, grouped, showClass, showSilenced } = settings
    const isList = mode === 'list'
    const filters = statusFilters()
    const queues = [...new Set(rows.flatMap(queuesOf))].sort()
    const customised = status !== DEFAULTS.status || showSilenced || showClass || (isList && (grouped !== DEFAULTS.grouped || queue !== DEFAULTS.queue))
    const current = filters.find((filter) => filter.value === status)

    return (
        <Dropdown icon={SlidersHorizontal} title={status === 'all' ? t('Filter & display') : `${t('Filter')} · ${current.label}`} active={customised}>
            <DropdownSection title={t('Status')}>
                {filters.map((filter) => (
                    <DropdownOption key={filter.value} selected={status === filter.value} onSelect={() => onChange({ status: filter.value })} hint={rows.filter(filter.match).length}>
                        {filter.label}
                    </DropdownOption>
                ))}
            </DropdownSection>
            {isList && (
                <DropdownSection title="Queue">
                    <DropdownOption selected={queue === 'all'} onSelect={() => onChange({ queue: 'all' })}>
                        {t('All queues')}
                    </DropdownOption>
                    {queues.map((name) => (
                        <DropdownOption key={name} selected={queue === name} onSelect={() => onChange({ queue: name })} hint={rows.filter((row) => queuesOf(row).includes(name)).length}>
                            {name}
                        </DropdownOption>
                    ))}
                </DropdownSection>
            )}
            <DropdownSection title={t('Show')}>
                {isList && (
                    <DropdownOption selected={grouped} onSelect={() => onChange({ grouped: !grouped })}>
                        {t('Group by domain')}
                    </DropdownOption>
                )}
                <DropdownOption selected={showClass} onSelect={() => onChange({ showClass: !showClass })}>
                    {t('Job class namespace')}
                </DropdownOption>
                <DropdownOption selected={showSilenced} onSelect={() => onChange({ showSilenced: !showSilenced })} hint={silencedCount}>
                    {t('Silenced jobs')}
                </DropdownOption>
            </DropdownSection>
        </Dropdown>
    )
}
