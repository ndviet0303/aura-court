import { ref, computed } from 'vue'
import { posApi } from '../api/posApi'
import type { Product } from '../types/pos'
import { errorMessage } from '@/core/utils/errorMessage'

export const defaultCatalog: Product[] = [
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
    stock_quantity: 0,
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

export function usePosProducts() {
  const products = ref<Product[]>([...defaultCatalog])
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

      const matchName = prod.name.toLowerCase().includes(q)
      const matchSku = prod.sku.toLowerCase().includes(q)
      const matchBarcode = prod.barcode ? prod.barcode.includes(q) : false
      return matchCat && (matchName || matchSku || matchBarcode)
    })
  })

  async function loadProducts() {
    loading.value = true
    error.value = ''
    try {
      const remote = await posApi.getProducts()
      if (Array.isArray(remote) && remote.length > 0) {
        products.value = remote
      }
    } catch (e) {
      // Fall back to default catalog on error
      products.value = [...defaultCatalog]
      error.value = errorMessage(e, 'Sử dụng danh mục POS offline.')
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
      p.stock_quantity = Math.max(0, p.stock_quantity + quantityChange)
    }
  }

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
