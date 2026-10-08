import Incident from './Incident'
import Incidents from './Incidents'
import Job from './Job'
import Jobs from './Jobs'
import Outgoing from './Outgoing'
import OutgoingEndpoint from './OutgoingEndpoint'
import Overview from './Overview'
import RequestRoute from './RequestRoute'
import Requests from './Requests'
import Rules from './Rules'
import Schedule from './Schedule'
import Schedules from './Schedules'
import Settings from './Settings'

export const VIEWS = {
    overview: Overview,
    requests: Requests,
    'requests.show': RequestRoute,
    outgoing: Outgoing,
    'outgoing.show': OutgoingEndpoint,
    jobs: Jobs,
    'jobs.show': Job,
    schedules: Schedules,
    'schedules.show': Schedule,
    incidents: Incidents,
    'incidents.show': Incident,
    rules: Rules,
    settings: Settings,
}
