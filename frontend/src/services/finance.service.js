import apiClient from './api.service'

export default {
  // ========== Fee Types ==========
  feeTypes: {
    list(params = {}) {
      return apiClient.get('/fee-types', { params })
    },
    get(id) {
      return apiClient.get(`/fee-types/${id}`)
    },
    create(data) {
      return apiClient.post('/fee-types', data)
    },
    update(id, data) {
      return apiClient.put(`/fee-types/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/fee-types/${id}`)
    },
  },

  // ========== Fee Structures ==========
  feeStructures: {
    list(params = {}) {
      return apiClient.get('/fee-structures', { params })
    },
    get(id) {
      return apiClient.get(`/fee-structures/${id}`)
    },
    create(data) {
      return apiClient.post('/fee-structures', data)
    },
    update(id, data) {
      return apiClient.put(`/fee-structures/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/fee-structures/${id}`)
    },
  },

  // ========== Fee Collections ==========
  collections: {
    list(params = {}) {
      return apiClient.get('/fee-collections', { params })
    },
    get(id) {
      return apiClient.get(`/fee-collections/${id}`)
    },
    create(data) {
      return apiClient.post('/fee-collections', data)
    },
    update(id, data) {
      return apiClient.put(`/fee-collections/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/fee-collections/${id}`)
    },
  },

  // ========== Expenses ==========
  expenses: {
    list(params = {}) {
      return apiClient.get('/expenses', { params })
    },
    get(id) {
      return apiClient.get(`/expenses/${id}`)
    },
    create(data) {
      return apiClient.post('/expenses', data)
    },
    update(id, data) {
      return apiClient.put(`/expenses/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/expenses/${id}`)
    },
  },

  // ========== Expense Categories ==========
  expenseCategories: {
    list(params = {}) {
      return apiClient.get('/expense-categories', { params })
    },
    get(id) {
      return apiClient.get(`/expense-categories/${id}`)
    },
    create(data) {
      return apiClient.post('/expense-categories', data)
    },
    update(id, data) {
      return apiClient.put(`/expense-categories/${id}`, data)
    },
    delete(id) {
      return apiClient.delete(`/expense-categories/${id}`)
    },
  },
}
