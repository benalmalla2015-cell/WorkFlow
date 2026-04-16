import React, { useState } from 'react';
import { Layout, Menu, Avatar, Dropdown, Button, Typography, Badge } from 'antd';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  DashboardOutlined,
  ShoppingCartOutlined,
  ToolOutlined,
  UserOutlined,
  SettingOutlined,
  AuditOutlined,
  LogoutOutlined,
  MenuFoldOutlined,
  MenuUnfoldOutlined,
  BellOutlined,
} from '@ant-design/icons';
import { useAuth } from '../contexts/AuthContext';

const { Header, Sider, Content } = Layout;
const { Text } = Typography;

const AppLayout = ({ children }) => {
  const [collapsed, setCollapsed] = useState(false);
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const salesMenuItems = [
    { key: '/sales/orders', icon: <ShoppingCartOutlined />, label: 'My Orders' },
  ];

  const factoryMenuItems = [
    { key: '/factory/orders', icon: <ToolOutlined />, label: 'Factory Orders' },
  ];

  const adminMenuItems = [
    { key: '/admin/dashboard', icon: <DashboardOutlined />, label: 'Dashboard' },
    { key: '/admin/users', icon: <UserOutlined />, label: 'User Management' },
    { key: '/admin/settings', icon: <SettingOutlined />, label: 'Settings' },
    { key: '/admin/audit-logs', icon: <AuditOutlined />, label: 'Audit Logs' },
    { key: '/sales/orders', icon: <ShoppingCartOutlined />, label: 'All Orders' },
  ];

  const menuItems = user?.role === 'admin'
    ? adminMenuItems
    : user?.role === 'factory'
    ? factoryMenuItems
    : salesMenuItems;

  const roleColors = { admin: '#ff4d4f', sales: '#1890ff', factory: '#52c41a' };
  const roleColor = roleColors[user?.role] || '#666';

  const userMenuItems = [
    { key: 'profile', icon: <UserOutlined />, label: user?.name },
    { key: 'divider', type: 'divider' },
    { key: 'logout', icon: <LogoutOutlined />, label: 'Logout', danger: true },
  ];

  const handleUserMenuClick = ({ key }) => {
    if (key === 'logout') {
      logout();
      navigate('/login');
    }
  };

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider
        trigger={null}
        collapsible
        collapsed={collapsed}
        style={{
          background: 'linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
          boxShadow: '2px 0 8px rgba(0,0,0,0.15)',
        }}
      >
        <div style={{
          height: 64,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          borderBottom: '1px solid rgba(255,255,255,0.1)',
          padding: '0 16px',
        }}>
          {!collapsed ? (
            <div style={{ textAlign: 'center' }}>
              <div style={{ fontSize: 24 }}>🏢</div>
              <Text style={{ color: 'white', fontWeight: 700, fontSize: 14 }}>WorkFlow</Text>
            </div>
          ) : (
            <div style={{ fontSize: 24 }}>🏢</div>
          )}
        </div>

        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={[location.pathname]}
          onClick={({ key }) => navigate(key)}
          items={menuItems}
          style={{ background: 'transparent', border: 'none', marginTop: 8 }}
        />
      </Sider>

      <Layout>
        <Header style={{
          background: 'white',
          padding: '0 24px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          boxShadow: '0 1px 4px rgba(0,0,0,0.08)',
          position: 'sticky',
          top: 0,
          zIndex: 100,
        }}>
          <Button
            type="text"
            icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
            onClick={() => setCollapsed(!collapsed)}
            style={{ fontSize: 16, width: 40, height: 40 }}
          />

          <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
            <Badge count={0} showZero={false}>
              <Button type="text" icon={<BellOutlined />} style={{ fontSize: 18 }} />
            </Badge>

            <Dropdown
              menu={{ items: userMenuItems, onClick: handleUserMenuClick }}
              placement="bottomRight"
              trigger={['click']}
            >
              <div style={{ cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8 }}>
                <Avatar style={{ background: roleColor, fontWeight: 700 }}>
                  {user?.name?.charAt(0)?.toUpperCase()}
                </Avatar>
                <div style={{ lineHeight: 1.3 }}>
                  <div style={{ fontWeight: 600, fontSize: 13 }}>{user?.name}</div>
                  <div style={{ fontSize: 11, color: roleColor, fontWeight: 500 }}>
                    {user?.role?.toUpperCase()}
                  </div>
                </div>
              </div>
            </Dropdown>
          </div>
        </Header>

        <Content style={{
          margin: 24,
          padding: 24,
          background: '#f0f2f5',
          minHeight: 'calc(100vh - 112px)',
          borderRadius: 8,
        }}>
          {children}
        </Content>
      </Layout>
    </Layout>
  );
};

export default AppLayout;
