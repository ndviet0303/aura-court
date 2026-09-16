## Purpose

Defines a worker-free operating model with host-triggered scheduling and synchronous job execution while preserving durable delivery and safe deployment recovery.

## ADDED Requirements

### Requirement: Host-owned schedule execution
Each deployed environment SHALL have one host-owned scheduling trigger running every minute against its existing application container, without a dedicated scheduler container. Existing business schedule frequencies and application timezone semantics MUST remain unchanged. Repeated ticks MUST NOT concurrently execute the same protected task, and invocation failures MUST be visible to operators.

#### Scenario: A scheduled task becomes due
- **WHEN** the host trigger invokes a healthy application at the task's scheduled time
- **THEN** the application SHALL evaluate its existing schedule and execute the due task without a scheduler container

#### Scenario: A previous task is still executing
- **WHEN** another tick occurs while that protected task is active
- **THEN** a second concurrent instance SHALL NOT execute and unrelated due tasks SHALL remain eligible

#### Scenario: The application container is unavailable
- **WHEN** the host attempts to invoke the application schedule
- **THEN** the failure SHALL be recorded for operators and future minute ticks SHALL remain enabled

### Requirement: Synchronous execution without queue services
Application and deployment defaults SHALL select synchronous job execution. Normal deployment and development startup MUST NOT launch a queue worker or scheduler service. Jobs SHALL execute within their caller's process; when after-commit delivery applies, execution SHALL wait for the outer transaction to commit and SHALL be discarded on rollback. Redis-backed cache and realtime functionality MUST remain available.

#### Scenario: A job is dispatched without a transaction
- **WHEN** an application operation dispatches a job in synchronous mode
- **THEN** the job SHALL execute before that dispatch completes without requiring a worker process

#### Scenario: A source transaction rolls back
- **WHEN** a transaction producing a notification, realtime event or provider side effect is rolled back
- **THEN** that side effect SHALL NOT be delivered

#### Scenario: Deployment uses repository defaults
- **WHEN** an operator starts the updated deployment with the documented environment
- **THEN** synchronous execution SHALL be effective and no dedicated queue or scheduler container SHALL be present

### Requirement: Durable failure recovery without workers
Transient failures of required after-commit delivery and persisted payment-event processing MUST remain recoverable without a queue worker. Due persisted failures SHALL be retried through host-triggered scheduling using bounded batches and backoff; one failing item MUST NOT prevent attempts for other eligible items. Recovery MUST preserve organization scope and existing business/financial idempotency. Unrecoverable or exhausted items SHALL remain inspectable for operator action rather than being silently discarded.

#### Scenario: A delivery fails after a successful business commit
- **WHEN** delivery fails transiently after the booking or payment has committed
- **THEN** the business state SHALL remain committed, the failure SHALL be durably recorded and a later eligible scheduled attempt SHALL retry delivery

#### Scenario: A failed payment event is retried
- **WHEN** scheduled recovery and provider redelivery encounter the same persisted event
- **THEN** the system SHALL preserve one logical financial outcome within the event's organization

#### Scenario: A recovery run encounters a failing item
- **WHEN** one eligible item fails during a bounded recovery run
- **THEN** its error and next attempt SHALL be retained and other eligible items in that run SHALL still be processed

#### Scenario: Delivery execution was interrupted
- **WHEN** a delivery claim has expired after its execution process terminated
- **THEN** the persisted item SHALL become eligible for safe recovery rather than remain permanently locked

### Requirement: Observable synchronous failures
The application MUST NOT report an uncommitted business operation as successful. A failed secondary delivery MUST NOT turn an already committed booking/payment into an apparent business rejection when that delivery has been durably retained for retry. Primary processing failures SHALL remain visible through the existing error contract, with payment-event recovery state retained where applicable. Operators MUST NOT interpret an empty synchronous queue as proof that delivery is healthy.

#### Scenario: Notification delivery fails after checkout
- **WHEN** checkout commits successfully but its notification delivery fails
- **THEN** the response SHALL preserve the successful booking outcome and the delivery failure SHALL remain available for retry and inspection

#### Scenario: Primary payment processing fails
- **WHEN** verified payment-event handling fails before completing its business transaction
- **THEN** the system SHALL NOT claim successful processing and SHALL retain the persisted event and diagnostic state for recovery

### Requirement: Reversible execution-mode cutover
Deployment instructions SHALL include backlog inventory, resolution of pending/reserved/delayed work on the previous queue connection, effective runtime configuration checks, scheduler ownership transfer and rollback. Cutover MUST NOT delete pending work, failed-job history or financial records. Only one scheduling owner SHALL be active at a time.

#### Scenario: Old asynchronous work remains
- **WHEN** the operator prepares to remove workers and discovers outstanding work
- **THEN** cutover SHALL remain blocked until that work is drained or explicitly accounted for with a durable recovery path

#### Scenario: Rollback is required
- **WHEN** synchronous execution or host scheduling fails acceptance checks
- **THEN** the operator SHALL be able to restore the recorded previous queue configuration and services without duplicating scheduler ownership or deleting committed data
