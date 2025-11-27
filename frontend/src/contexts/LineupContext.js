import React, { createContext, useContext, useReducer, useCallback } from 'react';
import { driversAPI, lineupsAPI } from '../services/api';

// Initial state
const initialState = {
    selectedDrivers: [],
    availableDrivers: [],
    totalCost: 0,
    maxBudget: 250,
    maxDriversCount: 6,
    isModalOpen: false,
    selectedSlotIndex: null,
    raceId: null,
    championshipId: null,
    loading: false,
    error: null,
    drsEnabled: false,
    drsForced: false,
    season: null,
};

// Action types
const ACTIONS = {
    SET_RACE_DATA: 'SET_RACE_DATA',
    SET_AVAILABLE_DRIVERS: 'SET_AVAILABLE_DRIVERS',
    SET_SELECTED_DRIVERS: 'SET_SELECTED_DRIVERS',
    SELECT_DRIVER: 'SELECT_DRIVER',
    REMOVE_DRIVER: 'REMOVE_DRIVER',
    OPEN_MODAL: 'OPEN_MODAL',
    CLOSE_MODAL: 'CLOSE_MODAL',
    SET_LOADING: 'SET_LOADING',
    SET_ERROR: 'SET_ERROR',
    SET_DRS_STATUS: 'SET_DRS_STATUS',
    RESET_LINEUP: 'RESET_LINEUP',
};

// Reducer function
const lineupReducer = (state, action) => {
    switch (action.type) {
        case ACTIONS.SET_RACE_DATA:
            return {
                ...state,
                raceId: action.payload.raceId,
                championshipId: action.payload.championshipId,
                maxBudget: action.payload.maxBudget || 250,
                maxDriversCount: action.payload.maxDriversCount || 6,
                season: action.payload.season,
            };

        case ACTIONS.SET_AVAILABLE_DRIVERS:
            return {
                ...state,
                availableDrivers: action.payload,
            };

        case ACTIONS.SET_SELECTED_DRIVERS:
            const totalCost = action.payload.reduce((sum, driver) => sum + (driver?.price || 0), 0);
            return {
                ...state,
                selectedDrivers: action.payload,
                totalCost,
            };

        case ACTIONS.SELECT_DRIVER:
            const newSelectedDrivers = [...state.selectedDrivers];
            newSelectedDrivers[action.payload.slotIndex] = action.payload.driver;
            const newTotalCost = newSelectedDrivers.reduce((sum, driver) => sum + (driver?.price || 0), 0);

            return {
                ...state,
                selectedDrivers: newSelectedDrivers,
                totalCost: newTotalCost,
                isModalOpen: false,
                selectedSlotIndex: null,
            };

        case ACTIONS.REMOVE_DRIVER:
            const updatedDrivers = [...state.selectedDrivers];
            updatedDrivers[action.payload] = null;
            const updatedTotalCost = updatedDrivers.reduce((sum, driver) => sum + (driver?.price || 0), 0);

            return {
                ...state,
                selectedDrivers: updatedDrivers,
                totalCost: updatedTotalCost,
            };

        case ACTIONS.OPEN_MODAL:
            return {
                ...state,
                isModalOpen: true,
                selectedSlotIndex: action.payload,
            };

        case ACTIONS.CLOSE_MODAL:
            return {
                ...state,
                isModalOpen: false,
                selectedSlotIndex: null,
            };

        case ACTIONS.SET_LOADING:
            return {
                ...state,
                loading: action.payload,
            };

        case ACTIONS.SET_ERROR:
            return {
                ...state,
                error: action.payload,
                loading: false,
            };

        case ACTIONS.SET_DRS_STATUS:
            return {
                ...state,
                drsEnabled: action.payload.enabled,
                drsForced: action.payload.forced,
            };

        case ACTIONS.RESET_LINEUP:
            return {
                ...state,
                selectedDrivers: Array(state.maxDriversCount).fill(null),
                totalCost: 0,
            };

        default:
            return state;
    }
};

// Create context
const LineupContext = createContext();

// LineupProvider component
export const LineupProvider = ({ children, raceId, championshipId }) => {
    const [state, dispatch] = useReducer(lineupReducer, {
        ...initialState,
        selectedDrivers: Array(initialState.maxDriversCount).fill(null),
    });

    const loadLineupData = useCallback(async () => {
        // Prevent multiple simultaneous calls
        if (state.loading) {
            return;
        }

        try {
            dispatch({ type: ACTIONS.SET_LOADING, payload: true });

            // Load available drivers for the race
            const driversResponse = await driversAPI.getAll(raceId);
            if (driversResponse.success) {
                dispatch({ type: ACTIONS.SET_AVAILABLE_DRIVERS, payload: driversResponse.data.drivers || [] });

                // Set race data from response
                const raceData = driversResponse.data.race;
                if (raceData) {
                    dispatch({
                        type: ACTIONS.SET_RACE_DATA,
                        payload: {
                            raceId,
                            championshipId,
                            maxBudget: raceData.effective_budget,
                            maxDriversCount: driversResponse.data.max_drivers_count || 6,
                            season: raceData.season_year,
                        },
                    });
                }
            } else {
                throw new Error(driversResponse.error || `Race ${raceId} not found or no drivers available`);
            }

            // Try to load existing lineup
            try {
                const lineupResponse = await lineupsAPI.getLineup(raceId, championshipId);
                if (lineupResponse.success && lineupResponse.data.drivers) {
                    const existingDrivers = Array(state.maxDriversCount).fill(null);
                    lineupResponse.data.drivers.forEach((driver, index) => {
                        if (index < state.maxDriversCount) {
                            existingDrivers[index] = driver;
                        }
                    });
                    dispatch({ type: ACTIONS.SET_SELECTED_DRIVERS, payload: existingDrivers });

                    // Set DRS status if available
                    if (lineupResponse.data.drs_enabled !== undefined) {
                        dispatch({
                            type: ACTIONS.SET_DRS_STATUS,
                            payload: {
                                enabled: lineupResponse.data.drs_enabled,
                                forced: lineupResponse.data.drs_forced || false,
                            },
                        });
                    }
                }
            } catch (lineupError) {
                // No existing lineup found - this is fine for new lineups
                console.log('No existing lineup found');
            }

        } catch (error) {
            console.error('Error loading lineup data:', error);

            // Provide more specific error messages
            let errorMessage = 'Failed to load lineup data';
            if (error.response?.status === 404) {
                errorMessage = 'Race or championship not found';
            } else if (error.response?.status === 401) {
                errorMessage = 'Authentication required - please login again';
            } else if (error.response?.data?.message) {
                errorMessage = error.response.data.message;
            } else if (error.message) {
                errorMessage = error.message;
            }

            dispatch({ type: ACTIONS.SET_ERROR, payload: errorMessage });
        } finally {
            dispatch({ type: ACTIONS.SET_LOADING, payload: false });
        }
    }, [raceId, championshipId, state.maxDriversCount, state.loading]);

    const selectDriver = (driver, slotIndex) => {
        dispatch({
            type: ACTIONS.SELECT_DRIVER,
            payload: { driver, slotIndex },
        });
    };

    const removeDriver = (slotIndex) => {
        dispatch({ type: ACTIONS.REMOVE_DRIVER, payload: slotIndex });
    };

    const openDriverModal = (slotIndex) => {
        dispatch({ type: ACTIONS.OPEN_MODAL, payload: slotIndex });
    };

    const closeDriverModal = () => {
        dispatch({ type: ACTIONS.CLOSE_MODAL });
    };

    const saveLineup = async () => {
        try {
            dispatch({ type: ACTIONS.SET_LOADING, payload: true });

            const driverIds = state.selectedDrivers
                .filter(driver => driver !== null)
                .map(driver => driver.id);

            const lineupData = {
                race_id: raceId,
                championship_id: championshipId,
                driver_ids: driverIds,
                drs_enabled: state.drsEnabled,
            };

            const response = await lineupsAPI.saveLineup(lineupData);

            if (response.success) {
                return { success: true, data: response.data };
            } else {
                throw new Error(response.error || 'Failed to save lineup');
            }
        } catch (error) {
            console.error('Error saving lineup:', error);
            dispatch({ type: ACTIONS.SET_ERROR, payload: error.message });
            return { success: false, error: error.message };
        } finally {
            dispatch({ type: ACTIONS.SET_LOADING, payload: false });
        }
    };

    const resetLineup = () => {
        dispatch({ type: ACTIONS.RESET_LINEUP });
    };

    const getAvailableDriversForSlot = (slotIndex) => {
        const selectedDriverIds = state.selectedDrivers
            .filter((driver, index) => driver !== null && index !== slotIndex)
            .map(driver => driver.id);

        return state.availableDrivers.filter(driver => !selectedDriverIds.includes(driver.id));
    };

    const contextValue = {
        ...state,
        selectDriver,
        removeDriver,
        openDriverModal,
        closeDriverModal,
        saveLineup,
        resetLineup,
        getAvailableDriversForSlot,
        loadLineupData,
    };

    return (
        <LineupContext.Provider value={contextValue}>
            {children}
        </LineupContext.Provider>
    );
};

// Custom hook to use the lineup context
export const useLineup = () => {
    const context = useContext(LineupContext);
    if (!context) {
        throw new Error('useLineup must be used within a LineupProvider');
    }
    return context;
};

export default LineupContext;
