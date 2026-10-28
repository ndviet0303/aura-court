import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { transferApi } from '../api/transferApi'
import type { TransferListing } from '../types/transfer'

const SEARCH_DEBOUNCE_MS = 300

/** UC-9.3, UC-9.4: listings that still have a booking to take, searched on the server, page by page. */
export function useTransferListings() {
  const { t } = useI18n()
  const q = ref('')
  const items = ref<TransferListing[]>([])
  const page = ref(1)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')
  let latest = 0
  let timer: number | undefined

  async function load(target = 1) {
    const request = ++latest
    loading.value = true
    error.value = ''
    try {
      const res = await transferApi.listings(q.value.trim(), target)
      if (request !== latest) return
      items.value = target === 1 ? res.data : [...items.value, ...res.data]
      page.value = res.meta.current_page
      lastPage.value = res.meta.last_page
    } catch (e) {
      if (request === latest) error.value = errorMessage(e, t('resale.loadError'))
    } finally {
      if (request === latest) loading.value = false
    }
  }

  watch(q, () => {
    window.clearTimeout(timer)
    timer = window.setTimeout(() => void load(1), SEARCH_DEBOUNCE_MS)
  })
  onMounted(() => void load(1))
  onUnmounted(() => window.clearTimeout(timer))

  return {
    q,
    items,
    loading,
    error,
    hasMore: computed(() => page.value < lastPage.value),
    loadMore: () => load(page.value + 1),
  }
}
