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
  <div class="partner-page-wrapper">
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <div class="partner-container">
      <div class="partner-card status-card">
        <div class="status-top-bar">
          <div>
            <div class="partner-chip">
              <span class="material-symbols-outlined icon-16">shield_person</span>
              <span>TIẾN TRÌNH THẨM ĐỊNH</span>
            </div>
            <h1 class="status-main-title">Trạng thái Hồ sơ Đối tác</h1>
            <p class="status-main-sub">
              Theo dõi tiến trình thẩm định và kết quả xét duyệt hồ sơ đối tác.
            </p>
          </div>

          <button type="button" :disabled="loading" class="btn-refresh-status" @click="loadData">
            <span class="material-symbols-outlined icon-16" :class="{ spin: loading }"
              >refresh</span
            >
            <span>Làm mới</span>
          </button>
        </div>

        <!-- Error Alert -->
        <div v-if="error" class="error-banner" role="alert">
          <span class="material-symbols-outlined icon-16">error</span>
          <span>{{ error }}</span>
        </div>

        <!-- Application Status Box -->
        <div v-if="application" class="application-details-box">
          <div class="app-summary-header">
            <div>
              <span class="app-id-tag">Hồ sơ #{{ application.id }}</span>
              <h2 class="app-business-name">
                {{ application.business_name || 'Hồ sơ đăng ký đối tác' }}
              </h2>
            </div>
            <span
              class="status-pill"
              :class="{
                'status-pending': isPendingReview,
                'status-supplement': isSupplementationRequested,
                'status-approved': isApproved,
                'status-rejected': isRejected,
              }"
            >
              {{
                isPendingReview
                  ? 'Chờ thẩm định'
                  : isSupplementationRequested
                    ? 'Cần bổ sung'
                    : isApproved
                      ? 'Đã duyệt'
                      : 'Từ chối'
              }}
            </span>
          </div>

          <!-- State: Pending Review (Pending edit prevention notice) -->
          <div v-if="isPendingReview" class="notice-box notice-pending">
            <p class="notice-title">
              <span class="material-symbols-outlined icon-18">lock</span>
              <span>Hồ sơ đang trong quá trình thẩm định</span>
            </p>
            <p class="notice-desc">
              Hồ sơ của bạn đã được khóa để chuyên viên xem xét. Bạn sẽ không thể chỉnh sửa thông
              tin cho đến khi có phản hồi chính thức.
            </p>
          </div>

          <!-- State: Supplementation Requested -->
          <div v-else-if="isSupplementationRequested" class="action-state-box">
            <div class="notice-box notice-warning">
              <p class="notice-title">
                <span class="material-symbols-outlined icon-18">warning</span>
                <span>Chuyên viên yêu cầu bổ sung thông tin:</span>
              </p>
              <p class="notice-desc">
                {{ history[0]?.reason || 'Vui lòng bổ sung hoặc làm rõ thông tin theo yêu cầu.' }}
              </p>
            </div>

            <div class="action-row">
              <button
                type="button"
                :disabled="submitting"
                class="btn-action-primary bg-amber bg-amber-600"
                @click="router.push('/partner-registration')"
              >
                <span class="material-symbols-outlined icon-16">edit</span>
                <span>Chỉnh sửa và nộp lại hồ sơ</span>
              </button>
            </div>
          </div>

          <!-- State: Approved -->
          <div v-else-if="isApproved" class="action-state-box">
            <div class="notice-box notice-success">
              <p class="notice-title">
                <span class="material-symbols-outlined icon-18">verified</span>
                <span>Hồ sơ đã được phê duyệt thành công!</span>
              </p>
              <p class="notice-desc">
                Bạn đã được cấp quyền quản trị câu lạc bộ. Vui lòng tiếp tục vào khu vực quản lý.
              </p>
            </div>

            <div class="action-row">
              <button
                type="button"
                class="btn-action-primary bg-green bg-emerald-600"
                @click="handleGoToDashboard"
              >
                <span class="material-symbols-outlined icon-16">dashboard</span>
                <span>Truy cập Quản lý Câu lạc bộ</span>
              </button>
            </div>
          </div>

          <!-- State: Rejected -->
          <div v-else-if="isRejected" class="action-state-box">
            <div class="notice-box notice-danger">
              <p class="notice-title">
                <span class="material-symbols-outlined icon-18">cancel</span>
                <span>Hồ sơ không được phê duyệt.</span>
              </p>
              <p class="notice-desc">
                Lý do: {{ history[0]?.reason || 'Chưa đáp ứng tiêu chuẩn.' }}
              </p>
            </div>

            <div class="action-row">
              <button
                type="button"
                class="btn-action-secondary"
                @click="router.push('/partner-registration')"
              >
                <span class="material-symbols-outlined icon-16">add</span>
                <span>Bắt đầu hồ sơ mới</span>
              </button>
            </div>
          </div>

          <!-- Review History Timeline -->
          <div class="timeline-wrapper">
            <ApplicationHistoryTimeline :history="history" :created-date="application.created_at" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style src="../styles/partner-registration.css"></style>
