<script setup lang="ts">
export interface BankOption {
  code: string
  name: string
}

const POPULAR_BANKS: BankOption[] = [
  { code: 'MB', name: 'MBBank (Quân Đội)' },
  { code: 'VCB', name: 'Vietcombank (Ngoại Thương)' },
  { code: 'TCB', name: 'Techcombank (Kỹ Thương)' },
  { code: 'ACB', name: 'ACB (Á Châu)' },
  { code: 'VPB', name: 'VPBank (Việt Nam Thịnh Vượng)' },
  { code: 'BIDV', name: 'BIDV (Đầu tư & Phát triển)' },
  { code: 'CTG', name: 'VietinBank (Công Thương)' },
  { code: 'TPB', name: 'TPBank (Tiên Phong)' },
  { code: 'HDB', name: 'HDBank (Phát triển TP.HCM)' },
  { code: 'VIB', name: 'VIB (Quốc Tế)' },
  { code: 'STB', name: 'Sacombank (Sài Gòn Thương Tín)' },
  { code: 'SHB', name: 'SHB (Sài Gòn - Hà Nội)' },
  { code: 'MSB', name: 'MSB (Hàng Hải)' },
  { code: 'OCB', name: 'OCB (Phương Đông)' },
  { code: 'LPB', name: 'LPBank (Lộc Phát)' },
  { code: 'AGRIBANK', name: 'Agribank (Nông Nghiệp)' },
]

defineProps<{
  fieldErrors: Record<string, string[]>
  selectedBankCode: string
}>()

const bankName = defineModel<string>('bankName', { required: true })
const bankCode = defineModel<string>('bankCode', { required: true })
const bankAccountNumber = defineModel<string>('bankAccountNumber', { required: true })
const bankAccountHolder = defineModel<string>('bankAccountHolder', {
  required: true,
  set: (value) => value.toUpperCase(),
})

const emit = defineEmits<{
  (e: 'bankChange', event: Event): void
}>()
</script>

<template>
  <div class="form-section-card">
    <div class="form-section-header">
      <div class="section-icon-box">
        <span class="material-symbols-outlined">account_balance</span>
      </div>
      <div class="section-title-wrap">
        <h4 class="section-title">4. Tài khoản Ngân hàng nhận tiền (VietQR)</h4>
        <p class="section-subtitle">
          Doanh thu khách đặt sân sẽ được đối soát và chuyển trực tiếp về tài khoản này
        </p>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-field-item form-grid-full">
        <label for="bank_select" class="field-label">Chọn ngân hàng nhận tiền</label>
        <select
          id="bank_select"
          :value="selectedBankCode"
          class="form-select"
          @change="emit('bankChange', $event)"
        >
          <option value="" disabled>-- Chọn ngân hàng thụ hưởng --</option>
          <option v-for="b in POPULAR_BANKS" :key="b.code" :value="b.code">{{ b.name }}</option>
          <option value="OTHER">Ngân hàng khác (Tự nhập thủ công)</option>
        </select>
      </div>
      <div class="form-field-item">
        <label for="bank_name" class="field-label">Tên ngân hàng</label>
        <input
          id="bank_name"
          v-model="bankName"
          type="text"
          placeholder="Ví dụ: MBBank"
          class="form-input"
        />
        <p v-if="fieldErrors.bank_name" class="field-error-msg">{{ fieldErrors.bank_name[0] }}</p>
      </div>
      <div class="form-field-item">
        <label for="bank_code" class="field-label">Mã ngân hàng (Bank Code)</label>
        <input
          id="bank_code"
          v-model="bankCode"
          type="text"
          placeholder="MB, VCB, TCB..."
          class="form-input"
        />
        <p v-if="fieldErrors.bank_code" class="field-error-msg">{{ fieldErrors.bank_code[0] }}</p>
      </div>
      <div class="form-field-item">
        <label for="bank_account_number" class="field-label">Số tài khoản ngân hàng</label>
        <input
          id="bank_account_number"
          v-model="bankAccountNumber"
          type="text"
          placeholder="Nhập số tài khoản nhận tiền"
          class="form-input"
        />
        <p v-if="fieldErrors.bank_account_number" class="field-error-msg">
          {{ fieldErrors.bank_account_number[0] }}
        </p>
      </div>
      <div class="form-field-item">
        <label for="bank_account_holder" class="field-label"
          >Tên chủ tài khoản (in hoa không dấu)</label
        >
        <input
          id="bank_account_holder"
          v-model="bankAccountHolder"
          type="text"
          placeholder="NGUYEN VAN A"
          class="form-input"
        />
        <p v-if="fieldErrors.bank_account_holder" class="field-error-msg">
          {{ fieldErrors.bank_account_holder[0] }}
        </p>
      </div>
    </div>
  </div>
</template>
