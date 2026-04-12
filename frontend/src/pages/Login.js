import React, { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Form, Input, Button, Card, Alert, Divider, Typography } from 'antd';
import { UserOutlined, LockOutlined, LoginOutlined } from '@ant-design/icons';
import { useAuth } from '../contexts/AuthContext';

const { Title, Text } = Typography;

const Login = () => {
  const [loading, setLoading] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [errorMsg, setErrorMsg] = useState('');
  const { login, user, isAuthenticated, loading: authLoading } = useAuth();
  const navigate = useNavigate();
  const [form] = Form.useForm();

  useEffect(() => {
    if (!authLoading && isAuthenticated && user) {
      const path = user.role === 'admin' ? '/admin/dashboard'
        : user.role === 'sales' ? '/sales/orders'
        : '/factory/orders';
      navigate(path, { replace: true });
    }
  }, [authLoading, isAuthenticated, user, navigate]);

  const handleSubmit = async (values) => {
    setLoading(true);
    setErrorMsg('');
    setSuccessMsg('');
    const result = await login(values);
    setLoading(false);

    if (result.success) {
      setSuccessMsg('Login successful! Redirecting...');
    } else {
      setErrorMsg(result.error || 'Login failed. Please check your credentials.');
    }
  };

  if (!authLoading && isAuthenticated && user) {
    const path = user.role === 'admin' ? '/admin/dashboard'
      : user.role === 'sales' ? '/sales/orders'
      : '/factory/orders';
    return <Navigate to={path} replace />;
  }

  return (
    <div style={{
      minHeight: '100vh',
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '20px',
    }}>
      <Card
        style={{
          width: '100%',
          maxWidth: 420,
          borderRadius: 16,
          boxShadow: '0 20px 60px rgba(0,0,0,0.15)',
          border: 'none',
        }}
        bodyStyle={{ padding: '40px' }}
      >
        <div style={{ textAlign: 'center', marginBottom: 32 }}>
          <div style={{ fontSize: 52, marginBottom: 8 }}>🏢</div>
          <Title level={2} style={{ margin: 0, color: '#333' }}>WorkFlow</Title>
          <Text type="secondary">Business Management System</Text>
        </div>

        {successMsg && (
          <Alert message={successMsg} type="success" showIcon style={{ marginBottom: 16 }} />
        )}
        {errorMsg && (
          <Alert message={errorMsg} type="error" showIcon style={{ marginBottom: 16 }} />
        )}

        <Form
          form={form}
          name="login"
          layout="vertical"
          onFinish={handleSubmit}
          size="large"
        >
          <Form.Item
            name="email"
            label="Email Address"
            rules={[
              { required: true, message: 'Please enter your email' },
              { type: 'email', message: 'Please enter a valid email' },
            ]}
          >
            <Input
              prefix={<UserOutlined style={{ color: '#999' }} />}
              placeholder="Enter your email"
            />
          </Form.Item>

          <Form.Item
            name="password"
            label="Password"
            rules={[{ required: true, message: 'Please enter your password' }]}
          >
            <Input.Password
              prefix={<LockOutlined style={{ color: '#999' }} />}
              placeholder="Enter your password"
            />
          </Form.Item>

          <Form.Item style={{ marginBottom: 16 }}>
            <Button
              type="primary"
              htmlType="submit"
              loading={loading}
              block
              icon={<LoginOutlined />}
              style={{
                height: 48,
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                border: 'none',
                borderRadius: 8,
                fontSize: 16,
                fontWeight: 600,
              }}
            >
              Login to System
            </Button>
          </Form.Item>
        </Form>

        <Divider style={{ color: '#999', fontSize: 12 }}>تنبيه أمني</Divider>

        <Alert
          type="info"
          showIcon
          message="تم تعطيل عرض أي حسابات افتراضية حفاظاً على أمان النظام."
          style={{ marginBottom: 8 }}
        />

        <div style={{ textAlign: 'center', marginTop: 24 }}>
          <Text type="secondary" style={{ fontSize: 11 }}>
            © 2024 WorkFlow Management System | dayancosys.com
          </Text>
        </div>
      </Card>
    </div>
  );
};

export default Login;
