import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

const Navigation = () => {
    const { user, logout } = useAuth();
    const location = useLocation();

    const navItems = [
        { path: '/', label: 'Dashboard', icon: '🏠' },
        { path: '/championships', label: 'Championships', icon: '🏆' },
        { path: '/races', label: 'Races', icon: '🏎️' },
        { path: '/results', label: 'Results', icon: '📊' },
    ];

    // Add admin panel for super admins
    if (user?.is_super_admin) {
        navItems.push({ path: '/admin', label: 'Admin Panel', icon: '⚙️' });
    }

    const isActive = (path) => {
        if (path === '/') {
            return location.pathname === '/';
        }
        return location.pathname.startsWith(path);
    };

    return (
        <header className="bg-black border-b border-gray-800 sticky top-0 z-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center h-16">
                    {/* Logo and Brand */}
                    <div className="flex items-center">
                        <Link to="/" className="flex items-center space-x-2">
                            <h1 className="text-xl font-bold tracking-wide text-white font-titillium">
                                Slipstream
                            </h1>
                        </Link>
                    </div>

                    {/* Navigation Links */}
                    <nav className="hidden md:flex space-x-8">
                        {navItems.map((item) => (
                            <Link
                                key={item.path}
                                to={item.path}
                                className={`flex items-center space-x-2 px-3 py-2 rounded-md text-sm font-medium transition-colors ${isActive(item.path)
                                    ? 'bg-primary text-white'
                                    : 'text-gray-300 hover:text-white hover:bg-gray-700'
                                    }`}
                            >
                                <span>{item.icon}</span>
                                <span>{item.label}</span>
                            </Link>
                        ))}
                    </nav>

                    {/* User Avatar / Logout */}
                    <div className="flex items-center">
                        <button
                            onClick={logout}
                            aria-label="Logout"
                            title={user?.username ? `Logout ${user.username}` : 'Logout'}
                            className="h-9 w-9 flex items-center justify-center rounded-full bg-primary text-white font-semibold hover:opacity-90 transition-opacity focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-black"
                        >
                            {user?.username ? user.username.charAt(0).toUpperCase() : 'U'}
                        </button>
                    </div>
                </div>
            </div>
        </header>
    );
};

export default Navigation;
