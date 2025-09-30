import React, { useState } from 'react';

const CreateChampionshipModal = ({ isOpen, onClose, onCreate }) => {
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        max_participants: 10,
        is_public: true,
        entry_fee: 0,
        season_year: new Date().getFullYear(),
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();

        // Basic validation
        if (!formData.name.trim()) {
            setError('Championship name is required');
            return;
        }

        if (formData.max_participants < 2 || formData.max_participants > 100) {
            setError('Max participants must be between 2 and 100');
            return;
        }

        try {
            setLoading(true);
            setError(null);

            await onCreate(formData);

            // Reset form on success
            setFormData({
                name: '',
                description: '',
                max_participants: 10,
                is_public: true,
                entry_fee: 0,
                season_year: new Date().getFullYear(),
            });
        } catch (err) {
            setError(err.message || 'Failed to create championship');
        } finally {
            setLoading(false);
        }
    };

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : type === 'number' ? parseInt(value) || 0 : value
        }));
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-gray-800 rounded-lg max-w-md w-full max-h-96 overflow-y-auto">
                <div className="p-6">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <h2 className="text-xl font-bold text-white font-titillium">
                            Create Championship
                        </h2>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-white text-2xl leading-none"
                        >
                            ×
                        </button>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <div className="mb-4 p-3 bg-red-900 border border-red-700 rounded-lg text-red-100 text-sm">
                            {error}
                        </div>
                    )}

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {/* Championship Name */}
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Championship Name *
                            </label>
                            <input
                                type="text"
                                name="name"
                                value={formData.name}
                                onChange={handleChange}
                                placeholder="Enter championship name"
                                className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                required
                            />
                        </div>

                        {/* Description */}
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Description
                            </label>
                            <textarea
                                name="description"
                                value={formData.description}
                                onChange={handleChange}
                                placeholder="Optional description"
                                rows={3}
                                className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
                            />
                        </div>

                        {/* Max Participants */}
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Max Participants
                            </label>
                            <input
                                type="number"
                                name="max_participants"
                                value={formData.max_participants}
                                onChange={handleChange}
                                min="2"
                                max="100"
                                className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            />
                            <p className="text-xs text-gray-400 mt-1">Between 2 and 100 participants</p>
                        </div>

                        {/* Season Year */}
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Season Year
                            </label>
                            <select
                                name="season_year"
                                value={formData.season_year}
                                onChange={handleChange}
                                className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            >
                                <option value={2024}>2024</option>
                                <option value={2025}>2025</option>
                                <option value={2026}>2026</option>
                            </select>
                        </div>

                        {/* Entry Fee */}
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                Entry Fee ($)
                            </label>
                            <input
                                type="number"
                                name="entry_fee"
                                value={formData.entry_fee}
                                onChange={handleChange}
                                min="0"
                                step="0.01"
                                className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            />
                            <p className="text-xs text-gray-400 mt-1">Leave as 0 for free championship</p>
                        </div>

                        {/* Public/Private */}
                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                name="is_public"
                                id="is_public"
                                checked={formData.is_public}
                                onChange={handleChange}
                                className="h-4 w-4 text-primary bg-gray-700 border-gray-600 rounded focus:ring-primary focus:ring-2"
                            />
                            <label htmlFor="is_public" className="ml-2 text-sm text-gray-300">
                                Make championship public
                            </label>
                        </div>
                        <p className="text-xs text-gray-400">
                            Public championships can be joined by anyone. Private ones require an invitation.
                        </p>

                        {/* Action Buttons */}
                        <div className="flex space-x-3 pt-4">
                            <button
                                type="button"
                                onClick={onClose}
                                className="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-2 px-4 rounded-lg transition-colors"
                                disabled={loading}
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={loading}
                                className="flex-1 btn-primary disabled:bg-gray-600 disabled:cursor-not-allowed"
                            >
                                {loading ? 'Creating...' : 'Create Championship'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default CreateChampionshipModal;
