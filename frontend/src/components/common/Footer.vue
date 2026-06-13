<template>
  <footer class="footer">
    <p>&copy; {{ currentYear }} Coaching Management System. All rights reserved.</p>
  </footer>
</template>

<script setup>
const currentYear = new Date().getFullYear()
</script>

<style scoped>
.footer {
  height: var(--footer-height);
  background: var(--footer-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  border-top: 1px solid var(--border-color);
  font-size: 14px;
  color: var(--text-muted);
}
</style>