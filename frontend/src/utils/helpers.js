/**
 * Utility functions for handling API responses and data
 */

/**
 * Extract data from nested API response structures
 * @param {Object} response - API response object
 * @param {string} dataKey - Key to extract data from (optional)
 * @returns {Array|Object} - Extracted data
 */
export const extractApiData = (response, dataKey = null) => {
    if (!response || !response.success) {
        return [];
    }

    if (dataKey && response.data?.[dataKey]) {
        return response.data[dataKey];
    }

    // Handle common nested structures
    if (response.data?.data) {
        return response.data.data;
    }

    if (response.data?.races) {
        return response.data.races;
    }

    return response.data || [];
};

/**
 * Format date string to readable format
 * @param {string} dateString - ISO date string
 * @param {Object} options - Intl.DateTimeFormat options
 * @returns {string} - Formatted date
 */
export const formatDate = (dateString, options = {}) => {
    if (!dateString) return 'TBD';

    try {
        const date = new Date(dateString);
        const defaultOptions = {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            ...options
        };
        return date.toLocaleDateString('en-US', defaultOptions);
    } catch (err) {
        return dateString;
    }
};

/**
 * Format time string to readable format
 * @param {string} timeString - Time string (HH:MM format)
 * @returns {string} - Formatted time
 */
export const formatTime = (timeString) => {
    if (!timeString) return 'TBD';

    try {
        const [hours, minutes] = timeString.split(':');
        const date = new Date();
        date.setHours(parseInt(hours), parseInt(minutes));
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    } catch (err) {
        return timeString;
    }
};

/**
 * Generate a status badge configuration
 * @param {string} status - Status string
 * @returns {Object} - Badge configuration with classes and text
 */
export const getStatusBadge = (status) => {
    const badges = {
        active: {
            classes: 'bg-green-100 text-green-800',
            text: 'Active',
            icon: '✓'
        },
        upcoming: {
            classes: 'bg-blue-100 text-blue-800',
            text: 'Upcoming',
            icon: '📅'
        },
        completed: {
            classes: 'bg-gray-100 text-gray-800',
            text: 'Completed',
            icon: '✓'
        },
        ongoing: {
            classes: 'bg-yellow-100 text-yellow-800',
            text: 'Live',
            icon: '🔴'
        },
        qualifying: {
            classes: 'bg-orange-100 text-orange-800',
            text: 'Qualifying',
            icon: '🏁'
        },
        default: {
            classes: 'bg-gray-100 text-gray-800',
            text: 'Unknown',
            icon: '❓'
        }
    };

    return badges[status] || badges.default;
};

/**
 * Debounce function for search inputs
 * @param {Function} func - Function to debounce
 * @param {number} delay - Delay in milliseconds
 * @returns {Function} - Debounced function
 */
export const debounce = (func, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(null, args), delay);
    };
};

/**
 * Validate championship form data
 * @param {Object} formData - Championship form data
 * @returns {Object} - Validation result with isValid and errors
 */
export const validateChampionshipForm = (formData) => {
    const errors = {};

    if (!formData.name?.trim()) {
        errors.name = 'Championship name is required';
    }

    if (formData.max_participants < 2 || formData.max_participants > 100) {
        errors.max_participants = 'Max participants must be between 2 and 100';
    }

    if (formData.entry_fee < 0) {
        errors.entry_fee = 'Entry fee cannot be negative';
    }

    const currentYear = new Date().getFullYear();
    if (formData.season_year < currentYear - 1 || formData.season_year > currentYear + 5) {
        errors.season_year = 'Season year must be within reasonable range';
    }

    return {
        isValid: Object.keys(errors).length === 0,
        errors
    };
};

/**
 * Calculate percentage for progress bars
 * @param {number} current - Current value
 * @param {number} total - Total value
 * @returns {number} - Percentage (0-100)
 */
export const calculatePercentage = (current, total) => {
    if (!total || total === 0) return 0;
    return Math.min(Math.round((current / total) * 100), 100);
};

const helpers = {
    extractApiData,
    formatDate,
    formatTime,
    getStatusBadge,
    debounce,
    validateChampionshipForm,
    calculatePercentage
};

export default helpers;
