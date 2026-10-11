import fs from 'node:fs'
import path from 'node:path'

interface GateVerificationOptions {
  junitPath?: string
  minTests?: number
  allowSkipped?: boolean
}

export function verifyTestGate(options: GateVerificationOptions = {}): {
  passed: boolean
  totalTests: number
  failures: number
  errors: number
  skipped: number
  flaky: number
  violations: string[]
} {
  const junitFile = options.junitPath || path.resolve(process.cwd(), 'test-results/junit.xml')
  const minExpected = options.minTests ?? 24
  const allowSkipped = options.allowSkipped ?? false

  const violations: string[] = []

  if (!fs.existsSync(junitFile)) {
    violations.push(`JUNIT_REPORT_MISSING: JUnit XML report does not exist at "${junitFile}".`)
    return {
      passed: false,
      totalTests: 0,
      failures: 0,
      errors: 0,
      skipped: 0,
      flaky: 0,
      violations,
    }
  }

  const xml = fs.readFileSync(junitFile, 'utf-8')

  // Parse root / testsuite summary attributes
  const testsMatch = xml.match(/tests="(\d+)"/i)
  const failuresMatch = xml.match(/failures="(\d+)"/i)
  const errorsMatch = xml.match(/errors="(\d+)"/i)
  const skippedMatch = xml.match(/skipped="(\d+)"/i)

  const totalTests = testsMatch ? Number(testsMatch[1]) : 0
  const failures = failuresMatch ? Number(failuresMatch[1]) : 0
  const errors = errorsMatch ? Number(errorsMatch[1]) : 0
  const skipped = skippedMatch ? Number(skippedMatch[1]) : 0

  // Count individual <failure>, <error>, <skipped> tags inside <testcase>
  const failureTagsCount = (xml.match(/<failure[\s>]/gi) || []).length
  const errorTagsCount = (xml.match(/<error[\s>]/gi) || []).length
  const skippedTagsCount = (xml.match(/<skipped[\s>]/gi) || []).length

  const effectiveFailures = Math.max(failures, failureTagsCount)
  const effectiveErrors = Math.max(errors, errorTagsCount)
  const effectiveSkipped = Math.max(skipped, skippedTagsCount)

  // Detect flaky tests: testcases with duplicate names where earlier attempt failed but later succeeded
  const testcaseNames = Array.from(xml.matchAll(/<testcase\s+name="([^"]+)"/gi)).map((m) => m[1])
  const nameOccurrences = new Map<string, number>()
  for (const name of testcaseNames) {
    nameOccurrences.set(name, (nameOccurrences.get(name) || 0) + 1)
  }
  let flaky = 0
  for (const [, count] of nameOccurrences) {
    if (count > 1) {
      flaky++
    }
  }

  // 1. Minimum test threshold check (zero-selection / missing suite protection)
  if (totalTests < minExpected) {
    violations.push(
      `TEST_COUNT_INSUFFICIENT: Expected at least ${minExpected} tests, found ${totalTests}. Zero-selection or missing tests detected!`,
    )
  }

  // 2. Failure and error check
  if (effectiveFailures > 0 || effectiveErrors > 0) {
    violations.push(
      `TEST_FAILURES_DETECTED: Found ${effectiveFailures} failure(s) and ${effectiveErrors} error(s).`,
    )
  }

  // 3. Skipped check
  if (!allowSkipped && effectiveSkipped > 0) {
    violations.push(
      `TEST_SKIPPED_FORBIDDEN: Found ${effectiveSkipped} skipped test(s). Skipped tests are forbidden in critical gates!`,
    )
  }

  // 4. Flaky test check
  if (flaky > 0) {
    violations.push(
      `FLAKY_RETRY_DETECTED: Found ${flaky} flaky test(s) that required retries. Retry-only success is forbidden in critical gates!`,
    )
  }

  return {
    passed: violations.length === 0,
    totalTests,
    failures: effectiveFailures,
    errors: effectiveErrors,
    skipped: effectiveSkipped,
    flaky,
    violations,
  }
}

// CLI entry point
if (import.meta.url === `file://${process.argv[1]}`) {
  const minArg = process.argv.find((a) => a.startsWith('--min-tests='))
  const minTests = minArg ? Number(minArg.split('=')[1]) : 24
  const allowSkipArg = process.argv.includes('--allow-skipped')
  const reportArg = process.argv.find((a) => a.startsWith('--junit='))
  const junitPath = reportArg ? reportArg.split('=')[1] : undefined

  console.log(
    `[CI Gate Verifier] Verifying gate against ${junitPath || 'test-results/junit.xml'} (minTests: ${minTests})...`,
  )
  const result = verifyTestGate({ minTests, allowSkipped: allowSkipArg, junitPath })

  if (!result.passed) {
    console.error('\n❌ CRITICAL GATE FAILURE:')
    for (const v of result.violations) {
      console.error(`  - ${v}`)
    }
    process.exit(1)
  } else {
    console.log(
      `\n✅ Gate verification PASSED: ${result.totalTests} tests executed with 0 failures, 0 skipped, 0 flaky.`,
    )
    process.exit(0)
  }
}
