<template>
  <section class="module-page">
    <h1>Student Management</h1>
    <form class="form" @submit.prevent="createStudent">
      <input v-model="form.full_name" placeholder="Student Name" required />
      <input v-model="form.email" placeholder="Email" type="email" required />
      <button type="submit">Add Student</button>
    </form>

    <ul>
      <li v-for="item in students" :key="item.id">
        <span>{{ item.full_name }} - {{ item.email }}</span>
        <button @click="deleteStudent(item.id)">Delete</button>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import studentService from '@/services/student.service'

const students = ref([])
const form = reactive({
  full_name: '',
  email: '',
})

const fetchStudents = async () => {
  const { data } = await studentService.list({ per_page: 50 })
  students.value = data.data || []
}

const createStudent = async () => {
  await studentService.create(form)
  form.full_name = ''
  form.email = ''
  await fetchStudents()
}

const deleteStudent = async (id) => {
  await studentService.remove(id)
  await fetchStudents()
}

onMounted(fetchStudents)
</script>

<style scoped>
.module-page { background: var(--bg-card); padding: 1rem; border-radius: 12px; }
.form { display: flex; gap: 0.5rem; margin: 1rem 0; }
input { padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; }
button { padding: 0.55rem 0.85rem; border: none; border-radius: 8px; background: #2563eb; color: #fff; cursor: pointer; }
ul { list-style: none; display: grid; gap: 0.45rem; }
li { display: flex; justify-content: space-between; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; }
</style>
