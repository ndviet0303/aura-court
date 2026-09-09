<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { socialApi } from '../api/socialApi'
import { errorMessage } from '@/core/utils/errorMessage'

const props = defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const loading = ref(false)
const reports = ref<any[]>([])
const selectedStatus = ref<'pending' | 'resolved' | 'all'>('pending')
const auditReason = ref('')
const processing = ref(false)
const notice = ref('')
const error = ref('')

async function loadReports() {
  loading.value = true
  error.value = ''
  try {
    const res = await socialApi.getFeedReports(selectedStatus.value)
    reports.value = res?.data || []
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể tải danh sách báo cáo vi phạm.')
  } finally {
    loading.value = false
  }
}

async function handleHide(report: any) {
  if (!auditReason.value.trim()) {
    error.value = 'Vui lòng nhập lý do kiểm duyệt để ẩn nội dung vi phạm.'
    return
  }
  processing.value = true
  error.value = ''
  try {
    const type = report.reportable_type?.includes('Comment') ? 'comment' : 'post'
    await socialApi.hideFeedItem(type, report.reportable_id, auditReason.value.trim())
    notice.value = 'Đã ẩn nội dung vi phạm và lưu vết kiểm duyệt thành công.'
    auditReason.value = ''
    await loadReports()
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể thực hiện ẩn nội dung.')
  } finally {
    processing.value = false
  }
}

async function handleRestore(report: any) {
  if (!auditReason.value.trim()) {
    error.value = 'Vui lòng nhập lý do kiểm duyệt để khôi phục nội dung.'
    return
  }
  processing.value = true
  error.value = ''
  try {
    const type = report.reportable_type?.includes('Comment') ? 'comment' : 'post'
    await socialApi.restoreFeedItem(type, report.reportable_id, auditReason.value.trim())
    notice.value = 'Đã khôi phục nội dung thành công.'
    auditReason.value = ''
    await loadReports()
  } catch (e: unknown) {
    error.value = errorMessage(e, 'Không thể khôi phục nội dung.')
  } finally {
    processing.value = false
  }
}

onMounted(() => {
  if (props.show) loadReports()
})
</script>

<template>
  <div v-if="show" class="moderation-modal-backdrop" @click="emit('close')">
    <div class="moderation-modal-card" @click.stop>
      <div class="modal-head">
        <div class="head-title">
          <span class="material-symbols-outlined head-ico">gavel</span>
          <h3>Kiểm Duyệt Báo Cáo Cộng Đồng</h3>
        </div>
        <button type="button" class="btn-close" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div v-if="notice" class="mod-alert success">{{ notice }}</div>
      <div v-if="error" class="mod-alert error">{{ error }}</div>

      <!-- Filter Controls -->
      <div class="mod-toolbar">
        <div class="status-filters">
          <button
            type="button"
            class="filter-pill"
            :class="{ active: selectedStatus === 'pending' }"
            @click="selectedStatus = 'pending'; loadReports()"
          >
            Chờ xử lý
          </button>
          <button
            type="button"
            class="filter-pill"
            :class="{ active: selectedStatus === 'resolved' }"
            @click="selectedStatus = 'resolved'; loadReports()"
          >
            Đã xử lý
          </button>
          <button
            type="button"
            class="filter-pill"
            :class="{ active: selectedStatus === 'all' }"
            @click="selectedStatus = 'all'; loadReports()"
          >
            Tất cả
          </button>
        </div>
      </div>

      <!-- Audit Reason Input -->
      <div class="audit-input-box">
        <label class="audit-label">Lý do kiểm duyệt (bắt buộc để ẩn / khôi phục):</label>
        <input
          v-model="auditReason"
          type="text"
          class="audit-text-input"
          placeholder="Ví dụ: Vi phạm điều khoản cộng đồng về ngôn từ / Đã đính chính thông tin..."
        />
      </div>

      <!-- Reports List -->
      <div v-if="loading" class="mod-loading">Đang tải báo cáo...</div>
      <div v-else-if="reports.length === 0" class="mod-empty">
        Không có báo cáo vi phạm nào trong danh mục này.
      </div>
      <div v-else class="reports-stream">
        <div v-for="r in reports" :key="r.id" class="report-card-item">
          <div class="report-meta-row">
            <span class="report-type-tag">
              {{ r.reportable_type?.includes('Comment') ? 'Bình luận' : 'Bài viết' }} #{{ r.reportable_id }}
            </span>
            <span class="report-status-badge" :class="r.status">
              {{ r.status === 'resolved' ? 'Đã xử lý' : 'Chờ xử lý' }}
            </span>
          </div>

          <div class="report-body-box">
            <div class="report-reason-txt">
              <strong>Lý do báo cáo:</strong> {{ r.reason }}
            </div>
            <div class="reporter-name">
              Người báo cáo: {{ r.reporter?.name || 'Ẩn danh' }}
            </div>
            <div v-if="r.reportable?.content" class="report-content-preview">
              "{{ r.reportable.content }}"
            </div>
          </div>

          <div class="report-action-buttons">
            <button
              type="button"
              class="btn-mod-hide"
              :disabled="processing"
              @click="handleHide(r)"
            >
              Ẩn nội dung
            </button>
            <button
              type="button"
              class="btn-mod-restore"
              :disabled="processing"
              @click="handleRestore(r)"
            >
              Khôi phục
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.moderation-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 16px;
}
.moderation-modal-card {
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 16px;
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  padding: 24px;
  gap: 16px;
  overflow: hidden;
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.head-title {
  display: flex;
  align-items: center;
  gap: 10px;
}
.head-ico {
  color: #f59e0b;
  font-size: 24px;
}
.modal-head h3 {
  margin: 0;
  color: #fff;
  font-size: 17px;
}
.btn-close {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}
.mod-alert {
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
}
.mod-alert.success {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}
.mod-alert.error {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}
.status-filters {
  display: flex;
  gap: 8px;
}
.filter-pill {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #94a3b8;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
}
.filter-pill.active {
  background: #3b82f6;
  color: #fff;
  border-color: #3b82f6;
}
.audit-input-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.audit-label {
  font-size: 12.5px;
  color: #cbd5e1;
  font-weight: 500;
}
.audit-text-input {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 8px;
  padding: 8px 12px;
  color: #fff;
  font-size: 13px;
}
.reports-stream {
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-right: 4px;
}
.report-card-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.report-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.report-type-tag {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}
.report-status-badge {
  font-size: 12px;
  font-weight: 600;
}
.report-status-badge.pending {
  color: #f59e0b;
}
.report-status-badge.resolved {
  color: #10b981;
}
.report-body-box {
  font-size: 13px;
  color: #cbd5e1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.report-content-preview {
  background: rgba(0, 0, 0, 0.25);
  padding: 8px 10px;
  border-radius: 6px;
  color: #94a3b8;
  font-style: italic;
}
.report-action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.btn-mod-hide {
  background: #ef4444;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.btn-mod-restore {
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.4);
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.mod-loading,
.mod-empty {
  text-align: center;
  padding: 30px;
  color: #94a3b8;
  font-size: 14px;
}
</style>
