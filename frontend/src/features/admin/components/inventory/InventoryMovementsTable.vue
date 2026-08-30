<script setup lang="ts">
import type { StockMovement } from '../../types/inventory'

defineProps<{
  movements: StockMovement[]
  typeFilter: 'all' | 'in' | 'out' | 'adjust' | 'return'
}>()

const emit = defineEmits<{
  (e: 'update:typeFilter', val: 'all' | 'in' | 'out' | 'adjust' | 'return'): void
}>()
</script>

<template>
  <div class="movements-table-card">
    <div class="table-toolbar">
      <h3 class="title">📜 Nhật Ký Biến Động Nhập / Xuất Kho</h3>

      <select
        :value="typeFilter"
        class="type-select"
        @change="emit('update:typeFilter', ($event.target as HTMLSelectElement).value as any)"
      >
        <option value="all">Tất cả loại giao dịch</option>
        <option value="in">Nhập kho (Stock In)</option>
        <option value="out">Xuất kho (Stock Out)</option>
        <option value="adjust">Kiểm kê điều chỉnh</option>
      </select>
    </div>

    <div class="table-responsive">
      <div class="movements-table">
      <div class="table-hdr">
        <span>Thời Gian</span>
        <span>Sản Phẩm</span>
        <span>Loại Giao Dịch</span>
        <span>Số Lượng</span>
        <span>Ghi Chú & Người Thực Hiện</span>
      </div>

      <div class="table-body">
        <div v-for="m in movements" :key="m.id" class="movement-row">
          <span class="font-mono time-val">{{ m.created_at.slice(0, 10) }} {{ m.created_at.slice(11, 16) }}</span>
          <strong>{{ m.product_name }}</strong>
          <span
            class="type-badge"
            :class="m.type === 'in' ? 'type-in' : m.type === 'out' ? 'type-out' : 'type-adjust'"
          >
            {{ m.type === 'in' ? '↓ NHẬP KHO' : m.type === 'out' ? '↑ XUẤT KHO' : '🔄 ĐIỀU CHỈNH' }}
          </span>
          <strong class="qty-val font-mono" :class="m.type === 'in' ? 'text-green' : 'text-pink'">
            {{ m.type === 'in' ? `+${m.quantity}` : `-${m.quantity}` }}
          </strong>
          <div class="note-user">
            <span>{{ m.note || 'Không có ghi chú' }}</span>
            <span class="user-sub">Bởi: {{ m.created_by || 'Admin' }}</span>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<style scoped>
.movements-table-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.type-select {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
}

.movements-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
  min-width: 800px;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.5fr 2fr 1.5fr 1fr 2fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.movement-row {
  display: grid;
  grid-template-columns: 1.5fr 2fr 1.5fr 1fr 2fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.movement-row:last-child {
  border-bottom: 0;
}

.time-val { font-size: 11px; color: var(--muted); }

.type-badge {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 4px;
  width: fit-content;
}

.type-in { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.type-out { background: rgba(236, 72, 153, 0.1); color: var(--pink-dark, #db2777); }
.type-adjust { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

.qty-val { font-size: 14px; }
.text-green { color: #10b981; }
.text-pink { color: var(--pink-dark, #db2777); }

.note-user {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.user-sub { font-size: 11px; color: var(--muted); }
</style>
