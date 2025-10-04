import axios from 'axios';

// Create axios instance with base configuration
// Use relative path; CRA dev proxy (package.json "proxy") will forward to backend and remove CORS issues.
const api = axios.create({
    baseURL: process.env.REACT_APP_API_BASE_URL || '/backend/api',
    headers: {
        'Content-Type': 'application/json',
    },
    timeout: 10000,
});

// Request interceptor to add auth token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('ff1_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Token expired or invalid
            localStorage.removeItem('ff1_token');
            localStorage.removeItem('ff1_user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

// Auth API functions
export const authAPI = {
    login: async (credentials) => {
        // Use form-encoded to avoid triggering a CORS preflight during development
        const params = new URLSearchParams();
        if (credentials.username) params.append('username', credentials.username);
        if (credentials.email) params.append('email', credentials.email);
        params.append('password', credentials.password || '');
        const response = await api.post('/auth/login.php', params, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });
        return response.data;
    },

    register: async (userData) => {
        const response = await api.post('/auth/register.php', userData);
        return response.data;
    },

    getProfile: async () => {
        const response = await api.get('/auth/profile.php');
        return response.data;
    },
};

// Championships API functions
export const championshipsAPI = {
    getAll: async () => {
        const response = await api.get('/championships/index.php');
        return response.data;
    },
    getForUser: async (userId) => {
        const response = await api.get('/championships/index.php', { params: { user_id: userId } });
        return response.data;
    },

    create: async (championshipData) => {
        const response = await api.post('/championships/index.php', championshipData);
        return response.data;
    },

    getStats: async (championshipId, userId = null) => {
        const params = { championship_id: championshipId };
        if (userId) params.user_id = userId;
        const response = await api.get('/championships/stats.php', { params });
        return response.data;
    },
};

// Races API functions
export const racesAPI = {
    getAll: async (season) => {
        const params = season ? { season } : {};
        const response = await api.get('/races/all.php', { params });
        return response.data;
    },

    getPaginated: async (params = {}) => {
        const response = await api.get('/races/index.php', { params });
        return response.data;
    },
};

// Drivers API functions
export const driversAPI = {
    getAll: async (raceId) => {
        const params = raceId ? { race_id: raceId } : {};
        const response = await api.get('/drivers/all.php', { params });
        return response.data;
    },

    getPaginated: async (params = {}) => {
        const response = await api.get('/drivers/index.php', { params });
        return response.data;
    },
};

// Lineups API functions
export const lineupsAPI = {
    getLineup: async (raceId, championshipId, params = {}) => {
        const queryParams = { race_id: raceId, championship_id: championshipId, ...params };
        const response = await api.get('/lineups/index.php', { params: queryParams });
        return response.data;
    },

    saveLineup: async (lineupData) => {
        const response = await api.post('/lineups/index.php', lineupData);
        return response.data;
    },
};

// Results API functions
export const resultsAPI = {
    getResults: async (raceId) => {
        const response = await api.get('/results/index.php', { params: { race_id: raceId } });
        return response.data;
    },

    submitResults: async (resultsData) => {
        const response = await api.post('/results/index.php', resultsData);
        return response.data;
    },
};

export default api;
