import React, { useState } from 'react';
import LoginPage from './LoginPage';
import RegisterPage from './RegisterPage';

const AuthPages = () => {
    const [isLogin, setIsLogin] = useState(true);

    return (
        <>
            {isLogin ? (
                <LoginPage onSwitchToRegister={() => setIsLogin(false)} />
            ) : (
                <RegisterPage onSwitchToLogin={() => setIsLogin(true)} />
            )}
        </>
    );
};

export default AuthPages;
