<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePartnerRegistration } from '../composables/usePartnerRegistration'
import ApplicationHistoryTimeline from '../components/ApplicationHistoryTimeline.vue'
import { useAuthStore } from '@/features/auth/stores/authStore'

const router = useRouter()
const auth = useAuthStore()
const {
  application,
  history,
  loading,
  submitting,
  error,
  fetchCurrent,
  fetchHistory,
  isPendingReview,
  isSupplementationRequested,
  isApproved,
  isRejected,
} = usePartnerRegistration()

async function loadData() {
  const current = await fetchCurrent()
  if (!current || current.status === 'draft') {
    router.push('/partner-registration')
    return
  }
  await fetchHistory()
}

onMounted(loadData)
async function handleGoToDashboard() {
  try {
    await auth.load(true)
  } catch {
    // Ignore load error
  }
  router.push('/admin/organization')
}

</script>

<template>
  <div class="partner-status-view py-8 px-4 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
          Trạng thái Hồ sơ Đối tác
        </h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
          Theo dõi tiến trình thẩm định và kết quả xét duyệt hồ sơ đối tác.
        </p>
      </div>

      <button
        type="button"
        :disabled="loading"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
        @click="loadData"
      >
        <span class="material-symbols-outlined text-sm" :class="{ 'animate-spin': loading }">refresh</span>
        Làm mới
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="p-3 text-xs text-red-700 bg-red-100 rounded-lg dark:bg-red-900/30 dark:text-red-400">
      {{ error }}
    </div>

    <!-- Application Status Box -->
    <div v-if="application" class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700 space-y-6">
      <div class="flex items-center justify-between border-b pb-4 dark:border-gray-700">
        <div>
          <span class="text-xs font-semibold text-gray-400 uppercase">Hồ sơ #{{ application.id }}</span>
          <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
            {{ application.business_name || 'Hồ sơ đăng ký đối tác' }}
          </h2>
        </div>
        <span
          class="px-3 py-1 text-xs font-semibold rounded-full"
          :class="{
            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': isPendingReview,
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': isSupplementationRequested,
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': isApproved,
            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': isRejected,
          }"
        >
          {{ isPendingReview ? 'Chờ thẩm định' : isSupplementationRequested ? 'Cần bổ sung' : isApproved ? 'Đã duyệt' : 'Từ chối' }}
        </span>
      </div>

      <!-- State: Pending Review (Pending edit prevention notice) -->
      <div v-if="isPendingReview" class="p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg text-sm text-blue-900 dark:text-blue-300 space-y-2">
        <p class="font-semibold flex items-center gap-1.5">
          <span class="material-symbols-outlined text-base">lock</span>
          Hồ sơ đang trong quá trình thẩm định
        </p>
        <p class="text-xs">
          Hồ sơ của bạn đã được khóa để chuyên viên xem xét. Bạn sẽ không thể chỉnh sửa thông tin cho đến khi có phản hồi chính thức.
        </p>
      </div>

      <!-- State: Supplementation Requested -->
      <div v-else-if="isSupplementationRequested" class="space-y-4">
        <div class="p-4 bg-amber-50 dark:bg-amber-950/20 rounded-lg text-sm text-amber-900 dark:text-amber-300">
          <p class="font-semibold">Chuyên viên yêu cầu bổ sung thông tin:</p>
          <p class="text-xs mt-1">
            {{ history[0]?.reason || 'Vui lòng bổ sung hoặc làm rõ thông tin theo yêu cầu.' }}
          </p>
        </div>

        <div class="space-y-3 border-t pt-4 dark:border-gray-700">
          <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Nội dung giải trình / bổ sung hồ sơ:
          </label>
          <button
            type="button"
            :disabled="submitting"
            class="px-5 py-2 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg disabled:opacity-50"
            @click="router.push('/partner-registration')"
          >
            Chỉnh sửa và nộp lại hồ sơ
          </button>
        </div>
      </div>

      <!-- State: Approved -->
      <div v-else-if="isApproved" class="space-y-4">
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/20 rounded-lg text-sm text-emerald-900 dark:text-emerald-300">
          <p class="font-semibold">Hồ sơ đã được phê duyệt thành công!</p>
          <p class="text-xs mt-1">
            Bạn đã được cấp quyền quản trị câu lạc bộ. Vui lòng tiếp tục vào khu vực quản lý.
          </p>
        </div>
        <button
          type="button"
          class="px-5 py-2 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg"
          @click="handleGoToDashboard"
        >
          Truy cập Quản lý Câu lạc bộ
        </button>
      </div>

      <!-- State: Rejected -->
      <div v-else-if="isRejected" class="space-y-4">
        <div class="p-4 bg-red-50 dark:bg-red-950/20 rounded-lg text-sm text-red-900 dark:text-red-300">
          <p class="font-semibold">Hồ sơ không được phê duyệt.</p>
          <p class="text-xs mt-1">Lý do: {{ history[0]?.reason || 'Chưa đáp ứng tiêu chuẩn.' }}</p>
        </div>
        <button
          type="button"
          class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg"
          @click="router.push('/partner-registration')"
        >
          Bắt đầu hồ sơ mới
        </button>
      </div>

      <!-- Review History Timeline -->
      <div class="border-t pt-6 dark:border-gray-700">
        <ApplicationHistoryTimeline :history="history" :created-date="application.created_at" />
      </div>
    </div>
  </div>
</template>
