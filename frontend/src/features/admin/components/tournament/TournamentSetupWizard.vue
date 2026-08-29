<script setup lang="ts">
import type { TournamentAdminData } from '../../types/tournamentAdmin'

defineProps<{
  tournament: TournamentAdminData
  currentStep: number
}>()

const emit = defineEmits<{
  (e: 'update:currentStep', step: number): void
}>()
</script>

<template>
  <div class="tournament-wizard-card">
    <!-- Wizard Steps Header -->
    <div class="wizard-steps-header">
      <div
        class="step-item"
        :class="{ active: currentStep === 1, completed: currentStep > 1 }"
        @click="emit('update:currentStep', 1)"
      >
        <span class="step-num">1</span>
        <span class="step-label">Thông Tin Giải Đấu</span>
      </div>

      <div class="step-connector"></div>

      <div
        class="step-item"
        :class="{ active: currentStep === 2, completed: currentStep > 2 }"
        @click="emit('update:currentStep', 2)"
      >
        <span class="step-num">2</span>
        <span class="step-label">Thể Thức & Hạt Giống</span>
      </div>

      <div class="step-connector"></div>

      <div
        class="step-item"
        :class="{ active: currentStep === 3, completed: currentStep >= 3 }"
        @click="emit('update:currentStep', 3)"
      >
        <span class="step-num">3</span>
        <span class="step-label">Bảng Nhánh & Lịch Thi Đấu</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tournament-wizard-card {
  padding: 16px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.wizard-steps-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 800px;
  margin: 0 auto;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  padding: 6px 12px;
  border-radius: 10px;
  transition: all 0.2s;
}

.step-num {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.08));
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
}

.step-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
}

.step-item.active .step-num {
  background: var(--pink-dark, #db2777);
  color: #fff;
}

.step-item.active .step-label {
  color: var(--ink);
  font-weight: 700;
}

.step-item.completed .step-num {
  background: #10b981;
  color: #fff;
}

.step-connector {
  flex: 1;
  height: 2px;
  background: var(--outline);
  margin: 0 8px;
}
</style>
