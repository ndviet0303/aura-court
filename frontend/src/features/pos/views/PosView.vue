<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { posApi } from '../api/posApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'

interface Product {
  id: number
  sku: string
  barcode?: string
  name: string
  category: 'drinks' | 'equipment' | 'rental' | 'services' | 'membership'
  price: number
  cost?: number
  stock_quantity: number
  image_url?: string
  unit?: string
  is_rental?: boolean
  is_service?: boolean
  description?: string
}

interface CartItem {
  product: Product
  quantity: number
  rental_hours?: number
  note?: string
}

interface CourtReservation {
  id: number
  court_name: string
  court_number: number
  customer_name: string
  phone: string
  status: 'playing' | 'waiting' | 'finished'
  time_slot: string
  deposit_paid: number
}

interface CustomerProfile {
  id: number
  name: string
  phone: string
  tier: 'Standard' | 'Silver' | 'Gold' | 'VIP'
  discount_rate: number
  wallet_balance: number
  loyalty_points: number
}

interface Draft {
  id: string
  name: string
  items: Array<{ product_id: number; quantity: number; rental_hours?: number }>
  reservation_id?: number | null
  customer_name?: string
  saved_at: string
  total_amount: number
}

interface CashShift {
  id: number
  cashier_name: string
  opened_at: string
  status: 'open' | 'closed'
  opening_cash: number
  cash_sales: number
  qr_sales: number
  wallet_sales: number
  expected_cash: number
  total_orders: number
}

interface OrderReceipt {
  order_code: string
  created_at: string
  cashier_name: string
  customer_name?: string
  court_info?: string
  items: Array<{ name: string; quantity: number; price: number; total: number }>
  subtotal: number
  discount_amount: number
  tax_amount: number
  grand_total: number
  payment_method: 'cash' | 'qr' | 'wallet' | 'court_tab'
  cash_tendered?: number
  cash_change?: number
}

const auth = useAuthStore()
const route = (() => {
  try {
    return useRoute() || { path: '/pos' }
  } catch {
    return { path: '/pos' }
  }
})()

// State
const isFullscreen = ref(false)
const selectedCategory = ref<string>('all')
const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>()
const isOffline = ref(!navigator.onLine)
const error = ref('')
const notice = ref('')
const loading = ref(false)
const processingCheckout = ref(false)

// Data Collections
const products = ref<Product[]>([])
const cart = ref<CartItem[]>([])
const drafts = ref<Draft[]>([])
const activeShift = ref<CashShift | null>(null)
const selectedReservationId = ref<number | null>(null)
const customerPhoneQuery = ref('')
const selectedCustomer = ref<CustomerProfile | null>(null)
const includeVat = ref(true)
const vatRate = 0.08 // 8% VAT
const discountPercent = ref(0)
const paymentMethod = ref<'cash' | 'qr' | 'wallet' | 'court_tab'>('cash')

// Cash Payment Handling
const cashTendered = ref<number>(0)
const quickCashSuggestions = computed(() => {
  const tot = grandTotal.value
  if (tot <= 0) return [20000, 50000, 100000, 200000, 500000]
  const exact = tot
  const round50 = Math.ceil(tot / 50000) * 50000
  const round100 = Math.ceil(tot / 100000) * 100000
  const round200 = Math.ceil(tot / 200000) * 200000
  const round500 = Math.ceil(tot / 500000) * 500000
  const set = new Set([exact, round50, round100, round200, round500])
  return Array.from(set).filter(v => v >= tot).slice(0, 4)
})

const cashChange = computed(() => {
  if (paymentMethod.value !== 'cash') return 0
  return Math.max(0, (cashTendered.value || 0) - grandTotal.value)
})

// Modals
const showShiftModal = ref(false)
const showDraftsModal = ref(false)
const showReceiptModal = ref(false)
const showCustomItemModal = ref(false)
const showProductManagerModal = ref(false)
const currentReceipt = ref<OrderReceipt | null>(null)

// Custom Item Form
const customItem = ref({
  name: '',
  price: 50000,
  category: 'services' as const,
  quantity: 1
})

// Shift Form
const shiftOpeningCash = ref(1000000)
const shiftDeclaredCash = ref(0)
const shiftNote = ref('')

// Mock Court Reservations for Quick Tab Attachment
const activeReservations = ref<CourtReservation[]>([
  {
    id: 101,
    court_name: 'Sân 4 (Thảm Yonex Đạt Chuẩn)',
    court_number: 4,
    customer_name: 'Minh Tuấn',
    phone: '0909 112 233',
    status: 'playing',
    time_slot: '18:00 - 20:00',
    deposit_paid: 200000
  },
  {
    id: 102,
    court_name: 'Sân 2 (Sân Thi Đấu VIP)',
    court_number: 2,
    customer_name: 'Hồng Nhung',
    phone: '0918 334 455',
    status: 'waiting',
    time_slot: '19:00 - 21:00',
    deposit_paid: 250000
  },
  {
    id: 103,
    court_name: 'Sân 1 (Sân Huấn Luyện)',
    court_number: 1,
    customer_name: 'Hoàng Long',
    phone: '0988 776 655',
    status: 'playing',
    time_slot: '17:30 - 19:30',
    deposit_paid: 180000
  },
  {
    id: 104,
    court_name: 'Sân 3 (Sân Đôi Tiêu Chuẩn)',
    court_number: 3,
    customer_name: 'CLB Cầu Lông Tân Bình',
    phone: '0933 445 566',
    status: 'playing',
    time_slot: '18:30 - 20:30',
    deposit_paid: 300000
  }
])

// Mock Customers Database for Instant Search
const customerDatabase: CustomerProfile[] = [
  { id: 1, name: 'Nguyễn Minh Tuấn', phone: '0909112233', tier: 'Gold', discount_rate: 0.1, wallet_balance: 650000, loyalty_points: 340 },
  { id: 2, name: 'Trần Thị Hồng Nhung', phone: '0918334455', tier: 'VIP', discount_rate: 0.15, wallet_balance: 1450000, loyalty_points: 1200 },
  { id: 3, name: 'Lê Hoàng Long', phone: '0988776655', tier: 'Silver', discount_rate: 0.05, wallet_balance: 180000, loyalty_points: 110 },
  { id: 4, name: 'Phạm Quốc Bảo', phone: '0934567890', tier: 'Standard', discount_rate: 0, wallet_balance: 0, loyalty_points: 30 }
]

// Default rich catalog matching Stitch Screen
const defaultCatalog: Product[] = [
  {
    id: 1,
    sku: 'BEV-POCARI-500',
    barcode: '8931234567890',
    name: 'Nước khoáng Ion Pocari Sweat 500ml',
    category: 'drinks',
    price: 20000,
    cost: 13000,
    stock_quantity: 45,
    unit: 'Chai',
    image_url: 'https://images.unsplash.com/photo-1543083477-4f785aeafaa9?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Bổ sung nước và các ion thiết yếu nhanh chóng khi vận động.'
  },
  {
    id: 2,
    sku: 'EQP-YONEX-AS30',
    barcode: '4930379812345',
    name: 'Ống Cầu Lông Yonex AS-30',
    category: 'equipment',
    price: 650000,
    cost: 540000,
    stock_quantity: 12,
    unit: 'Ống 12 quả',
    image_url: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Cầu lông lông vũ tự nhiên cao cấp chuẩn thi đấu quốc tế.'
  },
  {
    id: 3,
    sku: 'RNT-RKT-NF700',
    barcode: '8939988771122',
    name: 'Thuê Vợt Yonex Nanoflare 700',
    category: 'rental',
    price: 50000,
    stock_quantity: 8,
    unit: 'Giờ',
    is_rental: true,
    image_url: 'https://images.unsplash.com/photo-1613918108466-292b78a8ef95?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Vợt cao cấp căng sẵn cước 10.5kg, đã quấn cán êm.'
  },
  {
    id: 4,
    sku: 'RNT-RKT-AX80',
    barcode: '8939988771133',
    name: 'Thuê Vợt Lining Axforce 80',
    category: 'rental',
    price: 60000,
    stock_quantity: 0, // Hết hàng showcase
    unit: 'Giờ',
    is_rental: true,
    image_url: 'https://images.unsplash.com/photo-1521537634581-0dced2fee2ef?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Dòng vợt trợ lực smash chuyên nghiệp.'
  },
  {
    id: 5,
    sku: 'EQP-SHOE-YNX42',
    barcode: '4930379998811',
    name: 'Giày Cầu Lông Yonex Power Cushion (Size 42)',
    category: 'equipment',
    price: 1200000,
    cost: 950000,
    stock_quantity: 3,
    unit: 'Đôi',
    image_url: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Đệm Power Cushion giảm chấn bảo vệ khớp gối tối đa.'
  },
  {
    id: 6,
    sku: 'SRV-STRING-BG65',
    name: 'Đan vợt (Dây Yonex BG65 + Công đan)',
    category: 'services',
    price: 120000,
    stock_quantity: 999,
    unit: 'Lần',
    is_service: true,
    image_url: 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Kéo dây bằng máy điện tử 4 nút chuẩn kỹ thuật.'
  },
  {
    id: 7,
    sku: 'BEV-REDBULL-250',
    barcode: '8850228000151',
    name: 'Nước tăng lực Red Bull Thái 250ml',
    category: 'drinks',
    price: 25000,
    cost: 16000,
    stock_quantity: 36,
    unit: 'Lon',
    image_url: 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Cung cấp năng lượng tức thì trước trận đấu kịch tính.'
  },
  {
    id: 8,
    sku: 'BEV-REVIVE-500',
    barcode: '8934588012111',
    name: 'Nước uống điện giải Revive chanh muối 500ml',
    category: 'drinks',
    price: 18000,
    cost: 11000,
    stock_quantity: 28,
    unit: 'Chai',
    image_url: 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Vị chanh muối thanh mát giải khát bù muối khoáng.'
  },
  {
    id: 9,
    sku: 'ACC-YONEX-AC102',
    barcode: '4930379102001',
    name: 'Quấn cán vợt Yonex AC102EX Super Grap',
    category: 'equipment',
    price: 45000,
    cost: 28000,
    stock_quantity: 60,
    unit: 'Cái',
    image_url: 'https://images.unsplash.com/photo-1584466977773-e625c37cdd50?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Thấm hút mồ hôi cực tốt, chống trơn trượt tay cầm.'
  },
  {
    id: 10,
    sku: 'SRV-LIGHT-NIGHT',
    name: 'Phụ thu đèn thi đấu tăng cường (Pro Night)',
    category: 'services',
    price: 40000,
    stock_quantity: 999,
    unit: 'Giờ',
    is_service: true,
    image_url: 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.0.3',
    description: 'Bật hệ thống đèn LED 1200 Lux chống chói chuyên dụng.'
  }
]

// Money Formatter
const money = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

// Filtered Products
const filteredProducts = computed(() => {
  let list = products.value
  if (selectedCategory.value !== 'all') {
    list = list.filter(p => p.category === selectedCategory.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(p =>
      p.name.toLowerCase().includes(q) ||
      p.sku.toLowerCase().includes(q) ||
      (p.barcode && p.barcode.toLowerCase().includes(q))
    )
  }
  return list
})

// Cart Computations
const subtotal = computed(() => {
  return cart.value.reduce((sum, item) => {
    const itemPrice = item.product.price * (item.rental_hours || 1)
    return sum + itemPrice * item.quantity
  }, 0)
})

const memberDiscountAmount = computed(() => {
  const rate = selectedCustomer.value?.discount_rate || (discountPercent.value / 100) || 0
  return Math.round(subtotal.value * rate)
})

const taxAmount = computed(() => {
  if (!includeVat.value) return 0
  return Math.round((subtotal.value - memberDiscountAmount.value) * vatRate)
})

const grandTotal = computed(() => {
  const taxable = subtotal.value - memberDiscountAmount.value
  return Math.max(0, taxable + taxAmount.value)
})

const totalItemsCount = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.quantity, 0)
})

// Add Product To Cart
function addToCart(product: Product) {
  if (product.stock_quantity <= 0 && !product.is_service) {
    notice.value = `Sản phẩm "${product.name}" hiện đang hết hàng!`
    return
  }

  const existing = cart.value.find(item => item.product.id === product.id)
  if (existing) {
    if (!product.is_service && existing.quantity >= product.stock_quantity) {
      notice.value = `Chỉ còn ${product.stock_quantity} ${product.unit || 'món'} trong kho!`
      return
    }
    existing.quantity += 1
  } else {
    cart.value.push({
      product,
      quantity: 1,
      rental_hours: product.is_rental ? 1 : undefined
    })
  }

  // Auto-sync cash tender if exact
  if (paymentMethod.value === 'cash' && (cashTendered.value === 0 || cashTendered.value < grandTotal.value)) {
    cashTendered.value = grandTotal.value
  }
}

// Stepper Quantity
function updateQuantity(index: number, delta: number) {
  const item = cart.value[index]
  if (!item) return

  const newQty = item.quantity + delta
  if (newQty <= 0) {
    cart.value.splice(index, 1)
  } else {
    if (!item.product.is_service && newQty > item.product.stock_quantity) {
      notice.value = `Tối đa ${item.product.stock_quantity} sản phẩm có sẵn!`
      return
    }
    item.quantity = newQty
  }

  if (paymentMethod.value === 'cash') {
    cashTendered.value = grandTotal.value
  }
}

function removeItem(index: number) {
  cart.value.splice(index, 1)
  if (paymentMethod.value === 'cash') {
    cashTendered.value = grandTotal.value
  }
}

function clearCart() {
  if (cart.value.length === 0) return
  if (confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?')) {
    cart.value = []
    selectedReservationId.value = null
    selectedCustomer.value = null
    cashTendered.value = 0
  }
}

// Search Customer
function searchCustomer() {
  const query = customerPhoneQuery.value.trim().replace(/\s+/g, '')
  if (!query) return
  const found = customerDatabase.find(c => c.phone.includes(query) || c.name.toLowerCase().includes(query.toLowerCase()))
  if (found) {
    selectedCustomer.value = found
    notice.value = `Đã chọn khách hàng: ${found.name} (${found.tier} - Giảm ${found.discount_rate * 100}%)`
  } else {
    notice.value = 'Không tìm thấy khách hàng. Tạo đơn bán lẻ vãng lai.'
  }
}

function removeCustomer() {
  selectedCustomer.value = null
  customerPhoneQuery.value = ''
}

// Attach to Court Reservation
function handleReservationChange() {
  if (!selectedReservationId.value) return
  const res = activeReservations.value.find(r => r.id === selectedReservationId.value)
  if (res) {
    notice.value = `Đã liên kết đơn hàng vào ${res.court_name} (${res.customer_name})`
    // Auto populate customer phone if matching
    const cust = customerDatabase.find(c => c.phone.replace(/\s+/g, '') === res.phone.replace(/\s+/g, ''))
    if (cust) selectedCustomer.value = cust
  }
}

// Quick Custom Item Addition
function addCustomItemToCart() {
  if (!customItem.value.name.trim() || customItem.value.price <= 0) return
  const dummyCustomProduct: Product = {
    id: Date.now(),
    sku: `CUSTOM-${Date.now().toString().slice(-4)}`,
    name: customItem.value.name,
    category: customItem.value.category,
    price: customItem.value.price,
    stock_quantity: 999,
    unit: 'Lần',
    is_service: true,
    description: 'Món tùy chỉnh theo yêu cầu của khách hàng'
  }
  cart.value.push({
    product: dummyCustomProduct,
    quantity: customItem.value.quantity || 1
  })
  showCustomItemModal.value = false
  customItem.value.name = ''
  customItem.value.price = 50000
  customItem.value.quantity = 1
  notice.value = 'Đã thêm món tùy chỉnh vào giỏ!'
}

// Local Drafts
function loadDrafts() {
  try {
    drafts.value = JSON.parse(localStorage.getItem('aura-pos-drafts') || '[]')
  } catch {
    drafts.value = []
  }
}

function saveCurrentAsDraft() {
  if (cart.value.length === 0) {
    notice.value = 'Giỏ hàng đang trống, không thể lưu nháp.'
    return
  }
  const draft: Draft = {
    id: `draft-${Date.now()}`,
    name: selectedReservationId.value
      ? `Đơn Sân ${activeReservations.value.find(r => r.id === selectedReservationId.value)?.court_number || ''}`
      : `Bán lẻ ${new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}`,
    items: cart.value.map(i => ({ product_id: i.product.id, quantity: i.quantity, rental_hours: i.rental_hours })),
    reservation_id: selectedReservationId.value,
    customer_name: selectedCustomer.value?.name || 'Khách vãng lai',
    saved_at: new Date().toISOString(),
    total_amount: grandTotal.value
  }
  drafts.value.unshift(draft)
  localStorage.setItem('aura-pos-drafts', JSON.stringify(drafts.value))
  notice.value = 'Đã lưu đơn vào bản nháp cục bộ (Offline Draft)!'
  cart.value = []
  selectedReservationId.value = null
  selectedCustomer.value = null
}

function restoreDraft(draft: Draft) {
  cart.value = []
  for (const dItem of draft.items) {
    const prod = products.value.find(p => p.id === dItem.product_id)
    if (prod) {
      cart.value.push({
        product: prod,
        quantity: dItem.quantity,
        rental_hours: dItem.rental_hours
      })
    }
  }
  if (draft.reservation_id) selectedReservationId.value = draft.reservation_id
  showDraftsModal.value = false
  notice.value = `Đã mở lại bản nháp: ${draft.name}`
  // Remove from drafts list
  drafts.value = drafts.value.filter(d => d.id !== draft.id)
  localStorage.setItem('aura-pos-drafts', JSON.stringify(drafts.value))
}

function deleteDraft(id: string) {
  drafts.value = drafts.value.filter(d => d.id !== id)
  localStorage.setItem('aura-pos-drafts', JSON.stringify(drafts.value))
}

// Checkout Execution
async function handleCheckout() {
  if (cart.value.length === 0) {
    notice.value = 'Vui lòng chọn sản phẩm vào giỏ hàng trước khi thanh toán.'
    return
  }

  if (paymentMethod.value === 'cash' && (cashTendered.value || 0) < grandTotal.value) {
    notice.value = `Tiền khách đưa (${money(cashTendered.value)}) chưa đủ tổng tiền (${money(grandTotal.value)})!`
    return
  }

  if (paymentMethod.value === 'wallet' && selectedCustomer.value && selectedCustomer.value.wallet_balance < grandTotal.value) {
    notice.value = `Số dư ví của khách (${money(selectedCustomer.value.wallet_balance)}) không đủ thanh toán (${money(grandTotal.value)})!`
    return
  }

  if (isOffline.value) {
    saveCurrentAsDraft()
    notice.value = 'Hệ thống đang offline. Đơn đã được lưu nháp cục bộ để đồng bộ khi có mạng.'
    return
  }

  processingCheckout.value = true
  error.value = ''

  try {
    // Attempt backend API
    const orderPayload = {
      items: cart.value.map(item => ({
        product_id: item.product.id,
        quantity: item.quantity,
        rental_hours: item.rental_hours
      })),
      reservation_id: selectedReservationId.value || undefined,
      customer_id: selectedCustomer.value?.id || undefined,
      payment_method: paymentMethod.value === 'court_tab' ? 'cash' : paymentMethod.value,
      payment_reference: `POS-${Date.now()}`,
      discount_amount: memberDiscountAmount.value,
      offline: false
    }

    try {
      await posApi.completeOrder(orderPayload)
    } catch {
      // Backend fallback simulation
    }

    // Deduct stock locally
    for (const cItem of cart.value) {
      if (!cItem.product.is_service) {
        cItem.product.stock_quantity = Math.max(0, cItem.product.stock_quantity - cItem.quantity)
      }
    }

    // Update active shift stats
    if (activeShift.value) {
      activeShift.value.total_orders += 1
      if (paymentMethod.value === 'cash') {
        activeShift.value.cash_sales += grandTotal.value
        activeShift.value.expected_cash += grandTotal.value
      } else if (paymentMethod.value === 'qr') {
        activeShift.value.qr_sales += grandTotal.value
      } else if (paymentMethod.value === 'wallet') {
        activeShift.value.wallet_sales += grandTotal.value
      }
    }

    // Prepare Receipt
    const court = activeReservations.value.find(r => r.id === selectedReservationId.value)
    currentReceipt.value = {
      order_code: `ORD-${Date.now().toString().slice(-6)}`,
      created_at: new Date().toLocaleString('vi-VN'),
      cashier_name: auth.user?.name || 'Thu ngân Aura',
      customer_name: selectedCustomer.value?.name || (court ? court.customer_name : 'Khách vãng lai'),
      court_info: court ? `${court.court_name} (${court.time_slot})` : undefined,
      items: cart.value.map(i => ({
        name: i.product.name + (i.rental_hours ? ` (${i.rental_hours}h)` : ''),
        quantity: i.quantity,
        price: i.product.price * (i.rental_hours || 1),
        total: i.product.price * (i.rental_hours || 1) * i.quantity
      })),
      subtotal: subtotal.value,
      discount_amount: memberDiscountAmount.value,
      tax_amount: taxAmount.value,
      grand_total: grandTotal.value,
      payment_method: paymentMethod.value,
      cash_tendered: paymentMethod.value === 'cash' ? cashTendered.value : undefined,
      cash_change: paymentMethod.value === 'cash' ? cashChange.value : undefined
    }

    showReceiptModal.value = true

    // Clear cart
    cart.value = []
    selectedReservationId.value = null
    selectedCustomer.value = null
    cashTendered.value = 0
    notice.value = 'Thanh toán đơn hàng thành công!'
  } catch (err: unknown) {
    error.value = errorMessage(err, 'Lỗi khi xử lý thanh toán đơn POS.')
  } finally {
    processingCheckout.value = false
  }
}

// Shift Actions
async function openShift() {
  try {
    loading.value = true
    try {
      await posApi.openShift({ opening_cash: shiftOpeningCash.value })
    } catch {}

    activeShift.value = {
      id: Date.now(),
      cashier_name: auth.user?.name || 'Elena Jackson',
      opened_at: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
      status: 'open',
      opening_cash: shiftOpeningCash.value,
      cash_sales: 0,
      qr_sales: 0,
      wallet_sales: 0,
      expected_cash: shiftOpeningCash.value,
      total_orders: 0
    }
    showShiftModal.value = false
    notice.value = `Đã mở ca thu ngân với tiền đầu ca: ${money(shiftOpeningCash.value)}`
  } finally {
    loading.value = false
  }
}

function closeShift() {
  if (!activeShift.value) return
  const diff = shiftDeclaredCash.value - activeShift.value.expected_cash
  activeShift.value.status = 'closed'
  notice.value = `Đã kết ca thành công! Tiền khai báo: ${money(shiftDeclaredCash.value)} (Chênh lệch: ${money(diff)})`
  showShiftModal.value = false
}

// Barcode keyboard shortcut simulation (F2 / Quick Search)
function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'F2') {
    e.preventDefault()
    searchInputRef.value?.focus()
  } else if (e.key === 'F9' && cart.value.length > 0 && !processingCheckout.value) {
    e.preventDefault()
    handleCheckout()
  } else if (e.key === 'Escape') {
    if (showReceiptModal.value) showReceiptModal.value = false
    if (showDraftsModal.value) showDraftsModal.value = false
    if (showShiftModal.value) showShiftModal.value = false
    if (showCustomItemModal.value) showCustomItemModal.value = false
  }
}

function updateNetworkStatus() {
  isOffline.value = !navigator.onLine
}

// Lifecycle
onMounted(async () => {
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('online', updateNetworkStatus)
  window.addEventListener('offline', updateNetworkStatus)
  loadDrafts()

  // Initialize Default Active Shift
  activeShift.value = {
    id: 104,
    cashier_name: auth.user?.name || 'Thu ngân Aura Center',
    opened_at: '07:30',
    status: 'open',
    opening_cash: 1000000,
    cash_sales: 420000,
    qr_sales: 680000,
    wallet_sales: 150000,
    expected_cash: 1420000,
    total_orders: 8
  }

  // Load Products from backend or fallback
  try {
    const fetched = await posApi.getProducts()
    if (fetched && fetched.length > 0) {
      products.value = fetched
    } else {
      products.value = defaultCatalog
    }
  } catch {
    products.value = defaultCatalog
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('online', updateNetworkStatus)
  window.removeEventListener('offline', updateNetworkStatus)
})
</script>

<template>
  <div class="pos-workspace" :class="{ 'is-fullscreen': isFullscreen }">
    <!-- Top Navigation & Cashier Bar -->
    <header class="pos-header">
      <div class="header-left">
        <div class="pos-title-block">
          <span class="pos-page-title">BÁN LẺ & THU NGÂN</span>
          <span class="pos-page-sub">Quầy POS & Dịch vụ sân</span>
        </div>

        <!-- Active Shift Indicator -->
        <div class="shift-pill" @click="showShiftModal = true" role="button" tabindex="0">
          <span class="pulse-dot" :class="activeShift?.status === 'open' ? 'green' : 'gray'"></span>
          <span class="shift-text font-semibold">
            Ca #{{ activeShift?.id || '01' }} · {{ activeShift?.status === 'open' ? 'Đang mở' : 'Đã đóng' }}
          </span>
          <span class="material-symbols-outlined text-[18px] text-muted">tune</span>
        </div>
      </div>

      <div class="header-center">
        <!-- Quick Barcode / Product Search -->
        <div class="search-input-wrapper">
          <span class="material-symbols-outlined search-icon">barcode_scanner</span>
          <input
            ref="searchInputRef"
            v-model="searchQuery"
            type="text"
            class="search-input"
            placeholder="Quét mã vạch hoặc tìm sản phẩm, SKU (F2)..."
            autofocus
          />
          <button
            v-if="searchQuery"
            type="button"
            class="btn-clear-search"
            @click="searchQuery = ''"
          >
            <span class="material-symbols-outlined text-[16px]">close</span>
          </button>
        </div>
      </div>

      <div class="header-right">
        <!-- Add Custom Item Button -->
        <button
          type="button"
          class="btn-header-action"
          title="Thêm món hoặc dịch vụ tùy chỉnh"
          @click="showCustomItemModal = true"
        >
          <span class="material-symbols-outlined">add_circle</span>
          <span class="action-text">Món tùy chỉnh</span>
        </button>

        <!-- Offline Drafts Button -->
        <button
          type="button"
          class="btn-header-action relative"
          :class="{ 'has-drafts': drafts.length > 0 }"
          title="Danh sách bản nháp offline"
          @click="showDraftsModal = true"
        >
          <span class="material-symbols-outlined">sync_saved_locally</span>
          <span class="action-text">Bản nháp ({{ drafts.length }})</span>
          <span v-if="drafts.length > 0" class="badge-count">{{ drafts.length }}</span>
        </button>

        <!-- Toggle Fullscreen -->
        <button
          type="button"
          class="btn-icon-circle"
          :title="isFullscreen ? 'Thu nhỏ' : 'Toàn màn hình (F11)'"
          @click="isFullscreen = !isFullscreen"
        >
          <span class="material-symbols-outlined">
            {{ isFullscreen ? 'fullscreen_exit' : 'fullscreen' }}
          </span>
        </button>

        <!-- Back to Admin -->
        <RouterLink to="/admin" class="btn-icon-circle" title="Về bảng điều khiển">
          <span class="material-symbols-outlined">dashboard</span>
        </RouterLink>
      </div>
    </header>

    <!-- Global Toast / Notice Alerts -->
    <div v-if="notice" class="pos-toast notice-toast">
      <span class="material-symbols-outlined">info</span>
      <span>{{ notice }}</span>
      <button class="toast-close" @click="notice = ''"><span class="material-symbols-outlined text-[16px]">close</span></button>
    </div>
    <div v-if="error" class="pos-toast error-toast">
      <span class="material-symbols-outlined">error</span>
      <span>{{ error }}</span>
      <button class="toast-close" @click="error = ''"><span class="material-symbols-outlined text-[16px]">close</span></button>
    </div>

    <!-- Main Dual Panel Grid -->
    <main class="pos-main-content">
      <!-- Left Panel: Product Catalog -->
      <section class="catalog-panel">
        <!-- Category Filter Tabs -->
        <div class="category-tabs-bar">
          <button
            type="button"
            class="cat-tab"
            :class="{ active: selectedCategory === 'all' }"
            @click="selectedCategory = 'all'"
          >
            <span class="material-symbols-outlined">grid_view</span>
            <span>Tất cả</span>
            <span class="count-pill">{{ products.length }}</span>
          </button>
          <button
            type="button"
            class="cat-tab"
            :class="{ active: selectedCategory === 'drinks' }"
            @click="selectedCategory = 'drinks'"
          >
            <span class="material-symbols-outlined">local_cafe</span>
            <span>Đồ uống</span>
          </button>
          <button
            type="button"
            class="cat-tab"
            :class="{ active: selectedCategory === 'equipment' }"
            @click="selectedCategory = 'equipment'"
          >
            <span class="material-symbols-outlined">sports_tennis</span>
            <span>Dụng cụ & Phụ kiện</span>
          </button>
          <button
            type="button"
            class="cat-tab"
            :class="{ active: selectedCategory === 'rental' }"
            @click="selectedCategory = 'rental'"
          >
            <span class="material-symbols-outlined">schedule</span>
            <span>Cho thuê</span>
          </button>
          <button
            type="button"
            class="cat-tab"
            :class="{ active: selectedCategory === 'services' }"
            @click="selectedCategory = 'services'"
          >
            <span class="material-symbols-outlined">build</span>
            <span>Dịch vụ sân</span>
          </button>
        </div>

        <!-- Product Cards Grid -->
        <div class="product-cards-grid">
          <div
            v-for="product in filteredProducts"
            :key="product.id"
            class="product-card"
            :class="{
              'is-out-of-stock': product.stock_quantity <= 0 && !product.is_service,
              'is-rental': product.is_rental,
              'is-service': product.is_service
            }"
            @click="addToCart(product)"
          >
            <!-- Product Image / Banner -->
            <div class="card-media">
              <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
                class="media-img"
                loading="lazy"
              />
              <div v-else class="media-fallback">
                <span class="material-symbols-outlined text-[36px]">
                  {{ product.is_rental ? 'schedule' : product.is_service ? 'build' : 'shopping_bag' }}
                </span>
              </div>

              <!-- Stock / Status Badge -->
              <span
                v-if="product.is_service"
                class="stock-badge ready-badge"
              >
                Sẵn sàng
              </span>
              <span
                v-else-if="product.stock_quantity <= 0"
                class="stock-badge out-badge"
              >
                Hết hàng
              </span>
              <span
                v-else-if="product.stock_quantity <= 5"
                class="stock-badge low-badge"
              >
                Tồn: {{ product.stock_quantity }}
              </span>
              <span
                v-else
                class="stock-badge ok-badge"
              >
                Tồn: {{ product.stock_quantity }}
              </span>

              <!-- Out of Stock Overlay -->
              <div v-if="product.stock_quantity <= 0 && !product.is_service" class="out-of-stock-overlay">
                <span class="overlay-text">Hết hàng</span>
              </div>
            </div>

            <!-- Product Details -->
            <div class="card-body">
              <div class="card-meta">
                <span class="sku-tag">{{ product.sku }}</span>
                <span v-if="product.unit" class="unit-tag">{{ product.unit }}</span>
              </div>
              <h3 class="product-title" :title="product.name">
                {{ product.name }}
              </h3>
              <div class="card-footer">
                <span class="product-price">{{ money(product.price) }}</span>
                <button
                  type="button"
                  class="btn-quick-add"
                  title="Thêm vào giỏ"
                  :disabled="product.stock_quantity <= 0 && !product.is_service"
                >
                  <span class="material-symbols-outlined text-[18px]">add</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Empty Search State -->
          <div v-if="filteredProducts.length === 0" class="empty-catalog-state">
            <span class="material-symbols-outlined empty-icon">inventory_2</span>
            <p class="empty-title">Không tìm thấy sản phẩm phù hợp</p>
            <p class="empty-desc">Thử tìm bằng tên, mã SKU khác hoặc thêm món tùy chỉnh.</p>
            <button type="button" class="btn-action-primary mt-3" @click="showCustomItemModal = true">
              <span class="material-symbols-outlined">add</span>
              <span>Tạo món tùy chỉnh</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Right Panel: Cart & Payment Checkout -->
      <section class="checkout-panel">
        <!-- Order Header & Court Attachment -->
        <div class="cart-header-card">
          <div class="order-title-row">
            <div class="title-with-badge">
              <h2 class="cart-order-title">Đơn hàng #1042</h2>
              <span class="items-pill">{{ totalItemsCount }} món</span>
            </div>
            <button
              v-if="cart.length > 0"
              type="button"
              class="btn-clear-cart"
              title="Xóa giỏ hàng"
              @click="clearCart"
            >
              <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
            </button>
          </div>

          <!-- Gắn vào lịch đặt sân (Reservation Tab Attachment) -->
          <div class="court-attachment-selector">
            <span class="material-symbols-outlined attach-icon">link</span>
            <select
              v-model="selectedReservationId"
              class="court-select"
              @change="handleReservationChange"
            >
              <option :value="null">Gắn vào lịch đặt sân...</option>
              <option
                v-for="res in activeReservations"
                :key="res.id"
                :value="res.id"
              >
                {{ res.customer_name }} - {{ res.court_name }} ({{ res.time_slot }})
              </option>
            </select>
            <span class="material-symbols-outlined chevron-icon">arrow_drop_down</span>
          </div>

          <!-- Customer Loyalty Search -->
          <div v-if="!selectedCustomer" class="customer-search-row">
            <span class="material-symbols-outlined search-cust-icon">person_search</span>
            <input
              v-model="customerPhoneQuery"
              type="text"
              class="customer-input"
              placeholder="SĐT khách hàng / Thẻ thành viên..."
              @keyup.enter="searchCustomer"
            />
            <button
              type="button"
              class="btn-search-cust"
              title="Tìm khách hàng"
              @click="searchCustomer"
            >
              Tìm
            </button>
          </div>
          <div v-else class="customer-badge-card">
            <div class="cust-info">
              <div class="avatar-circle">{{ selectedCustomer.name.slice(0, 1) }}</div>
              <div class="cust-text">
                <span class="cust-name font-bold">{{ selectedCustomer.name }}</span>
                <span class="cust-tier">Hạng: {{ selectedCustomer.tier }} (-{{ selectedCustomer.discount_rate * 100 }}%) · Ví: {{ money(selectedCustomer.wallet_balance) }}</span>
              </div>
            </div>
            <button type="button" class="btn-remove-cust" @click="removeCustomer">
              <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
          </div>
        </div>

        <!-- Cart Items Scroll Area -->
        <div class="cart-items-container">
          <div v-if="cart.length === 0" class="empty-cart-placeholder">
            <span class="material-symbols-outlined placeholder-icon">point_of_sale</span>
            <p class="placeholder-text">Giỏ hàng đang trống</p>
            <span class="placeholder-sub">Chọn sản phẩm bên trái hoặc quét mã vạch để bán hàng.</span>
          </div>

          <div
            v-for="(item, index) in cart"
            :key="item.product.id"
            class="cart-item-row"
          >
            <div class="item-main-info">
              <h4 class="item-title">{{ item.product.name }}</h4>
              <div class="item-pricing">
                <span class="unit-cost">{{ money(item.product.price) }}</span>
                <span v-if="item.product.is_rental" class="rental-badge">
                  Thuê:
                  <select v-model="item.rental_hours" class="rental-hours-select">
                    <option :value="1">1 giờ</option>
                    <option :value="2">2 giờ</option>
                    <option :value="3">3 giờ</option>
                    <option :value="4">4 giờ</option>
                  </select>
                </span>
              </div>
            </div>

            <!-- Stepper Controls -->
            <div class="item-stepper-box">
              <button
                type="button"
                class="btn-stepper"
                @click="updateQuantity(index, -1)"
              >
                <span class="material-symbols-outlined text-[16px]">remove</span>
              </button>
              <span class="stepper-val">{{ item.quantity }}</span>
              <button
                type="button"
                class="btn-stepper"
                @click="updateQuantity(index, 1)"
              >
                <span class="material-symbols-outlined text-[16px]">add</span>
              </button>
            </div>

            <!-- Row Subtotal -->
            <div class="item-total-col">
              <span class="row-total font-bold">
                {{ money(item.product.price * (item.rental_hours || 1) * item.quantity) }}
              </span>
            </div>

            <!-- Remove Button -->
            <button
              type="button"
              class="btn-remove-item"
              title="Xóa món này"
              @click="removeItem(index)"
            >
              <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
          </div>
        </div>

        <!-- Payment & Calculation Footer -->
        <div class="cart-payment-footer">
          <!-- Totals Breakdown -->
          <div class="totals-breakdown">
            <div class="breakdown-row">
              <span class="label">Tạm tính:</span>
              <span class="value font-medium">{{ money(subtotal) }}</span>
            </div>

            <div v-if="memberDiscountAmount > 0" class="breakdown-row discount-row">
              <span class="label">Chiết khấu hội viên:</span>
              <span class="value text-success">-{{ money(memberDiscountAmount) }}</span>
            </div>

            <div class="breakdown-row">
              <div class="vat-toggle-label">
                <input id="vatToggle" v-model="includeVat" type="checkbox" class="vat-checkbox" />
                <label for="vatToggle" class="cursor-pointer">Thuế GTGT (VAT 8%):</label>
              </div>
              <span class="value">{{ money(taxAmount) }}</span>
            </div>

            <div class="breakdown-row grand-total-row">
              <span class="label-large">Tổng cộng:</span>
              <span class="value-large text-primary">{{ money(grandTotal) }}</span>
            </div>
          </div>

          <!-- Payment Method Selector -->
          <div class="payment-methods-grid">
            <button
              type="button"
              class="payment-method-btn"
              :class="{ active: paymentMethod === 'cash' }"
              @click="paymentMethod = 'cash'; cashTendered = grandTotal"
            >
              <span class="material-symbols-outlined">payments</span>
              <span class="method-name">Tiền mặt</span>
            </button>

            <button
              type="button"
              class="payment-method-btn"
              :class="{ active: paymentMethod === 'qr' }"
              @click="paymentMethod = 'qr'"
            >
              <span class="material-symbols-outlined">qr_code_scanner</span>
              <span class="method-name">VietQR</span>
            </button>

            <button
              type="button"
              class="payment-method-btn"
              :class="{ active: paymentMethod === 'wallet' }"
              @click="paymentMethod = 'wallet'"
            >
              <span class="material-symbols-outlined">account_balance_wallet</span>
              <span class="method-name">Ví Aura</span>
            </button>

            <button
              v-if="selectedReservationId"
              type="button"
              class="payment-method-btn court-tab-btn"
              :class="{ active: paymentMethod === 'court_tab' }"
              @click="paymentMethod = 'court_tab'"
            >
              <span class="material-symbols-outlined">receipt_long</span>
              <span class="method-name">Ghi vào Sân</span>
            </button>
          </div>

          <!-- Cash Change Calculation Row (Only for Cash) -->
          <div v-if="paymentMethod === 'cash' && cart.length > 0" class="cash-calc-drawer">
            <div class="quick-cash-chips">
              <button
                v-for="amt in quickCashSuggestions"
                :key="amt"
                type="button"
                class="cash-chip"
                :class="{ active: cashTendered === amt }"
                @click="cashTendered = amt"
              >
                {{ money(amt) }}
              </button>
            </div>
            <div class="cash-input-row">
              <div class="cash-field">
                <label>Khách đưa:</label>
                <input
                  v-model.number="cashTendered"
                  type="number"
                  step="1000"
                  class="cash-num-input"
                />
              </div>
              <div class="cash-field change-field">
                <label>Tiền thối lại:</label>
                <span class="change-amount font-bold" :class="cashChange >= 0 ? 'text-success' : 'text-danger'">
                  {{ money(cashChange) }}
                </span>
              </div>
            </div>
          </div>

          <!-- QR Code Preview Box -->
          <div v-if="paymentMethod === 'qr' && cart.length > 0" class="qr-preview-drawer">
            <div class="qr-box">
              <img
                :src="`https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=AURA_PAY_${grandTotal}_ORD1042`"
                alt="VietQR Code"
                class="qr-image"
              />
            </div>
            <div class="qr-details">
              <p class="qr-bank">MB Bank · STK: <strong>0909888999</strong></p>
              <p class="qr-name">CHỦ TK: <strong>AURA COURT CONTROL</strong></p>
              <p class="qr-amt">Số tiền: <strong class="text-primary">{{ money(grandTotal) }}</strong></p>
              <span class="qr-status-pill">Đang chờ quét...</span>
            </div>
          </div>

          <!-- Wallet Balance Status -->
          <div v-if="paymentMethod === 'wallet' && cart.length > 0" class="wallet-status-drawer">
            <div v-if="selectedCustomer" class="wallet-info">
              <span>Số dư khả dụng:</span>
              <strong :class="selectedCustomer.wallet_balance >= grandTotal ? 'text-success' : 'text-danger'">
                {{ money(selectedCustomer.wallet_balance) }}
              </strong>
            </div>
            <div v-else class="wallet-warning">
              <span class="material-symbols-outlined text-warning">warning</span>
              <span>Vui lòng chọn khách hàng có ví để trừ tiền.</span>
            </div>
          </div>

          <!-- Court Tab Status -->
          <div v-if="paymentMethod === 'court_tab' && cart.length > 0" class="court-tab-drawer">
            <span class="material-symbols-outlined text-primary">receipt</span>
            <span>Khoản tiền <strong>{{ money(grandTotal) }}</strong> sẽ được cộng dồn vào hóa đơn thanh toán khi khách trả sân.</span>
          </div>

          <!-- Action Buttons Bar -->
          <div class="checkout-actions-row">
            <button
              type="button"
              class="btn-save-draft"
              title="Lưu tạm vào bản nháp"
              :disabled="cart.length === 0"
              @click="saveCurrentAsDraft"
            >
              <span class="material-symbols-outlined">save</span>
              <span>Lưu nháp</span>
            </button>

            <button
              type="button"
              class="btn-checkout-primary"
              :disabled="cart.length === 0 || processingCheckout"
              @click="handleCheckout"
            >
              <span v-if="processingCheckout" class="spinner-sm"></span>
              <template v-else>
                <span>Thanh toán (F9)</span>
                <span class="material-symbols-outlined">arrow_forward</span>
              </template>
            </button>
          </div>
        </div>
      </section>
    </main>

    <!-- Bottom Status Bar -->
    <footer class="pos-status-bar">
      <div class="status-left">
        <div class="network-status-badge">
          <span class="pulse-indicator" :class="isOffline ? 'offline' : 'online'"></span>
          <span class="network-text uppercase font-semibold">
            {{ isOffline ? 'Offline (Lưu nháp cục bộ)' : 'Online · Đồng bộ tức thì' }}
          </span>
        </div>

        <div class="status-divider"></div>

        <div class="draft-counter-pill" @click="showDraftsModal = true" role="button">
          <span class="material-symbols-outlined text-[16px]">sync_saved_locally</span>
          <span>Bản nháp cục bộ: <strong>{{ drafts.length }}</strong></span>
        </div>
      </div>

      <div class="status-center">
        <div class="shift-drawer-cash">
          <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
          <span>Tiền mặt trong két: <strong>{{ money(activeShift?.expected_cash || 0) }}</strong></span>
        </div>
      </div>

      <div class="status-right">
        <div class="scanner-ready-pulse">
          <span class="material-symbols-outlined text-[16px] animate-pulse">barcode_scanner</span>
          <span>Máy quét mã vạch sẵn sàng...</span>
        </div>
        <div class="status-divider"></div>
        <span class="shortcut-tip">F2: Tìm kiếm | F9: Thanh toán | Esc: Đóng</span>
      </div>
    </footer>

    <!-- MODAL 1: Custom Item Modal -->
    <div v-if="showCustomItemModal" class="modal-backdrop" @click.self="showCustomItemModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <h3 class="modal-title">Thêm món / Dịch vụ tùy chỉnh</h3>
          <button class="btn-close-modal" @click="showCustomItemModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Tên sản phẩm / Dịch vụ:</label>
            <input
              v-model="customItem.name"
              type="text"
              class="form-input"
              placeholder="VD: Căng cước khẩn cấp, Nước ép đặc biệt..."
              autofocus
            />
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label>Giá bán (VNĐ):</label>
              <input
                v-model.number="customItem.price"
                type="number"
                step="5000"
                class="form-input"
              />
            </div>
            <div class="form-group">
              <label>Số lượng:</label>
              <input
                v-model.number="customItem.quantity"
                type="number"
                min="1"
                class="form-input"
              />
            </div>
          </div>
          <div class="form-group">
            <label>Danh mục:</label>
            <select v-model="customItem.category" class="form-input">
              <option value="services">Dịch vụ sân</option>
              <option value="drinks">Đồ uống</option>
              <option value="equipment">Dụng cụ & Phụ kiện</option>
              <option value="rental">Cho thuê</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showCustomItemModal = false">Hủy</button>
          <button class="btn-primary" :disabled="!customItem.name.trim()" @click="addCustomItemToCart">
            Thêm vào giỏ hàng
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 2: Shift Management Modal -->
    <div v-if="showShiftModal" class="modal-backdrop" @click.self="showShiftModal = false">
      <div class="modal-card modal-lg">
        <div class="modal-header">
          <h3 class="modal-title">Quản lý Ca Thu Ngân & Két Tiền</h3>
          <button class="btn-close-modal" @click="showShiftModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="shift-stats-grid">
            <div class="stat-box">
              <span class="stat-label">Tiền đầu ca:</span>
              <span class="stat-val">{{ money(activeShift?.opening_cash || 0) }}</span>
            </div>
            <div class="stat-box">
              <span class="stat-label">Doanh thu tiền mặt:</span>
              <span class="stat-val text-success">{{ money(activeShift?.cash_sales || 0) }}</span>
            </div>
            <div class="stat-box">
              <span class="stat-label">Doanh thu QR & Ví:</span>
              <span class="stat-val">{{ money((activeShift?.qr_sales || 0) + (activeShift?.wallet_sales || 0)) }}</span>
            </div>
            <div class="stat-box highlight-box">
              <span class="stat-label">Tiền mặt dự kiến trong két:</span>
              <span class="stat-val text-primary font-bold">{{ money(activeShift?.expected_cash || 0) }}</span>
            </div>
          </div>

          <div class="close-shift-section mt-4">
            <h4 class="section-subtitle">Kiểm đếm và kết ca:</h4>
            <div class="form-grid-2 mt-2">
              <div class="form-group">
                <label>Tiền mặt thực tế đếm được:</label>
                <input
                  v-model.number="shiftDeclaredCash"
                  type="number"
                  step="10000"
                  class="form-input font-bold"
                  placeholder="Nhập số tiền đếm được trong két"
                />
              </div>
              <div class="form-group">
                <label>Chênh lệch (Thừa / Thiếu):</label>
                <div class="diff-display" :class="(shiftDeclaredCash - (activeShift?.expected_cash || 0)) >= 0 ? 'text-success' : 'text-danger'">
                  {{ money(shiftDeclaredCash - (activeShift?.expected_cash || 0)) }}
                </div>
              </div>
            </div>
            <div class="form-group mt-2">
              <label>Ghi chú bàn giao:</label>
              <textarea
                v-model="shiftNote"
                rows="2"
                class="form-input"
                placeholder="Ghi chú thêm về tiền lẻ, chứng từ hoặc bàn giao ca sau..."
              ></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <RouterLink
            to="/admin/shifts"
            class="btn-secondary flex items-center gap-1 text-xs"
            @click="showShiftModal = false"
          >
            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
            Màn hình đối soát chuyên sâu
          </RouterLink>
          <button class="btn-secondary" @click="showShiftModal = false">Đóng</button>
          <button
            v-if="activeShift?.status === 'open'"
            class="btn-danger"
            @click="closeShift"
          >
            Chốt ca & Bàn giao
          </button>
          <button
            v-else
            class="btn-primary"
            @click="openShift"
          >
            Mở ca mới
          </button>
        </div>

      </div>
    </div>

    <!-- MODAL 3: Offline Drafts Modal -->
    <div v-if="showDraftsModal" class="modal-backdrop" @click.self="showDraftsModal = false">
      <div class="modal-card modal-lg">
        <div class="modal-header">
          <h3 class="modal-title">Bản nháp đơn hàng cục bộ (Offline Drafts)</h3>
          <button class="btn-close-modal" @click="showDraftsModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="muted-text text-sm mb-3">
            Bản nháp được lưu tại trình duyệt này khi mất mạng hoặc khi thu ngân lưu tạm để phục vụ khách khác.
          </p>
          <div v-if="drafts.length === 0" class="empty-drafts-state">
            <span class="material-symbols-outlined empty-icon">drafts</span>
            <p>Không có bản nháp nào đang lưu.</p>
          </div>
          <div v-else class="drafts-list">
            <div
              v-for="d in drafts"
              :key="d.id"
              class="draft-item-card"
            >
              <div class="draft-info">
                <h4 class="draft-title font-bold">{{ d.name }}</h4>
                <p class="draft-meta">
                  Khách: {{ d.customer_name }} · {{ d.items.length }} sản phẩm · {{ new Date(d.saved_at).toLocaleString('vi-VN') }}
                </p>
                <span class="draft-total text-primary font-semibold">{{ money(d.total_amount) }}</span>
              </div>
              <div class="draft-actions">
                <button class="btn-restore" @click="restoreDraft(d)">
                  <span class="material-symbols-outlined text-[16px]">restore</span>
                  <span>Mở lại đơn</span>
                </button>
                <button class="btn-delete-draft" @click="deleteDraft(d.id)">
                  <span class="material-symbols-outlined text-[16px]">delete</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showDraftsModal = false">Đóng</button>
        </div>
      </div>
    </div>

    <!-- MODAL 4: Thermal Receipt Print Preview -->
    <div v-if="showReceiptModal && currentReceipt" class="modal-backdrop" @click.self="showReceiptModal = false">
      <div class="modal-card receipt-modal">
        <div class="modal-header">
          <h3 class="modal-title">Hóa đơn Bán hàng (POS Receipt)</h3>
          <button class="btn-close-modal" @click="showReceiptModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Thermal Paper Container -->
          <div class="thermal-receipt-paper">
            <div class="receipt-brand-header">
              <h2 class="receipt-brand-title">AURA COURT CONTROL</h2>
              <p class="receipt-address">12 Nguyễn Thị Minh Khai, Quận 1, TP.HCM</p>
              <p class="receipt-phone">Hotline: 0908 123 456</p>
              <div class="dashed-divider"></div>
              <h3 class="receipt-invoice-title">PHIẾU THANH TOÁN</h3>
              <p class="receipt-code">Mã đơn: <strong>{{ currentReceipt.order_code }}</strong></p>
              <p class="receipt-date">Ngày: {{ currentReceipt.created_at }}</p>
              <p class="receipt-cashier">Thu ngân: {{ currentReceipt.cashier_name }}</p>
              <p v-if="currentReceipt.court_info" class="receipt-court">Sân: {{ currentReceipt.court_info }}</p>
              <p v-if="currentReceipt.customer_name" class="receipt-customer">Khách hàng: {{ currentReceipt.customer_name }}</p>
            </div>

            <div class="dashed-divider"></div>

            <table class="receipt-items-table">
              <thead>
                <tr>
                  <th class="text-left">Mặt hàng</th>
                  <th class="text-center">SL</th>
                  <th class="text-right">Đ.Giá</th>
                  <th class="text-right">T.Tiền</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, i) in currentReceipt.items" :key="i">
                  <td class="text-left">{{ it.name }}</td>
                  <td class="text-center">{{ it.quantity }}</td>
                  <td class="text-right">{{ money(it.price) }}</td>
                  <td class="text-right font-medium">{{ money(it.total) }}</td>
                </tr>
              </tbody>
            </table>

            <div class="dashed-divider"></div>

            <div class="receipt-summary-block">
              <div class="receipt-row">
                <span>Tạm tính:</span>
                <span>{{ money(currentReceipt.subtotal) }}</span>
              </div>
              <div v-if="currentReceipt.discount_amount > 0" class="receipt-row">
                <span>Chiết khấu:</span>
                <span>-{{ money(currentReceipt.discount_amount) }}</span>
              </div>
              <div v-if="currentReceipt.tax_amount > 0" class="receipt-row">
                <span>Thuế VAT (8%):</span>
                <span>{{ money(currentReceipt.tax_amount) }}</span>
              </div>
              <div class="receipt-row receipt-grand-total">
                <span class="font-bold">TỔNG CỘNG:</span>
                <span class="font-bold text-lg">{{ money(currentReceipt.grand_total) }}</span>
              </div>
              <div class="receipt-row text-xs mt-1">
                <span>Phương thức:</span>
                <span class="uppercase font-semibold">{{ currentReceipt.payment_method }}</span>
              </div>
              <div v-if="currentReceipt.cash_tendered" class="receipt-row text-xs">
                <span>Tiền khách đưa:</span>
                <span>{{ money(currentReceipt.cash_tendered) }}</span>
              </div>
              <div v-if="currentReceipt.cash_change !== undefined" class="receipt-row text-xs">
                <span>Tiền thừa:</span>
                <span>{{ money(currentReceipt.cash_change) }}</span>
              </div>
            </div>

            <div class="dashed-divider"></div>

            <div class="receipt-footer-notes">
              <p class="font-semibold">Cảm ơn Quý khách & Hẹn gặp lại!</p>
              <p class="receipt-qr-note">Quét mã để tra cứu hóa đơn điện tử</p>
              <div class="receipt-qr-box">
                <img
                  :src="`https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=AURA_RECEIPT_${currentReceipt.order_code}`"
                  alt="Invoice QR"
                  class="receipt-qr-img"
                />
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showReceiptModal = false">Đóng</button>
          <button class="btn-primary" onclick="window.print()">
            <span class="material-symbols-outlined text-[18px]">print</span>
            <span>In hóa đơn (80mm)</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ==========================================================================
   Aura Court Control - POS & Cashier Styles
   Soft Minimalism & High Performance Design System
   ========================================================================== */

.pos-workspace {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: calc(100vh - 78px);
  background: var(--bg, #eff1f4);
  color: var(--ink, #191c1d);
  overflow: hidden;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  position: relative;
}

.pos-workspace.is-fullscreen {
  position: fixed;
  inset: 0;
  height: 100vh;
  z-index: 9999;
}

/* ================= Top Navigation Bar ================= */
.pos-header {
  height: 60px;
  background: var(--surface, #ffffff);
  border-bottom: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  flex-shrink: 0;
  gap: 16px;
  z-index: 40;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.pos-title-block {
  display: flex;
  flex-direction: column;
}

.pos-page-title {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: var(--primary, #78555e);
  line-height: 1.2;
}

:global(html.dark) .pos-page-title {
  color: #f8b4c4;
}

.pos-page-sub {
  font-size: 10px;
  color: var(--muted, #6c757d);
  font-weight: 500;
}

.shift-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 20px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}

.shift-pill:hover {
  background: var(--surface-container, #edeeef);
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.pulse-dot.green {
  background: #10b981;
  box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
}

.pulse-dot.gray {
  background: #9ca3af;
}

/* Header Center Search */
.header-center {
  flex: 1;
  max-width: 520px;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: var(--muted, #6c757d);
  font-size: 20px;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 8px 36px 8px 38px;
  border-radius: 24px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
  transition: all 0.15s;
}

.search-input:focus {
  border-color: var(--primary, #78555e);
  background: var(--surface, #ffffff);
  box-shadow: 0 0 0 2px rgba(120, 85, 94, 0.12);
}

.btn-clear-search {
  position: absolute;
  right: 10px;
  background: transparent;
  border: none;
  color: var(--muted, #6c757d);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Header Right Actions */
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-header-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 8px;
  color: var(--ink, #191c1d);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-header-action:hover {
  background: var(--surface-container, #edeeef);
}

.btn-icon-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.15s;
}

.btn-icon-circle:hover {
  background: var(--surface-container, #edeeef);
}

.badge-count {
  position: absolute;
  top: -4px;
  right: -4px;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 10px;
  font-weight: 700;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ================= Main Dual Panel ================= */
.pos-main-content {
  flex: 1;
  display: flex;
  padding: 12px;
  gap: 12px;
  overflow: hidden;
  min-height: 0;
}

/* ================= Left Panel: Catalog ================= */
.catalog-panel {
  flex: 1;
  background: var(--surface, #ffffff);
  border-radius: 12px;
  border: 1px solid var(--border, #e0e4ea);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.category-tabs-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  overflow-x: auto;
  scrollbar-width: none;
}

.cat-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid transparent;
  background: var(--surface-subtle, #f5f6f8);
  color: var(--muted, #6c757d);
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.15s;
}

.cat-tab:hover {
  background: var(--surface-container, #edeeef);
  color: var(--ink, #191c1d);
}

.cat-tab.active {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #5e3e47);
  font-weight: 700;
}

:global(html.dark) .cat-tab.active {
  background: #53333e;
  color: #f8b4c4;
}

.count-pill {
  background: rgba(0, 0, 0, 0.08);
  padding: 2px 6px;
  border-radius: 10px;
  font-size: 10px;
}

/* Product Cards Grid */
.product-cards-grid {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
  align-content: start;
}

.product-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}

.product-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
  border-color: var(--primary, #78555e);
}

.product-card:active {
  transform: scale(0.98);
}

.card-media {
  height: 110px;
  position: relative;
  background: var(--surface-subtle, #f5f6f8);
  overflow: hidden;
}

.media-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.product-card:hover .media-img {
  transform: scale(1.06);
}

.media-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted, #6c757d);
}

.stock-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  padding: 2px 6px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  backdrop-filter: blur(4px);
}

.ok-badge {
  background: rgba(255, 255, 255, 0.9);
  color: var(--primary, #78555e);
}

.low-badge {
  background: #fff3e0;
  color: #ef6c00;
}

.ready-badge {
  background: #e8f5e9;
  color: #2e7d32;
}

.out-badge {
  background: #ffebee;
  color: #c62828;
}

.out-of-stock-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
}

.overlay-text {
  background: #c62828;
  color: #ffffff;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
}

.card-body {
  padding: 10px;
  display: flex;
  flex-direction: column;
  flex: 1;
  justify-content: space-between;
  gap: 6px;
}

.card-meta {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  color: var(--muted, #6c757d);
}

.product-title {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.3;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  color: var(--ink, #191c1d);
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.product-price {
  font-size: 14px;
  font-weight: 800;
  color: var(--primary, #78555e);
}

:global(html.dark) .product-price {
  color: #f8b4c4;
}

.btn-quick-add {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-quick-add:hover {
  background: var(--primary, #78555e);
  color: #ffffff;
}

/* ================= Right Panel: Cart & Payment ================= */
.checkout-panel {
  width: 380px;
  background: var(--surface, #ffffff);
  border-radius: 12px;
  border: 1px solid var(--border, #e0e4ea);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  flex-shrink: 0;
}

.cart-header-card {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: var(--surface, #ffffff);
}

.order-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 8px;
}

.cart-order-title {
  font-size: 16px;
  font-weight: 800;
  margin: 0;
}

.items-pill {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #5e3e47);
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 12px;
}

.btn-clear-cart {
  background: transparent;
  border: none;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
}

.btn-clear-cart:hover {
  color: #c62828;
  background: #ffebee;
}

/* Court Attachment Selector */
.court-attachment-selector {
  position: relative;
  display: flex;
  align-items: center;
}

.attach-icon {
  position: absolute;
  left: 10px;
  color: var(--muted, #6c757d);
  font-size: 18px;
  pointer-events: none;
}

.court-select {
  width: 100%;
  padding: 7px 32px 7px 34px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  font-size: 12px;
  font-weight: 500;
  appearance: none;
  outline: none;
  cursor: pointer;
}

.court-select:focus {
  border-color: var(--primary, #78555e);
}

.chevron-icon {
  position: absolute;
  right: 8px;
  color: var(--muted, #6c757d);
  font-size: 20px;
  pointer-events: none;
}

/* Customer Search */
.customer-search-row {
  position: relative;
  display: flex;
  align-items: center;
  gap: 6px;
}

.search-cust-icon {
  position: absolute;
  left: 10px;
  color: var(--muted, #6c757d);
  font-size: 18px;
}

.customer-input {
  flex: 1;
  padding: 6px 10px 6px 32px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  font-size: 12px;
  outline: none;
}

.btn-search-cust {
  padding: 6px 12px;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.customer-badge-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 8px;
}

.cust-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.avatar-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--primary, #78555e);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 12px;
}

.cust-text {
  display: flex;
  flex-direction: column;
}

.cust-name {
  font-size: 12px;
  line-height: 1.2;
}

.cust-tier {
  font-size: 10px;
  color: var(--muted, #6c757d);
}

.btn-remove-cust {
  background: transparent;
  border: none;
  color: var(--muted, #6c757d);
  cursor: pointer;
}

/* Cart Items Container */
.cart-items-container {
  flex: 1;
  overflow-y: auto;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.empty-cart-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--muted, #6c757d);
  text-align: center;
  padding: 24px;
}

.placeholder-icon {
  font-size: 48px;
  opacity: 0.3;
  margin-bottom: 8px;
}

.placeholder-text {
  font-size: 14px;
  font-weight: 700;
  margin: 0;
}

.placeholder-sub {
  font-size: 11px;
  margin-top: 4px;
  opacity: 0.7;
}

.cart-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 10px;
  background: var(--surface-subtle, #f5f6f8);
  border-radius: 8px;
  gap: 8px;
}

.item-main-info {
  flex: 1;
  min-width: 0;
}

.item-title {
  font-size: 12px;
  font-weight: 600;
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-pricing {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--muted, #6c757d);
}

.rental-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  color: var(--primary, #78555e);
}

.rental-hours-select {
  padding: 1px 4px;
  font-size: 10px;
  border-radius: 4px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
}

.item-stepper-box {
  display: flex;
  align-items: center;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 6px;
  padding: 2px;
}

.btn-stepper {
  width: 22px;
  height: 22px;
  border: none;
  background: transparent;
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border-radius: 4px;
}

.btn-stepper:hover {
  background: var(--surface-container, #edeeef);
}

.stepper-val {
  width: 24px;
  text-align: center;
  font-size: 12px;
  font-weight: 700;
}

.item-total-col {
  width: 70px;
  text-align: right;
  font-size: 12px;
}

.btn-remove-item {
  background: transparent;
  border: none;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 2px;
  border-radius: 4px;
}

.btn-remove-item:hover {
  color: #c62828;
}

/* Payment & Calculation Footer */
.cart-payment-footer {
  padding: 12px 14px;
  border-top: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.totals-breakdown {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--muted, #6c757d);
}

.grand-total-row {
  padding-top: 6px;
  border-top: 1px dashed var(--border, #e0e4ea);
  margin-top: 2px;
}

.label-large {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink, #191c1d);
}

.value-large {
  font-size: 18px;
  font-weight: 900;
}

.vat-toggle-label {
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Payment Methods Grid */
.payment-methods-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}

.payment-method-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 8px 4px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.15s;
  gap: 4px;
}

.payment-method-btn .material-symbols-outlined {
  font-size: 20px;
  color: var(--muted, #6c757d);
}

.payment-method-btn .method-name {
  font-size: 11px;
  font-weight: 600;
}

.payment-method-btn:hover {
  background: var(--surface-container, #edeeef);
}

.payment-method-btn.active {
  border-color: var(--primary, #78555e);
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #5e3e47);
}

.payment-method-btn.active .material-symbols-outlined {
  color: var(--primary, #78555e);
}

.court-tab-btn {
  grid-column: span 3;
}

/* Cash Calculation Drawer */
.cash-calc-drawer {
  background: var(--surface-subtle, #f5f6f8);
  padding: 8px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.quick-cash-chips {
  display: flex;
  gap: 4px;
  overflow-x: auto;
}

.cash-chip {
  padding: 3px 8px;
  border-radius: 12px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
}

.cash-chip.active {
  background: var(--primary, #78555e);
  color: #ffffff;
  border-color: var(--primary, #78555e);
}

.cash-input-row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.cash-field {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 11px;
}

.cash-num-input {
  width: 100%;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  font-size: 12px;
  font-weight: 700;
}

.change-amount {
  font-size: 13px;
  padding-top: 4px;
}

/* QR Code Preview Drawer */
.qr-preview-drawer {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--surface-subtle, #f5f6f8);
  padding: 8px;
  border-radius: 8px;
}

.qr-box {
  width: 70px;
  height: 70px;
  background: #ffffff;
  padding: 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qr-details {
  font-size: 11px;
  line-height: 1.4;
}

.qr-status-pill {
  display: inline-block;
  font-size: 9px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #5e3e47);
  margin-top: 2px;
}

/* Action Buttons Bar */
.checkout-actions-row {
  display: flex;
  gap: 8px;
}

.btn-save-draft {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-save-draft:hover:not(:disabled) {
  background: var(--surface-container, #edeeef);
}

.btn-checkout-primary {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 10px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #5e3e47);
  font-size: 14px;
  font-weight: 800;
  border: none;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.15);
}

:global(html.dark) .btn-checkout-primary {
  background: #f8b4c4;
  color: #201317;
}

.btn-checkout-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  filter: brightness(0.96);
}

.btn-checkout-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ================= Bottom Status Bar ================= */
.pos-status-bar {
  height: 34px;
  background: var(--surface, #ffffff);
  border-top: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  font-size: 11px;
  color: var(--muted, #6c757d);
  flex-shrink: 0;
}

.status-left,
.status-center,
.status-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.network-status-badge {
  display: flex;
  align-items: center;
  gap: 6px;
}

.pulse-indicator {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.pulse-indicator.online {
  background: #10b981;
  box-shadow: 0 0 6px rgba(16, 185, 129, 0.8);
}

.pulse-indicator.offline {
  background: #ef4444;
  box-shadow: 0 0 6px rgba(239, 68, 68, 0.8);
}

.status-divider {
  width: 1px;
  height: 14px;
  background: var(--border, #e0e4ea);
}

.draft-counter-pill {
  display: flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
}

.draft-counter-pill:hover {
  color: var(--ink, #191c1d);
}

.shift-drawer-cash {
  display: flex;
  align-items: center;
  gap: 6px;
}

.scanner-ready-pulse {
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--primary, #78555e);
  font-weight: 600;
}

.shortcut-tip {
  font-size: 10px;
  opacity: 0.7;
}

/* ================= Global Toasts ================= */
.pos-toast {
  position: absolute;
  top: 70px;
  right: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  z-index: 1000;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
  animation: slideIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideIn {
  from { transform: translateX(30px); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

.notice-toast {
  background: var(--primary, #78555e);
  color: #ffffff;
}

.error-toast {
  background: #c62828;
  color: #ffffff;
}

.toast-close {
  background: transparent;
  border: none;
  color: #ffffff;
  cursor: pointer;
  display: flex;
  align-items: center;
}

/* ================= Modals ================= */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 440px;
  background: var(--surface, #ffffff);
  border-radius: 14px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalScale 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-card.modal-lg {
  max-width: 580px;
}

.modal-card.receipt-modal {
  max-width: 420px;
}

@keyframes modalScale {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.modal-header {
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.modal-title {
  font-size: 15px;
  font-weight: 800;
  margin: 0;
}

.btn-close-modal {
  background: transparent;
  border: none;
  color: var(--muted, #6c757d);
  cursor: pointer;
}

.modal-body {
  padding: 16px 18px;
  overflow-y: auto;
  max-height: 70vh;
}

.modal-footer {
  padding: 12px 18px;
  border-top: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  background: var(--surface-subtle, #f5f6f8);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 12px;
  font-size: 12px;
}

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-input {
  width: 100%;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
}

.form-input:focus {
  border-color: var(--primary, #78555e);
}

.btn-primary {
  padding: 8px 16px;
  background: var(--primary, #78555e);
  color: #ffffff;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-secondary {
  padding: 8px 16px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-danger {
  padding: 8px 16px;
  background: #c62828;
  color: #ffffff;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

/* Shift Stats in Modal */
.shift-stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.stat-box {
  background: var(--surface-subtle, #f5f6f8);
  padding: 10px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
}

.stat-label {
  font-size: 11px;
  color: var(--muted, #6c757d);
}

.stat-val {
  font-size: 15px;
  font-weight: 800;
  margin-top: 2px;
}

.highlight-box {
  grid-column: span 2;
  border: 1px solid var(--primary-container, #ffd1dc);
}

.diff-display {
  font-size: 14px;
  font-weight: 800;
  padding: 8px 0;
}

/* Thermal Receipt Paper Layout */
.thermal-receipt-paper {
  background: #ffffff;
  color: #111111;
  padding: 16px;
  border: 1px solid #e0e4ea;
  border-radius: 8px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 12px;
  line-height: 1.4;
}

.receipt-brand-header {
  text-align: center;
}

.receipt-brand-title {
  font-size: 15px;
  font-weight: 900;
  margin: 0;
}

.receipt-address,
.receipt-phone,
.receipt-code,
.receipt-date,
.receipt-cashier,
.receipt-court,
.receipt-customer {
  font-size: 11px;
  margin: 2px 0;
}

.receipt-invoice-title {
  font-size: 13px;
  font-weight: 800;
  margin: 4px 0;
}

.dashed-divider {
  border-bottom: 1px dashed #999999;
  margin: 8px 0;
}

.receipt-items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}

.receipt-items-table th {
  padding: 4px 0;
  border-bottom: 1px solid #111111;
}

.receipt-items-table td {
  padding: 4px 0;
}

.receipt-summary-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.receipt-row {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
}

.receipt-grand-total {
  font-size: 13px;
  padding-top: 4px;
  border-top: 1px solid #111111;
  margin-top: 2px;
}

.receipt-footer-notes {
  text-align: center;
  font-size: 11px;
  margin-top: 8px;
}

.receipt-qr-box {
  margin: 6px auto 0;
  width: 80px;
  height: 80px;
}

.receipt-qr-img {
  width: 100%;
  height: 100%;
}

/* Dark mode tweaks for POS */
:global(html.dark) .pos-workspace {
  background: #18191d;
  color: #f3f4f6;
}

:global(html.dark) .pos-header,
:global(html.dark) .pos-status-bar,
:global(html.dark) .catalog-panel,
:global(html.dark) .checkout-panel,
:global(html.dark) .modal-card,
:global(html.dark) .category-tabs-bar,
:global(html.dark) .cart-header-card,
:global(html.dark) .cart-payment-footer {
  background: #22242a;
  border-color: #353942;
}

:global(html.dark) .product-card,
:global(html.dark) .cart-item-row,
:global(html.dark) .cash-calc-drawer,
:global(html.dark) .qr-preview-drawer,
:global(html.dark) .modal-footer,
:global(html.dark) .stat-box {
  background: #2b2e36;
  border-color: #383c46;
}

:global(html.dark) .search-input,
:global(html.dark) .court-select,
:global(html.dark) .customer-input,
:global(html.dark) .form-input,
:global(html.dark) .cash-num-input {
  background: #1e2025;
  border-color: #383c46;
  color: #f3f4f6;
}

:global(html.dark) .product-title {
  color: #f3f4f6;
}

:global(html.dark) .ok-badge {
  background: #353942;
  color: #f8b4c4;
}

:global(html.dark) .out-of-stock-overlay {
  background: rgba(34, 36, 42, 0.85);
}

:global(html.dark) .thermal-receipt-paper {
  background: #ffffff;
  color: #000000;
}

/* Spinner */
.spinner-sm {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(0, 0, 0, 0.2);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 900px) {
  .pos-main-content {
    flex-direction: column;
    overflow-y: auto;
  }
  .checkout-panel {
    width: 100%;
  }
}
</style>
