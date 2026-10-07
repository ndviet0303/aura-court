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
  <div class="application-history-timeline">
    <h4 class="timeline-title">
      <span class="material-symbols-outlined icon-18">history</span>
      <span>Lịch sử Thẩm định & Cập nhật</span>
    </h4>

    <div v-if="!history.length" class="empty-timeline-text">
      Chưa có ghi nhận thẩm định nào từ đội ngũ vận hành.
    </div>

    <ol v-else class="timeline-list">
      <li v-for="item in history" :key="item.id" class="timeline-item">
        <div
          class="timeline-dot"
          :class="{
            'dot-approve': item.decision === 'approve',
            'dot-supplement': item.decision === 'revision_required' || item.decision === 'supplement',
            'dot-reject': item.decision === 'reject',
          }"
        ></div>

        <div class="timeline-card">
          <div class="timeline-card-header">
            <span
              class="decision-badge"
              :class="{
                'badge-approve': item.decision === 'approve',
                'badge-supplement': item.decision === 'revision_required' || item.decision === 'supplement',
                'badge-reject': item.decision === 'reject',
              }"
            >
              {{ item.decision === 'approve' ? 'Phê duyệt' : item.decision === 'revision_required' || item.decision === 'supplement' ? 'Yêu cầu bổ sung' : 'Từ chối' }}
            </span>
            <time class="timeline-time">{{ formatDate(item.created_at) }}</time>
          </div>

          <p v-if="item.reason" class="timeline-reason">
            {{ item.reason }}
          </p>

          <div v-if="item.feedback_fields && Object.keys(item.feedback_fields).length > 0" class="timeline-feedback-box">
            <span class="feedback-heading">Trường thông tin cần lưu ý:</span>
            <ul class="feedback-fields-list">
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

<style src="../styles/partner-registration.css"></style>
