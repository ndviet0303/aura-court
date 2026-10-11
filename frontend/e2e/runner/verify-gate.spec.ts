import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import os from 'node:os'
import { verifyTestGate } from './verify-gate.ts'

test('Gate Verifier: accepts clean run with 24+ passing tests', () => {
  const tmpXml = path.join(os.tmpdir(), `junit_clean_${Date.now()}.xml`)
  const testcases = Array.from(
    { length: 24 },
    (_, i) => `<testcase name="Test ${i}" time="0.1" />`,
  ).join('\n')
  fs.writeFileSync(
    tmpXml,
    `<testsuites tests="24" failures="0" errors="0" skipped="0">\n<testsuite tests="24">\n${testcases}\n</testsuite>\n</testsuites>`,
  )

  try {
    const res = verifyTestGate({ junitPath: tmpXml, minTests: 24 })
    assert.equal(res.passed, true)
    assert.equal(res.totalTests, 24)
    assert.equal(res.violations.length, 0)
  } finally {
    fs.rmSync(tmpXml, { force: true })
  }
})

test('Gate Verifier: rejects zero-selection or insufficient test count', () => {
  const tmpXml = path.join(os.tmpdir(), `junit_short_${Date.now()}.xml`)
  fs.writeFileSync(
    tmpXml,
    `<testsuites tests="5" failures="0" errors="0" skipped="0"><testsuite tests="5"></testsuite></testsuites>`,
  )

  try {
    const res = verifyTestGate({ junitPath: tmpXml, minTests: 24 })
    assert.equal(res.passed, false)
    assert.ok(res.violations.some((v) => v.includes('TEST_COUNT_INSUFFICIENT')))
  } finally {
    fs.rmSync(tmpXml, { force: true })
  }
})

test('Gate Verifier: rejects critical skipped tests', () => {
  const tmpXml = path.join(os.tmpdir(), `junit_skip_${Date.now()}.xml`)
  fs.writeFileSync(
    tmpXml,
    `<testsuites tests="24" failures="0" errors="0" skipped="1">
      <testsuite tests="24">
        <testcase name="Skipped Test"><skipped message="Skipped by test.skip"/></testcase>
      </testsuite>
    </testsuites>`,
  )

  try {
    const res = verifyTestGate({ junitPath: tmpXml, minTests: 24 })
    assert.equal(res.passed, false)
    assert.ok(res.violations.some((v) => v.includes('TEST_SKIPPED_FORBIDDEN')))
  } finally {
    fs.rmSync(tmpXml, { force: true })
  }
})

test('Gate Verifier: rejects flaky retry-only pass', () => {
  const tmpXml = path.join(os.tmpdir(), `junit_flaky_${Date.now()}.xml`)
  fs.writeFileSync(
    tmpXml,
    `<testsuites tests="24" failures="0" errors="0" skipped="0">
      <testsuite tests="24">
        <testcase name="Flaky Test Journey 1" time="0.5"><failure>Initial fail</failure></testcase>
        <testcase name="Flaky Test Journey 1" time="0.4" />
      </testsuite>
    </testsuites>`,
  )

  try {
    const res = verifyTestGate({ junitPath: tmpXml, minTests: 24 })
    assert.equal(res.passed, false)
    assert.ok(
      res.violations.some(
        (v) => v.includes('FLAKY_RETRY_DETECTED') || v.includes('TEST_FAILURES_DETECTED'),
      ),
    )
  } finally {
    fs.rmSync(tmpXml, { force: true })
  }
})

test('Gate Verifier: rejects missing JUnit report', () => {
  const res = verifyTestGate({ junitPath: '/tmp/nonexistent-report.xml', minTests: 24 })
  assert.equal(res.passed, false)
  assert.ok(res.violations.some((v) => v.includes('JUNIT_REPORT_MISSING')))
})
