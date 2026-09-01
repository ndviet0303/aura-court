<script setup lang="ts">
import { SelectDropdown, type DropdownOption } from '@/shared/components'

defineProps<{
  format: 'doubles' | 'singles' | 'team'
  skillLevel: 'all' | 'intermediate' | 'advanced'
  splitCost: boolean
  costPerPerson: number
  maxPlayers: number
  visibility: 'public' | 'invite_only'
  matchDescription: string
}>()

const emit = defineEmits<{
  (e: 'update:format', fmt: 'doubles' | 'singles' | 'team'): void
  (e: 'update:skillLevel', level: 'all' | 'intermediate' | 'advanced'): void
  (e: 'update:splitCost', split: boolean): void
  (e: 'update:costPerPerson', cost: number): void
  (e: 'update:maxPlayers', max: number): void
  (e: 'update:visibility', vis: 'public' | 'invite_only'): void
  (e: 'update:matchDescription', desc: string): void
}>()

const formatOptions: DropdownOption[] = [
  { value: 'doubles', label: 'Đánh Đôi (Doubles - 4 người)', sublabel: '2 người mỗi bên', icon: 'groups' },
  { value: 'singles', label: 'Đánh Đơn (Singles - 2 người)', sublabel: '1 vs 1 đối kháng', icon: 'person' },
  { value: 'team', label: 'Đấu Nhóm / CLB (Team Match)', sublabel: 'Nhiều người xoay tua', icon: 'diversity_3' },
]

const skillOptions: DropdownOption[] = [
  { value: 'all', label: 'Mọi trình độ (Giao lưu vui vẻ / Open)', sublabel: 'Level 1.0 - 5.0', icon: 'sentiment_satisfied' },
  { value: 'intermediate', label: 'Trung bình / Phong trào cứng', sublabel: 'Level 2.5 - 3.5', icon: 'military_tech' },
  { value: 'advanced', label: 'Bán chuyên / Nâng cao (Competitive)', sublabel: 'Level 4.0 - 5.0', icon: 'workspace_premium' },
]

const visibilityOptions: DropdownOption[] = [
  { value: 'public', label: 'Công khai (Public)', sublabel: 'Hiển thị trên bảng tin cộng đồng', icon: 'public' },
  { value: 'invite_only', label: 'Chỉ người được mời (Invite Only)', sublabel: 'Cần phê duyệt từ chủ phòng', icon: 'lock' },
]

const quickCosts = [
  { label: 'Miễn phí', value: 0 },
  { label: '30.000₫', value: 30000 },
  { label: '50.000₫', value: 50000 },
  { label: '70.000₫', value: 70000 },
  { label: '100.000₫', value: 100000 },
]

function adjustPlayers(current: number, delta: number) {
  const next = Math.max(2, Math.min(20, current + delta))
  emit('update:maxPlayers', next)
}
</script>

<template>
  <div class="match-settings-section">
    <div class="section-head">
      <div class="section-title-wrap">
        <span class="material-symbols-outlined head-icon">tune</span>
        <h3 class="section-title">Thông Số & Quy Định Trận Đấu</h3>
      </div>
      <span class="section-desc">Thiết lập thể thức thi đấu, trình độ yêu cầu và chi phí chia sẻ</span>
    </div>

    <div class="form-grid">
      <!-- Match Format -->
      <div class="form-field-group">
        <label class="field-label">
          <span class="material-symbols-outlined label-ico">groups</span>
          Thể thức thi đấu
        </label>
        <SelectDropdown
          :model-value="format"
          :options="formatOptions"
          variant="bordered"
          placeholder="Chọn hình thức..."
          @update:model-value="emit('update:format', $event as any)"
        />
      </div>

      <!-- Skill Level Requirement -->
      <div class="form-field-group">
        <label class="field-label">
          <span class="material-symbols-outlined label-ico">military_tech</span>
          Yêu cầu trình độ (Skill Level)
        </label>
        <SelectDropdown
          :model-value="skillLevel"
          :options="skillOptions"
          variant="bordered"
          placeholder="Chọn trình độ..."
          @update:model-value="emit('update:skillLevel', $event as any)"
        />
      </div>

      <!-- Visibility -->
      <div class="form-field-group">
        <label class="field-label">
          <span class="material-symbols-outlined label-ico">visibility</span>
          Chế độ phòng
        </label>
        <SelectDropdown
          :model-value="visibility"
          :options="visibilityOptions"
          variant="bordered"
          placeholder="Chọn chế độ..."
          @update:model-value="emit('update:visibility', $event as any)"
        />
      </div>

      <!-- Max Players Stepper -->
      <div class="form-field-group">
        <label class="field-label">
          <span class="material-symbols-outlined label-ico">person_add</span>
          Giới hạn người tham gia
        </label>
        <div class="stepper-box">
          <button
            type="button"
            class="step-btn"
            :disabled="maxPlayers <= 2"
            @click="adjustPlayers(maxPlayers, -1)"
          >
            <span class="material-symbols-outlined">remove</span>
          </button>
          <span class="stepper-val font-mono">{{ maxPlayers }} người</span>
          <button
            type="button"
            class="step-btn"
            :disabled="maxPlayers >= 20"
            @click="adjustPlayers(maxPlayers, 1)"
          >
            <span class="material-symbols-outlined">add</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Cost and Split Bill Section -->
    <div class="cost-split-card">
      <div class="cost-split-header">
        <div class="cost-title-wrap">
          <span class="material-symbols-outlined cost-ico">payments</span>
          <div class="cost-txt">
            <strong class="cost-title">Chi Phí / Người & Chia Tiền Tự Động</strong>
            <span class="cost-sub">Số tiền mỗi người tham gia đóng góp cho buổi chơi</span>
          </div>
        </div>
        <label class="toggle-switch">
          <input
            :checked="splitCost"
            type="checkbox"
            @change="emit('update:splitCost', ($event.target as HTMLInputElement).checked)"
          />
          <span class="toggle-slider"></span>
        </label>
      </div>

      <div v-if="splitCost" class="cost-input-body">
        <div class="quick-costs-row">
          <button
            v-for="qc in quickCosts"
            :key="qc.value"
            type="button"
            class="quick-cost-pill"
            :class="{ active: costPerPerson === qc.value }"
            @click="emit('update:costPerPerson', qc.value)"
          >
            {{ qc.label }}
          </button>
        </div>

        <div class="custom-amount-row">
          <label for="custom-cost-input" class="amount-label">Số tiền tùy chỉnh (VNĐ):</label>
          <div class="amount-input-wrap">
            <input
              id="custom-cost-input"
              :value="costPerPerson"
              type="number"
              min="0"
              step="5000"
              class="styled-input font-mono"
              @input="emit('update:costPerPerson', Number(($event.target as HTMLInputElement).value))"
            />
            <span class="currency-tag">₫/người</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Match Note / Description -->
    <div class="form-field-group">
      <label for="match-desc-input" class="field-label">
        <span class="material-symbols-outlined label-ico">edit_note</span>
        Ghi chú & Thỏa thuận (tùy chọn)
      </label>
      <textarea
        id="match-desc-input"
        :value="matchDescription"
        class="styled-textarea"
        rows="3"
        placeholder="Ví dụ: Đã có bóng/cầu sẵn, sân số 3, anh em khởi động đúng giờ..."
        @input="emit('update:matchDescription', ($event.target as HTMLTextAreaElement).value)"
      ></textarea>
    </div>
  </div>
</template>

<style scoped>
.match-settings-section {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 22px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 18px;
  box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04));
}

.section-head {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.section-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.head-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.section-title {
  margin: 0;
  font-size: 15.5px;
  font-weight: 700;
  color: var(--ink);
}

.section-desc {
  font-size: 12.5px;
  color: var(--muted);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.form-field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.label-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

.stepper-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  height: 42px;
}

.step-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s ease;
}

.step-btn:hover:not(:disabled) {
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border-color: var(--pink-dark, #db2777);
}

.step-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.stepper-val {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
}

.cost-split-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
}

.cost-split-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cost-title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cost-ico {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.cost-txt {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cost-title {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
}

.cost-sub {
  font-size: 11.5px;
  color: var(--muted);
}

.cost-input-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 6px;
  border-top: 1px dashed var(--outline);
}

.quick-costs-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quick-cost-pill {
  padding: 6px 12px;
  border-radius: 18px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.quick-cost-pill:hover {
  border-color: rgba(219, 39, 119, 0.4);
}

.quick-cost-pill.active {
  background: var(--pink-dark, #db2777);
  color: #ffffff;
  border-color: var(--pink-dark, #db2777);
}

.custom-amount-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.amount-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--muted);
}

.amount-input-wrap {
  position: relative;
  width: 180px;
}

.styled-input {
  width: 100%;
  padding: 8px 60px 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 600;
  outline: none;
}

.styled-input:focus {
  border-color: var(--pink-dark, #db2777);
}

.currency-tag {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 11.5px;
  font-weight: 600;
  color: var(--muted);
  pointer-events: none;
}

.styled-textarea {
  width: 100%;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-family: inherit;
  resize: vertical;
  outline: none;
}

.styled-textarea:focus {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

/* Custom Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 42px;
  height: 24px;
  flex-shrink: 0;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--outline);
  transition: 0.25s ease;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.25s ease;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: var(--pink-dark, #db2777);
}

input:checked + .toggle-slider:before {
  transform: translateX(18px);
}

@media (max-width: 680px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .custom-amount-row {
    flex-direction: column;
    align-items: flex-start;
  }
  .amount-input-wrap {
    width: 100%;
  }
}
</style>
