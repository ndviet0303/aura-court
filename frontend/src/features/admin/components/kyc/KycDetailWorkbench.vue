<script setup lang="ts">
import type { HostKyc } from '../../types/kyc'

const props = defineProps<{
  item: HostKyc
  checklist: {
    rep_identity_matched: boolean
    business_license_valid: boolean
    bank_account_matched: boolean
    brand_name_discrepancy_accepted: boolean
    facility_inspected: boolean
  }
  reviewer_notes: string
}>()

const emit = defineEmits<{
  (e: 'update:checklist', val: typeof props.checklist): void
  (e: 'update:reviewer_notes', val: string): void
}>()

function toggleChecklist(key: keyof typeof props.checklist) {
  emit('update:checklist', {
    ...props.checklist,
    [key]: !props.checklist[key],
  })
}
</script>

<template>
  <div class="kyc-detail-workbench">
    <!-- Panel Top Header -->
    <div class="workbench-header">
      <div class="host-info-block">
        <div class="host-icon-box">
          <span class="material-symbols-outlined icon-28">domain</span>
        </div>
        <div>
          <h2 class="host-title">{{ item.business_name }}</h2>
          <div class="host-sub-row">
            <span class="sub-item">MST: <strong>{{ item.tax_code }}</strong></span>
            <span class="dot-separator"></span>
            <span class="sub-item">Đại diện: <strong>{{ item.representative_name }}</strong></span>
            <span class="dot-separator"></span>
            <span class="sub-item">Loại hình: <strong>{{ item.business_type === 'enterprise' ? 'Doanh nghiệp TNHH' : item.business_type === 'business_household' ? 'Hộ kinh doanh' : 'Cá nhân' }}</strong></span>
          </div>
        </div>
      </div>

      <div class="header-tools">
        <button type="button" class="btn-tool" title="Xem lịch sử thay đổi">
          <span class="material-symbols-outlined icon-18">history</span> Lịch sử
        </button>
        <button type="button" class="btn-tool" title="Gọi hotline chủ sân">
          <span class="material-symbols-outlined icon-18">call</span> Hotline
        </button>
      </div>
    </div>

    <!-- Scrollable Verification Content -->
    <div class="workbench-body">
      <!-- Section 1: CCCD Identity Verification -->
      <section class="verify-section">
        <div class="section-title-row">
          <div class="title-left">
            <span class="material-symbols-outlined title-icon primary">badge</span>
            <h3 class="section-title">1. Xác minh Định danh Người Đại diện (CCCD)</h3>
          </div>
          <span class="status-tag success">
            <span class="material-symbols-outlined icon-16">check_circle</span> Đã đối khớp CSDL
          </span>
        </div>

        <div class="verify-grid">
          <!-- Document Viewer -->
          <div class="doc-viewer-box">
            <div
              class="doc-img"
              :style="{ backgroundImage: `url(${item.id_card_front_url})` }"
            ></div>
            <div class="doc-watermark">AURA KYC VERIFIED</div>
            <button type="button" class="btn-inspect">
              <span class="material-symbols-outlined icon-18">zoom_in</span> Xem chi tiết
            </button>
          </div>

          <!-- OCR Extracted Table -->
          <div class="extracted-data-card">
            <div class="data-row">
              <span class="data-label">Họ và tên</span>
              <div class="data-val-group">
                <span class="data-value">{{ item.representative_name }}</span>
                <span class="match-badge">100% Khớp</span>
              </div>
            </div>
            <div class="data-row">
              <span class="data-label">Số CCCD</span>
              <div class="data-val-group">
                <span class="data-value font-mono">{{ item.id_card_number }}</span>
                <span class="match-badge">Khớp CSDL Dân cư</span>
              </div>
            </div>
            <div class="data-row">
              <span class="data-label">Sinh trắc khuôn mặt (Face-Match)</span>
              <div class="data-val-group">
                <span class="data-value">{{ item.face_match_score }}%</span>
                <span class="match-badge success">Khớp selfie</span>
              </div>
            </div>
            <div class="ai-banner success">
              <span class="material-symbols-outlined icon-20">verified_user</span>
              <p>Điểm tin cậy AI OCR: <strong>{{ item.ai_ocr_score }}%</strong>. Dữ liệu trùng khớp hoàn toàn với Cổng Dịch vụ công Quốc gia.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 2: Business License & Tax Code -->
      <section class="verify-section">
        <div class="section-title-row">
          <div class="title-left">
            <span class="material-symbols-outlined title-icon warning">description</span>
            <h3 class="section-title">2. Giấy Phép Đăng Ký Kinh Doanh (GPKD)</h3>
          </div>
          <span class="status-tag warning">
            <span class="material-symbols-outlined icon-16">warning</span> Cảnh báo chênh lệch tên
          </span>
        </div>

        <div class="verify-grid">
          <!-- Document Viewer -->
          <div class="doc-viewer-box">
            <div
              class="doc-img"
              :style="{ backgroundImage: `url(${item.business_license_url})` }"
            ></div>
            <div class="doc-watermark">AURA KYC VERIFIED</div>
            <button type="button" class="btn-inspect">
              <span class="material-symbols-outlined icon-18">zoom_in</span> Xem bản scan
            </button>
          </div>

          <!-- Comparison Table -->
          <div class="extracted-data-card">
            <div class="data-row-block">
              <span class="data-label">Tên pháp lý trên GPKD</span>
              <span class="data-value highlight">{{ item.legal_name }}</span>
            </div>
            <div class="data-row-block">
              <span class="data-label">Tên hiển thị thương hiệu</span>
              <div class="brand-diff-row">
                <span class="data-value line-through-text">{{ item.business_name }}</span>
                <span class="material-symbols-outlined icon-18 warning-icon">error</span>
              </div>
            </div>
            <div class="data-row">
              <span class="data-label">Mã số thuế (Tổng cục Thuế)</span>
              <div class="data-val-group">
                <span class="data-value font-mono">{{ item.tax_code }}</span>
                <span class="match-badge success">{{ item.tax_status }}</span>
              </div>
            </div>
            <div class="ai-banner warning">
              <span class="material-symbols-outlined icon-20">lightbulb</span>
              <p>Tên hiển thị trên app khác với tên pháp lý doanh nghiệp. Đã kiểm tra ngành nghề kinh doanh Cầu lông (mã 9311): Hợp lệ.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 3: NAPAS Bank Validation -->
      <section class="verify-section">
        <div class="section-title-row">
          <div class="title-left">
            <span class="material-symbols-outlined title-icon success">account_balance</span>
            <h3 class="section-title">3. Xác Thực Tài Khoản Thụ Hưởng (NAPAS 24/7)</h3>
          </div>
          <span class="status-tag success">
            <span class="material-symbols-outlined icon-16">check_circle</span> Đã liên kết tài khoản
          </span>
        </div>

        <div class="bank-card-container">
          <div class="bank-logo-box">MB</div>
          <div class="bank-details">
            <div class="bank-name-text">{{ item.bank_name }}</div>
            <div class="bank-acc-number font-mono">{{ item.bank_account_number }}</div>
            <div class="bank-holder-row">
              <span class="label-text">Chủ tài khoản:</span>
              <span class="holder-name">{{ item.bank_account_holder }}</span>
              <span class="match-badge success">Match 100%</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 4: Facility & Lease Verification -->
      <section class="verify-section">
        <div class="section-title-row">
          <div class="title-left">
            <span class="material-symbols-outlined title-icon primary">stadium</span>
            <h3 class="section-title">4. Thẩm Định Cơ Sở Vật Chất & Mặt Bằng Sân</h3>
          </div>
          <span class="status-tag success">
            <span class="material-symbols-outlined icon-16">check_circle</span> Đạt chuẩn
          </span>
        </div>

        <div class="facility-grid">
          <div v-if="item.facility_images && item.facility_images.length > 0" class="facility-photos-grid">
            <div
              v-for="(img, idx) in item.facility_images.slice(0, 3)"
              :key="idx"
              class="facility-photo"
              :style="{ backgroundImage: `url(${img})` }"
            ></div>
            <div v-if="item.facility_images.length > 3" class="facility-photo more-photo">+{{ item.facility_images.length - 3 }} ảnh khác</div>
          </div>

          <div class="facility-specs-card">
            <div class="spec-row">
              <span class="material-symbols-outlined spec-icon">sports_tennis</span>
              <span class="spec-text"><strong>{{ item.court_scale }} sân</strong> tiêu chuẩn thi đấu BWF</span>
            </div>
            <div class="spec-row">
              <span class="material-symbols-outlined spec-icon">lightbulb</span>
              <span class="spec-text">{{ item.lighting_spec || 'Hệ thống đèn LED chống chói 1000W' }}</span>
            </div>
            <div class="spec-row">
              <span class="material-symbols-outlined spec-icon">real_estate_agent</span>
              <span class="spec-text">{{ item.lease_contract_status }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 5: Underwriter Checklist & Internal Notes -->
      <section class="checklist-section">
        <h4 class="checklist-title">Checklist Thẩm Định Viên</h4>
        <div class="checklist-grid">
          <label class="check-item">
            <input
              type="checkbox"
              :checked="checklist.rep_identity_matched"
              @change="toggleChecklist('rep_identity_matched')"
            />
            <span>Khớp CCCD & sinh trắc người đại diện</span>
          </label>
          <label class="check-item">
            <input
              type="checkbox"
              :checked="checklist.business_license_valid"
              @change="toggleChecklist('business_license_valid')"
            />
            <span>Mã số thuế & GPKD đang hoạt động</span>
          </label>
          <label class="check-item">
            <input
              type="checkbox"
              :checked="checklist.bank_account_matched"
              @change="toggleChecklist('bank_account_matched')"
            />
            <span>Tài khoản ngân hàng chính chủ NAPAS</span>
          </label>
          <label class="check-item">
            <input
              type="checkbox"
              :checked="checklist.brand_name_discrepancy_accepted"
              @change="toggleChecklist('brand_name_discrepancy_accepted')"
            />
            <span>Chấp nhận sai lệch tên thương hiệu</span>
          </label>
        </div>

        <div class="notes-box">
          <label class="notes-label">Ghi chú biên bản thẩm định nội bộ:</label>
          <textarea
            :value="reviewer_notes"
            placeholder="Nhập ý kiến thẩm định, căn cứ xét duyệt hoặc lý do yêu cầu bổ sung..."
            rows="3"
            class="notes-textarea"
            @input="emit('update:reviewer_notes', ($event.target as HTMLTextAreaElement).value)"
          ></textarea>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.kyc-detail-workbench {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
}

.workbench-header {
  padding: 16px 24px;
  background: #ffffff;
  border-bottom: 1px solid #e1e3e4;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.host-info-block {
  display: flex;
  align-items: center;
  gap: 14px;
}

.host-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: #ffd1dc;
  color: #78555e;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.host-title {
  font-size: 18px;
  font-weight: 800;
  color: #191c1d;
  margin: 0;
}

.host-sub-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
  font-size: 13px;
  color: #5d5f5f;
  flex-wrap: wrap;
}

.dot-separator {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #d3c3c5;
}

.header-tools {
  display: flex;
  gap: 8px;
}

.btn-tool {
  border: 1px solid #e1e3e4;
  background: #f8f9fa;
  padding: 6px 12px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
  color: #191c1d;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: background 0.2s;
}

.btn-tool:hover {
  background: #edeeef;
}

.workbench-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 28px;
  max-height: calc(100vh - 350px);
  overflow-y: auto;
}

.verify-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #edeeef;
  padding-bottom: 8px;
}

.title-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-title {
  font-size: 15px;
  font-weight: 700;
  color: #191c1d;
  margin: 0;
}

.title-icon.primary { color: #78555e; }
.title-icon.warning { color: #d97706; }
.title-icon.success { color: #059669; }

.status-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.status-tag.success { background: #d1fae5; color: #059669; }
.status-tag.warning { background: #fef3c7; color: #d97706; }

.verify-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 900px) {
  .verify-grid {
    grid-template-columns: 1fr;
  }
}

.doc-viewer-box {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 14px;
  height: 200px;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.doc-img {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  transition: transform 0.3s;
}

.doc-viewer-box:hover .doc-img {
  transform: scale(1.04);
}

.doc-watermark {
  position: absolute;
  font-size: 16px;
  font-weight: 900;
  color: rgba(255, 255, 255, 0.4);
  letter-spacing: 0.1em;
  transform: rotate(-30deg);
  pointer-events: none;
}

.btn-inspect {
  position: relative;
  z-index: 2;
  background: rgba(255, 255, 255, 0.95);
  border: 1px solid #e1e3e4;
  color: #191c1d;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 4px;
}

.extracted-data-card {
  background: #fdfdfd;
  border: 1px solid #e1e3e4;
  border-radius: 14px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.data-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 1px solid #f3f4f5;
}

.data-row-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f3f4f5;
}

.data-label {
  font-size: 12px;
  color: #5d5f5f;
}

.data-value {
  font-size: 14px;
  font-weight: 700;
  color: #191c1d;
}

.data-value.highlight {
  color: #78555e;
}

.line-through-text {
  text-decoration: line-through;
  color: #817476;
}

.brand-diff-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.warning-icon {
  color: #d97706;
}

.data-val-group {
  display: flex;
  align-items: center;
  gap: 6px;
}

.match-badge {
  font-size: 10px;
  font-weight: 700;
  background: #d1fae5;
  color: #059669;
  padding: 2px 6px;
  border-radius: 999px;
}

.ai-banner {
  margin-top: 4px;
  padding: 10px 12px;
  border-radius: 10px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  line-height: 1.4;
}

.ai-banner.success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.ai-banner.warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
}

.bank-card-container {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 14px;
  padding: 14px 18px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.bank-logo-box {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: #003399;
  color: #ffffff;
  font-size: 15px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bank-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.bank-name-text {
  font-size: 14px;
  font-weight: 700;
  color: #191c1d;
}

.bank-acc-number {
  font-size: 16px;
  font-weight: 800;
  color: #78555e;
}

.bank-holder-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #5d5f5f;
}

.holder-name {
  font-weight: 700;
  color: #191c1d;
}

.facility-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 900px) {
  .facility-grid {
    grid-template-columns: 1fr;
  }
}

.facility-photos-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.facility-photo {
  height: 90px;
  border-radius: 10px;
  background-size: cover;
  background-position: center;
  background-color: #f3f4f5;
}

.facility-photo.more-photo {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: #5d5f5f;
  background: #edeeef;
}

.facility-specs-card {
  background: #fdfdfd;
  border: 1px solid #e1e3e4;
  border-radius: 14px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 12px;
}

.spec-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: #191c1d;
}

.spec-icon {
  color: #78555e;
  font-size: 20px;
}

.checklist-section {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.checklist-title {
  font-size: 14px;
  font-weight: 700;
  color: #191c1d;
  margin: 0;
}

.checklist-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

@media (max-width: 768px) {
  .checklist-grid {
    grid-template-columns: 1fr;
  }
}

.check-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 500;
  color: #191c1d;
  cursor: pointer;
}

.check-item input[type='checkbox'] {
  accent-color: #78555e;
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.notes-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.notes-label {
  font-size: 12px;
  font-weight: 600;
  color: #5d5f5f;
}

.notes-textarea {
  border: 1px solid #e1e3e4;
  background: #ffffff;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 13px;
  color: #191c1d;
  outline: none;
  resize: none;
  font-family: inherit;
}

.notes-textarea:focus {
  border-color: #78555e;
}
</style>
