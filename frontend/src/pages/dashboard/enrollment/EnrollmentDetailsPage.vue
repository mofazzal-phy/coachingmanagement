<template>
  <div class="enrollment-details-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <router-link to="/dashboard/enrollment/enrollments" class="btn-back">
          <i class="pi pi-arrow-left"></i>
        </router-link>
        <div>
          <h1>Enrollment Details</h1>
          <p class="header-subtitle" v-if="enrollment.enrollment_no">
            {{ enrollment.enrollment_no }} · {{ enrollment.student?.first_name }} {{ enrollment.student?.last_name }}
          </p>
        </div>
      </div>
      <div class="header-actions">
        <span v-if="enrollment.id" :class="['status-badge', enrollment.status]">
          <span class="status-dot"></span>
          {{ enrollment.status }}
        </span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-ring"></div>
      <p>Loading enrollment details...</p>
    </div>

    <div v-else-if="enrollment.id" class="has-data">
      <!-- Top Stats Row -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2)">
            <i class="pi pi-id-card"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Enrollment No</span>
            <span class="stat-value code">{{ enrollment.enrollment_no }}</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c)">
            <i class="pi pi-credit-card"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Payment</span>
            <span :class="['stat-value', 'payment-' + (enrollment.payment_status || 'pending')]">
              {{ enrollment.payment_status }}
            </span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe)">
            <i class="pi pi-book"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Course</span>
            <span class="stat-value">{{ enrollment.batch?.course?.name || '-' }}</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7)">
            <i class="pi pi-calendar"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Enrolled</span>
            <span class="stat-value">{{ enrollment.enrolled_at ? new Date(enrollment.enrolled_at).toLocaleDateString('en-BD', { day:'numeric', month:'short', year:'numeric' }) : '-' }}</span>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="content-grid">
        <!-- Left Column -->
        <div class="left-col">
          <!-- Student Card -->
          <div class="detail-card">
            <div class="card-heading">
              <i class="pi pi-user"></i>
              <h3>Student Information</h3>
            </div>
            <div class="card-body">
              <div class="student-profile">
                <div class="avatar">
                  {{ (enrollment.student?.first_name?.[0] || '?') }}{{ (enrollment.student?.last_name?.[0] || '') }}
                </div>
                <div class="profile-info">
                  <h4>{{ enrollment.student?.first_name }} {{ enrollment.student?.last_name }}</h4>
                  <span class="student-id">{{ enrollment.student?.student_id }}</span>
                </div>
              </div>
              <div class="info-divider"></div>
              <div class="info-list">
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-phone"></i> Phone</span>
                  <span class="info-value">{{ enrollment.student?.phone || '-' }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-envelope"></i> Email</span>
                  <span class="info-value">{{ enrollment.student?.email || '-' }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-users"></i> Guardian</span>
                  <span class="info-value">{{ enrollment.student?.guardian?.guardian_name || enrollment.guardian_phone || '-' }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-phone"></i> Guardian Phone</span>
                  <span class="info-value">{{ enrollment.guardian_phone || enrollment.student?.guardian?.guardian_phone || '-' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Course & Batch Card -->
          <div class="detail-card">
            <div class="card-heading">
              <i class="pi pi-bookmark"></i>
              <h3>Course & Batch</h3>
            </div>
            <div class="card-body">
              <div class="course-badge">
                <div class="course-icon">
                  <i class="pi pi-book"></i>
                </div>
                <div class="course-info">
                  <h4>{{ enrollment.batch?.course?.name || 'Unknown Course' }}</h4>
                  <span class="batch-name">{{ enrollment.batch?.name }}</span>
                </div>
              </div>
              <div class="info-divider"></div>
              <div class="info-list">
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-clock"></i> Schedule</span>
                  <span class="info-value">{{ enrollment.batch?.days?.join(', ') || '-' }} {{ formatTime(enrollment.batch?.start_time) }} - {{ formatTime(enrollment.batch?.end_time) }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-building"></i> Room</span>
                  <span class="info-value">{{ enrollment.batch?.room?.name || '-' }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-chalkboard"></i> Teacher</span>
                  <span class="info-value">{{ enrollment.batch?.teacher?.name || enrollment.batch?.teacher?.first_name || '-' }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-tag"></i> Mode</span>
                  <span class="info-value">{{ enrollment.mode || enrollment.batch?.mode || '-' }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-sitemap"></i> Class / Group</span>
                  <span class="info-value">{{ enrollment.enrolledClass?.name || '-' }} {{ enrollment.enrolledGroup ? '/ ' + enrollment.enrolledGroup.name : '' }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label"><i class="pi pi-calendar"></i> Session</span>
                  <span class="info-value">{{ enrollment.academicSession?.name || '-' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Subjects Card -->
          <div class="detail-card">
            <div class="card-heading">
              <i class="pi pi-list"></i>
              <h3>Subjects ({{ enrollment.subjects?.length || 0 }})</h3>
            </div>
            <div class="card-body">
              <div v-if="enrollment.subjects?.length" class="subjects-grid">
                <div v-for="subject in enrollment.subjects" :key="subject.id" class="subject-chip">
                  <div class="subject-color" :style="{ background: subject.color || 'var(--primary-color)' }"></div>
                  <span class="subject-name">{{ subject.name }}</span>
                  <span class="subject-fee">
                    ৳{{ subject.pivot?.subject_fee || 0 }}
                    <small v-if="enrollment.fee_type === 'monthly'" class="fee-type-hint">/mo</small>
                  </span>
                </div>
              </div>
              <p v-else class="empty-state">
                <i class="pi pi-info-circle"></i> No subjects selected for this enrollment.
              </p>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="right-col">
          <!-- Fee Summary Section -->
          <div class="fee-summary-section">
            <div class="section-header">
              <h3>💰 Fee & Payment Summary</h3>
              <button class="btn btn-primary btn-sm" @click="loadFeeSummary" :disabled="feeLoading">
                {{ feeLoading ? 'Loading...' : '🔄 Refresh' }}
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
                    <span class="summary-value">৳{{ Number(feeSummary.total_fee || 0).toLocaleString() }}</span>
                  </div>
                </div>
                <div class="summary-card total-discount" v-if="feeSummary.total_discount > 0">
                  <div class="summary-icon">🏷️</div>
                  <div class="summary-details">
                    <span class="summary-label">Total Discount</span>
                    <span class="summary-value discount">-৳{{ Number(feeSummary.total_discount || 0).toLocaleString() }}</span>
                  </div>
                </div>
                <div class="summary-card total-paid">
                  <div class="summary-icon">✅</div>
                  <div class="summary-details">
                    <span class="summary-label">Total Paid</span>
                    <span class="summary-value">৳{{ Number(feeSummary.total_paid || 0).toLocaleString() }}</span>
                  </div>
                </div>
                <div class="summary-card total-due">
                  <div class="summary-icon">⏳</div>
                  <div class="summary-details">
                    <span class="summary-label">Total Due</span>
                    <span class="summary-value">৳{{ Number(feeSummary.total_due || 0).toLocaleString() }}</span>
                  </div>
                </div>
                <div class="summary-card payment-pct">
                  <div class="summary-icon">📊</div>
                  <div class="summary-details">
                    <span class="summary-label">Payment Progress</span>
                    <span class="summary-value">{{ (feeSummary.total_fee - feeSummary.total_discount) > 0 ? Math.round((feeSummary.total_paid / (feeSummary.total_fee - feeSummary.total_discount)) * 100) : 0 }}%</span>
                  </div>
                </div>
              </div>

              <!-- Progress Bar -->
              <!-- <div class="progress-bar-container">
                <div class="progress-bar">
                  <div class="progress-fill" :style="{ width: (feeSummary.total_fee > 0 ? Math.round((feeSummary.total_paid / feeSummary.total_fee) * 100) : 0) + '%' }" :class="progressBarClass"></div>
                </div>
                <span class="progress-text">{{ feeSummary.total_fee > 0 ? Math.round((feeSummary.total_paid / feeSummary.total_fee) * 100) : 0 }}% Paid</span>
              </div> -->

              <!-- Fee Card (StudentDetailsPage pattern) -->
              <div class="detail-card fee-card enrollment-fee-card">
                <!-- <div class="enrollment-fee-header">
                  <div>
                    <strong>{{ enrollment.batch?.course?.name || 'Course' }}</strong>
                    <span class="enrollment-fee-badge">{{ enrollment.enrollment_no }}</span>
                    <span :class="['badge', feeTypeBadge]">
                      {{ enrollment.fee_type === 'monthly' ? '📅 Monthly' : '💰 One-Time' }}
                    </span>
                  </div>
                  <div class="enrollment-fee-actions">
                    <span :class="['badge', getPaymentStatusBadge(enrollment.payment_status)]">{{ enrollment.payment_status }}</span>
                    <button v-if="enrollment.fee_type === 'monthly'" class="btn btn-primary btn-sm" @click="openMonthlyPaymentDialogForFirstDue">
                      💳 Pay Now
                    </button>
                    <button v-else-if="enrollment.payable_amount > 0" class="btn btn-primary btn-sm" @click="openOneTimePaymentDialog">
                      💳 Pay Now
                    </button>
                  </div>
                </div> -->
                <div class="enrollment-fee-body">
                  <div class="fee-detail-item">
                    <span class="label">{{ enrollment.fee_type === 'monthly' ? 'Monthly Fee' : 'Total Fee' }}</span>
                    <span class="value">
                      ৳{{ Number(enrollment.fee_type === 'monthly' ? monthlyFeePerMonth : enrollment.total_fee || 0).toLocaleString() }}
                      <small v-if="enrollment.fee_type === 'monthly'">/mo</small>
                    </span>
                  </div>
                  <div v-if="enrollment.fee_type === 'monthly'" class="fee-detail-item">
                    <span class="label">Total Months</span>
                    <span class="value">{{ feeSummary.total_months || monthlyFeeRecords.length || '-' }}</span>
                  </div>
                  <div class="fee-detail-item">
                    <span class="label">Payable</span>
                    <span class="value">৳{{ Number(enrollment.payable_fee || 0).toLocaleString() }}</span>
                  </div>
                  <!-- Discount display — works for both percentage AND flat discounts -->
                  <!-- Uses feeSummary.total_discount from backend (total_monthly_fee - due_amount summed across all records) -->
                  <!-- Falls back to percentage calculation for backward compatibility -->
                  <div v-if="feeSummary.total_discount > 0 || enrollment.discount_percent > 0" class="fee-detail-item">
                    <span class="label">
                      Discount
                      <template v-if="enrollment.fee_type === 'monthly' && enrollment.discount_percent > 0"> ({{ enrollment.discount_percent }}%/mo)</template>
                      <template v-else-if="enrollment.discount_percent > 0"> ({{ enrollment.discount_percent }}%)</template>
                    </span>
                    <span class="value discount">
                      -৳{{ Number(
                        feeSummary.total_discount > 0
                          ? feeSummary.total_discount
                          : (enrollment.total_fee || 0) * (enrollment.discount_percent || 0) / 100
                      ).toLocaleString() }}
                    </span>
                  </div>
                  <div class="fee-detail-item">
                    <span class="label">Paid</span>
                    <span class="value paid">৳{{ Number(feeSummary.total_paid || enrollment.paid_amount || 0).toLocaleString() }}</span>
                  </div>
                  <div class="fee-detail-item">
                    <span class="label">Due</span>
                    <span class="value" :class="(feeSummary.total_due || enrollment.payable_amount || 0) > 0 ? 'due' : 'paid'">৳{{ Number(feeSummary.total_due || enrollment.payable_amount || 0).toLocaleString() }}</span>
                  </div>
                </div>

                <!-- Inline Payment Form for One-Time Fee -->
                <div v-if="enrollment.fee_type !== 'monthly' && enrollment.payable_amount > 0" class="payment-section">
                  <div class="section-title">💳 Record Payment</div>
                  <div class="payment-input-group">
                    <div class="input-wrapper">
                      <span class="input-prefix">৳</span>
                      <input
                        v-model.number="paymentAmount"
                        type="number"
                        min="1"
                        :max="enrollment.payable_amount"
                        class="payment-input"
                        placeholder="Enter amount"
                      />
                    </div>
                    <button class="btn-pay" @click="recordPayment" :disabled="!paymentAmount || paying">
                      {{ paying ? 'Processing...' : 'Pay Now' }}
                    </button>
                  </div>
                  <small style="color: var(--text-muted); margin-top: 6px; display: block;">
                    Max payable: ৳{{ Number(enrollment.payable_amount || 0).toLocaleString() }}
                  </small>
                </div>

                <!-- Month Status Bar (for monthly fee type) -->
                <div v-if="enrollment.fee_type === 'monthly' && feeSummary" class="month-status-bar">
                  <div class="month-status-item last-paid">
                    <span class="month-status-label">✅ Last Paid</span>
                    <span class="month-status-value" v-if="feeSummary.last_paid_month">
                      {{ feeSummary.last_paid_month_name }} — ৳{{ Number(feeSummary.last_paid_amount).toLocaleString() }}
                    </span>
                    <span class="month-status-value no-data" v-else>No payments yet</span>
                  </div>
                  <div class="month-status-divider"></div>
                  <div class="month-status-item next-pending">
                    <span class="month-status-label">⏳ Next Due</span>
                    <span class="month-status-value" v-if="feeSummary.next_month_name && feeSummary.next_month_due > 0">
                      {{ feeSummary.next_month_name }} — ৳{{ Number(feeSummary.next_month_due).toLocaleString() }}
                      <span :class="['badge', getMonthlyStatusBadge(feeSummary.next_month_status)]" class="month-status-badge">{{ feeSummary.next_month_status }}</span>
                    </span>
                    <span class="month-status-value no-data" v-else>All months paid ✅</span>
                  </div>
                </div>

                <!-- Monthly Fee Records (for monthly fee type) -->
                <div v-if="enrollment.fee_type === 'monthly' && monthlyFeeRecords.length > 0" class="monthly-records">
                  <h4>📅 All Monthly Records</h4>
                  <div class="monthly-records-table">
                    <div class="monthly-record-header">
                      <span>Month</span>
                      <span>Total</span>
                      <span>Discount</span>
                      <span>Payable</span>
                      <span>Paid</span>
                      <span>Payable</span>
                      <span>Status</span>
                      <span style="width:80px">Action</span>
                    </div>
                    <div
                      v-for="rec in monthlyFeeRecords"
                      :key="rec.id"
                      :class="['monthly-record-row', 'row-' + rec.payment_status, { 'row-next-pay': feeSummary?.next_unpaid_month?.id === rec.id }]"
                    >
                      <span>
                        {{ formatMonthName(rec.month) }}
                        <span v-if="feeSummary?.next_unpaid_month?.id === rec.id" class="next-badge">Next</span>
                        <span v-if="rec.month === currentMonthStr" class="current-badge">Now</span>
                      </span>
                      <span>৳{{ Number(rec.total_monthly_fee).toLocaleString() }}</span>
                      <span class="discount">-৳{{ Number(rec.total_monthly_fee - rec.due_amount).toLocaleString() }}</span>
                      <span>৳{{ Number(rec.due_amount).toLocaleString() }}</span>
                      <span class="paid">৳{{ Number(rec.paid_amount).toLocaleString() }}</span>
                      <!-- Use balance field from backend (due_amount - paid_amount + fine) -->
                      <!-- Falls back to manual calculation if balance not available -->
                      <span class="payable">৳{{ Number(rec.balance ?? Math.max(0, rec.due_amount - rec.paid_amount) + (rec.fine_amount || 0)).toLocaleString() }}</span>
                      <span :class="['badge', getMonthlyStatusBadge(rec.payment_status)]">{{ rec.payment_status }}</span>
                      <span>
                        <div class="action-btns">
                          <!-- Use balance for pay button condition: show if balance > 0 and not fully paid -->
                          <button v-if="(rec.balance ?? Math.max(0, rec.due_amount - rec.paid_amount) + (rec.fine_amount || 0)) > 0 && rec.payment_status !== 'paid'" class="btn-pay-mini" :disabled="isRecordLocked(rec)" :class="{ 'locked': isRecordLocked(rec) }" @click="openMonthlyPaymentDialog(rec)" :title="isRecordLocked(rec) ? '🔒 Payment awaiting admin approval for this month' : 'Pay for ' + formatMonthName(rec.month)">{{ isRecordLocked(rec) ? '🔒' : '💳' }} {{ isRecordLocked(rec) ? 'Locked' : 'Pay' }}</button>
                          <button v-if="rec.confirmed_payments?.length" class="btn-invoice-mini" @click="downloadInvoice(rec.confirmed_payments[0]?.invoice?.id || rec.confirmed_payments[0]?.id)" title="Download Invoice">📄</button>
                          <span v-if="rec.payment_status === 'paid' && !rec.confirmed_payments?.length" class="paid-check">✅</span>
                        </div>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Recent Payments -->
                <div v-if="allConfirmedPayments.length > 0" class="recent-payments">
                  <h4>🕐 Recent Payments</h4>
                  <div class="payment-history-list">
                    <div
                      v-for="payment in allConfirmedPayments"
                      :key="payment.id"
                      class="payment-history-item"
                    >
                      <div class="payment-info">
                        <span class="payment-receipt">#{{ payment.invoice?.invoice_no || payment.id }}</span>
                        <span class="payment-date">{{ formatDate(payment.created_at) }}</span>
                        <span class="payment-method">{{ payment.payment_method || '—' }}</span>
                      </div>
                      <div class="payment-amount-info">
                        <span class="payment-amount">৳{{ Number(payment.amount).toLocaleString() }}</span>
                        <span :class="['badge', getPaymentStatusBadge(payment.payment_status)]">{{ payment.payment_status }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- No Fee Data -->
            <div v-else class="no-fee-data">
              <p>Click "Refresh" to load fee payment information.</p>
            </div>
          </div>

          <!-- Actions Card -->
          <div class="detail-card actions-card">
            <div class="card-heading">
              <i class="pi pi-cog"></i>
              <h3>Actions</h3>
            </div>
            <div class="card-body">
              <button v-if="enrollment.status === 'active'" @click="openChangeBatch" class="action-btn change-batch">
                <i class="pi pi-refresh"></i>
                <div class="action-text">
                  <span class="action-title">Change Batch</span>
                  <span class="action-desc">Move to a different batch</span>
                </div>
                <i class="pi pi-chevron-right"></i>
              </button>
              <button v-if="enrollment.status === 'pending'" @click="confirmEnrollmentAction" class="action-btn confirm">
                <i class="pi pi-check-circle"></i>
                <div class="action-text">
                  <span class="action-title">Confirm Enrollment</span>
                  <span class="action-desc">Activate this enrollment</span>
                </div>
                <i class="pi pi-chevron-right"></i>
              </button>
              <button v-if="enrollment.status === 'active'" @click="confirmDropout" class="action-btn dropout">
                <i class="pi pi-user-minus"></i>
                <div class="action-text">
                  <span class="action-title">Dropout</span>
                  <span class="action-desc">Remove from this batch</span>
                </div>
                <i class="pi pi-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Not Found -->
    <div v-else class="empty-state full-page">
      <i class="pi pi-exclamation-circle"></i>
      <h3>Enrollment not found</h3>
      <router-link to="/dashboard/enrollment/enrollments" class="btn-back-link">Back to Enrollments</router-link>
    </div>

    <!-- Change Batch Dialog -->
    <Dialog v-model:visible="showChangeBatch" header="Change Batch" :modal="true" :style="{ width: '420px' }" class="batch-dialog">
      <div class="dialog-body">
        <p class="dialog-desc">Select a new batch for <strong>{{ enrollment.student?.first_name }} {{ enrollment.student?.last_name }}</strong></p>
        <div class="field">
          <label class="field-label">Current Batch</label>
          <div class="current-batch-info">
            <i class="pi pi-bookmark"></i> {{ enrollment.batch?.name }}
          </div>
        </div>
        <div class="field">
          <label class="field-label" for="newBatch">New Batch</label>
          <select id="newBatch" v-model="newBatchId" class="form-select">
            <option value="">Select New Batch</option>
            <option v-for="batch in availableBatches" :key="batch.id" :value="batch.id">
              {{ batch.name }} ({{ batch.available_seats || batch.availableSeats || '?' }} seats)
            </option>
          </select>
        </div>
      </div>
      <template #footer>
        <button @click="showChangeBatch = false" class="btn-cancel">Cancel</button>
        <button @click="changeBatch" class="btn-primary" :disabled="!newBatchId || changing">
          {{ changing ? 'Changing...' : 'Change Batch' }}
        </button>
      </template>
    </Dialog>

    <!-- Monthly Fee Payment Dialog (StudentDetailsPage pattern) -->
    <div v-if="showMonthlyPaymentDialog" class="modal-overlay" @click.self="showMonthlyPaymentDialog = false">
      <div class="modal-content payment-modal">
        <div class="modal-header">
          <h3>💳 Record Monthly Fee Payment</h3>
          <button class="modal-close" @click="showMonthlyPaymentDialog = false">&times;</button>
        </div>
        <div class="modal-body">
          <!-- Enrollment Info -->
          <div class="payment-enrollment-info">
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Student</span>
                <span class="info-value">{{ enrollment.student?.first_name }} {{ enrollment.student?.last_name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Course</span>
                <span class="info-value">{{ enrollment.batch?.course?.name || '—' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Enrollment</span>
                <span class="info-value">{{ enrollment.enrollment_no }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Fee Type</span>
                <span class="info-value">📅 Monthly</span>
              </div>
              <div class="info-item" v-if="selectedMonthlyRecord">
                <span class="info-label">Selected Month</span>
                <span class="info-value">
                  <span class="next-month-highlight">{{ formatMonthName(selectedMonthlyRecord.month) }}</span>
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">Payable Amount</span>
                <span class="info-value due">৳{{ Number(selectedMonthlyRecord?.due_amount || 0).toLocaleString() }}</span>
              </div>
            </div>
          </div>

          <!-- Month Selector for Monthly Fee Type -->
          <div class="form-group full-width" v-if="pendingMonthlyRecords.length > 0">
            <label for="monthSelect">Pay For Month</label>
            <select id="monthSelect" v-model="monthlyPaymentForm.monthly_record_id" class="form-input" @change="onMonthChange">
              <option value="">— Select a month —</option>
              <option
                v-for="rec in pendingMonthlyRecords"
                :key="rec.id"
                :value="rec.id"
              >
                {{ formatMonthName(rec.month) }} — Due: ৳{{ Number(rec.due_amount).toLocaleString() }} ({{ rec.payment_status }})
              </option>
            </select>
            <small class="form-hint">Select a specific month to pay, or choose the pre-filled month below</small>
          </div>

          <!-- Two-column form fields -->
          <div class="form-row-2col">
            <div class="form-group">
              <label for="paymentAmount">Amount (৳) *</label>
              <input
                id="paymentAmount"
                v-model.number="monthlyPaymentForm.amount"
                type="number"
                min="1"
                step="0.01"
                class="form-input"
                placeholder="Enter payment amount"
                :max="selectedMonthlyRecord?.due_amount || 0"
              />
              <small class="form-hint">Max payable: ৳{{ Number(selectedMonthlyRecord?.due_amount || 0).toLocaleString() }}</small>
            </div>

            <div class="form-group">
              <label for="paymentMethod">Payment Method</label>
              <select id="paymentMethod" v-model="monthlyPaymentForm.method" class="form-input">
                <option value="cash">💵 Cash</option>
                <option value="bkash">📱 bKash</option>
                <option value="nagad">📱 Nagad</option>
                <option value="rocket">🚀 Rocket</option>
                <option value="bank_transfer">🏦 Bank Transfer</option>
              </select>
            </div>

            <div class="form-group">
              <label for="transactionId">Transaction ID</label>
              <input
                id="transactionId"
                v-model="monthlyPaymentForm.transaction_id"
                type="text"
                class="form-input"
                placeholder="e.g., TRX123456"
              />
              <small class="form-hint">Required for non-cash payments</small>
            </div>

            <div class="form-group">
              <label for="paymentNote">Note</label>
              <input
                id="paymentNote"
                v-model="monthlyPaymentForm.note"
                type="text"
                class="form-input"
                placeholder="Any notes..."
              />
              <small class="form-hint">Optional</small>
            </div>
          </div>

          <!-- Conditional fields for mobile banking -->
          <div v-if="monthlyPaymentForm.method === 'bkash' || monthlyPaymentForm.method === 'nagad' || monthlyPaymentForm.method === 'rocket'" class="form-group full-width">
            <label for="senderNumber">Sender Number</label>
            <input id="senderNumber" v-model="monthlyPaymentForm.sender_number" class="form-input" placeholder="e.g. 01XXXXXXXXX" />
          </div>
          <div v-if="monthlyPaymentForm.method === 'bank_transfer'" class="form-group full-width">
            <label for="bankName">Bank Name</label>
            <input id="bankName" v-model="monthlyPaymentForm.bank_name" class="form-input" placeholder="Enter bank name" />
          </div>

          <!-- Info note for non-cash methods -->
          <div v-if="monthlyPaymentForm.method !== 'cash'" class="info-note">
            <i class="pi pi-info-circle"></i>
            <span>Payments via <strong>{{ formatMethod(monthlyPaymentForm.method) }}</strong> require admin confirmation. Invoice will be auto-generated upon confirmation.</span>
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn btn-outline" @click="showMonthlyPaymentDialog = false" :disabled="monthlyPaying">Cancel</button>
          <button class="btn btn-primary" :disabled="!monthlyPaymentForm.amount || monthlyPaying" @click="submitMonthlyPayment">
            {{ monthlyPaying ? 'Processing...' : '✅ Confirm Payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import enrollmentService from '@/services/enrollment.service';
import monthlyFeeService from '@/services/monthly-fee.service';
import smartFeeService from '@/services/smart-fee.service';
import Dialog from 'primevue/dialog';

export default {
  name: 'EnrollmentDetailsPage',
  components: { Dialog },
  data() {
    return {
      enrollment: {},
      loading: false,
      paymentAmount: null,
      paying: false,
      showChangeBatch: false,
      newBatchId: '',
      availableBatches: [],
      changing: false,
      // Fee summary data (StudentDetailsPage pattern)
      feeSummary: null,
      feeLoading: false,
      feeError: null,
      monthlyFeeRecords: [],
      monthlyFeeSummary: null,
      // Monthly fee payment dialog
      showMonthlyPaymentDialog: false,
      selectedMonthlyRecord: null,
      monthlyPaying: false,
      monthlyPaymentForm: { amount: 0, method: 'cash', transaction_id: '', sender_number: '', bank_name: '', note: '', monthly_record_id: '' },
      paymentMethods: ['cash', 'bkash', 'nagad', 'rocket', 'bank_transfer'],
      pendingPayments: {}, // { paymentId: { assignment_ids: [...], ... } }
    };
  },
  computed: {
    statusClass() {
      const map = { active: 'badge-success', pending: 'badge-warning', completed: 'badge-info', dropped: 'badge-danger' };
      return map[this.enrollment.status] || 'badge-secondary';
    },
    paymentClass() {
      const map = { paid: 'badge-success', partial: 'badge-warning', pending: 'badge-danger' };
      return map[this.enrollment.payment_status] || 'badge-secondary';
    },
    feeTypeBadge() {
      return this.enrollment.fee_type === 'monthly' ? 'fee-type-badge-monthly' : 'fee-type-badge-onetime';
    },
    payableFee() {
      return Number(this.enrollment.payable_fee || 0);
    },
    paidAmount() {
      return Number(this.enrollment.paid_amount || 0);
    },
    feePercentage() {
      const payable = this.payableFee;
      if (payable <= 0) return this.paidAmount > 0 ? 100 : 0;
      const pct = Math.round((this.paidAmount / payable) * 100);
      return Math.min(pct, 100);
    },
    progressRingOffset() {
      const circumference = 2 * Math.PI * 52;
      const pct = Math.min(1, this.paidAmount / Math.max(1, this.payableFee));
      return circumference * (1 - pct);
    },
    currentMonthStr() {
      const d = new Date();
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    },
    /** Per-month fee: get from first monthly record's due_amount (discounted), or calculate from summary */
    monthlyFeePerMonth() {
      // For one-time fee type, return the total fee
      if (this.enrollment.fee_type !== 'monthly') {
        return Number(this.enrollment.total_fee || 0);
      }
      // First try from monthlyFeeRecords array — use due_amount (discounted) so the label
      // shows what the student actually pays per month (e.g., 3,600 after 10% discount on 4,000)
      if (Array.isArray(this.monthlyFeeRecords) && this.monthlyFeeRecords.length > 0) {
        return Number(this.monthlyFeeRecords[0].due_amount || 0);
      }
      // Fallback: try from feeSummary.records
      if (this.feeSummary?.records && Array.isArray(this.feeSummary.records) && this.feeSummary.records.length > 0) {
        return Number(this.feeSummary.records[0].due_amount || 0);
      }
      // Fallback: try from monthlyFeeSummary.records
      if (this.monthlyFeeSummary?.records && Array.isArray(this.monthlyFeeSummary.records) && this.monthlyFeeSummary.records.length > 0) {
        return Number(this.monthlyFeeSummary.records[0].due_amount || 0);
      }
      // Last resort: calculate from payable_fee / total_months
      if (this.enrollment.payable_fee && this.feeSummary?.total_months) {
        return Number(this.enrollment.payable_fee) / Number(this.feeSummary.total_months);
      }
      if (this.monthlyFeeSummary?.total_fee && this.monthlyFeeSummary?.total_months) {
        return Number(this.monthlyFeeSummary.total_fee) / Number(this.monthlyFeeSummary.total_months);
      }
      return 0;
    },
    /** All confirmed payments across all monthly records */
    allConfirmedPayments() {
      const payments = [];
      // Check monthlyFeeRecords first
      if (Array.isArray(this.monthlyFeeRecords)) {
        for (const rec of this.monthlyFeeRecords) {
          if (rec.confirmed_payments && Array.isArray(rec.confirmed_payments)) {
            for (const p of rec.confirmed_payments) {
              payments.push(p);
            }
          }
        }
      }
      // Also check feeSummary.records if monthlyFeeRecords is empty
      if (payments.length === 0 && this.feeSummary?.records && Array.isArray(this.feeSummary.records)) {
        for (const rec of this.feeSummary.records) {
          if (rec.confirmed_payments && Array.isArray(rec.confirmed_payments)) {
            for (const p of rec.confirmed_payments) {
              payments.push(p);
            }
          }
        }
      }
      // Sort by created_at descending, take latest 5
      payments.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
      return payments.slice(0, 5);
    },
    /** Progress bar color class based on percentage */
    progressBarClass() {
      const total = this.feeSummary?.total_fee || this.monthlyFeeSummary?.total_fee || Number(this.enrollment.total_fee || 0);
      const paid = this.feeSummary?.total_paid || this.monthlyFeeSummary?.total_paid || Number(this.enrollment.paid_amount || 0);
      const pct = total > 0 ? Math.round((paid / total) * 100) : 0;
      if (pct >= 80) return 'progress-high';
      if (pct >= 50) return 'progress-mid';
      if (pct >= 25) return 'progress-low';
      return 'progress-very-low';
    },
    /** Pending monthly records (not fully paid) for month selector */
    pendingMonthlyRecords() {
      if (Array.isArray(this.monthlyFeeRecords) && this.monthlyFeeRecords.length > 0) {
        return this.monthlyFeeRecords.filter(r => r.payment_status !== 'paid');
      }
      // Fallback: check feeSummary.records
      if (this.feeSummary?.records && Array.isArray(this.feeSummary.records) && this.feeSummary.records.length > 0) {
        return this.feeSummary.records.filter(r => r.payment_status !== 'paid');
      }
      return [];
    },
  },
  created() {
    this.loadEnrollment();
  },
  methods: {
    async loadEnrollment() {
      this.loading = true;
      try {
        const res = await enrollmentService.getEnrollment(this.$route.params.id);
        this.enrollment = res.data?.data || res.data;

        // Load fee summary (works for both monthly and one-time fee types)
        await this.loadFeeSummary();
        // Load pending payments for per-record lock
        this.loadPendingPayments();
      } catch (e) { console.error(e); }
      finally { this.loading = false; }
    },
    async loadPendingPayments() {
      try {
        const res = await smartFeeService.student.payments(this.$route.params.id);
        const payments = res.data?.data?.data || res.data?.data || [];
        this.pendingPayments = {};
        payments.forEach(p => {
          if (p.status === 'pending') {
            const allocs = p.allocations || [];
            const assignmentIds = allocs.length > 0
              ? allocs.map(a => a.fee_assignment_id).filter(Boolean)
              : [p.fee_assignment_id].filter(Boolean);
            this.pendingPayments[p.id] = { assignment_ids: assignmentIds };
          }
        });
      } catch (e) { /* silently fail - lock is best-effort */ }
    },
    isRecordLocked(rec) {
      if (!rec) return false;
      const candidateIds = [rec.fee_assignment_id, rec.id, rec.monthly_fee_record_id].filter(Boolean).map(String);
      return Object.values(this.pendingPayments).some(p => {
        if (p.assignment_ids && p.assignment_ids.length > 0) {
          if (p.assignment_ids.some(id => candidateIds.includes(String(id)))) return true;
        }
        if (candidateIds.includes(String(p.fee_assignment_id))) return true;
        if (candidateIds.includes(String(p.monthly_fee_record_id))) return true;
        return false;
      });
    },
    async loadFeeSummary() {
      this.feeLoading = true;
      this.feeError = null;
      try {
        if (this.enrollment.fee_type === 'monthly') {
          // For monthly fee type: use the summary endpoint which returns comprehensive fee data
          const sumRes = await monthlyFeeService.getEnrollmentSummary(this.$route.params.id);
          const rawSummary = sumRes.data?.data || sumRes.data || null;
          const summary = rawSummary?.summary || rawSummary || null;
          this.feeSummary = summary;

          // Also load monthly fee records for the monthly records table
          try {
            const recRes = await monthlyFeeService.getEnrollmentRecords(this.$route.params.id);
            const recordsData = recRes.data?.data || {};
            this.monthlyFeeRecords = recordsData.records || [];
            this.monthlyFeeSummary = summary;
          } catch (e) {
            console.error('Failed to load monthly fee records:', e);
            // If records endpoint fails, try to get records from summary
            if (summary?.records && Array.isArray(summary.records)) {
              this.monthlyFeeRecords = summary.records;
            }
            this.monthlyFeeSummary = summary;
          }
        } else {
          // For one-time fee type: build summary from enrollment data directly
          this.monthlyFeeRecords = [];
          this.monthlyFeeSummary = null;
          // Calculate discount amount for one-time fee type
          const totalFee = Number(this.enrollment.total_fee || 0);
          const discountPercent = Number(this.enrollment.discount_percent || 0);
          const totalDiscount = discountPercent > 0 ? (totalFee * discountPercent / 100) : 0;
          this.feeSummary = {
            total_fee: totalFee,
            total_discount: totalDiscount,
            total_paid: Number(this.enrollment.paid_amount || 0) + Number(this.enrollment.enrollment_fee_paid || 0),
            total_due: Number(this.enrollment.due_amount || 0),
            total_months: 1,
            paid_months: this.enrollment.payment_status === 'paid' ? 1 : 0,
            pending_months: this.enrollment.payment_status === 'pending' ? 1 : 0,
            partial_months: this.enrollment.payment_status === 'partial' ? 1 : 0,
            overdue_months: 0,
            current_month: null,
            current_month_name: null,
            next_unpaid_month: null,
            next_month_name: null,
            next_month_due: this.enrollment.payable_amount > 0 ? Number(this.enrollment.payable_amount) : 0,
            next_month_status: this.enrollment.payment_status === 'paid' ? 'paid' : 'pending',
            last_paid_month: null,
            last_paid_month_name: null,
            last_paid_amount: this.enrollment.payment_status === 'paid' ? Number(this.enrollment.paid_amount) : 0,
            records: [],
            student_name: this.enrollment.student?.full_name || 'N/A',
            course_name: this.enrollment.batch?.course?.name || 'N/A',
            enrollment_status: this.enrollment.status,
            payment_status: this.enrollment.payment_status,
          };
        }
      } catch (e) {
        console.error('Failed to load fee summary:', e);
        this.feeError = e.response?.data?.message || 'Failed to load fee summary';
        // Fallback: use enrollment data for basic fee info
        const totalFee = Number(this.enrollment.total_fee || 0);
        const discountPercent = Number(this.enrollment.discount_percent || 0);
        this.feeSummary = {
          total_fee: totalFee,
          total_discount: discountPercent > 0 ? (totalFee * discountPercent / 100) : 0,
          total_paid: Number(this.enrollment.paid_amount || 0) + Number(this.enrollment.enrollment_fee_paid || 0),
          total_due: Number(this.enrollment.due_amount || 0),
          total_months: this.enrollment.total_months || 1,
          paid_months: this.enrollment.paid_months || 0,
        };
      } finally {
        this.feeLoading = false;
      }
    },
    async recordPayment() {
      if (!this.paymentAmount) return;
      this.paying = true;
      try {
        await enrollmentService.recordPayment(this.$route.params.id, { amount: Number(this.paymentAmount) });
        this.paymentAmount = null;
        await this.loadEnrollment();
        this.$toast?.success('Payment recorded successfully');
      } catch (e) {
        console.error(e);
        this.$toast?.error(e.response?.data?.message || 'Payment failed');
      } finally { this.paying = false; }
    },
    async confirmEnrollmentAction() {
      if (!confirm('Confirm this enrollment?')) return;
      try {
        await enrollmentService.confirmEnrollment(this.$route.params.id);
        await this.loadEnrollment();
        this.$toast?.success('Enrollment confirmed');
      } catch (e) { console.error(e); }
    },
    async confirmDropout() {
      if (!confirm('Are you sure you want to dropout this student?')) return;
      try {
        await enrollmentService.dropoutStudent(this.$route.params.id);
        await this.loadEnrollment();
        this.$toast?.success('Student dropped out');
      } catch (e) { console.error(e); }
    },
    formatTime(time) {
      if (!time) return '-';
      const parts = time.split(':');
      if (parts.length < 2) return time;
      let hours = parseInt(parts[0], 10);
      const minutes = parts[1];
      const ampm = hours >= 12 ? 'PM' : 'AM';
      if (hours === 0) hours = 12;
      else if (hours > 12) hours -= 12;
      return hours + ':' + minutes + ' ' + ampm;
    },
    formatMonthName(monthStr) {
      if (!monthStr) return '-';
      // monthStr is in YYYY-MM format
      const parts = monthStr.split('-');
      if (parts.length < 2) return monthStr;
      const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
      const monthIndex = parseInt(parts[1], 10) - 1;
      return months[monthIndex] + ' ' + parts[0];
    },
    async openChangeBatch() {
      this.showChangeBatch = true;
      this.newBatchId = '';
      try {
        const courseId = this.enrollment.batch?.course_id;
        if (courseId) {
          const res = await enrollmentService.getBatchesByCourse(courseId);
          this.availableBatches = res.data?.data || res.data || [];
        }
      } catch (e) { console.error(e); }
    },
    async changeBatch() {
      if (!this.newBatchId) return;
      this.changing = true;
      try {
        await enrollmentService.changeBatch(this.$route.params.id, this.newBatchId);
        this.showChangeBatch = false;
        await this.loadEnrollment();
        this.$toast?.success('Batch changed successfully');
      } catch (e) {
        console.error(e);
        this.$toast?.error(e.response?.data?.message || 'Failed to change batch');
      } finally { this.changing = false; }
    },
    // ===== Monthly Fee Payment Methods =====
    formatMethod(method) {
      const labels = { cash: 'Cash', bkash: 'bKash', nagad: 'Nagad', rocket: 'Rocket', bank_transfer: 'Bank Transfer', bank: 'Bank', online: 'Online' };
      return labels[method] || method;
    },
    onMethodChange(method) {
      this.monthlyPaymentForm.method = method;
      if (method === 'cash') {
        this.monthlyPaymentForm.sender_number = '';
        this.monthlyPaymentForm.bank_name = '';
        this.monthlyPaymentForm.transaction_id = '';
      }
    },
    onMonthChange() {
      // When a specific month is selected, update the selected record and auto-fill amount
      if (this.monthlyPaymentForm.monthly_record_id && Array.isArray(this.monthlyFeeRecords)) {
        const selected = this.monthlyFeeRecords.find(r => r.id === this.monthlyPaymentForm.monthly_record_id);
        if (selected) {
          this.selectedMonthlyRecord = selected;
          this.monthlyPaymentForm.amount = selected.due_amount > 0 ? selected.due_amount : selected.total_monthly_fee;
        }
      }
    },
    openMonthlyPaymentDialog(record) {
      this.selectedMonthlyRecord = record;
      this.monthlyPaymentForm = { amount: (record.balance ?? Math.max(0, record.due_amount - record.paid_amount) + (record.fine_amount || 0)) || 0, method: 'cash', transaction_id: '', sender_number: '', bank_name: '', note: '', monthly_record_id: record.id || '' };
      this.showMonthlyPaymentDialog = true;
    },
    async submitMonthlyPayment() {
      if (!this.monthlyPaymentForm.amount || !this.selectedMonthlyRecord) return;
      this.monthlyPaying = true;
      try {
        const payload = {
          amount: Number(this.monthlyPaymentForm.amount),
          payment_method: this.monthlyPaymentForm.method,
          transaction_id: this.monthlyPaymentForm.transaction_id || null,
          note: this.monthlyPaymentForm.note || null,
        };
        // Include monthly_record_id if a specific month was selected via dropdown
        if (this.monthlyPaymentForm.monthly_record_id) {
          payload.monthly_record_id = this.monthlyPaymentForm.monthly_record_id;
        }
        if (this.monthlyPaymentForm.method === 'bkash' || this.monthlyPaymentForm.method === 'nagad' || this.monthlyPaymentForm.method === 'rocket') {
          payload.sender_number = this.monthlyPaymentForm.sender_number || null;
        }
        if (this.monthlyPaymentForm.method === 'bank_transfer') {
          payload.bank_name = this.monthlyPaymentForm.bank_name || null;
        }
        await monthlyFeeService.recordPayment(this.selectedMonthlyRecord.id, payload);
        this.showMonthlyPaymentDialog = false;
        this.selectedMonthlyRecord = null;
        // Reload fee summary and enrollment
        await this.loadFeeSummary();
        const res = await enrollmentService.getEnrollment(this.$route.params.id);
        this.enrollment = res.data?.data || res.data;
        this.$toast?.success('Monthly payment recorded successfully');
      } catch (e) {
        console.error(e);
        this.$toast?.error(e.response?.data?.message || 'Payment failed');
      } finally {
        this.monthlyPaying = false;
      }
    },
    openOneTimePaymentDialog() {
      // For one-time fee, just use the existing payment input in the fee card
      // Focus the payment amount input
      this.paymentAmount = this.enrollment.payable_amount || 0;
      this.$nextTick(() => {
        const input = document.querySelector('.payment-input');
        if (input) input.focus();
      });
    },
    async downloadInvoice(paymentId) {
      try {
        const response = await monthlyFeeService.downloadInvoice(paymentId);
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `invoice-${paymentId}.pdf`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      } catch (e) {
        console.error('Failed to download invoice:', e);
        this.$toast?.error('Failed to download invoice');
      }
    },
    // ===== Helper Methods (matching StudentDetailsPage pattern) =====
    getPaymentStatusBadge(status) {
      const map = { paid: 'badge-success', partial: 'badge-warning', pending: 'badge-danger', awaiting_confirmation: 'badge-info' };
      return map[status] || 'badge-secondary';
    },
    getMonthlyStatusBadge(status) {
      const map = { paid: 'badge-success', partial: 'badge-warning', pending: 'badge-danger', awaiting_confirmation: 'badge-info' };
      return map[status] || 'badge-secondary';
    },
    formatDate(dateStr) {
      if (!dateStr) return '-';
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-BD', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    openMonthlyPaymentDialogForFirstDue() {
      // Find the first record with due_amount > 0 in monthlyFeeRecords
      if (Array.isArray(this.monthlyFeeRecords)) {
        const firstDue = this.monthlyFeeRecords.find(r => (r.balance ?? Math.max(0, r.due_amount - r.paid_amount) + (r.fine_amount || 0)) > 0 && r.payment_status !== 'paid');
        if (firstDue) {
          this.openMonthlyPaymentDialog(firstDue);
          return;
        }
      }
      // If no due record found, try opening the next unpaid month from monthlyFeeSummary
      if (this.monthlyFeeSummary?.next_unpaid_month?.id) {
        const nextRec = this.monthlyFeeRecords.find(r => r.id === this.monthlyFeeSummary.next_unpaid_month.id);
        if (nextRec) {
          this.openMonthlyPaymentDialog(nextRec);
          return;
        }
      }
      // Also check feeSummary.next_unpaid_month (primary source)
      if (this.feeSummary?.next_unpaid_month?.id) {
        const nextRec = this.monthlyFeeRecords.find(r => r.id === this.feeSummary.next_unpaid_month.id);
        if (nextRec) {
          this.openMonthlyPaymentDialog(nextRec);
          return;
        }
        // If the record isn't in monthlyFeeRecords yet, try to find by month
        if (this.feeSummary.next_unpaid_month.month) {
          const byMonth = this.monthlyFeeRecords.find(r => r.month === this.feeSummary.next_unpaid_month.month);
          if (byMonth) {
            this.openMonthlyPaymentDialog(byMonth);
            return;
          }
        }
      }
      this.$toast?.info('All months are paid. No pending payments.');
    },
  },
};
</script>

<style scoped>
.enrollment-details-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
}

/* ===== HEADER ===== */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 28px;
  flex-wrap: wrap;
  gap: 12px;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}
.btn-back {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  color: var(--text-dark);
  font-size: 16px;
  transition: var(--transition);
}
.btn-back:hover {
  background: var(--bg-card-hover);
  border-color: var(--primary-color);
  color: var(--primary-color);
}
.header-left h1 {
  font-size: 22px;
  font-weight: 700;
  color: var(--text-heading);
  margin: 0;
  line-height: 1.3;
}
.header-subtitle {
  font-size: 13px;
  color: var(--text-muted);
  margin: 2px 0 0;
}
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  text-transform: capitalize;
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.status-badge.active { background: #ecfdf5; color: #059669; }
.status-badge.active .status-dot { background: #059669; }
.status-badge.pending { background: #fffbeb; color: #d97706; }
.status-badge.pending .status-dot { background: #d97706; }
.status-badge.completed { background: #eff6ff; color: #2563eb; }
.status-badge.completed .status-dot { background: #2563eb; }
.status-badge.dropped { background: #fef2f2; color: #dc2626; }
.status-badge.dropped .status-dot { background: #dc2626; }
.status-badge.waiting { background: #f5f3ff; color: #7c3aed; }
.status-badge.waiting .status-dot { background: #7c3aed; }

/* ===== LOADING ===== */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  color: var(--text-muted);
  gap: 16px;
}
.spinner-ring {
  width: 48px;
  height: 48px;
  border: 4px solid var(--border-color);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== STATS ROW ===== */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: var(--transition);
}
.stat-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}
.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 20px;
  flex-shrink: 0;
}
.stat-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.stat-label {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.stat-value {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-heading);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.stat-value.code {
  font-family: 'Courier New', monospace;
  font-size: 14px;
  letter-spacing: 0.5px;
}
.stat-value.payment-paid { color: var(--color-success); }
.stat-value.payment-partial { color: var(--color-warning); }
.stat-value.payment-pending { color: var(--color-danger); }

/* ===== CONTENT GRID ===== */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 24px;
  align-items: start;
}
@media (max-width: 900px) {
  .content-grid { grid-template-columns: 1fr; }
}

/* ===== DETAIL CARDS ===== */
.detail-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  overflow: hidden;
  margin-bottom: 20px;
  transition: var(--transition);
}
.detail-card:hover {
  box-shadow: var(--shadow-sm);
}
.card-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg-accent);
}
.card-heading i {
  font-size: 18px;
  color: var(--primary-color);
}
.card-heading h3 {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-heading);
  margin: 0;
}
.card-body {
  padding: 20px;
}

/* ===== STUDENT PROFILE ===== */
.student-profile {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}
.avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary-color), #6366f1);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 700;
  flex-shrink: 0;
}
.profile-info h4 {
  font-size: 17px;
  font-weight: 600;
  color: var(--text-heading);
  margin: 0 0 2px;
}
.student-id {
  font-size: 13px;
  color: var(--text-muted);
  font-family: 'Courier New', monospace;
}
.info-divider {
  height: 1px;
  background: var(--border-light);
  margin: 12px 0;
}

/* ===== INFO LIST ===== */
.info-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
}
.info-label {
  font-size: 13px;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 6px;
}
.info-label i {
  font-size: 13px;
  width: 16px;
  text-align: center;
}
.info-value {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-dark);
  text-align: right;
  max-width: 60%;
}

/* ===== COURSE BADGE ===== */
.course-badge {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 12px;
}
.course-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-sm);
  background: linear-gradient(135deg, #4facfe, #00f2fe);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 20px;
  flex-shrink: 0;
}
.course-info h4 {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-heading);
  margin: 0 0 2px;
}
.batch-name {
  font-size: 13px;
  color: var(--text-muted);
}

/* ===== SUBJECTS ===== */
.subjects-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.subject-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--bg-accent);
  border: 1px solid var(--border-light);
  border-radius: 8px;
  padding: 8px 14px;
  transition: var(--transition);
}
.subject-chip:hover {
  border-color: var(--primary-color);
  background: var(--bg-card-hover);
}
.subject-color {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.subject-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-dark);
}
.subject-fee {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 500;
  margin-left: 4px;
}
.fee-type-hint {
  font-size: 10px;
  color: var(--primary-color);
  font-weight: 600;
  margin-left: 2px;
}

/* ===== FEE CARD ===== */
.fee-visual {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}
.fee-circle {
  position: relative;
  width: 120px;
  height: 120px;
}
.progress-ring {
  width: 100%;
  height: 100%;
}
.fee-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.fee-percent {
  font-size: 24px;
  font-weight: 700;
  color: var(--text-heading);
  line-height: 1;
}
.fee-paid-label {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-top: 2px;
}

.fee-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.fee-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
}
.fee-label {
  font-size: 13px;
  color: var(--text-muted);
}
.fee-amount {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-dark);
}
.fee-item.discount .fee-amount { color: var(--color-success); }
.fee-item.payable .fee-amount { font-size: 16px; color: var(--text-heading); }
.fee-item.paid .fee-amount { color: var(--color-success); }
.fee-item.due .fee-amount { color: var(--color-danger); }
.fee-item.fully-paid .fee-amount { color: var(--color-success); }
.discount-reason {
  font-size: 12px;
  color: var(--color-success);
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 2px 0 4px;
}
.fee-divider {
  height: 1px;
  background: var(--border-color);
  margin: 4px 0;
}
/* ===== FEE SUMMARY SECTION (StudentDetailsPage pattern) ===== */
.fee-summary-section {
  margin-bottom: 20px;
}
.fee-summary-section .section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.fee-summary-section .section-header h3 {
  font-size: 17px;
  font-weight: 700;
  color: var(--text-heading);
  margin: 0;
}
.fee-summary-section .loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: var(--text-muted);
  gap: 12px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
}
.fee-summary-section .loading-state .spinner {
  width: 36px;
  height: 36px;
  border: 3px solid var(--border-color);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.fee-summary-section .error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  gap: 12px;
  background: var(--bg-card);
  border: 1px solid #fecaca;
  border-radius: var(--radius-md);
}
.fee-summary-section .error-state .error-msg {
  color: #dc2626;
  font-size: 14px;
  margin: 0;
}
.fee-summary-section .no-fee-data {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  color: var(--text-muted);
  font-size: 14px;
}
.fee-type-badge-onetime {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
  white-space: nowrap;
}

/* ===== OVERALL SUMMARY CARDS (StudentDetailsPage pattern) ===== */
.overall-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.summary-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: var(--transition);
}
.summary-card:hover {
  box-shadow: var(--shadow-sm);
  transform: translateY(-2px);
}
.summary-icon {
  font-size: 28px;
  flex-shrink: 0;
}
.summary-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.summary-label {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 500;
}
.summary-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--text-heading);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.summary-value small {
  font-size: 11px;
  font-weight: 400;
  color: var(--text-muted);
  margin-left: 2px;
}
.summary-card.total-fee .summary-value { color: #2c3e50; }
.summary-card.total-paid .summary-value { color: #27ae60; }
.summary-card.total-due .summary-value { color: #e74c3c; }
.summary-card.payment-pct .summary-value { color: #2980b9; }

/* ===== PROGRESS BAR ===== */
.progress-bar-container {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  background: var(--bg-surface-muted);
  padding: 12px 16px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-light);
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
.progress-fill.progress-very-low { background: linear-gradient(90deg, #ef4444, #f97316); }
.progress-fill.progress-low { background: linear-gradient(90deg, #f97316, #f59e0b); }
.progress-fill.progress-mid { background: linear-gradient(90deg, #f59e0b, #10b981); }
.progress-fill.progress-high { background: linear-gradient(90deg, #10b981, #059669); }
.progress-text {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-muted);
  white-space: nowrap;
}

/* ===== ENROLLMENT FEE CARD (StudentDetailsPage pattern) ===== */
.enrollment-fee-card {
  margin-bottom: 20px;
}
.enrollment-fee-card .card-heading {
  display: none;
}
.enrollment-fee-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg-accent);
  flex-wrap: wrap;
  gap: 8px;
}
.enrollment-fee-header > div:first-child {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.enrollment-fee-header strong {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-heading);
}
.enrollment-fee-badge {
  font-size: 12px;
  color: var(--text-muted);
  font-family: 'Courier New', monospace;
  background: #f0f0f0;
  padding: 2px 8px;
  border-radius: 4px;
}
.enrollment-fee-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.enrollment-fee-body {
  padding: 16px 20px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 20px;
}
.fee-detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
}
.fee-detail-item .label {
  font-size: 13px;
  color: var(--text-muted);
}
.fee-detail-item .value {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-dark);
}
.fee-detail-item .value.paid { color: #27ae60; }
.fee-detail-item .value.due { color: #e74c3c; }
.fee-detail-item .value.discount { color: #27ae60; }

/* ===== MONTH STATUS BAR ===== */
.month-status-bar {
  display: flex;
  align-items: stretch;
  margin: 0 20px 16px;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--bg-accent);
}
.month-status-item {
  flex: 1;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.month-status-item.last-paid {
  background: #f0fdf4;
}
.month-status-item.next-pending {
  background: #fffbeb;
}
.month-status-divider {
  width: 1px;
  background: var(--border-light);
}
.month-status-label {
  font-size: 12px;
  font-weight: 600;
  color: #555;
}
.month-status-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-heading);
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.month-status-value.no-data {
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 400;
}
.month-status-badge {
  font-size: 10px;
  padding: 1px 6px;
}

/* ===== MONTHLY RECORDS ===== */
.monthly-records {
  margin: 0 20px 16px;
}
.monthly-records h4 {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-heading);
  margin: 0 0 10px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.monthly-records-table {
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
  overflow: hidden;
}
.monthly-record-header {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 80px;
  gap: 4px;
  padding: 8px 12px;
  background: var(--bg-page);
  border-bottom: 2px solid var(--border-color);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--text-muted);
}
.monthly-record-row {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 80px;
  gap: 4px;
  padding: 8px 12px;
  border-bottom: 1px solid #f0f0f0;
  font-size: 13px;
  align-items: center;
}
.monthly-record-row:last-child {
  border-bottom: none;
}
.monthly-record-row.row-paid { background: #f0faf4; }
.monthly-record-row.row-pending { background: #fffdf5; }
.monthly-record-row.row-partial { background: #fff8e1; }
.monthly-record-row .paid { color: #27ae60; font-weight: 600; }
.monthly-record-row .due { color: #e74c3c; font-weight: 600; }

/* ===== RECENT PAYMENTS ===== */
.recent-payments {
  margin: 0 20px 16px;
  padding-top: 12px;
  border-top: 1px solid var(--border-light);
}
.recent-payments h4 {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-heading);
  margin: 0 0 10px;
  display: flex;
  align-items: center;
  gap: 6px;
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
  padding: 8px 12px;
  background: var(--bg-accent);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
}
.payment-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.payment-receipt {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-heading);
  font-family: 'Courier New', monospace;
}
.payment-date {
  font-size: 12px;
  color: var(--text-muted);
}
.payment-method {
  font-size: 12px;
  color: var(--text-muted);
  background: #f0f0f0;
  padding: 1px 6px;
  border-radius: 4px;
}
.payment-amount-info {
  display: flex;
  align-items: center;
  gap: 8px;
}
.payment-amount {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-heading);
}

/* ===== BADGES ===== */
.badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}
.badge-success { background: #eafaf1; color: #27ae60; }
.badge-warning { background: #fff8e1; color: #f39c12; }
.badge-danger { background: #fdeaea; color: #e74c3c; }
.badge-info { background: #e8f4fd; color: #2980b9; }
.badge-secondary { background: #f0f0f0; color: var(--text-muted); }
.fee-type-badge-monthly {
  background: #e8f4fd;
  color: #2980b9;
  border: 1px solid #b3d9f2;
}

/* ===== PAYMENT SECTION ===== */
.payment-section {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--border-light);
}
.section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-heading);
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.payment-input-group {
  display: flex;
  gap: 10px;
}
.input-wrapper {
  flex: 1;
  position: relative;
}
.input-prefix {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14px;
  font-weight: 600;
  color: var(--text-muted);
}
.payment-input {
  width: 100%;
  padding: 10px 12px 10px 28px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  font-size: 14px;
  background: var(--bg-input);
  color: var(--text-dark);
  transition: var(--transition);
}
.payment-input:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.1);
}
.btn-pay {
  padding: 10px 20px;
  background: linear-gradient(135deg, var(--color-success), #059669);
  color: #fff;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  transition: var(--transition);
}
.btn-pay:hover:not(:disabled) {
  box-shadow: 0 4px 12px rgba(18, 183, 106, 0.3);
  transform: translateY(-1px);
}
.btn-pay:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ===== ACTIONS ===== */
.actions-card .card-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.action-btn {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  background: var(--bg-card);
  cursor: pointer;
  transition: var(--transition);
  width: 100%;
  text-align: left;
}
.action-btn:hover {
  background: var(--bg-card-hover);
  border-color: var(--primary-color);
  transform: translateX(4px);
}
.action-btn i:first-child {
  font-size: 20px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
}
.action-btn i:last-child {
  margin-left: auto;
  font-size: 14px;
  color: var(--text-muted);
}
.action-btn.change-batch i:first-child { background: #eff6ff; color: #2563eb; }
.action-btn.confirm i:first-child { background: #ecfdf5; color: #059669; }
.action-btn.dropout i:first-child { background: #fef2f2; color: #dc2626; }

/* ===== EMPTY STATE ===== */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: var(--text-muted);
  gap: 8px;
}
.empty-state i { font-size: 32px; }
.empty-state.full-page {
  min-height: 400px;
}
.empty-state h3 {
  font-size: 18px;
  color: var(--text-heading);
  margin: 0;
}
.btn-back-link {
  margin-top: 8px;
  padding: 8px 20px;
  background: var(--primary-color);
  color: #fff;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 500;
  transition: var(--transition);
}
.btn-back-link:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* ===== FEE TYPE BADGE ===== */
.fee-type-badge-row { margin-bottom: 0.75rem; }
.fee-type-badge { display:inline-block; padding:0.25rem 0.75rem; border-radius:20px; font-size:0.8rem; font-weight:600; }
.fee-type-badge.monthly { background:#e8f4fd; color:#2980b9; border:1px solid #b3d9f2; }
.fee-type-badge.one-time { background:#fef9e7; color:#d4a017; border:1px solid #f9e79f; }

/* ===== MONTHLY FEE RECORDS ===== */
.monthly-records-table { margin-bottom: 1rem; }
.mini-table { width:100%; border-collapse:collapse; font-size:0.8rem; }
.mini-table th { background: var(--bg-page); padding:0.4rem 0.5rem; text-align:left; font-size:0.7rem; text-transform:uppercase; color: var(--text-muted); border-bottom: 2px solid var(--border-color); }
.mini-table td { padding:0.4rem 0.5rem; border-bottom:1px solid #f0f0f0; }
.mini-table .row-paid td { background:#f0faf4; }
.mini-table .row-pending td { background:#fffdf5; }
.mini-table .row-partial td { background:#fff8e1; }
.mini-badge { padding:0.1rem 0.4rem; border-radius:8px; font-size:0.65rem; font-weight:600; text-transform:capitalize; }
.mini-badge.paid { background:#eafaf1; color:#27ae60; }
.mini-badge.pending { background:#fdeaea; color:#e74c3c; }
.mini-badge.partial { background:#fff8e1; color:#f39c12; }
.monthly-summary { background: var(--bg-accent); border-radius:8px; padding:0.75rem; }
.summary-row { display:flex; justify-content:space-between; padding:0.25rem 0; font-size:0.82rem; }
.text-success { color:#27ae60; }
.text-danger { color:#e74c3c; }

/* ===== DIALOG ===== */
.batch-dialog :deep(.p-dialog-header) {
  padding: 18px 24px;
  border-bottom: 1px solid var(--border-light);
}
.batch-dialog :deep(.p-dialog-title) {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-heading);
}
.batch-dialog :deep(.p-dialog-content) {
  padding: 20px 24px;
}
.batch-dialog :deep(.p-dialog-footer) {
  padding: 12px 24px;
  border-top: 1px solid var(--border-light);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.dialog-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.dialog-desc {
  font-size: 14px;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.5;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-label);
}
.current-batch-info {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: var(--bg-accent);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--text-dark);
}
.current-batch-info i { color: var(--primary-color); }
.form-select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  font-size: 14px;
  background: var(--bg-input);
  color: var(--text-dark);
  transition: var(--transition);
}
.form-select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.1);
}
.btn-cancel {
  padding: 8px 18px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-sm);
  background: var(--bg-card);
  color: var(--text-dark);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: var(--transition);
}
.btn-cancel:hover {
  background: var(--bg-card-hover);
}
/* ===== MONTHLY FEE PAYMENT MODAL (StudentDetailsPage pattern) ===== */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-content { background: var(--bg-card); border-radius: 14px; max-width: 560px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid #eee; position: sticky; top: 0; background: var(--bg-card); z-index: 1; }
.modal-header h3 { margin: 0; font-size: 1.05rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); padding: 0; line-height: 1; }
.modal-close:hover { color: var(--text-dark); }
.modal-body { padding: 1.5rem; }
.modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid #eee; position: sticky; bottom: 0; background: var(--bg-card); }

/* Payment Enrollment Info Grid */
.payment-enrollment-info {
  background: var(--bg-accent);
  border-radius: 10px;
  padding: 1rem;
  margin-bottom: 1.25rem;
  border: 1px solid var(--border-color);
}
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 16px;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.info-item .info-label {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #888;
}
.info-item .info-value {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-dark);
}
.info-item .info-value.due {
  color: #e74c3c;
}
.next-month-highlight {
  display: inline-block;
  background: #dbeafe;
  color: #1e40af;
  padding: 1px 8px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
}

/* Form Row 2-Column Layout */
.form-row-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
}
.form-group { margin-bottom: 0; }
.form-group.full-width { grid-column: 1 / -1; margin-bottom: 0.5rem; }
.form-group label {
  display: block;
  font-size: 0.78rem;
  font-weight: 600;
  margin-bottom: 0.3rem;
  color: #555;
}
.form-input {
  width: 100%;
  padding: 0.55rem 0.7rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.88rem;
  box-sizing: border-box;
  background: var(--bg-card);
  color: var(--text-dark);
  transition: border-color 0.2s;
}
.form-input:focus {
  border-color: #4a90d9;
  outline: none;
  box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.1);
}
.form-hint {
  display: block;
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 3px;
}

/* Method Chips */
.method-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.method-chip { padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 20px; background: var(--bg-card); cursor: pointer; font-size: 0.85rem; text-transform: capitalize; }
.method-chip:hover { border-color: #4a90d9; }
.method-chip.active { background: #4a90d9; color: #fff; border-color: #4a90d9; }

/* Payment Buttons */
.btn-pay-mini { padding: 0.2rem 0.5rem; font-size: 0.7rem; border-radius: 6px; cursor: pointer; border: 1px solid #4a90d9; background: var(--bg-card); color: #4a90d9; white-space: nowrap; }
.btn-pay-mini:hover { background: #4a90d9; color: #fff; }
.paid-check { font-size: 0.85rem; }
.btn-view-all { font-size: 0.75rem; color: #4a90d9; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-left: auto; }
.btn-view-all:hover { text-decoration: underline; }
.card-heading .btn-view-all { margin-left: auto; }
.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.8rem; border-radius: 6px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 0.2rem; }
.btn-outline { border: 1px solid #4a90d9; color: #4a90d9; background: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.2s; }
.btn-outline:hover { background: #f0f7ff; }

/* Payment Error & Success */
.payment-error {
  background: #fef2f2;
  color: #dc2626;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-top: 12px;
  border: 1px solid #fecaca;
}
.payment-success {
  background: #f0fdf4;
  color: #16a34a;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-top: 12px;
  border: 1px solid #bbf7d0;
}

/* ===== PAYMENT INDICATORS ===== */
.payment-indicators {
  display: flex;
  gap: 4px;
  align-items: center;
  justify-content: center;
}
.payment-indicators .pi { font-size: 0.85rem; }

/* ===== ACTION BUTTONS IN TABLE ===== */
.action-btns {
  display: flex;
  gap: 4px;
  align-items: center;
}
.btn-invoice-mini {
  padding: 0.2rem 0.4rem;
  font-size: 0.75rem;
  border-radius: 6px;
  cursor: pointer;
  border: 1px solid #27ae60;
  background: var(--bg-card);
  color: #27ae60;
  line-height: 1;
  transition: all 0.2s;
}
.btn-invoice-mini:hover {
  background: #27ae60;
  color: #fff;
}

/* ===== INFO NOTE ===== */
.info-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  background: #fff8e1;
  border: 1px solid #f9e79f;
  border-radius: 8px;
  font-size: 0.78rem;
  color: #856404;
  margin-top: 8px;
}
.info-note i {
  font-size: 1rem;
  margin-top: 1px;
  flex-shrink: 0;
}

/* ===== MONTH BADGES ===== */
.next-badge {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 700;
  background: #fef3c7;
  color: #92400e;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.current-badge {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 700;
  background: #dbeafe;
  color: #1e40af;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* ===== HIGHLIGHT NEXT PAY ROW ===== */
.row-next-pay {
  background: #fffbeb !important;
  border-left: 3px solid #f59e0b !important;
}
.row-next-pay:hover {
  background: #fef3c7 !important;
}

/* ===== CURRENT MONTH INFO ===== */
.current-month-info {
  background: #eff6ff;
  border-radius: var(--radius-sm);
  padding: 8px 12px !important;
  margin-bottom: 4px;
}
.current-month-info .fee-label {
  font-weight: 700;
  color: #1e40af;
}
.current-month-info .fee-amount {
  font-weight: 700;
  color: #1e40af;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  border: none;
}
.btn-primary {
  padding: 8px 18px;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--primary-color);
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
}
.btn-primary:hover:not(:disabled) {
  box-shadow: 0 4px 12px rgba(74, 144, 217, 0.3);
  transform: translateY(-1px);
}
.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-sm {
  padding: 0.3rem 0.6rem;
  font-size: 0.8rem;
  border-radius: 6px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .overall-summary {
    grid-template-columns: repeat(2, 1fr);
  }
  .enrollment-fee-body {
    grid-template-columns: 1fr;
  }
  .month-status-bar {
    flex-direction: column;
  }
  .month-status-divider {
    width: 100%;
    height: 1px;
  }
  .monthly-record-header,
  .monthly-record-row {
    grid-template-columns: 1.2fr 0.8fr 0.8fr 0.8fr 0.8fr 60px;
    font-size: 11px;
    padding: 6px 8px;
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
  .form-row-2col {
    grid-template-columns: 1fr;
  }
  .modal-content {
    max-width: 100%;
    width: 95%;
    max-height: 90vh;
  }
  .content-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 480px) {
  .overall-summary {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .summary-card {
    padding: 12px;
    gap: 8px;
  }
  .summary-icon {
    font-size: 22px;
  }
  .summary-value {
    font-size: 15px;
  }
  .enrollment-fee-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .enrollment-fee-actions {
    width: 100%;
    justify-content: flex-start;
  }
  .monthly-record-header,
  .monthly-record-row {
    grid-template-columns: 1fr 0.7fr 0.7fr 0.7fr 0.7fr 50px;
    font-size: 10px;
    padding: 4px 6px;
  }
  .modal-body {
    padding: 1rem;
  }
  .modal-header {
    padding: 0.75rem 1rem;
  }
  .modal-actions {
    padding: 0.75rem 1rem;
  }
}

</style>