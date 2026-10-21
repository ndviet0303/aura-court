## ADDED Requirements

### Requirement: Single organization-scoped venue write path
Venue creation and venue information updates SHALL go through one write path that names the organization in the request path. That path SHALL require an unlocked account holding the owner role of that organization, SHALL enforce the organization's venue quota, SHALL apply the rules of "Venue information validation" and SHALL record an audit entry with the actor and time. A created venue SHALL belong to the organization named in the path, never to another membership of the caller, and SHALL start inactive. No other endpoint SHALL create, update or delete a venue; the former generic administration endpoints for venue create, update and delete SHALL answer 404. Venues and courts SHALL NOT be deletable through any endpoint; ending their operation SHALL go through closure.

#### Scenario: Owner of two organizations creates a venue
- **WHEN** a user who owns organizations A and B creates a venue through the write path that names organization B
- **THEN** the venue SHALL belong to organization B with status inactive
- **AND** it SHALL NOT appear in public venue or court lists

#### Scenario: Caller is not an owner of the named organization
- **WHEN** a staff member, a customer, an owner of another organization or a platform admin calls the venue write path for organization A
- **THEN** the request SHALL be refused with 403 and no venue SHALL be created or changed

#### Scenario: Retired write endpoints
- **WHEN** any client calls a former generic venue create, update or delete endpoint, or a venue or court delete endpoint
- **THEN** the platform SHALL answer 404 and no venue, court, booking or payment record SHALL change

### Requirement: Venue information validation
Venue create and update requests SHALL trim leading and trailing whitespace from every text value before validating it and SHALL store the trimmed value. The venue name SHALL be required with 2–100 characters. The address SHALL be required with 10–255 characters. The contact phone SHALL be required and SHALL be either 10 digits starting with 0, or +84 followed by 9 digits. The contact email SHALL be required, SHALL have a valid address format, SHALL contain no whitespace and SHALL have at most 254 characters. The description SHALL be optional with at most 2000 characters. Amenities SHALL be an optional list of at most 20 items, each with 1–50 characters. A request with any invalid field SHALL be rejected with 422 and an error for each invalid field, and SHALL store nothing, so the saved values stay unchanged. The owner interface SHALL show each error next to its field and keep the values the owner entered. A venue information update SHALL NOT change the venue's status, operating hours, court schedules or prices; those change only through their own functions. A saved change SHALL be visible on every screen that shows the venue from the next request, without any review step. A venue with no amenities SHALL show no amenities; no default amenity list SHALL be invented.

#### Scenario: Boundary values
- **WHEN** an owner submits a name of 1 or 101 characters, an address of 9 characters, the phone "012345678" or "+8412345678", the email "a b@x.vn" or an email of 255 characters, or a description of 2001 characters
- **THEN** the request SHALL be rejected with 422 naming each invalid field and nothing SHALL be saved

#### Scenario: Accepted formats and trimming
- **WHEN** an owner submits the name " Sân A " with the phone "0912345678" or "+84912345678" and otherwise valid values
- **THEN** the venue SHALL be saved with the name "Sân A" and the given phone

#### Scenario: Partly invalid update
- **WHEN** an owner updates a venue with a valid new name and an invalid phone
- **THEN** the request SHALL be rejected with 422 on the phone field and neither the name nor the phone SHALL change

#### Scenario: Amenity limits
- **WHEN** an owner submits 21 amenities, or one amenity of 51 characters
- **THEN** the request SHALL be rejected with 422 and nothing SHALL be saved

#### Scenario: No amenities declared
- **WHEN** a venue has no saved amenities
- **THEN** every venue and court view SHALL show no amenities

#### Scenario: Field errors in the form
- **WHEN** the server rejects a venue form with errors on the phone and address fields
- **THEN** the form SHALL stay open, show each message next to its field and keep the entered values

### Requirement: Venue and court images
Owners SHALL add venue and court images only by uploading files to the platform; no interface SHALL accept an image link. Each venue and each court SHALL hold at most 10 images. Each uploaded image SHALL be a JPG, JPEG or PNG file by both extension and content, with a size of at most 5 MB. An upload with another type or a larger size SHALL be rejected with 422 and the file SHALL NOT be stored. A venue or court save SHALL accept as images only files uploaded to the platform and the built-in court images that the platform itself serves; a save with more than 10 images, or with any other image reference, such as a link to another website, SHALL be rejected with 422 and nothing SHALL be saved. A court SHALL hold a list of images rather than a single image.

#### Scenario: Image type and size
- **WHEN** an owner uploads a 5.1 MB PNG, a GIF or a WebP file
- **THEN** the upload SHALL be rejected with 422 and no file SHALL be stored

#### Scenario: Valid image
- **WHEN** an owner uploads a 4.9 MB JPG for a court
- **THEN** the upload SHALL succeed and the image SHALL be addable to that court's image list

#### Scenario: Too many images or an external link
- **WHEN** an owner saves a venue or court with 11 images, or with an image link to another website
- **THEN** the save SHALL be rejected with 422 and the stored images SHALL stay unchanged

#### Scenario: Built-in court image
- **WHEN** an owner saves a court whose image list holds a built-in court image that the platform serves, such as one assigned by seed data
- **THEN** the save SHALL be accepted and that image SHALL be kept

### Requirement: Court code identity
Every court SHALL belong to exactly one venue and SHALL have an internal id that never changes. The court code SHALL be the court's display name, and a court SHALL have no separate name field. The code SHALL be required with 1–50 characters after trimming, and SHALL be unique within its venue after trimming and case-insensitive comparison; the same code MAY exist at different venues. The court description SHALL be optional with at most 2000 characters. A duplicate or invalid code SHALL be rejected with 422 and nothing SHALL change. Every link and API path SHALL identify a court by its internal id; the code SHALL never be used to resolve a court. After a code change, the new code SHALL be shown on the court and on every related booking, including historical ones, while the court id, booking links, usage times, amounts and booking statuses stay unchanged.

#### Scenario: Duplicate code in the same venue
- **WHEN** a venue already has court "A1" and the owner creates or renames another court of that venue to " a1 "
- **THEN** the request SHALL be rejected with 422 and no court SHALL change

#### Scenario: Same code at two venues
- **WHEN** two venues each have a court with code "Sân 1" and a client requests each court by its id
- **THEN** both courts SHALL exist and each request SHALL return the court with that id

#### Scenario: Code limits
- **WHEN** an owner submits an empty code, a code of 51 characters or a description of 2001 characters
- **THEN** the request SHALL be rejected with 422 and nothing SHALL be saved

#### Scenario: Renamed court on bookings
- **WHEN** an owner changes a court's code from "Sân 1" to "Sân VIP"
- **THEN** existing and historical bookings of that court SHALL show "Sân VIP" with the same court id, usage time, amount and status

#### Scenario: Existing court names become codes
- **WHEN** the change is migrated onto existing data
- **THEN** each court's code SHALL be its current display name, trimmed, codes that collide within one venue SHALL be made distinct, and no court id or booking link SHALL change

### Requirement: Venue operating hours and court service schedules
The owner SHALL set the venue's operating hours for each weekday, mark closed days, and record dated exceptions for the venue and for its courts. Each court SHALL have a service schedule of weekday intervals, either its own or inherited from the venue, and the court views SHALL show which one applies. A court's service intervals and court-level exceptions SHALL lie entirely within the venue's operating hours for the same weekday or date. Intervals of one court on one day SHALL NOT overlap; the end of one interval MAY equal the start of the next. A court schedule or exception that breaks these rules SHALL be rejected with 422 identifying the offending intervals, and nothing SHALL be saved. A court SHALL be bookable only at times when both the venue and the court are open; when the venue's hours shrink, the court's effective service time SHALL be the part of its schedule inside the new venue hours. A court with no service interval SHALL NOT be treated as open around the clock. Only the owner SHALL change operating hours, exceptions and service schedules; any other caller SHALL receive 403. Changes that affect existing bookings SHALL follow "Affected bookings from operational changes".

#### Scenario: Court schedule outside venue hours
- **WHEN** a venue is open 08:00–22:00 and the owner saves a court interval 06:00–23:00
- **THEN** the save SHALL be rejected with 422 identifying that interval

#### Scenario: Touching and overlapping intervals
- **WHEN** the owner saves court intervals 08:00–12:00 and 12:00–22:00 inside venue hours 08:00–22:00
- **THEN** the schedule SHALL be saved
- **AND** intervals 08:00–12:00 and 11:00–13:00 on the same day SHALL be rejected with 422

#### Scenario: Venue hours shrink
- **WHEN** venue hours change from 08:00–22:00 to 10:00–20:00 while a court has its own interval 08:00–12:00
- **THEN** that court SHALL be bookable only from 10:00 to 12:00 that weekday and a quote at 08:00 SHALL be refused

#### Scenario: Staff tries to change hours
- **WHEN** an assigned staff member submits new operating hours, an exception or a court schedule
- **THEN** the request SHALL be refused with 403 and nothing SHALL change

### Requirement: Court price table link
When setting up a court, the owner SHALL select the court's price table from the price tables that belong to the same venue as the court, and the platform SHALL store that court–price table link. Selecting a price table of another venue or another organization SHALL be rejected with 422 and the existing link SHALL stay unchanged. The court screens SHALL NOT accept or edit prices directly; they SHALL show the linked price table and the prices that price management resolves for the court's service schedule, marking any uncovered interval. The court screen and price management SHALL read and write the same court–price table link. The price table itself, its kinds, its management, how the link is stored, the link's effective times and the way prices are resolved SHALL be defined by price management. A change of the linked price table or of its prices SHALL NOT change the amounts of confirmed bookings. Only the owner SHALL change the link; any other caller SHALL receive 403.

#### Scenario: Link a price table of the same venue
- **WHEN** the owner selects a price table of the court's venue for that court
- **THEN** the link SHALL be stored and SHALL be returned in the court's detail and in price management

#### Scenario: Price table of another venue
- **WHEN** the owner selects a price table that belongs to another venue
- **THEN** the request SHALL be rejected with 422 and the court's link SHALL stay unchanged

#### Scenario: Confirmed booking after a price change
- **WHEN** the prices of a court's linked price table change after a booking on that court was confirmed
- **THEN** the booking SHALL keep its recorded unit prices and total amount

### Requirement: Venue opening conditions
The owner SHALL open an inactive venue through a dedicated open action. The venue SHALL open only when all of these hold: its information satisfies "Venue information validation", including name, address, phone and email; it has weekly operating hours with at least one open interval; and it has no active suspension. If any condition fails, the action SHALL be rejected with 422 listing every missing item, and the status SHALL stay inactive. On success the venue SHALL become active and its courts SHALL keep their own statuses. Opening SHALL NOT require any platform review. Partner approval, the platform plan or trial, the verified owner collection connection, the booking policy, courts and prices SHALL NOT block opening; they are conditions of "Unified public and new-booking eligibility". The open action SHALL apply only to an inactive venue: a paused venue SHALL return through resume and a closed venue SHALL never reopen, and both SHALL be rejected with 422. Only the owner SHALL open a venue; staff and platform admins SHALL receive 403.

#### Scenario: Missing items
- **WHEN** the owner opens a venue that has no contact phone and no operating hours
- **THEN** the action SHALL be rejected with 422 listing the phone and the operating hours, and the venue SHALL stay inactive

#### Scenario: Suspended venue
- **WHEN** the owner opens an inactive venue that has an active suspension
- **THEN** the action SHALL be rejected with 422 listing the suspension

#### Scenario: Opening without courts or payment setup
- **WHEN** the owner opens a venue with valid information and operating hours but no court, no platform plan and no verified collection connection
- **THEN** the venue SHALL become active
- **AND** it SHALL accept no booking until a court is opened and the booking eligibility conditions hold

#### Scenario: Courts stay as they are
- **WHEN** the owner opens a venue that has two inactive courts
- **THEN** the venue SHALL become active and both courts SHALL stay inactive

#### Scenario: Wrong source status or actor
- **WHEN** the owner uses the open action on a paused or closed venue, or a staff member or platform admin uses it on any venue
- **THEN** the owner's request SHALL be rejected with 422, the others SHALL be refused with 403, and no status SHALL change

### Requirement: Court opening conditions
The owner SHALL open each inactive court separately through a dedicated open action, at any time after the court's conditions hold. A court SHALL open only when all of these hold: it has a code and a sport; its effective service schedule has at least one interval inside the venue's operating hours; it is linked to a price table of the same venue whose prices, as price management resolves them, give exactly one price for every bookable interval of that schedule; and its venue is active with no active suspension. If any condition fails, the action SHALL be rejected with 422 listing every missing item, including each uncovered interval, and the court SHALL stay inactive. On success the court SHALL become active. The open action SHALL apply only to an inactive court: a paused court SHALL return through resume and a closed court SHALL never reopen, and both SHALL be rejected with 422. Only the owner SHALL open a court; any other caller SHALL receive 403.

#### Scenario: No service schedule
- **WHEN** the owner opens a court that has no service interval
- **THEN** the action SHALL be rejected with 422 listing the service schedule

#### Scenario: Price gap
- **WHEN** the court serves 08:00–22:00 and its linked price table has no price for 21:00–22:00
- **THEN** the action SHALL be rejected with 422 listing the uncovered interval 21:00–22:00

#### Scenario: Venue not open
- **WHEN** the owner opens a court whose venue is inactive, paused, closed or suspended
- **THEN** the action SHALL be rejected with 422 listing the venue condition

#### Scenario: All conditions met
- **WHEN** the owner opens a court with a code, a sport, a service schedule inside venue hours and a fully covering linked price table, at an active venue without suspension
- **THEN** the court SHALL become active and SHALL be bookable when the booking eligibility conditions hold

#### Scenario: Paused or closed court
- **WHEN** the owner uses the open action on a paused or closed court
- **THEN** the action SHALL be rejected with 422 and the status SHALL stay unchanged

### Requirement: Operational stops for venues and courts
A venue or court SHALL stop serving only through an operational stop. A stop SHALL have a kind: maintenance (a court only, for maintenance, damage or incidents), business pause (a venue or a court) or closure (a venue or a court). A pause is a maintenance stop or a business pause. The request SHALL carry a reason of 10–1000 characters after trimming, a start that is the time of confirmation or later, and an optional expected end that SHALL be after the start. A missing or invalid reason, a start in the past, or an expected end not after the start SHALL be rejected with 422 and nothing SHALL be saved. The expected end SHALL be informational only and SHALL never end a stop or reopen a venue or court. From the moment a stop is confirmed, a new booking SHALL be refused when its time overlaps the stop window: a booking from s to e overlaps a stop that starts at t and has a recorded end r when e > t and, if r exists, s < r; a booking that ends exactly at t SHALL NOT overlap. This blocking SHALL NOT depend on any scheduled processing having run. Before the start, the object's status SHALL stay unchanged; once the start is reached, a pause SHALL set the status to paused and a closure SHALL set it to closed, and a paused object SHALL return to active only through resume. A venue stop SHALL block every court of the venue without changing any court's status; a court stop SHALL affect only that court. A pause SHALL be accepted only for an active object: a pause on an inactive, paused or closed object SHALL be rejected with 422 as an invalid transition, and a pause on an active object that already has an open pause, such as one scheduled to start later, SHALL be rejected with 409. A closure SHALL be accepted for an inactive, active or paused object and SHALL never end. Stops SHALL never be deleted; ending a stop SHALL record the end time and the actor. Existing bookings SHALL never be cancelled by a stop; they SHALL be handled through "Affected bookings from operational changes". Court status events SHALL follow "Broadcast court status updates".

#### Scenario: Future pause blocks at once
- **WHEN** the owner confirms a business pause of a court starting at T plus two days
- **THEN** a new booking at T plus three days SHALL be refused immediately, a booking ending exactly at T plus two days SHALL be accepted, and the court SHALL stay active until T plus two days

#### Scenario: Open-ended stop
- **WHEN** a maintenance stop has no recorded end
- **THEN** a new booking on that court one year later SHALL be refused

#### Scenario: Expected end passes
- **WHEN** the expected end of a maintenance stop passes without a resume
- **THEN** the court SHALL stay paused and new bookings SHALL still be refused

#### Scenario: Invalid stop requests
- **WHEN** a stop request has a reason of 9 or 1001 characters, a start in the past, or an expected end equal to or before the start
- **THEN** the request SHALL be rejected with 422 and no stop SHALL be saved

#### Scenario: Status not yet updated
- **WHEN** a stop's start has passed but the stored status has not been updated yet
- **THEN** new bookings in the stop window SHALL still be refused

#### Scenario: Venue pause keeps court statuses
- **WHEN** a venue with one active and one paused court is paused for business
- **THEN** both courts SHALL keep their own statuses and new bookings on both SHALL be refused

#### Scenario: Second pause
- **WHEN** an active court already has an open pause scheduled to start later and another pause is requested for it
- **THEN** the request SHALL be rejected with 409 and nothing SHALL be saved
- **AND** a pause requested for an inactive, paused or closed court SHALL be rejected with 422 and nothing SHALL be saved

#### Scenario: Maintenance stop reaches its start
- **WHEN** a maintenance stop of an active court reaches its start
- **THEN** the court SHALL become paused
- **AND** it SHALL become active again only when an authorized actor resumes it, never at the expected end

#### Scenario: Existing maintenance blocks
- **WHEN** the change is migrated onto existing data
- **THEN** each existing court maintenance block SHALL become a maintenance stop with its old end as its recorded end, so it blocks the same times as before

### Requirement: Stop and resume authority
The owner SHALL create and end stops of every kind at the venues and courts of the owner's organization. Which stops an assigned staff member may create and end SHALL follow "Role-based owner operations"; staff SHALL NOT act on any venue not assigned to them, even in the same organization, and such requests SHALL be refused with 403. Platform admins SHALL NOT open, pause, resume or close any venue or court and SHALL receive 403; their only status-related action SHALL be suspension. Locked accounts SHALL receive 403, and a revoked assignment or membership SHALL take effect from the next request.

#### Scenario: Sibling venue
- **WHEN** a staff member assigned only to venue A records a stop on a court of venue B of the same organization
- **THEN** the request SHALL be refused with 403

#### Scenario: Platform admin
- **WHEN** a platform admin requests to pause, resume, close or open a venue or court
- **THEN** the request SHALL be refused with 403 and the status SHALL stay unchanged

### Requirement: Resuming paused venues and courts
Resume SHALL apply only to a paused venue or court; a closed object SHALL never be resumed and an inactive object SHALL use the open action, and both SHALL be rejected with 422. A court SHALL resume only when the conditions of "Court opening conditions" hold, other than the court being inactive: it has a code and a sport, its effective service schedule has at least one interval inside the venue's operating hours, it is linked to a price table of the same venue that gives exactly one price for every bookable interval of that schedule, and its venue is active with no active suspension. A venue SHALL resume only when its information satisfies "Venue information validation", it has weekly operating hours with at least one open interval, and it has no active suspension; its courts SHALL NOT be checked. If any condition fails, the resume SHALL be rejected with 422 listing every missing item. A successful resume SHALL end the open pause at the moment of confirmation, recording the end time and the actor, and SHALL set the status to active. The court SHALL accept new bookings from that moment under its schedule, prices and booking eligibility. Resuming a venue SHALL NOT change any court's status: only active courts that meet "Unified public and new-booking eligibility", including a service schedule with full price coverage, SHALL accept bookings again, while paused, inactive and closed courts SHALL stay as they are. A resume SHALL NOT restore bookings that were cancelled, moved or rescheduled during the stop, and SHALL NOT lift a venue suspension.

#### Scenario: Court resume while the venue is paused
- **WHEN** a court is resumed while its venue is paused
- **THEN** the resume SHALL be rejected with 422 listing the venue condition

#### Scenario: Court resume with a price gap
- **WHEN** a paused court is resumed while its linked price table leaves part of its schedule unpriced
- **THEN** the resume SHALL be rejected with 422 listing the uncovered intervals

#### Scenario: Successful court resume
- **WHEN** the owner resumes a paused court whose conditions hold
- **THEN** the stop SHALL remain stored with its end time and actor, the court SHALL be active, and a booking one hour later SHALL be accepted

#### Scenario: Venue resume with paused courts
- **WHEN** the owner resumes a paused venue whose courts are all paused
- **THEN** the venue SHALL become active and every court SHALL stay paused

#### Scenario: Venue resumed with a partially priced active court
- **WHEN** the owner resumes a paused venue that has one active court whose schedule is fully priced and one active court whose linked price table leaves 21:00–22:00 unpriced
- **THEN** the venue SHALL become active, both courts SHALL stay active, the fully priced court SHALL accept new bookings, and every new booking for the partially priced court SHALL be refused

#### Scenario: Suspended or closed object
- **WHEN** the owner resumes a venue that has an active suspension, or resumes a closed venue or court
- **THEN** the resume SHALL be rejected with 422 and nothing SHALL change

#### Scenario: Handled bookings are not restored
- **WHEN** a court resumes after one affected booking was moved to another court and one was cancelled
- **THEN** the moved booking SHALL stay on its new court and the cancelled booking SHALL stay cancelled

### Requirement: Read access to venues and courts
The owner SHALL read every venue and court of the owner's organization. Which venues an assigned staff member reads, the status codes for unassigned venues, for venue identifiers outside the named organization, for customers, outsiders, locked accounts and platform administrators, and the effect of a revoked assignment or membership, SHALL follow "Organization-scoped venue and court reads". Readable venue information SHALL include name, address, phone, email, description, images, amenities, weekly operating hours with closed days, dated exceptions and status. Readable court information SHALL include code, sport, description, images, the service schedule with whether it is inherited from the venue, and the operating status. The owner and staff SHALL have read-only venue and court detail views; staff views SHALL show no action to create or edit venues or courts, change prices, open, close or business-pause, and SHALL offer only the maintenance stop and maintenance resume actions. The owner's venue view SHALL show an active suspension with its reason and a link to the decision. When the organization has no venue, the owner's list SHALL be empty with an action to add a venue.

#### Scenario: Staff court detail
- **WHEN** an assigned staff member opens a court detail
- **THEN** the view SHALL show code, sport, description, images, service schedule and operating status without edit, price, open, close or business-pause actions

#### Scenario: Owner venue detail
- **WHEN** the owner opens a venue detail
- **THEN** the read-only view SHALL show name, address, phone, email, description, images, amenities, weekly hours with closed days and status

#### Scenario: Organization without venues
- **WHEN** an owner whose organization has no venue opens the venue list
- **THEN** the list SHALL be empty and SHALL offer to add a venue

### Requirement: Affected bookings from operational changes
The platform SHALL detect the bookings affected by a change of venue operating hours or closed days, by creating, changing or deleting a venue or court dated exception, by a change of a court's service schedule, by any operational stop, and by a venue suspension. A booking SHALL be affected when it is held (awaiting payment), confirmed or in use (checked in), and not cancelled, expired, completed or no-show, ends after the current time, and either overlaps the stop window as defined in "Operational stops for venues and courts", using the start for a stop or suspension without a recorded end and never the expected end, or no longer lies entirely within the new effective service time. A venue-level change SHALL check every court of the venue; a court-level change SHALL check only that court.

Before saving such a change, the actor SHALL be able to preview the affected bookings, and owner-hosted sessions on the affected courts and times, without saving anything. A save whose affected list is not empty SHALL be accepted only with an explicit acknowledgement of that list; without it the save SHALL be rejected with 409 carrying the affected list, and nothing SHALL be saved. With the acknowledgement the change SHALL be saved even while bookings remain unhandled, so an incident stop can take effect at once. Affected bookings SHALL keep their status, slots and amounts and SHALL never be cancelled, moved, rescheduled or marked no-show automatically.

On each saved change the platform SHALL open one handling case per affected booking and source, so that repeating the same save creates no second case. A case SHALL have one of the statuses pending, contacting or unreachable, which are open, or moved, time_changed, cancelled or kept, which are closed. A new case SHALL be pending.

The owner SHALL list the cases of the organization's venues, and an assigned staff member SHALL list only the cases of venues assigned to them. Each row SHALL show the booking code, the customer's name and phone, the court code, the usage time, the payment status, the source with its time window, and the handling status. The list SHALL filter by venue, court, source and handling status, SHALL show open cases by default, and SHALL leave out bookings that are cancelled, expired or completed. The owner or assigned staff SHALL record the outcomes contacting, unreachable and kept with the contact channel (phone, Zalo or chat) and a note; recording an outcome SHALL change nothing on the booking. The outcomes moved, time_changed and cancelled SHALL be set only by the operator commands in "Operator handling of affected bookings". Only the owner SHALL record a note that a refund was arranged outside the platform; staff SHALL receive 403. No outcome SHALL move money, create a refund record or label a booking as refunded. A booking with an open case SHALL NOT be marked no-show; such a request SHALL be rejected with 409. Every outcome SHALL be recorded with its actor and time. Requests for cases of a venue the caller may not operate SHALL be refused with 403. The platform admin's preview for a suspension SHALL show the number of courts and affected bookings with booking code, court and time, without customer contact details.

#### Scenario: Incident stop over a confirmed booking
- **WHEN** staff preview a maintenance stop starting now on a court with a confirmed booking in two hours, then confirm with acknowledgement
- **THEN** the preview SHALL list that booking, the stop SHALL be saved, the booking SHALL stay confirmed and a pending case SHALL be opened for it

#### Scenario: Checked-in booking during an incident stop
- **WHEN** staff stop a court for maintenance starting now while a checked-in booking is in use on it, then confirm with acknowledgement
- **THEN** the preview SHALL list that booking, a pending case SHALL be opened for it, the booking SHALL stay in use, and the move-court command of "Operator handling of affected bookings" SHALL accept it

#### Scenario: Save without acknowledgement
- **WHEN** the owner saves new venue hours that leave a confirmed booking outside them without acknowledging the affected list
- **THEN** the save SHALL be rejected with 409 carrying the affected list and the old hours SHALL stay in force

#### Scenario: Deleted special-hours exception
- **WHEN** the owner deletes a special-hours exception that extended a date's hours and a booking lies in the extended hours
- **THEN** the preview SHALL list that booking and the deletion SHALL require acknowledgement

#### Scenario: Overlap boundaries
- **WHEN** an open-ended stop starts at T
- **THEN** a booking ending exactly at T SHALL NOT be affected, a booking one month later SHALL be affected, and cancelled, expired, completed, no-show and past bookings SHALL NOT be affected

#### Scenario: Venue and court scope
- **WHEN** the owner previews a business pause of a venue that has bookings on two courts, then a closure of only one court
- **THEN** the venue preview SHALL list the bookings of both courts and the court preview SHALL list only that court's bookings

#### Scenario: Repeated save
- **WHEN** the same acknowledged change is saved twice
- **THEN** each affected booking SHALL have exactly one case for that source

#### Scenario: Unreachable customer
- **WHEN** staff record the outcome unreachable for a case
- **THEN** the booking's status, slots and amount SHALL stay unchanged and the case SHALL stay open

#### Scenario: Expired hold leaves the case list
- **WHEN** a held booking with an open case expires unpaid
- **THEN** the default case list SHALL no longer show it, and the booking SHALL stay expired

#### Scenario: Refund note by staff
- **WHEN** a staff member records a note that a refund was arranged outside the platform
- **THEN** the request SHALL be refused with 403

#### Scenario: No-show on an open case
- **WHEN** staff mark a booking with an open case as no-show, including a case marked unreachable
- **THEN** the request SHALL be rejected with 409 and the booking SHALL keep its status and slots

#### Scenario: Staff of another venue
- **WHEN** a staff member assigned only to venue B lists or updates the cases of venue A
- **THEN** the request SHALL be refused with 403

### Requirement: Venue suspension and remediation
Any platform admin with an unlocked account SHALL suspend a venue by giving a reason of 10–1000 characters after trimming; another length SHALL be rejected with 422. Before confirming, the admin SHALL be able to view the impact as defined in "Affected bookings from operational changes". A venue SHALL have at most one active suspension; suspending a venue that already has one SHALL be rejected with 409. A suspension SHALL take effect when confirmed: every court of the venue SHALL be refused for new bookings, including bookings from earlier quotes, while the venue's and courts' statuses stay unchanged and existing bookings stay as they are with handling cases opened for them. The decision SHALL record the venue, reason, time and admin. Suspension SHALL be separate from the venue's status and SHALL NOT be created, changed or lifted by any status change.

While a venue is suspended, its owner SHALL still read and edit venue and court information, images and schedules and handle affected bookings, but SHALL NOT open or resume the venue or its courts. Owners and staff SHALL have no way to lift a suspension. The owner SHALL read the suspension decisions of the organization's venues with reason, time, acting admin, lift time, lifting admin and conclusion, and, for each remediation submission of a decision, oldest first, its content, its evidence files through time-limited links, its state, the admin's response, and the submission and response times; staff, customers and other organizations SHALL be refused with 403. Platform admins SHALL read them through "Platform venue monitoring".

Only an active owner of the venue's organization SHALL submit a remediation for one specific active suspension decision of that venue. A submission SHALL carry remediation content of 10–5000 characters after trimming and 1–10 evidence files, each a PDF, JPG, JPEG or PNG file of at most 10 MB. A submission that breaks these rules SHALL be rejected with 422 and nothing, including files, SHALL be stored. A remediation request SHALL have the states "Chờ xem xét" (pending review), "Yêu cầu bổ sung" (more information requested), "Từ chối" (rejected) and "Chấp thuận" (approved), and its state SHALL be the state of its latest submission. A first submission SHALL be accepted only when the decision has none; a later submission SHALL be accepted only when the latest one is more information requested or rejected. A submission while the latest is pending review or approved, or for a lifted decision, SHALL be rejected with 409. A decision SHALL have at most one pending submission, even under concurrent requests. Every submission SHALL be immutable: earlier content, files and responses SHALL be kept and no edit or delete SHALL exist. Submitting SHALL NOT lift the suspension. Evidence files SHALL be readable only by owners of that organization and platform admins, through time-limited links.

Any platform admin SHALL list remediation requests, filter them by state with pending review first and oldest first, and open a request with its suspension decision, reason, all submissions, evidence and earlier responses. For a pending request the admin SHALL choose request_more_info, reject or approve, each with a non-blank response after trimming; a missing response SHALL be rejected with 422. A review of a request that is not pending SHALL be rejected with 409 and SHALL overwrite nothing. request_more_info SHALL set more information requested and reject SHALL set rejected, and both SHALL keep the suspension. approve SHALL set approved and, in the same atomic operation, lift the suspension with the lift time, the admin and the response as conclusion. The review SHALL record the admin, time, outcome and response. Approval SHALL be the only way to lift a suspension and SHALL NOT change the venue's or any court's status: a paused or closed venue SHALL stay unbookable, and at an active venue only active courts with a service schedule and full price coverage SHALL accept bookings again.

The platform SHALL send in-platform notifications that link directly to the related detail: to every active owner of the venue's organization when the venue is suspended and when a review outcome is recorded, and to every platform admin when a remediation is submitted or resubmitted. These notifications SHALL NOT go to staff or customers. Decisions, submissions and review responses SHALL be kept for later reference.

#### Scenario: Suspension reason length
- **WHEN** a platform admin suspends a venue with a reason of 9 or 1001 characters
- **THEN** the request SHALL be rejected with 422 and the venue SHALL stay unsuspended
- **AND** a reason of 10 characters SHALL be accepted

#### Scenario: Already suspended
- **WHEN** a platform admin suspends a venue that has an active suspension
- **THEN** the request SHALL be rejected with 409 and the active decision SHALL stay unchanged

#### Scenario: Suspension keeps statuses and bookings
- **WHEN** a platform admin suspends a paused venue that has a confirmed future booking and a customer then checks out with a quote issued before the suspension
- **THEN** the venue SHALL stay paused, every court SHALL keep its status, the booking SHALL stay confirmed with a pending case, and the checkout SHALL be refused with 409 without creating a booking

#### Scenario: Owner during suspension
- **WHEN** the owner of a suspended venue edits a court's code and images, then tries to open that court and to resume the venue
- **THEN** the edits SHALL be saved, both actions SHALL be rejected with 422 listing the suspension, and the suspension SHALL stay active

#### Scenario: Remediation validation
- **WHEN** the owner submits content of 9 or 5001 characters, no file, 11 files, a DOCX file or an 11 MB file
- **THEN** the submission SHALL be rejected with 422 and no submission or file SHALL be stored

#### Scenario: Pending request blocks a new submission
- **WHEN** the owner submits again while the latest submission is pending review
- **THEN** the submission SHALL be rejected with 409

#### Scenario: Resubmission keeps history
- **WHEN** the owner resubmits after the admin requested more information
- **THEN** a new pending-review submission SHALL be stored and the earlier submission's content, files and response SHALL stay unchanged

#### Scenario: Owner tracks a remediation request
- **WHEN** the admin requests more information on the owner's first submission and the owner then resubmits
- **THEN** the owner's view of the decision SHALL list both submissions oldest first, each with its content, evidence files, state and times, the first with the admin's response, and the request SHALL show pending review

#### Scenario: Not the owner
- **WHEN** a staff member, an owner of another organization or a customer submits a remediation for the venue
- **THEN** the request SHALL be refused with 403

#### Scenario: Review rules
- **WHEN** a platform admin reviews a pending request without a response, or reviews a request that is no longer pending
- **THEN** the first SHALL be rejected with 422, the second with 409, and nothing SHALL change

#### Scenario: Approval lifts without reopening
- **WHEN** a platform admin approves the pending request of a suspended paused venue
- **THEN** the request SHALL be approved and the suspension lifted in one operation, and the venue SHALL stay paused and unbookable

#### Scenario: Approval at an active venue
- **WHEN** a platform admin approves the request of a suspended active venue with one active fully priced court, one active court whose linked price table leaves part of its schedule unpriced, and one inactive court
- **THEN** only the fully priced active court SHALL accept new bookings

#### Scenario: Notification recipients
- **WHEN** a venue is suspended, its owner submits a remediation and an admin records a review outcome
- **THEN** the venue's owners SHALL receive the suspension and outcome notifications, every platform admin SHALL receive the submission notification, each SHALL link to the related detail, and no staff member or customer SHALL receive them

### Requirement: Platform venue monitoring
Any platform admin with an unlocked account SHALL list the venues of every organization, filtered by organization, status, suspension and keyword, and SHALL open a venue's monitoring detail. The detail SHALL show the owning organization, the owners' names and contacts, the venue's name, address, phone, email, operating hours, status, active suspension and suspension history with reason, time, acting admin and lift details, and every court with code, sport, description, images, service schedule and operating status. Monitoring SHALL be read-only: platform admins SHALL NOT change venue information, operating hours, schedules, prices or courts, and such requests SHALL be refused with 403. Every monitoring list and detail request SHALL be recorded as an audit entry with the admin, the venue or query, and the time. Non-admin callers SHALL be refused with 403.

#### Scenario: Cross-organization list and detail
- **WHEN** a platform admin without any organization membership lists venues and opens one
- **THEN** venues of all organizations SHALL be listed and the detail SHALL show every field above, including each court's schedule and status

#### Scenario: Audited view
- **WHEN** a platform admin opens a venue's monitoring detail
- **THEN** an audit entry SHALL record the admin, the venue and the time

#### Scenario: No editing through monitoring
- **WHEN** a platform admin tries to change a venue's information, hours or prices
- **THEN** the request SHALL be refused with 403 and nothing SHALL change

#### Scenario: Non-admin access
- **WHEN** an owner, staff member or customer requests the monitoring list or detail
- **THEN** the request SHALL be refused with 403

## MODIFIED Requirements

### Requirement: Catalog and operational calendar
Authorized venue operators SHALL persist identity, address/location, contact details, description, image collection, amenities, sports, court surface/capacity and operational state. Weekly opening intervals, dated exceptions, holidays and operational stops SHALL be persisted by venue/court, timezone-aware and used identically by discovery, quote, booking, recurrence and community inventory. Holidays here SHALL be closed days of the service calendar and SHALL carry no price; holiday pricing SHALL be defined by price management. A change of opening hours, dated exceptions or a court's service schedule, or an operational stop, that affects existing bookings SHALL be saved only after the actor previews and acknowledges the affected bookings as defined in "Affected bookings from operational changes"; those bookings SHALL keep their status, slots and amounts and SHALL remain valid until they are played or handled by an authorized operator.

#### Scenario: Closed slot
- **WHEN** an operator closes a court period without existing obligations
- **THEN** all clients and direct booking calls SHALL show/refuse the same closed interval after reload on another device

#### Scenario: Existing bookings
- **WHEN** a proposed schedule change, exception or stop overlaps confirmed bookings or sessions
- **THEN** the change SHALL be saved only after the listed affected bookings and sessions are acknowledged, without silently cancelling, moving or rescheduling them

#### Scenario: Catalog persistence
- **WHEN** an owner saves contact information, venue images or court surface/capacity
- **THEN** owner and staff detail views, and public views of an active venue, SHALL show the same saved values from the next request

### Requirement: Catalog and publication contract completeness
Venue creation, update and opening APIs and court creation, update and opening APIs SHALL resolve valid request handlers, preserve all supported fields, enforce organization ownership and version checks, and return documented structures consumed by the actual UI. Public venue and court list/detail/search/cache responses SHALL use the same eligibility as new bookings and SHALL NOT expose inactive, paused, closed or suspended venues, or courts that are not active. Public responses SHALL show the venue's current saved information; there SHALL be no separately approved public version. Public responses SHALL NOT include the owning organization's private record. Rollout flags MUST NOT silently admit ineligible new tenants.

#### Scenario: Known draft identifier
- **WHEN** a guest requests by ID the detail of a court that is not active, or of a court of an inactive venue, including one migrated from a draft
- **THEN** private content SHALL not be returned

#### Scenario: Readiness and owner creation
- **WHEN** an owner creates a venue and a court and runs their opening actions through the UI
- **THEN** requests SHALL reach valid handlers and missing items SHALL be reported rather than an internal error

#### Scenario: Subscription expires
- **WHEN** a previously cached public venue loses entitlement
- **THEN** new public availability and booking SHALL be invalidated or re-evaluated without relying on the stale cache

#### Scenario: Edit of an active venue
- **WHEN** the owner changes an active venue's description
- **THEN** the public venue detail SHALL show the new description on the next request without any review step

### Requirement: Court lifecycle preservation
Pausing or closing a court or a venue SHALL preserve the venue, court, booking and payment history and SHALL prevent new reservations from the stop's start. Venues and courts SHALL NOT be deleted; closure SHALL be the only way to end their operation. A closed venue or court SHALL remain readable in history and SHALL never accept reservations again.

#### Scenario: Deactivate court
- **WHEN** an operator pauses or closes a court with booking history
- **THEN** history remains accessible and new reservations from the stop's start are rejected

#### Scenario: Data kept after closure
- **WHEN** a venue and its courts are closed while they have reservations and payments
- **THEN** every venue, court, reservation and payment record SHALL still exist and SHALL be returned by the history views

#### Scenario: Closed court stays closed
- **WHEN** the owner tries to open or resume a closed court
- **THEN** the request SHALL be rejected with 422 and the court SHALL stay closed

### Requirement: Unified public and new-booking eligibility
Public discovery, detail, availability and all new-booking entry points, including quotes, holds, checkout, recurring series and owner-hosted sessions, SHALL use the same effective eligibility: approved active partner, valid plan/trial, a venue in active status with no active suspension, a court in active status whose effective service schedule is fully priced as required by "Court opening conditions", with exactly one price for every bookable interval, no operational stop of the venue or court overlapping the requested time, a requested time inside the court's effective service time, an applicable configured price, a saved venue booking policy, and a current verified owner collection connection for the venue. Partner approval, plan/trial, booking policy and the collection connection SHALL be booking eligibility conditions only and SHALL NOT block opening a venue or court. Direct requests MUST recheck eligibility when the hold or booking is created, serialized with that creation, including requests that carry only a previously issued quote. A slot resale purchase SHALL be a new-booking entry point with a narrower check: under the lock that serializes the purchase it SHALL recheck the venue and court statuses, suspension, any operational stop overlapping the slot, partner approval, plan/trial and the collection connection, and SHALL NOT recheck price coverage, the applicable price, the booking policy or the effective service time, because the slot keeps its recorded time and price. Suspension, stops, expiry or closure MUST NOT be bypassed by stale UI, cached availability, a previously issued quote or a legacy activation toggle. Interfaces MUST NOT display a substitute or default price for a court that has no applicable configured price.

#### Scenario: Direct booking on nonpublic venue
- **WHEN** a customer submits a known court identifier belonging to an inactive venue
- **THEN** no new booking SHALL be created and public APIs SHALL NOT disclose private venue content

#### Scenario: Cached availability after suspension
- **WHEN** an owner is suspended after a customer loaded available slots
- **THEN** subsequent new-booking creation SHALL be refused even if the customer's screen still displays those slots

#### Scenario: Quote issued before a stop or suspension
- **WHEN** a customer holds or checks out with only a quote issued before the venue was suspended, paused or closed
- **THEN** the request SHALL be refused with 409 and no reservation SHALL be created

#### Scenario: Court without a configured price
- **WHEN** an active court of an active venue has no applicable configured price for any bookable time, and another active court has no applicable configured price for one requested time
- **THEN** the first court SHALL be omitted from public lists and its public detail SHALL NOT be returned, the time without an applicable price SHALL be omitted from the second court's availability, new bookings for either SHALL be refused, and no interface SHALL show a substitute price

#### Scenario: Active court with partial price coverage
- **WHEN** an active court of an active, unsuspended venue has a linked price table that leaves 21:00–22:00 of its service schedule unpriced
- **THEN** every new booking for that court, including times that have a price, SHALL be refused, and public lists, detail and availability SHALL treat the court as not bookable

#### Scenario: Lifted suspension on a paused venue
- **WHEN** the suspension of a paused venue is lifted
- **THEN** new bookings at that venue SHALL still be refused

#### Scenario: Open venue without a collection connection
- **WHEN** an active venue with an active, fully priced court has no current verified collection connection
- **THEN** every new booking for that court, including online checkout, SHALL be refused with 409 and setup guidance for the owner, without creating a booking, hold or payment attempt

#### Scenario: Resale purchase after a stop or suspension
- **WHEN** a buyer tries to lock a resale listing after the slot's venue was suspended, paused or closed, its court stopped or left active status, or the venue lost its partner approval, plan or verified collection connection
- **THEN** these conditions SHALL be rechecked under the lock that serializes the purchase, the request SHALL be refused with 409, and no buyer lock, seller payment instructions or entitlement transfer SHALL be created

#### Scenario: Database deadlock while locking
- **WHEN** the database aborts a hold, checkout, recurring series, owner-hosted session, resale purchase or eligibility recheck because of a deadlock (SQLSTATE 40P01)
- **THEN** the request SHALL be answered with 409 and a Vietnamese message asking the user to try again, not with 500, and no booking, hold, payment attempt or buyer lock SHALL persist

### Requirement: Venue lifecycle and captured policy continuity
Venues and courts SHALL each have exactly one of four operating statuses: inactive ("Chưa hoạt động"), active ("Hoạt động"), paused ("Tạm ngừng") and closed ("Ngừng kinh doanh"). A new venue or court SHALL start inactive and SHALL NOT accept bookings. An inactive object SHALL become active only through its open action; an active object SHALL become paused only when a pause takes effect; a paused object SHALL become active only through resume; an inactive, active or paused object SHALL become closed when a closure takes effect; closed SHALL be final. Any other transition SHALL be rejected with 422. No endpoint other than the open, pause, resume and close commands SHALL change a venue's or court's status; the former generic status-transition endpoints SHALL answer 404 without changing any record. A venue's status SHALL NOT be written onto its courts, and each court SHALL keep its own status while its venue is paused, closed or suspended. Suspension SHALL be a separate venue-level record that never changes the venue's or any court's status; courts SHALL have no suspension of their own. Suspension, pause, closure and expiry SHALL block new business but preserve historical and future obligations. Each venue's booking policy SHALL be versioned and SHALL define the booking window, minimum and maximum durations, arrival rules, recurring terms and resale terms, and SHALL NOT define cancellation, refund or change terms. Price and policy changes SHALL not rewrite existing booking snapshots; authorized servicing SHALL use the captured policy and record explicit revisions.

#### Scenario: Close a venue with outstanding bookings
- **WHEN** an operator closes a venue with confirmed bookings
- **THEN** new business SHALL stop, authorized servicing of the existing bookings SHALL remain available, and any settlement with affected customers SHALL happen outside the platform without being presented as a platform refund

#### Scenario: Policy changes after booking
- **WHEN** the owner publishes a new booking policy
- **THEN** existing bookings SHALL retain their accepted terms rather than silently inherit the new policy

#### Scenario: Restore suspended venue
- **WHEN** the owner of a suspended venue tries to open or resume it or one of its courts, a staff member assigned to it tries to resume a court from their own maintenance stop, and a staff member requests any other status change of the venue or its courts
- **THEN** the owner's and the maintenance resume requests SHALL be rejected with 422, the other staff request SHALL be refused with 403, the suspension SHALL stay in force, and only an approved remediation review SHALL lift it

#### Scenario: Suspension does not rewrite status
- **WHEN** a paused venue is suspended and the suspension is later lifted
- **THEN** the venue SHALL be paused throughout and SHALL stay paused after the lift

#### Scenario: Retired status-transition endpoints
- **WHEN** any client calls a former generic venue or court status-transition endpoint, including the partner operational-status and lifecycle endpoints and the platform admin lifecycle endpoint
- **THEN** the platform SHALL answer 404 and no venue, court, stop, booking or payment record SHALL change

#### Scenario: Closed is final
- **WHEN** any actor tries to open or resume a closed venue or court
- **THEN** the request SHALL be rejected with 422 and the status SHALL stay closed

#### Scenario: Existing venue and court statuses are mapped
- **WHEN** the change is migrated onto existing data
- **THEN** draft, pending publication and revision required venues SHALL become inactive, published and active venues active, temporarily closed venues paused, and closed or archived venues closed
- **AND** each suspended venue SHALL get an active suspension record and the status it had before suspension, or paused when that is unknown
- **AND** courts that accepted bookings SHALL become active and the other courts paused

### Requirement: Area and nearby court discovery
Customer court discovery SHALL group and filter public courts by the venue's area (district, falling back to city) rather than by venue identity, and SHALL show each court's area so same-named courts at different venues stay distinguishable. Venues MAY store a validated latitude/longitude pair, which SHALL be shown from the venue's current saved information. On explicit request, the client MAY use the device's position to sort areas and courts nearest first; that position MUST stay on the device, MUST NOT be sent to the server or stored, and venues without coordinates SHALL sort last. Denied or unavailable location SHALL leave discovery usable and explain how to recover.

#### Scenario: Filter by area
- **WHEN** a customer selects an area such as Cầu Giấy
- **THEN** only courts whose venue lies in that area SHALL be offered, each labelled with its area

#### Scenario: Nearest first
- **WHEN** a customer grants location access from the "near me" control
- **THEN** areas and courts SHALL be ordered by distance, the nearest court SHALL be selected and its distance shown, without any request carrying the customer's coordinates

#### Scenario: Location denied
- **WHEN** the browser denies or cannot provide a position
- **THEN** the original ordering SHALL remain and an explanation SHALL be shown

### Requirement: Courts carry their own sport and booking interval
An organization SHALL own venues, and each venue SHALL own courts directly, with no grouping layer between venue and court. Every court SHALL record exactly one sport from the supported list (badminton, football_5v5, football_7v7, tennis, pickleball, basketball, volleyball, table_tennis) and a booking interval length in minutes, which defaults to 60. The owner SHALL choose the sport when creating a court; the court SHALL belong to one venue of the owner's organization that is not closed, so an inactive venue can receive its first court before it opens, and the new court SHALL start inactive. A court's sport and venue SHALL NOT change after creation: an update that changes either SHALL be rejected with 422 and SHALL change nothing. A court update SHALL change only the code, description and images, plus the retained surface, capacity, booking interval length and amenities. An unsupported sport SHALL be rejected. Creating a venue SHALL NOT create any court or court grouping implicitly. Availability, quotes, booking and recurring sessions SHALL take the interval length from the court itself. Sport filters on public court and venue lists SHALL match the courts' own sport. The platform SHALL NOT expose sport-zone list, detail or management endpoints.

#### Scenario: Operator creates a court
- **WHEN** the owner creates a court with sport pickleball and no interval length while one of their venues is selected
- **THEN** the court SHALL be stored under that venue with sport pickleball, a 60-minute interval and status inactive, and SHALL appear under the pickleball filter only after it is opened and its venue is eligible

#### Scenario: Court on an inactive or closed venue
- **WHEN** the owner creates a court on an inactive venue, on a closed venue, and on a venue of another organization
- **THEN** the first SHALL be created inactive, the second SHALL be rejected with 422 and the third SHALL be refused with 403

#### Scenario: Sport or venue change
- **WHEN** the owner updates a court with a different sport or a different venue
- **THEN** the request SHALL be rejected with 422 and the court SHALL stay unchanged

#### Scenario: Unsupported sport
- **WHEN** an operator submits a court with a sport outside the supported list
- **THEN** the request SHALL be rejected with a validation error and no court SHALL be created or changed

#### Scenario: Sport filter on public lists
- **WHEN** a guest lists courts or venues filtered by sport tennis
- **THEN** only courts whose own sport is tennis, and only venues with at least one active tennis court, SHALL be returned

#### Scenario: New venue has no implicit grouping
- **WHEN** an owner creates a venue
- **THEN** the venue SHALL have no courts until the owner adds them, and no sport grouping SHALL be created

#### Scenario: Retired sport-zone endpoints
- **WHEN** any client requests a sport-zone list, detail or management endpoint
- **THEN** the platform SHALL answer 404

#### Scenario: Existing courts keep their sport and interval
- **WHEN** the change is migrated onto existing data
- **THEN** every court SHALL keep the sport and interval length of the grouping it belonged to, courts without a grouping SHALL get badminton and 60 minutes, each court SHALL keep its own active state, and no court's price or interval length SHALL change

## REMOVED Requirements

### Requirement: Report-aligned draft venue readiness
**Reason**: Report Module 02 (BR-33, FR-34, step 5) lets the owner open a venue directly once its required information and operating hours are set and it is not suspended, and opens each court separately under its own conditions. The readiness gate (active court, prices, collection connection, booking policy, partner approval, plan and quotas) and the submission for review came from the previous report version.
**Migration**: Replaced by "Venue opening conditions" and "Court opening conditions". Partner approval, plan/trial, booking policy and the verified collection connection move to "Unified public and new-booking eligibility"; the venue quota stays enforced on creation by "Single organization-scoped venue write path". The booking policy definition moves to "Venue lifecycle and captured policy continuity". Retire the readiness and submit-for-publication endpoints and screens. Draft, pending publication and revision required venues become inactive.

### Requirement: Independent publication review
**Reason**: Report Module 02 has no platform publication review. The owner opens venues and courts; the platform admin only monitors, suspends and reviews remediation (ACT-05, steps 13–16).
**Migration**: Remove the publication queue, review and revision endpoints and screens. Public reads show the venue's current saved values and the approved public snapshot is no longer used. Published venues become active; pending publication and revision required venues become inactive. Platform authority over venues is limited to "Venue suspension and remediation" and "Platform venue monitoring".
