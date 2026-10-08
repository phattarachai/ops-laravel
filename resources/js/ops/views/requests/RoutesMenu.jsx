import { SlidersHorizontal } from 'lucide-react'

import { t } from '../../i18n'
import { number } from '../../lib/format'
import { Dropdown, DropdownOption, DropdownSection } from '../../ui/Dropdown'

export function statusFilters() {
    return [
        { value: 'all', label: t('All'), match: () => true },
        { value: 's5xx', label: t('Has 5xx'), match: (route) => route.s5xx > 0 },
        { value: 's429', label: t('Has 429'), match: (route) => route.s429 > 0 },
        { value: 's4xx', label: t('Has 4xx'), match: (route) => route.s4xx > 0 },
    ]
}

export const DEFAULTS = { status: 'all', grouped: false, showName: false, showUnmatched: false }

export function RoutesMenu({ routes, settings, onChange, unmatchedRequests }) {
    const { status, grouped, showName, showUnmatched } = settings
    const customised = status !== DEFAULTS.status || grouped || showName || showUnmatched

    return (
        <Dropdown icon={SlidersHorizontal} title={t('Filter & display')} active={customised}>
            <DropdownSection title={t('Status')}>
                {statusFilters().map((filter) => (
                    <DropdownOption key={filter.value} selected={status === filter.value} onSelect={() => onChange({ status: filter.value })} hint={routes.filter(filter.match).length}>
                        {filter.label}
                    </DropdownOption>
                ))}
            </DropdownSection>
            <DropdownSection title={t('Show')}>
                <DropdownOption selected={grouped} onSelect={() => onChange({ grouped: !grouped })}>
                    {t('Group by resource')}
                </DropdownOption>
                <DropdownOption selected={showName} onSelect={() => onChange({ showName: !showName })}>
                    {t('Route name')}
                </DropdownOption>
                <DropdownOption selected={showUnmatched} onSelect={() => onChange({ showUnmatched: !showUnmatched })} hint={number(unmatchedRequests)}>
                    {t('Requests that matched no route (unmatched)')}
                </DropdownOption>
            </DropdownSection>
        </Dropdown>
    )
}
