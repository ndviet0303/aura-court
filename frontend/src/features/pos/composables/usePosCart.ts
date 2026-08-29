import { ref, computed } from 'vue'
import type { CartItem, Product, CustomerProfile } from '../types/pos'

export function usePosCart() {
  const cart = ref<CartItem[]>([])
  const selectedCustomer = ref<CustomerProfile | null>(null)
  const selectedReservationId = ref<number | null>(null)
  const includeVat = ref(true)
  const vatRate = 0.08 // 8% VAT
  const customDiscountPercent = ref(0)
  const paymentMethod = ref<'cash' | 'qr' | 'wallet' | 'court_tab'>('cash')
  const cashTendered = ref<number>(0)

  const discountPercent = computed(() => {
    if (selectedCustomer.value && selectedCustomer.value.discount_rate > 0) {
      return Math.max(customDiscountPercent.value, selectedCustomer.value.discount_rate * 100)
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
    return Math.round((subtotal.value * discountPercent.value) / 100)
  })

  const taxableAmount = computed(() => {
    return Math.max(0, subtotal.value - discountAmount.value)
  })

  const taxAmount = computed(() => {
    return includeVat.value ? Math.round(taxableAmount.value * vatRate) : 0
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
    const round50 = Math.ceil(tot / 50000) * 50000
    const round100 = Math.ceil(tot / 100000) * 100000
    const round200 = Math.ceil(tot / 200000) * 200000
    const round500 = Math.ceil(tot / 500000) * 500000
    const set = new Set([exact, round50, round100, round200, round500])
    return Array.from(set).filter((v) => v >= tot).slice(0, 4)
  })

  const cashChange = computed(() => {
    if (paymentMethod.value !== 'cash') return 0
    return Math.max(0, (cashTendered.value || 0) - grandTotal.value)
  })

  function addToCart(product: Product, quantity = 1, rentalHours?: number) {
    const existingIndex = cart.value.findIndex(
      (item) => item.product.id === product.id && (item.rental_hours || 1) === (rentalHours || 1)
    )

    if (existingIndex >= 0 && cart.value[existingIndex]) {
      cart.value[existingIndex]!.quantity += quantity
    } else {
      cart.value.push({
        product,
        quantity,
        rental_hours: product.is_rental ? rentalHours || 1 : undefined,
      })
    }
  }

  function removeFromCart(index: number) {
    if (index >= 0 && index < cart.value.length) {
      cart.value.splice(index, 1)
    }
  }

  function updateQuantity(index: number, delta: number) {
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
    const item = cart.value[index]
    if (!item || !item.rental_hours) return
    const newHours = item.rental_hours + delta
    if (newHours >= 1) {
      item.rental_hours = newHours
    }
  }

  function clearCart() {
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
    includeVat,
    vatRate,
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
    removeFromCart,
    updateQuantity,
    updateRentalHours,
    clearCart,
  }
}
