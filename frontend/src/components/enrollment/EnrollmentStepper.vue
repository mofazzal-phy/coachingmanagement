<template>
  <div class="enrollment-stepper">
    <div class="stepper-track">
      <div
        v-for="(step, idx) in steps"
        :key="idx"
        class="stepper-step"
        :class="{
          completed: completedSteps.includes(idx),
          active: currentStep === idx,
        }"
        @click="completedSteps.includes(idx) ? $emit('go-step', idx) : null"
      >
        <div class="step-circle">
          <span v-if="completedSteps.includes(idx)">✓</span>
          <span v-else>{{ idx + 1 }}</span>
        </div>
        <span class="step-label">{{ step }}</span>
      </div>
      <div class="stepper-line">
        <div class="stepper-fill" :style="{ width: fillPercent + '%' }"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  steps: { type: Array, default: () => ['Type', 'Student', 'Academic', 'Course', 'Docs', 'Payment'] },
  currentStep: { type: Number, default: 0 },
  completedSteps: { type: Array, default: () => [] },
})

defineEmits(['go-step'])

const fillPercent = computed(() => {
  return Math.round((props.completedSteps.length / (props.steps.length - 1)) * 100)
})
</script>

<style scoped>
.stepper-track {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  padding: 0.5rem 0;
  margin-bottom: 1.5rem;
}

.stepper-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  z-index: 1;
  cursor: default;
}

.stepper-step.completed { cursor: pointer; }

.step-circle {
  width: 30px; height: 30px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 700;
  background: #e5e7eb; color: var(--text-muted);
  transition: all 0.3s;
}

.stepper-step.active .step-circle { background: #4a90d9; color: #fff; box-shadow: 0 0 0 4px rgba(74,144,217,0.2); }
.stepper-step.completed .step-circle { background: #27ae60; color: #fff; }

.step-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 500; }
.stepper-step.active .step-label { color: #4a90d9; font-weight: 600; }
.stepper-step.completed .step-label { color: #27ae60; }

.stepper-line {
  position: absolute; top: 15px; left: 20px; right: 20px;
  height: 3px; background: #e5e7eb; z-index: 0;
}
.stepper-fill { height: 100%; background: #27ae60; border-radius: 2px; transition: width 0.4s ease; }
</style>
