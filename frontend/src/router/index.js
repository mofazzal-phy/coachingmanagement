import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'

const LoginPage = () => import('@/pages/auth/LoginPage.vue')
const DashboardPage = () => import('@/pages/dashboard/DashboardPage.vue')
const DashboardLayout = () => import('@/layouts/DashboardLayout.vue')

const routes = [
  { path: '/login', name: 'Login', component: LoginPage, meta: { guest: true } },
  { path: '/', redirect: '/site' },
  { path: '/enroll', redirect: '/site/enroll' },
  {
    path: '/site',
    component: () => import('@/layouts/PublicSiteLayout.vue'),
    children: [
      { path: '', name: 'MarketingHome', component: () => import('@/pages/public/MarketingHomePage.vue') },

      // Admission
      { path: 'courses', name: 'PublicCourses', component: () => import('@/pages/public/PublicCoursesPage.vue') },
      { path: 'courses/:id', name: 'PublicCourseDetail', component: () => import('@/pages/public/PublicCourseDetailPage.vue') },
      { path: 'batches', name: 'PublicBatches', component: () => import('@/pages/public/PublicBatchesPage.vue') },
      { path: 'enroll', name: 'PublicEnroll', component: () => import('@/pages/public/PublicEnrollPage.vue') },
      { path: 'track', name: 'PublicTrack', component: () => import('@/pages/public/PublicTrackPage.vue') },

      // People & about
      { path: 'about', name: 'PublicAbout', component: () => import('@/pages/public/PublicAboutPage.vue') },
      { path: 'teachers', name: 'PublicTeachers', component: () => import('@/pages/public/PublicTeachersPage.vue') },
      { path: 'teachers/:id', name: 'PublicTeacherDetail', component: () => import('@/pages/public/PublicTeacherDetailPage.vue') },
      { path: 'contact', name: 'PublicContact', component: () => import('@/pages/public/PublicContactPage.vue') },

      // Content
      { path: 'blog', name: 'PublicBlog', component: () => import('@/pages/public/PublicBlogPage.vue') },
      { path: 'blog/:slug', name: 'PublicBlogDetail', component: () => import('@/pages/public/PublicBlogDetailPage.vue') },
      { path: 'notices', name: 'PublicNotices', component: () => import('@/pages/public/PublicNoticesPage.vue') },
      { path: 'events', name: 'PublicEvents', component: () => import('@/pages/public/PublicEventsPage.vue') },
      { path: 'gallery', name: 'PublicGallery', component: () => import('@/pages/public/PublicGalleryPage.vue') },
      { path: 'success-stories', name: 'PublicSuccessStories', component: () => import('@/pages/public/PublicSuccessStoriesPage.vue') },
      { path: 'success-stories/:slug', name: 'PublicSuccessStory', component: () => import('@/pages/public/PublicSuccessStoryPage.vue') },
      { path: 'downloads', name: 'PublicDownloads', component: () => import('@/pages/public/PublicDownloadsPage.vue') },
      { path: 'pages/:slug', name: 'PublicCmsPage', component: () => import('@/pages/public/PublicCmsPage.vue') },
    ],
  },
  {
    path: '/dashboard',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'Dashboard', component: DashboardPage },
      
      // Users & Auth
      { path: 'users', name: 'UserList', component: () => import('@/pages/dashboard/users/UserListPage.vue'), meta: { permission: 'view users' } },
      { path: 'users/create', name: 'UserCreate', component: () => import('@/pages/dashboard/users/UserCreatePage.vue'), meta: { permission: 'create users' } },
      { path: 'users/:id/edit', name: 'UserEdit', component: () => import('@/pages/dashboard/users/UserEditPage.vue'), meta: { permission: 'edit users' } },
      { path: 'roles', name: 'RoleList', component: () => import('@/pages/dashboard/users/RoleListPage.vue'), meta: { permission: 'view roles' } },
      { path: 'permissions', name: 'PermissionList', component: () => import('@/pages/dashboard/users/PermissionListPage.vue'), meta: { permission: 'view permissions' } },
      
      // Students
      { path: 'students', name: 'StudentList', component: () => import('@/pages/dashboard/students/StudentListPage.vue'), meta: { permission: 'view students' } },
      { path: 'students/create', name: 'StudentCreate', component: () => import('@/pages/dashboard/students/StudentCreatePage.vue'), meta: { permission: 'create students' } },
      { path: 'students/:id', name: 'StudentDetails', component: () => import('@/pages/dashboard/students/StudentDetailsPage.vue'), meta: { permission: 'view students' } },
      { path: 'students/:id/edit', name: 'StudentEdit', component: () => import('@/pages/dashboard/students/StudentEditPage.vue'), meta: { permission: 'edit students' } },
      { path: 'student-leaves', name: 'StudentLeaveList', component: () => import('@/pages/dashboard/students/StudentLeaveListPage.vue'), meta: { permission: 'view students' } },
      { path: 'guardians', name: 'GuardianList', component: () => import('@/pages/dashboard/guardians/GuardianListPage.vue'), meta: { permission: 'view guardians' } },
      { path: 'admissions', name: 'AdmissionList', component: () => import('@/pages/dashboard/admissions/AdmissionListPage.vue'), meta: { permission: 'view admissions' } },
      
      // Academic
      { path: 'academic/sessions', name: 'AcademicSessionList', component: () => import('@/pages/dashboard/academic/AcademicSessionListPage.vue'), meta: { permission: 'view academic sessions' } },
      { path: 'academic/classes', name: 'ClassList', component: () => import('@/pages/dashboard/academic/ClassListPage.vue'), meta: { permission: 'view classes' } },
      { path: 'academic/sections', name: 'SectionList', component: () => import('@/pages/dashboard/academic/SectionListPage.vue'), meta: { permission: 'view sections' } },
      { path: 'academic/subjects', name: 'SubjectList', component: () => import('@/pages/dashboard/academic/SubjectListPage.vue'), meta: { permission: 'view subjects' } },
      { path: 'academic/groups', name: 'GroupList', component: () => import('@/pages/dashboard/academic/GroupListPage.vue'), meta: { permission: 'view academic groups' } },
      { path: 'academic/groups/create', name: 'GroupCreate', component: () => import('@/pages/dashboard/academic/GroupCreatePage.vue'), meta: { permission: 'create academic groups' } },
      { path: 'academic/groups/:id/edit', name: 'GroupEdit', component: () => import('@/pages/dashboard/academic/GroupEditPage.vue'), meta: { permission: 'edit academic groups' } },
      { path: 'academic/rooms', name: 'RoomList', component: () => import('@/pages/dashboard/academic/RoomListPage.vue'), meta: { permission: 'view rooms' } },
      { path: 'academic/periods', name: 'PeriodList', component: () => import('@/pages/dashboard/academic/PeriodListPage.vue'), meta: { permission: 'view routine periods' } },
      { path: 'academic/routine', name: 'RoutineList', component: () => import('@/pages/dashboard/academic/RoutineManagementPage.vue'), meta: { permission: 'view class routines' } },
      { path: 'academic/routine-exceptions', name: 'RoutineExceptionList', component: () => import('@/pages/dashboard/academic/RoutineExceptionListPage.vue'), meta: { permission: 'view routine exceptions' } },
       
      // Teachers
      { path: 'teachers', name: 'TeacherList', component: () => import('@/pages/dashboard/teachers/TeacherListPage.vue'), meta: { permission: 'view teachers' } },
      { path: 'teachers/create', name: 'TeacherCreate', component: () => import('@/pages/dashboard/teachers/TeacherCreatePage.vue'), meta: { permission: 'create teachers' } },
      { path: 'teachers/:id/edit', name: 'TeacherEdit', component: () => import('@/pages/dashboard/teachers/TeacherEditPage.vue'), meta: { permission: 'edit teachers' } },
      { path: 'teachers/:id', name: 'TeacherDetails', component: () => import('@/pages/dashboard/teachers/TeacherDetailsPage.vue'), meta: { permission: 'view teachers' } },
      { path: 'teacher/my-schedule', name: 'TeacherSchedule', component: () => import('@/pages/dashboard/teachers/MySchedulePage.vue'), meta: { permission: 'view my schedule' } },
      { path: 'teacher/my-exam-routines', name: 'TeacherMyExamRoutines', component: () => import('@/pages/dashboard/teachers/TeacherMyExamRoutinesPage.vue'), meta: { permission: 'view exam routines' } },
      { path: 'teacher/exam-marks', name: 'TeacherExamMarks', component: () => import('@/pages/dashboard/teachers/TeacherExamMarksPage.vue'), meta: { permission: 'create exam results' } },
      { path: 'teacher/exam-leaderboard', name: 'TeacherExamLeaderboard', component: () => import('@/pages/dashboard/teachers/TeacherExamLeaderboardPage.vue'), meta: { permission: 'view exam results' } },
      { path: 'teacher/questions', name: 'TeacherQuestions', component: () => import('@/pages/dashboard/teachers/TeacherQuestionsPage.vue'), meta: { permission: 'view questions', teacherMode: true } },
      { path: 'teacher/questions/create/mcq', name: 'TeacherQuestionBulkMcq', component: () => import('@/pages/dashboard/exams/QuestionBulkCreatePage.vue'), meta: { permission: 'create questions', teacherMode: true, bulkType: 'mcq' } },
      { path: 'teacher/questions/create/cq', name: 'TeacherQuestionBulkCq', component: () => import('@/pages/dashboard/exams/QuestionBulkCreatePage.vue'), meta: { permission: 'create questions', teacherMode: true, bulkType: 'cq' } },
      { path: 'teacher/exam-duties', name: 'TeacherExamDuties', component: () => import('@/pages/dashboard/teachers/TeacherExamRoutinePage.vue'), meta: { permission: 'view exam routines' } },
      { path: 'my-attendance', name: 'MyAttendance', component: () => import('@/pages/dashboard/attendance/MyAttendancePage.vue'), meta: { permission: 'view attendance' } },
      
      // Enrollment
      { path: 'enrollment/courses', name: 'CourseList', component: () => import('@/pages/dashboard/enrollment/CourseListPage.vue'), meta: { permission: 'view courses' } },
      { path: 'enrollment/courses/create', name: 'CourseCreate', component: () => import('@/pages/dashboard/enrollment/CourseCreatePage.vue'), meta: { permission: 'create courses' } },
      { path: 'enrollment/courses/:id', name: 'CourseDetails', component: () => import('@/pages/dashboard/enrollment/CourseDetailsPage.vue'), meta: { permission: 'view courses' } },
      { path: 'enrollment/courses/:id/edit', name: 'CourseEdit', component: () => import('@/pages/dashboard/enrollment/CourseCreatePage.vue'), meta: { permission: 'edit courses' } },
      { path: 'enrollment/batches', name: 'BatchList', component: () => import('@/pages/dashboard/enrollment/BatchListPage.vue'), meta: { permission: 'view batches' } },
      { path: 'enrollment/batches/create', name: 'BatchCreate', component: () => import('@/pages/dashboard/enrollment/BatchCreatePage.vue'), meta: { permission: 'create batches' } },
      { path: 'enrollment/batches/:id', name: 'BatchDetails', component: () => import('@/pages/dashboard/enrollment/BatchDetailsPage.vue'), meta: { permission: 'view batches' } },
      { path: 'enrollment/batches/:id/edit', name: 'BatchEdit', component: () => import('@/pages/dashboard/enrollment/BatchCreatePage.vue'), meta: { permission: 'edit batches' } },
      { path: 'enrollment/enrollments', name: 'EnrollmentList', component: () => import('@/pages/dashboard/enrollment/EnrollmentListPage.vue'), meta: { permission: 'view enrollments' } },
      { path: 'enrollment/enrollments/create', name: 'EnrollmentCreate', component: () => import('@/pages/dashboard/enrollment/EnrollmentWizard.vue'), meta: { permission: 'create enrollments' } },
      { path: 'enrollment/enrollments/:id', name: 'EnrollmentDetails', component: () => import('@/pages/dashboard/enrollment/EnrollmentDetailsPage.vue'), meta: { permission: 'view enrollments' } },
      { path: 'enrollment/enrollments/:id/edit', name: 'EnrollmentEdit', component: () => import('@/pages/dashboard/enrollment/EnrollmentWizard.vue'), meta: { permission: 'edit enrollments' } },
      { path: 'enrollment/enrollments/pending/list', name: 'PendingEnrollments', component: () => import('@/pages/dashboard/enrollment/PendingEnrollmentsPage.vue'), meta: { permission: 'view enrollments' } },
      { path: 'enrollment/waiting-list', name: 'WaitingList', component: () => import('@/pages/dashboard/enrollment/WaitingListPage.vue'), meta: { permission: 'view enrollments' } },
      { path: 'enrollment/reports', name: 'EnrollmentReports', component: () => import('@/pages/dashboard/enrollment/ReportsDashboard.vue'), meta: { permission: 'view reports' } },
      { path: 'enrollment/activity-logs', name: 'ActivityLogs', component: () => import('@/pages/dashboard/enrollment/ActivityLogPage.vue'), meta: { permission: 'view reports' } },

      // Attendance
      { path: 'attendance', redirect: { name: 'DashboardStudentAttendance' } },
      { path: 'attendance/dashboard', name: 'AttendanceDashboard', component: () => import('@/pages/dashboard/attendance/AttendanceDashboardPage.vue'), meta: { permission: 'view attendance dashboard' } },
      { path: 'attendance/legacy', name: 'AttendanceLegacy', component: () => import('@/pages/dashboard/attendance/AttendancePage.vue'), meta: { permission: 'view attendance' } },
      { path: 'attendance/students', name: 'DashboardStudentAttendance', component: () => import('@/pages/dashboard/attendance/StudentAttendancePage.vue'), meta: { permission: 'view student attendance' } },
      { path: 'attendance/teachers', name: 'TeacherAttendance', component: () => import('@/pages/dashboard/attendance/TeacherAttendancePage.vue'), meta: { permission: 'view teacher attendance' } },
      { path: 'attendance/employees', name: 'EmployeeAttendance', component: () => import('@/pages/dashboard/attendance/EmployeeAttendancePage.vue'), meta: { permission: 'view employee attendance' } },
      { path: 'attendance/sessions', name: 'AttendanceSessions', component: () => import('@/pages/dashboard/attendance/AttendanceSessionPage.vue'), meta: { permission: 'view attendance sessions' } },
      { path: 'attendance/devices', name: 'BiometricDevices', component: () => import('@/pages/dashboard/attendance/BiometricDevicesPage.vue'), meta: { permission: 'view biometric devices' } },
      { path: 'attendance/reports', name: 'AttendanceReports', component: () => import('@/pages/dashboard/attendance/AttendanceReportsPage.vue'), meta: { permission: 'view attendance reports' } },
      { path: 'attendance/simulator', name: 'DeviceSimulator', component: () => import('@/pages/dashboard/attendance/DeviceSimulatorPage.vue'), meta: { permission: 'view device simulator' } },
      
      // Exams
      { path: 'exams', name: 'ExamList', component: () => import('@/pages/dashboard/exams/ExamListPage.vue'), meta: { permission: 'view exams' } },
      { path: 'exams/wizard', name: 'ExamSetupWizard', component: () => import('@/pages/dashboard/exams/ExamSetupWizard.vue'), meta: { permission: 'create exams' } },
      { path: 'exams/types', name: 'ExamTypeList', component: () => import('@/pages/dashboard/exams/ExamTypeListPage.vue'), meta: { permission: 'view exam types' } },
      { path: 'exams/routines', name: 'ExamRoutineList', component: () => import('@/pages/dashboard/exams/ExamRoutinePage.vue'), meta: { permission: 'view exam routines' } },
      { path: 'exams/results', name: 'ExamResultList', component: () => import('@/pages/dashboard/exams/ExamResultPage.vue'), meta: { permission: 'view exam results' } },
      { path: 'exams/leaderboard', name: 'ExamLeaderboard', component: () => import('@/pages/dashboard/exams/ExamLeaderboardPage.vue'), meta: { permission: 'view exam results' } },
      { path: 'exams/analytics', name: 'ExamAnalytics', component: () => import('@/pages/dashboard/exams/ExamAnalyticsPage.vue'), meta: { permission: 'view reports' } },
      { path: 'exams/grading-scale', name: 'ExamGradingScale', component: () => import('@/pages/dashboard/exams/ExamGradingScalePage.vue'), meta: { permission: 'view settings' } },
      { path: 'exams/questions', name: 'QuestionBank', component: () => import('@/pages/dashboard/exams/QuestionBankPage.vue'), meta: { permission: 'view questions' } },
      { path: 'exams/questions/create/mcq', name: 'QuestionBulkMcq', component: () => import('@/pages/dashboard/exams/QuestionBulkCreatePage.vue'), meta: { permission: 'create questions', bulkType: 'mcq' } },
      { path: 'exams/questions/create/cq', name: 'QuestionBulkCq', component: () => import('@/pages/dashboard/exams/QuestionBulkCreatePage.vue'), meta: { permission: 'create questions', bulkType: 'cq' } },
      { path: 'exams/questions/review', name: 'QuestionReviewQueue', component: () => import('@/pages/dashboard/exams/QuestionReviewQueuePage.vue'), meta: { permission: 'approve questions' } },
      
      // Finance
      { path: 'finance/fee-types', name: 'FeeTypeList', component: () => import('@/pages/dashboard/finance/FeeTypeListPage.vue'), meta: { permission: 'view fee types' } },
      { path: 'finance/fee-structures', name: 'FeeStructureList', component: () => import('@/pages/dashboard/finance/FeeStructureListPage.vue'), meta: { permission: 'view fee structures' } },
      { path: 'finance/collect-fee', name: 'CollectFee', component: () => import('@/pages/dashboard/finance/CollectFeePage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'finance/collections', name: 'FeeCollectionList', component: () => import('@/pages/dashboard/finance/FeeCollectionPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'finance/expenses', name: 'ExpenseList', component: () => import('@/pages/dashboard/finance/ExpenseListPage.vue'), meta: { permission: 'view expenses' } },
      { path: 'finance/payment-management', name: 'PaymentManagement', component: () => import('@/pages/dashboard/finance/PaymentManagementPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'finance/exam-fee-collection', name: 'ExamFeeCollection', component: () => import('@/pages/dashboard/finance/ExamFeeCollectionPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'finance/invoices', name: 'InvoiceList', component: () => import('@/pages/dashboard/finance/InvoiceListPage.vue'), meta: { permission: 'view fee collections' } },
      
      // HR
      { path: 'hr/employees', name: 'EmployeeList', component: () => import('@/pages/dashboard/hr/EmployeeListPage.vue'), meta: { permission: 'view employees' } },
      { path: 'hr/departments', name: 'DepartmentList', component: () => import('@/pages/dashboard/hr/DepartmentListPage.vue'), meta: { permission: 'view departments' } },
      { path: 'hr/designations', name: 'DesignationList', component: () => import('@/pages/dashboard/hr/DesignationListPage.vue'), meta: { permission: 'view designations' } },
      { path: 'hr/staff-attendance', name: 'StaffAttendance', component: () => import('@/pages/dashboard/hr/StaffAttendancePage.vue'), meta: { permission: 'view staff attendance' } },
      { path: 'hr/leave-requests', name: 'LeaveRequestList', component: () => import('@/pages/dashboard/hr/LeaveRequestPage.vue'), meta: { permission: 'view leave requests' } },
      { path: 'hr/payroll', name: 'PayrollList', component: () => import('@/pages/dashboard/hr/PayrollPage.vue'), meta: { permission: 'view payroll' } },
      
      // Communication
      { path: 'communication/notice-board', name: 'NoticeBoard', component: () => import('@/pages/dashboard/communication/NoticeBoardPage.vue'), meta: { permission: 'view notice board' } },
      { path: 'communication/notifications', name: 'NotificationList', component: () => import('@/pages/dashboard/communication/NotificationPage.vue'), meta: { permission: 'view notifications' } },
      
      // CMS
      { path: 'cms/pages', name: 'CmsPageList', component: () => import('@/pages/dashboard/cms/PageListPage.vue'), meta: { permission: 'view cms pages' } },
      { path: 'cms/blog', name: 'CmsBlogList', component: () => import('@/pages/dashboard/cms/BlogListPage.vue'), meta: { permission: 'view cms pages' } },
      { path: 'cms/sliders', name: 'SliderList', component: () => import('@/pages/dashboard/cms/SliderListPage.vue'), meta: { permission: 'view sliders' } },
      { path: 'cms/events', name: 'EventList', component: () => import('@/pages/dashboard/cms/EventListPage.vue'), meta: { permission: 'view events' } },
      { path: 'cms/gallery', name: 'GalleryList', component: () => import('@/pages/dashboard/cms/GalleryListPage.vue'), meta: { permission: 'view gallery' } },
      { path: 'cms/testimonials', name: 'TestimonialList', component: () => import('@/pages/dashboard/cms/TestimonialListPage.vue'), meta: { permission: 'view testimonials' } },
      { path: 'cms/success-stories', name: 'SuccessStoryList', component: () => import('@/pages/dashboard/cms/SuccessStoryListPage.vue'), meta: { permission: 'view success stories' } },
      { path: 'cms/study-materials', name: 'StudyMaterialList', component: () => import('@/pages/dashboard/cms/StudyMaterialListPage.vue'), meta: { permission: 'view study materials' } },
      { path: 'cms/download-center', name: 'DownloadCenter', component: () => import('@/pages/dashboard/cms/DownloadCenterPage.vue'), meta: { permission: 'view download center' } },
      { path: 'cms/approval-queue', name: 'CmsApprovalQueue', component: () => import('@/pages/dashboard/cms/CmsApprovalQueuePage.vue'), meta: { permission: 'approve cms content' } },
      { path: 'cms/audit-logs', name: 'CmsAuditLogs', component: () => import('@/pages/dashboard/cms/CmsAuditLogPage.vue'), meta: { permission: 'view cms audit logs' } },
      { path: 'cms/analytics', name: 'CmsAnalytics', component: () => import('@/pages/dashboard/cms/CmsAnalyticsPage.vue'), meta: { permission: 'view cms analytics' } },
      
      // Reports
      { path: 'reports', name: 'Reports', component: () => import('@/pages/dashboard/reports/ReportPage.vue'), meta: { permission: 'view reports' } },
      
      // Settings
      { path: 'settings', name: 'Settings', component: () => import('@/pages/dashboard/settings/SettingsPage.vue'), meta: { permission: 'view settings' } },
    ],
  },
  {
    path: '/teacher',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'TeacherDashboard', component: () => import('@/pages/teacher/TeacherDashboardPage.vue') },
    ],
  },
  {
    path: '/guardian',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'GuardianDashboard', component: () => import('@/pages/guardian/GuardianDashboardPage.vue') },
      { path: 'children', name: 'GuardianChildren', component: () => import('@/pages/guardian/GuardianChildrenPage.vue'), meta: { permission: 'view students' } },
      { path: 'children/:id', name: 'GuardianChildDetail', component: () => import('@/pages/guardian/GuardianChildDetailPage.vue'), meta: { permission: 'view students' } },
      { path: 'notices', name: 'GuardianNotices', component: () => import('@/pages/guardian/GuardianNoticesPage.vue'), meta: { permission: 'view notice board' } },
    ],
  },
  {
    path: '/employee',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'EmployeeDashboard', component: () => import('@/pages/employee/EmployeeDashboardPage.vue') },
      { path: 'notices', name: 'EmployeeNotices', component: () => import('@/pages/employee/EmployeeNoticesPage.vue'), meta: { permission: 'view notice board' } },
    ],
  },
  {
    path: '/student',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      // Dashboard
      { path: '', name: 'StudentDashboard', component: () => import('@/pages/student/StudentDashboardPage.vue') },
      
      // Fee Management
      { path: 'fee-dashboard', name: 'StudentFeeDashboard', component: () => import('@/pages/student/StudentFeeDashboardPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'fee-ledger', name: 'StudentFeeLedger', component: () => import('@/pages/student/StudentFeeLedgerPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'fee-ledger/:enrollmentId', name: 'StudentFeeLedgerDetail', component: () => import('@/pages/student/StudentFeeLedgerPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'fee-payment', name: 'StudentFeePayment', component: () => import('@/pages/student/StudentFeePaymentPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'fee-payment/:enrollmentId', name: 'StudentFeePaymentDetail', component: () => import('@/pages/student/StudentFeePaymentPage.vue'), meta: { permission: 'view fee collections' } },
      { path: 'fee-notifications', name: 'StudentFeeNotifications', component: () => import('@/pages/student/StudentFeeNotificationsPage.vue'), meta: { permission: 'view fee collections' } },
      
      // Exams
      { path: 'exams', name: 'StudentExams', component: () => import('@/pages/student/StudentExamListPage.vue'), meta: { permission: 'view exams' } },
      { path: 'exams/:examId/admit-card', name: 'StudentExamAdmit', component: () => import('@/pages/student/StudentExamAdmitPage.vue'), meta: { permission: 'view exams' } },
      { path: 'practice', name: 'StudentPractice', component: () => import('@/pages/student/StudentPracticeCenterPage.vue'), meta: { permission: 'view exams' } },
      { path: 'exams/live/:routineId', name: 'StudentLiveExam', component: () => import('@/pages/student/OnlineExamPlayerPage.vue'), meta: { permission: 'view exams' } },
      { path: 'exam-routines', name: 'StudentExamRoutines', component: () => import('@/pages/student/StudentExamRoutinesPage.vue'), meta: { permission: 'view exam routines' } },
      { path: 'exam-results', name: 'StudentExamResults', component: () => import('@/pages/student/StudentExamResultsPage.vue'), meta: { permission: 'view exam results' } },
      
      // Class Routine
      { path: 'class-routine', name: 'StudentClassRoutine', component: () => import('@/pages/student/StudentClassRoutinePage.vue'), meta: { permission: 'view class routines' } },
      
      // Attendance
      { path: 'attendance', name: 'StudentAttendance', component: () => import('@/pages/student/StudentAttendancePage.vue'), meta: { permission: 'view attendance' } },
      
      // Notices
      { path: 'notices', name: 'StudentNotices', component: () => import('@/pages/student/StudentNoticesPage.vue'), meta: { permission: 'view notice board' } },

      // Study Materials & Downloads
      { path: 'study-materials', name: 'StudentStudyMaterials', component: () => import('@/pages/student/StudentStudyMaterialsPage.vue'), meta: { permission: 'view study materials' } },
      { path: 'downloads', name: 'StudentDownloads', component: () => import('@/pages/student/StudentDownloadsPage.vue'), meta: { permission: 'view download center' } },
      
      // Leave
      { path: 'leave-apply', name: 'StudentLeaveApply', component: () => import('@/pages/student/StudentLeaveApplyPage.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/site' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0 }
  },
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  console.log('[Router Guard] === NAVIGATION ===')
  console.log('[Router Guard] From:', from.path, 'To:', to.path)
  console.log('[Router Guard] Route name:', to.name)
  console.log('[Router Guard] Meta:', JSON.stringify(to.meta))
  console.log('[Router Guard] Token exists:', !!authStore.token)
  console.log('[Router Guard] User:', authStore.user ? `${authStore.user.name} (${authStore.user.role})` : 'null')

  // If route requires auth but no token, redirect to login
  if (to.meta.requiresAuth && !authStore.token) {
    console.log('[Router Guard] No token, redirecting to login')
    return next({ name: 'Login' })
  }

  // If route is for guests only (like login) but user is already logged in, redirect to dashboard
    if (to.meta.guest && authStore.token) {
    console.log('[Router Guard] Already logged in, redirecting to dashboard')
    return next(authStore.dashboardPath)
  }

  // If user has a token but no user data loaded yet, fetch it
  if (to.meta.requiresAuth && authStore.token && !authStore.user) {
    try {
      console.log('[Router Guard] Fetching user data...')
      await authStore.fetchUser()
      console.log('[Router Guard] User fetched:', authStore.user?.name, authStore.user?.role)
    } catch (error) {
      console.error('[Router Guard] Failed to fetch user:', error)
      authStore.logout()
      return next({ name: 'Login' })
    }
  }

  // Check permission if route requires one
  if (to.meta.permission && authStore.user) {
    const hasPerm = authStore.hasPermission(to.meta.permission)
    console.log(`[Router Guard] Permission check: "${to.meta.permission}" → ${hasPerm}`)
    if (!hasPerm) {
      console.log('[Router Guard] Permission denied, redirecting to dashboard')
      const roleFallbacks = { student: '/student', teacher: '/teacher', guardian: '/guardian', employee: '/employee' }
      const fallback = roleFallbacks[authStore.userRole] || authStore.dashboardPath
      return next(fallback)
    }
  }

  next()
})

export default router
