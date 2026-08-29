export interface InventoryProduct {
  id: number
  sku: string
  barcode?: string
  name: string
  category: 'drinks' | 'equipment' | 'accessories' | 'rental' | 'services' | 'snacks'
  price: number
  cost: number
  stock_quantity: number
  min_stock: number
  unit: string
  image_url?: string
  description?: string
  is_active: boolean
  last_restocked?: string
  supplier?: string
}

export interface StockMovement {
  id: number
  product_id: number
  product_name: string
  product_sku: string
  type: 'in' | 'out' | 'adjust' | 'return'
  quantity: number
  unit: string
  reference_code: string
  note?: string
  created_by: string
  created_at: string
  balance_after: number
}

export interface InventoryAudit {
  id: number
  code: string
  status: 'draft' | 'in_progress' | 'completed' | 'cancelled'
  created_by: string
  created_at: string
  completed_at?: string
  total_items: number
  matched_items: number
  variance_items: number
}

export interface Supplier {
  id: number
  name: string
  contact_phone?: string
  email?: string
  address?: string
  categories: string[]
}

export const INITIAL_INVENTORY_PRODUCTS: InventoryProduct[] = [
  {
    id: 1, sku: 'YNX-AS30', barcode: '8901234567001', name: 'Ống Cầu Yonex AS-30',
    category: 'accessories', price: 650000, cost: 520000, stock_quantity: 5, min_stock: 20,
    unit: 'ống', image_url: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAbY8vHlBZKth_MrcTu4e5WkigpC-wZllD1vyEFP_Qxv9JHhM94Phm7CcGUp5xylDKKgmoG3NroeOT0nRUmG1OaHeiIAkn1YjrvYIYWM24coEEp656PG7iuWNr5vVrVDKd_rObPtLSu3zd-CONTvjtO78G-uObE_eOokCczdKzunCOT6OAVLi9mJ4xQD3eIZbLeDKcgL4rbCPppYDyB2j1mhMpsG2qfntjuPMo676X_5CxGCVsF_VQ9',
    is_active: true, last_restocked: '2026-08-15', supplier: 'Yonex Vietnam'
  },
  {
    id: 2, sku: 'DRK-POC500', barcode: '8901234567002', name: 'Nước Bù Khoáng Pocari 500ml',
    category: 'drinks', price: 20000, cost: 12000, stock_quantity: 142, min_stock: 50,
    unit: 'chai', image_url: 'https://lh3.googleusercontent.com/aida-public/AB6AXuD6YBKmd4oP36z611_qavF0nLz5zeOwbjPvTMY1IVVz6Ae0kG32mU-LQr18i_856IGX7GCJJIGf3fVDc6Psfra-mwKXAe44jnqHuVVCEflslNBAum4Q0wsPuEzoUnC3A7hEISCQD3AVdy9zwzRUeVcylhuQahP-8EGYTpxw6QRiRYYPrfJGOcqc_KfdoZleJH-Lxi7oQ2GMT2HV7Bii2LFqupAeJ0vFu-HKY8b_pjbiPhWYxJStBS83',
    is_active: true, last_restocked: '2026-08-28', supplier: 'Otsuka'
  },
  {
    id: 3, sku: 'YNX-GRP-WH', barcode: '8901234567003', name: 'Quấn Cán Yonex Super Grap (Trắng)',
    category: 'accessories', price: 35000, cost: 22000, stock_quantity: 28, min_stock: 30,
    unit: 'cuộn', image_url: 'https://lh3.googleusercontent.com/aida-public/AB6AXuCixxyCpbFWe4VTs_k2BHbODdoNQZuxYYbettMnjlYmIJh3fDGl8rOfhtq7mlhDgr1vorhd_7pwg24yFPNK9PEjH9TzC3vhMiEKwHdC8TIYPgIc1JPvP717Hsr6Rte9YyUY-FmcOIFQxyxva8oZ4S41XnaY9fCPZphbYPVO2BP4s0MVvLFkP75I8HTUHpStYIESAho-1IAjk4Ud6FxKluU34-LaUCX4THZGVE3pXDvui7_eUp-713nE',
    is_active: true, last_restocked: '2026-08-20', supplier: 'Yonex Vietnam'
  },
  {
    id: 4, sku: 'DRK-RBL250', barcode: '8901234567004', name: 'Bò Húc (Red Bull) 250ml',
    category: 'drinks', price: 15000, cost: 9000, stock_quantity: 12, min_stock: 40,
    unit: 'lon', image_url: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDMBdOpSHVzIw9MucGxI9sojJgMjs3vYGIXzwrVdVPASvXf46SXOMck9vEcjX2jwnkMsnuyz4bkXpBsc5onx2fvv6iwHphETEQ0WnszlETKaZ7YCwTjSXzX3e0rQ-kmJOj8sIWBku-FtDHz56wQ1Y2Bc4SNT9jZ8qEFG8N4XZaycgy0G1V4bnq_I0hvuoaByOeq_rIf1tIgVgx59HmS55EgOKNo8gE7H-Xaoe4oVl60_UVtRfzmTCDs',
    is_active: true, last_restocked: '2026-08-27', supplier: 'Red Bull VN'
  },
  {
    id: 5, sku: 'DRK-AQUA500', barcode: '8901234567005', name: 'Nước Suối Aquafina 500ml',
    category: 'drinks', price: 10000, cost: 5000, stock_quantity: 240, min_stock: 100,
    unit: 'chai', is_active: true, last_restocked: '2026-08-25', supplier: 'PepsiCo'
  },
  {
    id: 6, sku: 'EQP-VT-LN01', barcode: '8901234567006', name: 'Vợt Cầu Lông Li-Ning Turbo Charging 75',
    category: 'equipment', price: 3200000, cost: 2500000, stock_quantity: 4, min_stock: 5,
    unit: 'cây', is_active: true, last_restocked: '2026-07-20', supplier: 'Li-Ning VN'
  },
  {
    id: 7, sku: 'RNT-VT-YNX', barcode: '8901234567007', name: 'Cho Thuê Vợt Yonex Astrox 88D',
    category: 'rental', price: 50000, cost: 0, stock_quantity: 10, min_stock: 5,
    unit: 'cây', is_active: true, supplier: 'Yonex Vietnam'
  },
  {
    id: 8, sku: 'SNK-BANH01', barcode: '8901234567008', name: 'Bánh Năng Lượng Protein Bar',
    category: 'snacks', price: 25000, cost: 16000, stock_quantity: 55, min_stock: 30,
    unit: 'thanh', is_active: true, last_restocked: '2026-08-22', supplier: 'FitBar VN'
  },
  {
    id: 9, sku: 'ACC-BG-YNX', barcode: '8901234567009', name: 'Túi Cầu Lông Yonex BA82226',
    category: 'accessories', price: 1800000, cost: 1400000, stock_quantity: 3, min_stock: 5,
    unit: 'cái', is_active: true, last_restocked: '2026-08-01', supplier: 'Yonex Vietnam'
  },
  {
    id: 10, sku: 'DRK-STING330', barcode: '8901234567010', name: 'Sting Dâu 330ml',
    category: 'drinks', price: 12000, cost: 7500, stock_quantity: 85, min_stock: 60,
    unit: 'chai', is_active: true, last_restocked: '2026-08-27', supplier: 'PepsiCo'
  },
  {
    id: 11, sku: 'SVC-STR01', barcode: '', name: 'Dịch vụ Căng Cước',
    category: 'services', price: 80000, cost: 30000, stock_quantity: 999, min_stock: 0,
    unit: 'lần', is_active: true, supplier: ''
  },
  {
    id: 12, sku: 'ACC-SOCK-WH', barcode: '8901234567012', name: 'Tất Thể Thao Yonex (Trắng)',
    category: 'accessories', price: 120000, cost: 75000, stock_quantity: 0, min_stock: 20,
    unit: 'đôi', is_active: true, last_restocked: '2026-07-15', supplier: 'Yonex Vietnam'
  }
]

export const INITIAL_STOCK_MOVEMENTS: StockMovement[] = [
  {
    id: 1, product_id: 1, product_name: 'Ống Cầu Yonex AS-30', product_sku: 'YNX-AS30',
    type: 'out', quantity: 3, unit: 'ống', reference_code: 'POS-20260829-014',
    note: 'Bán qua POS', created_by: 'Nguyễn Văn A', created_at: '2026-08-29 19:30:00', balance_after: 5
  },
  {
    id: 2, product_id: 2, product_name: 'Nước Bù Khoáng Pocari 500ml', product_sku: 'DRK-POC500',
    type: 'in', quantity: 48, unit: 'chai', reference_code: 'GRN-20260828-003',
    note: 'Nhập hàng từ NCC Otsuka', created_by: 'Trần Thị B', created_at: '2026-08-28 10:00:00', balance_after: 142
  },
  {
    id: 3, product_id: 4, product_name: 'Bò Húc (Red Bull) 250ml', product_sku: 'DRK-RBL250',
    type: 'out', quantity: 6, unit: 'lon', reference_code: 'POS-20260829-008',
    note: 'Bán qua POS', created_by: 'Nguyễn Văn A', created_at: '2026-08-29 17:15:00', balance_after: 12
  },
  {
    id: 4, product_id: 3, product_name: 'Quấn Cán Yonex Super Grap (Trắng)', product_sku: 'YNX-GRP-WH',
    type: 'adjust', quantity: -2, unit: 'cuộn', reference_code: 'ADJ-20260829-001',
    note: 'Kiểm kê phát hiện thiếu', created_by: 'Trần Thị B', created_at: '2026-08-29 14:00:00', balance_after: 28
  },
  {
    id: 5, product_id: 7, product_name: 'Cho Thuê Vợt Yonex Astrox 88D', product_sku: 'RNT-VT-YNX',
    type: 'return', quantity: 1, unit: 'cây', reference_code: 'RTN-20260829-002',
    note: 'Khách trả vợt thuê - Sân 4', created_by: 'Nguyễn Văn A', created_at: '2026-08-29 20:05:00', balance_after: 10
  },
  {
    id: 6, product_id: 5, product_name: 'Nước Suối Aquafina 500ml', product_sku: 'DRK-AQUA500',
    type: 'in', quantity: 120, unit: 'chai', reference_code: 'GRN-20260825-001',
    note: 'Nhập lô hàng tuần', created_by: 'Trần Thị B', created_at: '2026-08-25 08:30:00', balance_after: 240
  },
  {
    id: 7, product_id: 12, product_name: 'Tất Thể Thao Yonex (Trắng)', product_sku: 'ACC-SOCK-WH',
    type: 'out', quantity: 5, unit: 'đôi', reference_code: 'POS-20260828-022',
    note: 'Bán qua POS', created_by: 'Nguyễn Văn A', created_at: '2026-08-28 18:45:00', balance_after: 0
  }
]

export const INITIAL_INVENTORY_AUDITS: InventoryAudit[] = [
  {
    id: 1, code: 'KK-2026-007', status: 'completed', created_by: 'Trần Thị B',
    created_at: '2026-07-30 08:00:00', completed_at: '2026-07-30 12:00:00',
    total_items: 12, matched_items: 10, variance_items: 2
  },
  {
    id: 2, code: 'KK-2026-008', status: 'in_progress', created_by: 'Nguyễn Văn A',
    created_at: '2026-08-29 14:00:00',
    total_items: 12, matched_items: 8, variance_items: 1
  }
]

export const INITIAL_SUPPLIERS: Supplier[] = [
  { id: 1, name: 'Yonex Vietnam', contact_phone: '028 3822 1234', email: 'sales@yonex.vn', address: 'Quận 1, TP.HCM', categories: ['accessories', 'equipment', 'rental'] },
  { id: 2, name: 'Otsuka (Pocari Sweat)', contact_phone: '028 3911 5678', email: 'orders@otsuka.vn', address: 'Quận 7, TP.HCM', categories: ['drinks'] },
  { id: 3, name: 'Red Bull VN', contact_phone: '0274 375 9999', email: 'dist@redbull.com.vn', address: 'Bình Dương', categories: ['drinks'] },
  { id: 4, name: 'PepsiCo Suntory', contact_phone: '1900 1234', email: 'care@pepsico.vn', address: 'Bắc Ninh', categories: ['drinks'] },
  { id: 5, name: 'Li-Ning VN', contact_phone: '024 3788 8888', email: 'info@lining.com.vn', address: 'Cầu Giấy, Hà Nội', categories: ['equipment', 'accessories'] },
  { id: 6, name: 'FitBar VN', contact_phone: '0909 112 233', email: 'hello@fitbar.vn', address: 'Thủ Đức, TP.HCM', categories: ['snacks'] },
]
