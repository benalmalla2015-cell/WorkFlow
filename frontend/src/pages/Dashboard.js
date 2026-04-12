import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useEffect } from 'react';

const Dashboard = () => {
  const { user } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    if (user) {
      if (user.role === 'admin') {
        navigate('/admin/dashboard', { replace: true });
      } else if (user.role === 'sales') {
        navigate('/sales/orders', { replace: true });
      } else if (user.role === 'factory') {
        navigate('/factory/orders', { replace: true });
      }
    }
  }, [user, navigate]);

  return null;
};

export default Dashboard;
