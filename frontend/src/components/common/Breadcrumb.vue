<template>
  <nav class="breadcrumb">
    <router-link to="/admin" class="breadcrumb-item">Home</router-link>
    <span v-for="(item, index) in items" :key="index">
      <span class="separator">/</span>
      <router-link v-if="item.link" :to="item.link" class="breadcrumb-item">
        {{ item.label }}
      </router-link>
      <span v-else class="breadcrumb-item active">{{ item.label }}</span>
    </span>
  </nav>
</template>

<script setup>
defineProps({
  items: {
    type: Array,
    default: () => []
  }
})
</script>

<style scoped>
.breadcrumb {
  padding: 1rem 0;
  font-size: 14px;
}

.breadcrumb-item {
  color: var(--primary-color);
}

.breadcrumb-item.active {
  color: var(--text-light);
}

.separator {
  margin: 0 0.5rem;
  color: var(--text-light);
}
</style>