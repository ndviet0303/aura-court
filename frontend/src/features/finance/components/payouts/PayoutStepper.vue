<script setup lang="ts">
const steps = [
  { num: 1, title: 'Tạo lệnh', desc: 'Chủ sân gửi yêu cầu' },
  { num: 2, title: 'Duyệt', desc: 'Kiểm tra đối soát P&L' },
  { num: 3, title: 'Gửi yêu cầu', desc: 'Chuyển lệnh sang Vietcombank' },
  { num: 4, title: 'Đang chi trả', desc: 'Xử lý qua NAPAS 247' },
  { num: 5, title: 'Hoàn tất', desc: 'Tiền đã vào tài khoản' },
]
</script>

<template>
  <div class="payout-stepper-box">
    <div class="stepper-track">
      <div
        v-for="step in steps"
        :key="step.num"
        class="step-node"
        :class="{ active: step.num <= 2 }"
      >
        <div class="node-circle">{{ step.num }}</div>
        <div class="node-text">
          <strong class="node-title">{{ step.title }}</strong>
          <span class="node-desc">{{ step.desc }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payout-stepper-box {
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.stepper-track {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
}

.step-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  position: relative;
}

.node-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 2px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
}

.step-node.active .node-circle {
  border-color: var(--pink-dark, #db2777);
  background: var(--pink-dark, #db2777);
  color: #fff;
}

.node-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.node-title {
  font-size: 13px;
}

.node-desc {
  font-size: 11px;
  color: var(--muted);
}

@media (max-width: 768px) {
  .stepper-track {
    grid-template-columns: 1fr;
  }
  .step-node {
    flex-direction: row;
    text-align: left;
  }
}
</style>
