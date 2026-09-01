<script setup lang="ts">
import { ref } from 'vue'
import { AuraButton } from '@/shared/components'

defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', name: string, category: 'drink' | 'gear' | 'service', qty: number, unitPrice: number): void
}>()

const PRESETS = [
  { name: 'Nước Tăng Lực Pocari Sweat 500ml', category: 'drink' as const, price: 20000, icon: 'local_drink' },
  { name: 'Nước Bù Khoáng Revive Chanh Muối', category: 'drink' as const, price: 15000, icon: 'local_drink' },
  { name: 'Nước Suối Tinh Khiết Aquafina 500ml', category: 'drink' as const, price: 10000, icon: 'water_drop' },
  { name: 'Hộp Cầu Lông Victor Champion 12 Quả', category: 'gear' as const, price: 120000, icon: 'sports_tennis' },
  { name: 'Hộp Cầu Lông Ba Sao Vàng 12 Quả', category: 'gear' as const, price: 160000, icon: 'sports_tennis' },
  { name: 'Thuê Vợt Cầu Lông Yonex Cao Cấp (1 Cây)', category: 'gear' as const, price: 30000, icon: 'sports' },
  { name: 'Khăn Lau Mồ Hôi Thể Thao Aura', category: 'service' as const, price: 25000, icon: 'dry_cleaning' },
]

const itemName = ref('')
const itemCategory = ref<'drink' | 'gear' | 'service'>('drink')
const itemQty = ref(1)
const itemPrice = ref(20000)

function selectPreset(preset: typeof PRESETS[0]) {
  itemName.value = preset.name
  itemCategory.value = preset.category
  itemPrice.value = preset.price
  itemQty.value = 1
}

function handleSubmit() {
  if (!itemName.value.trim() || itemQty.value <= 0 || itemPrice.value <= 0) return
  emit('submit', itemName.value.trim(), itemCategory.value, itemQty.value, itemPrice.value)
  itemName.value = ''
  itemQty.value = 1
  itemPrice.value = 20000
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="header-title-wrap">
          <span class="material-symbols-outlined header-icon">add_shopping_cart</span>
          <h3 class="modal-title">Thêm Nước Uống, Cầu Lông & Dịch Vụ Sân</h3>
        </div>
        <button class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- Quick Sports Presets -->
        <div class="preset-section">
          <span class="preset-title">Món phổ biến tại sân Aura:</span>
          <div class="preset-chips">
            <button
              v-for="p in PRESETS"
              :key="p.name"
              type="button"
              class="preset-btn"
              @click="selectPreset(p)"
            >
              <span class="material-symbols-outlined p-ico">{{ p.icon }}</span>
              <span class="p-name">{{ p.name }}</span>
              <span class="p-price font-mono">{{ formatMoney(p.price) }}</span>
            </button>
          </div>
        </div>

        <form class="custom-item-form" @submit.prevent="handleSubmit">
          <div class="form-row">
            <div class="form-group flex-2">
              <label class="form-label">Tên món / dịch vụ (*):</label>
              <input
                v-model="itemName"
                type="text"
                required
                placeholder="Nhập tên dịch vụ hoặc chọn từ trên..."
                class="form-input"
              />
            </div>

            <div class="form-group flex-1">
              <label class="form-label">Phân loại:</label>
              <select v-model="itemCategory" class="form-select">
                <option value="drink">Nước uống</option>
                <option value="gear">Dụng cụ / Cầu</option>
                <option value="service">Dịch vụ khác</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group flex-1">
              <label class="form-label">Số lượng:</label>
              <input
                v-model.number="itemQty"
                type="number"
                min="1"
                required
                class="form-input font-mono"
              />
            </div>

            <div class="form-group flex-1">
              <label class="form-label">Đơn giá (VNĐ):</label>
              <input
                v-model.number="itemPrice"
                type="number"
                min="1000"
                step="5000"
                required
                class="form-input font-mono"
              />
            </div>

            <div class="form-group flex-1">
              <label class="form-label">Thành tiền:</label>
              <div class="computed-total font-mono">
                {{ formatMoney(itemQty * itemPrice) }}
              </div>
            </div>
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
              :disabled="!itemName.trim()"
            >
              <span>Thêm Vào Hóa Đơn</span>
            </AuraButton>
          </div>
        </form>
      </div>
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
  max-width: 560px;
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
  gap: 16px;
}

.preset-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.preset-title {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted, #9e939a);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.preset-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  max-height: 140px;
  overflow-y: auto;
  padding-right: 4px;
}

.preset-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface-container, rgba(255, 255, 255, 0.04));
  color: var(--on-surface, #FAF7F3);
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}

.preset-btn:hover {
  border-color: var(--primary, #E58E9F);
  background: rgba(229, 142, 159, 0.1);
}

.p-ico {
  font-size: 14px;
  color: var(--primary, #E58E9F);
}

.p-name {
  font-weight: 600;
}

.p-price {
  font-size: 11px;
  color: var(--muted, #9e939a);
}

.custom-item-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-row {
  display: flex;
  gap: 12px;
}

.flex-1 { flex: 1; }
.flex-2 { flex: 2; }

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--on-surface, #FAF7F3);
}

.form-input, .form-select {
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface-container, rgba(0, 0, 0, 0.2));
  color: var(--on-surface, #FAF7F3);
  font-size: 13px;
  outline: none;
}

.form-input:focus, .form-select:focus {
  border-color: var(--primary, #E58E9F);
}

.computed-total {
  padding: 8px 12px;
  border-radius: 10px;
  background: var(--surface-container-high, rgba(229, 142, 159, 0.1));
  color: var(--primary, #E58E9F);
  font-weight: 800;
  font-size: 14px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}
</style>
