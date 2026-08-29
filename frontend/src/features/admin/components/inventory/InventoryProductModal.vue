<script setup lang="ts">
import { ref, watch } from 'vue'
import type { InventoryProduct } from '../../types/inventory'

const props = defineProps<{
  show: boolean
  product: InventoryProduct | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: any): void
}>()

const name = ref('')
const sku = ref('')
const category = ref<InventoryProduct['category']>('drinks')
const price = ref(20000)
const cost = ref(12000)
const stockQty = ref(50)
const minStock = ref(10)
const unit = ref('chai')

watch(
  () => props.product,
  (p) => {
    if (p) {
      name.value = p.name
      sku.value = p.sku
      category.value = p.category
      price.value = p.price
      cost.value = p.cost
      stockQty.value = p.stock_quantity
      minStock.value = p.min_stock
      unit.value = p.unit
    } else {
      name.value = ''
      sku.value = `SKU-${Date.now().toString().slice(-4)}`
      category.value = 'drinks'
      price.value = 20000
      cost.value = 12000
      stockQty.value = 50
      minStock.value = 10
      unit.value = 'chai'
    }
  },
  { immediate: true }
)

function handleSave() {
  if (!name.value.trim()) return
  emit('save', {
    name: name.value.trim(),
    sku: sku.value.trim(),
    category: category.value,
    price: price.value,
    cost: cost.value,
    stock_quantity: stockQty.value,
    min_stock: minStock.value,
    unit: unit.value.trim(),
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">
          {{ product ? '✏️ Chỉnh Sửa Sản Phẩm' : '➕ Thêm Mặt Hàng Mới' }}
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full-width">
            <label class="form-label">Tên sản phẩm:</label>
            <input v-model="name" type="text" class="form-input" placeholder="Vd: Nước bù khoáng Pocari..." />
          </div>

          <div class="form-group">
            <label class="form-label">Mã SKU:</label>
            <input v-model="sku" type="text" class="form-input font-mono" />
          </div>

          <div class="form-group">
            <label class="form-label">Danh mục:</label>
            <select v-model="category" class="form-input">
              <option value="drinks">Đồ uống (Drinks)</option>
              <option value="equipment">Thiết bị & Dụng cụ</option>
              <option value="accessories">Phụ kiện thi đấu</option>
              <option value="snacks">Đồ ăn nhẹ</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Giá vốn (Cost):</label>
            <input v-model.number="cost" type="number" step="1000" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Giá bán lẻ (Price):</label>
            <input v-model.number="price" type="number" step="1000" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Số lượng tồn ban đầu:</label>
            <input v-model.number="stockQty" type="number" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Mức tồn tối thiểu (Min):</label>
            <input v-model.number="minStock" type="number" class="form-input" />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" :disabled="!name.trim()" @click="handleSave">
          Lưu Mặt Hàng
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.full-width {
  grid-column: 1 / -1;
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
