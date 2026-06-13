<template>
  <div class="page-container">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1>👨‍🎓 Student Details</h1>
        <p class="text-muted" v-if="student">Viewing: {{ student.first_name }} {{ student.last_name }} ({{ student.student_id }})</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline" @click="$router.push('/dashboard/students')">
          ← Back to List
        </button>
        <button
          v-if="student && student.enrollments_count > 0"
          class="btn btn-outline"
          @click="$router.push(`/dashboard/enrollment/enrollments/${student.enrollments[0]?.id}`)"
        >
          📋 View Enrollment
        </button>
        <button
          v-if="student && student.enrollments_count === 0"
          class="btn btn-primary"
          @click="goToEnroll"
        >
          🎓 Enroll Now
        </button>
        <button
          v-if="student"
          class="btn btn-primary"
          @click="$router.push(`/dashboard/students/${student.id}/edit`)"
        >
          ✏️ Edit
        </button>
        <button
          v-if="student && student.enrollments_count === 0"
          class="btn btn-danger"
          @click="confirmDelete"
        >
          🗑️ Delete
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading student details...</p>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="error-state">
      <p class="error-msg">{{ loadError }}</p>
      <button class="btn btn-outline" @click="loadStudent">Retry</button>
    </div>

    <!-- Not Found -->
    <div v-else-if="!student" class="error-state">
      <p class="error-msg">Student not found.</p>
      <button class="btn btn-outline" @click="$router.push('/dashboard/students')">Back to List</button>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Profile Header -->
      <div class="profile-header">
        <div class="profile-avatar">
          <div class="avatar-placeholder">{{ initials }}</div>
        </div>
        <div class="profile-info">
          <h2>{{ student.first_name }} {{ student.last_name }}</h2>
          <div class="profile-meta">
            <span class="badge badge-id">{{ student.student_id }}</span>
            <span :class="['badge', statusBadgeClass]">{{ student.status }}</span>
            <span :class="['badge', student.enrollments_count > 0 ? 'badge-success' : 'badge-warning']">
              {{ student.enrollments_count > 0 ? `✅ Enrolled (${student.enrollments_count})` : '❌ Not Enrolled' }}
            </span>
          </div>
        </div>
      </div>

      <div class="details-grid">
        <!-- Personal Information -->
        <div class="detail-card">
          <h3>👤 Personal Information</h3>
          <div class="detail-table">
            <div class="detail-row">
              <span class="label">First Name</span>
              <span class="value">{{ student.first_name }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Last Name</span>
              <span class="value">{{ student.last_name || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Email</span>
              <span class="value">{{ student.email || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Phone</span>
              <span class="value">{{ student.phone || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Gender</span>
              <span class="value">{{ student.gender ? student.gender.charAt(0).toUpperCase() + student.gender.slice(1) : '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Date of Birth</span>
              <span class="value">{{ student.date_of_birth ? formatDate(student.date_of_birth) : '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Blood Group</span>
              <span class="value">{{ student.blood_group || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Religion</span>
              <span class="value">{{ student.religion || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Academic Information -->
        <div class="detail-card">
          <h3>📚 Academic Information</h3>
          <div class="detail-table">
            <div class="detail-row">
              <span class="label">Student ID</span>
              <span class="value">{{ student.student_id }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Roll No</span>
              <span class="value">{{ student.roll_no || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Class</span>
              <span class="value">{{ student.current_class?.name || student.current_class_id || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Section</span>
              <span class="value">{{ student.current_section?.name || student.current_section_id || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Admission Date</span>
              <span class="value">{{ student.admission_date ? formatDate(student.admission_date) : '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Status</span>
              <span class="value">
                <span :class="['badge', statusBadgeClass]">{{ student.status }}</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Address Information -->
        <div class="detail-card">
          <h3>📍 Address</h3>
          <div class="detail-table">
            <div class="detail-row">
              <span class="label">Present Address</span>
              <span class="value">{{ student.present_address || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Permanent Address</span>
              <span class="value">{{ student.permanent_address || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">City</span>
              <span class="value">{{ student.city || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">State</span>
              <span class="value">{{ student.state || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Zip Code</span>
              <span class="value">{{ student.zip_code || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Country</span>
              <span class="value">{{ student.country || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Guardian Information -->
        <div class="detail-card">
          <h3>👪 Guardian Information</h3>
          <div class="detail-table">
            <div class="detail-row">
              <span class="label">Father's Name</span>
              <span class="value">{{ student.father_name || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Father's Phone</span>
              <span class="value">{{ student.father_phone || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Mother's Name</span>
              <span class="value">{{ student.mother_name || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Mother's Phone</span>
              <span class="value">{{ student.mother_phone || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Emergency Contact</span>
              <span class="value">{{ student.emergency_contact || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Emergency Phone</span>
              <span class="value">{{ student.emergency_phone || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Previous School -->
        <div class="detail-card">
          <h3>🏫 Previous School</h3>
          <div class="detail-table">
            <div class="detail-row">
              <span class="label">Previous School</span>
              <span class="value">{{ student.previous_school || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Previous Class</span>
              <span class="value">{{ student.previous_class || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Remarks -->
        <div class="detail-card" v-if="student.remarks">
          <h3>📝 Remarks</h3>
          <p class="remarks-text">{{ student.remarks }}</p>
        </div>
      </div>

      <!-- Fee Payment Summary Section -->
      <div v-if="student.enrollments && student.enrollments.length > 0" class="fee-summary-section">
        <div class="section-header">
          <h3>💰 Fee Payment Summary</h3>
          <button class="btn btn-primary btn-sm" @click="loadFeeSummary" :disabled="feeLoading">
            {{ feeLoading ? 'Loading...' : '🔄 Refresh Summary' }}
          </button>
        </div>

        <!-- Fee Loading -->
        <div v-if="feeLoading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading fee summary...</p>
        </div>

        <!-- Fee Error -->
        <div v-else-if="feeError" class="error-state">
          <p class="error-msg">{{ feeError }}</p>
          <button class="btn btn-outline" @click="loadFeeSummary">Retry</button>
        </div>

        <!-- Fee Summary Content -->
        <template v-else-if="feeSummary">
          <!-- Overall Summary Cards -->
          <div class="overall-summary">
            <div class="summary-card total-fee">
              <div class="summary-icon">💰</div>
              <div class="summary-details">
                <span class="summary-label">Total Fee</span>
                <span class="summary-value">৳{{ formatNumber(feeSummary.overall.total_fee) }}</span>
              </div>
            </div>
            <div class="summary-card total-discount" v-if="feeSummary.overall.total_discount > 0">
              <div class="summary-icon">🏷️</div>
              <div class="summary-details">
                <span class="summary-label">Total Discount</span>
                <span class="summary-value discount">-৳{{ formatNumber(feeSummary.overall.total_discount) }}</span>
              </div>
            </div>
            <div class="summary-card total-paid">
              <div class="summary-icon">✅</div>
              <div class="summary-details">
                <span class="summary-label">Total Paid</span>
                <span class="summary-value">৳{{ formatNumber(feeSummary.overall.total_paid) }}</span>
              </div>
            </div>
            <div class="summary-card total-due">
              <div class="summary-icon">⏳</div>
              <div class="summary-details">
                <span class="summary-label">Total Due</span>
                <span class="summary-value">৳{{ formatNumber(feeSummary.overall.total_due) }}</span>
              </div>
            </div>
            <div class="summary-card payment-pct">
              <div class="summary-icon">📊</div>
              <div class="summary-details">
                <span class="summary-label">Payment Progress</span>
                <span class="summary-value">{{ feeSummary.overall.payment_percentage }}%</span>
              </div>
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="progress-bar-container">
            <div class="progress-bar">
              <div
                class="progress-fill"
                :style="{ width: feeSummary.overall.payment_percentage + '%' }"
                :class="progressBarClass"
              ></div>
            </div>
            <span class="progress-text">{{ feeSummary.overall.payment_percentage }}% Paid</span>
          </div>

          <!-- Per-Enrollment Fee Breakdown -->
          <div
            v-for="enr in feeSummary.enrollments"
            :key="enr.enrollment_id"
            class="enrollment-fee-card"
          >
            <div class="enrollment-fee-header">
              <div>
                <strong>{{ enr.course_name }}</strong>
                <span class="enrollment-fee-badge">{{ enr.enrollment_no }}</span>
                <span class="cat-badge" :class="'cat-' + (enr.fee_type || 'monthly')">
                  {{ categoryLabel(enr.fee_type) }}
                </span>
              </div>
              <div class="enrollment-fee-actions">
                <span :class="['badge', getPaymentStatusBadge(enr.payment_status)]">{{ enr.payment_status }}</span>
                <button class="btn btn-primary btn-sm" @click="openPaymentDialog(enr)">
                  💳 Pay Now
                </button>
              </div>
            </div>
            <div class="enrollment-fee-body">
              <div class="fee-detail-item">
                <span class="label">Total Fee</span>
                <span class="value">৳{{ formatNumber(enr.total_fee) }}</span>
              </div>
              <div class="fee-detail-item">
                <span class="label">Payable</span>
                <span class="value">৳{{ formatNumber(enr.payable_fee) }}</span>
              </div>
              <div class="fee-detail-item">
                <span class="label">Paid</span>
                <span class="value paid">৳{{ formatNumber(enr.paid_amount) }}</span>
              </div>
              <div class="fee-detail-item">
                <span class="label">Due</span>
                <span class="value due">৳{{ formatNumber(enr.due_amount) }}</span>
              </div>
              <!-- Show discount for both percentage and flat discounts -->
              <!-- For monthly fee type: check monthly_summary.total_discount -->
              <!-- For one-time fee type: check discount_percent -->
              <div class="fee-detail-item" v-if="enr.fee_type === 'monthly' && enr.monthly_summary && enr.monthly_summary.total_discount > 0">
                <span class="label">Discount</span>
                <span class="value discount">-৳{{ formatNumber(enr.monthly_summary.total_discount) }}</span>
              </div>
              <div class="fee-detail-item" v-else-if="enr.discount_percent > 0">
                <span class="label">Discount ({{ enr.discount_percent }}%)</span>
                <span class="value discount">-৳{{ formatNumber(enr.total_fee * enr.discount_percent / 100) }}</span>
              </div>
            </div>

            <!-- Last Paid & Next Pending Month Info (for monthly fee type) -->
            <div v-if="enr.fee_type === 'monthly'" class="month-status-bar">
              <div class="month-status-item last-paid">
                <span class="month-status-label">✅ Last Paid</span>
                <span class="month-status-value" v-if="enr.last_paid_month">
                  {{ enr.last_paid_month.month_name }} — ৳{{ formatNumber(enr.last_paid_month.paid_amount) }}
                </span>
                <span class="month-status-value no-data" v-else>No payments yet</span>
              </div>
              <div class="month-status-divider"></div>
              <div class="month-status-item next-pending">
                <span class="month-status-label">⏳ Next Due</span>
                <span class="month-status-value" v-if="enr.next_pending_month">
                  {{ enr.next_pending_month.month_name }} — ৳{{ formatNumber(enr.next_pending_month.due_amount) }}
                  <span :class="['badge', getMonthlyStatusBadge(enr.next_pending_month.status)]" class="month-status-badge">{{ enr.next_pending_month.status }}</span>
                </span>
                <span class="month-status-value no-data" v-else>All months paid ✅</span>
              </div>
            </div>

            <!-- Monthly Fee Records (if monthly fee type) -->
            <div v-if="enr.monthly_summary && enr.monthly_summary.records && enr.monthly_summary.records.length > 0" class="monthly-records">
              <h4>📅 All Monthly Records</h4>
              <div class="monthly-records-table">
                <div class="monthly-record-header">
                  <span>Month</span>
                  <span>Total</span>
                  <span>Discount</span>
                  <span>Payable</span>
                  <span>Paid</span>
                  <span>Due</span>
                  <span>Status</span>
                </div>
                <div
                  v-for="record in enr.monthly_summary.records"
                  :key="record.id"
                  class="monthly-record-row"
                >
                  <span>{{ record.month }}</span>
                  <span>৳{{ formatNumber(record.total_monthly_fee) }}</span>
                  <span class="discount">-৳{{ formatNumber(Math.max(0, record.total_monthly_fee - record.due_amount)) }}</span>
                  <span>৳{{ formatNumber(record.due_amount) }}</span>
                  <span class="paid">৳{{ formatNumber(record.paid_amount) }}</span>
                  <span class="due">৳{{ formatNumber(Math.max(0, record.due_amount - record.paid_amount)) }}</span>
                  <span :class="['badge', getMonthlyStatusBadge(record.payment_status)]">{{ record.payment_status }}</span>
                </div>
              </div>
            </div>

            <!-- Recent Payments -->
            <div v-if="enr.recent_payments && enr.recent_payments.length > 0" class="recent-payments">
              <h4>🕐 Recent Payments</h4>
              <div class="payment-history-list">
                <div
                  v-for="payment in enr.recent_payments"
                  :key="payment.id"
                  class="payment-history-item"
                >
                  <div class="payment-info">
                    <span class="payment-receipt">#{{ payment.receipt_no || payment.id }}</span>
                    <span class="payment-date">{{ formatDate(payment.created_at) }}</span>
                    <span class="payment-method">{{ payment.payment_method || '—' }}</span>
                  </div>
                  <div class="payment-amount-info">
                    <span class="payment-amount">৳{{ formatNumber(payment.amount) }}</span>
                    <span :class="['badge', getPaymentStatusBadge(payment.payment_status)]">{{ payment.payment_status }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- No Fee Data -->
        <div v-else class="no-fee-data">
          <p>Click "Refresh Summary" to load fee payment information.</p>
        </div>
      </div>

      <!-- Enrollment History -->
      <div class="enrollment-section" v-if="student.enrollments && student.enrollments.length > 0">
        <h3>📋 Enrollment History</h3>
        <div class="enrollment-cards">
          <div
            v-for="enrollment in student.enrollments"
            :key="enrollment.id"
            class="enrollment-card"
            @click="$router.push(`/dashboard/enrollment/enrollments/${enrollment.id}`)"
          >
            <div class="enrollment-header">
              <span class="enrollment-no">{{ enrollment.enrollment_no }}</span>
              <span :class="['badge', getEnrollmentStatusBadge(enrollment.status)]">{{ enrollment.status }}</span>
            </div>
            <div class="enrollment-body">
              <div class="enrollment-detail">
                <span class="label">Course</span>
                <span class="value">{{ enrollment.batch?.course?.name || '—' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Batch</span>
                <span class="value">{{ enrollment.batch?.name || '—' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Session</span>
                <span class="value">{{ enrollment.batch?.academicSession?.name || enrollment.academic_session_id || '—' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Type</span>
                <span class="value">{{ enrollment.enrollment_type || '—' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Mode</span>
                <span class="value">{{ enrollment.mode || '—' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Total Fee</span>
                <span class="value">৳{{ enrollment.total_fee ? enrollment.total_fee.toLocaleString() : '—' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Paid</span>
                <span class="value">৳{{ enrollment.paid_amount ? enrollment.paid_amount.toLocaleString() : '0' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Due</span>
                <span class="value">৳{{ enrollment.due_amount ? enrollment.due_amount.toLocaleString() : '0' }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Payment Status</span>
                <span :class="['badge', getPaymentStatusBadge(enrollment.payment_status)]">{{ enrollment.payment_status }}</span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Fee Type</span>
                <span class="value">
                  <span class="cat-badge" :class="'cat-' + (enrollment.fee_type || 'monthly')">
                    {{ categoryLabel(enrollment.fee_type) }}
                  </span>
                </span>
              </div>
              <div class="enrollment-detail">
                <span class="label">Enrolled At</span>
                <span class="value">{{ enrollment.enrolled_at ? formatDate(enrollment.enrolled_at) : '—' }}</span>
              </div>
            </div>
            <div class="enrollment-footer">
              <span v-if="enrollment.fee_type === 'monthly'" class="monthly-fee-link" @click.stop="$router.push(`/dashboard/enrollment/monthly-fees?enrollment_id=${enrollment.id}`)">
                📅 View Monthly Fee Records →
              </span>
              <span class="click-hint">Click to view full details →</span>
            </div>
          </div>
        </div>
      </div>

      <!-- No Enrollment -->
      <div v-else class="no-enrollment-section">
        <div class="no-enrollment-card">
          <h3>📋 Enrollment History</h3>
          <p>This student has no enrollments yet.</p>
          <button class="btn btn-primary" @click="goToEnroll">🎓 Enroll Now</button>
        </div>
      </div>
    </template>

    <!-- Payment Dialog Modal -->
    <div v-if="showPaymentDialog" class="modal-overlay" @click.self="closePaymentDialog">
      <div class="modal-content payment-modal">
        <div class="modal-header">
          <h3>💳 Record Payment</h3>
          <button class="modal-close" @click="closePaymentDialog">&times;</button>
        </div>
        <div class="modal-body">
          <div class="payment-enrollment-info">
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Course</span>
                <span class="info-value">{{ selectedEnrollment?.course_name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Enrollment</span>
                <span class="info-value">{{ selectedEnrollment?.enrollment_no }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Fee Type</span>
                <span class="info-value">{{ selectedEnrollment?.fee_type === 'monthly' ? '📅 Monthly' : '💰 One-Time' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Total Due</span>
                <span class="info-value due">৳{{ formatNumber(selectedEnrollment?.due_amount) }}</span>
              </div>
              <div class="info-item" v-if="selectedEnrollment?.fee_type === 'monthly' && selectedEnrollment?.next_pending_month">
                <span class="info-label">Next Due Month</span>
                <span class="info-value">
                  <span class="next-month-highlight">{{ selectedEnrollment.next_pending_month.month_name }}</span>
                  — ৳{{ formatNumber(selectedEnrollment.next_pending_month.due_amount) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Month Selector for Monthly Fee Type (full width) -->
          <div class="form-group full-width" v-if="selectedEnrollment?.fee_type === 'monthly' && pendingMonthlyRecords.length > 0">
            <label for="monthSelect">Pay For Month</label>
            <select id="monthSelect" v-model="paymentForm.monthly_record_id" class="form-input" @change="onMonthChange">
              <option value="">— Auto (Next Due Month) —</option>
              <option
                v-for="rec in pendingMonthlyRecords"
                :key="rec.id"
                :value="rec.id"
              >
                {{ formatMonthName(rec.month) }} — Due: ৳{{ formatNumber(rec.due_amount) }} ({{ rec.payment_status }})
              </option>
            </select>
            <small class="form-hint">Select a specific month to pay, or leave as "Auto" to pay the next due month</small>
          </div>

          <!-- Two-column form fields -->
          <div class="form-row-2col">
            <div class="form-group">
              <label for="paymentAmount">Amount (৳)</label>
              <input
                id="paymentAmount"
                v-model="paymentForm.amount"
                type="number"
                min="1"
                step="0.01"
                class="form-input"
                placeholder="Enter payment amount"
                :max="selectedEnrollment?.due_amount"
              />
              <small class="form-hint">Max due: ৳{{ formatNumber(selectedEnrollment?.due_amount) }}</small>
            </div>

            <div class="form-group">
              <label for="paymentMethod">Payment Method</label>
              <select id="paymentMethod" v-model="paymentForm.payment_method" class="form-input">
                <option value="cash">💵 Cash</option>
                <option value="bKash">📱 bKash</option>
                <option value="Nagad">📱 Nagad</option>
                <option value="bank">🏦 Bank Transfer</option>
                <option value="card">💳 Card</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div class="form-group">
              <label for="transactionId">Transaction ID</label>
              <input
                id="transactionId"
                v-model="paymentForm.transaction_id"
                type="text"
                class="form-input"
                placeholder="e.g., TRX123456"
              />
              <small class="form-hint">Optional</small>
            </div>

            <div class="form-group">
              <label for="paymentReference">Reference / Note</label>
              <input
                id="paymentReference"
                v-model="paymentForm.reference"
                type="text"
                class="form-input"
                placeholder="Any reference or note"
              />
              <small class="form-hint">Optional</small>
            </div>
          </div>

          <div v-if="paymentError" class="payment-error">
            {{ paymentError }}
          </div>

          <div v-if="paymentSuccess" class="payment-success">
            ✅ {{ paymentSuccess }}
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn btn-outline" @click="closePaymentDialog" :disabled="paymentProcessing">Cancel</button>
          <button
            class="btn btn-primary"
            @click="submitPayment"
            :disabled="paymentProcessing || !paymentForm.amount || paymentForm.amount <= 0"
          >
            {{ paymentProcessing ? 'Processing...' : '✅ Confirm Payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal-content">
        <h3>🗑️ Delete Student</h3>
        <p>Are you sure you want to delete <strong>{{ student?.first_name }} {{ student?.last_name }}</strong> ({{ student?.student_id }})?</p>
        <p class="text-muted">This action cannot be undone.</p>
        <div class="modal-actions">
          <button class="btn btn-outline" @click="showDeleteModal = false">Cancel</button>
          <button class="btn btn-danger" @click="deleteStudent" :disabled="deleting">
            {{ deleting ? 'Deleting...' : 'Yes, Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const categoryLabel = (cat) => {
  const labels = { one_time: 'One-Time', monthly: 'Monthly', event_based: 'Event' }
  return labels[cat] || cat || 'Monthly'
}
import { useRoute, useRouter } from 'vue-router'
import studentService from '@/services/student.service'

const route = useRoute()
const router = useRouter()

const student = ref(null)
const loading = ref(true)
const loadError = ref(null)
const showDeleteModal = ref(false)
const deleting = ref(false)

// Fee summary state
const feeSummary = ref(null)
const feeLoading = ref(false)
const feeError = ref(null)

// Payment dialog state
const showPaymentDialog = ref(false)
const selectedEnrollment = ref(null)
const paymentProcessing = ref(false)
const paymentError = ref(null)
const paymentSuccess = ref(null)
const paymentForm = ref({
  enrollment_id: null,
  amount: null,
  payment_method: 'cash',
  transaction_id: '',
  reference: '',
  monthly_record_id: '',
})

// Pending monthly records for the selected enrollment (for month-wise payment)
const pendingMonthlyRecords = computed(() => {
  if (!selectedEnrollment.value?.monthly_summary?.records) return []
  return selectedEnrollment.value.monthly_summary.records.filter(r => r.payment_status !== 'paid')
})

const initials = computed(() => {
  if (!student.value) return '?'
  const first = student.value.first_name?.charAt(0)?.toUpperCase() || ''
  const last = student.value.last_name?.charAt(0)?.toUpperCase() || ''
  return first + last || '?'
})

const statusBadgeClass = computed(() => {
  if (!student.value) return ''
  const map = {
    active: 'badge-success',
    inactive: 'badge-secondary',
    graduated: 'badge-info',
    transferred: 'badge-warning',
    expelled: 'badge-danger',
  }
  return map[student.value.status] || 'badge-secondary'
})

const progressBarClass = computed(() => {
  const pct = feeSummary.value?.overall?.payment_percentage || 0
  if (pct >= 100) return 'progress-complete'
  if (pct >= 50) return 'progress-good'
  return 'progress-low'
})

function getEnrollmentStatusBadge(status) {
  const map = {
    active: 'badge-success',
    pending: 'badge-warning',
    completed: 'badge-info',
    dropped: 'badge-danger',
    transferred: 'badge-secondary',
  }
  return map[status] || 'badge-secondary'
}

function getPaymentStatusBadge(status) {
  const map = {
    paid: 'badge-success',
    partial: 'badge-warning',
    unpaid: 'badge-danger',
    pending: 'badge-secondary',
    awaiting_confirmation: 'badge-warning',
    confirmed: 'badge-success',
    rejected: 'badge-danger',
  }
  return map[status] || 'badge-secondary'
}

function getFeeTypeBadge(type) {
  return type === 'monthly' ? 'badge-info' : 'badge-primary'
}

function getMonthlyStatusBadge(status) {
  const map = {
    paid: 'badge-success',
    partial: 'badge-warning',
    unpaid: 'badge-danger',
    pending: 'badge-secondary',
    overdue: 'badge-danger',
  }
  return map[status] || 'badge-secondary'
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatNumber(num) {
  if (num === null || num === undefined) return '0'
  return Number(num).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function formatMonthName(monthStr) {
  if (!monthStr) return '—'
  // monthStr is in Y-m format, e.g., "2026-01"
  const [year, month] = monthStr.split('-')
  const date = new Date(parseInt(year), parseInt(month) - 1, 1)
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' })
}

function onMonthChange() {
  // When a specific month is selected, auto-fill the amount with that month's due
  if (paymentForm.value.monthly_record_id && selectedEnrollment.value?.monthly_summary?.records) {
    const selected = selectedEnrollment.value.monthly_summary.records.find(
      r => r.id === paymentForm.value.monthly_record_id
    )
    if (selected) {
      paymentForm.value.amount = selected.due_amount > 0 ? selected.due_amount : selected.total_monthly_fee
    }
  } else {
    // Reset to total due when "Auto" is selected
    paymentForm.value.amount = selectedEnrollment.value?.due_amount > 0 ? selectedEnrollment.value.due_amount : null
  }
}

async function loadStudent() {
  loading.value = true
  loadError.value = null
  try {
    const response = await studentService.get(route.params.id)
    student.value = response.data?.data || response.data
    // Auto-load fee summary after student loads
    if (student.value?.enrollments_count > 0) {
      await loadFeeSummary()
    }
  } catch (err) {
    console.error('Failed to load student:', err)
    loadError.value = err.response?.data?.message || 'Failed to load student details.'
  } finally {
    loading.value = false
  }
}

async function loadFeeSummary() {
  if (!student.value?.id) return
  feeLoading.value = true
  feeError.value = null
  try {
    const response = await studentService.getFeeSummary(student.value.id)
    feeSummary.value = response.data?.data || response.data
  } catch (err) {
    console.error('Failed to load fee summary:', err)
    feeError.value = err.response?.data?.message || 'Failed to load fee summary.'
  } finally {
    feeLoading.value = false
  }
}

function openPaymentDialog(enrollment) {
  selectedEnrollment.value = enrollment
  paymentForm.value = {
    enrollment_id: enrollment.enrollment_id,
    amount: enrollment.due_amount > 0 ? enrollment.due_amount : null,
    payment_method: 'cash',
    transaction_id: '',
    reference: '',
    monthly_record_id: '',
  }
  paymentError.value = null
  paymentSuccess.value = null
  showPaymentDialog.value = true
}

function closePaymentDialog() {
  showPaymentDialog.value = false
  selectedEnrollment.value = null
  paymentForm.value = {
    enrollment_id: null,
    amount: null,
    payment_method: 'cash',
    transaction_id: '',
    reference: '',
    monthly_record_id: '',
  }
  paymentError.value = null
  paymentSuccess.value = null
}

async function submitPayment() {
  if (!paymentForm.value.amount || paymentForm.value.amount <= 0) {
    paymentError.value = 'Please enter a valid amount.'
    return
  }

  paymentProcessing.value = true
  paymentError.value = null
  paymentSuccess.value = null

  try {
    const payload = {
      enrollment_id: paymentForm.value.enrollment_id,
      amount: parseFloat(paymentForm.value.amount),
      payment_method: paymentForm.value.payment_method,
      transaction_id: paymentForm.value.transaction_id || null,
      reference: paymentForm.value.reference || null,
    }

    // Include monthly_record_id if a specific month was selected
    if (paymentForm.value.monthly_record_id) {
      payload.monthly_record_id = paymentForm.value.monthly_record_id
    }

    const response = await studentService.recordPayment(student.value.id, payload)
    const result = response.data?.data || response.data

    paymentSuccess.value = result?.message || 'Payment recorded successfully!'

    // Refresh fee summary after a short delay
    setTimeout(async () => {
      await loadFeeSummary()
      closePaymentDialog()
    }, 1500)
  } catch (err) {
    console.error('Payment failed:', err)
    paymentError.value = err.response?.data?.message || 'Payment failed. Please try again.'
  } finally {
    paymentProcessing.value = false
  }
}

function goToEnroll() {
  router.push(`/dashboard/enrollment/enrollments/create?student_id=${student.value.id}`)
}

function confirmDelete() {
  showDeleteModal.value = true
}

async function deleteStudent() {
  deleting.value = true
  try {
    await studentService.delete(student.value.id)
    showDeleteModal.value = false
    router.push('/dashboard/students')
  } catch (err) {
    console.error('Failed to delete student:', err)
    alert(err.response?.data?.message || 'Failed to delete student.')
  } finally {
    deleting.value = false
  }
}

onMounted(loadStudent)
</script>

<style scoped>
.cat-badge { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; width: fit-content; }
.cat-badge.cat-one_time { background: #dbeafe; color: #1e40af; }
.cat-badge.cat-monthly { background: #d1fae5; color: #065f46; }
.cat-badge.cat-event_based { background: #fef3c7; color: #92400e; }
.page-container {
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: 700;
  margin: 0 0 4px 0;
  color: var(--text-primary);
}

.text-muted {
  color: var(--text-muted);
  font-size: 14px;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  text-decoration: none;
}

.btn-sm {
  padding: 5px 12px;
  font-size: 13px;
}

.btn-primary {
  background: #4f46e5;
  color: white;
}
.btn-primary:hover { background: #4338ca; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-outline {
  background: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
}
.btn-outline:hover { background: #f3f4f6; }

.btn-danger {
  background: #ef4444;
  color: white;
}
.btn-danger:hover { background: #dc2626; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }

/* Loading & Error */
.loading-state, .error-state {
  text-align: center;
  padding: 60px 20px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin { to { transform: rotate(360deg); } }

.error-msg {
  color: #ef4444;
  font-size: 16px;
  margin-bottom: 16px;
}

/* Profile Header */
.profile-header {
  display: flex;
  align-items: center;
  gap: 20px;
  background: var(--bg-card);
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.profile-avatar .avatar-placeholder {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: 700;
}

.profile-info h2 {
  margin: 0 0 8px 0;
  font-size: 22px;
  color: var(--text-primary);
}

.profile-meta {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.badge-id { background: #eef2ff; color: #4f46e5; }
.badge-success { background: #dcfce7; color: #16a34a; }
.badge-warning { background: #fef3c7; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-info { background: #dbeafe; color: #2563eb; }
.badge-secondary { background: #f3f4f6; color: var(--text-muted); }
.badge-primary { background: #eef2ff; color: #4f46e5; }

/* Details Grid */
.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.detail-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.detail-card h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: var(--text-primary);
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-light);
}

.detail-table {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.detail-row .label {
  font-size: 13px;
  color: var(--text-muted);
  min-width: 130px;
  flex-shrink: 0;
}

.detail-row .value {
  font-size: 14px;
  color: var(--text-primary);
  text-align: right;
  font-weight: 500;
}

.remarks-text {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.6;
  margin: 0;
}

/* Fee Summary Section */
.fee-summary-section {
  margin-bottom: 24px;
  background: var(--bg-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.section-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

/* Overall Summary Cards */
.overall-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 10px;
  background: var(--bg-surface-muted);
  border: 1px solid var(--border-color);
}

.summary-card .summary-icon {
  font-size: 28px;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-card);
  border-radius: 10px;
}

.summary-card.total-fee .summary-icon { background: #eef2ff; }
.summary-card.total-discount .summary-icon { background: #fce7f3; }
.summary-card.total-paid .summary-icon { background: #dcfce7; }
.summary-card.total-due .summary-icon { background: #fef3c7; }
.summary-card.payment-pct .summary-icon { background: #dbeafe; }

.summary-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.summary-label {
  font-size: 12px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.summary-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--text-primary);
}

/* Progress Bar */
.progress-bar-container {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  padding: 12px 16px;
  background: var(--bg-surface-muted);
  border-radius: 8px;
}

.progress-bar {
  flex: 1;
  height: 10px;
  background: #e5e7eb;
  border-radius: 5px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 5px;
  transition: width 0.5s ease;
}

.progress-complete { background: #16a34a; }
.progress-good { background: #4f46e5; }
.progress-low { background: #f59e0b; }

.progress-text {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  white-space: nowrap;
}

/* Per-Enrollment Fee Cards */
.enrollment-fee-card {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  margin-bottom: 16px;
  overflow: hidden;
}

.enrollment-fee-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: var(--bg-surface-muted);
  border-bottom: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 8px;
}

.enrollment-fee-header > div {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.enrollment-fee-badge {
  font-size: 12px;
  color: #4f46e5;
  font-weight: 600;
}

.enrollment-fee-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.enrollment-fee-body {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
  padding: 16px;
}

.fee-detail-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.fee-detail-item .label {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.fee-detail-item .value {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-primary);
}

.fee-detail-item .value.paid { color: #16a34a; }
.fee-detail-item .value.due { color: #dc2626; }
.fee-detail-item .value.discount { color: #4f46e5; }

/* Month Status Bar (Last Paid / Next Due) */
.month-status-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  background: linear-gradient(135deg, #f0fdf4, #f0f9ff);
  border-top: 1px solid var(--border-color);
  flex-wrap: wrap;
}

.month-status-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 180px;
}

.month-status-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.month-status-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
}

.month-status-value.no-data {
  color: var(--text-muted);
  font-weight: 400;
  font-style: italic;
}

.month-status-badge {
  margin-left: 6px;
  vertical-align: middle;
}

.month-status-divider {
  width: 1px;
  height: 36px;
  background: #d1d5db;
  flex-shrink: 0;
}

.next-month-highlight {
  display: inline-block;
  background: #dbeafe;
  color: #1d4ed8;
  padding: 1px 8px;
  border-radius: 4px;
  font-weight: 600;
  font-size: 13px;
}

/* Monthly Records */
.monthly-records {
  padding: 12px 16px;
  border-top: 1px solid var(--border-color);
  background: #fafafa;
}

.monthly-records h4,
.recent-payments h4 {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  margin: 0 0 10px 0;
}

.monthly-records-table {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.monthly-record-header,
.monthly-record-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
  gap: 8px;
  padding: 6px 8px;
  font-size: 13px;
  align-items: center;
}

.monthly-record-header {
  font-weight: 600;
  color: var(--text-muted);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 8px;
}

.monthly-record-row:nth-child(even) {
  background: #f3f4f6;
  border-radius: 4px;
}

.monthly-record-row .paid { color: #16a34a; font-weight: 600; }
.monthly-record-row .due { color: #dc2626; font-weight: 600; }

/* Recent Payments */
.recent-payments {
  padding: 12px 16px;
  border-top: 1px solid var(--border-color);
}

.payment-history-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.payment-history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  background: var(--bg-surface-muted);
  border-radius: 6px;
  gap: 12px;
}

.payment-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.payment-receipt {
  font-weight: 600;
  color: #4f46e5;
  font-size: 13px;
}

.payment-date {
  font-size: 12px;
  color: var(--text-muted);
}

.payment-method {
  font-size: 12px;
  color: var(--text-secondary);
  background: #e5e7eb;
  padding: 2px 8px;
  border-radius: 4px;
}

.payment-amount-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.payment-amount {
  font-weight: 700;
  font-size: 15px;
  color: var(--text-primary);
}

.no-fee-data {
  text-align: center;
  padding: 30px;
  color: var(--text-muted);
}

/* Payment Modal */
.payment-modal {
  max-width: 680px;
  width: 94%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  padding: 0;
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.payment-modal .modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px 12px;
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}

.payment-modal .modal-header h3 {
  margin: 0;
  font-size: 19px;
  color: var(--text-primary);
  font-weight: 700;
}

.payment-modal .modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0;
  line-height: 1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  transition: all 0.15s;
}

.payment-modal .modal-close:hover {
  background: #f3f4f6;
  color: var(--text-secondary);
}

.payment-modal .modal-body {
  padding: 18px 30px;
  overflow-y: auto;
  flex: 1;
  margin-bottom: 0;
}

.payment-modal .modal-actions {
  padding: 12px 30px 20px;
  border-top: 1px solid #f3f4f6;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  flex-shrink: 0;
  margin-top: 0;
}

/* Enrollment Info Grid */
.payment-enrollment-info {
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 18px;
  border: 1px solid var(--border-color);
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 24px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.info-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.info-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
}

.info-value.due {
  color: #dc2626;
}

/* Two-column form row */
.form-row-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 20px;
}

.form-group {
  margin-bottom: 0;
}

.form-group.full-width {
  grid-column: 1 / -1;
  margin-bottom: 4px;
}

.form-group label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 5px;
}

.form-input {
  width: 100%;
  padding: 9px 13px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 14px;
  color: var(--text-primary);
  background: var(--bg-card);
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
}

.form-hint {
  display: block;
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 3px;
}

.payment-error {
  background: #fef2f2;
  color: #dc2626;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 10px;
  border: 1px solid #fecaca;
}

.payment-success {
  background: #f0fdf4;
  color: #16a34a;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 10px;
  border: 1px solid #bbf7d0;
}

/* Enrollment Section */
.enrollment-section {
  margin-bottom: 24px;
}

.enrollment-section h3 {
  font-size: 18px;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 16px 0;
}

.enrollment-cards {
  display: grid;
  gap: 16px;
}

.enrollment-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid transparent;
}

.enrollment-card:hover {
  border-color: #4f46e5;
  box-shadow: 0 4px 12px rgba(79,70,229,0.12);
  transform: translateY(-1px);
}

.enrollment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-light);
}

.enrollment-no {
  font-weight: 700;
  font-size: 15px;
  color: #4f46e5;
}

.enrollment-body {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.enrollment-detail {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.enrollment-detail .label {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.enrollment-detail .value {
  font-size: 14px;
  color: var(--text-primary);
  font-weight: 500;
}

.enrollment-footer {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #f3f4f6;
  text-align: right;
}

.click-hint {
  font-size: 12px;
  color: #4f46e5;
  font-weight: 500;
}
.monthly-fee-link {
  font-size: 12px;
  color: #059669;
  font-weight: 600;
  cursor: pointer;
  margin-right: 12px;
}
.monthly-fee-link:hover {
  text-decoration: underline;
}

/* No Enrollment */
.no-enrollment-section {
  margin-bottom: 24px;
}

.no-enrollment-card {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 40px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.no-enrollment-card h3 {
  margin: 0 0 12px 0;
  font-size: 18px;
  color: var(--text-primary);
}

.no-enrollment-card p {
  color: var(--text-muted);
  margin: 0 0 20px 0;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: var(--bg-card);
  border-radius: 12px;
  padding: 24px;
  max-width: 420px;
  width: 90%;
}

.modal-content h3 {
  margin: 0 0 12px 0;
  font-size: 18px;
  color: var(--text-primary);
}

.modal-content p {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: var(--text-secondary);
}

.modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 20px;
}

/* Responsive */
@media (max-width: 1024px) {
  .details-grid { grid-template-columns: 1fr; }
  .overall-summary { grid-template-columns: 1fr 1fr; }
  .enrollment-fee-body { grid-template-columns: 1fr 1fr; }
  .info-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
  .page-container { padding: 16px; }
  .page-header { flex-direction: column; align-items: stretch; }
  .page-header h1 { font-size: 20px; }
  .header-actions { width: 100%; flex-direction: column; }
  .header-actions .btn { flex: 1; justify-content: center; width: 100%; }
  .details-grid { grid-template-columns: 1fr; }
  .enrollment-body { grid-template-columns: 1fr; }
  .profile-header { flex-direction: column; text-align: center; padding: 16px; }
  .profile-meta { justify-content: center; }
  .profile-avatar .avatar-placeholder { width: 56px; height: 56px; font-size: 22px; }
  .profile-info h2 { font-size: 18px; }
  .overall-summary { grid-template-columns: 1fr 1fr; gap: 12px; }
  .enrollment-fee-body { grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px; }
  .fee-detail-item .value { font-size: 14px; }
  .monthly-record-header,
  .monthly-record-row {
    grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
    font-size: 11px;
    gap: 4px;
    padding: 4px 6px;
  }
  .month-status-bar { flex-direction: column; gap: 8px; padding: 10px 12px; }
  .month-status-divider { width: 100%; height: 1px; }
  .month-status-item { min-width: auto; width: 100%; }
  .payment-modal { max-width: 100%; width: 96%; border-radius: 12px; max-height: 85vh; }
  .payment-modal .modal-header { padding: 16px 20px 10px; }
  .payment-modal .modal-header h3 { font-size: 17px; }
  .payment-modal .modal-body { padding: 14px 20px; }
  .payment-modal .modal-actions { padding: 10px 20px 16px; }
  .info-grid { grid-template-columns: 1fr 1fr; gap: 6px 16px; }
  .form-row-2col { grid-template-columns: 1fr; gap: 10px; }
  .enrollment-fee-card { border-radius: 8px; }
  .enrollment-fee-header { padding: 10px 12px; }
  .detail-card { padding: 16px; }
  .payment-enrollment-info { padding: 10px 14px; }
  .no-enrollment-card { padding: 24px; }
}

@media (max-width: 480px) {
  .page-container { padding: 12px; }
  .page-header h1 { font-size: 18px; }
  .overall-summary { grid-template-columns: 1fr 1fr; gap: 8px; }
  .overall-stat { padding: 12px; }
  .overall-stat .stat-value { font-size: 18px; }
  .enrollment-fee-body { grid-template-columns: 1fr 1fr; gap: 6px; padding: 10px; }
  .fee-detail-item .value { font-size: 13px; }
  .monthly-record-header,
  .monthly-record-row {
    grid-template-columns: 1fr 1fr 1fr;
    font-size: 10px;
    gap: 2px;
    padding: 3px 4px;
  }
  .monthly-record-header span:nth-child(3),
  .monthly-record-row span:nth-child(3),
  .monthly-record-header span:nth-child(4),
  .monthly-record-row span:nth-child(4) {
    display: none;
  }
  .info-grid { grid-template-columns: 1fr; gap: 4px; }
  .payment-modal { width: 98%; border-radius: 10px; }
  .payment-modal .modal-header { padding: 14px 16px 8px; }
  .payment-modal .modal-body { padding: 12px 16px; }
  .payment-modal .modal-actions { padding: 8px 16px 14px; flex-direction: column-reverse; }
  .payment-modal .modal-actions .btn { width: 100%; justify-content: center; }
  .profile-header { padding: 12px; gap: 12px; }
  .profile-avatar .avatar-placeholder { width: 48px; height: 48px; font-size: 18px; }
  .profile-info h2 { font-size: 16px; }
  .detail-card { padding: 12px; }
  .enrollment-card { padding: 14px; }
  .badge { font-size: 10px; padding: 2px 8px; }
  .btn { font-size: 13px; padding: 6px 12px; }
  .form-input { font-size: 13px; padding: 8px 11px; }
}
</style>