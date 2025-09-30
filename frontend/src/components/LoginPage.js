import React, { useState } from 'react';
import { EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';
import { useAuth } from '../contexts/AuthContext';

const LoginPage = ({ onSwitchToRegister }) => {
    const { login, isLoading, error, clearError } = useAuth();
    const [formData, setFormData] = useState({
        username: '', // Changed from email to username to match API
        password: '',
        rememberMe: false
    });
    const [showPassword, setShowPassword] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        clearError();

        const result = await login({
            username: formData.username,
            password: formData.password
        });

        if (result.success) {
            // Redirect will be handled by App component based on auth state
            console.log('Login successful!');
        }
        // Error handling is done in the AuthContext
    };

    const handleInputChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
    };

    return (
        <div className="min-h-screen bg-dark-200 flex items-center justify-center px-6 py-10 font-titillium">
            <div className="w-full max-w-xs">
                <h1 className="text-white text-sm tracking-widest mb-10 text-center font-normal">FANTASY FORMULA 1</h1>
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Username */}
                    <div>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            required
                            value={formData.username}
                            onChange={handleInputChange}
                            placeholder="Username"
                            className="w-full bg-transparent border-0 border-b border-gray-600 focus:border-primary focus:ring-0 text-sm text-white placeholder-gray-500 py-2"
                        />
                    </div>
                    {/* Password */}
                    <div className="relative">
                        <input
                            id="password"
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            required
                            value={formData.password}
                            onChange={handleInputChange}
                            placeholder="Password"
                            className="w-full bg-transparent border-0 border-b border-gray-600 focus:border-primary focus:ring-0 text-sm text-white placeholder-gray-500 py-2 pr-8"
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-0 top-1.5 text-gray-500 hover:text-gray-300"
                        >
                            {showPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                        </button>
                    </div>
                    {error && (
                        <div className="text-xs text-red-400 bg-red-900/10 border border-red-500/40 rounded px-3 py-2">
                            {error}
                        </div>
                    )}
                    <div className="flex justify-end">
                        <button type="button" className="text-[10px] tracking-wide text-gray-400 hover:text-white">
                            Password dimenticata?
                        </button>
                    </div>
                    <button
                        type="submit"
                        disabled={isLoading}
                        className="w-full bg-primary hover:bg-[#c92424] transition-colors text-white text-sm font-medium py-2 rounded disabled:opacity-50"
                    >
                        {isLoading ? '...' : 'Login'}
                    </button>
                    <p className="text-center text-[11px] text-gray-500 pt-2">
                        Non hai un account?{' '}
                        <button
                            type="button"
                            onClick={onSwitchToRegister}
                            className="text-primary hover:underline"
                        >
                            Registrati
                        </button>
                    </p>
                </form>
            </div>
        </div>
    );
};

export default LoginPage;
