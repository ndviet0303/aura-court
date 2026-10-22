<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { PromotionProgram } from '../../types/promotionProgram'
import { usePromotionProgramLabels } from '../../composables/usePromotionProgramLabels'

const props = defineProps<{
  program: PromotionProgram
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'edit'): void
  (e: 'stop'): void
  (e: 'codes'): void
}>()

const { t } = useI18n()
const { type, kind, status, discount, period, instant, courtName, money } =
  usePromotionProgramLabels()

const stopped = computed(() => props.program.status === 'stopped')
const total = computed(() => props.program.max_uses ?? props.program.code_count ?? 0)
</script>

<template>
  <section class="program-detail" data-testid="promotion-program-detail">
    <header class="detail-header">
      <div>
        <h2 class="detail-title">{{ program.name }}</h2>
        <p class="detail-meta">
          <span class="badge" :class="`badge--${program.status}`">{{
            status(program.status)
          }}</span>
          {{ type(program.type) }} · {{ kind(program.discount_kind) }}
        </p>
      </div>
      <div class="detail-actions">
        <AuraButton
          v-if="program.type === 'private'"
          variant="secondary"
          size="sm"
          icon="qr_code_2"
          @click="emit('codes')"
        >
          {{ t('admin.promotions.codesBtn') }}
        </AuraButton>
        <AuraButton
          v-if="!stopped"
          variant="primary"
          size="sm"
          :disabled="saving"
          @click="emit('edit')"
        >
          {{ t('admin.promotions.editBtn') }}
        </AuraButton>
        <AuraButton
          v-if="!stopped"
          variant="outline"
          size="sm"
          :disabled="saving"
          @click="emit('stop')"
        >
          {{ t('admin.promotions.stopBtn') }}
        </AuraButton>
      </div>
    </header>

    <dl class="terms">
      <dt>{{ t('admin.promotions.discountLabel') }}</dt>
      <dd>{{ discount(program) }}</dd>
      <dt>{{ t('admin.promotions.minRentalLabel') }}</dt>
      <dd>{{ program.min_rental ? money(program.min_rental) : t('admin.promotions.none') }}</dd>
      <dt>{{ t('admin.promotions.periodLabel') }}</dt>
      <dd>{{ period(program) }}</dd>
      <template v-if="program.stopped_at">
        <dt>{{ t('admin.promotions.stoppedAtLabel') }}</dt>
        <dd>{{ instant(program.stopped_at) }}</dd>
      </template>
      <dt>{{ t('admin.promotions.courtsLabel') }}</dt>
      <dd>{{ program.courts.map((court) => courtName(court.name)).join(', ') }}</dd>
    </dl>

    <h3 class="section-title">{{ t('admin.promotions.usageTitle') }}</h3>
    <div class="usage-grid">
      <div class="usage-cell">
        <span class="usage-value">{{ total }}</span>
        <span class="usage-label">{{
          t(
            program.type === 'public'
              ? 'admin.promotions.maxUsesLabel'
              : 'admin.promotions.codeCountLabel',
          )
        }}</span>
      </div>
      <div class="usage-cell">
        <span class="usage-value">{{ program.usage.used }}</span>
        <span class="usage-label">{{ t('admin.promotions.usedLabel') }}</span>
      </div>
      <div class="usage-cell">
        <span class="usage-value">{{ program.usage.held }}</span>
        <span class="usage-label">{{ t('admin.promotions.heldLabel') }}</span>
      </div>
      <div class="usage-cell">
        <span class="usage-value">{{ program.usage.remaining }}</span>
        <span class="usage-label">{{ t('admin.promotions.remainingLabel') }}</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
.program-detail {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.detail-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
}

.detail-title {
  margin: 0;
  font-size: 18px;
}

.detail-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.detail-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.terms {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 6px 16px;
  margin: 0;
  font-size: 13px;
}

.terms dt {
  color: var(--muted);
}

.terms dd {
  margin: 0;
}

.section-title {
  margin: 8px 0 0;
  font-size: 14px;
}

.usage-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 8px;
}

.usage-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--soft, rgba(0, 0, 0, 0.03));
}

.usage-value {
  font-size: 18px;
  font-weight: 700;
}

.usage-label {
  color: var(--muted);
  font-size: 12px;
}

.badge {
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--soft, rgba(0, 0, 0, 0.05));
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
}

.badge--running {
  color: #047857;
  background: rgba(16, 185, 129, 0.12);
}

.badge--upcoming {
  color: #b45309;
  background: rgba(245, 158, 11, 0.12);
}

.badge--ended,
.badge--stopped {
  color: var(--muted);
}
</style>
