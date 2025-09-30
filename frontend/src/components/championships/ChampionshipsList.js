import React from 'react';
import ChampionshipCard from './ChampionshipCard';

const ChampionshipsList = ({ championships, onJoinChampionship, searchTerm }) => {
    if (championships.length === 0) {
        return (
            <div className="text-center py-12">
                <div className="text-6xl mb-4">🏆</div>
                <h3 className="text-xl font-semibold text-white mb-2">
                    {searchTerm ? 'No championships found' : 'No championships available'}
                </h3>
                <p className="text-gray-400 mb-6">
                    {searchTerm
                        ? `No championships match "${searchTerm}". Try a different search term.`
                        : 'Be the first to create a championship and start your fantasy league!'
                    }
                </p>
                {searchTerm && (
                    <button
                        onClick={() => window.location.reload()}
                        className="text-primary hover:text-red-400 underline"
                    >
                        Clear search and view all
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {championships.map((championship) => (
                <ChampionshipCard
                    key={championship.id}
                    championship={championship}
                    onJoin={onJoinChampionship}
                />
            ))}
        </div>
    );
};

export default ChampionshipsList;
