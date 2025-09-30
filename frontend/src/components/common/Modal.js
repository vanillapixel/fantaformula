import React from 'react';

const Modal = ({ isOpen, onClose, title, children, maxWidth = 'max-w-md' }) => {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className={`bg-gray-800 rounded-lg ${maxWidth} w-full max-h-96 overflow-y-auto`}>
                <div className="p-6">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <h2 className="text-xl font-bold text-white font-titillium">
                            {title}
                        </h2>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-white text-2xl leading-none focus:outline-none"
                        >
                            ×
                        </button>
                    </div>

                    {/* Content */}
                    <div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Modal;
