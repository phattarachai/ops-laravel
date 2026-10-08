import { OpsPage } from '../shell/OpsPage'

export default function Missing({ view }) {
    return (
        <OpsPage title="Ops">
            <p className="py-10 text-center text-zinc-400">Unknown Ops view: {view}</p>
        </OpsPage>
    )
}
