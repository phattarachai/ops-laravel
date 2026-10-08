import { SlidersHorizontal, X } from 'lucide-react'

import { t } from '../../i18n'
import { Badge } from '../../ui/Badge'
import { Dropdown, DropdownOption, DropdownSection } from '../../ui/Dropdown'
import { healthFilters } from './shared'

export function FilterMenu({ endpoints, health, onChange }) {
    const filters = healthFilters()
    const active = filters.find((filter) => filter.value === health)

    return (
        <>
            <Dropdown icon={SlidersHorizontal} title={t('Filter')} active={health !== 'all'}>
                {({ close }) => (
                    <DropdownSection title={t('Health')}>
                        {filters.map((filter) => (
                            <DropdownOption
                                key={filter.value}
                                selected={health === filter.value}
                                hint={endpoints.filter(filter.match).length}
                                onSelect={() => {
                                    close()
                                    onChange(filter.value)
                                }}
                            >
                                {filter.label}
                            </DropdownOption>
                        ))}
                    </DropdownSection>
                )}
            </Dropdown>
            {health !== 'all' && (
                <Badge tone="zinc">
                    {active.label}
                    <button type="button" onClick={() => onChange('all')} aria-label={t('Clear filter')} className="text-zinc-400 hover:text-zinc-700">
                        <X className="size-3" />
                    </button>
                </Badge>
            )}
        </>
    )
}
