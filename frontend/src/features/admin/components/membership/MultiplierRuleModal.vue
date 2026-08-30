<script setup lang="ts">
import { ref, watch } from 'vue'
import type { MultiplierRule } from '../../types/membership'

const props = defineProps<{
  show: boolean
  rule: MultiplierRule | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: Partial<MultiplierRule>): void
}>()

const formName = ref('')
const formMultiplier = ref(1.5)
const formTag = ref('Off-peak')
const formSchedule = ref('')
const formDesc = ref('')
const formIsActive = ref(true)

watch(
  () => props.rule,
  (val) => {
    if (val) {
      formName.value = val.name
      formMultiplier.value = val.multiplier
      formTag.value = val.tag
      formSchedule.value = val.schedule
      formDesc.value = val.description
      formIsActive.value = val.isActive
    } else {
      formName.value = ''
      formMultiplier.value = 1.5
      formTag.value = 'Event'
      formSchedule.value = 'T2-T6, 08:00 - 15:00'
      formDesc.value = ''
      formIsActive.value = true
    }
  },
  { immediate: true },
)

function onSubmit() {
  if (!formName.value.trim()) return

  emit('save', {
    name: formName.value.trim(),
    multiplier: Number(formMultiplier.value),
    tag: formTag.value,
    schedule: formSchedule.value.trim(),
    description: formDesc.value.trim() || formSchedule.value.trim(),
    isActive: formIsActive.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-box" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="header-title-block">
          <span class="material-symbols-outlined header-icon">
            {{ rule ? 'edit' : 'auto_fix_high' }}
          </span>
          <div>
            <h3 class="modal-title">{{ rule ? 'Chỉnh sửa Quy tắc Nhân điểm' : 'Thêm Quy tắc Nhân điểm' }}</h3>
            <p class="modal-subtitle">Cấu hình sự kiện và khung giờ áp dụng hệ số nhân điểm thưởng.</p>
          </div>
        </div>
        <button type="button" class="btn-close" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <form class="modal-body" @submit.prevent="onSubmit">
        <div class="form-group">
          <label class="form-label">Tên quy tắc *</label>
          <input
            v-model="formName"
            type="text"
            class="form-input"
            placeholder="VD: Giờ Vàng (Off-peak), Sinh nhật hội viên"
            required
          />
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Hệ số nhân (x) *</label>
            <input
              v-model.number="formMultiplier"
              type="number"
              step="0.1"
              min="1"
              max="10"
              class="form-input"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label">Phân loại (Tag)</label>
            <select v-model="formTag" class="form-select">
              <option value="Off-peak">Giờ thấp điểm (Off-peak)</option>
              <option value="Birthday">Sinh nhật (Birthday)</option>
              <option value="Tournament">Giải đấu (Tournament)</option>
              <option value="Weekend">Cuối tuần (Weekend)</option>
              <option value="Special">Đặc biệt (Special)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Thời gian / Điều kiện áp dụng *</label>
          <input
            v-model="formSchedule"
            type="text"
            class="form-input"
            placeholder="VD: T2-T6, 08:00 - 15:00 hoặc Trong tháng sinh nhật"
            required
          />
        </div>

        <div class="form-group">
          <label class="form-label">Mô tả bổ sung</label>
          <input
            v-model="formDesc"
            type="text"
            class="form-input"
            placeholder="Ghi chú chi tiết cho lễ tân hoặc người dùng app..."
          />
        </div>

        <label class="checkbox-label">
          <input v-model="formIsActive" type="checkbox" />
          <span>Kích hoạt quy tắc này ngay lập tức</span>
        </label>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" @click="emit('close')">Hủy</button>
          <button type="submit" class="btn-primary">
            {{ rule ? 'Lưu Quy Tắc' : 'Thêm Quy Tắc' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-box {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  border: 1px solid var(--outline-variant, #e1e3e4);
}

:global(html.dark) .modal-box {
  background: #1e2025;
  border-color: #353942;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--surface-container, #edeeef);
}

:global(html.dark) .modal-header {
  border-color: #353942;
}

.header-title-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  font-size: 26px;
  color: var(--primary, #78555e);
}

:global(html.dark) .header-icon {
  color: #f8b4c4;
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .modal-title {
  color: #ffffff;
}

.modal-subtitle {
  margin: 2px 0 0 0;
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.btn-close {
  border: none;
  background: transparent;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .form-label {
  color: #e2e8f0;
}

.form-input,
.form-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--on-surface, #191c1d);
  font-size: 14px;
  outline: none;
}

:global(html.dark) .form-input,
:global(html.dark) .form-select {
  background: #262930;
  border-color: #454747;
  color: #ffffff;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 4px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 10px;
  padding-top: 16px;
  border-top: 1px solid var(--surface-container, #edeeef);
}

:global(html.dark) .modal-footer {
  border-color: #353942;
}

.btn-secondary {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  padding: 10px 20px;
  border-radius: 10px;
  border: none;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
</style>
