import React, { useState, useMemo } from 'react';
import { useLineup } from '../../contexts/LineupContext';

const DriverSelectionModal = () => {
    const {
        isModalOpen,
        selectedSlotIndex,
        closeDriverModal,
        selectDriver,
        getAvailableDriversForSlot
    } = useLineup();

    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('price'); // price, name, constructor

    const availableDrivers = useMemo(() => {
        if (selectedSlotIndex === null) return [];
        return getAvailableDriversForSlot(selectedSlotIndex);
    }, [selectedSlotIndex, getAvailableDriversForSlot]);

    const filteredAndSortedDrivers = useMemo(() => {
        let filtered = availableDrivers.filter(driver => {
            if (!searchTerm) return true;
            const search = searchTerm.toLowerCase();
            return (
                driver.first_name?.toLowerCase().includes(search) ||
                driver.last_name?.toLowerCase().includes(search) ||
                driver.constructor_name?.toLowerCase().includes(search) ||
                driver.constructor_short_name?.toLowerCase().includes(search)
            );
        });

        // Sort drivers
        filtered.sort((a, b) => {
            switch (sortBy) {
                case 'price':
                    return (a.price || 0) - (b.price || 0);
                case 'name':
                    return `${a.first_name} ${a.last_name}`.localeCompare(`${b.first_name} ${b.last_name}`);
                case 'constructor':
                    return (a.constructor_name || '').localeCompare(b.constructor_name || '');
                default:
                    return 0;
            }
        });

        return filtered;
    }, [availableDrivers, searchTerm, sortBy]);

    const handleDriverSelect = (driver) => {
        selectDriver(driver, selectedSlotIndex);
    };

    const handleClose = () => {
        closeDriverModal();
        setSearchTerm('');
        setSortBy('price');
    };

    // Mock recent results data - in production this would come from API
    const getMockRecentResults = (driverId) => {
        const circuits = ['🇦🇺', '🇧🇭', '🇸🇦', '🇯🇵', '🇨🇳'];
        const positions = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];

        return circuits.map((flag, index) => ({
            circuit: flag,
            position: positions[Math.floor(Math.random() * positions.length)],
            raceId: index + 1
        }));
    };

    if (!isModalOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div className="bg-dark-100 w-full max-w-md max-h-screen overflow-hidden rounded-lg border border-gray-700">
                {/* Modal Header */}
                <div className="p-4 border-b border-gray-700">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-xl font-bold text-white font-titillium">
                            Select driver
                        </h2>
                        <button
                            onClick={handleClose}
                            className="w-8 h-8 bg-gray-700 hover:bg-gray-600 text-white rounded-full flex items-center justify-center transition-colors"
                        >
                            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    {/* Search and Sort Controls */}
                    <div className="space-y-3">
                        <input
                            type="text"
                            placeholder="Search drivers..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        />

                        <select
                            value={sortBy}
                            onChange={(e) => setSortBy(e.target.value)}
                            className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        >
                            <option value="price">Sort by Price</option>
                            <option value="name">Sort by Name</option>
                            <option value="constructor">Sort by Team</option>
                        </select>
                    </div>
                </div>

                {/* Drivers List */}
                <div className="overflow-y-auto" style={{ maxHeight: 'calc(100vh - 200px)' }}>
                    {filteredAndSortedDrivers.length === 0 ? (
                        <div className="p-8 text-center text-gray-400">
                            <div className="text-4xl mb-2">🏎️</div>
                            <p>No drivers available</p>
                        </div>
                    ) : (
                        <div className="p-2">
                            {filteredAndSortedDrivers.map((driver) => {
                                const recentResults = getMockRecentResults(driver.id);

                                return (
                                    <div
                                        key={driver.id}
                                        onClick={() => handleDriverSelect(driver)}
                                        className="bg-gray-800 border border-gray-700 rounded-lg p-4 mb-3 cursor-pointer hover:bg-gray-700 hover:border-gray-600 transition-all duration-200"
                                    >
                                        <div className="flex items-start space-x-4">
                                            {/* Driver Number/Logo */}
                                            <div className="flex-shrink-0">
                                                {driver.logo_url ? (
                                                    <img
                                                        src={`${process.env.REACT_APP_API_BASE_URL || '/backend/api'}${driver.logo_url}`}
                                                        alt={`Driver #${driver.driver_number}`}
                                                        className="w-12 h-12 object-cover rounded bg-white"
                                                        onError={(e) => {
                                                            e.target.style.display = 'none';
                                                            e.target.nextSibling.style.display = 'flex';
                                                        }}
                                                    />
                                                ) : null}

                                                {/* Fallback number display */}
                                                <div
                                                    className={`w-12 h-12 bg-white border border-gray-300 rounded flex items-center justify-center ${driver.logo_url ? 'hidden' : 'flex'
                                                        }`}
                                                    style={{ display: driver.logo_url ? 'none' : 'flex' }}
                                                >
                                                    <span className="text-lg font-bold text-black">
                                                        {driver.driver_number || '?'}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Driver Info */}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center space-x-2 mb-1">
                                                    {/* Team color indicator */}
                                                    {driver.constructor_color && (
                                                        <div
                                                            className="w-1 h-4 rounded"
                                                            style={{ backgroundColor: driver.constructor_color }}
                                                        ></div>
                                                    )}
                                                    <p className="text-white font-semibold font-titillium">
                                                        {driver.first_name}
                                                    </p>
                                                </div>

                                                <p className="text-gray-300 font-bold text-sm mb-1">
                                                    {driver.last_name?.toUpperCase()}
                                                </p>

                                                {driver.constructor_short_name && (
                                                    <p className="text-gray-400 text-xs mb-2">
                                                        {driver.constructor_short_name}
                                                    </p>
                                                )}

                                                {/* Recent Results */}
                                                <div className="flex items-center space-x-1 mb-2">
                                                    {recentResults.map((result, index) => (
                                                        <div key={index} className="flex flex-col items-center">
                                                            <div className={`text-xs font-bold px-1 py-0.5 rounded ${result.position <= 10
                                                                ? 'bg-green-600 text-white'
                                                                : 'bg-gray-600 text-gray-300'
                                                                }`}>
                                                                {result.position}
                                                            </div>
                                                            <div className="text-xs mt-1">{result.circuit}</div>
                                                        </div>
                                                    ))}
                                                </div>

                                                {/* Price */}
                                                <p className="text-yellow-400 font-bold text-sm">
                                                    ${driver.price?.toFixed(1) || '0.0'} mln
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default DriverSelectionModal;
