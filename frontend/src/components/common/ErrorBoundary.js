import React from 'react';

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        this.setState({
            error: error,
            errorInfo: errorInfo
        });
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen bg-dark-100 flex items-center justify-center">
                    <div className="max-w-md mx-auto text-center">
                        <div className="text-6xl mb-4">😵</div>
                        <h2 className="text-2xl font-bold text-white mb-4">Something went wrong</h2>
                        <p className="text-gray-400 mb-6">
                            An unexpected error occurred. Please refresh the page or try again later.
                        </p>
                        <button
                            onClick={() => window.location.reload()}
                            className="btn-primary"
                        >
                            Refresh Page
                        </button>

                        {/* Error details for development */}
                        {process.env.NODE_ENV === 'development' && (
                            <details className="mt-6 text-left">
                                <summary className="text-sm text-gray-500 cursor-pointer hover:text-gray-400">
                                    Error Details (Development Only)
                                </summary>
                                <div className="mt-2 p-3 bg-gray-800 rounded text-xs text-red-300 overflow-auto">
                                    <p className="font-bold">Error:</p>
                                    <pre>{this.state.error && this.state.error.toString()}</pre>
                                    <p className="font-bold mt-2">Component Stack:</p>
                                    <pre>{this.state.errorInfo && this.state.errorInfo.componentStack ? this.state.errorInfo.componentStack : 'No stack available'}</pre>
                                </div>
                            </details>
                        )}
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
