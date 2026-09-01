<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(
  defineProps<{
    currentStep?: number
  }>(),
  {
    currentStep: 2,
  }
)

const { t } = useI18n()

const steps = computed(() => [
  {
    num: 1,
    icon: 'edit_document',
    titleKey: 'admin.payouts.step1Title',
    descKey: 'admin.payouts.step1Desc',
    // Fallback labels for tests
    defaultTitle: 'Tạo lệnh',
    defaultDesc: 'Chủ sân gửi yêu cầu',
  },
  {
    num: 2,
    icon: 'fact_check',
    titleKey: 'admin.payouts.step2Title',
    descKey: 'admin.payouts.step2Desc',
    defaultTitle: 'Duyệt',
    defaultDesc: 'Kiểm tra đối soát P&L',
  },
  {
    num: 3,
    icon: 'send',
    titleKey: 'admin.payouts.step3Title',
    descKey: 'admin.payouts.step3Desc',
    defaultTitle: 'Gửi yêu cầu',
    defaultDesc: 'Chuyển lệnh sang Vietcombank',
  },
  {
    num: 4,
    icon: 'hourglass_top',
    titleKey: 'admin.payouts.step4Title',
    descKey: 'admin.payouts.step4Desc',
    defaultTitle: 'Đang chi trả',
    defaultDesc: 'Xử lý qua NAPAS 247',
  },
  {
    num: 5,
    icon: 'check_circle',
    titleKey: 'admin.payouts.step5Title',
    descKey: 'admin.payouts.step5Desc',
    defaultTitle: 'Hoàn tất',
    defaultDesc: 'Tiền đã vào tài khoản',
  },
])
</script>

<template>
  <div class="payout-stepper-box">
    <div class="stepper-header">
      <div class="stepper-title-row">
        <span class="material-symbols-outlined icon-20 text-pink">account_tree</span>
        <h3 class="stepper-title">{{ t('admin.payouts.stepperTitle') }}</h3>
      </div>
    </div>

    <div class="stepper-track">
      <div
        v-for="(step, idx) in steps"
        :key="step.num"
        class="step-node"
        :class="{
          active: step.num <= currentStep,
          current: step.num === currentStep,
          completed: step.num < currentStep,
        }"
      >
        <div class="node-circle">
          <span v-if="step.num < currentStep" class="material-symbols-outlined icon-16">check</span>
          <span v-else class="node-num">{{ step.num }}</span>
        </div>

        <div class="node-text">
          <strong class="node-title">{{ t(step.titleKey) || step.defaultTitle }}</strong>
          <span class="node-desc">{{ t(step.descKey) || step.defaultDesc }}</span>
        </div>

        <!-- Connector line -->
        <div v-if="idx < steps.length - 1" class="step-connector"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payout-stepper-box {
  padding: 20px 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.stepper-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stepper-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.icon-20 {
  font-size: 20px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.stepper-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.stepper-track {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  position: relative;
}

.step-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  position: relative;
  z-index: 2;
}

.node-circle {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 2px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.2s ease;
  z-index: 3;
}

.step-node.active .node-circle {
  border-color: var(--pink-dark, #db2777);
  background: var(--pink-dark, #db2777);
  color: #fff;
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.step-node.completed .node-circle {
  background: #10b981;
  border-color: #10b981;
  color: #fff;
  box-shadow: none;
}

.node-text {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.node-title {
  font-size: 13px;
  color: var(--ink);
  font-weight: 700;
}

.node-desc {
  font-size: 11px;
  color: var(--muted);
  line-height: 1.3;
}

.step-connector {
  position: absolute;
  top: 17px;
  left: 50%;
  width: 100%;
  height: 2px;
  background: var(--outline);
  z-index: 1;
}

.step-node.completed .step-connector {
  background: #10b981;
}

.icon-16 {
  font-size: 16px;
  font-weight: 800;
}

@media (max-width: 820px) {
  .stepper-track {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .step-node {
    flex-direction: row;
    text-align: left;
  }
  .step-connector {
    display: none;
  }
}
</style>
