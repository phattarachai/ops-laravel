import { t } from '../../i18n'

export function RecordingNotice({ recording }) {
    if (recording) {
        return null
    }

    return <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">{t('Request recording is off — these numbers were recorded before it was switched off.')}</div>
}
