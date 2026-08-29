<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const partnerList = ref([
  { id: 1, name: 'Lê Minh Quân', level: 'Level 4.0 Pro', badge: 'Chuyên Đôi Nam', city: 'Quận 1, TP.HCM' },
  { id: 2, name: 'Nguyễn Bích Ngọc', level: 'Level 3.5 Khá', badge: 'Chuyên Đôi Nam Nữ', city: 'Bình Thạnh, TP.HCM' },
  { id: 3, name: 'Trần Văn Đức', level: 'Level 4.5 Master', badge: 'Cựu VĐV Trẻ', city: 'Thủ Đức, TP.HCM' },
])
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

        <div class="partners-list">
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
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.p-meta {
  font-size: 11px;
  color: var(--pink-dark, #db2777);
  font-weight: 600;
}

.p-city {
  font-size: 11px;
  color: var(--muted);
}

.btn-connect {
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
