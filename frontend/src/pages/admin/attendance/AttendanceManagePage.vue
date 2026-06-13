<template>
  <section class="module-page">
    <h1>Attendance Management</h1>
    <form class="form" @submit.prevent="createAttendance">
      <input v-model="form.student_name" placeholder="Student Name" required />
      <input v-model="form.course_name" placeholder="Course Name" />
      <input v-model="form.attendance_date" type="date" required />
      <select v-model="form.status">
        <option value="present">Present</option>
        <option value="absent">Absent</option>
        <option value="late">Late</option>
      </select>
      <button type="submit">Save</button>
    </form>

    <ul>
      <li v-for="item in attendances" :key="item.id">
        <span>{{ item.student_name }} - {{ item.status }} ({{ item.attendance_date }})</span>
        <button @click="deleteAttendance(item.id)">Delete</button>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import attendanceService from '@/services/attendance.service'

const attendances = ref([])
const form = reactive({
  student_name: '',
  course_name: '',
  attendance_date: '',
  status: 'present',
})

const fetchAttendance = async () => {
  const { data } = await attendanceService.list({ per_page: 50 })
  attendances.value = data.data || []
}

const createAttendance = async () => {
  await attendanceService.create(form)
  form.student_name = ''
  form.course_name = ''
  form.attendance_date = ''
  form.status = 'present'
  await fetchAttendance()
}

const deleteAttendance = async (id) => {
  await attendanceService.remove(id)
  await fetchAttendance()
}

onMounted(fetchAttendance)
</script>

<style scoped>
.module-page { background: var(--bg-card); padding: 1rem; border-radius: 12px; }
.form { display: flex; gap: 0.5rem; margin: 1rem 0; flex-wrap: wrap; }
input, select { padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; }
button { padding: 0.55rem 0.85rem; border: none; border-radius: 8px; background: #2563eb; color: #fff; cursor: pointer; }
ul { list-style: none; display: grid; gap: 0.45rem; }
li { display: flex; justify-content: space-between; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; }
</style>
