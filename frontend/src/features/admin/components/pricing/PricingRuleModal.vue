<script setup lang="ts">
import { ref, watch } from 'vue'
import type { PriceRuleItem } from '../../composables/usePricingCalendarV2'

const props = defineProps<{
  show: boolean
  rule: PriceRuleItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: any): void
}>()

const name = ref('')
const type = ref<'holiday' | 'peak' | 'special_event' | 'bad_weather' | 'surge'>('peak')
const priority = ref(2)
const adjustmentType = ref<'fixed' | 'percentage_add' | 'percentage_discount'>('fixed')
const adjustmentValue = ref(180000)
const startsAt = ref('17:00')
const endsAt = ref('21:00')

watch(
  () => props.rule,
  (r) => {
    if (r) {
      name.value = r.name
      type.value = r.type
      priority.value = r.priority
      adjustmentType.value = r.adjustment_type
      adjustmentValue.value = r.adjustment_value
      startsAt.value = r.starts_at || '17:00'
      endsAt.value = r.ends_at || '21:00'
    } else {
      name.value = ''
      type.value = 'peak'
      priority.value = 2
      adjustmentType.value = 'fixed'
      adjustmentValue.value = 180000
    }
  },
  { immediate: true }
)

function handleSave() {
  if (!name.value.trim()) return
  emit('save', {
    name: name.value.trim(),
    type: type.value,
    priority: priority.value,
    adjustment_type: adjustmentType.value,
    adjustment_value: adjustmentValue.value,
    starts_at: startsAt.value,
    ends_at: endsAt.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">
          {{ rule ? '✏️ Chỉnh Sửa Quy Tắc Giá' : '➕ Thêm Quy Tắc Giá Linh Hoạt Mới' }}
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="rule-form-grid">
          <div class="form-group">
            <label class="form-label">Tên quy tắc:</label>
            <input v-model="name" type="text" class="form-input" placeholder="Vd: Giờ Vàng Buổi Tối..." />
          </div>

          <div class="form-group">
            <label class="form-label">Loại quy tắc:</label>
            <select v-model="type" class="form-input">
              <option value="peak">Giờ cao điểm (Peak)</option>
              <option value="holiday">Ngày lễ / Tết (Holiday)</option>
              <option value="surge">Khuyến mãi / Giảm giá (Surge/Discount)</option>
              <option value="special_event">Sự kiện đặc biệt</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Độ ưu tiên (1 = Cao nhất):</label>
            <input v-model.number="priority" type="number" min="1" max="10" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Loại điều chỉnh giá:</label>
            <select v-model="adjustmentType" class="form-input">
              <option value="fixed">Số tiền cố định (₫)</option>
              <option value="percentage_add">Cộng thêm phần trăm (%)</option>
              <option value="percentage_discount">Giảm giá phần trăm (%)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Giá trị điều chỉnh:</label>
            <input v-model.number="adjustmentValue" type="number" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Khung giờ áp dụng:</label>
            <div class="time-range-row">
              <input v-model="startsAt" type="time" class="form-input" />
              <span>➔</span>
              <input v-model="endsAt" type="time" class="form-input" />
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" :disabled="!name.trim()" @click="handleSave">
          Lưu Quy Tắc
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rule-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.time-range-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
