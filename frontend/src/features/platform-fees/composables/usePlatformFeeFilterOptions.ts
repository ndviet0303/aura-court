import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DropdownOption } from '@/shared/components'
import { platformFeeApi } from '../api/platformFeeApi'
import { PACKAGE_TERMS, type PlatformFeeVenue } from '../types/platformFee'

/** Venue and term filter options, "every venue" and "every term" first; /admin/venues is closed to platform admins. */
export function usePlatformFeeFilterOptions() {
  const { t } = useI18n()
  const venues = ref<PlatformFeeVenue[]>([])

  // ponytail: one page of 100 venues (the API's per_page cap); switch to a search box once the platform outgrows it.
  // The list only feeds the filter: if it fails, the screen still reads every venue.
  onMounted(async () => {
    const page = await platformFeeApi.getVenues({ per_page: 100 }).catch(() => null)
    venues.value = page?.data ?? []
  })

  const venueOptions = computed<DropdownOption[]>(() => [
    { value: 0, label: t('admin.platformFees.filters.allVenues') },
    ...venues.value
      .map((venue) => ({ value: venue.venue_id, label: venue.venue_name ?? `#${venue.venue_id}` }))
      .sort((a, b) => a.label.localeCompare(b.label, 'vi')),
  ])
  const termOptions = computed<DropdownOption[]>(() => [
    { value: 0, label: t('admin.platformFees.filters.allTerms') },
    ...PACKAGE_TERMS.map((days) => ({
      value: days,
      label: t('admin.packages.termLabel', { days }),
    })),
  ])

  return { venueOptions, termOptions }
}
