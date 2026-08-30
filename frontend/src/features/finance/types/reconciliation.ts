export type ReconciliationStatus = 'matched' | 'mismatch' | 'running' | 'failed' | 'pending'

export type RiskLevel = 'low' | 'medium' | 'high' | 'critical'

export interface ReconciliationRun {
  id: number
  organization_id?: number
  run_date: string
  status: ReconciliationStatus
  provider_total: number
  journal_total: number
  settlement_total: number
  payout_total: number
  mismatch_amount: number
  details?: Record<string, any>
  started_at?: string
  completed_at?: string
  created_at?: string
  // UI helper fields
  channel?: string
  txnCode?: string
  riskLevel?: RiskLevel
  statusLabel?: string
}

export interface HealthTrendPoint {
  date: string
  status: string
  provider_total: number
  journal_total: number
  settlement_total: number
  mismatch_amount: number
}

export interface ReconciliationStats {
  provider_total: number
  journal_total: number
  settlement_total: number
  payout_total: number
  mismatch_total: number
  open_alerts_count: number
  match_rate: number
  last_reconciled_at: string
  sync_status: string
  health_trend: HealthTrendPoint[]
}

export interface ReconciliationFilterParams extends Record<string, any> {
  status?: string
  date_from?: string
  date_to?: string
  channel?: string
  search?: string
  per_page?: number
}

export interface ReconciliationAiDiagnosis {
  has_issue: boolean
  severity: string
  root_cause: string
  recommended_action: string
  confidence_score: number
  analyzed_at: string
}

export interface ReconciliationDetail {
  run: ReconciliationRun
  provider_events: any[]
  journals: any[]
  ai_diagnosis: ReconciliationAiDiagnosis
  details: Record<string, any>
}

export interface LiveReconEvent {
  id: string
  timestamp: string
  type: 'sync' | 'mismatch' | 'auto_resolve' | 'payout_verified'
  title: string
  description: string
  channel?: string
  amount?: number
}
