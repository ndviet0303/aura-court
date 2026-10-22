import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import type {
  PromotionDiscountKind,
  PromotionProgram,
  PromotionProgramStatus,
  PromotionProgramType,
  VoucherCodeState,
} from '../types/promotionProgram'

type DiscountTerms = Pick<PromotionProgram, 'discount_kind' | 'percent' | 'amount' | 'max_discount'>

/** Locale-aware labels for promotion programs. */
export function usePromotionProgramLabels() {
  const { t, d, locale } = useI18n()

  const instant = (iso: string) => d(new Date(iso), 'datetime')
  const type = (value: PromotionProgramType) => t(`admin.promotions.type.${value}`)
  const kind = (value: PromotionDiscountKind) => t(`admin.promotions.kind.${value}`)
  const status = (value: PromotionProgramStatus) => t(`admin.promotions.status.${value}`)
  const codeState = (value: VoucherCodeState) => t(`admin.promotions.codes.state.${value}`)
  const percent = (value: number) =>
    `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(value)}%`
  const discount = (terms: DiscountTerms) => {
    if (terms.discount_kind === 'fixed') return formatCurrency(terms.amount ?? 0)
    const value = percent(terms.percent ?? 0)
    return terms.max_discount
      ? t('admin.promotions.percentCapped', { value, max: formatCurrency(terms.max_discount) })
      : value
  }
  const period = (window: { starts_at: string; ends_at: string }) =>
    t('admin.promotions.period', { from: instant(window.starts_at), to: instant(window.ends_at) })
  const courtName = (name: string) => resolveLocalizedText(name)

  return {
    instant,
    type,
    kind,
    status,
    codeState,
    discount,
    period,
    courtName,
    money: formatCurrency,
  }
}
