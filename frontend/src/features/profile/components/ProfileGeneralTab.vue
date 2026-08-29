<script setup lang="ts">
defineProps<{
  name: string
  phone: string
  playLevel: string
}>()

const emit = defineEmits<{
  (e: 'update:name', val: string): void
  (e: 'update:phone', val: string): void
  (e: 'update:playLevel', val: string): void
  (e: 'save'): void
}>()
</script>

<template>
  <div class="profile-tab-card">
    <h3 class="tab-title">👤 Thông Tin Cá Nhân & Trình Độ</h3>

    <form class="profile-form" @submit.prevent="emit('save')">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Họ và tên hiển thị:</label>
          <input
            :value="name"
            type="text"
            class="form-input"
            @input="emit('update:name', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Số điện thoại liên hệ:</label>
          <input
            :value="phone"
            type="tel"
            class="form-input"
            @input="emit('update:phone', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <div class="form-group full-width">
          <label class="form-label">Trình độ đánh cầu lông:</label>
          <select
            :value="playLevel"
            class="form-input"
            @change="emit('update:playLevel', ($event.target as HTMLSelectElement).value)"
          >
            <option value="beginner">Mới chơi / Phong trào cơ bản (Beginner)</option>
            <option value="intermediate">Trung bình / Đánh bền 2-3 năm (Intermediate)</option>
            <option value="advanced">Bán chuyên / Cọ sát giải (Advanced)</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save">
          💾 Lưu Thay Đổi
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.tab-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

.full-width {
  grid-column: 1 / -1;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}

.btn-save {
  padding: 10px 24px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
