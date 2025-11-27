import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import AuthPages from './components/AuthPages';
import Dashboard from './components/Dashboard';
import Navigation from './components/common/Navigation';
import ErrorBoundary from './components/common/ErrorBoundary';
import ChampionshipsPage from './components/championships/ChampionshipsPage';
import LeaderboardPage from './components/championships/LeaderboardPage';
import RacesPage from './components/races/RacesPage';
import CreateLineupPage from './components/lineup/CreateLineupPage';
import AdminPanel from './components/admin/AdminPanel';

// Protected Route Component
const ProtectedRoute = ({ children }) => {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="min-h-screen bg-dark-100 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
          <p className="text-gray-400">Loading...</p>
        </div>
      </div>
    );
  }

  return isAuthenticated ? children : <Navigate to="/auth" replace />;
};

// Main App Content Component
const AppContent = () => {
  const { isAuthenticated, isLoading } = useAuth();

  // Show loading spinner during initial auth check
  if (isLoading) {
    return (
      <div className="min-h-screen bg-dark-100 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
          <p className="text-gray-400">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <BrowserRouter>
      <div className="min-h-screen bg-dark-100">
        {isAuthenticated && <Navigation />}
        <Routes>
          <Route
            path="/auth"
            element={isAuthenticated ? <Navigate to="/" replace /> : <AuthPages />}
          />
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute>
            }
          />
          <Route
            path="/championships"
            element={
              <ProtectedRoute>
                <ChampionshipsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/championships/:championshipId/leaderboard"
            element={
              <ProtectedRoute>
                <LeaderboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/races"
            element={
              <ProtectedRoute>
                <RacesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/lineup/:raceId/:championshipId"
            element={
              <ProtectedRoute>
                <CreateLineupPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/results"
            element={
              <ProtectedRoute>
                <div>Results Page - Coming Soon</div>
              </ProtectedRoute>
            }
          />
          <Route
            path="/admin"
            element={
              <ProtectedRoute>
                <AdminPanel />
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </BrowserRouter>
  );
};

// Main App Component with Auth Provider
function App() {
  return (
    <ErrorBoundary>
      <AuthProvider>
        <div className="App">
          <AppContent />
        </div>
      </AuthProvider>
    </ErrorBoundary>
  );
}

export default App;
