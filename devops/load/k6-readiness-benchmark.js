import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Phase 0 Load & Concurrency Benchmark Suite
 * Targets:
 * - 200 peak read requests / sec
 * - 25 booking/payment write transactions / sec
 * - 2,000 concurrent sessions
 * - 100-way simultaneous court slot contention
 */

export const options = {
  scenarios: {
    // 1. Peak Read Throughput (Target: 200 RPS)
    peak_reads: {
      executor: 'constant-arrival-rate',
      rate: 200,
      timeUnit: '1s',
      duration: '30s',
      preAllocatedVUs: 50,
      maxVUs: 100,
      exec: 'testCourtAvailability',
    },
    // 2. Booking & Payment Write Baseline (Target: 25 TPS)
    write_transactions: {
      executor: 'constant-arrival-rate',
      rate: 25,
      timeUnit: '1s',
      duration: '30s',
      preAllocatedVUs: 30,
      maxVUs: 60,
      exec: 'testBookingFlow',
    },
    // 3. Concurrent User Sessions (Target: 2,000 Sessions)
    concurrent_sessions: {
      executor: 'ramping-vus',
      startVUs: 100,
      stages: [
        { duration: '30s', target: 2000 },
        { duration: '1m', target: 2000 },
        { duration: '30s', target: 0 },
      ],
      exec: 'testSessionBrowsing',
    },
  },
  thresholds: {
    'http_req_duration{scenario:peak_reads}': ['p(95)<150'], // 95% of reads < 150ms
    'http_req_duration{scenario:write_transactions}': ['p(95)<400'], // 95% of writes < 400ms
    'http_req_failed': ['rate<0.01'], // Global error rate < 1%
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export function testCourtAvailability() {
  const res = http.get(`${BASE_URL}/api/courts`);
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
}

export function testBookingFlow() {
  const payload = JSON.stringify({
    court_id: 1,
    slots: [new Date(Date.now() + 86400000).toISOString()],
  });
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${__ENV.TEST_TOKEN || 'demo-token'}`,
      'Idempotency-Key': `k6-${__VU}-${__ITER}`,
    },
  };
  const res = http.post(`${BASE_URL}/api/reservations/checkout`, payload, params);
  check(res, {
    'write handled gracefully (created or conflict)': (r) => [201, 409, 422].includes(r.status),
  });
}

export function testSessionBrowsing() {
  const res = http.get(`${BASE_URL}/api/availability?date=${new Date().toISOString().split('T')[0]}`);
  check(res, {
    'availability status 200': (r) => r.status === 200,
  });
  sleep(1);
}
