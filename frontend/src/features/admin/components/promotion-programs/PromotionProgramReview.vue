<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { CreatePromotionProgramPayload } from '../../types/promotionProgram'
import { usePromotionProgramLabels } from '../../composables/usePromotionProgramLabels'

// The terms the owner confirms before issuing; nothing here is sent until the parent's confirm.
defineProps<{
  payload: CreatePromotionProgramPayload
  courtNames: string[]
}>()

const { t } = useI18n()
const { type, kind, discount, period, money } = usePromotionProgramLabels()
</script>

<template>
  <div class="program-review" data-testid="promotion-program-review">
    <p class="review-intro">{{ t('admin.promotions.review.intro') }}</p>
    <dl class="review-terms">
      <dt>{{ t('admin.promotions.form.nameLabel') }}</dt>
      <dd>{{ payload.name }}</dd>
      <dt>{{ t('admin.promotions.form.typeLabel') }}</dt>
      <dd>{{ type(payload.type) }}</dd>
      <dt>{{ t('admin.promotions.form.kindLabel') }}</dt>
      <dd>{{ kind(payload.discount_kind) }}</dd>
      <dt>{{ t('admin.promotions.discountLabel') }}</dt>
      <dd>
        {{
          discount({
            discount_kind: payload.discount_kind,
            percent: payload.percent ?? null,
            amount: payload.amount ?? null,
            max_discount: payload.max_discount ?? null,
          })
        }}
      </dd>
      <dt>{{ t('admin.promotions.minRentalLabel') }}</dt>
      <dd>{{ payload.min_rental ? money(payload.min_rental) : t('admin.promotions.none') }}</dd>
      <dt>{{ t('admin.promotions.periodLabel') }}</dt>
      <dd>{{ period(payload) }}</dd>
      <dt>{{ t('admin.promotions.courtsLabel') }}</dt>
      <dd>{{ courtNames.join(', ') }}</dd>
      <template v-if="payload.type === 'public'">
        <dt>{{ t('admin.promotions.maxUsesLabel') }}</dt>
        <dd>{{ payload.max_uses }}</dd>
      </template>
      <template v-else>
        <dt>{{ t('admin.promotions.codeCountLabel') }}</dt>
        <dd>{{ payload.code_count }}</dd>
      </template>
    </dl>
    <p class="review-warning" role="note">
      <span class="material-symbols-outlined">warning</span>
      {{ t('admin.promotions.review.immutableNote') }}
    </p>
  </div>
</template>

<style scoped>
.review-intro {
  margin: 0 0 12px;
  font-size: 14px;
}

.review-terms {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 8px 16px;
  margin: 0 0 16px;
  font-size: 14px;
}

.review-terms dt {
  color: var(--muted);
}

.review-terms dd {
  margin: 0;
  font-weight: 600;
}

.review-warning {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.12);
  color: #b45309;
  font-size: 13px;
}
</style>
