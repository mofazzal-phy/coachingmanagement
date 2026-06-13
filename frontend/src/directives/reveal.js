// v-reveal: adds `is-visible` when the element scrolls into view.
// Pair with `.pub-reveal` for the fade-up animation. Optional delay via value (ms).

const observer = typeof IntersectionObserver !== 'undefined'
  ? new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible')
            obs.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    )
  : null

export const reveal = {
  mounted(el, binding) {
    el.classList.add('pub-reveal')
    if (binding.value) {
      el.style.animationDelay = `${binding.value}ms`
    }
    if (observer) {
      observer.observe(el)
    } else {
      el.classList.add('is-visible')
    }
  },
  unmounted(el) {
    if (observer) observer.unobserve(el)
  },
}
