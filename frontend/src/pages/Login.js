import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Form, Input, Button, Card, message, Divider, Typography } from 'antd';
import { UserOutlined, LockOutlined, LoginOutlined } from '@ant-design/icons';
import { useAuth } from '../contexts/AuthContext';

const { Title, Text } = Typography;

const Login = () => {
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (values) => {
    setLoading(true);
    const result = await login(values);
    setLoading(false);

    if (result.success) {
      message.success('Login successful');
      navigate('/');
    } else {
      message.error(result.error || 'Login failed. Please check your credentials.');
    }
  };

  const fillDemo = (email, password) => {
    // Handled via button click fill
    document.querySelector('input[id="email"]').value = email;
    document.querySelector('input[id="password"]').value = password;
  };

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

        <Form
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

        <Divider style={{ color: '#999', fontSize: 12 }}>Demo Accounts</Divider>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {[
            { role: 'Admin', email: 'admin@workflow.com', pass: 'admin123', color: '#ff4d4f' },
            { role: 'Sales', email: 'sales@workflow.com', pass: 'sales123', color: '#1890ff' },
            { role: 'Factory', email: 'factory@workflow.com', pass: 'factory123', color: '#52c41a' },
          ].map(({ role, email, pass, color }) => (
            <div
              key={role}
              style={{
                background: '#f8f9fa',
                border: `1px solid ${color}33`,
                borderRadius: 8,
                padding: '8px 12px',
                cursor: 'pointer',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                transition: 'all 0.2s',
              }}
              onClick={() => {
                const form = document.querySelector('form');
                if (form) {
                  const emailInput = form.querySelector('input[type="email"], input:not([type])');
                  const passwordInput = form.querySelector('input[type="password"]');
                  if (emailInput) emailInput.value = email;
                  if (passwordInput) passwordInput.value = pass;
                }
                // Use antd form approach
              }}
              onMouseEnter={(e) => { e.currentTarget.style.background = `${color}10`; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = '#f8f9fa'; }}
            >
              <span>
                <span style={{
                  background: color,
                  color: 'white',
                  padding: '2px 8px',
                  borderRadius: 4,
                  fontSize: 11,
                  fontWeight: 600,
                  marginRight: 8,
                }}>{role}</span>
                <Text style={{ fontSize: 12 }}>{email}</Text>
              </span>
              <Text type="secondary" style={{ fontSize: 11 }}>{pass}</Text>
            </div>
          ))}
        </div>

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
