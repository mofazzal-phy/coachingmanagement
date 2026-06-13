ADMIN SIDE ENROLLMENT WORKFLOW PROMPT

Build a complete smart Enrollment Workflow for a Coaching Management System using:

Laravel 12
Modular Architecture (Modules/)
Vue 3 + Composition API
Pinia
MySQL
JWT Authentication
Spatie Permission
REST API
Clean Architecture
Repository + Service Pattern

The project already has these modules:

Enrollment Module
Admission Module
Students Module
Academic Module

Do NOT recreate those modules.

Instead, extend and integrate them to create a full professional Enrollment Workflow.

MAIN GOAL

Admin should be able to:

Manually enroll a student
Create new student during enrollment
Enroll existing student
Select course & batch
Receive admission fee
Verify payment
Confirm enrollment
Generate invoice
Assign batch
Handle pending/confirmed states
Manage waiting list
Auto update seat count

The workflow must behave like a real ERP system.

COMPLETE ENROLLMENT FLOW
Dashboard
   ↓
Manual Enrollment
   ↓
Choose Enrollment Type
   ↓
(New Student OR Existing Student)
   ↓
Fill Student Information
   ↓
Fill Academic Information
   ↓
Select Course & Batch
   ↓
Check Seat Availability
   ↓
Upload Documents
   ↓
Review Information
   ↓
Receive Admission Fee
   ↓
Verify Payment
   ↓
Confirm Enrollment
   ↓
Generate Student ID
   ↓
Assign Batch
   ↓
Generate Invoice/Receipt
   ↓
Send Notifications
ENROLLMENT TYPES
TYPE 1 — New Student Enrollment

Admin creates:

student account
guardian info
academic info

Then proceeds to enrollment.

TYPE 2 — Existing Student Enrollment

Admin searches existing student using:

Student ID
Phone Number
Email

Then directly enrolls student into course/batch.

ADMIN PAGES REQUIRED

Create these admin pages:

Manual Enrollment Wizard
Enrollment List
Pending Enrollments
Confirmed Enrollments
Payment Verification
Waiting List
Enrollment Details
Invoice View
ENROLLMENT WIZARD STEPS
STEP 1 — Enrollment Type

Options:

New Student
Existing Student
Import Student
STEP 2 — Student Information

Fields:

Full Name
Phone
Email
DOB
Gender
Address
Photo

Guardian fields:

Guardian Name
Guardian Phone
Relation
Occupation

Validation:

phone unique
required guardian
STEP 3 — Academic Information

Fields:

School/College
Class
Group
GPA
Previous Institute
STEP 4 — Course & Batch Selection

Admin selects:

Course
Batch
Shift
Admission Date
Admission Fee
Discount

System should automatically show:

Total Seats
Booked Seats
Available Seats
SEAT MANAGEMENT RULES

IF:

available_seat > 0

THEN:

allow enrollment

ELSE:

move to waiting list
STEP 5 — DOCUMENT UPLOAD

Allow upload:

Student Photo
Birth Certificate
SSC Marksheet
NID (optional)

Store files securely.

Use:

storage/app/public
STEP 6 — REVIEW INFORMATION

Show complete summary:

Student Info
Guardian Info
Academic Info
Course Info
Fee Info
Documents

Admin must confirm before payment.

STEP 7 — PAYMENT COLLECTION

Payment methods:

Cash
bKash
Nagad
Rocket
Bank
Card

Fields:

Amount
Received Amount
Payment Date
Transaction ID
Payment Note
PAYMENT STATUS FLOW

Possible statuses:

unpaid
pending
verification_pending
paid
failed
refunded
PAYMENT LOGIC

IF:

payment_status = paid

THEN:

confirm enrollment

ELSE:

keep enrollment pending
STEP 8 — ENROLLMENT CONFIRMATION

After successful payment:

System must:

Create enrollment
Generate enrollment number
Generate student ID if not exists
Assign batch
Reduce available seats
Update enrollment status
STUDENT ID FORMAT
STU-2026-000245
ENROLLMENT NUMBER FORMAT
ENR-2026-000145
STEP 9 — INVOICE GENERATION

Generate:

Money Receipt
Admission Invoice
Enrollment Slip

Features:

Printable
PDF Download
Barcode/QR optional
STEP 10 — NOTIFICATIONS

Automatically send:

Student Notifications
Enrollment Confirmed
Payment Received
Batch Assigned
Admin Notifications
New Enrollment
Payment Completed
Seat Full Warning

Channels:

SMS
Email
In-App
WhatsApp
WAITING LIST FEATURE

IF:

batch full

THEN:

add student to waiting list

Fields:

waiting_position
priority
remarks
ENROLLMENT STATUS

Possible statuses:

pending
confirmed
cancelled
waiting
inactive
REQUIRED DATABASE OPERATIONS

During enrollment use:

DB::transaction()

Inside transaction:

Create/Update Student
Create Application
Create Payment
Create Enrollment
Assign Batch
Update Seat Count
Create Invoice
Create Notifications

Rollback if any step fails.

REQUIRED SERVICES

Create/Use services:

EnrollmentService
StudentService
PaymentService
BatchService
InvoiceService
NotificationService
REQUIRED REPOSITORIES

Use repositories:

EnrollmentRepository
StudentRepository
PaymentRepository
BatchRepository
API ENDPOINTS
Enrollment APIs
POST   /api/admin/enrollments
GET    /api/admin/enrollments
GET    /api/admin/enrollments/{id}
PUT    /api/admin/enrollments/{id}
Payment APIs
POST /api/admin/payments/collect
POST /api/admin/payments/verify
Waiting List APIs
POST /api/admin/waiting-list/add
POST /api/admin/waiting-list/approve
FRONTEND REQUIREMENTS

Use:

Vue 3 Composition API
Pinia
Axios
Reusable Components
Dynamic Forms
Step Wizard
REQUIRED UI FEATURES
Multi Step Enrollment Wizard
Searchable Student Selector
Batch Seat Indicator
Real-time Fee Calculation
Discount Support
Status Badges
Invoice Preview
Enrollment Timeline
DASHBOARD STATISTICS

Show:

Total Enrollments
Pending Enrollments
Confirmed Enrollments
Today's Revenue
Seat Occupancy
Waiting Students
FILTERS REQUIRED

Enrollment list filters:

Course
Batch
Status
Date Range
Payment Status
Student Search
SECURITY REQUIREMENTS

Use:

JWT Authentication
Spatie Permission
Form Request Validation
Policy Authorization
Secure File Upload
Rate Limiting
REQUIRED ROLES
Super Admin
Admin
Admission Officer
Accountant
REQUIRED PERMISSIONS
create_enrollment
edit_enrollment
confirm_payment
view_invoice
manage_waiting_list
IMPORTANT BUSINESS RULES
RULE 1

Enrollment confirmation ONLY after successful payment verification.

RULE 2

Batch seat must decrease automatically after confirmed enrollment.

RULE 3

If payment fails:

keep enrollment pending
RULE 4

If batch full:

move student to waiting list
RULE 5

Prevent duplicate enrollment in same course/batch.

CODE QUALITY RULES

MUST FOLLOW:

Clean Code
SOLID Principles
Reusable Components
API Resource Responses
Service Layer
Repository Pattern
DTO if needed
Form Requests
Events & Listeners
Queue Notifications
EXPECTED FINAL RESULT

A complete ERP-level Enrollment Workflow where admin can:

manually enroll students
manage payment
assign batches
confirm enrollment
generate invoices
manage waiting list
monitor enrollment status

with a modern scalable architecture and professional workflow.