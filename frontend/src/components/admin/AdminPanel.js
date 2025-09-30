import React from 'react';
import { useAuth } from '../../contexts/AuthContext';

const AdminPanel = () => {
    const { user } = useAuth();

    // Redirect if not super admin
    if (!user?.is_super_admin) {
        return (
            <div className="min-h-screen bg-dark-100 flex items-center justify-center">
                <div className="text-center">
                    <div className="text-6xl mb-4">🚫</div>
                    <h2 className="text-2xl font-bold text-white mb-2">Access Denied</h2>
                    <p className="text-gray-400">You don't have permission to access the admin panel.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-dark-100">
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-white font-titillium mb-2">
                        Admin Panel
                    </h1>
                    <p className="text-gray-400">
                        System administration and content management
                    </p>
                </div>

                {/* Admin Cards Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {/* Results Management */}
                    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
                        <div className="flex items-center mb-4">
                            <div className="bg-primary rounded-lg p-3 mr-4">
                                <span className="text-white text-xl">📊</span>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white">Results Management</h3>
                                <p className="text-gray-400 text-sm">Submit and manage race results</p>
                            </div>
                        </div>
                        <button className="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">
                            Manage Results
                        </button>
                    </div>

                    {/* User Management */}
                    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
                        <div className="flex items-center mb-4">
                            <div className="bg-primary rounded-lg p-3 mr-4">
                                <span className="text-white text-xl">👥</span>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white">User Management</h3>
                                <p className="text-gray-400 text-sm">Manage user accounts and permissions</p>
                            </div>
                        </div>
                        <button className="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">
                            Manage Users
                        </button>
                    </div>

                    {/* Championship Management */}
                    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
                        <div className="flex items-center mb-4">
                            <div className="bg-primary rounded-lg p-3 mr-4">
                                <span className="text-white text-xl">🏆</span>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white">Championships</h3>
                                <p className="text-gray-400 text-sm">Manage all championships</p>
                            </div>
                        </div>
                        <button className="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">
                            Manage Championships
                        </button>
                    </div>

                    {/* Race Management */}
                    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
                        <div className="flex items-center mb-4">
                            <div className="bg-primary rounded-lg p-3 mr-4">
                                <span className="text-white text-xl">🏎️</span>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white">Race Calendar</h3>
                                <p className="text-gray-400 text-sm">Manage races and schedules</p>
                            </div>
                        </div>
                        <button className="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">
                            Manage Races
                        </button>
                    </div>

                    {/* Driver & Constructor Management */}
                    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
                        <div className="flex items-center mb-4">
                            <div className="bg-primary rounded-lg p-3 mr-4">
                                <span className="text-white text-xl">🏁</span>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white">Drivers & Constructors</h3>
                                <p className="text-gray-400 text-sm">Manage driver and constructor data</p>
                            </div>
                        </div>
                        <button className="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">
                            Manage Drivers
                        </button>
                    </div>

                    {/* System Settings */}
                    <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
                        <div className="flex items-center mb-4">
                            <div className="bg-primary rounded-lg p-3 mr-4">
                                <span className="text-white text-xl">⚙️</span>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white">System Settings</h3>
                                <p className="text-gray-400 text-sm">Configure system parameters</p>
                            </div>
                        </div>
                        <button className="w-full bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg transition-colors">
                            System Settings
                        </button>
                    </div>
                </div>

                {/* Quick Stats */}
                <div className="mt-8">
                    <h2 className="text-xl font-bold text-white mb-4">System Overview</h2>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="bg-gray-800 rounded-lg p-4 border border-gray-700">
                            <div className="text-2xl font-bold text-primary mb-1">-</div>
                            <div className="text-sm text-gray-400">Total Users</div>
                        </div>
                        <div className="bg-gray-800 rounded-lg p-4 border border-gray-700">
                            <div className="text-2xl font-bold text-primary mb-1">-</div>
                            <div className="text-sm text-gray-400">Active Championships</div>
                        </div>
                        <div className="bg-gray-800 rounded-lg p-4 border border-gray-700">
                            <div className="text-2xl font-bold text-primary mb-1">-</div>
                            <div className="text-sm text-gray-400">Completed Races</div>
                        </div>
                        <div className="bg-gray-800 rounded-lg p-4 border border-gray-700">
                            <div className="text-2xl font-bold text-primary mb-1">-</div>
                            <div className="text-sm text-gray-400">Upcoming Races</div>
                        </div>
                    </div>
                </div>

                {/* Development Notice */}
                <div className="mt-8 p-4 bg-yellow-900 bg-opacity-50 border border-yellow-700 rounded-lg">
                    <div className="flex items-center">
                        <span className="text-yellow-400 text-xl mr-3">🚧</span>
                        <div>
                            <h3 className="text-yellow-200 font-semibold">Development Notice</h3>
                            <p className="text-yellow-300 text-sm">
                                Admin panel features are currently under development. Full functionality will be available soon.
                            </p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
};

export default AdminPanel;
