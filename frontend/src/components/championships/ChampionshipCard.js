import React, { useState } from 'react';
import { useAuth } from '../../contexts/AuthContext';

const ChampionshipCard = ({ championship, onJoin }) => {
    const { user } = useAuth();
    const [loading, setLoading] = useState(false);

    const handleJoinChampionship = async () => {
        try {
            setLoading(true);

            if (onJoin) {
                await onJoin(championship.id);
            } else {
                // Fallback alert if onJoin is not provided
                alert('Join functionality will be implemented soon!');
            }
        } catch (err) {
            console.error('Error joining championship:', err);
            alert('Failed to join championship. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const getStatusBadge = () => {
        if (championship.status === 'active') {
            return (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Active
                </span>
            );
        } else if (championship.status === 'upcoming') {
            return (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    Upcoming
                </span>
            );
        } else if (championship.status === 'completed') {
            return (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Completed
                </span>
            );
        }
        return null;
    };

    const isParticipating = championship.participants?.some(p => p.user_id === user?.id);
    const isAdmin = championship.admin_id === user?.id || user?.is_super_admin;
    const canJoin = championship.is_public && !isParticipating && championship.status !== 'completed';
    const isFull = championship.current_participants >= championship.max_participants;

    return (
        <div className="bg-gray-800 rounded-lg p-6 border border-gray-700 hover:border-gray-600 transition-colors">
            {/* Header */}
            <div className="flex items-start justify-between mb-4">
                <div>
                    <h3 className="text-lg font-semibold text-white font-titillium mb-1">
                        {championship.name}
                    </h3>
                    {getStatusBadge()}
                </div>
                {isAdmin && (
                    <span className="text-xs bg-primary text-white px-2 py-1 rounded-full">
                        Admin
                    </span>
                )}
            </div>

            {/* Description */}
            {championship.description && (
                <p className="text-gray-400 text-sm mb-4 line-clamp-2">
                    {championship.description}
                </p>
            )}

            {/* Stats */}
            <div className="space-y-2 mb-4">
                <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Participants:</span>
                    <span className="text-white">
                        {championship.current_participants || 0} / {championship.max_participants}
                    </span>
                </div>

                <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Season:</span>
                    <span className="text-white">{championship.season_year || '2025'}</span>
                </div>

                <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Admin:</span>
                    <span className="text-white">{championship.admin_username || 'Unknown'}</span>
                </div>

                {championship.entry_fee && championship.entry_fee > 0 && (
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-400">Entry Fee:</span>
                        <span className="text-primary font-medium">${championship.entry_fee}</span>
                    </div>
                )}
            </div>

            {/* Progress Bar */}
            <div className="mb-4">
                <div className="flex justify-between text-xs text-gray-400 mb-1">
                    <span>Capacity</span>
                    <span>
                        {Math.round(((championship.current_participants || 0) / championship.max_participants) * 100)}%
                    </span>
                </div>
                <div className="w-full bg-gray-700 rounded-full h-2">
                    <div
                        className="bg-primary h-2 rounded-full transition-all duration-300"
                        style={{
                            width: `${Math.min(((championship.current_participants || 0) / championship.max_participants) * 100, 100)}%`
                        }}
                    />
                </div>
            </div>

            {/* Action Button */}
            <div className="flex space-x-2">
                {isParticipating ? (
                    <button
                        className="flex-1 bg-green-700 text-white py-2 px-4 rounded-lg text-sm font-medium cursor-not-allowed"
                        disabled
                    >
                        ✓ Joined
                    </button>
                ) : canJoin ? (
                    <button
                        onClick={handleJoinChampionship}
                        disabled={loading || isFull}
                        className={`flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-colors ${loading || isFull
                                ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                                : 'btn-primary hover:bg-red-600'
                            }`}
                    >
                        {loading ? 'Joining...' : isFull ? 'Full' : 'Join Championship'}
                    </button>
                ) : !championship.is_public ? (
                    <button
                        className="flex-1 bg-gray-600 text-gray-400 py-2 px-4 rounded-lg text-sm cursor-not-allowed"
                        disabled
                    >
                        🔒 Private
                    </button>
                ) : (
                    <button
                        className="flex-1 bg-gray-600 text-gray-400 py-2 px-4 rounded-lg text-sm cursor-not-allowed"
                        disabled
                    >
                        Cannot Join
                    </button>
                )}

                {/* View Details Button */}
                <button className="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg text-sm transition-colors">
                    View
                </button>
            </div>

            {/* Additional Info */}
            {!championship.is_public && (
                <p className="text-xs text-gray-500 mt-2 text-center">
                    This is a private championship
                </p>
            )}
        </div>
    );
};

export default ChampionshipCard;
