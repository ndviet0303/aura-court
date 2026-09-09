## Purpose

Provides real tenant customer records and consent-based player connections with a basic moderated community feed and durable notifications.

## ADDED Requirements

### Requirement: Customer records distinct from staff
The platform SHALL search persisted tenant customer records separately from employee memberships; discounts, wallet balances and loyalty SHALL come from authoritative entitlement and financial data. Guest contacts SHALL NOT receive fabricated user IDs or wallet balances.

#### Scenario: Customer lookup
- **WHEN** a cashier searches a known customer
- **THEN** the actual customer and permitted benefits are returned without granting staff permissions

### Requirement: Friendships and invitations
Players SHALL send, accept, decline and remove friendship requests. Only accepted connections SHALL appear as friends; match invitations SHALL target real permitted users and display individual failure outcomes.

#### Scenario: Accept friendship
- **WHEN** the addressed player accepts a pending request
- **THEN** both players see the persisted connection after reload

#### Scenario: Unauthorized response
- **WHEN** a different player responds to a request
- **THEN** the relationship is unchanged

### Requirement: Basic community feed and moderation
Authenticated players SHALL create text posts scoped to an organization or an accepted-friends audience, comment, react once per reaction type, and report posts or comments. Authors SHALL edit/delete their own content; authorized moderators SHALL hide reported organization content with an audit reason. Private content SHALL be hidden from unauthorized readers.

#### Scenario: Private post
- **WHEN** a non-friend requests a friends-only post
- **THEN** the post and its comments are not disclosed

#### Scenario: Moderation
- **WHEN** an authorized moderator hides reported content
- **THEN** ordinary readers cannot see it and the actor and reason remain auditable

### Requirement: Durable social notifications
Friendship requests, match invitations and responses, and relevant tournament updates SHALL produce recipient-scoped persisted notifications only after successful source changes. Retried delivery SHALL NOT duplicate the logical notification.

#### Scenario: Notification reload
- **WHEN** an invitee reloads after a committed match invitation
- **THEN** the invitation notification remains available and can be marked read

