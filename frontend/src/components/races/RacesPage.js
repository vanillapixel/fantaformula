import React, { useState, useEffect, useCallback } from 'react';
import { racesAPI } from '../../services/api';
import RaceCard from './RaceCard';
import LoadingSpinner from '../common/LoadingSpinner';

const RacesPage = () => {
    const [races, setRaces] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedSeason, setSelectedSeason] = useState(2025);

    const loadRaces = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await racesAPI.getAll(selectedSeason);

            if (response.success) {
                // Handle nested data structure from API
                const racesData = response.data?.races || response.data || [];
                setRaces(racesData);
            } else {
                setError(response.error || 'Failed to load races');
            }
        } catch (err) {
            console.error('Error loading races:', err);
            setError('Failed to load races. Please try again.');
        } finally {
            setLoading(false);
        }
    }, [selectedSeason]);

    useEffect(() => {
        loadRaces();
    }, [loadRaces]);

    if (loading) {
        return <LoadingSpinner message="Loading races..." />;
    }

    return (
        <div className="min-h-screen bg-dark-100">
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-white font-titillium mb-2">
                            Race Calendar
                        </h1>
                        <p className="text-gray-400">
                            View upcoming races and manage your fantasy teams
                        </p>
                    </div>

                    {/* Season Selector */}
                    <div className="mt-4 sm:mt-0">
                        <select
                            value={selectedSeason}
                            onChange={(e) => setSelectedSeason(parseInt(e.target.value))}
                            className="px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        >
                            <option value={2024}>2024 Season</option>
                            <option value={2025}>2025 Season</option>
                            <option value={2026}>2026 Season</option>
                        </select>
                    </div>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="mb-6 p-4 bg-red-900 border border-red-700 rounded-lg text-red-100">
                        {error}
                        <button
                            onClick={loadRaces}
                            className="ml-4 text-red-200 hover:text-white underline"
                        >
                            Try Again
                        </button>
                    </div>
                )}

                {/* Races Grid */}
                {races.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {races.map((race) => (
                            <RaceCard
                                key={race.id}
                                race={race}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <div className="text-6xl mb-4">🏎️</div>
                        <h3 className="text-xl font-semibold text-white mb-2">
                            No races found
                        </h3>
                        <p className="text-gray-400">
                            No races available for the {selectedSeason} season.
                        </p>
                    </div>
                )}
            </main>
        </div>
    );
};

export default RacesPage;
