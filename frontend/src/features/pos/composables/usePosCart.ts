import { ref, computed } from 'vue'
import type {
  CartItem,
  Product,
  CustomerProfile,
  PosPaymentMethod,
  VatBreakdown,
} from '../types/pos'

export function usePosCart() {
  const cart = ref<CartItem[]>([])
  const selectedCustomer = ref<CustomerProfile | null>(null)
  const selectedReservationId = ref<number | null>(null)
  const customDiscountPercent = ref(0)
  const paymentMethod = ref<PosPaymentMethod>('cash')
  const cashTendered = ref<number>(0)
  const checkoutKey = ref<string | null>(null)

  function getCheckoutKey(): string {
    if (!checkoutKey.value) {
      checkoutKey.value = `pos-chk-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`
    }
    return checkoutKey.value
  }

  function resetCheckoutKey() {
    checkoutKey.value = null
  }
  const discountPercent = computed(() => {
    if (selectedCustomer.value && selectedCustomer.value.discount_rate > 0) {
      return Math.max(
        customDiscountPercent.value,
        Math.round(selectedCustomer.value.discount_rate * 100),
      )
    }
    return customDiscountPercent.value
  })

  const subtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
      const hours = item.rental_hours || 1
      return sum + item.product.price * item.quantity * hours
    }, 0)
  })

  const discountAmount = computed(() => {
    if (discountPercent.value <= 0) return 0
    return Math.round((subtotal.value * discountPercent.value) / 100)
  })

  const taxableAmount = computed(() => {
    return Math.max(0, subtotal.value - discountAmount.value)
  })

  // Per-item VAT breakdown calculation
  const vatBreakdown = computed<VatBreakdown[]>(() => {
    const ratio = subtotal.value > 0 ? 1 - discountPercent.value / 100 : 1
    const map: Record<number, number> = {}

    cart.value.forEach((item) => {
      const hours = item.rental_hours || 1
      const lineTotal = item.product.price * item.quantity * hours
      const lineAfterDiscount = lineTotal * ratio
      const rate = item.vat_rate !== undefined ? item.vat_rate : (item.product.vat_rate ?? 0.08)
      if (rate > 0) {
        const tax = Math.round(lineAfterDiscount * rate)
        map[rate] = (map[rate] || 0) + tax
      }
    })

    return Object.entries(map)
      .map(([rateStr, amount]) => ({
        rate: Number(rateStr),
        percent: Math.round(Number(rateStr) * 100),
        amount,
      }))
      .sort((a, b) => a.percent - b.percent)
  })

  const taxAmount = computed(() => {
    return vatBreakdown.value.reduce((sum, b) => sum + b.amount, 0)
  })

  const grandTotal = computed(() => {
    return taxableAmount.value + taxAmount.value
  })

  const totalItems = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0)
  })

  const quickCashSuggestions = computed(() => {
    const tot = grandTotal.value
    if (tot <= 0) return [20000, 50000, 100000, 200000, 500000]
    const exact = tot
    const round20 = Math.ceil(tot / 20000) * 20000
    const round50 = Math.ceil(tot / 50000) * 50000
    const round100 = Math.ceil(tot / 100000) * 100000
    const round200 = Math.ceil(tot / 200000) * 200000
    const round500 = Math.ceil(tot / 500000) * 500000
    const set = new Set([exact, round20, round50, round100, round200, round500])
    return Array.from(set)
      .filter((v) => v >= tot)
      .slice(0, 5)
  })

  const cashChange = computed(() => {
    if (paymentMethod.value !== 'cash') return 0
    return Math.max(0, (cashTendered.value || 0) - grandTotal.value)
  })

  function getItemDefaultVat(product: Product): number {
    if (typeof product.vat_rate === 'number') return product.vat_rate
    if (product.category === 'equipment' || product.category === 'membership') return 0.1
    if (product.category === 'drinks' || product.category === 'nutrition') return 0.08
    if (product.category === 'rental' || product.category === 'services') return 0.08
    return 0.08
  }

  function addToCart(product: Product, quantity = 1, rentalHours?: number, vatRate?: number) {
    resetCheckoutKey()
    const itemVat = vatRate !== undefined ? vatRate : getItemDefaultVat(product)
    const existingIndex = cart.value.findIndex(
      (item) =>
        item.product.id === product.id &&
        (item.rental_hours || 1) === (rentalHours || 1) &&
        (item.vat_rate ?? getItemDefaultVat(item.product)) === itemVat,
    )

    if (existingIndex >= 0 && cart.value[existingIndex]) {
      cart.value[existingIndex]!.quantity += quantity
    } else {
      cart.value.push({
        product,
        quantity,
        rental_hours: product.is_rental ? rentalHours || 1 : undefined,
        vat_rate: itemVat,
      })
    }
  }

  function updateItemVatRate(index: number, newVatRate: number) {
    resetCheckoutKey()
    const item = cart.value[index]
    if (item) {
      item.vat_rate = newVatRate
    }
  }

  function removeFromCart(index: number) {
    resetCheckoutKey()
    if (index >= 0 && index < cart.value.length) {
      cart.value.splice(index, 1)
    }
  }

  function updateQuantity(index: number, delta: number) {
    resetCheckoutKey()
    const item = cart.value[index]
    if (!item) return
    const newQty = item.quantity + delta
    if (newQty <= 0) {
      removeFromCart(index)
    } else {
      item.quantity = newQty
    }
  }

  function updateRentalHours(index: number, delta: number) {
    resetCheckoutKey()
    const item = cart.value[index]
    if (!item || !item.rental_hours) return
    const newHours = item.rental_hours + delta
    if (newHours >= 1) {
      item.rental_hours = newHours
    }
  }

  function clearCart() {
    resetCheckoutKey()
    cart.value = []
    selectedCustomer.value = null
    selectedReservationId.value = null
    cashTendered.value = 0
    customDiscountPercent.value = 0
    paymentMethod.value = 'cash'
  }

  return {
    cart,
    selectedCustomer,
    selectedReservationId,
    vatBreakdown,
    customDiscountPercent,
    discountPercent,
    paymentMethod,
    cashTendered,
    subtotal,
    discountAmount,
    taxAmount,
    grandTotal,
    totalItems,
    quickCashSuggestions,
    cashChange,
    addToCart,
    updateItemVatRate,
    removeFromCart,
    updateQuantity,
    updateRentalHours,
    clearCart,
    getCheckoutKey,
    resetCheckoutKey,
  }
}
