<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/features/auth/stores/authStore'
import type { Reservation } from '../../types/reservation'

// Split the payment of a held booking with teammates, or open the split group it already has.
defineProps<{ booking: Reservation }>()
const auth = useAuthStore()
</script>

<template>
  <RouterLink
    v-if="auth.splitPaymentsEnabled && (booking.status === 'held' || booking.has_split_group)"
    :to="{ name: 'split-bill', params: { code: booking.code } }"
    data-testid="booking-split-btn"
  >
    <span class="material-symbols-outlined">group</span>
    <span>{{ $t('booking.splitBillBtn') }}</span>
  </RouterLink>
</template>
