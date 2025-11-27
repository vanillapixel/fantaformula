import React from 'react';

const DRSStatusIndicator = ({ enabled, forced, className = '' }) => {
    const isOn = enabled || forced;

    return (
        <div className={`flex items-center justify-between p-4 bg-gray-800 border border-gray-600 rounded-lg ${className}`}>
            <div className="flex items-center space-x-3">
                <span className="text-white font-semibold font-titillium">DRS:</span>

                {/* Toggle Switch Visual */}
                <div className={`relative w-16 h-8 rounded-full border-2 transition-all duration-300 ${isOn
                        ? 'bg-purple-600 border-purple-500 shadow-purple-500/50'
                        : 'bg-gray-600 border-gray-500'
                    } shadow-lg`}>
                    {/* Switch handle */}
                    <div className={`absolute top-0.5 w-6 h-6 rounded-full transition-all duration-300 ${isOn
                            ? 'bg-white transform translate-x-8 shadow-lg'
                            : 'bg-gray-300 transform translate-x-0.5'
                        }`}>
                        {/* Inner glow when active */}
                        {isOn && (
                            <div className="absolute inset-0 rounded-full bg-purple-200 opacity-60 animate-pulse"></div>
                        )}
                    </div>

                    {/* Background glow when active */}
                    {isOn && (
                        <div className="absolute inset-0 rounded-full bg-purple-400 opacity-30 blur-sm"></div>
                    )}
                </div>

                {/* Status Text */}
                <span className={`font-bold text-lg ${isOn ? 'text-purple-400' : 'text-red-400'
                    }`}>
                    {isOn ? 'ON' : 'OFF'}
                </span>
            </div>

            {/* Status Information */}
            <div className="text-right">
                {forced ? (
                    <div className="text-xs text-purple-300">
                        <div className="font-semibold">ENFORCED</div>
                        <div className="text-gray-400">Qualify time not passed</div>
                    </div>
                ) : (
                    <div className="text-xs text-gray-400">
                        <div>Manual Selection</div>
                        <div>{isOn ? '1.2x multiplier' : 'No multiplier'}</div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default DRSStatusIndicator;
