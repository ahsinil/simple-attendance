# Dynamic Barcode + GPS Attendance System — Full Blueprint

## 1. System Overview

A secure attendance system that verifies **who**, **when**, and **where** an employee is present using:

1. Authenticated user login
2. Time-based dynamic barcode (rotates every 5 minutes)
3. GPS location validation

This design prevents proxy attendance, screenshot reuse, and remote check-ins.

---

## 2. Core Principles

* Barcode is **not identity** — it is a temporary proof of physical presence
* User identity comes from authenticated session/token
* All verification happens on the **server**
* System must tolerate small clock drift & GPS inaccuracy

---

## 3. High-Level Architecture

```
[Office Display]
   (Dynamic Barcode)
         ↓
[Employee Device]
  Web / Mobile App
         ↓
[API Backend]
  (Laravel)
         ↓
[Database + Cache]
 MySQL + Redis
```

---

## 4. Technology Stack (Recommended)

### Backend

* Laravel (API-based) v12 with Laravel Boost (MCP Server)
* MySQL (persistent data)
* Redis (barcode cache & rate limit)
* Laravel Scheduler (barcode rotation)

### Frontend

* Web: Vue / Blade + JS barcode scanner
* CSS Framework: **TailwindCSS** with dark mode support

### UI Design

All UI designs are built with **TailwindCSS** and support both **light and dark modes**.

Sample designs available in `/sample-design/`:

| Page | File | Description |
| ---- | ---- | ----------- |
| Login | `login-page.html` | Employee/Admin login page |
| Employee Dashboard | `employee-dashboard.html` | Main dashboard with check-in, stats, activity |
| Employee Schedule | `employee-schedules.html` | Calendar view of assigned shifts |
| Manual Request | `employee-request-manual.html` | Form to request manual attendance |
| Employee Settings | `employee-settings.html` | Profile, security, registered devices |
| Admin Dashboard | `dashboard.html` | Overview for admins |
| Manual Approval | `manual-attendace-approval.html` | Admin view for approving requests |
| Reports | `reports.html` | Attendance reports and analytics |
| Shift Management | `shift-management.html` | Create and manage shifts |
| Shift Planning | `shift-planning.html` | Assign shifts to employees |
| User Management | `user-management.html` | Manage employees and roles |

#### Design System

* **Primary Color**: `#2b8cee`
* **Background Light**: `#f6f7f8`
* **Background Dark**: `#101922`
* **Surface Dark**: `#1a2632`
* **Border Dark**: `#324d67`
* **Font**: Manrope (Google Fonts)
* **Icons**: Material Symbols Outlined

#### Dark Mode Implementation

```html
<html class="dark">
  <!-- Toggle via class on html element -->
</html>
```

TailwindCSS config:

```javascript
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "primary": "#2b8cee",
                "background-light": "#f6f7f8",
                "background-dark": "#101922",
                "surface-dark": "#1a2632",
            },
            fontFamily: {
                "display": ["Manrope", "sans-serif"]
            }
        }
    }
}
```

### Hardware

* TV / Tablet / Monitor to display barcode
* Camera or USB scanner on employee device

---

## 5. Database Design

### users

```
id
name
email
password
status
created_at
updated_at
```

### locations

```
id
code                -- OFFICE-JKT-01
name
latitude
longitude
allowed_radius_m
timezone            -- e.g., 'Asia/Jakarta'
created_at
updated_at
```

### attendance

```
id
user_id
location_id
scan_time
check_type          -- IN / OUT
gps_lat
gps_lng
gps_accuracy_m
distance_m
time_slot
ip_address
device_id           -- (optional) browser/device fingerprint for audit
created_at
updated_at
```

> **Note:** `device_id` and `ip_address` are captured for audit logging only. 
> The system does not enforce device registration or restrict which devices can be used.

### attendance_logs (optional)

```
id
user_id
reason
payload
created_at
updated_at
```

---

## 5.1 Timezone Handling

> [!IMPORTANT]
> All timestamps are stored in **UTC** in the database.

* Each location has a `timezone` field (e.g., `Asia/Jakarta`)
* Server converts UTC to local time for display
* Client sends timestamps in UTC
* Shift times are interpreted in the location's timezone

**Conversion on display:**

```
display_time = attendance.scan_time.to_timezone(location.timezone)
```

**Why UTC?**
* Consistent storage across multi-timezone offices
* Avoids daylight saving issues
* Easy comparison and reporting

---

## 5.2 Holiday Calendar (Overtime Calculation)

Holidays are used to calculate **overtime pay**, not to skip attendance.

### holidays

```
id
date
name                -- "New Year's Day"
type                -- NATIONAL / COMPANY / OPTIONAL
overtime_multiplier -- 2.0 (200% pay)
created_at
updated_at
```

### Holiday Types

| Type     | Description                          | Overtime |
| -------- | ------------------------------------ | -------- |
| NATIONAL | Government holidays                  | 2x       |
| COMPANY  | Company-specific holidays            | 2x       |
| OPTIONAL | Optional holidays (e.g., religious)  | 1.5x     |

---

### 5.2.1 Overtime Calculation Logic

When an employee works on a holiday:

```
if is_holiday(attendance.date):
    holiday = get_holiday(attendance.date)
    overtime_hours = attendance.work_minutes / 60
    overtime_pay = overtime_hours * hourly_rate * holiday.overtime_multiplier
```

### 5.2.2 Overtime Fields (attendance extended)

```
is_holiday          -- true / false
overtime_min        -- minutes worked on holiday
overtime_multiplier -- copied from holiday record
```

---

### 5.2.3 Weekend Overtime

Optionally, weekends can also be treated as overtime:

| Day      | Overtime Multiplier |
| -------- | ------------------- |
| Saturday | 1.5x                |
| Sunday   | 2x                  |

Configurable via `app_settings`:

| Key                     | Default |
| ----------------------- | ------- |
| weekend_overtime_enabled | true   |
| saturday_multiplier     | 1.5    |
| sunday_multiplier       | 2.0    |

---

## 6. Time-Based Barcode Design

### Time Slot

* Rotation interval: 5 minutes (300 seconds)

```
time_slot = floor(current_unix_time / 300)
```

### Barcode Payload

```
payload = location_code | time_slot
```

### Hashing (HMAC)

```
barcode = HMAC_SHA256(payload, SECRET_KEY)
```

* SECRET_KEY stored only on server
* Encoded to Base32 / shortened string for QR

---

## 7. Barcode Generation Flow

1. Laravel scheduled job runs every minute
2. Calculates current time_slot
3. Generates barcode for each location
4. Stores result in Redis:

```
barcode:OFFICE-JKT-01 = current_barcode
```

5. Office display polls API every 30s

---

## 8. Office Display Screen

UI Components:

* QR / Barcode image
* Countdown timer (expires in mm:ss)
* Location name

Rules:

* Display-only (no logic)
* Never expose secret or time_slot

---

## 9. Employee Attendance Flow

```
Login → Request GPS → Scan Barcode → Submit
```

Client sends:

```
{
  barcode,
  latitude,
  longitude,
  accuracy
}
```

---

## 10. Server-Side Verification Logic

### Step 1: Authentication

* Validate session / JWT

### Step 2: Barcode Validation

```
current_slot = floor(now / 300)

for slot in [current_slot, current_slot - 1]:
    expected = HMAC(location_code | slot, SECRET)
    if expected == barcode:
        valid
```

### Step 3: GPS Validation

* Calculate distance (Haversine)
* Reject if:

  * accuracy > 100m
  * distance > allowed_radius

### Step 4: Attendance Logic

```
if no attendance today:
    create CHECK-IN
else if last record has no CHECK-OUT:
    create CHECK-OUT
else:
    reject
```

---

## 11. Distance Calculation (Haversine)

```
d = 2r * asin(
  sqrt(
    sin²((lat2-lat1)/2) +
    cos(lat1)*cos(lat2)*sin²((lng2-lng1)/2)
  )
)
```

r = 6371000 meters

---

## 12. Rate Limiting & Anti-Replay

Rules:

* 1 scan per user per time_slot
* Max 5 attempts / minute
* Reject reused barcode after success

Use Redis keys:

```
scan:user:{id}:{slot}
```

---

## 13. Device Registration (Optional Feature)

Device registration restricts attendance to approved devices only. This feature can be **enabled or disabled** via app settings.

---

### 13.1 App Settings

#### app_settings

```
id
key                 -- e.g., 'device_registration_enabled'
value               -- 'true' / 'false'
created_at
updated_at
```

Default settings:

| Key                          | Default | Description                        |
| ---------------------------- | ------- | ---------------------------------- |
| device_registration_enabled  | false   | Require registered devices         |
| max_devices_per_user         | 2       | Max devices an employee can register |

---

### 13.2 Devices Table

#### devices

```
id
user_id
device_fingerprint      -- unique hash of device info
device_name             -- "John's iPhone 15"
device_info             -- JSON: model, OS, browser, etc.
is_approved             -- true / false
registered_at
last_used_at
created_at
updated_at
```

---

### 13.3 Device Fingerprint

Captured on each scan attempt:

* **Web**: User-Agent + screen resolution + timezone + canvas fingerprint

Fingerprint is hashed and compared against registered devices.

---

### 13.4 Registration Flow

```
Employee scans for first time
        ↓
System detects new device
        ↓
If registration ENABLED:
    → Save device as PENDING
    → Admin approves/rejects
        ↓
If registration DISABLED:
    → Auto-approve, log device for audit only
```

---

### 13.5 Attendance Verification with Device Check

```
if setting('device_registration_enabled'):
    device = find_device(user_id, fingerprint)
    
    if device not found:
        reject("Device not registered")
    
    if not device.is_approved:
        reject("Device pending approval")

# Continue with normal barcode + GPS verification
```

---

### 13.6 Admin Device Management

Admin can:

* View all registered devices per user
* Approve / Reject pending devices
* Revoke approved devices
* Set max devices per user

---

## 14. Security Enhancements (Optional)

* IP whitelist (office network)
* Wi-Fi SSID validation
* Emulator detection (mobile)
* Admin override with reason

---

## 14. UX Rules (Important)

* Ask GPS permission before scan
* Show GPS accuracy status
* Clear error messages:

  * "Barcode expired"
  * "Outside office area"
  * "GPS signal weak"

---

## 15. Failure Scenarios & Handling

| Case               | Action      |
| ------------------ | ----------- |
| Barcode expired    | Ask rescan  |
| GPS weak           | Retry GPS   |
| Outside radius     | Reject      |
| Already checked in | Show status |

---

## 16. Manual Attendance Override (Admin)

### Purpose

Handle real-world exceptions when automatic attendance fails (e.g. GPS outside radius, weak signal, emergency, offsite work) while keeping the system auditable and abuse-resistant.

---

### 16.1 Trigger Conditions

Manual attendance request is allowed when:

* GPS distance > allowed radius
* GPS accuracy > threshold
* Barcode valid but location invalid

System MUST NOT auto-approve in these cases.

---

### 16.2 Employee Flow (Request Manual Attendance)

```
AUTO attendance fails
      ↓
System shows failure reason
      ↓
Employee clicks "Request Manual Attendance"
      ↓
Employee uploads photo + note
      ↓
Request saved as PENDING
```

Employee submission data:

* Photo (selfie / contextual proof)
* Reason (mandatory)
* Optional additional note

---

### 16.3 Admin Flow (Approval)

Admin Dashboard:

* View pending requests
* Inspect photo
* View GPS coordinates, distance, accuracy
* Read employee note

Admin actions:

* Approve
* Reject
* Add admin note (mandatory)

---

### 16.4 Database Additions

#### attendance (extended)

```
method          -- AUTO / MANUAL
status          -- APPROVED / REJECTED
approved_by
approved_at
```

#### attendance_requests (new)

```
id
user_id
location_id
request_time
gps_lat
gps_lng
distance_m
gps_accuracy_m
reason
photo_path
status          -- PENDING / APPROVED / REJECTED
admin_note
created_at
updated_at
```

#### attendance_logs (immutable)

```
id
attendance_id
action          -- AUTO_CHECKIN / MANUAL_APPROVE / MANUAL_REJECT
actor_id
payload
created_at
```

---

### 16.5 Admin Approval Logic

```
if request.status != PENDING:
    reject

if admin approves:
    create attendance record
    set method = MANUAL
    set status = APPROVED
    log admin action
```

---

### 16.6 Photo Upload Rules

* Max size: 2MB
* Allowed types: JPG, PNG
* Stored in private storage
* Filename includes timestamp + user_id

Photos are evidence, not identity proof.

---

### 16.7 Anti-Abuse Controls

* Limit manual requests per user per month
* Mandatory reason + admin note
* Manual attendance rate monitored
* High override rate triggers audit

---

### 16.8 Reporting & Transparency

Reports MUST clearly label manual attendance:

| Date | User | Type | Method | Approved By |
| ---- | ---- | ---- | ------ | ----------- |

Manual attendance must never be hidden or merged silently.

---

## 17. Audit & Reporting

Reports:

* Daily attendance
* Late check-ins
* Manual override rate
* Distance anomalies
* Failed scan logs

Export:

* CSV / Excel

---

## 18. Business Logic — Shifts & Schedules

### 18.1 Shift Definitions

Shifts define expected working times and rules.

#### shifts

```
id
code            -- SHIFT-MORNING
name
start_time      -- 08:00
end_time        -- 17:00
late_after_min  -- 15
allow_checkout_before_end (bool)
created_at
updated_at
```

---

### 18.2 User Schedules

Assign shifts to users by date range.

#### user_schedules

```
id
user_id
shift_id
start_date
end_date
created_at
updated_at
```

---

### 18.3 Attendance vs Shift Logic

On check-in:

* Determine active shift
* Calculate lateness
* Flag early / late / on-time

Computed fields (not user-editable):

```
status      -- ON_TIME / LATE / EARLY / ABSENT
late_min
early_leave_min
work_minutes
penalty_tier    -- NONE / WARNING / DEDUCTION / HALF_DAY / ABSENT
```

---

### 18.4 Late Penalty Tiers

Define configurable penalty levels based on lateness.

#### late_penalty_tiers

```
id
code            -- TIER-1, TIER-2, etc.
name            -- "Warning", "Deduction", etc.
min_late_min    -- 1
max_late_min    -- 15
penalty_type    -- WARNING / DEDUCTION / HALF_DAY
deduction_pct   -- 0, 25, 50 (percentage of daily pay)
created_at
updated_at
```

Default tiers:

| Tier | Late Range | Penalty | Deduction |
| ---- | ---------- | ------- | --------- |
| 1    | 1-15 min   | Warning | 0%        |
| 2    | 16-30 min  | Deduction | 25%     |
| 3    | 31-60 min  | Deduction | 50%     |
| 4    | >60 min    | Half Day | 50%      |

On check-in:

```
late_min = check_in_time - (shift_start + grace_period)

if late_min <= 0:
    penalty_tier = NONE
else:
    penalty_tier = find_tier(late_min)
```

---

### 18.5 Early Checkout Detection

If employee checks out before shift ends:

```
early_leave_min = shift_end - check_out_time

if early_leave_min > allowed_early_min:
    flag as EARLY_LEAVE
    apply penalty if configured
```

---

### 18.6 Absent Detection (Scheduled Job)

A scheduled job runs at the end of each workday to detect no-shows.

#### Job: DetectAbsentEmployees

Runs daily at: `shift_end + 1 hour` (or end of business day)

Logic:

```
for each user with active schedule today:
    if no attendance record for today:
        create attendance record:
            status = ABSENT
            penalty_tier = ABSENT
            method = SYSTEM
            
        log to attendance_logs
```

#### Absent Rules

* Only applies to users with an active shift assignment
* Skips weekends / holidays (if holiday table exists)
* Skips users on approved leave (if leave integration exists)
* Admin can manually override ABSENT to EXCUSED with reason

#### attendance status values

| Status   | Description                          |
| -------- | ------------------------------------ |
| ON_TIME  | Checked in within grace period       |
| LATE     | Checked in after grace period        |
| EARLY    | Checked in before shift start        |
| ABSENT   | No check-in detected (system-marked) |
| EXCUSED  | Admin override for valid reason      |

---

## 19. User & Role Management

### 19.1 Roles

| Role        | Capabilities                    |
| ----------- | ------------------------------- |
| Employee    | Scan attendance, request manual |
| Supervisor  | View team, recommend approval   |
| Admin       | Approve manual, manage shifts   |
| Super Admin | System config, audit            |

---

### 19.2 Role Tables

#### roles

```
id
name
created_at
updated_at
```

#### user_roles

```
user_id
role_id
created_at
```

---

### 19.3 Access Control

* Attendance scan → Employee+
* Manual approval → Admin+
* Shift management → Admin+
* Audit logs → Super Admin only

Use policy-based authorization.

---

## 20. Notifications System

### 20.1 Notification Channels

* In-app
* Email
* WhatsApp / SMS (optional)

---

### 20.2 Notification Triggers

| Event                    | Recipient            |
| ------------------------ | -------------------- |
| Late check-in            | Employee, Supervisor |
| Manual request submitted | Admin                |
| Manual approved/rejected | Employee             |
| Shift change             | Employee             |

---

### 20.3 notifications table

```
id
user_id
title
message
channel
is_read
created_at
updated_at
```

---

### 20.4 Delivery Rules

* Critical alerts: immediate
* Info alerts: batched
* All notifications logged

---

## 21. Audit & Reporting (Extended)

Additional Reports:

* Lateness by shift
* Attendance by role
* Manual override rate per supervisor
* Work hours vs scheduled hours

---

## 22. Deployment Checklist (Extended)

* [ ] Roles & permissions tested
* [ ] Shifts configured
* [ ] Notification channels verified
* [ ] Admin override audit enabled
* [ ] HR policy documented

---

End of Blueprint