import { api } from '@/core/api/client'
import type {
  MembershipPlan,
  LoyaltyConversionConfig,
  MultiplierRule,
  PlanVersionAudit,
  MembershipKpiStats,
} from '../types/membership'

export const membershipApi = {
  // Membership Plans
  getPlans: () => api.get<MembershipPlan[]>('/admin/memberships/plans'),
  createPlan: (payload: Partial<MembershipPlan>) =>
    api.post<MembershipPlan>('/admin/memberships/plans', payload),
  updatePlan: (id: string | number, payload: Partial<MembershipPlan>) =>
    api.patch<MembershipPlan>(`/admin/memberships/plans/${id}`, payload),
  archivePlan: (id: string | number) =>
    api.post<{ message: string }>(`/admin/memberships/plans/${id}/archive`, {}),

  // Loyalty Points Configuration
  getLoyaltyConfig: () => api.get<LoyaltyConversionConfig>('/admin/memberships/loyalty-config'),
  updateLoyaltyConfig: (payload: Partial<LoyaltyConversionConfig>) =>
    api.patch<LoyaltyConversionConfig>('/admin/memberships/loyalty-config', payload),

  // Multiplier Rules
  getMultiplierRules: () => api.get<MultiplierRule[]>('/admin/memberships/multiplier-rules'),
  createMultiplierRule: (payload: Partial<MultiplierRule>) =>
    api.post<MultiplierRule>('/admin/memberships/multiplier-rules', payload),
  updateMultiplierRule: (id: string, payload: Partial<MultiplierRule>) =>
    api.patch<MultiplierRule>(`/admin/memberships/multiplier-rules/${id}`, payload),
  deleteMultiplierRule: (id: string) =>
    api.delete<{ message: string }>(`/admin/memberships/multiplier-rules/${id}`),

  // Version Audits & History
  getVersionHistory: () => api.get<PlanVersionAudit[]>('/admin/memberships/version-history'),
  getStats: () => api.get<MembershipKpiStats>('/admin/memberships/stats'),
}
