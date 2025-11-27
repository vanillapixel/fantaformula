import React from 'react';

const BudgetProgressBar = ({ totalCost, maxBudget, className = '' }) => {
    const percentage = Math.min((totalCost / maxBudget) * 100, 100);
    const remaining = Math.max(maxBudget - totalCost, 0);
    const isOverBudget = totalCost > maxBudget;

    return (
        <div className={`w-full ${className}`}>
            {/* Budget Info */}
            <div className="flex justify-between items-center mb-2">
                <div className="flex items-center space-x-2">
                    <span className="text-yellow-400 text-lg">💰</span>
                    <span className="text-white font-semibold">Budget</span>
                </div>
                <div className="text-right">
                    <div className={`text-lg font-bold ${isOverBudget ? 'text-red-400' : 'text-white'}`}>
                        ${totalCost.toFixed(1)} / ${maxBudget.toFixed(1)}
                    </div>
                    <div className={`text-sm ${isOverBudget ? 'text-red-400' : 'text-green-400'}`}>
                        {isOverBudget ? `Over by $${(totalCost - maxBudget).toFixed(1)}` : `$${remaining.toFixed(1)} remaining`}
                    </div>
                </div>
            </div>

            {/* Futuristic Progress Bar Container */}
            <div className="relative">
                {/* Background Bar */}
                <div className="h-8 bg-gray-800 border border-gray-600 relative overflow-hidden">
                    {/* Skewed background pattern */}
                    <div className="absolute inset-0">
                        <div className="h-full bg-gradient-to-r from-gray-700 to-gray-800 transform skew-x-12 -translate-x-4"></div>
                    </div>

                    {/* Progress Fill */}
                    <div
                        className={`absolute top-0 left-0 h-full transition-all duration-500 ease-out ${isOverBudget
                                ? 'bg-gradient-to-r from-red-500 to-red-600'
                                : 'bg-gradient-to-r from-yellow-400 to-yellow-500'
                            }`}
                        style={{ width: `${Math.min(percentage, 100)}%` }}
                    >
                        {/* Skewed progress pattern */}
                        <div className="h-full bg-gradient-to-r from-transparent via-white to-transparent opacity-20 transform skew-x-12 -translate-x-4"></div>

                        {/* Animated stripes */}
                        <div className="absolute inset-0 bg-gradient-to-r from-transparent via-black to-transparent opacity-10 transform skew-x-12 animate-pulse"></div>
                    </div>

                    {/* Glass effect overlay */}
                    <div className="absolute inset-0 bg-gradient-to-b from-white to-transparent opacity-10"></div>

                    {/* Border glow effect */}
                    {isOverBudget && (
                        <div className="absolute inset-0 border-2 border-red-400 opacity-60 animate-pulse"></div>
                    )}
                </div>

                {/* Progress indicator dot */}
                <div
                    className={`absolute top-1/2 transform -translate-y-1/2 w-3 h-3 rounded-full transition-all duration-500 ${isOverBudget ? 'bg-red-400 shadow-red-400' : 'bg-yellow-400 shadow-yellow-400'
                        } shadow-lg`}
                    style={{
                        left: `${Math.min(percentage, 100)}%`,
                        marginLeft: '-6px'
                    }}
                >
                    {/* Glow effect */}
                    <div className={`absolute inset-0 rounded-full ${isOverBudget ? 'bg-red-400' : 'bg-yellow-400'
                        } animate-pulse opacity-60`}></div>
                </div>

                {/* Percentage labels */}
                <div className="absolute inset-0 flex items-center justify-center">
                    <span className="text-xs font-bold text-black drop-shadow-sm">
                        {percentage.toFixed(0)}%
                    </span>
                </div>
            </div>

            {/* Warning message for over budget */}
            {isOverBudget && (
                <div className="mt-2 p-2 bg-red-900 bg-opacity-50 border border-red-600 rounded text-red-200 text-sm">
                    ⚠️ Budget exceeded! Remove some drivers to continue.
                </div>
            )}
        </div>
    );
};

export default BudgetProgressBar;
