<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useOwnerPaymentExceptions } from '../composables/useOwnerPaymentExceptions'
import { useOwnerPaymentExceptionDetail } from '../composables/useOwnerPaymentExceptionDetail'
import OwnerPaymentExceptionsTable from '../components/exceptions/OwnerPaymentExceptionsTable.vue'
import OwnerPaymentExceptionDetailModal from '../components/exceptions/OwnerPaymentExceptionDetailModal.vue'

const { t } = useI18n()
const { exceptions, page, lastPage, loading, error, load } = useOwnerPaymentExceptions()
const {
  openId,
  detail,
  loading: detailLoading,
  error: detailError,
  open,
  close,
} = useOwnerPaymentExceptionDetail()
</script>

<template>
  <div class="py-6 px-4 max-w-6xl mx-auto space-y-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
          {{ t('admin.paymentExceptions.title') }}
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          {{ t('admin.paymentExceptions.subtitle') }}
        </p>
      </div>
      <button
        type="button"
        :disabled="loading"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
        @click="load(page)"
      >
        <span class="material-symbols-outlined text-sm" aria-hidden="true">refresh</span>
        {{ t('admin.paymentExceptions.refresh') }}
      </button>
    </div>

    <p
      class="p-3 text-xs text-amber-800 bg-amber-50 rounded-lg dark:bg-amber-950/30 dark:text-amber-300"
    >
      {{ t('admin.paymentExceptions.readOnly') }}
    </p>
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
      <p v-if="loading && !exceptions.length" class="p-8 text-center text-sm text-gray-500">
        {{ t('common.loading') }}
      </p>
      <p
        v-else-if="!exceptions.length && !error"
        class="p-8 text-center text-sm text-gray-500 italic"
        data-testid="owner-payment-exceptions-empty"
      >
        {{ t('admin.paymentExceptions.empty') }}
      </p>
      <OwnerPaymentExceptionsTable
        v-else-if="exceptions.length"
        :exceptions="exceptions"
        @select="open"
      />
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

    <OwnerPaymentExceptionDetailModal
      v-if="openId !== null"
      :detail="detail"
      :loading="detailLoading"
      :error="detailError"
      @close="close"
    />
  </div>
</template>
