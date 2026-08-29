<script setup lang="ts">
defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'applyPreset', name: string): void
}>()

const presets = [
  {
    name: 'Sân Trung Tâm Đô Thị (Sài Gòn / Hà Nội)',
    desc: 'Tối ưu hóa doanh thu giờ cao điểm 17:00 - 21:00 và phụ thu cuối tuần +20%.',
    badge: 'Phổ biến nhất',
  },
  {
    name: 'Sân Sinh Viên / Phong Trào Huyện',
    desc: 'Giá phẳng cả tuần, ưu đãi giờ trưa giảm 30% cho học sinh sinh viên.',
    badge: 'Bình dân',
  },
]
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">📋 Mẫu Cấu Hình Ma Trận Giá Tiêu Chuẩn</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="presets-list">
          <div v-for="p in presets" :key="p.name" class="preset-card">
            <div class="p-info">
              <span class="p-badge">{{ p.badge }}</span>
              <strong class="p-title">{{ p.name }}</strong>
              <p class="p-desc">{{ p.desc }}</p>
            </div>
            <button class="btn-apply-preset" @click="emit('applyPreset', p.name)">
              Áp Dụng
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
.presets-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preset-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  gap: 12px;
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.p-badge {
  font-size: 10px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 2px 6px;
  border-radius: 4px;
  width: fit-content;
}

.p-title {
  font-size: 14px;
}

.p-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.btn-apply-preset {
  padding: 8px 14px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
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
