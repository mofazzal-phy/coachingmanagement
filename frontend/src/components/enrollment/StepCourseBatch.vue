<template>
  <div class="step-card">
    <h2>📖 Course & Batch</h2>

    <CourseSelectCard
      v-if="!selectedCourse"
      :class-id="null"
      @course-selected="onCourse"
    />

    <template v-if="selectedCourse">
      <div class="selected-course-banner">
        ✓ Selected: <strong>{{ selectedCourse.name }}</strong>
        <button class="btn-link" @click="selectedCourse = null; selectedBatch = null">Change</button>
      </div>

      <BatchSelectCard
        v-if="!selectedBatch"
        :course-id="selectedCourse.id"
        @batch-selected="onBatch"
        @waitlist-requested="onWaitlist"
      />

      <template v-if="selectedBatch">
        <div class="selected-course-banner">
          ✓ Batch: <strong>{{ selectedBatch.name }}</strong> ({{ selectedBatch.mode }})
          <button class="btn-link" @click="selectedBatch = null">Change</button>
        </div>

        <FeeBreakdownCard
          :course-id="selectedCourse.id"
          :student-id="null"
          @fee-calculated="fee = $event"
        />
      </template>
    </template>

    <div class="step-actions" v-if="selectedBatch">
      <button class="btn btn-primary" @click="save">Continue →</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import CourseSelectCard from '@/components/enrollment/CourseSelectCard.vue'
import BatchSelectCard from '@/components/enrollment/BatchSelectCard.vue'
import FeeBreakdownCard from '@/components/enrollment/FeeBreakdownCard.vue'

const emit = defineEmits(['next'])
const selectedCourse = ref(null)
const selectedBatch = ref(null)
const fee = ref(null)

const onCourse = (c) => { selectedCourse.value = c }
const onBatch = (b) => { selectedBatch.value = b }
const onWaitlist = () => { alert('Added to waiting list!') }

const save = () => {
  emit('next', {
    course_id: selectedCourse.value?.id,
    batch_id: selectedBatch.value?.id,
    subject_ids: selectedCourse.value?.subjects?.filter(s => s.is_mandatory).map(s => s.id) || [],
  })
}
</script>

<style scoped>
.step-card { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.step-card h2 { margin: 0 0 1rem 0; font-size: 1.15rem; }

.selected-course-banner {
  background: #eafaf1; border: 1px solid #a3e4b8; border-radius: 10px;
  padding: 0.6rem 1rem; margin-bottom: 1rem; font-size: 0.9rem;
  display: flex; align-items: center; gap: 0.5rem;
}
.btn-link { color: #e74c3c; background: none; border: none; cursor: pointer; font-size: 0.8rem; text-decoration: underline; margin-left: auto; }

.step-actions { text-align: right; margin-top: 1rem; }
.btn-primary { background: #4a90d9; color: #fff; border: none; padding: 0.7rem 1.5rem; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
</style>
