import { computed } from 'vue'
import { useRoute } from 'vue-router'

/** The same screens serve owners and staff in the admin layout and representatives outside it. */
export function useManageBase() {
  const route = useRoute()
  return computed(() =>
    route.path.startsWith('/admin/') ? '/admin/tournaments' : '/tournaments/manage',
  )
}
