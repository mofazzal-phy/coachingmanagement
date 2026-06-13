<template>
  <footer class="footer">© {{ year }} Herd Coaching Management. All rights reserved.</footer>
</template>

<script setup>
const year = new Date().getFullYear()
</script>

<style scoped>
.footer {
  border-top: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  padding: 0.85rem 1.25rem;
  font-size: 0.9rem;
}
</style>
