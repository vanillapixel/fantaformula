import React, { useEffect, useState, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { LineupProvider, useLineup } from '../../contexts/LineupContext';
import BudgetProgressBar from './BudgetProgressBar';
import DRSStatusIndicator from './DRSStatusIndicator';
import DriverSlot from './DriverSlot';
import DriverSelectionModal from './DriverSelectionModal';
import LoadingSpinner from '../common/LoadingSpinner';

// Inner component that has access to lineup context
const CreateLineupContent = () => {
    const navigate = useNavigate();
    const {
        selectedDrivers,
        totalCost,
        maxBudget,
        maxDriversCount,
        drsEnabled,
        drsForced,
        loading,
        error,
        openDriverModal,
        removeDriver,
        saveLineup,
        resetLineup,
        loadLineupData
    } = useLineup();

    const [saveLoading, setSaveLoading] = useState(false);
    const [saveSuccess, setSaveSuccess] = useState(false);
    const hasLoadedRef = useRef(false);

    const isOverBudget = totalCost > maxBudget;
    const hasEmptySlots = selectedDrivers.some(driver => driver === null);
    const canSave = !isOverBudget && !hasEmptySlots && !loading && !saveLoading;

    useEffect(() => {
        // Load lineup data when component mounts - only run once
        if (!hasLoadedRef.current) {
            hasLoadedRef.current = true;
            console.log('CreateLineupContent: Loading lineup data...');
            loadLineupData();
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleSaveLineup = async () => {
        if (!canSave) return;

        setSaveLoading(true);
        setSaveSuccess(false);

        try {
            const result = await saveLineup();

            if (result.success) {
                setSaveSuccess(true);
                setTimeout(() => setSaveSuccess(false), 3000);
            }
        } catch (error) {
            console.error('Error saving lineup:', error);
        } finally {
            setSaveLoading(false);
        }
    };

    const handleResetLineup = () => {
        if (window.confirm('Are you sure you want to reset your lineup? This will remove all selected drivers.')) {
            resetLineup();
        }
    };

    const handleGoBack = () => {
        navigate(-1);
    };

    if (loading && selectedDrivers.length === 0) {
        return <LoadingSpinner message="Loading lineup..." />;
    }

    // Show error if race/championship data failed to load
    if (error && !selectedDrivers.length && !loading) {
        return (
            <div className="min-h-screen bg-dark-100 flex items-center justify-center px-4">
                <div className="text-center max-w-md">
                    <div className="text-6xl mb-4">⚠️</div>
                    <h2 className="text-xl text-white font-semibold mb-2">Failed to Load Race Data</h2>
                    <p className="text-gray-400 mb-4">{error}</p>
                    <button
                        onClick={handleGoBack}
                        className="btn-primary"
                    >
                        Go Back
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="max-w-md mx-auto px-4 py-6">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <button
                        onClick={handleGoBack}
                        className="w-10 h-10 bg-white hover:bg-gray-100 text-gray-800 rounded-full flex items-center justify-center transition-colors border border-gray-300"
                    >
                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                    </button>

                    <h1 className="text-xl font-bold text-gray-800 font-titillium uppercase">
                        PILOTI
                    </h1>

                    <button
                        onClick={handleResetLineup}
                        className="text-gray-600 hover:text-gray-800 transition-colors"
                    >
                        <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clipRule="evenodd" />
                        </svg>
                    </button>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="mb-4 p-3 bg-red-900 border border-red-700 rounded text-red-200 text-sm">
                        {error}
                    </div>
                )}

                {/* DRS Status */}
                <div className="mb-6">
                    <DRSStatusIndicator
                        enabled={drsEnabled}
                        forced={drsForced}
                    />
                </div>

                {/* Budget Progress Bar */}
                <div className="mb-6">
                    <BudgetProgressBar
                        totalCost={totalCost}
                        maxBudget={maxBudget}
                    />
                </div>

                {/* Driver Slots Grid */}
                <div className="mb-6">
                    <h2 className="text-lg font-semibold text-white mb-4 font-titillium">
                        PILOTI ({selectedDrivers.filter(d => d !== null).length}/{maxDriversCount})
                    </h2>

                    <div className="grid grid-cols-3 gap-4">
                        {Array.from({ length: maxDriversCount }, (_, index) => (
                            <DriverSlot
                                key={index}
                                driver={selectedDrivers[index]}
                                slotIndex={index}
                                onSlotClick={openDriverModal}
                                onRemoveDriver={removeDriver}
                            />
                        ))}
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="space-y-3">
                    {/* Save Button */}
                    <button
                        onClick={handleSaveLineup}
                        disabled={!canSave}
                        className={`w-full py-4 rounded-lg font-bold transition-all duration-200 ${canSave
                            ? 'bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white'
                            : 'bg-gray-600 text-gray-400 cursor-not-allowed'
                            }`}
                    >
                        {saveLoading ? (
                            <div className="flex items-center justify-center">
                                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                                Saving...
                            </div>
                        ) : (
                            'Save Lineup'
                        )}
                    </button>

                    {/* Success Message */}
                    {saveSuccess && (
                        <div className="p-3 bg-green-900 border border-green-700 rounded text-green-200 text-sm text-center">
                            ✅ Lineup saved successfully!
                        </div>
                    )}

                    {/* Validation Messages */}
                    {!canSave && !loading && (
                        <div className="space-y-2">
                            {isOverBudget && (
                                <p className="text-red-400 text-sm text-center">
                                    ⚠️ Budget exceeded by ${(totalCost - maxBudget).toFixed(1)}
                                </p>
                            )}
                            {hasEmptySlots && (
                                <p className="text-yellow-400 text-sm text-center">
                                    ⚠️ Please select all {maxDriversCount} drivers
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Driver Selection Modal */}
            <DriverSelectionModal />
        </div>
    );
};

// Main component with lineup provider
const CreateLineupPage = () => {
    const { raceId, championshipId } = useParams();

    // Convert params to numbers
    const raceIdNum = parseInt(raceId);
    const championshipIdNum = parseInt(championshipId);

    if (!raceIdNum || !championshipIdNum || isNaN(raceIdNum) || isNaN(championshipIdNum)) {
        return (
            <div className="min-h-screen bg-dark-100 flex items-center justify-center">
                <div className="text-center">
                    <div className="text-6xl mb-4">❌</div>
                    <h2 className="text-xl text-white font-semibold mb-2">Invalid Parameters</h2>
                    <p className="text-gray-400">
                        Race ID ({raceId}) and Championship ID ({championshipId}) must be valid numbers.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <LineupProvider raceId={raceIdNum} championshipId={championshipIdNum}>
            <CreateLineupContent />
        </LineupProvider>
    );
};

export default CreateLineupPage;
