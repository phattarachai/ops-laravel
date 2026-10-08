import { UserRoundSearch } from 'lucide-react'

import { t } from '../../i18n'
import { Dropdown, DropdownOption, DropdownSection } from '../../ui/Dropdown'
import { visit } from './shared'

export function SegmentMenu({ filters, options }) {
    if (options.length === 0) {
        return null
    }

    return (
        <Dropdown icon={UserRoundSearch} title={t('Filter users by segment')} active={filters.segment !== null} align="right">
            <DropdownSection title={t('Segment')}>
                <div className="max-h-48 overflow-y-auto">
                    <DropdownOption selected={filters.segment === null} onSelect={() => visit({ segment: undefined })}>
                        {t('All segments')}
                    </DropdownOption>
                    {options.map((segment) => (
                        <DropdownOption key={segment} selected={filters.segment === segment} onSelect={() => visit({ segment })}>
                            {segment}
                        </DropdownOption>
                    ))}
                </div>
            </DropdownSection>
        </Dropdown>
    )
}
