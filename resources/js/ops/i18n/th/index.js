import common from './common'
import incidents from './incidents'
import jobs from './jobs'
import outgoing from './outgoing'
import overview from './overview'
import requests from './requests'
import schedules from './schedules'
import settings from './settings'
import system from './system'

export default { ...common, ...overview, ...requests, ...outgoing, ...jobs, ...schedules, ...incidents, ...settings, ...system }
