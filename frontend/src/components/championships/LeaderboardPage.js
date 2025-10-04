import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { championshipsAPI } from '../../services/api';
import { useAuth } from '../../contexts/AuthContext';
import LoadingSpinner from '../common/LoadingSpinner';

const LeaderboardPage = () => {
    const { championshipId } = useParams();
    const { user } = useAuth();
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [championship, setChampionship] = useState(null);
    const [standings, setStandings] = useState([]);
    const [userStats, setUserStats] = useState(null);

    useEffect(() => {
        const loadLeaderboard = async () => {
            try {
                setLoading(true);
                setError(null);

                // Get championship stats/leaderboard
                const response = await championshipsAPI.getStats(championshipId, user?.id);

                if (response.success) {
                    const data = response.data;
                    setStandings(data.standings || []);
                    setUserStats(data.user || null);

                    // Get championship details
                    const champResponse = await championshipsAPI.getAll();
                    if (champResponse.success) {
                        const champs = champResponse.data?.data || champResponse.data || [];
                        const champ = champs.find(c => c.id === parseInt(championshipId));
                        setChampionship(champ);
                    }
                } else {
                    setError(response.message || 'Failed to load leaderboard');
                }
            } catch (err) {
                console.error('Leaderboard error:', err);
                setError(err.message || 'Failed to load leaderboard');
            } finally {
                setLoading(false);
            }
        };

        if (championshipId && user) {
            loadLeaderboard();
        }
    }, [championshipId, user]);

    const getPositionStyle = (position) => {
        if (position === 1) return 'bg-yellow-500 text-black';
        if (position === 2) return 'bg-gray-400 text-black';
        if (position === 3) return 'bg-amber-600 text-white';
        return 'bg-gray-700 text-gray-200';
    };

    const isCurrentUser = (userId) => user && user.id === parseInt(userId);

    if (loading) {
        return (
            <div className="min-h-screen bg-black">
                <div className="max-w-5xl mx-auto px-4 py-8">
                    <LoadingSpinner />
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen bg-black">
                <div className="max-w-5xl mx-auto px-4 py-8">
                    <div className="bg-red-600/20 border border-red-500 text-red-200 px-4 py-3 rounded mb-6">
                        {error}
                    </div>
                    <Link
                        to="/"
                        className="inline-flex items-center text-primary hover:text-primary/80 transition-colors"
                    >
                        ← Back to Dashboard
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-black">
            <div className="max-w-5xl mx-auto px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <Link
                        to="/"
                        className="inline-flex items-center text-primary hover:text-primary/80 transition-colors mb-4"
                    >
                        ← Back to Dashboard
                    </Link>

                    <div className="bg-dark-100 rounded-lg p-6">
                        <h1 className="text-3xl font-titillium font-bold text-white mb-2">
                            Classifica
                        </h1>
                        {championship && (
                            <p className="text-gray-300">
                                {championship.name} <span className="text-primary">{championship.season_year}</span>
                            </p>
                        )}
                    </div>
                </div>

                {/* User's Position Summary */}
                {userStats && (
                    <div className="mb-8 bg-primary/10 border border-primary rounded-lg p-6">
                        <h2 className="text-xl font-titillium font-bold text-white mb-4">
                            La Tua Posizione
                        </h2>
                        <div className="grid grid-cols-3 gap-4">
                            <div className="text-center">
                                <div className="text-3xl font-bold text-primary">{userStats.position}</div>
                                <div className="text-sm text-gray-300 uppercase tracking-wide">Posizione</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-white">{Math.round(userStats.champ_points)}</div>
                                <div className="text-sm text-gray-300 uppercase tracking-wide">Punti Classifica</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-gray-300">{Math.round(userStats.raw_points)}</div>
                                <div className="text-sm text-gray-300 uppercase tracking-wide">Punti Totali</div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Leaderboard */}
                <div className="bg-dark-100 rounded-lg overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-700">
                        <h2 className="text-xl font-titillium font-bold text-white">
                            Classifica Completa
                        </h2>
                    </div>

                    {standings.length === 0 ? (
                        <div className="text-center py-12 text-gray-400">
                            Nessun partecipante trovato
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-700">
                            {standings.map((participant) => (
                                <div
                                    key={participant.user_id}
                                    className={`px-6 py-4 flex items-center justify-between transition-colors ${isCurrentUser(participant.user_id)
                                            ? 'bg-primary/20 border-l-4 border-primary'
                                            : 'hover:bg-gray-800'
                                        }`}
                                >
                                    <div className="flex items-center space-x-4">
                                        {/* Position */}
                                        <div
                                            className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${getPositionStyle(participant.position)}`}
                                        >
                                            {participant.position}
                                        </div>

                                        {/* Username */}
                                        <div>
                                            <div className={`font-semibold ${isCurrentUser(participant.user_id) ? 'text-primary' : 'text-white'}`}>
                                                {participant.username}
                                                {isCurrentUser(participant.user_id) && (
                                                    <span className="ml-2 text-xs bg-primary text-black px-2 py-1 rounded-full">
                                                        TU
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Points */}
                                    <div className="flex items-center space-x-8 text-right">
                                        <div>
                                            <div className="text-white font-bold">
                                                {Math.round(participant.champ_points)}
                                            </div>
                                            <div className="text-xs text-gray-400 uppercase tracking-wide">
                                                Punti Classifica
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-gray-300">
                                                {Math.round(participant.raw_points)}
                                            </div>
                                            <div className="text-xs text-gray-400 uppercase tracking-wide">
                                                Punti Totali
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Info Section */}
                <div className="mt-8 bg-gray-900 rounded-lg p-6">
                    <h3 className="text-lg font-titillium font-bold text-white mb-4">
                        Come Funziona la Classifica
                    </h3>
                    <div className="text-sm text-gray-300 space-y-2">
                        <p>• <strong>Punti Classifica:</strong> Punti assegnati in base alla posizione in ogni gara (25, 18, 14, 10, 6, 3, 1 per i primi 7 classificati)</p>
                        <p>• <strong>Punti Totali:</strong> Somma di tutti i punti ottenuti dai tuoi piloti nelle gare (usati per spareggiare in caso di parità)</p>
                        <p>• La classifica è ordinata prima per Punti Classifica, poi per Punti Totali in caso di parità</p>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default LeaderboardPage;
