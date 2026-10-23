import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { communityApi } from '../api/communityApi'
import type { ReportTarget } from '../types/community'

/** FR-132: a signed-in user reports a post, comment or review; REPORT_* codes map to friendly copy. */
export function useContentReport() {
  const { t } = useI18n()
  const sending = ref(false)
  const error = ref('')

  async function submit(target: ReportTarget, reason: string): Promise<boolean> {
    sending.value = true
    error.value = ''
    try {
      await communityApi.report(target, reason.trim())
      return true
    } catch (e) {
      error.value = errorMessage(e, t('community.report.error'))
      return false
    } finally {
      sending.value = false
    }
  }

  return { sending, error, submit }
}
