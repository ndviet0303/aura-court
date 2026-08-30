<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiV2 } from '@/core/api/client'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

export interface PartnerRequest {
  id: number
  name: string
  level: string
  badge: string
  city: string
}

const partnerList = ref<PartnerRequest[]>([])
const loading = ref(false)

async function loadPartners() {
  loading.value = true
  try {
    const res = await apiV2.get<{ data: PartnerRequest[] }>('/social/partners')
    if (res?.data) {
      partnerList.value = res.data
    }
  } catch {
    partnerList.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadPartners()
})
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">🤝 Tìm Bạn Ghép Đôi / Đội Hình Đấu Giải</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="sub">Danh sách các tay vợt đang tìm partner cùng hạng đấu tại khu vực của bạn:</p>

        <div v-if="partnerList.length > 0" class="partners-list">
          <div v-for="p in partnerList" :key="p.id" class="partner-row">
            <div class="p-info">
              <strong>{{ p.name }}</strong>
              <span class="p-meta">{{ p.level }} · {{ p.badge }}</span>
              <span class="p-city">📍 {{ p.city }}</span>
            </div>

            <button class="btn-connect">
              💬 Nhắn Tin
            </button>
          </div>
        </div>

        <div v-else class="empty-partners">
          <span>Chưa có vận động viên nào đăng tin tìm bạn ghép cặp.</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sub {
  margin: 0 0 12px;
  font-size: 13px;
  color: var(--muted);
}

.empty-partners {
  padding: 24px;
  text-align: center;
  color: var(--muted);
  font-size: 14px;
}

.partners-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.partner-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  background: var(--surface-subtle);
  border-radius: 10px;
  border: 1px solid var(--border);
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.p-meta {
  font-size: 12px;
  color: var(--primary);
  font-weight: 600;
}

.p-city {
  font-size: 11px;
  color: var(--muted);
}

.btn-connect {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--primary);
  background: transparent;
  color: var(--primary);
  font-weight: 700;
  font-size: 12px;
  cursor: pointer;
}

.btn-connect:hover {
  background: var(--primary);
  color: #fff;
}
</style>
