import { ref, computed, onMounted } from 'vue'
import { posApi } from '../api/posApi'
import type { Product } from '../types/pos'
import { errorMessage } from '@/core/utils/errorMessage'

export function usePosProducts() {
  const products = ref<Product[]>([])
  const selectedCategory = ref<string>('all')
  const searchQuery = ref<string>('')
  const loading = ref(false)
  const error = ref('')

  const categories = [
    { key: 'all', label: 'Tất Cả', icon: '⚡' },
    { key: 'drinks', label: 'Nước Giải Khát', icon: '🥤' },
    { key: 'equipment', label: 'Dụng Cụ & Phụ Kiện', icon: '🏸' },
    { key: 'rental', label: 'Cho Thuê', icon: '⏱️' },
    { key: 'services', label: 'Dịch Vụ Sân', icon: '🔧' },
    { key: 'membership', label: 'Gói Hội Viên', icon: '👑' },
  ]

  const filteredProducts = computed(() => {
    return products.value.filter((prod) => {
      const matchCat =
        selectedCategory.value === 'all' || prod.category === selectedCategory.value
      const q = searchQuery.value.trim().toLowerCase()
      if (!q) return matchCat

      const matchName = prod.name?.toLowerCase().includes(q)
      const matchSku = prod.sku?.toLowerCase().includes(q)
      const matchBarcode = prod.barcode ? prod.barcode.includes(q) : false
      return matchCat && (matchName || matchSku || matchBarcode)
    })
  })

  async function loadProducts() {
    loading.value = true
    error.value = ''
    try {
      const remote = await posApi.getProducts()
      if (Array.isArray(remote)) {
        products.value = remote
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh mục POS.')
    } finally {
      loading.value = false
    }
  }

  function addCustomProduct(prod: Product) {
    products.value.unshift(prod)
  }

  function adjustProductStock(productId: number, quantityChange: number) {
    const p = products.value.find((item) => item.id === productId)
    if (p && !p.is_service) {
      p.stock_quantity = Math.max(0, (p.stock_quantity || 0) + quantityChange)
    }
  }

  onMounted(() => {
    loadProducts()
  })

  return {
    products,
    selectedCategory,
    searchQuery,
    loading,
    error,
    categories,
    filteredProducts,
    loadProducts,
    addCustomProduct,
    adjustProductStock,
  }
}
