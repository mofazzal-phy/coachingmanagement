<template>
  <div class="fee-payment-page">
    <!-- ====== PAGE HEADER ====== -->
    <div class="page-header">
      <button class="back-btn" @click="goBack">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="header-content">
        <h1 class="page-title">Pay Fees</h1>
        <p class="page-subtitle">View your fee summary and make secure payments</p>
      </div>
    </div>

    <!-- ====== ENROLLMENT SELECTOR ====== -->
    <div v-if="!hasEnrollmentId" class="enrollment-card">
      <div class="enrollment-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
        </svg>
      </div>
      <div class="enrollment-body">
        <label class="enrollment-label">Select Enrollment</label>
        <select v-model="selectedEnrollmentId" class="enrollment-select" @change="onEnrollmentChange">
          <option value="">Choose your enrollment...</option>
          <option v-for="enr in enrollments" :key="enr.id" :value="enr.id">
            {{ enr.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- ====== LOADING ====== -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading your fee records...</p>
    </div>

    <!-- ====== EMPTY STATE ====== -->
    <div v-else-if="!hasEnrollmentId && !selectedEnrollmentId && enrollments.length > 0" class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
      </div>
      <h3>Select an Enrollment</h3>
      <p>Please choose an enrollment above to view your fee details.</p>
    </div>

    <div v-else-if="!hasEnrollmentId && enrollments.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="12" cy="12" r="10"/>
          <path d="M12 16v-4M12 8h.01"/>
        </svg>
      </div>
      <h3>No Enrollments Found</h3>
      <p>You don't have any active enrollments with fee records yet.</p>
    </div>

    <!-- ====== MAIN CONTENT ====== -->
    <template v-else>

      <!-- Pending Payments Alert -->
      <div v-if="pendingCount > 0" class="alert alert-warning">
        <div class="alert-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </div>
        <div class="alert-body">
          <strong>{{ pendingCount }} payment{{ pendingCount > 1 ? 's' : '' }} awaiting approval</strong>
          <p>New payments are blocked until admin approves or rejects pending transactions.</p>
        </div>
      </div>

      <!-- Notification-driven fee banner (from Pay Now) -->
      <div v-if="notifyFeeInfo" class="notif-banner">
        <div class="notif-banner-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
        </div>
        <div class="notif-banner-body">
          <div class="notif-banner-title">📋 Fee from Notification</div>
          <div class="notif-banner-desc">
            <span class="notif-banner-fee-name">{{ notifyFeeInfo.title }}</span>
            <span class="notif-banner-amount">Amount: <strong>৳{{ formatNumber(totalSelectedAmount) }}</strong></span>
          </div>
          <p class="notif-banner-hint">Click the button below to select this fee and proceed with payment. You can also select additional fees to pay together.</p>
        </div>
        <button
          v-if="!hasPendingPayments"
          class="notif-banner-btn"
          @click="payNotifiedFee"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
          Pay ৳{{ formatNumber(totalSelectedAmount) }} Now
        </button>
        <div v-else class="notif-banner-locked">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Locked
        </div>
      </div>

      <!-- ====== LANDING VIEW: Category Cards ====== -->
      <template v-if="viewMode === 'landing'">
        <!-- Category Cards Grid -->
        <div class="category-cards-grid">
          <div
            v-for="cat in categoryCards"
            :key="cat.key"
            class="category-card"
            :class="'cc-' + cat.key"
            @click="selectCategory(cat.key)"
          >
            <div class="cc-icon-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="cc-icon">
                <path v-if="cat.key === 'monthly'" d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                <path v-else-if="cat.key === 'event_based'" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4"/>
                <path v-else d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
              </svg>
              <!-- New exam fee alert badge -->
              <div v-if="cat.hasNewExamFees" class="cc-alert-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="cc-alert-icon">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
              </div>
            </div>
            <div class="cc-body">
              <h3 class="cc-title">{{ cat.label }}</h3>
              <p class="cc-desc">{{ cat.description }}</p>
              <!-- New exam fee alert text -->
              <div v-if="cat.hasNewExamFees" class="cc-alert-text">
                <span class="cc-alert-dot"></span>
                {{ cat.newExamFeeAlert }}
              </div>
              <div class="cc-stats">
                <div class="cc-stat">
                  <span class="cc-stat-label">Payable</span>
                  <span class="cc-stat-value">৳{{ formatNumber(cat.payableAmount) }}</span>
                </div>
                <div class="cc-stat">
                  <span class="cc-stat-label">Due</span>
                  <span class="cc-stat-value cc-stat-due">৳{{ formatNumber(cat.dueAmount) }}</span>
                </div>
                <div class="cc-stat" v-if="cat.discountAmount > 0">
                  <span class="cc-stat-label">Discount</span>
                  <span class="cc-stat-value cc-stat-discount">-৳{{ formatNumber(cat.discountAmount) }}</span>
                </div>
                <div class="cc-stat">
                  <span class="cc-stat-label">Items</span>
                  <span class="cc-stat-value">{{ cat.count }}</span>
                </div>
              </div>
            </div>
            <div class="cc-arrow">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
              </svg>
            </div>
          </div>
        </div>

        <!-- ====== FLOATING PAY SELECTED BAR ====== -->
        <Transition name="float-up">
          <div v-if="totalSelectedCount > 0 && !hasPendingPayments" class="floating-pay-bar">
            <div class="float-left">
              <span class="float-count">{{ totalSelectedCount }} fee{{ totalSelectedCount !== 1 ? 's' : '' }} selected</span>
              <span class="float-divider">|</span>
              <span class="float-total">Total: <strong>৳{{ formatNumber(totalSelectedAmount) }}</strong></span>
            </div>
            <div class="float-right">
              <button class="float-clear" @click="clearAllSelections">Clear</button>
              <button class="float-pay-btn" @click="initiateBulkPayment">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-icon">
                  <polyline points="9 18 15 12 9 6"/>
                </svg>
                Pay Selected
              </button>
            </div>
          </div>
        </Transition>

        <!-- Success Toast -->
        <Transition name="toast">
          <div v-if="paymentSuccess" class="toast toast-success">
            <div class="toast-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
            </div>
            <div class="toast-body">
              <strong>Payment Successful!</strong>
              <p>{{ paymentSuccessMessage }}</p>
              <div v-if="paymentInvoice" class="toast-actions">
                <button class="btn-invoice" @click="downloadInvoice">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                  </svg>
                  Download Invoice
                </button>
                <span class="invoice-ref">{{ paymentInvoice.invoice_no }}</span>
              </div>
            </div>
            <button class="toast-close" @click="paymentSuccess = false">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>
        </Transition>
      </template>

      <!-- ====== CATEGORY DETAIL VIEW ====== -->
      <template v-if="viewMode === 'detail'">
        <!-- Detail Header with Back Button -->
        <div class="category-detail-header">
          <button class="back-btn" @click="backToLanding">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
          </button>
          <div class="detail-header-info">
            <h2 class="detail-header-title">{{ activeCategoryLabel }}</h2>
            <p class="detail-header-subtitle">{{ activeCategoryDesc }}</p>
          </div>
          <div class="detail-header-stats">
            <span class="detail-stat">
              Due: <strong>৳{{ formatNumber(activeCategoryDue) }}</strong>
            </span>
            <span class="detail-stat-sep">|</span>
            <span class="detail-stat">
              {{ activeCategoryRecords.length }} item{{ activeCategoryRecords.length !== 1 ? 's' : '' }}
            </span>
          </div>
        </div>

        <!-- ====== MONTHLY FEE SUMMARY SECTION (same as EnrollmentDetailsPage.vue) ====== -->
        <div v-if="activeCategory === 'monthly' && monthlyRecords.length > 0" class="monthly-summary-section">

          <!-- Overall Summary Cards -->
          <div class="overall-summary">
            <div class="summary-card total-fee">
              <div class="summary-icon">💰</div>
              <div class="summary-details">
                <span class="summary-label">Total Fee</span>
                <span class="summary-value">৳{{ formatNumber(monthlyTotalFee) }}</span>
              </div>
            </div>
            <div v-if="monthlyTotalDiscount > 0" class="summary-card total-discount">
              <div class="summary-icon">🏷️</div>
              <div class="summary-details">
                <span class="summary-label">Total Discount</span>
                <span class="summary-value discount">-৳{{ formatNumber(monthlyTotalDiscount) }}</span>
              </div>
            </div>
            <div class="summary-card total-paid">
              <div class="summary-icon">✅</div>
              <div class="summary-details">
                <span class="summary-label">Total Paid</span>
                <span class="summary-value">৳{{ formatNumber(monthlyTotalPaid) }}</span>
              </div>
            </div>
            <div class="summary-card total-due">
              <div class="summary-icon">⏳</div>
              <div class="summary-details">
                <span class="summary-label">Total Due</span>
                <span class="summary-value">৳{{ formatNumber(monthlyTotalDue) }}</span>
              </div>
            </div>
            <div class="summary-card payment-pct">
              <div class="summary-icon">📊</div>
              <div class="summary-details">
                <span class="summary-label">Payment Progress</span>
                <span class="summary-value">{{ monthlyPaymentPercent }}%</span>
              </div>
            </div>
          </div>

          <!-- Fee Detail Items -->
          <div class="detail-card fee-card enrollment-fee-card">
            <div class="enrollment-fee-body">
              <div class="fee-detail-item">
                <span class="label">Monthly Fee</span>
                <span class="value">
                  ৳{{ formatNumber(monthlyFeePerMonth) }}<small>/mo</small>
                </span>
              </div>
              <div class="fee-detail-item">
                <span class="label">Total Months</span>
                <span class="value">{{ monthlyRecords.length }}</span>
              </div>
              <div class="fee-detail-item">
                <span class="label">Payable</span>
                <span class="value">৳{{ formatNumber(monthlyTotalPayable) }}</span>
              </div>
              <div v-if="monthlyTotalDiscount > 0" class="fee-detail-item">
                <span class="label">
                  Discount
                  <template v-if="enrollmentDiscountPercent > 0"> ({{ enrollmentDiscountPercent }}%/mo)</template>
                </span>
                <span class="value discount">-৳{{ formatNumber(monthlyTotalDiscount) }}</span>
              </div>
              <div class="fee-detail-item">
                <span class="label">Paid</span>
                <span class="value paid">৳{{ formatNumber(monthlyTotalPaid) }}</span>
              </div>
              <div class="fee-detail-item">
                <span class="label">Due</span>
                <span class="value due">৳{{ formatNumber(monthlyTotalDue) }}</span>
              </div>
            </div>

            <!-- Month Status Bar -->
            <div class="month-status-bar">
              <div class="month-status-item last-paid">
                <span class="month-status-label">✅ Last Paid</span>
                <span class="month-status-value" v-if="monthlyLastPaidMonth">
                  {{ monthlyLastPaidMonthName }} — ৳{{ formatNumber(monthlyLastPaidAmount) }}
                </span>
                <span class="month-status-value no-data" v-else>No payments yet</span>
              </div>
              <div class="month-status-divider"></div>
              <div class="month-status-item next-pending">
                <span class="month-status-label">⏳ Next Due</span>
                <span class="month-status-value" v-if="monthlyNextDueMonth && monthlyNextDueAmount > 0">
                  {{ monthlyNextDueMonthName }} — ৳{{ formatNumber(monthlyNextDueAmount) }}
                  <span class="badge month-status-badge" :class="'badge-' + monthlyNextDueStatus">{{ monthlyNextDueStatus }}</span>
                </span>
                <span class="month-status-value no-data" v-else>All months paid ✅</span>
              </div>
            </div>

            <!-- Monthly Records Table (with Pay & Invoice actions) -->
            <div class="monthly-records">
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
                  <span style="width:80px">Action</span>
                </div>
                <div
                  v-for="rec in monthlyRecords"
                  :key="rec.id"
                  :class="['monthly-record-row', 'row-' + (rec.payment_status || rec.status), { 'row-next-pay': monthlyNextDueRecord?.id === rec.id }]"
                >
                  <span>
                    {{ formatMonthName(rec.month) }}
                    <span v-if="monthlyNextDueRecord?.id === rec.id" class="next-badge">Next</span>
                  </span>
                  <span>৳{{ formatNumber(rec.total_monthly_fee || rec.amount || 0) }}</span>
                  <span class="discount">-৳{{ formatNumber(Math.max(0, (rec.total_monthly_fee || 0) - (rec.due_amount || 0))) }}</span>
                  <span>৳{{ formatNumber(rec.due_amount || 0) }}</span>
                  <span class="paid">৳{{ formatNumber(rec.paid_amount || 0) }}</span>
                  <span class="due">৳{{ formatNumber(Math.max(0, (rec.due_amount || 0) - (rec.paid_amount || 0))) }}</span>
                  <span :class="['badge', getMonthlyStatusBadge(rec.payment_status || rec.status)]">{{ statusLabel(rec.payment_status || rec.status) }}</span>
                  <span>
                    <div class="action-btns">
                      <button
                        v-if="Math.max(0, (rec.due_amount || 0) - (rec.paid_amount || 0)) > 0 && rec.payment_status !== 'paid'"
                        class="btn-pay-mini"
                        :disabled="hasPendingPayments"
                        :class="{ 'locked': hasPendingPayments }"
                        @click.stop="initiatePayment(rec)"
                        :title="hasPendingPayments ? '🔒 Awaiting admin approval for previous payment' : 'Pay for ' + formatMonthName(rec.month)"
                      >{{ hasPendingPayments ? '🔒' : '💳' }} {{ hasPendingPayments ? 'Locked' : 'Pay' }}</button>
                      <button
                        v-if="rec.payment_status === 'paid' && rec.paid_amount > 0"
                        class="btn-invoice-mini"
                        @click.stop="downloadRecordInvoice(rec)"
                        title="Download Invoice"
                      >📄</button>
                    </div>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Fee Cards in this category (hidden for monthly since summary table has Pay buttons) -->
        <div v-if="activeCategory !== 'monthly'" class="fee-section">
          <div class="section-header">
            <div class="section-title-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="section-icon icon-records">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
              </svg>
              <h2 class="section-title">{{ activeCategoryLabel }} Fees</h2>
              <span class="section-badge">{{ activeCategoryRecords.length }} record{{ activeCategoryRecords.length !== 1 ? 's' : '' }}</span>
            </div>
            <label v-if="selectableCategoryRecords.length > 0" class="select-all-toggle" @click="toggleAllCategory">
              <input
                type="checkbox"
                :checked="allCategorySelected"
                :indeterminate="someCategorySelected && !allCategorySelected"
              />
              <span class="toggle-label">{{ allCategorySelected ? 'Deselect All' : 'Select All' }}</span>
            </label>
          </div>

          <div v-if="activeCategoryRecords.length === 0" class="section-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="empty-icon-sm">
              <circle cx="12" cy="12" r="10"/>
              <path d="M12 16v-4M12 8h.01"/>
            </svg>
            <p>No fee records found in this category.</p>
          </div>

          <div v-else class="fee-card-grid">
            <div
              v-for="rec in activeCategoryRecords"
              :key="rec.id"
              class="fee-card"
              :class="[
                rec.payment_status ? 'card-' + rec.payment_status : '',
                { 'card-selected': selectedRecordIds.has(rec.id) || selectedNotifiedIds.has(rec.id) }
              ]"
              @click="toggleCategoryRecord(rec)"
            >
              <!-- Card Top: Checkbox + Category + Status + Action -->
              <div class="card-top">
                <label class="checkbox-wrap" @click.stop>
                  <input
                    type="checkbox"
                    :checked="selectedRecordIds.has(rec.id) || selectedNotifiedIds.has(rec.id)"
                    :disabled="getRecordDue(rec) <= 0 || rec.status === 'paid' || rec.status === 'expired'"
                    @change="toggleCategoryRecord(rec)"
                  />
                  <span class="checkmark"></span>
                </label>
                <span class="cat-badge" :class="'cat-' + (rec.fee_category || 'monthly')">
                  {{ categoryLabel(rec.fee_category) }}
                </span>
                <span class="card-status" :class="'st-' + (rec.payment_status || rec.status)">
                  {{ statusLabel(rec.payment_status || rec.status) }}
                </span>
                <button
                  v-if="getRecordDue(rec) > 0"
                  class="card-pay-btn"
                  :disabled="hasPendingPayments"
                  :title="hasPendingPayments ? '🔒 Awaiting admin approval' : ''"
                  @click.stop="initiatePayment(rec)"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                  </svg>
                </button>
                <span v-else-if="getRecordDue(rec) <= 0 && (rec.payment_status === 'paid' || rec.status === 'paid')" class="card-paid-badge">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                </span>
              </div>
              <!-- Card Body -->
              <div class="card-body">
                <h3 class="card-title">{{ rec.fee_type_name || rec.title || 'Fee' }}</h3>
                <div v-if="rec.month" class="card-meta-row">
                  <span class="card-meta-label">Month</span>
                  <span class="card-meta-value">{{ rec.month }}</span>
                </div>
                <!-- Amount breakdown -->
                <div class="card-amounts">
                  <div class="card-amount-item">
                    <span class="ca-label">Fee</span>
                    <span class="ca-value">৳{{ formatNumber(rec.total_monthly_fee || rec.amount || 0) }}</span>
                  </div>
                  <div v-if="getRecordDiscount(rec) > 0" class="card-amount-item ca-discount">
                    <span class="ca-label">Discount</span>
                    <span class="ca-value">-৳{{ formatNumber(getRecordDiscount(rec)) }}</span>
                  </div>
                  <div v-if="rec.paid_amount > 0" class="card-amount-item ca-paid">
                    <span class="ca-label">Paid</span>
                    <span class="ca-value">৳{{ formatNumber(rec.paid_amount) }}</span>
                  </div>
                  <div class="card-amount-item ca-due">
                    <span class="ca-label">Due</span>
                    <span class="ca-value">৳{{ formatNumber(getRecordDue(rec)) }}</span>
                  </div>
                </div>
              </div>
              <!-- Card Footer: Due date + Invoice Download -->
              <div class="card-footer">
                <div class="card-footer-left">
                  <span v-if="rec.due_date" class="card-footer-text" :class="{ 'text-danger': isOverdue(rec.due_date) && getRecordDue(rec) > 0 }">
                    Due: {{ formatDate(rec.due_date) }}
                    <span v-if="isOverdue(rec.due_date) && getRecordDue(rec) > 0" class="overdue-dot"></span>
                  </span>
                </div>
                <div class="card-footer-right">
                  <button
                    v-if="rec.payment_status === 'paid' || rec.status === 'paid'"
                    class="invoice-btn"
                    @click.stop="downloadRecordInvoice(rec)"
                    title="Download Invoice"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                      <polyline points="7 10 12 15 17 10"/>
                      <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Invoice
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ====== OPTIONAL MONTHLY FEE TOGGLE (Exam Fee detail view only) ====== -->
        <div v-if="activeCategory === 'event_based' && availableMonthlyFee" class="monthly-toggle-card">
          <div class="mt-header">
            <div class="mt-header-left">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-icon">
                <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
              </svg>
              <div class="mt-header-text">
                <span class="mt-title">Also pay Course Fee for <strong>{{ availableMonthlyFee.month || 'next month' }}</strong>?</span>
                <span class="mt-desc">Pay your upcoming monthly fee together with this exam fee. A single invoice will be generated with both fees, and it will adjust with your regular monthly billing.</span>
              </div>
            </div>
            <label class="mt-toggle" @click.stop>
              <input
                type="checkbox"
                :checked="examMonthlyIncluded"
                @change="toggleExamMonthlyFee"
              />
              <span class="mt-toggle-slider"></span>
            </label>
          </div>

          <Transition name="expand">
            <div v-if="examMonthlyIncluded" class="mt-body">
              <!-- Selected Month Highlight -->
              <div class="mt-month-highlight">
                <div class="mt-month-highlight-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                </div>
                <div class="mt-month-highlight-info">
                  <span class="mt-month-highlight-label">This month will be marked as PAID</span>
                  <span class="mt-month-highlight-month">{{ availableMonthlyFee.month || 'Next month' }}</span>
                </div>
              </div>
              <div class="mt-fee-row">
                <div class="mt-fee-info">
                  <span class="mt-fee-badge mt-fee-badge-monthly">Monthly</span>
                  <span class="mt-fee-name">{{ availableMonthlyFee.fee_type_name || 'Monthly Fee' }}</span>
                  <span v-if="availableMonthlyFee.month" class="mt-fee-month">{{ availableMonthlyFee.month }}</span>
                </div>
                <div class="mt-fee-amounts">
                  <span class="mt-fee-label">Due:</span>
                  <span class="mt-fee-value">৳{{ formatNumber(getRecordDue(availableMonthlyFee)) }}</span>
                </div>
              </div>
              <div class="mt-summary">
                <div class="mt-summary-row">
                  <span>Exam Fee Total</span>
                  <span>৳{{ formatNumber(activeCategoryDue) }}</span>
                </div>
                <div class="mt-summary-row">
                  <span>Monthly Fee — {{ availableMonthlyFee.month || 'next month' }}</span>
                  <span>+ ৳{{ formatNumber(getRecordDue(availableMonthlyFee)) }}</span>
                </div>
                <div class="mt-summary-divider"></div>
                <div class="mt-summary-row mt-summary-total">
                  <span>Combined Total</span>
                  <span>৳{{ formatNumber(activeCategoryDue + getRecordDue(availableMonthlyFee)) }}</span>
                </div>
              </div>
              <p class="mt-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-note-icon">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="16" x2="12" y2="12"/>
                  <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                A single invoice will be generated showing the full breakdown: Exam Fee + Monthly Fee. After payment, <strong>{{ availableMonthlyFee.month || 'this month' }}</strong> will show as <strong>Paid</strong> everywhere — in the Monthly Fee card, Fee Ledger, and payment history.
              </p>
            </div>
          </Transition>
        </div>

        <!-- ====== CATEGORY PAYMENT HISTORY ====== -->
        <div class="history-card">
          <div class="table-header">
            <h2 class="table-title">{{ activeCategoryLabel }} Payment History</h2>
            <span class="table-count">{{ categoryPaidRecords.length }} paid / {{ categoryPendingRecords.length }} pending</span>
          </div>

          <div v-if="categoryPaidRecords.length === 0 && categoryPendingRecords.length === 0" class="table-empty">
            <p>No payment history available for this category.</p>
          </div>

          <div v-else class="history-timeline">
            <!-- Paid Items -->
            <div v-if="categoryPaidRecords.length > 0" class="history-group">
              <div class="history-group-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hgroup-icon paid-icon">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                  <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Paid
              </div>
              <div class="history-chips">
                <span
                  v-for="rec in categoryPaidRecords"
                  :key="rec.id"
                  class="history-chip chip-paid"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="chip-icon">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                  {{ rec.fee_type_name || rec.title || 'Fee' }}
                  <!-- Show the actual paid amount (after discount) instead of the full fee -->
                  <span class="chip-amount">৳{{ formatNumber(rec.paid_amount || rec.total_monthly_fee || rec.amount || 0) }}</span>
                </span>
              </div>
            </div>

            <!-- Unpaid / Pending Items -->
            <div v-if="categoryPendingRecords.length > 0" class="history-group">
              <div class="history-group-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hgroup-icon pending-icon">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12 6 12 12 16 14"/>
                </svg>
                Unpaid / Pending
              </div>
              <div class="history-chips">
                <span
                  v-for="rec in categoryPendingRecords"
                  :key="rec.id"
                  class="history-chip chip-pending"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="chip-icon">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                  </svg>
                  {{ rec.fee_type_name || rec.title || 'Fee' }}
                  <span class="chip-amount">৳{{ formatNumber(getRecordDue(rec)) }}</span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- ====== FLOATING PAY SELECTED BAR (Detail) ====== -->
        <Transition name="float-up">
          <div v-if="totalSelectedCount > 0 && !hasPendingPayments" class="floating-pay-bar">
            <div class="float-left">
              <span class="float-count">{{ totalSelectedCount }} fee{{ totalSelectedCount !== 1 ? 's' : '' }} selected</span>
              <span class="float-divider">|</span>
              <span class="float-total">Total: <strong>৳{{ formatNumber(totalSelectedAmount) }}</strong></span>
            </div>
            <div class="float-right">
              <button class="float-clear" @click="clearAllSelections">Clear</button>
              <button class="float-pay-btn" @click="initiateBulkPayment">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-icon">
                  <polyline points="9 18 15 12 9 6"/>
                </svg>
                Pay Selected
              </button>
            </div>
          </div>
        </Transition>
      </template>
    </template>

    <!-- ====== CHECKOUT MODAL ====== -->
    <Transition name="modal">
      <div v-if="showPaymentModal" class="modal-overlay" @click.self="showPaymentModal = false">
        <div class="modal-card modal-lg">
          <!-- Step 1: Confirm Amount -->
          <template v-if="checkoutStep === 'amount'">
            <div class="modal-header">
              <div class="modal-title-wrap">
                <h3>{{ isBulkPayment ? 'Confirm Bulk Payment' : 'Confirm Payment' }}</h3>
                <span v-if="!isBulkPayment" class="modal-month-badge">{{ payingRecord?.month }}</span>
                <span v-else class="modal-month-badge">{{ totalSelectedCount }} fees</span>
              </div>
              <button class="modal-close" @click="showPaymentModal = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
            <div class="modal-body">
              <!-- ====== COMBINED EXAM + MONTHLY FEE (from dashboard card) ====== -->
              <div v-if="includeMonthly && monthlyFeeToInclude && !monthlyFeeSkipped" class="combined-fee-banner">
                <div class="cfb-header">
                  <span class="cfb-icon">📋</span>
                  <span class="cfb-title">Combined Payment</span>
                </div>
                <div class="cfb-fees">
                  <div class="cfb-fee-item cfb-exam">
                    <div class="cfb-fee-left">
                      <span class="cfb-badge cfb-badge-exam">Exam</span>
                      <span class="cfb-fee-name">{{ notifyTitle.value || 'Exam Fee' }}</span>
                    </div>
                    <span class="cfb-fee-amount">৳{{ formatNumber(Number(notifyAmount.value || 0)) }}</span>
                  </div>
                  <div class="cfb-plus">+</div>
                  <div class="cfb-fee-item cfb-monthly">
                    <div class="cfb-fee-left">
                      <span class="cfb-badge cfb-badge-monthly">Monthly</span>
                      <span class="cfb-fee-name">{{ monthlyFeeToInclude.fee_type_name || 'Monthly Fee' }} - {{ monthlyFeeToInclude.month }}</span>
                    </div>
                    <span class="cfb-fee-amount">৳{{ formatNumber(getRecordDue(monthlyFeeToInclude)) }}</span>
                  </div>
                </div>
                <div class="cfb-total-row">
                  <span class="cfb-total-label">Total to Pay</span>
                  <span class="cfb-total-value">৳{{ formatNumber(totalSelectedAmount) }}</span>
                </div>
                <button class="cfb-skip-btn" @click="skipMonthlyFee">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="cfb-skip-icon">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    <line x1="10" y1="11" x2="14" y2="11"/>
                  </svg>
                  Skip Monthly Fee — Pay Only Exam Fee
                </button>
              </div>

              <!-- Selected Fees Breakdown -->
              <div v-if="isBulkPayment" class="bulk-breakdown">
                <h4 class="breakdown-title">Selected Fees</h4>
                <div class="breakdown-list">
                  <div v-for="item in selectedFeeBreakdown" :key="item.id" class="breakdown-item">
                    <span class="breakdown-label">
                      <span class="cat-badge-sm" :class="'cat-' + (item.category || 'monthly')">{{ categoryLabel(item.category) }}</span>
                      {{ item.name }}
                    </span>
                    <span class="breakdown-amount">৳{{ formatNumber(item.amount) }}</span>
                  </div>
                </div>
                <div class="breakdown-total">
                  <span>Total Amount</span>
                  <span class="breakdown-total-value">৳{{ formatNumber(totalSelectedAmount) }}</span>
                </div>
              </div>

              <!-- Single Fee Summary -->
              <div v-else class="checkout-summary">
                <div class="checkout-row">
                  <span class="checkout-label">Fee Amount</span>
                  <span class="checkout-value">৳{{ formatNumber(payingRecord?.total_monthly_fee) }}</span>
                </div>
                <div v-if="getRecordDiscount(payingRecord) > 0" class="checkout-row">
                  <span class="checkout-label">Discount</span>
                  <span class="checkout-value checkout-discount">-৳{{ formatNumber(getRecordDiscount(payingRecord)) }}</span>
                </div>
                <div v-if="payingRecord?.paid_amount > 0" class="checkout-row">
                  <span class="checkout-label">Already Paid</span>
                  <span class="checkout-value">৳{{ formatNumber(payingRecord.paid_amount) }}</span>
                </div>
                <div class="checkout-divider"></div>
                <div class="checkout-row checkout-total">
                  <span class="checkout-label">Amount to Pay</span>
                  <span class="checkout-value checkout-amount">
                    <input
                      v-model.number="payingAmount"
                      type="number"
                      class="amount-input"
                      min="1"
                      :max="getRecordDue(payingRecord)"
                    />
                    <span class="amount-max" @click="payingAmount = getRecordDue(payingRecord)">Max</span>
                  </span>
                </div>
              </div>
              <button class="btn-primary btn-full" @click="checkoutStep = 'method'">
                Continue to Payment
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="9 18 15 12 9 6"/>
                </svg>
              </button>
            </div>
          </template>

          <!-- Step 2: Choose Payment Method -->
          <template v-if="checkoutStep === 'method'">
            <div class="modal-header">
              <div class="modal-back-wrap">
                <button class="modal-back" @click="checkoutStep = 'amount'">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                  </svg>
                </button>
                <div class="modal-title-wrap">
                  <h3>Payment Method</h3>
                  <span v-if="!isBulkPayment" class="modal-month-badge">{{ payingRecord?.month }}</span>
                  <span v-else class="modal-month-badge">{{ totalSelectedCount }} fees</span>
                </div>
              </div>
              <button class="modal-close" @click="showPaymentModal = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
            <div class="modal-body">
              <!-- Amount Recap -->
              <div class="recap-bar">
                <span>Paying: <strong>৳{{ formatNumber(payingAmount) }}</strong></span>
                <span v-if="!isBulkPayment" class="recap-month">{{ payingRecord?.month }}</span>
                <span v-else class="recap-month">{{ totalSelectedCount }} fees</span>
              </div>

              <!-- Online Gateways -->
              <div class="method-section">
                <h4 class="method-heading">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                  Pay Online
                </h4>
                <p class="method-hint">Instant confirmation & auto-generated invoice</p>
                <div class="gateway-grid">
                  <button
                    v-for="gw in gateways"
                    :key="gw.code"
                    class="gateway-btn"
                    :class="'gw-' + gw.code"
                    @click="payWithGateway(gw.code)"
                    :disabled="isPaying"
                  >
                    <span class="gw-icon">{{ gw.icon || '💳' }}</span>
                    <span class="gw-info">
                      <span class="gw-name">{{ gw.name }}</span>
                      <span class="gw-desc">Instant</span>
                    </span>
                    <svg class="gw-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="9 18 15 12 9 6"/>
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Cash -->
              <div class="method-section">
                <h4 class="method-heading">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                  </svg>
                  Pay with Cash
                </h4>
                <p class="method-hint">Requires admin approval — takes 1-2 business days</p>
                <button
                  class="cash-btn"
                  @click="payManual('cash')"
                  :disabled="isPaying"
                >
                  <span class="cash-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                  </span>
                  <span class="cash-info">
                    <span class="cash-name">Pay with Cash</span>
                    <span class="cash-desc">Pending approval</span>
                  </span>
                  <svg class="gw-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                  </svg>
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import studentPortalService from '@/services/student-portal.service'
import smartFeeService from '@/services/smart-fee.service'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const records = ref([])
const summary = ref(null)
const payAmounts = ref({})
const paying = ref({})
const showPaymentModal = ref(false)
const payingRecord = ref(null)
const payingAmount = ref(0)
const gateways = ref([])
const paymentSuccess = ref(false)
const paymentSuccessMessage = ref('')
const paymentInvoice = ref(null)
const enrollments = ref([])
const selectedEnrollmentId = ref(null)
const enrollmentData = ref(null)
const checkoutStep = ref('amount')

// Multi-fee selection state
const selectedRecordIds = ref(new Set())
const selectedNotifiedIds = ref(new Set())
const notifiedFees = ref([])
const isBulkPayment = ref(false)
const isPaying = ref(false)

// Track pending payments for this enrollment
const pendingPayments = reactive({})

// Notification-driven fee pre-selection (from Pay Now button)
const notifyFeeId = ref(route.query.notify_fee_id || null)
const notifyAmount = ref(route.query.notify_amount || null)
const notifyTitle = ref(route.query.notify_title || null)

// Combined exam + monthly fee flow (from dashboard exam fee cards)
const includeMonthly = ref(route.query.include_monthly === 'true')
const showMonthlyFeeOption = ref(true) // Whether to show the monthly fee skip option in the modal
const monthlyFeeSkipped = ref(false) // Whether user chose to skip monthly fee

const hasEnrollmentId = computed(() => !!route.params.enrollmentId)

// Computed info for the notification banner
const notifyFeeInfo = computed(() => {
  if (!notifyFeeId.value) return null
  return {
    id: notifyFeeId.value,
    title: notifyTitle.value || 'Exam Fee',
    amount: Number(notifyAmount.value || 0),
  }
})

const enrollmentDiscountPercent = computed(() => enrollmentData.value?.discount_percent || 0)
const enrollmentDiscountReason = computed(() => enrollmentData.value?.discount_reason || 'Discount')

const totalDiscount = computed(() => {
  return records.value.reduce((sum, r) => sum + Math.max(0, (r.total_monthly_fee || 0) - (r.due_amount || 0)), 0)
})

const getRecordDiscount = (rec) => Math.max(0, (rec?.total_monthly_fee || 0) - (rec?.due_amount || 0))
const getRecordDue = (rec) => {
  // For notified fees, use amount as the due amount
  const due = rec?.due_amount ?? rec?.amount ?? 0
  return Math.max(0, Number(due) - Number(rec?.paid_amount || 0))
}

const statusLabel = (status) => {
  const map = { paid: 'Paid', pending: 'Unpaid', partial: 'Partial', overdue: 'Overdue', unread: 'Pending', read: 'Pending', expired: 'Expired' }
  return map[status] || status || 'Pending'
}

// Payment history computed properties (global - kept for reference)
const paidRecords = computed(() => {
  return records.value.filter(r => r.payment_status === 'paid')
})

const pendingRecords = computed(() => {
  return records.value.filter(r => r.payment_status !== 'paid')
})

// Category-specific payment history (used in detail view)
const categoryPaidRecords = computed(() => {
  return activeCategoryRecords.value.filter(r => r.payment_status === 'paid' || r.status === 'paid')
})

const categoryPendingRecords = computed(() => {
  return activeCategoryRecords.value.filter(r => r.payment_status !== 'paid' && r.status !== 'paid')
})

const formatNumber = (num) => Number(num || 0).toLocaleString('en-BD')

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-BD', { day: 'numeric', month: 'short', year: 'numeric' })
}

const isOverdue = (dateStr) => {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

const categoryLabel = (cat) => {
  const labels = { one_time: 'One-Time', monthly: 'Monthly', event_based: 'Event' }
  return labels[cat] || cat
}

// ====== View Mode State (persisted in URL query params to survive page refresh) ======
const viewMode = ref(route.query.view || 'landing') // 'landing' | 'detail'
const activeCategory = ref(route.query.category || null) // 'monthly' | 'event_based' | 'one_time'

// Category configuration
const categoryConfig = {
  monthly: {
    label: 'Monthly Course Fee',
    description: 'Tuition & monthly course fees',
    icon: 'monthly',
  },
  event_based: {
    label: 'Exam Fee',
    description: 'Exam, assessment & event fees',
    icon: 'event_based',
  },
  one_time: {
    label: 'Other Fees',
    description: 'Admission, library & one-time fees',
    icon: 'one_time',
  },
}

// Dedup key — exam fees are unique per notification/exam, not per fee_structure_id
const getRecordDedupKey = (rec) => {
  const rawId = String(rec.id || '')
  const notifId = rec.notification_id || (rawId.startsWith('notif_') ? rawId.slice(6) : null)
  const examId = rec.meta?.exam_id || rec.exam_id

  if (notifId) {
    return `notif:${rec.enrollment_id || ''}:${notifId}`
  }
  if ((rec.fee_category === 'event_based' || rec.type === 'exam_fee') && examId) {
    return `exam:${rec.enrollment_id || ''}:${examId}`
  }
  if (rec.fee_assignment_id || (rec.is_smart_fee && rec.fee_category === 'event_based')) {
    return `assign:${rec.enrollment_id || ''}:${rec.fee_assignment_id || rec.id}`
  }
  if (rec.fee_structure_id) {
    return `fs:${rec.enrollment_id || ''}:${rec.fee_structure_id}`
  }
  return `id:${rec.fee_assignment_id || rec.assignment_id || rec.id}`
}

const isExamFeeNotification = (rec) => {
  return !!(
    rec?.is_notification ||
    rec?.notification_id ||
    String(rec?.id || '').startsWith('notif_') ||
    rec?.type === 'exam_fee' ||
    rec?._source === 'notified'
  )
}

// Resolve a selected id to its fee row (records, notifiedFees, or merged allRecords)
const resolveSelectedItem = (id) => {
  const sid = String(id)
  const fromAll = allRecords.value.find(r => String(r.id) === sid)
  if (fromAll) return fromAll
  const fromRecords = records.value.find(r => String(r.id) === sid)
  if (fromRecords) return fromRecords
  const fromNotified = notifiedFees.value.find(f => String(f.id) === sid)
  if (fromNotified) return fromNotified
  if (sid.startsWith('notif_')) {
    const nid = sid.slice(6)
    return (
      records.value.find(r => r.notification_id === nid) ||
      allRecords.value.find(r => r.notification_id === nid) ||
      notifiedFees.value.find(f => String(f.id) === nid)
    )
  }
  return null
}

// ====== All Records (merged from records + notifiedFees) ======
const allRecords = computed(() => {
  const merged = []
  const seenKeys = new Set()

  // API feeRecords already includes notifications — records take precedence
  for (const rec of records.value) {
    const key = getRecordDedupKey(rec)
    if (!seenKeys.has(key)) {
      seenKeys.add(key)
      merged.push({
        ...rec,
        _source: 'record',
        amount: rec.total_monthly_fee || rec.amount || 0,
        status: rec.status || rec.payment_status || rec.status,
        title: rec.fee_type_name || rec.title || rec.month || 'Fee',
      })
    }
  }

  for (const fee of notifiedFees.value) {
    const key = getRecordDedupKey(fee)
    if (seenKeys.has(key)) continue
    seenKeys.add(key)
    merged.push({
      ...fee,
      _source: 'notified',
      total_monthly_fee: fee.amount || 0,
      due_amount: fee.amount || 0,
      paid_amount: fee.paid_amount || 0,
      payment_status: fee.status === 'paid' ? 'paid' : (fee.status === 'expired' ? 'paid' : 'pending'),
      fee_category: fee.fee_category || 'event_based',
      fee_type_name: fee.title || fee.fee_type_name || 'Notified Fee',
      month: fee.month || '',
    })
  }

  return merged
})

// ====== Category Filtered Computed ======
const monthlyRecords = computed(() => {
  return allRecords.value.filter(r => r.fee_category === 'monthly')
})

// ====== Monthly Summary Computed Properties (matching EnrollmentDetailsPage.vue) ======

const monthlyTotalFee = computed(() => {
  return monthlyRecords.value.reduce((sum, r) => sum + Number(r.total_monthly_fee || r.amount || 0), 0)
})

const monthlyTotalDiscount = computed(() => {
  return monthlyRecords.value.reduce((sum, r) => sum + Math.max(0, (r.total_monthly_fee || 0) - (r.due_amount || 0)), 0)
})

const monthlyTotalPaid = computed(() => {
  return monthlyRecords.value.reduce((sum, r) => sum + Number(r.paid_amount || 0), 0)
})

const monthlyTotalDue = computed(() => {
  return monthlyRecords.value.reduce((sum, r) => sum + getRecordDue(r), 0)
})

const monthlyPaymentPercent = computed(() => {
  const totalPayable = monthlyTotalPayable.value
  if (totalPayable <= 0) return 0
  return Math.round((monthlyTotalPaid.value / totalPayable) * 100)
})

const monthlyFeePerMonth = computed(() => {
  if (monthlyRecords.value.length === 0) return 0
  // Use the first record's total_monthly_fee as the per-month fee
  return monthlyRecords.value[0]?.total_monthly_fee || monthlyRecords.value[0]?.amount || 0
})

const monthlyTotalPayable = computed(() => {
  return monthlyRecords.value.reduce((sum, r) => sum + Number(r.due_amount || 0), 0)
})

// Last paid month info — from API summary (matches EnrollmentDetailsPage.vue)
const monthlyLastPaidMonth = computed(() => summary.value?.last_paid_month || null)
const monthlyLastPaidMonthName = computed(() => summary.value?.last_paid_month_name || '')
const monthlyLastPaidAmount = computed(() => summary.value?.last_paid_amount || 0)

// Next due month info — from API summary
const monthlyNextDueRecord = computed(() => summary.value?.next_unpaid_month || null)
const monthlyNextDueMonth = computed(() => monthlyNextDueRecord.value?.dedup_month || summary.value?.next_month_name || null)
const monthlyNextDueMonthName = computed(() => summary.value?.next_month_name || '')
const monthlyNextDueAmount = computed(() => summary.value?.next_month_due || 0)
const monthlyNextDueStatus = computed(() => summary.value?.next_month_status || 'pending')

// Format month name helper (e.g., "2026-09" → "September 2026" or "Course Fee - September 2026" → "September 2026")
const formatMonthName = (monthStr) => {
  if (!monthStr) return ''
  // Try to extract YYYY-MM format
  const match = monthStr.match(/(\d{4})-(\d{2})/)
  if (match) {
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December']
    const monthIndex = parseInt(match[2]) - 1
    return `${months[monthIndex] || match[2]} ${match[1]}`
  }
  // If it's already a named format like "Course Fee - September 2026", extract the month name
  const namedMonths = ['January','February','March','April','May','June','July','August','September','October','November','December']
  for (const m of namedMonths) {
    if (monthStr.includes(m)) {
      const yearMatch = monthStr.match(/\b(20\d{2})\b/)
      const year = yearMatch ? yearMatch[1] : ''
      return year ? `${m} ${year}` : m
    }
  }
  return monthStr
}

// Get badge class for monthly status
const getMonthlyStatusBadge = (status) => {
  const map = {
    paid: 'badge-success',
    pending: 'badge-warning',
    partial: 'badge-info',
    overdue: 'badge-danger',
    expired: 'badge-secondary',
  }
  return map[status] || 'badge-secondary'
}

const examRecords = computed(() => {
  return allRecords.value.filter(r => r.fee_category === 'event_based')
})

const otherRecords = computed(() => {
  return allRecords.value.filter(r => r.fee_category === 'one_time')
})

// ====== Category Cards Data ======
const categoryCards = computed(() => {
  const cats = ['monthly', 'event_based', 'one_time']
  return cats.map(key => {
    const items = key === 'monthly' ? monthlyRecords.value
      : key === 'event_based' ? examRecords.value
      : otherRecords.value
    const totalAmount = items.reduce((sum, r) => sum + Number(r.total_monthly_fee || r.amount || 0), 0)
    const discountAmount = items.reduce((sum, r) => sum + Math.max(0, (r.total_monthly_fee || 0) - (r.due_amount || 0)), 0)
    const payableAmount = items.reduce((sum, r) => sum + Number(r.due_amount || 0), 0)
    const dueAmount = items.reduce((sum, r) => sum + getRecordDue(r), 0)

    // For event_based (exam fees), detect new/unread notifications to show an alert
    let newExamFees = []
    if (key === 'event_based') {
      newExamFees = items.filter(r =>
        isExamFeeNotification(r) &&
        (r.status === 'unread' || r.status === 'read' || r.payment_status === 'pending') &&
        getRecordDue(r) > 0
      )
    }

    return {
      key,
      ...categoryConfig[key],
      totalAmount,
      discountAmount,
      payableAmount,
      dueAmount,
      count: items.length,
      items,
      newExamFees,
      hasNewExamFees: newExamFees.length > 0,
      newExamFeeAlert: newExamFees.length > 0
        ? newExamFees.map(f => f.title || f.fee_type_name || 'Exam Fee').join(', ') + ' available'
        : '',
    }
  })
})

// ====== Active Category Computed ======
const activeCategoryRecords = computed(() => {
  if (!activeCategory.value) return []
  let items = activeCategory.value === 'monthly' ? monthlyRecords.value
    : activeCategory.value === 'event_based' ? examRecords.value
    : activeCategory.value === 'one_time' ? otherRecords.value
    : []
  const examFilter = route.query.exam_id ? String(route.query.exam_id) : null
  if (examFilter && activeCategory.value === 'event_based') {
    items = items.filter(r => String(r.exam_id || r.meta?.exam_id || '') === examFilter)
  }
  return items
})

const activeCategoryLabel = computed(() => {
  return activeCategory.value ? categoryConfig[activeCategory.value]?.label || activeCategory.value : ''
})

const activeCategoryDesc = computed(() => {
  return activeCategory.value ? categoryConfig[activeCategory.value]?.description || '' : ''
})

const activeCategoryDue = computed(() => {
  return activeCategoryRecords.value.reduce((sum, r) => sum + getRecordDue(r), 0)
})

// ====== Category Detail Selection ======
const selectableCategoryRecords = computed(() => {
  return activeCategoryRecords.value.filter(r => {
    if (getRecordDue(r) <= 0) return false
    if (r.payment_status === 'paid' || r.status === 'paid' || r.status === 'expired') return false
    return true
  })
})

const allCategorySelected = computed(() => {
  return selectableCategoryRecords.value.length > 0 && selectableCategoryRecords.value.every(r => {
    return selectedRecordIds.value.has(r.id) || selectedNotifiedIds.value.has(r.id)
  })
})

const someCategorySelected = computed(() => {
  return selectableCategoryRecords.value.some(r => {
    return selectedRecordIds.value.has(r.id) || selectedNotifiedIds.value.has(r.id)
  })
})

// ====== View Navigation (persist in URL query params) ======
const selectCategory = (key) => {
  activeCategory.value = key
  viewMode.value = 'detail'
  // Update URL query params so the detail view survives page refresh
  router.replace({ query: { ...route.query, view: 'detail', category: key } })
}

const backToLanding = () => {
  viewMode.value = 'landing'
  activeCategory.value = null
  // Remove view/category query params from URL
  const { view, category, ...rest } = route.query
  router.replace({ query: rest })
}

// ====== Category Record Toggle ======
const toggleCategoryRecord = (rec) => {
  if (rec._source === 'notified') {
    toggleNotifiedFee(rec)
  } else {
    toggleRecord(rec)
  }
}

const toggleAllCategory = () => {
  if (allCategorySelected.value) {
    // Deselect all in this category
    for (const r of selectableCategoryRecords.value) {
      if (selectedRecordIds.value.has(r.id)) {
        const newSet = new Set(selectedRecordIds.value)
        newSet.delete(r.id)
        selectedRecordIds.value = newSet
      }
      if (selectedNotifiedIds.value.has(r.id)) {
        const newSet = new Set(selectedNotifiedIds.value)
        newSet.delete(r.id)
        selectedNotifiedIds.value = newSet
      }
    }
  } else {
    // Select all in this category
    for (const r of selectableCategoryRecords.value) {
      if (r._source === 'notified') {
        if (!selectedNotifiedIds.value.has(r.id)) {
          const newSet = new Set(selectedNotifiedIds.value)
          newSet.add(r.id)
          selectedNotifiedIds.value = newSet
        }
      } else {
        if (!selectedRecordIds.value.has(r.id)) {
          const newSet = new Set(selectedRecordIds.value)
          newSet.add(r.id)
          selectedRecordIds.value = newSet
        }
      }
    }
  }
}

// ====== Download Invoice for a Paid Record ======
const downloadRecordInvoice = async (rec) => {
  try {
    const enrollmentId = route.params.enrollmentId || selectedEnrollmentId.value
    const assignmentId = rec.fee_assignment_id || null

    // Helper to download an invoice by its ID
    const downloadById = async (invoiceId) => {
      const dlRes = await smartFeeService.invoices.download(invoiceId)
      const url = window.URL.createObjectURL(new Blob([dlRes.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `invoice-${invoiceId}.pdf`)
      document.body.appendChild(link); link.click(); document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
      return true
    }

    // Fetch all invoices for this enrollment (with allocations eager-loaded)
    const invRes = await smartFeeService.invoices.byEnrollment(enrollmentId)
    const invoices = invRes.data?.data || []

    if (!invoices.length) {
      console.warn('No invoices found for this enrollment')
      alert('No invoice found for this payment. Please contact the admin.')
      return
    }

    // Resolve target month from record
    const targetMonth = rec.dedup_month || (rec.month ? rec.month.match(/(\d{4}-\d{2})/)?.[1] : null)

    // Build readable month name from targetMonth (e.g., "January 2026")
    let readableMonth = ''
    if (targetMonth) {
      const [year, monthNum] = targetMonth.split('-')
      const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December']
      const monthName = monthNames[parseInt(monthNum, 10) - 1]
      readableMonth = `${monthName} ${year}`
    }

    // Helper: check if an invoice matches the target month via its metadata
    const matchesTargetMonth = (inv) => {
      const meta = inv.metadata || {}

      // Check legacy_months array
      if (meta.legacy_months && Array.isArray(meta.legacy_months)) {
        if (meta.legacy_months.includes(targetMonth)) return true
        // Also check if any legacy month matches the readable format
        if (readableMonth && meta.legacy_months.some(m => {
          const [y, mn] = (m || '').split('-')
          const names = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December']
          return names[parseInt(mn, 10) - 1] + ' ' + y === readableMonth
        })) return true
      }

      // Check period_description
      if (meta.period_description && readableMonth) {
        if (meta.period_description.includes(readableMonth)) return true
        // Also check for YYYY-MM format in period_description
        if (targetMonth && meta.period_description.includes(targetMonth)) return true
      }

      return false
    }

    // Helper: check if an invoice's transaction has allocations matching this record
    const matchesAllocations = (inv) => {
      const allocs = inv.payment_transaction?.allocations || []

      // Check by fee_assignment_id
      if (assignmentId && allocs.some(a => String(a.fee_assignment_id) === String(assignmentId))) {
        return true
      }

      // Check by period_month on feeAssignment
      if (targetMonth && allocs.some(a => a.fee_assignment?.period_month === targetMonth)) {
        return true
      }

      return false
    }

    // ===== STRATEGY 1: Match by fee_assignment_id via allocations =====
    if (assignmentId) {
      const matchedInvoice = invoices.find(inv => {
        const allocs = inv.payment_transaction?.allocations || []
        return allocs.some(a => String(a.fee_assignment_id) === String(assignmentId))
      })
      if (matchedInvoice) {
        paymentInvoice.value = matchedInvoice
        await downloadById(matchedInvoice.id)
        return
      }
    }

    // ===== STRATEGY 2: Match by period_month via allocations.feeAssignment =====
    if (targetMonth) {
      const matchedInvoice = invoices.find(inv => {
        const allocs = inv.payment_transaction?.allocations || []
        return allocs.some(a => a.fee_assignment?.period_month === targetMonth)
      })
      if (matchedInvoice) {
        paymentInvoice.value = matchedInvoice
        await downloadById(matchedInvoice.id)
        return
      }
    }

    // ===== STRATEGY 3: Match by metadata.legacy_months =====
    if (targetMonth) {
      const matchedInvoice = invoices.find(inv => matchesTargetMonth(inv))
      if (matchedInvoice) {
        paymentInvoice.value = matchedInvoice
        await downloadById(matchedInvoice.id)
        return
      }
    }

    // ===== STRATEGY 4: Match by period_description containing the readable month =====
    if (readableMonth) {
      const matchedInvoice = invoices.find(inv => {
        const desc = inv.metadata?.period_description
        return desc && (desc.includes(readableMonth) || (targetMonth && desc.includes(targetMonth)))
      })
      if (matchedInvoice) {
        paymentInvoice.value = matchedInvoice
        await downloadById(matchedInvoice.id)
        return
      }
    }

    // ===== STRATEGY 5: Match by transaction creation date proximity =====
    // For legacy records, the invoice's transaction date should be close to when the fee was due
    if (rec.due_date) {
      const dueDate = new Date(rec.due_date)
      const dueMonth = dueDate.getMonth()
      const dueYear = dueDate.getFullYear()

      const matchedInvoice = invoices.find(inv => {
        const trx = inv.payment_transaction
        if (!trx?.created_at) return false
        const trxDate = new Date(trx.created_at)
        // Check if transaction was created in the same month or the month after the due date
        const trxMonth = trxDate.getMonth()
        const trxYear = trxDate.getFullYear()
        // Same month/year, or next month (payment may be late)
        return (trxYear === dueYear && trxMonth === dueMonth) ||
               (trxYear === dueYear && trxMonth === dueMonth + 1) ||
               (trxYear === dueYear + 1 && dueMonth === 11 && trxMonth === 0)
      })
      if (matchedInvoice) {
        paymentInvoice.value = matchedInvoice
        await downloadById(matchedInvoice.id)
        return
      }
    }

    // ===== STRATEGY 6: If only one invoice exists, use it =====
    if (invoices.length === 1) {
      paymentInvoice.value = invoices[0]
      await downloadById(invoices[0].id)
      return
    }

    // ===== STRATEGY 7: Last resort - try each invoice until one downloads =====
    for (const inv of invoices) {
      try {
        paymentInvoice.value = inv
        await downloadById(inv.id)
        return // Success, exit
      } catch (dlErr) {
        console.warn('Failed to download invoice', inv.id, dlErr)
        // Continue to next invoice
      }
    }

    console.warn('All strategies exhausted. No matching invoice found.')
    alert('No matching invoice found for this payment. Please contact the admin.')
  } catch (err) {
    console.error('Failed to download invoice:', err)
    const msg = err.response?.data?.message || err.message || 'Unknown error'
    alert('Failed to download invoice: ' + msg)
  }
}

// ====== Combined Fee Computed ======

// Find the first pending monthly fee record to auto-include when paying exam fee
const monthlyFeeToInclude = computed(() => {
  if (!includeMonthly.value || monthlyFeeSkipped.value) return null
  // Find the first unpaid monthly fee record
  return records.value.find(r => r.fee_category === 'monthly' && getRecordDue(r) > 0) || null
})

// ====== Optional Monthly Fee Toggle in Exam Fee Detail View ======
// Tracks whether the student opted to include a monthly fee when paying exam fees
const examMonthlyIncluded = ref(false)

// Get the proper assignment ID for a record (fee_assignment_id for smart fees, id as fallback)
const getAssignmentId = (rec) => {
  return rec?.fee_assignment_id || rec?.id || null
}

// Find the next upcoming monthly fee to offer as optional add-on in exam fee detail view
// This pays the month right after the last paid month, so it adjusts with regular monthly billing
const availableMonthlyFee = computed(() => {
  if (activeCategory.value !== 'event_based') return null

  // Get all monthly records sorted by due_date (chronological from API)
  const allMonthly = monthlyRecords.value

  // Find the last paid month index
  let lastPaidIndex = -1
  for (let i = 0; i < allMonthly.length; i++) {
    const r = allMonthly[i]
    if (r.payment_status === 'paid' || r.status === 'paid') {
      lastPaidIndex = i
    }
  }

  // The next month after the last paid one is the one to offer
  const nextIndex = lastPaidIndex + 1
  if (nextIndex < allMonthly.length) {
    const candidate = allMonthly[nextIndex]
    // Only offer if it's not already paid and has a due amount
    if (candidate.payment_status !== 'paid' && candidate.status !== 'paid' && getRecordDue(candidate) > 0) {
      return candidate
    }
  }

  // If no paid records yet (all unpaid), find the first unpaid that is NOT overdue
  if (lastPaidIndex === -1) {
    const now = new Date()
    // Look for a record whose month is current or future (not overdue)
    for (const r of allMonthly) {
      if (getRecordDue(r) > 0 && r.status !== 'paid' && r.status !== 'expired') {
        const m = r.month || ''
        // Try to extract month name and year from the month string
        const months = ['January','February','March','April','May','June','July','August','September','October','November','December']
        for (const monthName of months) {
          if (m.includes(monthName)) {
            // Check if this month is current or future
            const monthIndex = months.indexOf(monthName)
            const yearMatch = m.match(/\b(20\d{2})\b/)
            const year = yearMatch ? parseInt(yearMatch[1]) : now.getFullYear()
            if (year > now.getFullYear() || (year === now.getFullYear() && monthIndex >= now.getMonth())) {
              return r
            }
          }
        }
      }
    }
    // Fallback: return the first unpaid (earliest due)
    const unpaid = allMonthly.filter(r => getRecordDue(r) > 0 && r.status !== 'paid' && r.status !== 'expired')
    if (unpaid.length > 0) return unpaid[0]
  }

  return null
})

// Toggle the monthly fee inclusion for exam fee payment
// Uses fee_assignment_id (StudentFeeAssignment::id) instead of record id to ensure
// the backend can find the correct StudentFeeAssignment record for payment allocation
const toggleExamMonthlyFee = () => {
  examMonthlyIncluded.value = !examMonthlyIncluded.value
  if (examMonthlyIncluded.value) {
    // Add the monthly fee to selected records using its assignment ID
    if (availableMonthlyFee.value) {
      const newSet = new Set(selectedRecordIds.value)
      const monthlyAssignmentId = getAssignmentId(availableMonthlyFee.value)
      if (monthlyAssignmentId) {
        newSet.add(monthlyAssignmentId)
        selectedRecordIds.value = newSet
      }
    }
  } else {
    // Remove the monthly fee from selected records
    if (availableMonthlyFee.value) {
      const newSet = new Set(selectedRecordIds.value)
      const monthlyAssignmentId = getAssignmentId(availableMonthlyFee.value)
      if (monthlyAssignmentId) {
        newSet.delete(monthlyAssignmentId)
        selectedRecordIds.value = newSet
      }
    }
  }
}

// ====== Multi-Fee Selection Computed ======

const selectableRecords = computed(() => {
  return records.value.filter(r => getRecordDue(r) > 0)
})

const allRecordsSelected = computed(() => {
  return selectableRecords.value.length > 0 && selectableRecords.value.every(r => selectedRecordIds.value.has(r.id))
})

const someRecordsSelected = computed(() => {
  return selectableRecords.value.some(r => selectedRecordIds.value.has(r.id))
})

const selectableNotified = computed(() => {
  return notifiedFees.value.filter(f => f.status !== 'paid' && f.status !== 'expired')
})

const allNotifiedSelected = computed(() => {
  return selectableNotified.value.length > 0 && selectableNotified.value.every(f => selectedNotifiedIds.value.has(f.id))
})

const someNotifiedSelected = computed(() => {
  return selectableNotified.value.some(f => selectedNotifiedIds.value.has(f.id))
})

const totalSelectedCount = computed(() => {
  return selectedRecordIds.value.size + selectedNotifiedIds.value.size
})

const totalSelectedAmount = computed(() => {
  let total = 0
  for (const id of selectedRecordIds.value) {
    const rec = resolveSelectedItem(id)
    if (rec) total += getRecordDue(rec)
  }
  for (const id of selectedNotifiedIds.value) {
    const rec = resolveSelectedItem(id)
    if (rec) total += getRecordDue(rec)
  }
  return total
})

const selectedFeeBreakdown = computed(() => {
  const items = []
  const pushItem = (id) => {
    const rec = resolveSelectedItem(id)
    if (!rec) return
    const due = getRecordDue(rec)
    if (due <= 0) return
    items.push({
      id: rec.id,
      name: rec.title || rec.fee_type_name || rec.month || 'Fee',
      amount: due,
      category: rec.fee_category || 'event_based',
      assignment_id: rec.fee_assignment_id || rec.assignment_id || rec.fee_structure_id || rec.id,
    })
  }
  for (const id of selectedRecordIds.value) pushItem(id)
  for (const id of selectedNotifiedIds.value) pushItem(id)
  return items
})

// ====== Selection Methods ======

const toggleRecord = (rec) => {
  if (getRecordDue(rec) <= 0) return
  const newSet = new Set(selectedRecordIds.value)
  if (newSet.has(rec.id)) {
    newSet.delete(rec.id)
  } else {
    newSet.add(rec.id)
  }
  selectedRecordIds.value = newSet
}

const toggleAllRecords = () => {
  if (allRecordsSelected.value) {
    selectedRecordIds.value = new Set()
  } else {
    const newSet = new Set()
    selectableRecords.value.forEach(r => newSet.add(r.id))
    selectedRecordIds.value = newSet
  }
}

const toggleNotifiedFee = (fee) => {
  if (fee.status === 'paid' || fee.status === 'expired') return
  const newSet = new Set(selectedNotifiedIds.value)
  if (newSet.has(fee.id)) {
    newSet.delete(fee.id)
  } else {
    newSet.add(fee.id)
  }
  selectedNotifiedIds.value = newSet
}

const toggleAllNotified = () => {
  if (allNotifiedSelected.value) {
    selectedNotifiedIds.value = new Set()
  } else {
    const newSet = new Set()
    selectableNotified.value.forEach(f => newSet.add(f.id))
    selectedNotifiedIds.value = newSet
  }
}

const clearAllSelections = () => {
  selectedRecordIds.value = new Set()
  selectedNotifiedIds.value = new Set()
  examMonthlyIncluded.value = false
}

// Skip the monthly fee in the combined exam+monthly payment flow
const skipMonthlyFee = () => {
  monthlyFeeSkipped.value = true
  // Remove the monthly fee from selected records
  if (monthlyFeeToInclude.value) {
    const newSet = new Set(selectedRecordIds.value)
    newSet.delete(monthlyFeeToInclude.value.id)
    selectedRecordIds.value = newSet
  }
  // Update the paying amount to only include the exam fee
  payingAmount.value = totalSelectedAmount.value
}

// ====== Load Notified Fees ======

const loadNotifiedFees = async (studentId) => {
  try {
    const res = await smartFeeService.student.notifiedFees({ student_id: studentId })
    // Handle both response formats:
    // 1. Direct array: res.data?.data = [...] (after backend fix)
    // 2. Double-wrapped: res.data?.data = { success: true, data: [...] } (before backend fix)
    let raw = res.data?.data || []
    if (!Array.isArray(raw) && raw?.data && Array.isArray(raw.data)) {
      raw = raw.data
    }
    // Flatten grouped notified fees into a single list
    const flat = []
    if (Array.isArray(raw)) {
      raw.forEach(group => {
        // Backend returns 'notifications' key, but also support 'fees' for flexibility
        const items = group.notifications || group.fees || []
        if (Array.isArray(items)) {
          items.forEach(fee => {
            flat.push({
              ...fee,
              enrollment_id: group.enrollment_id,
              enrollment_label: group.enrollment_label,
              batch_name: group.batch_name,
              course_name: group.course_name,
              assignment_id: fee.assignment_id || null,
              exam_id: fee.exam_id || fee.meta?.exam_id || null,
              type: fee.type || 'exam_fee',
            })
          })
        }
      })
    }
    const enrollmentId = route.params.enrollmentId || selectedEnrollmentId.value
    const coveredNotifIds = new Set(
      records.value
        .filter(r => r.notification_id && r.enrollment_id === enrollmentId)
        .map(r => r.notification_id),
    )
    const coveredNotifKeys = new Set(
      records.value
        .filter(r => String(r.id || '').startsWith('notif_'))
        .map(r => String(r.id).slice(6)),
    )
    notifiedFees.value = flat.filter(f =>
      f.enrollment_id === enrollmentId &&
      !coveredNotifIds.has(f.id) &&
      !coveredNotifKeys.has(f.id),
    )

    // Auto-select the notified fee if navigated from Pay Now button (notify_fee_id query param)
    if (notifyFeeId.value && notifiedFees.value.length > 0) {
      const notifyKey = String(notifyFeeId.value)
      const matchedFee = notifiedFees.value.find(
        f => f.fee_structure_id === notifyKey
          || f.id === notifyKey
          || `notif_${f.id}` === notifyKey
          || (notifyKey.startsWith('notif_') && f.id === notifyKey.slice(6))
      )
      if (matchedFee && (matchedFee.status === 'unread' || matchedFee.status === 'read')) {
        const newSet = new Set(selectedNotifiedIds.value)
        newSet.add(matchedFee.id)
        selectedNotifiedIds.value = newSet
      }
    }

    // Auto-select monthly fee if include_monthly is true and we have a monthly fee to include
    if (includeMonthly.value && !monthlyFeeSkipped.value) {
      const monthlyFee = monthlyFeeToInclude.value
      if (monthlyFee) {
        const newSet = new Set(selectedRecordIds.value)
        newSet.add(monthlyFee.id)
        selectedRecordIds.value = newSet
      }
    }
  } catch (e) {
    console.warn('Could not load notified fees:', e)
    notifiedFees.value = []
  }
}

// ====== Load Data ======

const loadEnrollments = async () => {
  try {
    const res = await studentPortalService.enrollments()
    const data = res.data?.data || []
    enrollments.value = data.map(e => ({
      id: e.id,
      label: `${e.batch?.course?.name || 'Course'} - ${e.batch?.name || 'Batch'}`,
      batch: e.batch,
    }))
    if (enrollments.value.length === 1) {
      selectedEnrollmentId.value = enrollments.value[0].id
      return true
    }
    return false
  } catch { return false }
}

const loadData = async () => {
  loading.value = true
  try {
    let enrollmentId = route.params.enrollmentId
    if (!enrollmentId) {
      if (selectedEnrollmentId.value) {
        enrollmentId = selectedEnrollmentId.value
      } else {
        const hasAutoSelect = await loadEnrollments()
        if (hasAutoSelect && selectedEnrollmentId.value) enrollmentId = selectedEnrollmentId.value
        else { loading.value = false; return }
      }
    }
    const recRes = await studentPortalService.feeRecords(enrollmentId)
    const data = recRes.data?.data || {}
    records.value = data.records || []
    // Debug: log all records from API
    console.log('[DEBUG loadData] All records from API:', records.value.map(r => ({
      id: r.id,
      month: r.month,
      dedup_month: r.dedup_month,
      fee_category: r.fee_category,
      payment_status: r.payment_status,
      status: r.status,
      total_monthly_fee: r.total_monthly_fee,
      due_amount: r.due_amount,
      paid_amount: r.paid_amount,
      is_smart_fee: r.is_smart_fee,
    })))
    summary.value = data.summary || null
    enrollmentData.value = data.enrollment || null
    gateways.value = data.gateways || []
    const amounts = {}, payingStates = {}
    records.value.forEach(r => {
      amounts[r.id] = Math.max(0, (r.due_amount || 0) - (r.paid_amount || 0))
      payingStates[r.id] = false
    })
    payAmounts.value = amounts
    paying.value = payingStates

    // Load pending payment transactions
    await loadPendingPayments(enrollmentId)

    // Load notified fees (exam fees, library fees, etc.)
    if (data.enrollment?.student_id) {
      await loadNotifiedFees(data.enrollment.student_id)
    }

    applyExamQuerySelection(enrollmentId)

    // Auto-open payment modal if navigated from dashboard exam fee card with include_monthly
    if (includeMonthly.value && totalSelectedCount.value > 0 && !hasPendingPayments.value) {
      // Small delay to let the UI settle
      setTimeout(() => {
        initiateBulkPayment()
      }, 300)
    }
  } catch (e) { console.error('Failed to load fee data:', e) }
  finally { loading.value = false }
}

const loadPendingPayments = async (enrollmentId) => {
  try {
    const res = await smartFeeService.student.payments(enrollmentId)
    const payments = res.data?.data?.data || res.data?.data || []
    Object.keys(pendingPayments).forEach(k => delete pendingPayments[k])
    // Preserve locally-locked records that aren't yet in the API response
    const localLocks = Object.entries(pendingPayments)
      .filter(([k, v]) => k.startsWith('local_'))
      .map(([k, v]) => ({ key: k, value: v }))

    payments.forEach(p => {
      // Check 'pending' status (student portal cash payments)  
      // and 'awaiting_confirmation' (legacy monthly fee)
      const isPending = p.status === 'pending' || p.status === 'awaiting_confirmation'
      if (isPending) {
        const allocs = p.allocations || []
        const assignmentIds = allocs.length > 0
          ? allocs.map(a => a.fee_assignment_id).filter(Boolean)
          : [p.fee_assignment_id, p.monthly_fee_record_id].filter(Boolean)
        // Collect due_dates and period_months from allocations for reliable matching
        const dueDates = allocs.flatMap(a => {
          const d = a.fee_assignment?.due_date || a.fee_assignment?.period_month || a.due_date
          return d ? [String(d).slice(0, 10)] : []
        })
        // Remove any local lock that this API payment now covers
        localLocks.forEach(lock => {
          const overlap = lock.value.assignment_ids?.some(id => assignmentIds.includes(id))
          if (overlap) delete pendingPayments[lock.key]
        })
        pendingPayments[p.id] = {
          transaction_no: p.transaction_no,
          amount: p.amount,
          payment_method: p.payment_method,
          status: p.status,
          created_at: p.created_at,
          assignment_ids: assignmentIds,
          due_dates: dueDates,
        }
      }
    })
    // Re-add local locks that weren't matched by API response
    localLocks.forEach(lock => {
      if (pendingPayments[lock.key] === undefined) {
        pendingPayments[lock.key] = lock.value
      }
    })
    // Mark records as locked/unlocked
    records.value.forEach(r => { r._locked = isRecordLocked(r) })
    notifiedFees.value.forEach(f => { f._locked = isRecordLocked(f) })
    console.log('[loadPendingPayments] locked records:', 
      records.value.filter(r => r._locked).map(r => ({ id: r.id, month: r.month, locked: r._locked })),
      'pendingPayments:', Object.values(pendingPayments)
    )
  } catch (e) {
    console.warn('Could not load pending payments:', e)
  }
}

// Immediately lock records after payment (before API reload confirms it)
const lockPaidRecords = () => {
  const tempId = 'local_' + Date.now()
  if (isBulkPayment.value) {
    const assignmentIds = getAllSelectedAssignmentIds()
    pendingPayments[tempId] = {
      transaction_no: 'pending',
      amount: payingAmount.value,
      payment_method: '',
      status: 'pending',
      created_at: new Date().toISOString(),
      assignment_ids: assignmentIds,
      due_dates: [],
    }
  } else if (payingRecord.value) {
    const rec = payingRecord.value
    const recordId = rec.fee_assignment_id || rec.id
    const recDates = [rec.due_date, rec.dedup_month, rec.month].filter(Boolean).map(d => String(d).slice(0,10))
    pendingPayments[tempId] = {
      transaction_no: 'pending',
      amount: payingAmount.value,
      payment_method: '',
      status: 'pending',
      created_at: new Date().toISOString(),
      assignment_ids: [recordId],
      due_dates: recDates,
    }
  }
  // Mark records as locked immediately for instant UI feedback
  records.value.forEach(r => { r._locked = isRecordLocked(r) })
  notifiedFees.value.forEach(f => { f._locked = isRecordLocked(f) })
}

const pendingCount = computed(() => Object.keys(pendingPayments).length)
const hasPendingPayments = computed(() => pendingCount.value > 0)

// Check if a specific record has a pending payment awaiting admin approval
const isRecordLocked = (rec) => {
  if (!rec) return false
  // Try multiple possible ID fields
  const candidateIds = [
    rec.fee_assignment_id,
    rec.id,
    rec.monthly_fee_record_id,
  ].filter(Boolean).map(String)
  
  // Record's date keys for reliable matching
  const recDates = [
    rec.due_date,
    rec.dedup_month,
    rec.month,
  ].filter(Boolean).map(d => String(d).slice(0, 10))
  
  return Object.values(pendingPayments).some(p => {
    // ID-based match
    if (p.assignment_ids && p.assignment_ids.length > 0) {
      if (p.assignment_ids.some(id => candidateIds.includes(String(id)))) return true
    }
    // Date-based match — much more reliable across systems
    if (p.due_dates && p.due_dates.length > 0) {
      if (p.due_dates.some(d => recDates.some(rd => rd.includes(d) || d.includes(rd)))) return true
    }
    // Direct field match
    if (candidateIds.includes(String(p.fee_assignment_id))) return true
    if (candidateIds.includes(String(p.monthly_fee_record_id))) return true
    return false
  })
}

const onEnrollmentChange = () => {
  clearAllSelections()
  // Reset to landing view when switching enrollments
  backToLanding()
  loadData()
}
const goBack = () => router.push({ name: 'StudentFeeDashboard' })

// ====== Single Fee Payment ======

const initiatePayment = (record) => {
  // Block payment for this specific record if it has a pending payment awaiting admin approval
  if (isRecordLocked(record)) {
    alert('🔒 This specific fee already has a payment awaiting admin approval. Please wait until it is confirmed or rejected.')
    return
  }
  // Prevent double payment: don't allow paying for already-paid records
  if (record.payment_status === 'paid' || record.status === 'paid') {
    console.warn('This record is already paid')
    return
  }
  const due = getRecordDue(record)
  if (due <= 0) {
    console.warn('No due amount for this record')
    return
  }
  isBulkPayment.value = false
  payingRecord.value = record
  payingAmount.value = payAmounts.value[record.id] || due
  checkoutStep.value = 'amount'
  showPaymentModal.value = true
  paymentSuccess.value = false
  paymentInvoice.value = null
}

// ====== Bulk Payment ======

const initiateBulkPayment = () => {
  const selectedRecords = [
    ...[...selectedRecordIds.value].map(resolveSelectedItem).filter(Boolean),
    ...[...selectedNotifiedIds.value].map(resolveSelectedItem).filter(Boolean),
  ]
  const lockedRecords = selectedRecords.filter(r => isRecordLocked(r))
  if (lockedRecords.length > 0) {
    alert(`🔒 ${lockedRecords.length} selected fee(s) already have a payment awaiting admin approval. Please deselect them or wait until approved.`)
    return
  }
  if (totalSelectedCount.value === 0) return
  isBulkPayment.value = true
  payingRecord.value = null
  payingAmount.value = totalSelectedAmount.value
  checkoutStep.value = 'amount'
  showPaymentModal.value = true
  paymentSuccess.value = false
  paymentInvoice.value = null
}

// Direct payment for a notified fee (from Pay Now banner button)
const payNotifiedFee = () => {
  if (hasPendingPayments.value) {
    alert('🔒 A previous payment is awaiting admin approval. New payments are blocked until it is processed.')
    return
  }
  if (!notifyFeeInfo.value) return
  // Try to find and select the matching fee in notifiedFees
  const matchedFee = notifiedFees.value.find(
    f => f.fee_structure_id === notifyFeeId.value || f.id === notifyFeeId.value
  )
  if (matchedFee && (matchedFee.status === 'unread' || matchedFee.status === 'read')) {
    const newSet = new Set(selectedNotifiedIds.value)
    newSet.add(matchedFee.id)
    selectedNotifiedIds.value = newSet
  }
  // If still no selection, manually add the fee_structure_id to selectedNotifiedIds
  // so getAllSelectedAssignmentIds() will include it
  if (totalSelectedCount.value === 0) {
    // Create a synthetic fee entry so the selection system works
    const syntheticFee = {
      id: notifyFeeId.value,
      fee_structure_id: notifyFeeId.value,
      assignment_id: notifyFeeId.value,
      amount: Number(notifyFeeInfo.value.amount),
      title: notifyFeeInfo.value.title,
      status: 'unread',
      fee_category: 'event_based',
    }
    notifiedFees.value.push(syntheticFee)
    const newSet = new Set(selectedNotifiedIds.value)
    newSet.add(syntheticFee.id)
    selectedNotifiedIds.value = newSet
  }
  // Open the payment modal
  if (totalSelectedCount.value > 0) {
    initiateBulkPayment()
  }
}

const getAllSelectedAssignmentIds = () => {
  const ids = []
  const pushAssignmentId = (id) => {
    const rec = resolveSelectedItem(id)
    if (!rec) {
      ids.push(id)
      return
    }
    if (rec.fee_assignment_id) {
      ids.push(rec.fee_assignment_id)
    } else if (rec.fee_structure_id) {
      ids.push(rec.fee_structure_id)
    } else if (rec.notification_id) {
      ids.push(rec.notification_id)
    } else if (String(rec.id || '').startsWith('notif_')) {
      ids.push(String(rec.id).slice(6))
    } else {
      ids.push(rec.id)
    }
  }
  for (const id of selectedRecordIds.value) pushAssignmentId(id)
  for (const id of selectedNotifiedIds.value) pushAssignmentId(id)
  return ids
}

const clearNotifyQuery = () => {
  // Clear the notification-driven query params so the banner doesn't persist
  notifyFeeId.value = null
  notifyAmount.value = null
  notifyTitle.value = null
  includeMonthly.value = false
  monthlyFeeSkipped.value = false
  // Also replace URL to remove query params
  const query = { ...route.query }
  delete query.notify_fee_id
  delete query.notify_amount
  delete query.notify_title
  delete query.include_monthly
  router.replace({ query }).catch(() => {})
}

const payWithGateway = async (gatewayCode) => {
  const enrollmentId = route.params.enrollmentId || selectedEnrollmentId.value
  if (!enrollmentId || !payingAmount.value) return

  showPaymentModal.value = false
  isPaying.value = true

  try {
    if (isBulkPayment.value) {
      // Bulk payment via bulkPay endpoint
      const assignmentIds = getAllSelectedAssignmentIds()
      console.log('[DEBUG] getAllSelectedAssignmentIds:', JSON.stringify(Array.from(selectedRecordIds.value)), JSON.stringify(Array.from(selectedNotifiedIds.value)), '→', JSON.stringify(assignmentIds))
      console.log('[DEBUG] availableMonthlyFee:', JSON.stringify(availableMonthlyFee.value))
      console.log('[DEBUG] examMonthlyIncluded:', examMonthlyIncluded.value)
      const res = await smartFeeService.student.bulkPay({
        enrollment_id: enrollmentId,
        assignment_ids: assignmentIds,
        amount: Number(payingAmount.value),
        payment_method: gatewayCode,
        gateway_trx_id: 'TRX' + Date.now(),
      })
      const result = res.data?.data || res.data
      paymentSuccessMessage.value = `Bulk payment of ৳${formatNumber(payingAmount.value)} via ${gatewayCode} completed successfully!`
      paymentSuccess.value = true
      clearAllSelections()
      clearNotifyQuery()
      if (result.auto_confirmed) {
        try {
          const invRes = await smartFeeService.invoices.getByTransaction(result.transaction?.id)
          if (invRes.data?.data) paymentInvoice.value = invRes.data.data
        } catch {}
      }
    } else {
      // Single payment via existing pay endpoint
      if (!payingRecord.value) return
      paying.value[payingRecord.value.id] = true
      const res = await smartFeeService.student.pay({
        enrollment_id: enrollmentId,
        amount: Number(payingAmount.value),
        payment_method: gatewayCode,
        gateway_trx_id: 'TRX' + Date.now(),
      })
      const result = res.data?.data || res.data
      if (result.auto_confirmed) {
        paymentSuccessMessage.value = `Payment of ৳${formatNumber(payingAmount.value)} via ${gatewayCode} completed successfully!`
        paymentSuccess.value = true
        clearNotifyQuery()
        try {
          const invRes = await smartFeeService.invoices.getByTransaction(result.transaction?.id)
          if (invRes.data?.data) paymentInvoice.value = invRes.data.data
        } catch {}
      } else {
        paymentSuccessMessage.value = `Payment of ৳${formatNumber(payingAmount.value)} via ${gatewayCode} submitted. Awaiting admin confirmation.`
        paymentSuccess.value = true
        clearNotifyQuery()
      }
    }
    // Immediately lock the record(s) locally before reload
    lockPaidRecords()
    await loadData()
  } catch (e) { alert(e.response?.data?.message || 'Payment failed') }
  finally {
    isPaying.value = false
    if (payingRecord.value) paying.value[payingRecord.value.id] = false
  }
}

const payManual = async (method) => {
  const enrollmentId = route.params.enrollmentId || selectedEnrollmentId.value
  if (!enrollmentId || !payingAmount.value) return

  showPaymentModal.value = false
  isPaying.value = true

  try {
    if (isBulkPayment.value) {
      // Bulk payment via bulkPay endpoint
      const assignmentIds = getAllSelectedAssignmentIds()
      const res = await smartFeeService.student.bulkPay({
        enrollment_id: enrollmentId,
        assignment_ids: assignmentIds,
        amount: Number(payingAmount.value),
        payment_method: method,
      })
      const result = res.data?.data || res.data
      paymentSuccessMessage.value = `Bulk payment of ৳${formatNumber(payingAmount.value)} via ${method} recorded. Awaiting admin verification.`
      paymentSuccess.value = true
      clearAllSelections()
      clearNotifyQuery()
      if (result.auto_confirmed) {
        try {
          const invRes = await smartFeeService.invoices.getByTransaction(result.transaction?.id)
          if (invRes.data?.data) paymentInvoice.value = invRes.data.data
        } catch {}
      }
    } else {
      // Single payment
      if (!payingRecord.value) return
      paying.value[payingRecord.value.id] = true
      const res = await smartFeeService.student.pay({
        enrollment_id: enrollmentId,
        amount: Number(payingAmount.value),
        payment_method: method,
      })
      const result = res.data?.data || res.data
      paymentSuccessMessage.value = `Payment of ৳${formatNumber(payingAmount.value)} via ${method} recorded. Awaiting admin verification.`
      paymentSuccess.value = true
      clearNotifyQuery()
      if (result.auto_confirmed) {
        try {
          const invRes = await smartFeeService.invoices.getByTransaction(result.transaction?.id)
          if (invRes.data?.data) paymentInvoice.value = invRes.data.data
        } catch {}
      }
    }
    // Immediately lock the record(s) locally before reload
    lockPaidRecords()
    await loadData()
  } catch (e) { alert(e.response?.data?.message || 'Payment failed') }
  finally {
    isPaying.value = false
    if (payingRecord.value) paying.value[payingRecord.value.id] = false
  }
}

const downloadInvoice = async () => {
  if (!paymentInvoice.value) return
  try {
    const response = await smartFeeService.invoices.download(paymentInvoice.value.id)
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${paymentInvoice.value.invoice_no}.pdf`)
    document.body.appendChild(link); link.click(); document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (err) { console.error('Failed to download invoice:', err) }
}

const applyExamQuerySelection = (enrollmentId) => {
  if (route.query.view === 'detail' && route.query.category) {
    viewMode.value = 'detail'
    activeCategory.value = route.query.category
  }
  const examFilter = route.query.exam_id ? String(route.query.exam_id) : null
  if (!examFilter) return
  const match = allRecords.value.find(r =>
    r.fee_category === 'event_based'
    && String(r.exam_id || r.meta?.exam_id || '') === examFilter
    && getRecordDue(r) > 0
  )
  if (match) {
    const newSet = new Set(selectedRecordIds.value)
    newSet.add(match.id)
    selectedRecordIds.value = newSet
    return
  }
  const notifMatch = notifiedFees.value.find(f =>
    String(f.exam_id || f.meta?.exam_id || '') === examFilter
    && (f.status === 'unread' || f.status === 'read')
  )
  if (notifMatch) {
    const newSet = new Set(selectedNotifiedIds.value)
    newSet.add(notifMatch.id)
    selectedNotifiedIds.value = newSet
  }
}

onMounted(() => loadData())
</script>

<style scoped>
/* ========== BASE ========== */
.fee-payment-page {
  max-width: 960px;
  margin: 0 auto;
  padding: 1.5rem;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  color: var(--text-primary);
}

/* ========== PAGE HEADER ========== */
.page-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 2rem;
}

.back-btn {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}

.back-btn svg { width: 1.25rem; height: 1.25rem; color: var(--text-muted); }

.back-btn:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
  color: var(--text-primary);
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 0.875rem;
  margin: 0.25rem 0 0;
}

/* ========== ENROLLMENT SELECTOR ========== */
.enrollment-card {
  background: var(--bg-card);
  border-radius: 1rem;
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  margin-bottom: 1.5rem;
}

.enrollment-icon {
  width: 2.5rem;
  height: 2.5rem;
  background: #eff6ff;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #3b82f6;
}

.enrollment-icon svg { width: 1.25rem; height: 1.25rem; }

.enrollment-body { flex: 1; }

.enrollment-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  margin-bottom: 0.35rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.enrollment-select {
  width: 100%;
  padding: 0.65rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 0.75rem;
  font-size: 0.9rem;
  color: var(--text-primary);
  background: var(--bg-card);
  cursor: pointer;
  transition: all 0.2s;
}

.enrollment-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

/* ========== LOADING ========== */
.loading-state {
  text-align: center;
  padding: 4rem 2rem;
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
}

.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin { to { transform: rotate(360deg); } }

.loading-state p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }

/* ========== EMPTY STATE ========== */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
}

.empty-icon {
  width: 3.5rem;
  height: 3.5rem;
  margin: 0 auto 1rem;
  background: var(--bg-surface-muted);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
}

.empty-icon svg { width: 1.75rem; height: 1.75rem; }

.empty-state h3 {
  font-size: 1.1rem;
  color: var(--text-primary);
  margin: 0 0 0.35rem;
}

.empty-state p {
  color: var(--text-muted);
  font-size: 0.875rem;
  margin: 0;
}

/* ========== STATS CARDS ========== */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: var(--bg-card);
  border-radius: 1rem;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid var(--border-color);
  transition: all 0.2s;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
  transform: translateY(-1px);
}

.stat-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon svg { width: 1.25rem; height: 1.25rem; }

.stat-due .stat-icon { background: #fef2f2; color: #ef4444; }
.stat-paid .stat-icon { background: #ecfdf5; color: #10b981; }
.stat-pending .stat-icon { background: #fffbeb; color: #f59e0b; }

.stat-info { flex: 1; }

.stat-label {
  display: block;
  font-size: 0.75rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  margin-bottom: 0.15rem;
}

.stat-value {
  display: block;
  font-size: 1.35rem;
  font-weight: 700;
}

.stat-due .stat-value { color: #ef4444; }
.stat-paid .stat-value { color: #10b981; }
.stat-pending .stat-value { color: #f59e0b; }

/* ========== ALERT ========== */
.alert {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 1rem 1.25rem;
  border-radius: 0.75rem;
  margin-bottom: 1.5rem;
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

.alert-warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
}

.alert-info {
  background: #eff6ff;
  border: 1px solid #93c5fd;
}

.alert-info .alert-icon {
  color: #3b82f6;
}

.alert-info .alert-body strong {
  color: #1e40af;
}

.alert-info .alert-body p {
  color: #2563eb;
}

.alert-info .alert-body .alert-hint {
  color: #3b82f6;
  font-size: 0.85rem;
  margin-top: 0.25rem;
  opacity: 0.85;
}

.alert-proceed-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 0.6rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  align-self: center;
  transition: background 0.2s, transform 0.15s;
}

.alert-proceed-btn svg {
  width: 1rem;
  height: 1rem;
}

.alert-proceed-btn:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.alert-proceed-btn:active {
  transform: translateY(0);
}

.alert-icon {
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  color: #f59e0b;
}

.alert-icon svg { width: 100%; height: 100%; }

.alert-body { flex: 1; }

.alert-body strong {
  display: block;
  font-size: 0.9rem;
  color: #92400e;
  margin-bottom: 0.15rem;
}

.alert-body p {
  margin: 0;
  font-size: 0.8rem;
  color: #b45309;
  line-height: 1.4;
}

/* ========== NOTIFICATION BANNER ========== */
.notif-banner {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  border: 1px solid #93c5fd;
  border-radius: 0.85rem;
  margin-bottom: 1.5rem;
  animation: slideDown 0.3s ease-out;
}

.notif-banner-icon {
  width: 2.5rem;
  height: 2.5rem;
  background: #3b82f6;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: white;
}

.notif-banner-icon svg {
  width: 1.25rem;
  height: 1.25rem;
}

.notif-banner-body {
  flex: 1;
  min-width: 0;
}

.notif-banner-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1e40af;
  margin-bottom: 0.25rem;
}

.notif-banner-desc {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.notif-banner-fee-name {
  font-size: 0.8rem;
  color: #2563eb;
  font-weight: 600;
}

.notif-banner-amount {
  font-size: 0.8rem;
  color: #1e40af;
}

.notif-banner-amount strong {
  font-size: 0.95rem;
}

.notif-banner-hint {
  font-size: 0.75rem;
  color: #3b82f6;
  margin: 0.35rem 0 0;
  opacity: 0.85;
  line-height: 1.3;
}

.notif-banner-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.25rem;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 0.65rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(37,99,235,0.25);
}

.notif-banner-btn svg {
  width: 1rem;
  height: 1rem;
}

.notif-banner-btn:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37,99,235,0.35);
}

.notif-banner-btn:active {
  transform: translateY(0);
}

.notif-banner-locked {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  background: var(--bg-accent);
  color: var(--text-muted);
  border-radius: 0.65rem;
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
}

.notif-banner-locked svg {
  width: 1rem;
  height: 1rem;
}

/* ========== FEE SECTION (Card-based) ========== */
.fee-section {
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
  overflow: hidden;
  margin-bottom: 1.5rem;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 0.5rem;
}

.section-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.section-icon {
  width: 1.2rem;
  height: 1.2rem;
  flex-shrink: 0;
}

.section-icon.icon-notif { color: #f59e0b; }
.section-icon.icon-records { color: #3b82f6; }

.section-title {
  font-size: 1rem;
  font-weight: 700;
  margin: 0;
  color: var(--text-primary);
}

.section-badge {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
  background: var(--bg-accent);
  padding: 0.2rem 0.65rem;
  border-radius: 1rem;
}

.section-empty {
  padding: 2.5rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.section-empty .empty-icon-sm {
  width: 2.5rem;
  height: 2.5rem;
  margin: 0 auto 0.75rem;
  color: #cbd5e1;
}

/* Select All Toggle */
.select-all-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 500;
  color: #3b82f6;
  padding: 0.3rem 0.65rem;
  border-radius: 0.5rem;
  border: 1px solid #dbeafe;
  background: #eff6ff;
  transition: all 0.15s;
  user-select: none;
}

.select-all-toggle:hover {
  background: #dbeafe;
  border-color: #93c5fd;
}

.select-all-toggle input {
  width: 0.9rem;
  height: 0.9rem;
  accent-color: #3b82f6;
}

.toggle-label {
  white-space: nowrap;
}

/* ========== FEE CARD GRID ========== */
.fee-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  padding: 1.25rem;
}

/* ========== FEE CARD ========== */
.fee-card {
  background: var(--bg-card);
  border: 1.5px solid #e2e8f0;
  border-radius: 0.85rem;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
}

.fee-card:hover {
  border-color: #93c5fd;
  box-shadow: 0 4px 12px rgba(59,130,246,0.08);
  transform: translateY(-2px);
}

.fee-card:active {
  transform: translateY(0);
}

/* Card Selected */
.fee-card.card-selected {
  border-color: #3b82f6;
  background: #f8faff;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.12), 0 4px 12px rgba(59,130,246,0.08);
}

/* Card Paid / Expired */
.fee-card.card-paid {
  opacity: 0.7;
  cursor: default;
}

.fee-card.card-paid:hover {
  transform: none;
  box-shadow: none;
  border-color: #e2e8f0;
}

/* Card Overdue */
.fee-card.card-overdue {
  border-color: #fca5a5;
  background: #fffbfb;
}

.fee-card.card-overdue:hover {
  border-color: #ef4444;
  box-shadow: 0 4px 12px rgba(239,68,68,0.08);
}

/* Card Top Row */
.card-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 0.85rem 0;
}

/* Category Badge (card) */
.cat-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.cat-badge.cat-one_time {
  background: #dbeafe;
  color: #1e40af;
}

.cat-badge.cat-monthly {
  background: #d1fae5;
  color: #065f46;
}

.cat-badge.cat-event_based {
  background: #fef3c7;
  color: #92400e;
}

/* Card Status */
.card-status {
  margin-left: auto;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  padding: 0.2rem 0.5rem;
  border-radius: 1rem;
}

.card-status.st-paid {
  background: #d1fae5;
  color: #065f46;
}

.card-status.st-pending {
  background: #fee2e2;
  color: #991b1b;
}

.card-status.st-partial {
  background: #fef3c7;
  color: #92400e;
}

.card-status.st-expired {
  background: #fce7f3;
  color: #9d174d;
}

/* Card Pay Button (small icon in top row) */
.card-pay-btn {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 0.4rem;
  border: 1px solid #dbeafe;
  background: #eff6ff;
  color: #3b82f6;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  flex-shrink: 0;
}

.card-pay-btn svg {
  width: 0.85rem;
  height: 0.85rem;
}

.card-pay-btn:hover {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.card-pay-btn:disabled {
  background: var(--bg-accent);
  color: var(--text-muted);
  border-color: #e2e8f0;
  cursor: not-allowed;
}

/* Card Paid Badge (small checkmark) */
.card-paid-badge {
  width: 1.75rem;
  height: 1.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #10b981;
  flex-shrink: 0;
}

.card-paid-badge svg {
  width: 1rem;
  height: 1rem;
}

/* Card Body */
.card-body {
  padding: 0.65rem 0.85rem;
  flex: 1;
}

.card-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Card Meta Row (e.g. Month) */
.card-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
  padding-bottom: 0.4rem;
  border-bottom: 1px dashed #f1f5f9;
}

.card-meta-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.card-meta-value {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
}

/* Card Amount Row (for notified fees) */
.card-amount-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.3rem;
}

.card-amount-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 500;
}

.card-amount-value {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text-primary);
}

/* Card Due Row */
.card-due-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-due-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 500;
}

.card-due-value {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
}

.card-due-value.due-overdue {
  color: #ef4444;
}

/* Overdue Dot */
.overdue-dot {
  display: inline-block;
  width: 0.45rem;
  height: 0.45rem;
  background: #ef4444;
  border-radius: 50%;
  margin-left: 0.3rem;
  animation: pulse-dot 1.5s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

/* Card Amounts (breakdown for fee records) */
.card-amounts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.3rem 0.75rem;
}

.card-amount-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.2rem 0;
}

.ca-label {
  font-size: 0.65rem;
  color: var(--text-muted);
  font-weight: 500;
}

.ca-value {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.card-amount-item.ca-discount .ca-value {
  color: #8b5cf6;
}

.card-amount-item.ca-paid .ca-value {
  color: #10b981;
}

.card-amount-item.ca-due .ca-value {
  color: #ef4444;
  font-size: 0.85rem;
}

/* Card Footer */
.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0.85rem;
  border-top: 1px solid var(--border-light);
  background: var(--bg-surface-muted);
}

.card-footer-text {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 500;
}

.card-footer-text.text-danger {
  color: #ef4444;
}

/* ========== TOAST ========== */
.toast {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-radius: 1rem;
  margin-bottom: 1.5rem;
}

.toast-success {
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  border: 1px solid #a7f3d0;
}

.toast-icon {
  width: 2.5rem;
  height: 2.5rem;
  background: #10b981;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.toast-icon svg { width: 1.25rem; height: 1.25rem; color: white; }

.toast-body { flex: 1; }

.toast-body strong {
  display: block;
  color: #065f46;
  font-size: 0.95rem;
  margin-bottom: 0.2rem;
}

.toast-body p {
  color: #047857;
  margin: 0;
  font-size: 0.85rem;
}

.toast-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #a7f3d0;
}

.btn-invoice {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  background: #10b981;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-invoice svg { width: 1rem; height: 1rem; }

.btn-invoice:hover { background: #059669; }

.invoice-ref {
  font-size: 0.8rem;
  color: #065f46;
  font-weight: 500;
}

.toast-close {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem;
  flex-shrink: 0;
}

.toast-close svg { width: 1.25rem; height: 1.25rem; color: #6ee7b7; }

.toast-close:hover svg { color: #10b981; }

/* Toast Transition */
.toast-enter-active { animation: slideDown 0.3s ease-out; }
.toast-leave-active { animation: slideDown 0.3s ease-in reverse; }

/* ========== MODAL ========== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-card {
  background: var(--bg-card);
  border-radius: 1.25rem;
  width: 100%;
  max-width: 460px;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 25px 50px rgba(0,0,0,0.25);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.modal-month-badge {
  font-size: 0.7rem;
  font-weight: 600;
  color: #3b82f6;
  background: #eff6ff;
  padding: 0.2rem 0.6rem;
  border-radius: 0.35rem;
  white-space: nowrap;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.modal-back-wrap {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.modal-back {
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.modal-back svg { width: 1rem; height: 1rem; color: var(--text-muted); }

.modal-back:hover { background: var(--bg-surface-muted); }

.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem;
}

.modal-close svg { width: 1.25rem; height: 1.25rem; color: var(--text-muted); }

.modal-close:hover svg { color: var(--text-primary); }

.modal-body { padding: 1.5rem; }

/* Modal Transition */
.modal-enter-active { transition: all 0.25s ease-out; }
.modal-leave-active { transition: all 0.2s ease-in; }
.modal-enter-from { opacity: 0; }
.modal-enter-from .modal-card { transform: scale(0.95) translateY(10px); }
.modal-leave-to { opacity: 0; }
.modal-leave-to .modal-card { transform: scale(0.95) translateY(10px); }

/* ========== CHECKOUT SUMMARY ========== */
.checkout-summary {
  background: var(--bg-surface-muted);
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 1.25rem;
}

.checkout-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.35rem 0;
  font-size: 0.875rem;
}

.checkout-label { color: var(--text-muted); }

.checkout-value { font-weight: 600; color: var(--text-primary); }

.checkout-discount { color: #8b5cf6; }

.checkout-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 0.5rem 0;
}

.checkout-total { padding: 0.5rem 0 0; }

.checkout-amount {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.amount-input {
  width: 100px;
  padding: 0.4rem 0.6rem;
  border: 2px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
  text-align: right;
  outline: none;
  transition: all 0.2s;
}

.amount-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.amount-max {
  font-size: 0.7rem;
  font-weight: 600;
  color: #3b82f6;
  cursor: pointer;
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  background: #eff6ff;
  transition: all 0.2s;
}

.amount-max:hover { background: #dbeafe; }

/* ========== BUTTONS ========== */
.btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.75rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary svg { width: 1.25rem; height: 1.25rem; }

.btn-primary:hover { background: #2563eb; }

.btn-full { width: 100%; }

/* ========== RECAP BAR ========== */
.recap-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: var(--bg-surface-muted);
  border-radius: 0.75rem;
  margin-bottom: 1.25rem;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.recap-bar strong { color: var(--text-primary); }

.recap-month {
  font-size: 0.75rem;
  font-weight: 600;
  color: #3b82f6;
  background: #eff6ff;
  padding: 0.2rem 0.6rem;
  border-radius: 0.25rem;
}

/* ========== METHOD SECTIONS ========== */
.method-section { margin-bottom: 1.25rem; }

.method-section:last-child { margin-bottom: 0; }

.method-heading {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin: 0 0 0.25rem;
}

.method-heading svg { width: 1rem; height: 1rem; }

.method-hint {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin: 0 0 0.75rem;
}

.gateway-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

.gateway-btn {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 0.75rem;
  background: var(--bg-card);
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}

.gateway-btn:hover {
  border-color: #3b82f6;
  background: #eff6ff;
}

.gateway-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.gw-icon {
  font-size: 1.35rem;
  width: 2.25rem;
  height: 2.25rem;
  background: var(--bg-accent);
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.gw-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.gw-name {
  font-weight: 600;
  font-size: 0.8rem;
  color: var(--text-primary);
}

.gw-desc {
  font-size: 0.65rem;
  color: var(--text-muted);
}

.gw-arrow {
  width: 1rem;
  height: 1rem;
  color: #cbd5e1;
  flex-shrink: 0;
}

/* Gateway Brand Colors */
.gw-bkash:hover { border-color: #e2136e; background: #fdf2f7; }
.gw-nagad:hover { border-color: #ed1c24; background: #fef2f2; }
.gw-rocket:hover { border-color: #9816f4; background: #faf5ff; }
.gw-card:hover { border-color: #3b82f6; background: #eff6ff; }
.gw-bank:hover { border-color: #059669; background: #ecfdf5; }

/* Cash Button */
.cash-btn {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #f59e0b;
  border-radius: 0.75rem;
  background: #fffbeb;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}

.cash-btn:hover { background: #fef3c7; }

.cash-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.cash-icon {
  width: 2.25rem;
  height: 2.25rem;
  background: #fef3c7;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #f59e0b;
}

.cash-icon svg { width: 1.25rem; height: 1.25rem; }

.cash-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.cash-name {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-primary);
}

.cash-desc {
  font-size: 0.7rem;
  color: #b45309;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .fee-payment-page { padding: 1rem; }

  .summary-grid { grid-template-columns: 1fr; }

  .gateway-grid { grid-template-columns: 1fr; }

  .modal-card { max-width: 100%; margin: 0.5rem; }

  .fee-card-grid {
    grid-template-columns: 1fr;
    padding: 0.85rem;
    gap: 0.75rem;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
    padding: 1rem 1.15rem;
  }

  .card-amounts {
    grid-template-columns: 1fr;
  }
}

/* ========== PAYMENT HISTORY ========== */
.history-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  overflow: hidden;
  margin-top: 1.25rem;
}

.history-timeline {
  padding: 0 1.25rem 1.25rem;
}

.history-group {
  margin-bottom: 1rem;
}

.history-group:last-child {
  margin-bottom: 0;
}

.history-group-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-bottom: 0.75rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border-light);
}

.hgroup-icon {
  width: 1.1rem;
  height: 1.1rem;
}

.hgroup-icon.paid-icon { color: #10b981; }
.hgroup-icon.pending-icon { color: #f59e0b; }

.history-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.history-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.85rem;
  border-radius: 2rem;
  font-size: 0.8rem;
  font-weight: 500;
  white-space: nowrap;
}

.chip-icon {
  width: 0.85rem;
  height: 0.85rem;
  flex-shrink: 0;
}

.chip-paid {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.chip-pending {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}

.chip-amount {
  font-weight: 700;
  margin-left: 0.15rem;
  opacity: 0.8;
}

/* ========== CHECKBOX ========== */
.checkbox-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: relative;
  cursor: pointer;
  width: 1.25rem;
  height: 1.25rem;
}

.checkbox-wrap input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 100%;
  height: 100%;
  z-index: 1;
}

.checkmark {
  width: 1.15rem;
  height: 1.15rem;
  border: 2px solid #cbd5e1;
  border-radius: 0.3rem;
  background: var(--bg-card);
  transition: all 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.checkbox-wrap input:checked ~ .checkmark {
  background: #3b82f6;
  border-color: #3b82f6;
}

.checkbox-wrap input:checked ~ .checkmark::after {
  content: '';
  width: 0.35rem;
  height: 0.6rem;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
  margin-top: -2px;
}

.checkbox-wrap input:indeterminate ~ .checkmark {
  background: #3b82f6;
  border-color: #3b82f6;
}

.checkbox-wrap input:indeterminate ~ .checkmark::after {
  content: '';
  width: 0.6rem;
  height: 2px;
  background: var(--bg-card);
}

.checkbox-wrap input:disabled ~ .checkmark {
  background: var(--bg-accent);
  border-color: #e2e8f0;
  cursor: not-allowed;
}

.checkbox-wrap input:disabled:checked ~ .checkmark {
  background: #93c5fd;
  border-color: #93c5fd;
}

.text-danger {
  color: #dc2626;
}

/* ========== FLOATING PAY BAR ========== */
.floating-pay-bar {
  position: fixed;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1.5rem;
  background: var(--bg-card);
  border-radius: 1rem;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
  border: 1px solid var(--border-color);
  min-width: 420px;
  max-width: 90vw;
  animation: floatUp 0.3s ease-out;
}

@keyframes floatUp {
  from { opacity: 0; transform: translateX(-50%) translateY(20px); }
  to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

.float-left {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.float-count {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-primary);
}

.float-divider {
  color: #e2e8f0;
  font-size: 1rem;
}

.float-total {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.float-total strong {
  color: var(--text-primary);
  font-size: 1rem;
}

.float-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.float-clear {
  padding: 0.5rem 0.85rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}

.float-clear:hover {
  background: var(--bg-surface-muted);
  border-color: #cbd5e1;
}

.float-pay-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 1.25rem;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.float-pay-btn:hover {
  background: #2563eb;
}

.float-pay-btn .btn-icon {
  width: 1rem;
  height: 1rem;
}

/* Float-up Transition */
.float-up-enter-active { animation: floatUp 0.3s ease-out; }
.float-up-leave-active { animation: floatUp 0.3s ease-in reverse; }

/* ========== COMBINED FEE BANNER (Exam + Monthly) ========== */
.combined-fee-banner {
  background: linear-gradient(135deg, #fefce8 0%, #fffbeb 100%);
  border: 1px solid #fde68a;
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 1.25rem;
}

.cfb-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.cfb-icon {
  font-size: 1.2rem;
}

.cfb-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: #92400e;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.cfb-fees {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-bottom: 0.75rem;
}

.cfb-fee-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0.75rem;
  background: var(--bg-card);
  border-radius: 0.5rem;
  border: 1px solid var(--border-color);
}

.cfb-fee-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  min-width: 0;
}

.cfb-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  flex-shrink: 0;
}

.cfb-badge-exam {
  background: #fef3c7;
  color: #92400e;
}

.cfb-badge-monthly {
  background: #d1fae5;
  color: #065f46;
}

.cfb-fee-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cfb-fee-amount {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-primary);
  white-space: nowrap;
  margin-left: 0.5rem;
}

.cfb-plus {
  text-align: center;
  font-size: 1rem;
  font-weight: 700;
  color: #f59e0b;
  line-height: 1;
}

.cfb-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background: var(--bg-accent);
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
}

.cfb-total-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.cfb-total-value {
  font-size: 1rem;
  font-weight: 800;
  color: #3b82f6;
}

.cfb-skip-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.55rem 1rem;
  background: var(--bg-card);
  border: 1px dashed #cbd5e1;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
}

.cfb-skip-btn:hover {
  background: var(--bg-surface-muted);
  border-color: var(--text-muted);
  color: var(--text-secondary);
}

.cfb-skip-icon {
  width: 1rem;
  height: 1rem;
}

/* ========== BULK BREAKDOWN ========== */
.bulk-breakdown {
  background: var(--bg-surface-muted);
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 1.25rem;
}

.breakdown-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 0.65rem;
}

.breakdown-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  max-height: 200px;
  overflow-y: auto;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.4rem 0;
  font-size: 0.8rem;
}

.breakdown-item + .breakdown-item {
  border-top: 1px solid #e2e8f0;
}

.breakdown-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--text-secondary);
  font-weight: 500;
  flex: 1;
  min-width: 0;
}

.breakdown-label .cat-badge-sm {
  flex-shrink: 0;
}

.breakdown-amount {
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  margin-left: 0.5rem;
}

.breakdown-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.65rem 0 0;
  margin-top: 0.5rem;
  border-top: 2px solid #e2e8f0;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
}

.breakdown-total-value {
  color: #3b82f6;
  font-size: 1.05rem;
}

/* ========== MODAL LARGE ========== */
.modal-lg {
  max-width: 520px;
}

/* ========== RESPONSIVE UPDATES ========== */
@media (max-width: 768px) {
  .floating-pay-bar {
    flex-direction: column;
    gap: 0.65rem;
    min-width: unset;
    width: calc(100% - 2rem);
    bottom: 1rem;
    padding: 0.75rem 1rem;
  }

  .float-left {
    width: 100%;
    justify-content: center;
  }

  .float-right {
    width: 100%;
    justify-content: center;
  }

  /* Category cards responsive */
  .category-cards-grid {
    grid-template-columns: 1fr;
  }

  .category-detail-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }

  .detail-header-stats {
    width: 100%;
    justify-content: flex-start;
  }

  .card-footer {
    flex-direction: column;
    gap: 0.5rem;
  }

  .card-footer-right {
    width: 100%;
    justify-content: flex-end;
  }

}

/* ========== CATEGORY CARDS ========== */
.category-cards-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.category-card {
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.25s ease;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  position: relative;
  overflow: hidden;
}

.category-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  opacity: 0;
  transition: opacity 0.25s ease;
}

.category-card:hover {
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  transform: translateY(-3px);
  border-color: transparent;
}

.category-card:hover::before {
  opacity: 1;
}

.category-card:active {
  transform: translateY(-1px);
}

/* Monthly Card - Blue */
.cc-monthly::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.cc-monthly:hover { box-shadow: 0 8px 24px rgba(59,130,246,0.15); }
.cc-monthly .cc-icon-wrap { background: #eff6ff; color: #3b82f6; }

/* Event/Exam Card - Amber */
.cc-event_based::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.cc-event_based:hover { box-shadow: 0 8px 24px rgba(245,158,11,0.15); }
.cc-event_based .cc-icon-wrap { background: #fffbeb; color: #f59e0b; }

/* One-Time Card - Purple */
.cc-one_time::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.cc-one_time:hover { box-shadow: 0 8px 24px rgba(139,92,246,0.15); }
.cc-one_time .cc-icon-wrap { background: #f5f3ff; color: #8b5cf6; }

.cc-icon-wrap {
  width: 3rem;
  height: 3rem;
  border-radius: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: transform 0.25s ease;
}

.category-card:hover .cc-icon-wrap {
  transform: scale(1.05);
}

.cc-icon {
  width: 1.5rem;
  height: 1.5rem;
}

.cc-body {
  flex: 1;
}

.cc-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.25rem;
}

.cc-desc {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0 0 0.85rem;
  line-height: 1.4;
}

.cc-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
}

.cc-stat {
  text-align: center;
  padding: 0.5rem;
  background: var(--bg-surface-muted);
  border-radius: 0.5rem;
}

.cc-stat-label {
  display: block;
  font-size: 0.65rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  margin-bottom: 0.15rem;
}

.cc-stat-value {
  display: block;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary);
}

.cc-stat-due {
  color: #ef4444 !important;
}

.cc-stat-discount {
  color: #10b981 !important;
}

.cc-arrow {
  position: absolute;
  top: 1.25rem;
  right: 1.25rem;
  width: 1.5rem;
  height: 1.5rem;
  color: var(--text-muted);
  transition: all 0.25s ease;
  opacity: 0.5;
}

.category-card:hover .cc-arrow {
  opacity: 1;
  transform: translateX(3px);
  color: #3b82f6;
}

.cc-arrow svg {
  width: 100%;
  height: 100%;
}

/* Alert badge on category card icon */
.cc-alert-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  width: 1.25rem;
  height: 1.25rem;
  background: #ef4444;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  animation: pulse-alert 2s infinite;
  box-shadow: 0 0 0 3px white;
}

.cc-alert-icon {
  width: 0.7rem;
  height: 0.7rem;
}

@keyframes pulse-alert {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

/* Alert text below description */
.cc-alert-text {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #dc2626;
  background: #fef2f2;
  padding: 0.35rem 0.65rem;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  animation: slideDown 0.3s ease-out;
  line-height: 1.3;
}

.cc-alert-dot {
  width: 0.45rem;
  height: 0.45rem;
  background: #ef4444;
  border-radius: 50%;
  flex-shrink: 0;
  animation: pulse-alert 2s infinite;
}

/* Make icon-wrap position relative for absolute badge positioning */
.cc-icon-wrap {
  position: relative;
}

/* ========== CATEGORY DETAIL HEADER ========== */
.category-detail-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding: 1rem 1.25rem;
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
  animation: slideDown 0.3s ease-out;
}

.detail-header-info {
  flex: 1;
}

.detail-header-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.15rem;
}

.detail-header-subtitle {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0;
}

.detail-header-stats {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-shrink: 0;
}

.detail-stat {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.detail-stat strong {
  color: var(--text-primary);
}

.detail-stat-sep {
  color: #e2e8f0;
}

/* ========== INVOICE BUTTON ========== */
.invoice-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
  border-radius: 0.4rem;
  font-size: 0.7rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.invoice-btn svg {
  width: 0.8rem;
  height: 0.8rem;
}

.invoice-btn:hover {
  background: #d1fae5;
  border-color: #6ee7b7;
}

/* ========== CARD FOOTER LAYOUT ========== */
.card-footer-left {
  flex: 1;
  min-width: 0;
}

.card-footer-right {
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

/* ========== SECTION EMPTY ========== */
.section-empty {
  text-align: center;
  padding: 2rem 1.5rem;
  color: var(--text-muted);
}

.section-empty .empty-icon-sm {
  width: 2rem;
  height: 2rem;
  margin-bottom: 0.5rem;
}

.section-empty p {
  font-size: 0.85rem;
  margin: 0;
}

/* ========== MONTHLY SUMMARY SECTION (same as EnrollmentDetailsPage.vue) ========== */
.monthly-summary-section {
  margin-bottom: 1.5rem;
}

/* Overall Summary Cards */
.overall-summary {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.summary-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  transition: all 0.2s;
}

.summary-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.summary-icon {
  font-size: 1.5rem;
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-surface-muted);
  border-radius: 0.5rem;
  flex-shrink: 0;
}

.summary-details {
  flex: 1;
  min-width: 0;
}

.summary-label {
  display: block;
  font-size: 0.65rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 0.15rem;
}

.summary-value {
  display: block;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
}

.summary-value.discount {
  color: #8b5cf6;
}

.summary-card.total-fee { border-left: 3px solid #3b82f6; }
.summary-card.total-discount { border-left: 3px solid #8b5cf6; }
.summary-card.total-paid { border-left: 3px solid #10b981; }
.summary-card.total-due { border-left: 3px solid #ef4444; }
.summary-card.payment-pct { border-left: 3px solid #f59e0b; }

/* Detail Card (Fee Card) */
.detail-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  overflow: hidden;
  margin-bottom: 1rem;
}

.enrollment-fee-card {
  border: 1px solid var(--border-color);
}

.enrollment-fee-body {
  padding: 1.25rem;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem 1.5rem;
}

.fee-detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.5rem 0.75rem;
  background: var(--bg-surface-muted);
  border-radius: 0.5rem;
}

.fee-detail-item .label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.fee-detail-item .value {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
}

.fee-detail-item .value small {
  font-size: 0.7rem;
  font-weight: 500;
  color: var(--text-muted);
}

.fee-detail-item .value.discount {
  color: #8b5cf6;
}

.fee-detail-item .value.paid {
  color: #10b981;
}

.fee-detail-item .value.due {
  color: #ef4444;
}

/* Month Status Bar */
.month-status-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1.25rem;
  background: var(--bg-surface-muted);
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid var(--border-color);
}

.month-status-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.month-status-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.month-status-value {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-primary);
}

.month-status-value.no-data {
  color: var(--text-muted);
  font-weight: 400;
  font-style: italic;
}

.month-status-divider {
  width: 1px;
  height: 2.5rem;
  background: #e2e8f0;
  flex-shrink: 0;
}

.month-status-badge {
  margin-left: 0.4rem;
  font-size: 0.6rem;
  padding: 0.15rem 0.4rem;
  vertical-align: middle;
}

/* Monthly Records Table */
.monthly-records {
  padding: 1rem 1.25rem;
}

.monthly-records h4 {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-secondary);
  margin: 0 0 0.75rem;
}

.monthly-records-table {
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  overflow: hidden;
}

.monthly-record-header {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 1fr 1fr 80px;
  gap: 0.5rem;
  padding: 0.6rem 0.85rem;
  background: var(--bg-surface-muted);
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border-bottom: 1px solid var(--border-color);
}

.monthly-record-row {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 1fr 1fr 80px;
  gap: 0.5rem;
  padding: 0.6rem 0.85rem;
  font-size: 0.78rem;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border-light);
  align-items: center;
  transition: background 0.15s;
}

.monthly-record-row:last-child {
  border-bottom: none;
}

.monthly-record-row:hover {
  background: var(--bg-surface-muted);
}

.monthly-record-row .discount {
  color: #8b5cf6;
}

.monthly-record-row .paid {
  color: #10b981;
  font-weight: 600;
}

.monthly-record-row .due {
  color: #ef4444;
  font-weight: 600;
}

.monthly-record-row.row-paid {
  opacity: 0.65;
}

.monthly-record-row.row-pending {
  background: #fffbeb;
}

.monthly-record-row.row-overdue {
  background: #fef2f2;
}

.monthly-record-row.row-partial {
  background: #fffbeb;
}

.monthly-record-row.row-next-pay {
  background: #eff6ff;
  border-left: 3px solid #3b82f6;
}

/* Action buttons in monthly records table */
.monthly-record-row .action-btns {
  display: flex;
  gap: 4px;
  align-items: center;
}

.monthly-record-row .btn-pay-mini {
  padding: 3px 8px;
  font-size: 0.7rem;
  border: none;
  border-radius: 4px;
  background: #3b82f6;
  color: #fff;
  cursor: pointer;
  transition: background 0.15s;
  white-space: nowrap;
}

.monthly-record-row .btn-pay-mini:hover {
  background: #2563eb;
}

.monthly-record-row .btn-invoice-mini {
  padding: 3px 6px;
  font-size: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background: var(--bg-card);
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.15s;
  line-height: 1;
}

.monthly-record-row .btn-invoice-mini:hover {
  background: #f3f4f6;
  color: var(--text-secondary);
}

.monthly-record-row .paid-check {
  font-size: 1rem;
  line-height: 1;
}

.next-badge {
  display: inline-block;
  font-size: 0.55rem;
  font-weight: 700;
  color: white;
  background: #3b82f6;
  padding: 0.1rem 0.35rem;
  border-radius: 0.2rem;
  margin-left: 0.3rem;
  vertical-align: middle;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.current-badge {
  display: inline-block;
  font-size: 0.55rem;
  font-weight: 700;
  color: white;
  background: #10b981;
  padding: 0.1rem 0.35rem;
  border-radius: 0.2rem;
  margin-left: 0.3rem;
  vertical-align: middle;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

/* Badge styles */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.5rem;
  border-radius: 1rem;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  white-space: nowrap;
}

.badge-success {
  background: #d1fae5;
  color: #065f46;
}

.badge-warning {
  background: #fef3c7;
  color: #92400e;
}

.badge-info {
  background: #dbeafe;
  color: #1e40af;
}

.badge-danger {
  background: #fee2e2;
  color: #991b1b;
}

.badge-secondary {
  background: var(--bg-accent);
  color: var(--text-muted);
}

/* ========== OPTIONAL MONTHLY FEE TOGGLE (Exam Fee Detail View) ========== */
.monthly-toggle-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 0.75rem;
  margin-top: 1rem;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  transition: box-shadow 0.2s;
}

.monthly-toggle-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.mt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  gap: 1rem;
}

.mt-header-left {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
}

.mt-icon {
  width: 1.5rem;
  height: 1.5rem;
  flex-shrink: 0;
  color: #f59e0b;
  margin-top: 0.1rem;
}

.mt-header-text {
  flex: 1;
  min-width: 0;
}

.mt-title {
  display: block;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.2rem;
}

.mt-desc {
  display: block;
  font-size: 0.75rem;
  color: var(--text-muted);
  line-height: 1.4;
}

/* Toggle Switch */
.mt-toggle {
  position: relative;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  flex-shrink: 0;
}

.mt-toggle input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.mt-toggle-slider {
  position: relative;
  width: 2.5rem;
  height: 1.4rem;
  background: #cbd5e1;
  border-radius: 0.7rem;
  transition: background 0.2s;
}

.mt-toggle-slider::before {
  content: '';
  position: absolute;
  top: 0.15rem;
  left: 0.15rem;
  width: 1.1rem;
  height: 1.1rem;
  background: var(--bg-card);
  border-radius: 50%;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

.mt-toggle input:checked + .mt-toggle-slider {
  background: #3b82f6;
}

.mt-toggle input:checked + .mt-toggle-slider::before {
  transform: translateX(1.1rem);
}

/* Expanded Body */
.mt-body {
  border-top: 1px solid #e2e8f0;
  padding: 1rem 1.25rem;
}

/* Month Highlight Banner */
.mt-month-highlight {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  border: 1px solid #a7f3d0;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
}

.mt-month-highlight-icon {
  width: 2.2rem;
  height: 2.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #10b981;
  border-radius: 50%;
  flex-shrink: 0;
}

.mt-month-highlight-icon svg {
  width: 1.2rem;
  height: 1.2rem;
  color: #ffffff;
  stroke: #ffffff;
}

.mt-month-highlight-info {
  flex: 1;
  min-width: 0;
}

.mt-month-highlight-label {
  display: block;
  font-size: 0.72rem;
  font-weight: 600;
  color: #047857;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 0.1rem;
}

.mt-month-highlight-month {
  display: block;
  font-size: 0.9rem;
  font-weight: 700;
  color: #065f46;
}

.mt-fee-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem;
  background: var(--bg-surface-muted);
  border-radius: 0.5rem;
  gap: 1rem;
}

.mt-fee-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  min-width: 0;
  flex-wrap: wrap;
}

.mt-fee-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.5rem;
  border-radius: 0.3rem;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.mt-fee-badge-monthly {
  background: #dbeafe;
  color: #1d4ed8;
}

.mt-fee-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-primary);
}

.mt-fee-month {
  font-size: 0.75rem;
  color: var(--text-muted);
  background: var(--bg-accent);
  padding: 0.15rem 0.45rem;
  border-radius: 0.25rem;
}

.mt-fee-amounts {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
}

.mt-fee-label {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.mt-fee-value {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
}

/* Summary */
.mt-summary {
  margin-top: 0.75rem;
  padding: 0.75rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 0.5rem;
}

.mt-summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
  color: var(--text-secondary);
  padding: 0.2rem 0;
}

.mt-summary-divider {
  height: 1px;
  background: #fde68a;
  margin: 0.35rem 0;
}

.mt-summary-total {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
}

/* Note */
.mt-note {
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 0.75rem 0 0 0;
  line-height: 1.4;
}

.mt-note-icon {
  width: 0.85rem;
  height: 0.85rem;
  flex-shrink: 0;
  margin-top: 0.1rem;
  color: var(--text-muted);
}

/* Expand transition */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
}
</style>