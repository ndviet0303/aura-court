<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { SelectDropdown, CurrencyInput, AuraButton } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { InventoryProduct, ProductFormPayload } from '../../types/inventory'

const props = defineProps<{
  show: boolean
  product: InventoryProduct | null
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: ProductFormPayload): void
}>()

const { t } = useI18n()

const name = ref('')
const sku = ref('')
const barcode = ref('')
const category = ref<string>('drinks')
const unit = ref('chai')
const taxRate = ref<number>(8)
const price = ref(25000)
const cost = ref(15000)
const stockQty = ref(50)
const minStock = ref(10)
const description = ref('')
const supplier = ref('')

const categoryOptions = computed<DropdownOption[]>(() => [
  { value: 'drinks', label: t('admin.inventory.catDrinksOption'), icon: 'local_drink' },
  { value: 'equipment', label: t('admin.inventory.catEquipmentOption'), icon: 'sports_tennis' },
  { value: 'accessories', label: t('admin.inventory.catAccessoriesOption'), icon: 'sports_score' },
  { value: 'snacks', label: t('admin.inventory.catSnacksOption'), icon: 'bakery_dining' },
  { value: 'rental', label: t('admin.inventory.catRentalOption'), icon: 'swap_horizontal_circle' },
  { value: 'services', label: t('admin.inventory.catServicesOption'), icon: 'room_service' },
])

const unitOptions = computed<DropdownOption[]>(() => [
  { value: 'chai', label: t('admin.inventory.unitBottle') },
  { value: 'lon', label: t('admin.inventory.unitCan') },
  { value: 'hộp', label: t('admin.inventory.unitBox') },
  { value: 'ống', label: t('admin.inventory.unitTube') },
  { value: 'cái', label: t('admin.inventory.unitItem') },
  { value: 'đôi', label: t('admin.inventory.unitPair') },
  { value: 'gói', label: t('admin.inventory.unitPack') },
  { value: 'set', label: t('admin.inventory.unitSet') },
])

const taxRateOptions = computed<DropdownOption[]>(() => [
  { value: '8', label: t('admin.inventory.tax8Percent'), icon: 'percent' },
  { value: '10', label: t('admin.inventory.tax10Percent'), icon: 'percent' },
  { value: '5', label: t('admin.inventory.tax5Percent'), icon: 'percent' },
  { value: '0', label: t('admin.inventory.tax0Percent'), icon: 'percent' },
])

const profitMargin = computed(() => {
  if (!price.value || price.value <= 0) return 0
  return Math.round(((price.value - (cost.value || 0)) / price.value) * 100)
})

const vatAmount = computed(() => {
  if (!price.value || price.value <= 0 || !taxRate.value) return 0
  return Math.round(price.value - price.value / (1 + taxRate.value / 100))
})

const netPrice = computed(() => {
  return Math.max(0, price.value - vatAmount.value)
})

function onCategoryChange(val: string) {
  category.value = val
  if (!props.product) {
    if (val === 'services') {
      taxRate.value = 10
    } else {
      taxRate.value = 8
    }
    generateSku()
  }
}

function generateSku() {
  const prefixMap: Record<string, string> = {
    drinks: 'DRK',
    equipment: 'EQP',
    accessories: 'ACC',
    snacks: 'SNK',
    rental: 'RNT',
    services: 'SRV',
  }
  const prefix = prefixMap[category.value] || 'POS'
  const random = Math.floor(1000 + Math.random() * 9000)
  sku.value = `${prefix}-${random}`
}

watch(
  () => props.product,
  (p) => {
    if (p) {
      name.value = p.name || ''
      sku.value = p.sku || ''
      barcode.value = p.barcode || ''
      category.value = p.category || 'drinks'
      unit.value = p.unit || 'chai'
      taxRate.value = p.tax_rate !== undefined ? Number(p.tax_rate) : (p.category === 'services' ? 10 : 8)
      price.value = Number(p.price) || 0
      cost.value = Number(p.cost) || 0
      stockQty.value = Number(p.stock_quantity) || 0
      minStock.value = Number(p.min_stock) || 10
      description.value = p.description || ''
      supplier.value = p.supplier || ''
    } else {
      name.value = ''
      category.value = 'drinks'
      taxRate.value = 8
      generateSku()
      barcode.value = ''
      unit.value = 'chai'
      price.value = 25000
      cost.value = 15000
      stockQty.value = 50
      minStock.value = 10
      description.value = ''
      supplier.value = ''
    }
  },
  { immediate: true }
)

function handleSave() {
  if (!name.value.trim() || !sku.value.trim()) return
  emit('save', {
    name: name.value.trim(),
    sku: sku.value.trim(),
    barcode: barcode.value.trim() || null,
    category: category.value,
    unit: unit.value.trim(),
    tax_rate: Number(taxRate.value),
    price: Math.max(0, price.value),
    cost: Math.max(0, cost.value),
    stock_quantity: Math.max(0, stockQty.value),
    min_stock: Math.max(0, minStock.value),
    description: description.value.trim() || null,
    supplier: supplier.value.trim() || null,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <div class="header-icon-box">
            <span class="material-symbols-outlined card-title-icon">{{ product ? 'edit_note' : 'add_box' }}</span>
          </div>
          <div>
            <h3 class="modal-title">
              {{ product ? t('admin.inventory.editProductTitle') : t('admin.inventory.addProductTitle') }}
            </h3>
            <p class="modal-sub">{{ t('admin.inventory.productModalSub') }}</p>
          </div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <form class="product-form" @submit.prevent="handleSave">
          <!-- Section 1: Thông tin cơ bản -->
          <div class="form-section">
            <div class="section-hdr">
              <span class="material-symbols-outlined icon-16 text-pink">info</span>
              <span>Thông tin mặt hàng</span>
            </div>

            <div class="section-grid-4">
              <!-- Name (Full Width) -->
              <div class="form-group full-width">
                <label class="form-label">{{ t('admin.inventory.productNameLabel') }} *</label>
                <input
                  v-model="name"
                  type="text"
                  class="form-input"
                  required
                  :placeholder="t('admin.inventory.productNamePlaceholder')"
                />
              </div>

              <!-- Category -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.categoryLabel') }} *</label>
                <SelectDropdown
                  :model-value="category"
                  :options="categoryOptions"
                  variant="bordered"
                  @update:model-value="onCategoryChange(String($event))"
                />
              </div>

              <!-- Unit -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.unitLabel') }} *</label>
                <SelectDropdown
                  :model-value="unit"
                  :options="unitOptions"
                  variant="bordered"
                  @update:model-value="unit = String($event)"
                />
              </div>

              <!-- SKU -->
              <div class="form-group">
                <label class="form-label flex-between">
                  <span>{{ t('admin.inventory.skuLabel') }} *</span>
                  <button type="button" class="btn-text-link" @click="generateSku">
                    {{ t('admin.inventory.autoGenSku') }}
                  </button>
                </label>
                <input v-model="sku" type="text" class="form-input font-mono" required />
              </div>

              <!-- Barcode -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.barcodeLabel') }}</label>
                <input
                  v-model="barcode"
                  type="text"
                  class="form-input font-mono"
                  :placeholder="t('admin.inventory.barcodePlaceholder')"
                />
              </div>
            </div>
          </div>

          <!-- Section 2: Giá & Thuế VAT -->
          <div class="form-section pricing-section">
            <div class="section-hdr">
              <span class="material-symbols-outlined icon-16 text-pink">payments</span>
              <span>Giá vốn, Giá bán & Thuế GTGT</span>
            </div>

            <div class="pricing-grid">
              <!-- Cost Price -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.costLabel') }}</label>
                <CurrencyInput v-model="cost" />
              </div>

              <!-- Retail Price -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.priceLabel') }} *</label>
                <CurrencyInput v-model="price" />
              </div>

              <!-- Tax Rate -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.taxRateLabel') }} *</label>
                <SelectDropdown
                  :model-value="String(taxRate)"
                  :options="taxRateOptions"
                  variant="bordered"
                  @update:model-value="taxRate = Number($event)"
                />
              </div>
            </div>

            <!-- Pricing Summary Strip -->
            <div class="pricing-summary-strip font-mono">
              <div class="summary-metric">
                <span class="metric-lbl">Tỷ suất lợi nhuận:</span>
                <span class="margin-tag" :class="profitMargin > 0 ? 'text-green' : 'text-muted'">
                  +{{ profitMargin }}% Lợi nhuận
                </span>
              </div>
              <div class="summary-metric">
                <span class="metric-lbl">Giá chưa thuế:</span>
                <strong class="metric-val">{{ formatCurrency(netPrice) }}</strong>
              </div>
              <div class="summary-metric">
                <span class="metric-lbl">Tiền thuế ({{ taxRate }}%):</span>
                <strong class="metric-val text-pink">{{ formatCurrency(vatAmount) }}</strong>
              </div>
            </div>
          </div>

          <!-- Section 3: Kho & Nhà cung cấp -->
          <div class="form-section">
            <div class="section-hdr">
              <span class="material-symbols-outlined icon-16 text-pink">inventory_2</span>
              <span>Định mức tồn kho & Nhà cung cấp</span>
            </div>

            <div class="section-grid-3">
              <!-- Initial/Current Stock -->
              <div class="form-group">
                <label class="form-label">
                  {{ product ? t('admin.inventory.currentStockLabel') : t('admin.inventory.initialStockLabel') }}
                </label>
                <input
                  v-model.number="stockQty"
                  type="number"
                  min="0"
                  class="form-input font-mono"
                  :disabled="Boolean(product)"
                />
              </div>

              <!-- Min Stock Alert -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.minStockLabel') }}</label>
                <input v-model.number="minStock" type="number" min="0" class="form-input font-mono" />
              </div>

              <!-- Supplier -->
              <div class="form-group">
                <label class="form-label">{{ t('admin.inventory.supplierLabel') }}</label>
                <input
                  v-model="supplier"
                  type="text"
                  class="form-input"
                  :placeholder="t('admin.inventory.supplierPlaceholder')"
                />
              </div>

              <!-- Description (Full Width) -->
              <div class="form-group full-width">
                <label class="form-label">{{ t('admin.inventory.descriptionLabel') }}</label>
                <input
                  v-model="description"
                  type="text"
                  class="form-input"
                  :placeholder="t('admin.inventory.descriptionPlaceholder')"
                />
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="secondary" @click="emit('close')">{{ t('common.cancel') }}</AuraButton>
        <AuraButton
          variant="primary"
          :disabled="!name.trim() || !sku.trim() || saving"
          @click="handleSave"
        >
          <span v-if="saving" class="material-symbols-outlined icon-16 spin-anim">progress_activity</span>
          <span>{{ saving ? t('common.saving') : t('admin.inventory.saveProductBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 780px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
  display: flex;
  flex-direction: column;
  max-height: 92vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(236, 72, 153, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-title-icon {
  font-size: 20px;
  color: var(--pink-dark, #ec4899);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub {
  margin: 1px 0 0;
  font-size: 11.5px;
  color: var(--muted);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.modal-close-btn:hover {
  color: var(--ink);
  background: var(--surface-secondary, rgba(255, 255, 255, 0.05));
}

.modal-body {
  padding: 14px 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.modal-body::-webkit-scrollbar {
  width: 4px;
}

.modal-body::-webkit-scrollbar-track {
  background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 999px;
}

.product-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Sections */
.form-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(255, 255, 255, 0.025));
  border: 1px solid var(--outline);
}

.section-hdr {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--ink);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.section-grid-4 {
  display: grid;
  grid-template-columns: 1.2fr 1fr 1fr 1fr;
  gap: 10px;
}

.section-grid-3 {
  display: grid;
  grid-template-columns: 1fr 1fr 1.2fr;
  gap: 10px;
}

.pricing-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1.3fr;
  gap: 10px;
}

.full-width {
  grid-column: 1 / -1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-label {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--ink);
}

.flex-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn-text-link {
  background: transparent;
  border: none;
  color: var(--pink-dark, #ec4899);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
}

.btn-text-link:hover {
  text-decoration: underline;
}

.form-input {
  width: 100%;
  height: 36px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-input:focus {
  border-color: var(--pink-dark, #ec4899);
  box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.12);
}

.form-input:disabled {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  color: var(--muted);
  cursor: not-allowed;
}

/* Pricing Summary Strip */
.pricing-summary-strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--outline);
  font-size: 11.5px;
  flex-wrap: wrap;
  gap: 6px;
}

.summary-metric {
  display: flex;
  align-items: center;
  gap: 6px;
}

.metric-lbl {
  color: var(--muted);
  font-size: 11px;
}

.metric-val {
  font-size: 12px;
  font-weight: 700;
}

.margin-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.text-pink {
  color: var(--pink-dark, #ec4899);
}

.text-green {
  color: #10b981;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.icon-16 { font-size: 16px; }

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .section-grid, .section-grid.grid-3, .pricing-grid {
    grid-template-columns: 1fr;
  }
}
</style>
