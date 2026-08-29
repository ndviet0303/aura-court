<script setup lang="ts">
import { ref, watch } from 'vue'
import type { AdminVenueItem } from '../../composables/useAdminVenues'

const props = defineProps<{
  show: boolean
  venue: AdminVenueItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: any): void
}>()

const name = ref('')
const address = ref('')
const googleMapsUrl = ref('')
const isActive = ref(true)

watch(
  () => props.venue,
  (v) => {
    if (v) {
      name.value = v.name
      address.value = v.address || ''
      googleMapsUrl.value = v.google_maps_url || ''
      isActive.value = v.is_active
    } else {
      name.value = ''
      address.value = ''
      googleMapsUrl.value = ''
      isActive.value = true
    }
  },
  { immediate: true }
)

function handleSave() {
  if (!name.value.trim()) return
  emit('save', {
    name: name.value.trim(),
    address: address.value.trim(),
    google_maps_url: googleMapsUrl.value.trim() || undefined,
    is_active: isActive.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">
          {{ venue ? '✏️ Chỉnh Sửa Cụm Sân' : '➕ Thêm Cơ Sở / Cụm Sân Mới' }}
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Tên cụm sân / cơ sở <span class="required">*</span>:</label>
          <input v-model="name" type="text" class="form-input" placeholder="Vd: Aura Center - Quận 1..." />
        </div>

        <div class="form-group">
          <label class="form-label">Địa chỉ đầy đủ:</label>
          <input v-model="address" type="text" class="form-input" placeholder="Số nhà, tên đường, phường, quận..." />
        </div>

        <div class="form-group">
          <label class="form-label">Liên kết Google Maps:</label>
          <input v-model="googleMapsUrl" type="url" class="form-input" placeholder="https://maps.google.com/..." />
        </div>

        <div class="form-group">
          <label class="form-label">Trạng thái hoạt động:</label>
          <label class="check-box-label">
            <input v-model="isActive" type="checkbox" />
            <span>Đang mở cửa vận hành (Active)</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" :disabled="!name.trim()" @click="handleSave">
          Lưu Cơ Sở
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.check-box-label {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
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
