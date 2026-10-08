import { setLocale } from './i18n'
import { LayoutContext } from './shell/LayoutContext'
import { OpsShell } from './shell/OpsShell'
import { VIEWS } from './views'
import Missing from './views/Missing'

export function Ops({ layout, ...props }) {
    setLocale(props.ops?.locale)

    const View = VIEWS[props.view] ?? Missing

    return (
        <LayoutContext.Provider value={layout ?? OpsShell}>
            <View {...props} />
        </LayoutContext.Provider>
    )
}
