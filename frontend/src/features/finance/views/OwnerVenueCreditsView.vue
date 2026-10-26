<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useOwnerVenueCredits } from '../composables/useOwnerVenueCredits'
import OwnerVenueCreditsTable from '../components/credits/OwnerVenueCreditsTable.vue'

const { t } = useI18n()
const { entries, outstanding, page, lastPage, loading, error, load } = useOwnerVenueCredits()
</script>

<template>
  <div class="py-6 px-4 max-w-6xl mx-auto space-y-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
          {{ t('admin.venueCredits.title') }}
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          {{ t('admin.venueCredits.subtitle') }}
        </p>
      </div>
      <button
        type="button"
        :disabled="loading"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
        @click="load(page)"
      >
        <span class="material-symbols-outlined text-sm" aria-hidden="true">refresh</span>
        {{ t('admin.venueCredits.refresh') }}
      </button>
    </div>

    <div
      class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700"
    >
      <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
        {{ t('admin.venueCredits.outstanding') }}
      </p>
      <p
        class="mt-1 text-2xl font-bold text-gray-900 dark:text-white"
        data-testid="owner-venue-credits-outstanding"
      >
        {{ formatCurrency(outstanding) }}
      </p>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {{ t('admin.venueCredits.outstandingHint') }}
      </p>
    </div>

    <p
      v-if="error"
      role="alert"
      class="p-3 text-xs text-red-800 bg-red-100 rounded-lg dark:bg-red-950/30 dark:text-red-400"
    >
      {{ error }}
    </p>

    <div
      class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700"
    >
      <p v-if="loading && !entries.length" class="p-8 text-center text-sm text-gray-500">
        {{ t('common.loading') }}
      </p>
      <p
        v-else-if="!entries.length && !error"
        class="p-8 text-center text-sm text-gray-500 italic"
        data-testid="owner-venue-credits-empty"
      >
        {{ t('admin.venueCredits.empty') }}
      </p>
      <OwnerVenueCreditsTable v-else-if="entries.length" :entries="entries" />
    </div>

    <nav v-if="lastPage > 1" class="flex items-center justify-end gap-3 text-sm">
      <button type="button" :disabled="loading || page <= 1" @click="load(page - 1)">
        {{ t('admin.paymentExceptions.prevPage') }}
      </button>
      <span>{{ t('admin.paymentExceptions.pageOf', { page, last: lastPage }) }}</span>
      <button type="button" :disabled="loading || page >= lastPage" @click="load(page + 1)">
        {{ t('admin.paymentExceptions.nextPage') }}
      </button>
    </nav>
  </div>
</template>
