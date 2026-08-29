<script setup lang="ts">
const permissionsMatrix = [
  { module: 'Quản lý Hệ thống Sân', action: 'Tạo, sửa, đổi trạng thái sân', owner: true, manager: true, receptionist: false, tech: false, referee: false },
  { module: 'Quản lý Bảng Giá', action: 'Cài đặt giá giờ vàng & giá linh hoạt', owner: true, manager: true, receptionist: false, tech: false, referee: false },
  { module: 'Lịch Đặt & Bán Hàng (POS)', action: 'Bán lẻ nước, phụ kiện, thu ngân', owner: true, manager: true, receptionist: true, tech: false, referee: false },
  { module: 'Duyệt Hoàn Tiền (Refunds)', action: 'Huỷ đơn và hoàn tiền cho khách', owner: true, manager: true, receptionist: false, tech: false, referee: false },
  { module: 'Bảo Trì & Khoá Sân', action: 'Tạo lịch khoá sân, kiểm tra kỹ thuật', owner: true, manager: true, receptionist: true, tech: true, referee: false },
  { module: 'Giải Đấu & Trọng Tài', action: 'Bàn trọng tài live, cập nhật tỷ số', owner: true, manager: true, receptionist: false, tech: false, referee: true },
  { module: 'Báo Cáo Doanh Thu', action: 'Xem P&L, đối soát tiền mặt ca làm', owner: true, manager: true, receptionist: false, tech: false, referee: false },
  { module: 'Phân Quyền Nhân Sự', action: 'Mời nhân viên, phân quyền truy cập', owner: true, manager: false, receptionist: false, tech: false, referee: false },
]
</script>

<template>
  <div class="role-matrix-container">
    <div class="matrix-header">
      <h3 class="matrix-heading">Ma trận Đối soát Quyền hạn (Role Permission Matrix)</h3>
      <p class="matrix-sub">Chi tiết phân quyền truy cập các tính năng cốt lõi theo từng chức danh nhân sự.</p>
    </div>

    <div class="matrix-table-wrap">
      <div class="matrix-header-row">
        <span>Module & Hành Động</span>
        <span class="role-col-hdr">Owner</span>
        <span class="role-col-hdr">Manager</span>
        <span class="role-col-hdr">Receptionist</span>
        <span class="role-col-hdr">Technician</span>
        <span class="role-col-hdr">Referee</span>
      </div>

      <div class="matrix-body-rows">
        <div
          v-for="(row, idx) in permissionsMatrix"
          :key="idx"
          class="matrix-row"
        >
          <div class="module-action-info">
            <strong>{{ row.module }}</strong>
            <span>{{ row.action }}</span>
          </div>

          <div class="check-col"><span v-if="row.owner" class="perm-yes">✓</span><span v-else class="perm-no">-</span></div>
          <div class="check-col"><span v-if="row.manager" class="perm-yes">✓</span><span v-else class="perm-no">-</span></div>
          <div class="check-col"><span v-if="row.receptionist" class="perm-yes">✓</span><span v-else class="perm-no">-</span></div>
          <div class="check-col"><span v-if="row.tech" class="perm-yes">✓</span><span v-else class="perm-no">-</span></div>
          <div class="check-col"><span v-if="row.referee" class="perm-yes">✓</span><span v-else class="perm-no">-</span></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.role-matrix-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.matrix-heading {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
}

.matrix-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.matrix-table-wrap {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
  margin-top: 8px;
}

.matrix-header-row {
  display: grid;
  grid-template-columns: 2fr repeat(5, 100px);
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
}

.role-col-hdr {
  text-align: center;
}

.matrix-row {
  display: grid;
  grid-template-columns: 2fr repeat(5, 100px);
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.matrix-row:last-child {
  border-bottom: 0;
}

.module-action-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.module-action-info span {
  font-size: 11px;
  color: var(--muted);
}

.check-col {
  text-align: center;
  font-weight: 800;
}

.perm-yes {
  color: #10b981;
  font-size: 15px;
}

.perm-no {
  color: var(--muted);
  opacity: 0.3;
}
</style>
