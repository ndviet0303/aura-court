<script setup lang="ts">
import { computed, ref, toRefs, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import type { Court, Venue } from '@/features/court/types/court'
import type { Coupon } from '@/features/booking/types/booking'

const props = withDefaults(
  defineProps<{
    show: boolean
    coupons: Coupon[]
    venues?: Venue[]
    courts?: Court[]
    saving?: boolean
  }>(),
  {
    venues: () => [],
    courts: () => [],
  },
)
const { show, coupons, venues, courts, saving } = toRefs(props)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'createCoupon', payload: CouponFormPayload): void
  (e: 'updateCoupon', id: number, payload: CouponFormPayload): void
  (e: 'transitionCoupon', coupon: Coupon, action: 'activate' | 'suspend' | 'retire'): void
  (e: 'transitionCampaign', id: number, action: 'activate' | 'suspend' | 'retire'): void
}>()

const { t } = useI18n()

type CouponFormPayload = {
  code: string
  discount_type: 'fixed' | 'percent'
  discount_value: number
  max_discount: number | null
  min_spend: number
  usage_limit: number | null
  starts_at: string | null
  ends_at: string | null
  can_combine_with_membership: boolean
  campaign_name: string
  campaign_description: string
  campaign_starts_at: string | null
  campaign_ends_at: string | null
  campaign_usage_limit: number | null
  campaign_min_spend: number
  campaign_can_combine_with_membership: boolean
  eligibility_group: 'all_customers' | 'first_time_customers' | 'returning_customers'
  venue_id: number | null
  court_id: number | null
  campaign_venue_id: number | null
  campaign_court_id: number | null
}

const editingCoupon = ref<Coupon | null>(null)
const code = ref('')
const campaignName = ref('')
const campaignDescription = ref('')
const eligibilityGroup = ref<CouponFormPayload['eligibility_group']>('all_customers')
const discountType = ref<'percent' | 'fixed'>('percent')
const discountValue = ref(15)
const maxDiscount = ref<number | null>(null)
const minSpend = ref(0)
const usageLimit = ref<number | null>(50)
const startsAt = ref('')
const endsAt = ref('')
const canCombineWithMembership = ref(true)
const venueScopeId = ref<number | null>(null)
const courtScopeId = ref<number | null>(null)
const scopedCourts = computed(() =>
  props.courts.filter(
    (court) => !venueScopeId.value || (court.venue_id ?? court.venue?.id) === venueScopeId.value,
  ),
)

watch(venueScopeId, () => {
  if (courtScopeId.value && !scopedCourts.value.some((court) => court.id === courtScopeId.value)) {
    courtScopeId.value = null
  }
})

function toLocalDateTime(value?: string | null): string {
  if (!value) return ''
  const date = new Date(value)
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
}

function toIsoDateTime(value: string): string | null {
  return value ? new Date(value).toISOString() : null
}

function beginEdit(coupon: Coupon) {
  editingCoupon.value = coupon
  code.value = coupon.code
  campaignName.value = coupon.campaign?.name || coupon.code
  campaignDescription.value = coupon.campaign?.description || ''
  eligibilityGroup.value = (coupon.campaign?.eligibility_group ||
    coupon.eligibility_group ||
    'all_customers') as CouponFormPayload['eligibility_group']
  discountType.value = coupon.discount_type
  discountValue.value = coupon.discount_value
  maxDiscount.value = coupon.max_discount ?? null
  minSpend.value = coupon.min_spend ?? coupon.campaign?.min_spend ?? 0
  usageLimit.value = coupon.usage_limit ?? coupon.campaign?.usage_limit ?? null
  startsAt.value = toLocalDateTime(coupon.campaign?.starts_at || coupon.starts_at)
  endsAt.value = toLocalDateTime(coupon.campaign?.ends_at || coupon.ends_at)
  canCombineWithMembership.value = coupon.campaign?.can_combine_with_membership ?? true
  venueScopeId.value = coupon.campaign?.venue_id ?? coupon.venue_id ?? null
  courtScopeId.value = coupon.campaign?.court_id ?? coupon.court_id ?? null
}

function handleSave() {
  if (!code.value.trim()) return

  const payload: CouponFormPayload = {
    code: code.value.toUpperCase().trim(),
    discount_type: discountType.value,
    discount_value: discountValue.value,
    max_discount: maxDiscount.value,
    min_spend: minSpend.value,
    usage_limit: usageLimit.value,
    starts_at: toIsoDateTime(startsAt.value),
    ends_at: toIsoDateTime(endsAt.value),
    can_combine_with_membership: canCombineWithMembership.value,
    campaign_name: campaignName.value.trim() || code.value.toUpperCase().trim(),
    campaign_description: campaignDescription.value,
    campaign_starts_at: toIsoDateTime(startsAt.value),
    campaign_ends_at: toIsoDateTime(endsAt.value),
    campaign_usage_limit: usageLimit.value,
    campaign_min_spend: minSpend.value,
    campaign_can_combine_with_membership: canCombineWithMembership.value,
    eligibility_group: eligibilityGroup.value,
    venue_id: venueScopeId.value,
    court_id: courtScopeId.value,
    campaign_venue_id: venueScopeId.value,
    campaign_court_id: courtScopeId.value,
  }

  if (editingCoupon.value) {
    emit('updateCoupon', editingCoupon.value.id, payload)
  } else {
    emit('createCoupon', payload)
  }
}

function cancelEdit() {
  editingCoupon.value = null
  code.value = ''
  campaignName.value = ''
  campaignDescription.value = ''
  eligibilityGroup.value = 'all_customers'
  discountType.value = 'percent'
  discountValue.value = 15
  maxDiscount.value = null
  minSpend.value = 0
  usageLimit.value = 50
  startsAt.value = ''
  endsAt.value = ''
  canCombineWithMembership.value = true
  venueScopeId.value = null
  courtScopeId.value = null
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">confirmation_number</span>
          <h3 class="modal-title">{{ t('admin.manage.couponModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Promotion campaign and coupon code form -->
        <div class="coupon-form-box">
          <div class="form-heading-row">
            <h4 class="form-sub-title">
              {{ editingCoupon ? t('admin.manage.editCoupon') : t('admin.manage.createNewCoupon') }}
            </h4>
            <button v-if="editingCoupon" type="button" class="btn-cancel-edit" @click="cancelEdit">
              {{ t('common.cancel') }}
            </button>
          </div>
          <div class="coupon-inputs-grid">
            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.campaignNameLabel') }}</label>
              <input
                v-model="campaignName"
                type="text"
                class="form-input"
                maxlength="120"
                data-testid="promotion-campaign-name"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.couponCodeLabel') }}</label>
              <input
                v-model="code"
                type="text"
                class="form-input font-mono"
                :placeholder="t('admin.manage.couponCodePlaceholder')"
                data-testid="promotion-coupon-code"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.discountTypeLabel') }}</label>
              <select v-model="discountType" class="form-input">
                <option value="percent">{{ t('admin.manage.discountPercentOption') }}</option>
                <option value="fixed">{{ t('admin.manage.discountFixedOption') }}</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.targetGroupLabel') }}</label>
              <select
                v-model="eligibilityGroup"
                class="form-input"
                data-testid="promotion-customer-group"
              >
                <option value="all_customers">{{ t('admin.manage.groupAllCustomers') }}</option>
                <option value="first_time_customers">
                  {{ t('admin.manage.groupFirstTimeCustomers') }}
                </option>
                <option value="returning_customers">
                  {{ t('admin.manage.groupReturningCustomers') }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.promotionVenueScopeLabel') }}</label>
              <select
                v-model.number="venueScopeId"
                class="form-input"
                data-testid="promotion-venue-scope"
              >
                <option :value="null">{{ t('admin.manage.promotionAllVenues') }}</option>
                <option v-for="venue in venues" :key="venue.id" :value="venue.id">
                  {{ resolveLocalizedText(venue.name) }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.promotionCourtScopeLabel') }}</label>
              <select
                v-model.number="courtScopeId"
                class="form-input"
                :disabled="!venueScopeId"
                data-testid="promotion-court-scope"
              >
                <option :value="null">{{ t('admin.manage.promotionAllCourts') }}</option>
                <option v-for="court in scopedCourts" :key="court.id" :value="court.id">
                  {{ resolveLocalizedText(court.name) }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.discountValueLabel') }}</label>
              <input v-model.number="discountValue" type="number" class="form-input font-mono" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.maxDiscountLabel') }}</label>
              <input
                v-model.number="maxDiscount"
                type="number"
                min="0"
                class="form-input font-mono"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.minSpendLabel') }}</label>
              <input v-model.number="minSpend" type="number" min="0" class="form-input font-mono" />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.usageLimitLabel') }}</label>
              <input
                v-model.number="usageLimit"
                type="number"
                min="1"
                class="form-input font-mono"
                data-testid="promotion-usage-limit"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.startsAtLabel') }}</label>
              <input
                v-model="startsAt"
                type="datetime-local"
                class="form-input"
                data-testid="promotion-starts-at"
              />
            </div>

            <div class="form-group">
              <label class="form-label">{{ t('admin.manage.endsAtLabel') }}</label>
              <input
                v-model="endsAt"
                type="datetime-local"
                class="form-input"
                data-testid="promotion-ends-at"
              />
            </div>

            <label class="coupon-combine-toggle">
              <input v-model="canCombineWithMembership" type="checkbox" />
              <span>{{ t('admin.manage.canCombineWithMembership') }}</span>
            </label>

            <div class="form-group span-2">
              <label class="form-label">{{ t('admin.manage.campaignDescriptionLabel') }}</label>
              <textarea
                v-model="campaignDescription"
                class="form-input"
                rows="2"
                maxlength="4000"
              />
            </div>

            <button
              type="button"
              class="btn-create-coupon"
              :disabled="!code.trim() || saving"
              data-testid="promotion-save"
              @click="handleSave"
            >
              <span class="material-symbols-outlined icon-16">{{
                editingCoupon ? 'save' : 'add'
              }}</span>
              <span>{{
                editingCoupon
                  ? t('admin.manage.updateCouponBtn')
                  : t('admin.manage.createCouponBtn')
              }}</span>
            </button>
          </div>
        </div>

        <!-- Coupons Table List -->
        <div class="coupons-table-wrap">
          <h4 class="form-sub-title">
            {{ t('admin.manage.promotionCodesList', { count: coupons.length }) }}
          </h4>
          <div class="coupons-list">
            <div v-for="c in coupons" :key="c.id" class="coupon-item-row">
              <div class="coupon-identity">
                <strong>{{ c.campaign?.name || c.code }}</strong>
                <span class="coupon-code-badge font-mono">{{ c.code }}</span>
              </div>
              <div class="coupon-meta">
                <strong v-if="c.discount_type === 'percent'">{{
                  t('admin.manage.discountPercentText', { value: c.discount_value })
                }}</strong>
                <strong v-else>{{
                  t('admin.manage.discountFixedText', { value: formatCurrency(c.discount_value) })
                }}</strong>
                <span class="font-mono">{{
                  t('admin.manage.usedCountText', {
                    used: c.usage_count ?? 0,
                    limit: c.usage_limit || '∞',
                  })
                }}</span>
                <span v-if="c.campaign?.eligibility_group">{{
                  t(`admin.manage.group.${c.campaign.eligibility_group}`)
                }}</span>
              </div>
              <span
                class="coupon-status"
                :class="`status-${c.status || (c.is_active ? 'active' : 'suspended')}`"
              >
                {{
                  t(
                    `admin.manage.promotionStatus.${c.status || (c.is_active ? 'active' : 'suspended')}`,
                  )
                }}
              </span>
              <span v-if="c.campaign" class="coupon-status" :class="`status-${c.campaign.status}`">
                {{ t('admin.manage.campaignStatusLabel') }}:
                {{ t(`admin.manage.promotionStatus.${c.campaign.status}`) }}
              </span>
              <div class="coupon-actions">
                <button
                  type="button"
                  class="btn-cancel-edit"
                  :disabled="saving || c.status === 'retired'"
                  :data-testid="`promotion-edit-${c.id}`"
                  @click="beginEdit(c)"
                >
                  {{ t('common.edit') }}
                </button>
                <button
                  v-if="c.status !== 'active' && c.status !== 'retired'"
                  type="button"
                  class="btn-cancel-edit"
                  :disabled="saving"
                  :data-testid="`promotion-activate-coupon-${c.id}`"
                  @click="emit('transitionCoupon', c, 'activate')"
                >
                  {{ t('admin.manage.activatePromotion') }}
                </button>
                <button
                  v-if="c.status === 'active'"
                  type="button"
                  class="btn-cancel-edit"
                  :disabled="saving"
                  :data-testid="`promotion-suspend-coupon-${c.id}`"
                  @click="emit('transitionCoupon', c, 'suspend')"
                >
                  {{ t('admin.manage.suspendPromotion') }}
                </button>
                <button
                  v-if="c.status !== 'retired'"
                  type="button"
                  class="btn-cancel-edit"
                  :disabled="saving"
                  :data-testid="`promotion-retire-coupon-${c.id}`"
                  @click="emit('transitionCoupon', c, 'retire')"
                >
                  {{ t('admin.manage.retirePromotion') }}
                </button>
                <button
                  v-if="
                    c.campaign && c.campaign.status !== 'active' && c.campaign.status !== 'retired'
                  "
                  type="button"
                  class="btn-cancel-edit"
                  :disabled="saving"
                  :data-testid="`promotion-activate-campaign-${c.campaign.id}`"
                  @click="emit('transitionCampaign', c.campaign.id, 'activate')"
                >
                  {{ t('admin.manage.activateCampaign') }}
                </button>
                <button
                  v-if="c.campaign?.status === 'active'"
                  type="button"
                  class="btn-cancel-edit"
                  :disabled="saving"
                  :data-testid="`promotion-suspend-campaign-${c.campaign.id}`"
                  @click="emit('transitionCampaign', c.campaign.id, 'suspend')"
                >
                  {{ t('admin.manage.suspendCampaign') }}
                </button>
                <button
                  v-if="c.campaign && c.campaign.status !== 'retired'"
                  type="button"
                  class="btn-cancel-edit"
                  :disabled="saving"
                  :data-testid="`promotion-retire-campaign-${c.campaign.id}`"
                  @click="emit('transitionCampaign', c.campaign.id, 'retire')"
                >
                  {{ t('admin.manage.retireCampaign') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('common.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.coupon-form-box {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.form-heading-row,
.coupon-identity,
.coupon-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-heading-row {
  justify-content: space-between;
}

.form-sub-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
}

.coupon-inputs-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  align-items: end;
}

.coupon-combine-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.btn-create-coupon {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.coupons-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 250px;
  overflow-y: auto;
}

.coupon-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.coupon-code-badge {
  font-weight: 800;
  font-size: 13px;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 4px 10px;
  border-radius: 6px;
}

.coupon-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.coupon-identity {
  flex-wrap: wrap;
}

.coupon-status {
  font-size: 11px;
  font-weight: 700;
}

.status-active {
  color: #10b981;
}
.status-draft {
  color: #64748b;
}
.status-suspended {
  color: #d97706;
}
.status-retired {
  color: #6b7280;
}

.coupon-actions {
  flex-wrap: wrap;
}

.btn-cancel-edit {
  border: 1px solid var(--outline);
  border-radius: 8px;
  background: transparent;
  color: var(--ink);
  padding: 6px 9px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.btn-cancel-edit:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.coupon-meta span {
  color: var(--muted);
  font-size: 11px;
}

.status-dot-active {
  color: #10b981;
  font-size: 11px;
  font-weight: 700;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}
</style>
