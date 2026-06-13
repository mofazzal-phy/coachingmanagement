import api from './api.service';

class TeacherService {
    async getAll(params = {}) {
        const response = await api.get('/teachers', { params });
        // Return the inner data array directly for consistency with enrollment service
        const body = response.data;
        return body?.data || body || [];
    }

    async getById(id) {
        const response = await api.get(`/teachers/${id}`);
        return response.data;
    }

    async create(data) {
        // If data is FormData (file upload), let axios set Content-Type automatically
        const config = data instanceof FormData
            ? { headers: { 'Content-Type': 'multipart/form-data' } }
            : {};
        const response = await api.post('/teachers', data, config);
        return response.data;
    }

    async update(id, data) {
        const config = data instanceof FormData
            ? { headers: { 'Content-Type': 'multipart/form-data' } }
            : {};
        const response = await api.put(`/teachers/${id}`, data, config);
        return response.data;
    }

    async delete(id) {
        const response = await api.delete(`/teachers/${id}`);
        return response.data;
    }

    async listAll(params = {}) {
        return api.get('/teachers/all', { params });
    }

    async downloadIdCard(id) {
        return api.get(`/teachers/${id}/id-card/download`, { responseType: 'blob' });
    }

    async bySubject(subjectId) {
        return api.get(`/teachers/by-subject/${subjectId}`);
    }

    /**
     * Get teachers filtered by class and subject.
     * @param {string} classId
     * @param {string} subjectId
     * @param {string|null} groupId
     */
    async byClassSubject(classId, subjectId, groupId = null) {
        const params = { class_id: classId, subject_id: subjectId };
        if (groupId) params.group_id = groupId;
        return api.get('/teachers/by-class-subject', { params });
    }

    // Flat alias for backward compatibility
    async getTeachers(params = {}) {
        return this.getAll(params);
    }
}

export default new TeacherService();
