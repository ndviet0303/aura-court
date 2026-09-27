<script setup lang="ts">
import type { PartnerReviewHistory } from '../types'

defineProps<{
  history: PartnerReviewHistory[]
  createdDate?: string
}>()

function formatDate(dateStr?: string): string {
  if (!dateStr) return 'N/A'
  try {
    const d = new Date(dateStr)
    return d.toLocaleString('vi-VN', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dateStr
  }
}
</script>

<template>
  <div class="application-history-timeline space-y-4">
    <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
      <span class="material-symbols-outlined text-base text-gray-500">history</span>
      Lịch sử Thẩm định & Cập nhật
    </h4>

    <div v-if="!history.length" class="text-xs text-gray-500 dark:text-gray-400 italic">
      Chưa có ghi nhận thẩm định nào từ đội ngũ vận hành.
    </div>

    <ol v-else class="relative border-l border-gray-200 dark:border-gray-700 ml-2 space-y-4">
      <li v-for="item in history" :key="item.id" class="ml-4">
        <div
          class="absolute w-2.5 h-2.5 rounded-full -left-1.5 border border-white dark:border-gray-900 mt-1.5"
          :class="{
            'bg-emerald-500': item.decision === 'approve',
            'bg-amber-500': item.decision === 'revision_required' || item.decision === 'supplement',
            'bg-red-500': item.decision === 'reject',
          }"
        ></div>

        <div class="p-3 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-lg shadow-sm space-y-1">
          <div class="flex items-center justify-between gap-2">
            <span
              class="text-xs font-semibold px-2 py-0.5 rounded"
              :class="{
                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': item.decision === 'approve',
                'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': item.decision === 'revision_required' || item.decision === 'supplement',
                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': item.decision === 'reject',
              }"
            >
              {{ item.decision === 'approve' ? 'Phê duyệt' : item.decision === 'revision_required' || item.decision === 'supplement' ? 'Yêu cầu bổ sung' : 'Từ chối' }}
            </span>
            <time class="text-[11px] text-gray-400">{{ formatDate(item.created_at) }}</time>
          </div>

          <p v-if="item.reason" class="text-xs text-gray-700 dark:text-gray-300 mt-1">
            {{ item.reason }}
          </p>

          <div v-if="item.feedback_fields && Object.keys(item.feedback_fields).length > 0" class="mt-2 text-[11px] bg-gray-50 dark:bg-gray-900/50 p-2 rounded">
            <span class="font-medium text-gray-600 dark:text-gray-400 block mb-0.5">Trường thông tin cần lưu ý:</span>
            <ul class="list-disc list-inside space-y-0.5 text-gray-500 dark:text-gray-400">
              <li v-for="(msg, field) in item.feedback_fields" :key="field">
                <strong>{{ field }}:</strong> {{ msg }}
              </li>
            </ul>
          </div>
        </div>
      </li>
    </ol>
  </div>
</template>
