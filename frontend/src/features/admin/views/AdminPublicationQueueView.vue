<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  venuePublicationApi,
  type VenuePublicationRevision,
} from '../api/venuePublicationApi'

const queue = ref<VenuePublicationRevision[]>([])
const selectedRevision = ref<VenuePublicationRevision | null>(null)
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const decisionReason = ref('')

async function fetchQueue() {
  loading.value = true
  error.value = null
  try {
    const res = await venuePublicationApi.getPublicationQueue()
    queue.value = res.data || []
    if (queue.value.length > 0 && !selectedRevision.value) {
      selectedRevision.value = queue.value[0] || null
    }
  } catch (err: any) {
    error.value = err.message || 'Không thể tải hàng đợi xét duyệt xuất bản.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchQueue)

async function handleDecision(decision: 'approve' | 'revision_required' | 'reject') {
  if (!selectedRevision.value) return
  if ((decision === 'revision_required' || decision === 'reject') && !decisionReason.value.trim()) {
    error.value = 'Vui lòng cung cấp lý do cho quyết định này.'
    return
  }

  submitting.value = true
  error.value = null
  try {
    const res = await venuePublicationApi.reviewPublicationRevision(
      selectedRevision.value.id,
      {
        decision,
        reason: decisionReason.value.trim() || undefined,
      }
    )
    notice.value = res.message || 'Xử lý xét duyệt thành công.'
    decisionReason.value = ''
    selectedRevision.value = null
    await fetchQueue()
  } catch (err: any) {
    error.value = err.message || 'Xử lý xét duyệt thất bại.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="admin-publication-queue-view py-6 px-4 max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
          Hàng đợi Phê duyệt Xuất bản Cơ sở
        </h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
          Thẩm định độc lập trước khi mở bán công khai cơ sở thể thao trên Aura Marketplace.
        </p>
      </div>

      <button
        type="button"
        :disabled="loading"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50"
        @click="fetchQueue"
      >
        <span class="material-symbols-outlined text-sm" :class="{ 'animate-spin': loading }">refresh</span>
        Làm mới
      </button>
    </div>

    <div v-if="notice" class="p-3 text-xs text-emerald-800 bg-emerald-100 rounded-lg dark:bg-emerald-950/30 dark:text-emerald-400">
      {{ notice }}
    </div>
    <div v-if="error" class="p-3 text-xs text-red-800 bg-red-100 rounded-lg dark:bg-red-950/30 dark:text-red-400">
      {{ error }}
    </div>

    <!-- Main Layout: Queue List (Left) + Detail & Decisions (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Queue List -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
          <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
            Yêu cầu chờ duyệt ({{ queue.length }})
          </span>
        </div>

        <div v-if="loading && !queue.length" class="p-8 text-center">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-emerald-600 mx-auto"></div>
        </div>
        <div v-else-if="!queue.length" class="p-8 text-center text-xs text-gray-500 italic">
          Không có yêu cầu xuất bản nào đang chờ duyệt.
        </div>

        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[600px] overflow-y-auto">
          <li
            v-for="rev in queue"
            :key="rev.id"
            class="p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            :class="{ 'bg-emerald-50/50 dark:bg-emerald-950/20 border-l-4 border-emerald-600': selectedRevision?.id === rev.id }"
            @click="selectedRevision = rev"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-bold text-gray-900 dark:text-white truncate">
                {{ rev.configuration_snapshot?.venue?.name || `Cơ sở #${rev.venue_id}` }}
              </span>
              <span class="text-[11px] text-emerald-600 font-medium">Rev {{ rev.revision_number }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
              {{ rev.configuration_snapshot?.venue?.address || 'Chưa có địa chỉ' }}
            </p>
          </li>
        </ul>
      </div>

      <!-- Revision Detail & Actions Workbench -->
      <div class="lg:col-span-2 space-y-6">
        <div v-if="!selectedRevision" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-8 text-center text-xs text-gray-500 italic">
          Chọn một yêu cầu trong danh sách để kiểm tra cấu hình.
        </div>

        <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-100 dark:border-gray-700 p-6 space-y-6">
          <div class="border-b pb-4 dark:border-gray-700">
            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase">
              Bản cấu hình xét duyệt #{{ selectedRevision.id }}
            </span>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-1">
              {{ selectedRevision.configuration_snapshot?.venue?.name }}
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              {{ selectedRevision.configuration_snapshot?.venue?.address }}
            </p>
          </div>

          <!-- Courts Snapshot -->
          <div>
            <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
              Danh sách sân & Bảng giá ({{ selectedRevision.configuration_snapshot?.courts?.length || 0 }} sân)
            </h3>
            <div class="space-y-2 max-h-48 overflow-y-auto">
              <div
                v-for="court in selectedRevision.configuration_snapshot?.courts"
                :key="court.id"
                class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded border border-gray-100 dark:border-gray-700 text-xs flex items-center justify-between"
              >
                <span class="font-medium text-gray-900 dark:text-white">{{ court.name }}</span>
                <span class="text-gray-500">{{ court.price_slots?.length || 0 }} khung giờ giá</span>
              </div>
            </div>
          </div>

          <!-- Review Controls -->
          <div class="border-t pt-4 dark:border-gray-700 space-y-4">
            <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
              Quyết định Thẩm định
            </h3>

            <div>
              <label for="decisionReason" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">
                Lý do / Yêu cầu chỉnh sửa (bắt buộc khi yêu cầu sửa hoặc từ chối):
              </label>
              <textarea
                id="decisionReason"
                v-model="decisionReason"
                rows="2"
                class="w-full text-xs p-2.5 border rounded-lg bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700"
                placeholder="Nhập ghi chú cho chủ cơ sở..."
              ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" :disabled="submitting" class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg dark:bg-red-950/40 dark:text-red-400" @click="handleDecision('reject')">Từ chối</button>
              <button type="button" :disabled="submitting" class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-100 hover:bg-amber-200 rounded-lg dark:bg-amber-950/40 dark:text-amber-400" @click="handleDecision('revision_required')">Yêu cầu chỉnh sửa</button>
              <button type="button" :disabled="submitting" class="px-5 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg" @click="handleDecision('approve')">{{ submitting ? 'Đang duyệt...' : 'Phê duyệt xuất bản' }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
