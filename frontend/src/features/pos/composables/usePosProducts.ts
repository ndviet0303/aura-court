import { ref, computed, onMounted } from 'vue'
import { posApi } from '../api/posApi'
import type { Product, ProductCategory } from '../types/pos'
import { errorMessage } from '@/core/utils/errorMessage'

export function usePosProducts() {
  const products = ref<Product[]>([])
  const selectedCategory = ref<ProductCategory>('all')
  const searchQuery = ref<string>('')
  const loading = ref(false)
  const error = ref('')

  const categories = [
    { key: 'all', labelKey: 'admin.pos.catAll', label: 'Tất Cả', icon: 'grid_view' },
    { key: 'drinks', labelKey: 'admin.pos.catDrinks', label: 'Nước Giải Khát', icon: 'local_cafe' },
    { key: 'nutrition', labelKey: 'admin.pos.catNutrition', label: 'Dinh Dưỡng', icon: 'nutrition' },
    { key: 'equipment', labelKey: 'admin.pos.catEquipment', label: 'Dụng Cụ & Phụ Kiện', icon: 'sports_tennis' },
    { key: 'rental', labelKey: 'admin.pos.catRental', label: 'Cho Thuê', icon: 'schedule' },
    { key: 'services', labelKey: 'admin.pos.catServices', label: 'Dịch Vụ Sân', icon: 'build' },
  ]

  const filteredProducts = computed(() => {
    return products.value.filter((prod) => {
      const matchCat =
        selectedCategory.value === 'all' || prod.category === selectedCategory.value
      const q = searchQuery.value.trim().toLowerCase()
      if (!q) return matchCat

      const matchName = prod.name?.toLowerCase().includes(q)
      const matchSku = prod.sku?.toLowerCase().includes(q)
      const matchBarcode = prod.barcode ? prod.barcode.toLowerCase().includes(q) : false
      return matchCat && (matchName || matchSku || matchBarcode)
    })
  })

  async function loadProducts() {
    loading.value = true
    error.value = ''
    try {
      const remote = await posApi.getProducts()
      if (Array.isArray(remote)) {
        products.value = remote.map((item: any) => {
          const category = (item.metadata?.category || item.category || 'equipment') as ProductCategory
          let defaultVat = 0.08
          if (category === 'equipment' || category === 'membership') {
            defaultVat = 0.10
          }
          const vatRate = item.vat_rate !== undefined ? Number(item.vat_rate) : (item.metadata?.vat_rate !== undefined ? Number(item.metadata.vat_rate) : defaultVat)

          return {
            id: item.id,
            sku: item.sku || `SKU-${item.id}`,
            barcode: item.barcode || '',
            name: item.name || 'Sản phẩm',
            category,
            price: Number(item.price || 0),
            cost: item.cost ? Number(item.cost) : undefined,
            stock_quantity: Number(item.stock_quantity ?? 0),
            image_url: item.image_url || item.metadata?.image_url,
            unit: item.unit || item.metadata?.unit || 'món',
            is_rental: Boolean(item.is_rental ?? item.metadata?.is_rental),
            is_service: Boolean(item.is_service ?? item.metadata?.is_service),
            vat_rate: vatRate,
            description: item.description || item.metadata?.description || '',
            metadata: item.metadata,
          }
        })
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh mục sản phẩm POS.')
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
