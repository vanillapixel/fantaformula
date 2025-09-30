import React, { useState } from 'react';
import { EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';
import { useAuth } from '../contexts/AuthContext';

const RegisterPage = ({ onSwitchToLogin }) => {
    const { register, isLoading, error, clearError } = useAuth();
    const [formData, setFormData] = useState({
        username: '',
        email: '',
        password: '',
        confirmPassword: ''
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [validationErrors, setValidationErrors] = useState({});

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
        if (validationErrors[name]) {
            setValidationErrors(prev => ({ ...prev, [name]: undefined }));
        }
        if (error) clearError();
    };

    const validateForm = () => {
        const errors = {};
        if (!formData.username.trim()) {
            errors.username = 'Username obbligatorio';
        } else if (formData.username.length < 3) {
            errors.username = 'Minimo 3 caratteri';
        }
        if (!formData.email.trim()) {
            errors.email = 'Email obbligatoria';
        } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
            errors.email = 'Email non valida';
        }
        if (!formData.password) {
            errors.password = 'Password obbligatoria';
        } else if (formData.password.length < 6) {
            errors.password = 'Minimo 6 caratteri';
        }
        if (!formData.confirmPassword) {
            errors.confirmPassword = 'Conferma la password';
        } else if (formData.password !== formData.confirmPassword) {
            errors.confirmPassword = 'Le password non coincidono';
        }
        return errors;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        clearError();
        const errors = validateForm();
        setValidationErrors(errors);
        if (Object.keys(errors).length) return;
        const result = await register({
            username: formData.username,
            email: formData.email,
            password: formData.password
        });
        if (result.success) {
            // After successful registration, switch to login view
            onSwitchToLogin();
        }
    };

    return (
        <div className="min-h-screen bg-dark-200 flex items-center justify-center px-6 py-10 font-titillium">
            <div className="w-full max-w-xs">
                <h1 className="text-white text-sm tracking-widest mb-10 text-center font-normal">CREA IL TUO ACCOUNT</h1>
                <form onSubmit={handleSubmit} className="space-y-6">
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
                        {validationErrors.username && <p className="mt-1 text-[10px] text-red-400">{validationErrors.username}</p>}
                    </div>
                    <div>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            value={formData.email}
                            onChange={handleInputChange}
                            placeholder="Email"
                            className="w-full bg-transparent border-0 border-b border-gray-600 focus:border-primary focus:ring-0 text-sm text-white placeholder-gray-500 py-2"
                        />
                        {validationErrors.email && <p className="mt-1 text-[10px] text-red-400">{validationErrors.email}</p>}
                    </div>
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
                        {validationErrors.password && <p className="mt-1 text-[10px] text-red-400">{validationErrors.password}</p>}
                    </div>
                    <div className="relative">
                        <input
                            id="confirmPassword"
                            name="confirmPassword"
                            type={showConfirmPassword ? 'text' : 'password'}
                            required
                            value={formData.confirmPassword}
                            onChange={handleInputChange}
                            placeholder="Conferma Password"
                            className="w-full bg-transparent border-0 border-b border-gray-600 focus:border-primary focus:ring-0 text-sm text-white placeholder-gray-500 py-2 pr-8"
                        />
                        <button
                            type="button"
                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                            className="absolute right-0 top-1.5 text-gray-500 hover:text-gray-300"
                        >
                            {showConfirmPassword ? <EyeSlashIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
                        </button>
                        {validationErrors.confirmPassword && <p className="mt-1 text-[10px] text-red-400">{validationErrors.confirmPassword}</p>}
                    </div>
                    {error && (
                        <div className="text-xs text-red-400 bg-red-900/10 border border-red-500/40 rounded px-3 py-2">
                            {error}
                        </div>
                    )}
                    <button
                        type="submit"
                        disabled={isLoading}
                        className="w-full bg-primary hover:bg-[#c92424] transition-colors text-white text-sm font-medium py-2 rounded disabled:opacity-50"
                    >
                        {isLoading ? '...' : 'Registrati'}
                    </button>
                    <p className="text-center text-[11px] text-gray-500 pt-2">
                        Hai già un account?{' '}
                        <button
                            type="button"
                            onClick={onSwitchToLogin}
                            className="text-primary hover:underline"
                        >
                            Accedi
                        </button>
                    </p>
                </form>
            </div>
        </div>
    );
};

export default RegisterPage;
