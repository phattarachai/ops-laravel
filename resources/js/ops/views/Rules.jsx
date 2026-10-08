import { router } from '@inertiajs/react'
import { useState } from 'react'

import { t } from '../i18n'
import { OpsPage } from '../shell/OpsPage'
import { Button } from '../ui/Button'
import { SearchInput } from '../ui/SearchInput'
import { RuleDialog } from './rules/RuleDialog'
import { RulesTable } from './rules/RulesTable'

function Toolbar({ options, onCreate }) {
    const [seeding, setSeeding] = useState(false)

    function seedDefaults() {
        router.post(options.defaults_url, {}, { preserveScroll: true, onStart: () => setSeeding(true), onFinish: () => setSeeding(false) })
    }

    return (
        <div className="flex items-center gap-2">
            {options.missing_defaults > 0 && (
                <Button size="sm" variant="outline" onClick={seedDefaults} disabled={seeding} title={t(':count default rules are not added yet', { count: options.missing_defaults })}>
                    {t('Add default rules')}
                </Button>
            )}
            <Button size="sm" icon="plus" onClick={onCreate}>
                {t('New rule')}
            </Button>
        </div>
    )
}

export default function Rules({ rules, options, focus_rule_id }) {
    const [editing, setEditing] = useState(() => {
        const focused = rules.find((rule) => rule.id === focus_rule_id)

        return focused ? { rule: focused } : null
    })
    const [search, setSearch] = useState('')

    const term = search.trim().toLowerCase()
    const visible = rules.filter((rule) => term === '' || `${rule.name} ${rule.signal}`.toLowerCase().includes(term))

    return (
        <OpsPage title="Rules" toolbar={<Toolbar options={options} onCreate={() => setEditing({ rule: null })} />}>
            <div className="space-y-3">
                <div className="flex flex-wrap items-center gap-2">
                    <SearchInput value={search} onChange={setSearch} placeholder={t('Search rule / signal…')} />
                </div>

                <RulesTable rules={visible} onEdit={(rule) => setEditing({ rule })} />
            </div>

            {editing && <RuleDialog key={editing.rule?.id ?? 'new'} rule={editing.rule} options={options} onClose={() => setEditing(null)} />}
        </OpsPage>
    )
}
