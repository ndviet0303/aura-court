<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  venuePublicationApi,
  type OwnerReceivingConnection,
  type VenueReadinessChecklist,
} from '../../api/venuePublicationApi'
import OwnerReceivingConnectionPanel from './OwnerReceivingConnectionPanel.vue'

const props = defineProps<{
  venue: any
  organizationId: number
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submitted'): void
  (e: 'statusChanged'): void
}>()

const { t } = useI18n()
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const readinessData = ref<VenueReadinessChecklist | null>(null)
const receivingConnection = ref<OwnerReceivingConnection | null>(null)
const receivingConnectionError = ref<string | null>(null)
const receivingConnectionVerified = computed(
  () =>
    readinessData.value?.checklist.receiving_connection_selected ??
    (receivingConnection.value?.status === 'verified' && receivingConnection.value.is_selected),
)
const publicationReady = computed(() =>
  Boolean(readinessData.value?.is_ready && receivingConnectionVerified.value),
)
const missingRequirementCount = computed(
  () =>
    (readinessData.value?.missing_items.length ?? 0) + (receivingConnectionVerified.value ? 0 : 1),
)

async function loadReadiness() {
  if (!props.venue?.id || !props.organizationId) return
  loading.value = true
  error.value = null
  notice.value = null
  try {
    const res = await venuePublicationApi.getReadiness(props.organizationId, props.venue.id)
    readinessData.value = res.data
  } catch (err: any) {
    error.value = err.message || 'Không thể tải thông tin điều kiện xuất bản.'
  }
  try {
    const connection = await venuePublicationApi.getReceivingConnection(
      props.organizationId,
      props.venue.id,
    )
    receivingConnection.value = connection.data
    receivingConnectionError.value = null
  } catch (err: any) {
    receivingConnection.value = null
    receivingConnectionError.value = err.message || 'Không thể tải kết nối nhận tiền.'
  } finally {
    loading.value = false
  }
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      loadReadiness()
    }
  },
  { immediate: true },
)

async function handleSubmitPublication() {
  if (!props.venue?.id || !props.organizationId) return
  if (!publicationReady.value) {
    error.value = t('admin.venues.receivingConnection.notVerifiedMissing')
    return
  }
  submitting.value = true
  error.value = null
  try {
    const res = await venuePublicationApi.submitPublication(props.organizationId, props.venue.id)
    notice.value = res.message || 'Đã gửi yêu cầu xuất bản cơ sở thể thao.'
    emit('submitted')
    await loadReadiness()
  } catch (err: any) {
    error.value = err.message || 'Gửi yêu cầu xuất bản thất bại.'
  } finally {
    submitting.value = false
  }
}

async function handleReceivingConnectionUpdated(connection: OwnerReceivingConnection) {
  receivingConnection.value = connection
  receivingConnectionError.value = null
  if (readinessData.value && connection.is_selected) {
    const selected = connection.status === 'verified'
    const missing = readinessData.value.missing_items.filter(
      (item) => item.code !== 'RECEIVING_CONNECTION_REQUIRED',
    )
    if (!selected) {
      missing.push({
        code: 'RECEIVING_CONNECTION_REQUIRED',
        message: t('admin.venues.receivingConnection.notSelectedMissing'),
        field: 'receiving_connection',
      })
    }
    readinessData.value = {
      ...readinessData.value,
      is_ready: missing.length === 0,
      missing_items: missing,
      checklist: { ...readinessData.value.checklist, receiving_connection_selected: selected },
    }
  }
  emit('statusChanged')
}

async function handleToggleOperational(targetStatus: 'published' | 'temporarily_closed') {
  if (!props.venue?.id || !props.organizationId) return
  submitting.value = true
  error.value = null
  try {
    const res = await venuePublicationApi.setOperationalStatus(
      props.organizationId,
      props.venue.id,
      {
        status: targetStatus,
        reason: targetStatus === 'temporarily_closed' ? 'Tạm đóng bảo trì vận hành' : undefined,
      },
    )
    notice.value = res.message || 'Cập nhật trạng thái vận hành thành công.'
    emit('statusChanged')
    await loadReadiness()
  } catch (err: any) {
    error.value = err.message || 'Cập nhật trạng thái thất bại.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
  >
    <div
      class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl overflow-hidden border border-gray-100 dark:border-gray-700 flex flex-col max-h-[90vh]"
    >
      <!-- Header -->
      <div
        class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between"
      >
        <div>
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">
            Điều kiện & Xuất bản Cơ sở
          </h3>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ venue.name }} (Trạng thái: {{ venue.status || 'draft' }})
          </p>
        </div>
        <button
          type="button"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 overflow-y-auto space-y-5">
        <div
          v-if="notice"
          class="p-3 text-xs text-emerald-800 bg-emerald-100 rounded-lg dark:bg-emerald-950/30 dark:text-emerald-400"
        >
          {{ notice }}
        </div>
        <div
          v-if="error"
          class="p-3 text-xs text-red-800 bg-red-100 rounded-lg dark:bg-red-950/30 dark:text-red-400"
        >
          {{ error }}
        </div>

        <div v-if="loading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
        </div>

        <div v-else-if="readinessData" class="space-y-4">
          <!-- Readiness Overview -->
          <div
            class="p-4 rounded-lg flex items-center justify-between"
            :class="
              publicationReady
                ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'
                : 'bg-amber-50 text-amber-900 dark:bg-amber-950/30 dark:text-amber-300'
            "
          >
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined">
                {{ publicationReady ? 'check_circle' : 'warning' }}
              </span>
              <span class="text-sm font-semibold">
                {{
                  publicationReady
                    ? 'Đạt toàn bộ tiêu chuẩn xuất bản'
                    : 'Chưa đủ điều kiện xuất bản'
                }}
              </span>
            </div>
            <span class="text-xs"> {{ missingRequirementCount }} điều kiện còn thiếu </span>
          </div>

          <!-- Checklist Items -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div
              class="flex items-center gap-2 p-2.5 rounded border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <span
                class="material-symbols-outlined text-sm"
                :class="
                  readinessData.checklist.partner_active ? 'text-emerald-600' : 'text-red-500'
                "
              >
                {{ readinessData.checklist.partner_active ? 'check' : 'close' }}
              </span>
              <span>Đối tác đã phê duyệt</span>
            </div>

            <div
              class="flex items-center gap-2 p-2.5 rounded border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <span
                class="material-symbols-outlined text-sm"
                :class="
                  readinessData.checklist.venue_metadata ? 'text-emerald-600' : 'text-red-500'
                "
              >
                {{ readinessData.checklist.venue_metadata ? 'check' : 'close' }}
              </span>
              <span>Thông tin & Địa chỉ đầy đủ</span>
            </div>

            <div
              class="flex items-center gap-2 p-2.5 rounded border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <span
                class="material-symbols-outlined text-sm"
                :class="readinessData.checklist.active_courts ? 'text-emerald-600' : 'text-red-500'"
              >
                {{ readinessData.checklist.active_courts ? 'check' : 'close' }}
              </span>
              <span>Có ít nhất 1 sân hoạt động</span>
            </div>

            <div
              class="flex items-center gap-2 p-2.5 rounded border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <span
                class="material-symbols-outlined text-sm"
                :class="readinessData.checklist.court_pricing ? 'text-emerald-600' : 'text-red-500'"
              >
                {{ readinessData.checklist.court_pricing ? 'check' : 'close' }}
              </span>
              <span>Bảng giá sân xác định</span>
            </div>

            <div
              class="flex items-center gap-2 p-2.5 rounded border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <span
                class="material-symbols-outlined text-sm"
                :class="
                  readinessData.checklist.policies_configured ? 'text-emerald-600' : 'text-red-500'
                "
              >
                {{ readinessData.checklist.policies_configured ? 'check' : 'close' }}
              </span>
              <span>Chính sách đặt/hủy hợp lệ</span>
            </div>

            <div
              class="flex items-center gap-2 p-2.5 rounded border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <span
                class="material-symbols-outlined text-sm"
                :class="
                  readinessData.checklist.operating_calendar_configured
                    ? 'text-emerald-600'
                    : 'text-red-500'
                "
              >
                {{ readinessData.checklist.operating_calendar_configured ? 'check' : 'close' }}
              </span>
              <span>{{ $t('admin.venues.operatingCalendarConfigured') }}</span>
            </div>

            <div
              class="flex items-center gap-2 p-2.5 rounded border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <span
                class="material-symbols-outlined text-sm"
                :class="receivingConnectionVerified ? 'text-emerald-600' : 'text-red-500'"
              >
                {{ receivingConnectionVerified ? 'check' : 'close' }}
              </span>
              <span>{{ $t('admin.venues.receivingConnection.readiness') }}</span>
            </div>
          </div>

          <!-- Missing Details -->
          <div
            v-if="readinessData.missing_items.length || !receivingConnectionVerified"
            class="space-y-1.5 border-t pt-3 dark:border-gray-700"
          >
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300"
              >Nội dung cần khắc phục:</span
            >
            <ul class="text-xs space-y-1 text-red-600 dark:text-red-400">
              <li
                v-for="item in readinessData.missing_items"
                :key="item.code"
                class="flex items-center gap-1.5"
              >
                <span class="material-symbols-outlined text-xs">arrow_right</span>
                <span>{{ item.message }} ({{ item.code }})</span>
              </li>
              <li v-if="!receivingConnectionVerified" class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-xs">arrow_right</span>
                <span>{{
                  receivingConnection?.status === 'verified'
                    ? $t('admin.venues.receivingConnection.notSelectedMissing')
                    : $t('admin.venues.receivingConnection.notVerifiedMissing')
                }}</span>
              </li>
            </ul>
          </div>
        </div>

        <OwnerReceivingConnectionPanel
          v-if="venue?.id && organizationId"
          :organization-id="organizationId"
          :venue-id="venue.id"
          :connection="receivingConnection"
          :loading="loading"
          :load-error="receivingConnectionError"
          @updated="handleReceivingConnectionUpdated"
          @refresh="loadReadiness"
        />
      </div>

      <!-- Footer Actions -->
      <div
        class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-900/30"
      >
        <!-- Operational toggles for already published venues -->
        <div v-if="venue.status === 'published' || venue.status === 'temporarily_closed'">
          <button
            v-if="venue.status === 'published'"
            type="button"
            :disabled="submitting"
            class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-100 hover:bg-amber-200 rounded-lg dark:bg-amber-950/40 dark:text-amber-300"
            @click="handleToggleOperational('temporarily_closed')"
          >
            Tạm dừng nhận khách
          </button>
          <button
            v-else
            type="button"
            :disabled="submitting"
            class="px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-100 hover:bg-emerald-200 rounded-lg dark:bg-emerald-950/40 dark:text-emerald-300"
            @click="handleToggleOperational('published')"
          >
            Mở lại hoạt động
          </button>
        </div>
        <div v-else></div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
            @click="emit('close')"
          >
            Đóng
          </button>

          <button
            v-if="venue.status === 'draft' || venue.status === 'revision_required'"
            type="button"
            :disabled="submitting || !publicationReady"
            class="px-5 py-2 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg disabled:opacity-50"
            @click="handleSubmitPublication"
          >
            {{ submitting ? 'Đang gửi...' : 'Gửi yêu cầu xuất bản' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
