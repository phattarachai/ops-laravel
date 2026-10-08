import { TimeFilter } from '../charts'
import { OpsPage } from '../shell/OpsPage'
import { AreaBlock } from './overview/AreaBlock'
import { Attention } from './overview/Attention'

export default function Overview({ time_filter, areas, attention }) {
    return (
        <OpsPage title="Overview" toolbar={<TimeFilter />}>
            <div className="space-y-3">
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                    {areas.map((area) => (
                        <AreaBlock key={area.key} area={area} />
                    ))}
                </div>
                <Attention rows={attention} windowLabel={time_filter.label} />
            </div>
        </OpsPage>
    )
}
