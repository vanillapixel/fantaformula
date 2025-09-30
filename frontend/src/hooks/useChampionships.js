import { useState, useEffect } from 'react';
import { championshipsAPI } from '../services/api';

export const useChampionships = () => {
    const [championships, setChampionships] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const loadChampionships = async () => {
        try {
            setLoading(true);
            setError(null);
            const response = await championshipsAPI.getAll();

            if (response.success) {
                // Handle nested data structure from API
                const data = response.data?.data || response.data || [];
                setChampionships(data);
            } else {
                setError(response.error || 'Failed to load championships');
            }
        } catch (err) {
            console.error('Error loading championships:', err);
            setError('Failed to load championships. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const createChampionship = async (championshipData) => {
        try {
            const response = await championshipsAPI.create(championshipData);

            if (response.success) {
                await loadChampionships(); // Reload the list
                return response;
            } else {
                throw new Error(response.error || 'Failed to create championship');
            }
        } catch (err) {
            console.error('Error creating championship:', err);
            throw err;
        }
    };

    const joinChampionship = async (championshipId) => {
        try {
            // TODO: Implement join championship API call when backend endpoint is ready
            console.log('Joining championship:', championshipId);
            // For now, just reload championships to simulate update
            await loadChampionships();
            return { success: true };
        } catch (err) {
            console.error('Error joining championship:', err);
            throw err;
        }
    };

    useEffect(() => {
        loadChampionships();
    }, []);

    return {
        championships,
        loading,
        error,
        loadChampionships,
        createChampionship,
        joinChampionship,
    };
};

export default useChampionships;
