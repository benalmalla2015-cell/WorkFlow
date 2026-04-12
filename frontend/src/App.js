import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from 'react-query';
import { ConfigProvider } from 'antd';
import { AuthProvider } from './contexts/AuthContext';
import PrivateRoute from './components/PrivateRoute';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import SalesOrders from './pages/sales/Orders';
import SalesOrderForm from './pages/sales/OrderForm';
import FactoryOrders from './pages/factory/Orders';
import FactoryOrderForm from './pages/factory/OrderForm';
import AdminDashboard from './pages/admin/Dashboard';
import UserManagement from './pages/admin/UserManagement';
import Settings from './pages/admin/Settings';
import AuditLogs from './pages/admin/AuditLogs';
import './App.css';

const queryClient = new QueryClient();

function App() {
  return (
    <ConfigProvider>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <Router>
            <div className="App">
              <Routes>
                <Route path="/login" element={<Login />} />
                <Route path="/" element={<PrivateRoute><Dashboard /></PrivateRoute>} />
                <Route path="/sales/orders" element={<PrivateRoute allowedRoles={['sales', 'admin']}><SalesOrders /></PrivateRoute>} />
                <Route path="/sales/orders/new" element={<PrivateRoute allowedRoles={['sales', 'admin']}><SalesOrderForm /></PrivateRoute>} />
                <Route path="/sales/orders/:id/edit" element={<PrivateRoute allowedRoles={['sales', 'admin']}><SalesOrderForm /></PrivateRoute>} />
                <Route path="/factory/orders" element={<PrivateRoute allowedRoles={['factory', 'admin']}><FactoryOrders /></PrivateRoute>} />
                <Route path="/factory/orders/:id/edit" element={<PrivateRoute allowedRoles={['factory', 'admin']}><FactoryOrderForm /></PrivateRoute>} />
                <Route path="/admin/dashboard" element={<PrivateRoute allowedRoles={['admin']}><AdminDashboard /></PrivateRoute>} />
                <Route path="/admin/users" element={<PrivateRoute allowedRoles={['admin']}><UserManagement /></PrivateRoute>} />
                <Route path="/admin/settings" element={<PrivateRoute allowedRoles={['admin']}><Settings /></PrivateRoute>} />
                <Route path="/admin/audit-logs" element={<PrivateRoute allowedRoles={['admin']}><AuditLogs /></PrivateRoute>} />
                <Route path="*" element={<Navigate to="/" />} />
              </Routes>
            </div>
          </Router>
        </AuthProvider>
      </QueryClientProvider>
    </ConfigProvider>
  );
}

export default App;
