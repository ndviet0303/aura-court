<script setup lang="ts">
import { ref } from 'vue'
import { AuraButton } from '@/shared/components'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', name: string, phone: string): void
}>()

const name = ref('')
const phone = ref('')

function handleSubmit() {
  if (!name.value.trim()) return
  emit('submit', name.value.trim(), phone.value.trim())
  name.value = ''
  phone.value = ''
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="header-title-wrap">
          <span class="material-symbols-outlined header-icon">person_add</span>
          <h3 class="modal-title">Thêm Thành Viên Vào Hóa Đơn</h3>
        </div>
        <button class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <form class="modal-body" @submit.prevent="handleSubmit">
        <div class="form-group">
          <label class="form-label">Tên hoặc biệt danh bạn chơi (*):</label>
          <input
            v-model="name"
            type="text"
            required
            placeholder="Ví dụ: Hoàng Sơn, Minh Trang..."
            class="form-input"
            autofocus
          />
        </div>

        <div class="form-group">
          <label class="form-label">Số điện thoại (tùy chọn):</label>
          <input
            v-model="phone"
            type="tel"
            placeholder="09xx xxx xxx"
            class="form-input font-mono"
          />
        </div>

        <div class="modal-footer">
          <AuraButton variant="outline" size="md" type="button" @click="emit('close')">
            <span>Hủy</span>
          </AuraButton>
          <AuraButton
            variant="primary"
            size="md"
            type="submit"
            icon="add"
            :disabled="!name.trim()"
          >
            <span>Thêm Vào Bill</span>
          </AuraButton>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 420px;
  background: var(--surface, #1e191c);
  border-radius: 18px;
  border: 1px solid var(--border, var(--outline, #382f35));
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}

.header-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon {
  color: var(--primary, #E58E9F);
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
  color: var(--on-surface, #FAF7F3);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted, #9e939a);
  cursor: pointer;
}

.modal-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--on-surface, #FAF7F3);
}

.form-input {
  padding: 9px 12px;
  border-radius: 10px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface-container, rgba(0, 0, 0, 0.2));
  color: var(--on-surface, #FAF7F3);
  font-size: 13.5px;
  outline: none;
  transition: border-color 0.15s;
}

.form-input:focus {
  border-color: var(--primary, #E58E9F);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}
</style>
