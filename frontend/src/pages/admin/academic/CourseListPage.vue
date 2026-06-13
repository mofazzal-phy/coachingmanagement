<template>
  <section class="module-page">
    <h1>Academic Management</h1>
    <form class="form" @submit.prevent="createCourse">
      <input v-model="form.course_name" placeholder="Course Name" required />
      <input v-model="form.course_code" placeholder="Course Code" required />
      <input v-model="form.batch_name" placeholder="Batch Name" required />
      <button type="submit">Add Course</button>
    </form>

    <ul>
      <li v-for="item in courses" :key="item.id">
        <span>{{ item.course_name }} ({{ item.course_code }}) - {{ item.batch_name }}</span>
        <button @click="deleteCourse(item.id)">Delete</button>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import academicService from '@/services/academic.service'

const courses = ref([])
const form = reactive({
  course_name: '',
  course_code: '',
  batch_name: '',
})

const fetchCourses = async () => {
  const { data } = await academicService.list({ per_page: 50 })
  courses.value = data.data || []
}

const createCourse = async () => {
  await academicService.create(form)
  form.course_name = ''
  form.course_code = ''
  form.batch_name = ''
  await fetchCourses()
}

const deleteCourse = async (id) => {
  await academicService.remove(id)
  await fetchCourses()
}

onMounted(fetchCourses)
</script>

<style scoped>
.module-page { background: var(--bg-card); padding: 1rem; border-radius: 12px; }
.form { display: flex; gap: 0.5rem; margin: 1rem 0; flex-wrap: wrap; }
input { padding: 0.55rem; border: 1px solid var(--border-color); border-radius: 8px; }
button { padding: 0.55rem 0.85rem; border: none; border-radius: 8px; background: #2563eb; color: #fff; cursor: pointer; }
ul { list-style: none; display: grid; gap: 0.45rem; }
li { display: flex; justify-content: space-between; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; }
</style>
