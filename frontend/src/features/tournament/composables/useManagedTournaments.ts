import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { tournamentManageApi } from '../api/tournamentManageApi'

/** Organization owners create tournaments; officials and representatives only manage granted ones. */
export function useIsOrganizationOwner() {
  const auth = useAuthStore()
  return computed(() =>
    (auth.user?.organizations ?? []).some(
      (o) => o.status === 'active' && o.role === 'organization_owner',
    ),
  )
}

/** Owned, assigned (official) and granted (representative) tournaments, each with the caller's role. */
export function useManagedTournaments() {
  const { t } = useI18n()
  const { data, loading, error, execute } = useApiCall(tournamentManageApi.list, {
    initialLoading: true,
    defaultErrorMessage: t('tournament.loadError'),
  })
  onMounted(() => execute().catch(() => null))

  return {
    items: computed(() => data.value?.data ?? []),
    loading,
    error,
    isOwner: useIsOrganizationOwner(),
  }
}
