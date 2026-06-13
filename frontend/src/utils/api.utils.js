/**
 * API Response Utilities
 *
 * Normalizes API response parsing across all frontend pages.
 *
 * Backend API response format:
 *   { status: 'success'|'error', message: '...', data: ..., meta?: ..., errors?: ... }
 *
 * Axios interceptor (api.service.js) returns the full axios response object.
 * So: response.data = { status, message, data, meta }
 * And actual content is in response.data.data
 */

/**
 * Extract data payload from API response.
 * Handles various nesting levels gracefully.
 *
 * @param {Object} response - Axios response object
 * @param {*} fallback - Default value if data is not found (default: null)
 * @returns {*} The extracted data
 *
 * @example
 *   const users = extractData(response, [])  // returns [] if no data
 *   const user = extractData(response, null)  // returns null if no data
 */
export function extractData(response, fallback = null) {
  if (!response) return fallback

  // response.data?.data?.data (double-wrapped, e.g. paginated nested)
  if (response.data?.data?.data != null) {
    return response.data.data.data
  }

  // response.data?.data (standard API format; skip null — error responses use data: null)
  if (response.data?.data != null) {
    return response.data.data
  }

  // response.data (raw data)
  if (response.data !== undefined) {
    return response.data
  }

  return fallback
}

/**
 * Extract pagination meta from API response.
 *
 * @param {Object} response - Axios response object
 * @param {Object} fallback - Default meta if not found (default: null)
 * @returns {Object|null} Meta object with pagination info
 *
 * @example
 *   const meta = extractMeta(response)
 *   // { current_page: 1, last_page: 5, per_page: 15, total: 72, ... }
 */
export function extractMeta(response, fallback = null) {
  if (!response) return fallback

  // response.data?.meta (standard)
  if (response.data?.meta) {
    return response.data.meta
  }

  // response.meta (if interceptor already unwrapped)
  if (response.meta) {
    return response.meta
  }

  return fallback
}

/**
 * Extract error message from API error response.
 *
 * @param {Error|Object} error - Axios error object
 * @param {string} fallback - Default message if not found
 * @returns {string} Error message
 *
 * @example
 *   catch (err) {
 *     error.value = extractErrorMessage(err, 'Something went wrong')
 *   }
 */
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

function humanizeValidationErrors(errors) {
  if (!errors || typeof errors !== 'object') return ''

  const parts = []
  for (const values of Object.values(errors)) {
    const flat = Array.isArray(values) ? values : [values]
    for (const value of flat) {
      const text = String(value ?? '').trim()
      if (!text || UUID_RE.test(text)) continue
      parts.push(text)
    }
  }
  return parts.join(' ')
}

export function extractErrorMessage(error, fallback = 'An unexpected error occurred') {
  if (!error) return fallback

  const data = error.response?.data

  if (data?.message) {
    const msg = String(data.message)
    const lower = msg.toLowerCase()
    if (lower.includes('token invalid') || lower.includes('token expired') || lower.includes('token not provided')) {
      return 'Your session expired. Please log in again and retry.'
    }
    return msg
  }

  const validationMsg = humanizeValidationErrors(data?.errors)
  if (validationMsg) return validationMsg

  if (error.message) {
    return error.message
  }

  return fallback
}

/**
 * Extract validation errors from API error response.
 *
 * @param {Error|Object} error - Axios error object
 * @returns {Object|null} Validation errors object or null
 *
 * @example
 *   const errors = extractValidationErrors(err)
 *   // { email: ['The email field is required.'], ... }
 */
export function extractValidationErrors(error) {
  if (!error) return null

  // error.response?.data?.errors (standard API validation error)
  if (error.response?.data?.errors) {
    return error.response.data.errors
  }

  return null
}

/**
 * Check if API response indicates success.
 *
 * @param {Object} response - Axios response object
 * @returns {boolean}
 */
export function isSuccess(response) {
  return response?.data?.status === 'success' || response?.status === 'success'
}

/**
 * Get the success message from API response.
 *
 * @param {Object} response - Axios response object
 * @param {string} fallback
 * @returns {string}
 */
export function getMessage(response, fallback = '') {
  return response?.data?.message || fallback
}

/**
 * Creates a debounced version of a function.
 *
 * @param {Function} fn - The function to debounce
 * @param {number} delay - Delay in milliseconds
 * @returns {Function} Debounced function
 *
 * @example
 *   const debouncedSearch = debounce(() => this.loadCourses(), 300)
 */
export function debounce(fn, delay = 300) {
  let timer = null
  return function (...args) {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => fn.apply(this, args), delay)
  }
}

/**
 * Normalize axios blob export responses and surface JSON API errors.
 *
 * @param {Object} response - Axios response with responseType blob
 * @param {string} mimeType - Expected file mime type
 * @returns {Promise<Blob>}
 */
export async function blobFromExportResponse(response, mimeType = 'application/pdf') {
  const raw = response?.data
  const blob = raw instanceof Blob ? raw : new Blob([raw], { type: mimeType })

  const likelyJson = blob.type.includes('json')
    || blob.type.includes('text')
    || blob.size < 4096

  if (likelyJson) {
    const text = (await blob.text()).trim()
    if (text.startsWith('{') || text.startsWith('[')) {
      try {
        const json = JSON.parse(text)
        throw new Error(json.message || 'Export failed')
      } catch (err) {
        if (err.message && err.message !== 'Export failed') {
          throw err
        }
        throw new Error(text || 'Export failed')
      }
    }
  }

  if (!blob.type || blob.type === 'application/octet-stream') {
    return new Blob([await blob.arrayBuffer()], { type: mimeType })
  }

  return blob
}