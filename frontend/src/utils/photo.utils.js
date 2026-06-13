export function getPhotoUrl(photo) {
  if (!photo) return null
  if (photo.startsWith('http') || photo.startsWith('data:') || photo.startsWith('blob:')) return photo
  const clean = String(photo).replace(/^\/?storage\//, '')
  return `/storage/${clean}`
}

export function getPersonInitials(person) {
  const first = person?.first_name?.[0] || ''
  const last = person?.last_name?.[0] || ''
  const initials = (first + last).toUpperCase()
  return initials || '?'
}

export function appendFormFields(formData, data) {
  Object.entries(data).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return
    if (value instanceof File) {
      formData.append(key, value)
    } else {
      formData.append(key, value)
    }
  })
  return formData
}

export function buildStudentFormData(data) {
  const formData = new FormData()
  appendFormFields(formData, data)
  return formData
}

export function downloadBlobFile(response, filename) {
  const blob = new Blob([response.data], { type: response.headers?.['content-type'] || 'application/pdf' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  window.URL.revokeObjectURL(url)
}
