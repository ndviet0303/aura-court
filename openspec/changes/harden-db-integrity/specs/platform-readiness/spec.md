## ADDED Requirements

### Requirement: Database-enforced record integrity
The data store SHALL enforce three structural rules without relying on application code:
- every persisted lifecycle status value is one of the values defined for that record type;
- every foreign key has an index whose leading columns cover the referencing columns;
- no foreign key cascades a deletion into a financial or audit record.

Three kinds of column MAY be exempt from the status rule, each with a recorded reason: a column that holds a protocol code, a column that preserves a status copied verbatim from a legacy system, and a column that stores a status reported by an external provider as received. An automated schema conformance test SHALL check all three rules against a freshly migrated database. The test MUST fail and name the table and column when a rule is broken.

#### Scenario: Unknown status value
- **WHEN** any writer stores a status value outside the defined set for that record type
- **THEN** the write SHALL be rejected and the existing record SHALL remain unchanged

#### Scenario: New foreign key without an index
- **WHEN** a migration adds a foreign key and no index covers its referencing columns
- **THEN** the schema conformance test SHALL fail, naming the table and constraint

#### Scenario: New status column without a constraint
- **WHEN** a migration adds a lifecycle status column without an allowed-value constraint and without a recorded exemption
- **THEN** the schema conformance test SHALL fail, naming the table and column

#### Scenario: New cascade into financial history
- **WHEN** a migration adds a foreign key that cascades deletion into a financial or audit table
- **THEN** the schema conformance test SHALL fail, naming the constraint

#### Scenario: Conforming schema
- **WHEN** every status column is constrained or exempt, every foreign key is indexed, and no foreign key cascades into financial history
- **THEN** the schema conformance test SHALL pass
