<script setup lang="ts">
defineProps<{
  format: 'doubles' | 'singles'
  skillLevel: 'all' | 'intermediate' | 'advanced'
  splitCost: boolean
  costPerPerson: number
  maxPlayers: number
}>()

const emit = defineEmits<{
  (e: 'update:format', fmt: 'doubles' | 'singles'): void
  (e: 'update:skillLevel', level: 'all' | 'intermediate' | 'advanced'): void
  (e: 'update:splitCost', split: boolean): void
  (e: 'update:costPerPerson', cost: number): void
  (e: 'update:maxPlayers', max: number): void
}>()
</script>

<template>
  <div class="match-settings-card">
    <div class="card-header">
      <h3 class="title">⚙️ Thông Số Trận Đấu</h3>
      <p class="sub">Thiết lập trình độ mong muốn, hình thức đánh và chi phí chia tiền.</p>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Hình thức thi đấu:</label>
        <select
          :value="format"
          class="form-input"
          @change="emit('update:format', ($event.target as HTMLSelectElement).value as any)"
        >
          <option value="doubles">Đánh Đôi (4 người)</option>
          <option value="singles">Đánh Đơn (2 người)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Yêu cầu trình độ (Skill Level):</label>
        <select
          :value="skillLevel"
          class="form-input"
          @change="emit('update:skillLevel', ($event.target as HTMLSelectElement).value as any)"
        >
          <option value="all">Mọi trình độ (Giao lưu vui vẻ)</option>
          <option value="intermediate">Trung bình / Phong trào cứng</option>
          <option value="advanced">Bán chuyên / Nâng cao</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Chi phí / người (₫):</label>
        <input
          :value="costPerPerson"
          type="number"
          step="10000"
          class="form-input"
          @input="emit('update:costPerPerson', Number(($event.target as HTMLInputElement).value))"
        />
      </div>

      <div class="form-group">
        <label class="form-label">Giới hạn số người tham gia:</label>
        <input
          :value="maxPlayers"
          type="number"
          min="2"
          max="12"
          class="form-input"
          @input="emit('update:maxPlayers', Number(($event.target as HTMLInputElement).value))"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.match-settings-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

@media (max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
