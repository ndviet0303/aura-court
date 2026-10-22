<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, CurrencyInput, DateTimePicker, SelectDropdown } from '@/shared/components'
import { venueClock, venueDate, venueInstant } from '@/core/utils/venueTime'
import type { Court } from '@/features/court/types/court'
import type {
  CreatePromotionProgramPayload,
  PromotionDiscountKind,
  PromotionProgram,
  PromotionProgramTerms,
  PromotionProgramType,
} from '../../types/promotionProgram'
import { usePromotionProgramLabels } from '../../composables/usePromotionProgramLabels'
import PromotionProgramReview from './PromotionProgramReview.vue'

const props = defineProps<{
  courts: Court[]
  saving: boolean
  errors: Record<string, string[]>
  /** Set to edit this program; otherwise the form creates one after a review step. */
  program?: PromotionProgram
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: CreatePromotionProgramPayload): void
  (e: 'update', payload: Partial<PromotionProgramTerms>): void
}>()

const { t } = useI18n()
const labels = usePromotionProgramLabels()

const localTime = (iso: string) => `${venueDate(iso)}T${venueClock(iso)}`
const source = props.program
const draft = reactive({
  name: source?.name ?? '',
  type: source?.type ?? ('public' as PromotionProgramType),
  kind: source?.discount_kind ?? ('percent' as PromotionDiscountKind),
  percent: source?.percent ?? (null as number | null),
  amount: source?.amount ?? (null as number | null),
  maxDiscount: source?.max_discount ?? (null as number | null),
  minRental: source?.min_rental ?? (null as number | null),
  startsAt: source ? localTime(source.starts_at) : localTime(new Date().toISOString()),
  endsAt: source ? localTime(source.ends_at) : '',
  courtIds: source?.courts.map((court) => court.id) ?? ([] as number[]),
  maxUses: source?.max_uses ?? (null as number | null),
  codeCount: source?.code_count ?? (null as number | null),
})
const reviewing = ref(false)

const typeOptions = computed(() =>
  (['public', 'private'] as const).map((value) => ({ value, label: labels.type(value) })),
)
const kindOptions = computed(() =>
  (['percent', 'fixed'] as const).map((value) => ({ value, label: labels.kind(value) })),
)
// The currency input has no native required check, so an empty fixed amount keeps the form from moving on.
const ready = computed(
  () =>
    draft.courtIds.length > 0 &&
    !!draft.startsAt &&
    !!draft.endsAt &&
    (draft.kind === 'percent' || (draft.amount ?? 0) > 0),
)

// Errors of a field and of its items, such as court_ids.0.
const errorsOf = (field: string) =>
  Object.entries(props.errors)
    .filter(([key]) => key === field || key.startsWith(`${field}.`))
    .flatMap(([, messages]) => messages)
const fixedErrors = computed(() =>
  ['program', 'type', 'discount_kind', 'code_count', 'codes'].flatMap(errorsOf),
)

// A server refusal sends the owner back to the fields it names.
watch(
  () => props.errors,
  (errors) => {
    if (Object.keys(errors).length > 0) reviewing.value = false
  },
)

// Only the fields of this program's discount kind and voucher kind; optional amounts left empty are cleared.
function terms(): PromotionProgramTerms {
  return {
    name: draft.name.trim(),
    ...(draft.kind === 'percent'
      ? { percent: draft.percent ?? 0, max_discount: draft.maxDiscount || null }
      : { amount: draft.amount ?? 0 }),
    min_rental: draft.minRental || null,
    starts_at: venueInstant(draft.startsAt),
    ends_at: venueInstant(draft.endsAt),
    court_ids: [...draft.courtIds].sort((a, b) => a - b),
    ...(draft.type === 'public' ? { max_uses: draft.maxUses ?? 0 } : {}),
  }
}

const payload = computed<CreatePromotionProgramPayload>(() => ({
  ...terms(),
  type: draft.type,
  discount_kind: draft.kind,
  ...(draft.type === 'private' ? { code_count: draft.codeCount ?? 0 } : {}),
}))
const reviewCourtNames = computed(() =>
  props.courts
    .filter((court) => draft.courtIds.includes(court.id))
    .map((court) => resolveLocalizedText(court.name)),
)

// The second click of a double-click on "Xem lại" lands on the confirm button, so it stays disabled for a moment.
const confirmLocked = ref(false)

function submit() {
  if (props.program) emit('update', terms())
  else if (!reviewing.value) {
    reviewing.value = true
    confirmLocked.value = true
    setTimeout(() => (confirmLocked.value = false), 500)
  } else if (!confirmLocked.value) emit('submit', payload.value)
}
</script>

<template>
  <div class="modal-backdrop" @click.self="!reviewing && emit('close')">
    <form
      class="modal-card modal-lg"
      :data-testid="program ? 'promotion-program-edit' : 'promotion-program-create'"
      @submit.prevent="submit"
    >
      <div class="modal-header">
        <h3 class="modal-title">
          {{
            program
              ? t('admin.promotions.form.editTitle', { name: program.name })
              : reviewing
                ? t('admin.promotions.review.title')
                : t('admin.promotions.form.createTitle')
          }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('admin.promotions.form.cancel')"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <div class="modal-body">
        <PromotionProgramReview
          v-if="reviewing"
          :payload="payload"
          :court-names="reviewCourtNames"
        />
        <template v-else>
          <p v-if="program" class="form-hint">{{ t('admin.promotions.form.immutableHint') }}</p>
          <p v-for="message in fixedErrors" :key="message" class="form-error">{{ message }}</p>
          <label class="form-group">
            <span class="form-label">{{ t('admin.promotions.form.nameLabel') }}</span>
            <input v-model="draft.name" class="form-input" name="name" maxlength="120" required />
            <span v-for="m in errorsOf('name')" :key="m" class="form-error">{{ m }}</span>
          </label>
          <div class="form-row">
            <div class="form-group">
              <span class="form-label">{{ t('admin.promotions.form.typeLabel') }}</span>
              <SelectDropdown
                :model-value="draft.type"
                :options="typeOptions"
                variant="bordered"
                :disabled="!!program"
                :aria-label="t('admin.promotions.form.typeLabel')"
                @update:model-value="draft.type = $event as PromotionProgramType"
              />
            </div>
            <div class="form-group">
              <span class="form-label">{{ t('admin.promotions.form.kindLabel') }}</span>
              <SelectDropdown
                :model-value="draft.kind"
                :options="kindOptions"
                variant="bordered"
                :disabled="!!program"
                :aria-label="t('admin.promotions.form.kindLabel')"
                @update:model-value="draft.kind = $event as PromotionDiscountKind"
              />
            </div>
          </div>
          <div class="form-row">
            <label v-if="draft.kind === 'percent'" class="form-group">
              <span class="form-label">{{ t('admin.promotions.form.percentLabel') }}</span>
              <input
                v-model.number="draft.percent"
                type="number"
                class="form-input"
                name="percent"
                min="0.01"
                max="100"
                step="0.01"
                required
              />
              <span class="form-hint">{{ t('admin.promotions.form.percentHint') }}</span>
              <span v-for="m in errorsOf('percent')" :key="m" class="form-error">{{ m }}</span>
            </label>
            <label v-else class="form-group">
              <span class="form-label">{{ t('admin.promotions.form.amountLabel') }}</span>
              <CurrencyInput v-model="draft.amount" />
              <span v-for="m in errorsOf('amount')" :key="m" class="form-error">{{ m }}</span>
            </label>
            <label v-if="draft.kind === 'percent'" class="form-group">
              <span class="form-label">{{ t('admin.promotions.form.maxDiscountLabel') }}</span>
              <CurrencyInput v-model="draft.maxDiscount" />
              <span class="form-hint">{{ t('admin.promotions.form.optionalHint') }}</span>
              <span v-for="m in errorsOf('max_discount')" :key="m" class="form-error">{{ m }}</span>
            </label>
            <label class="form-group">
              <span class="form-label">{{ t('admin.promotions.minRentalLabel') }}</span>
              <CurrencyInput v-model="draft.minRental" />
              <span class="form-hint">{{ t('admin.promotions.form.optionalHint') }}</span>
              <span v-for="m in errorsOf('min_rental')" :key="m" class="form-error">{{ m }}</span>
            </label>
          </div>
          <div class="form-row">
            <div class="form-group">
              <span class="form-label">{{ t('admin.promotions.form.startsAtLabel') }}</span>
              <DateTimePicker v-model="draft.startsAt" />
              <span v-for="m in errorsOf('starts_at')" :key="m" class="form-error">{{ m }}</span>
            </div>
            <div class="form-group">
              <span class="form-label">{{ t('admin.promotions.form.endsAtLabel') }}</span>
              <DateTimePicker v-model="draft.endsAt" />
              <span v-for="m in errorsOf('ends_at')" :key="m" class="form-error">{{ m }}</span>
            </div>
          </div>
          <fieldset class="form-group court-picker">
            <legend class="form-label">{{ t('admin.promotions.courtsLabel') }}</legend>
            <p v-if="courts.length === 0" class="form-hint">
              {{ t('admin.promotions.form.noCourts') }}
            </p>
            <label v-for="court in courts" :key="court.id" class="court-option">
              <input v-model="draft.courtIds" type="checkbox" :value="court.id" />
              <span>{{ resolveLocalizedText(court.name) }}</span>
            </label>
            <span v-for="m in errorsOf('court_ids')" :key="m" class="form-error">{{ m }}</span>
          </fieldset>
          <label v-if="draft.type === 'public'" class="form-group">
            <span class="form-label">{{ t('admin.promotions.maxUsesLabel') }}</span>
            <input
              v-model.number="draft.maxUses"
              type="number"
              class="form-input"
              name="max_uses"
              min="1"
              max="1000000"
              step="1"
              required
            />
            <span v-if="program" class="form-hint">{{
              t('admin.promotions.form.maxUsesHint', {
                count: program.usage.held + program.usage.used,
              })
            }}</span>
            <span v-for="m in errorsOf('max_uses')" :key="m" class="form-error">{{ m }}</span>
          </label>
          <label v-else class="form-group">
            <span class="form-label">{{ t('admin.promotions.codeCountLabel') }}</span>
            <input
              v-model.number="draft.codeCount"
              type="number"
              class="form-input"
              name="code_count"
              min="1"
              max="5000"
              step="1"
              :disabled="!!program"
              required
            />
          </label>
        </template>
      </div>

      <div class="modal-footer">
        <AuraButton variant="secondary" @click="reviewing ? (reviewing = false) : emit('close')">
          {{ t(reviewing ? 'admin.promotions.review.back' : 'admin.promotions.form.cancel') }}
        </AuraButton>
        <AuraButton
          type="submit"
          variant="primary"
          :loading="saving"
          :disabled="!ready || confirmLocked"
        >
          {{
            t(
              program
                ? 'admin.promotions.form.save'
                : reviewing
                  ? 'admin.promotions.review.confirm'
                  : 'admin.promotions.form.next',
            )
          }}
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0 12px;
}

.court-picker {
  margin: 0 0 16px;
  padding: 0;
  border: 0;
}

.court-option {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  cursor: pointer;
}
</style>
