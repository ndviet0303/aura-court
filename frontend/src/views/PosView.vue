<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiV2, errorMessage } from '@/lib/api'

type Product = { id: number; sku: string; name: string; price: number; stock_quantity: number }
type Draft = { id: string; items: Array<{ product_id: number; quantity: number }>; saved_at: string }
type Wallet = { balance: number }
type Plan = { id: number; name: string; version: number; price: number; benefits: Record<string, unknown> }
const products = ref<Product[]>([])
const cart = ref<Array<{ product: Product; quantity: number }>>([])
const drafts = ref<Draft[]>([])
const shifts = ref<{ id: number; status: string; expected_cash: number }[]>([])
const wallet = ref<Wallet>({ balance: 0 })
const plans = ref<Plan[]>([])
const spendAmount = ref(0)
const offline = ref(!navigator.onLine)
const error = ref('')
const notice = ref('')
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const total = computed(() => cart.value.reduce((sum, item) => sum + item.product.price * item.quantity, 0))

function loadDrafts(): void { drafts.value = JSON.parse(localStorage.getItem('aura-pos-drafts') || '[]') as Draft[] }
function saveDraft(): void {
  const draft = { id: `${Date.now()}`, items: cart.value.map((item) => ({ product_id: item.product.id, quantity: item.quantity })), saved_at: new Date().toISOString() }
  drafts.value.push(draft); localStorage.setItem('aura-pos-drafts', JSON.stringify(drafts.value)); notice.value = 'Đã lưu bản nháp cục bộ; offline không thể thanh toán hoặc trừ kho.'
}
function add(product: Product): void {
  const item = cart.value.find((entry) => entry.product.id === product.id)
  if (item) item.quantity += 1
  else cart.value.push({ product, quantity: 1 })
}
async function load(): Promise<void> {
  try { products.value = await apiV2.get<Product[]>('/pos/products'); wallet.value = await apiV2.get<Wallet>('/wallet'); plans.value = await apiV2.get<Plan[]>('/memberships/plans'); loadDrafts() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được catalog POS.') }
}
async function spendWallet(): Promise<void> {
  try { await apiV2.post('/wallet/spend', { amount: spendAmount.value, reference: `wallet-${Date.now()}` }); notice.value = 'Đã dùng wallet credit của tenant.'; wallet.value = await apiV2.get<Wallet>('/wallet') }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể dùng wallet credit.') }
}
async function checkout(method: 'cash' | 'qr' | 'wallet'): Promise<void> {
  if (offline.value) { saveDraft(); return }
  try { await apiV2.post('/pos/orders', { items: cart.value.map((item) => ({ product_id: item.product.id, quantity: item.quantity })), payment_method: method }); cart.value = []; notice.value = 'Đã hoàn tất đơn POS, tồn kho và sổ tài chính.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể hoàn tất đơn POS.') }
}
async function openShift(): Promise<void> {
  try { const result = await apiV2.post<{ shift: { id: number; status: string; expected_cash: number } }>('/pos/shifts', { opening_cash: 0 }); shifts.value.push(result.shift); notice.value = 'Đã mở ca thu ngân.' }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể mở ca.') }
}
function updateNetwork(): void { offline.value = !navigator.onLine }
onMounted(() => { load(); loadDrafts(); window.addEventListener('online', updateNetwork); window.addEventListener('offline', updateNetwork) })
</script>

<template>
  <main class="page-shell">
    <section class="row"><div><div class="eyebrow">API v2 · Tenant POS</div><h1 class="page-title">POS & cashier</h1><p class="muted">Catalog, tồn kho, ca thu ngân, membership, loyalty và wallet theo tenant.</p></div><span class="badge">{{ offline ? 'Offline draft only' : 'Connected' }}</span></section>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
    <section class="card" style="margin-top: 18px"><div class="row"><h2>Catalog</h2><button class="btn" @click="openShift">Mở ca</button></div><div class="grid" style="margin-top: 14px"><button v-for="product in products" :key="product.id" class="panel" style="text-align: left" @click="add(product)"><strong>{{ product.name }}</strong><p class="muted">{{ product.sku }} · tồn {{ product.stock_quantity }}</p><strong>{{ money(product.price) }}</strong></button></div></section>
    <section class="card" style="margin-top: 18px"><h2>Cart</h2><div v-for="item in cart" :key="item.product.id" class="row" style="padding: 10px 0; border-bottom: 1px solid var(--line)"><span>{{ item.product.name }} × {{ item.quantity }}</span><strong>{{ money(item.product.price * item.quantity) }}</strong></div><div class="row" style="margin-top: 14px"><strong>Tổng {{ money(total) }}</strong><div class="actions"><button class="link-button" @click="saveDraft">Lưu draft</button><button class="btn" :disabled="offline" @click="checkout('cash')">Tiền mặt</button><button class="btn" :disabled="offline" @click="checkout('qr')">QR</button><button class="btn" :disabled="offline" @click="checkout('wallet')">Wallet</button></div></div></section>
    <section class="card" style="margin-top: 18px"><h2>Draft offline</h2><p class="muted">Draft chỉ lưu ở máy này. Không có payment hoặc stock movement nào được finalize khi mất kết nối.</p><div v-for="draft in drafts" :key="draft.id" class="row" style="padding: 8px 0"><span>{{ draft.items.length }} mặt hàng</span><span class="muted">{{ new Date(draft.saved_at).toLocaleString('vi-VN') }}</span></div><p v-if="!drafts.length" class="muted">Chưa có draft.</p></section>
    <section class="card" style="margin-top: 18px"><h2>Membership, loyalty & wallet</h2><div class="row" style="margin-top: 12px"><span>Wallet tenant credit</span><strong>{{ money(wallet.balance) }}</strong><div class="actions"><input v-model.number="spendAmount" type="number" min="1" placeholder="Số tiền" /><button class="btn" :disabled="offline" @click="spendWallet">Dùng wallet</button></div></div><div v-for="plan in plans" :key="plan.id" class="panel" style="margin-top: 10px"><strong>{{ plan.name }} · v{{ plan.version }}</strong><span class="muted"> · {{ money(plan.price) }}</span></div><p v-if="!plans.length" class="muted" style="margin-top: 10px">Tenant chưa công bố membership plan.</p><p class="muted" style="margin-top: 10px">Loyalty redemption và membership benefits được kiểm tra server-side theo tenant.</p></section>
  </main>
</template>
