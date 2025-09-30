import React from 'react';

const RaceCard = ({ race }) => {
    const formatDate = (dateString) => {
        if (!dateString) return 'TBD';

        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        } catch (err) {
            return dateString;
        }
    };

    const formatTime = (timeString) => {
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

    const getStatusBadge = () => {
        switch (race.status) {
            case 'completed':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        ✓ Completed
                    </span>
                );
            case 'ongoing':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        🔴 Live
                    </span>
                );
            case 'qualifying':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                        🏁 Qualifying
                    </span>
                );
            case 'upcoming':
            default:
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        📅 Upcoming
                    </span>
                );
        }
    };

    const canSelectLineup = race.status === 'upcoming' || race.status === 'qualifying';

    return (
        <div className="bg-gray-800 rounded-lg p-6 border border-gray-700 hover:border-gray-600 transition-colors">
            {/* Header */}
            <div className="flex items-start justify-between mb-4">
                <div>
                    <h3 className="text-lg font-semibold text-white font-titillium mb-1">
                        {race.name || `Race ${race.round_number}`}
                    </h3>
                    {getStatusBadge()}
                </div>
                <div className="text-right text-sm text-gray-400">
                    Round {race.round_number}
                </div>
            </div>

            {/* Circuit Info */}
            <div className="mb-4">
                <p className="text-white font-medium">{race.track_name || race.circuit_name}</p>
                <p className="text-gray-400 text-sm">{race.country}</p>
            </div>

            {/* Race Details */}
            <div className="space-y-2 mb-4 text-sm">
                <div className="flex justify-between">
                    <span className="text-gray-400">Date:</span>
                    <span className="text-white">{formatDate(race.race_date || race.date)}</span>
                </div>

                {race.qualifying_date && (
                    <div className="flex justify-between">
                        <span className="text-gray-400">Qualifying:</span>
                        <span className="text-white">
                            {formatDate(race.qualifying_date)}
                        </span>
                    </div>
                )}

                {race.time && (
                    <div className="flex justify-between">
                        <span className="text-gray-400">Race Time:</span>
                        <span className="text-white">{formatTime(race.time)}</span>
                    </div>
                )}

                {race.laps && (
                    <div className="flex justify-between">
                        <span className="text-gray-400">Laps:</span>
                        <span className="text-white">{race.laps}</span>
                    </div>
                )}
            </div>

            {/* Action Buttons */}
            <div className="flex space-x-2">
                {canSelectLineup ? (
                    <button className="flex-1 btn-primary">
                        Set Lineup
                    </button>
                ) : race.status === 'completed' ? (
                    <button className="flex-1 bg-green-700 hover:bg-green-600 text-white py-2 px-4 rounded-lg transition-colors">
                        View Results
                    </button>
                ) : (
                    <button
                        className="flex-1 bg-gray-600 text-gray-400 py-2 px-4 rounded-lg cursor-not-allowed"
                        disabled
                    >
                        {race.status === 'ongoing' ? 'Race in Progress' : 'Not Available'}
                    </button>
                )}

                <button className="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">
                    Details
                </button>
            </div>

            {/* Lineup Submission Deadline */}
            {canSelectLineup && race.team_selection_deadline && (
                <div className="mt-3 p-2 bg-yellow-900 bg-opacity-50 border border-yellow-700 rounded text-xs text-yellow-200">
                    <strong>Lineup deadline:</strong> {formatDate(race.team_selection_deadline)} at {formatTime(race.team_selection_deadline_time)}
                </div>
            )}
        </div>
    );
};

export default RaceCard;
