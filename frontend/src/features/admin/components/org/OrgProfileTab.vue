<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Organization } from '../../composables/useOrganizationOwner'

const props = defineProps<{
  org: Organization
  brandColors: Array<{ label: string; value: string }>
}>()

const emit = defineEmits<{
  (e: 'save', payload: Partial<Organization>): void
}>()

const name = ref(props.org.name)
const taxCode = ref(props.org.tax_code || '')
const supportEmail = ref(props.org.support_email || '')
const phone = ref(props.org.phone || '')
const address = ref(props.org.address || '')
const brandColor = ref(props.org.brand_color || '#78555e')

watch(
  () => props.org,
  (o) => {
    name.value = o.name
    taxCode.value = o.tax_code || ''
    supportEmail.value = o.support_email || ''
    phone.value = o.phone || ''
    address.value = o.address || ''
    brandColor.value = o.brand_color || '#78555e'
  }
)

function handleSave() {
  emit('save', {
    name: name.value.trim(),
    tax_code: taxCode.value.trim(),
    support_email: supportEmail.value.trim(),
    phone: phone.value.trim(),
    address: address.value.trim(),
    brand_color: brandColor.value,
  })
}
</script>

<template>
  <div class="org-profile-tab-card">
    <h3 class="tab-title">🏢 Hồ Sơ Doanh Nghiệp & Định Danh Thương Hiệu</h3>

    <form class="profile-form" @submit.prevent="handleSave">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Tên doanh nghiệp / Chuỗi sân:</label>
          <input v-model="name" type="text" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Mã số thuế (Tax ID):</label>
          <input v-model="taxCode" type="text" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Email hỗ trợ khách hàng:</label>
          <input v-model="supportEmail" type="email" class="form-input" />
        </div>

        <div class="form-group">
          <label class="form-label">Hotline liên hệ:</label>
          <input v-model="phone" type="tel" class="form-input" />
        </div>

        <div class="form-group full-width">
          <label class="form-label">Địa chỉ trụ sở chính:</label>
          <input v-model="address" type="text" class="form-input" />
        </div>

        <div class="form-group full-width">
          <label class="form-label">Màu sắc chủ đạo thương hiệu (Brand Color):</label>
          <div class="colors-palette-row">
            <label
              v-for="c in brandColors"
              :key="c.value"
              class="color-chip-card"
              :class="{ active: brandColor === c.value }"
            >
              <input v-model="brandColor" type="radio" :value="c.value" name="brand-color" />
              <span class="color-circle" :style="{ background: c.value }"></span>
              <span class="c-lbl">{{ c.label }}</span>
            </label>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save">
          💾 Lưu Thông Tin Doanh Nghiệp
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.org-profile-tab-card {
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

.colors-palette-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.color-chip-card {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  cursor: pointer;
  font-size: 12px;
}

.color-chip-card.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
}

.color-circle {
  width: 18px;
  height: 18px;
  border-radius: 50%;
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
