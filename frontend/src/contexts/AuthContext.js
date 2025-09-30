import React, { createContext, useContext, useReducer, useEffect } from 'react';
import { authAPI } from '../services/api';

// Auth Context
const AuthContext = createContext();

// Auth Actions
const AUTH_ACTIONS = {
    LOGIN_START: 'LOGIN_START',
    LOGIN_SUCCESS: 'LOGIN_SUCCESS',
    LOGIN_FAILURE: 'LOGIN_FAILURE',
    LOGOUT: 'LOGOUT',
    SET_USER: 'SET_USER',
    CLEAR_ERROR: 'CLEAR_ERROR'
};

// Auth Reducer
const authReducer = (state, action) => {
    switch (action.type) {
        case AUTH_ACTIONS.LOGIN_START:
            return {
                ...state,
                isLoading: true,
                error: null
            };
        case AUTH_ACTIONS.LOGIN_SUCCESS:
            return {
                ...state,
                isLoading: false,
                isAuthenticated: true,
                user: action.payload.user,
                token: action.payload.token,
                error: null
            };
        case AUTH_ACTIONS.LOGIN_FAILURE:
            return {
                ...state,
                isLoading: false,
                isAuthenticated: false,
                user: null,
                token: null,
                error: action.payload
            };
        case AUTH_ACTIONS.LOGOUT:
            return {
                ...state,
                isAuthenticated: false,
                user: null,
                token: null,
                error: null,
                isLoading: false
            };
        case AUTH_ACTIONS.SET_USER:
            return {
                ...state,
                user: action.payload,
                isAuthenticated: true
            };
        case AUTH_ACTIONS.CLEAR_ERROR:
            return {
                ...state,
                error: null
            };
        default:
            return state;
    }
};

// Initial state
const initialState = {
    isAuthenticated: false,
    user: null,
    token: null,
    isLoading: false,
    error: null
};

// Auth Provider Component
export const AuthProvider = ({ children }) => {
    const [state, dispatch] = useReducer(authReducer, initialState);

    // Check for existing auth on mount
    useEffect(() => {
        const token = localStorage.getItem('ff1_token');
        const user = localStorage.getItem('ff1_user');

        if (token && user) {
            try {
                const parsedUser = JSON.parse(user);
                dispatch({
                    type: AUTH_ACTIONS.LOGIN_SUCCESS,
                    payload: { token, user: parsedUser }
                });
            } catch (error) {
                // Invalid stored data, clear it
                localStorage.removeItem('ff1_token');
                localStorage.removeItem('ff1_user');
            }
        }
    }, []);

    // Login function
    const login = async (credentials) => {
        dispatch({ type: AUTH_ACTIONS.LOGIN_START });

        try {
            const response = await authAPI.login(credentials);

            if (response.success && response.data) {
                const { user, token } = response.data;

                // Store in localStorage
                localStorage.setItem('ff1_token', token);
                localStorage.setItem('ff1_user', JSON.stringify(user));

                dispatch({
                    type: AUTH_ACTIONS.LOGIN_SUCCESS,
                    payload: { user, token }
                });

                return { success: true };
            } else {
                throw new Error(response.message || 'Login failed');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Login failed';
            dispatch({
                type: AUTH_ACTIONS.LOGIN_FAILURE,
                payload: errorMessage
            });
            return { success: false, error: errorMessage };
        }
    };

    // Logout function
    const logout = () => {
        localStorage.removeItem('ff1_token');
        localStorage.removeItem('ff1_user');
        dispatch({ type: AUTH_ACTIONS.LOGOUT });
    };

    // Register function
    const register = async (userData) => {
        dispatch({ type: AUTH_ACTIONS.LOGIN_START });

        try {
            const response = await authAPI.register(userData);

            if (response.success) {
                // After successful registration, automatically log in
                return await login({
                    username: userData.username,
                    password: userData.password
                });
            } else {
                throw new Error(response.message || 'Registration failed');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message || 'Registration failed';
            dispatch({
                type: AUTH_ACTIONS.LOGIN_FAILURE,
                payload: errorMessage
            });
            return { success: false, error: errorMessage };
        }
    };

    // Clear error function
    const clearError = () => {
        dispatch({ type: AUTH_ACTIONS.CLEAR_ERROR });
    };

    // Update user profile
    const updateUser = (userData) => {
        localStorage.setItem('ff1_user', JSON.stringify(userData));
        dispatch({
            type: AUTH_ACTIONS.SET_USER,
            payload: userData
        });
    };

    const value = {
        ...state,
        login,
        logout,
        register,
        clearError,
        updateUser
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

// Custom hook to use auth context
export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};
