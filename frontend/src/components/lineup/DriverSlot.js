import React from 'react';
import './DriverSlot.css';

const DriverSlot = ({
    driver,
    slotIndex,
    onSlotClick,
    onRemoveDriver,
    isSelected = false,
    className = ''
}) => {
    const isEmpty = !driver;

    const handleClick = () => {
        if (onSlotClick) {
            onSlotClick(slotIndex);
        }
    };

    const handleRemove = (e) => {
        e.stopPropagation();
        if (onRemoveDriver) {
            onRemoveDriver(slotIndex);
        }
    };

    // Placeholder for empty slots
    const PlaceholderContent = () => (
        <div className="flex flex-col items-center justify-center h-full text-center">
            <div className="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center mb-2">
                <svg className="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                </svg>
            </div>
            <p className="text-gray-400 text-xs font-medium">
                Select Driver
            </p>
        </div>
    );

    if (isEmpty) {
        return (
            <div
                className={`driver-slot
          relative bg-white p-2
          cursor-pointer transition-all duration-200 hover:bg-gray-50
          min-h-[140px] flex items-center justify-center
          ${isSelected ? 'border-primary bg-gray-50' : ''}
          ${className}
        `}
                onClick={handleClick}
            >
                <PlaceholderContent />
            </div>
        );
    }

    return (
        <div
            className={`driver-slot
        relative bg-white overflow-hidden
        cursor-pointer transition-all duration-200 hover:shadow-lg
        ${isSelected ? 'ring-2 ring-primary' : ''}
        ${className}
      `}
            onClick={handleClick}
        >
            {/* Remove button - shown on hover or always on mobile */}
            {driver && (
                <button
                    className="absolute top-2 right-2 w-6 h-6 bg-red-600 hover:bg-red-700 text-white rounded-full flex items-center justify-center transition-colors duration-200 z-10"
                    onClick={handleRemove}
                    title="Remove driver"
                >
                    <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                </button>
            )}

            {/* Driver content */}
            <div className="p-4 flex flex-col items-center">
                {/* Driver number - large and prominent */}
                <div className="w-full text-center mb-3">
                    {driver.logo_url ? (
                        <img
                            src={`${process.env.REACT_APP_API_BASE_URL || '/backend/api'}${driver.logo_url}`}
                            alt={`Driver #${driver.driver_number}`}
                            className="w-20 h-20 mx-auto object-contain"
                            onError={(e) => {
                                e.target.style.display = 'none';
                                e.target.nextSibling.style.display = 'block';
                            }}
                        />
                    ) : null}

                    {/* Fallback number display */}
                    <div
                        className={`text-6xl font-black leading-none text-gray-900 ${driver.logo_url ? 'hidden' : 'block'
                            }`}
                        style={{ display: driver.logo_url ? 'none' : 'block' }}
                    >
                        {driver.driver_number || '?'}
                    </div>
                </div>

                {/* Driver name with team color indicator */}
                <div
                    className="w-full mb-3 pl-2 border-l-4"
                    style={{ borderColor: driver.constructor_color || '#6B7280' }}
                >
                    <div className="flex items-center justify-start">
                        <span className="text-xs font-thin text-gray-500 uppercase tracking-wide">
                            {driver.first_name}
                        </span>
                    </div>
                    <div className="text-sm font-black text-gray-900 uppercase tracking-tight leading-tight">
                        {driver.last_name}
                    </div>
                </div>

                {/* Price */}
                <div className="text-sm text-gray-700 font-semibold">
                    {driver.price?.toFixed(1).replace('.', ',') || '0,0'} mln
                </div>
            </div>
        </div>
    );
};

export default DriverSlot;
