import React, { useState } from 'react';
import ChampionshipsList from './ChampionshipsList';
import CreateChampionshipModal from './CreateChampionshipModal';
import LoadingSpinner from '../common/LoadingSpinner';
import useChampionships from '../../hooks/useChampionships';

const ChampionshipsPage = () => {
    const { championships, loading, error, createChampionship, joinChampionship, loadChampionships } = useChampionships();
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');

    const handleCreateChampionship = async (championshipData) => {
        try {
            await createChampionship(championshipData);
            setShowCreateModal(false);
        } catch (err) {
            console.error('Error creating championship:', err);
            throw err; // Re-throw to let modal handle the error
        }
    };

    // Filter championships based on search term
    const filteredChampionships = championships.filter(championship =>
        championship.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        championship.description?.toLowerCase().includes(searchTerm.toLowerCase())
    );

    if (loading) {
        return <LoadingSpinner message="Loading championships..." />;
    }

    return (
        <div className="min-h-screen bg-dark-100">
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-white font-titillium mb-2">
                            Championships
                        </h1>
                        <p className="text-gray-400">
                            Join existing championships or create your own fantasy league
                        </p>
                    </div>

                    <div className="mt-4 sm:mt-0">
                        <button
                            onClick={() => setShowCreateModal(true)}
                            className="btn-primary flex items-center space-x-2"
                        >
                            <span>➕</span>
                            <span>Create Championship</span>
                        </button>
                    </div>
                </div>

                {/* Search Bar */}
                <div className="mb-6">
                    <div className="relative">
                        <input
                            type="text"
                            placeholder="Search championships..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full px-4 py-3 pl-10 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-colors"
                        />
                        <div className="absolute left-3 top-3.5">
                            <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="mb-6 p-4 bg-red-900 border border-red-700 rounded-lg text-red-100">
                        {error}
                        <button
                            onClick={loadChampionships}
                            className="ml-4 text-red-200 hover:text-white underline"
                        >
                            Try Again
                        </button>
                    </div>
                )}

                {/* Championships List */}
                <ChampionshipsList
                    championships={filteredChampionships}
                    onJoinChampionship={joinChampionship}
                    searchTerm={searchTerm}
                />

                {/* Create Championship Modal */}
                {showCreateModal && (
                    <CreateChampionshipModal
                        isOpen={showCreateModal}
                        onClose={() => setShowCreateModal(false)}
                        onCreate={handleCreateChampionship}
                    />
                )}
            </main>
        </div>
    );
};

export default ChampionshipsPage;
